<?php

namespace App\Services;

use App\Models\AdminNotification;
use App\Models\EmployeeDeviceToken;
use App\Models\EmployeeNotificationDelivery;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class FirebaseCloudMessagingService
{
    private const OAUTH_SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';
    private const OAUTH_TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const SEND_CONCURRENCY = 12;

    private bool $serviceAccountLoaded = false;
    private ?array $serviceAccount = null;

    public function sendAdminNotification(AdminNotification $notification): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        $deliveries = EmployeeNotificationDelivery::query()
            ->where('admin_notification_id', $notification->id)
            ->get(['id', 'employee_id']);

        if ($deliveries->isEmpty()) {
            return;
        }

        $deliveriesByEmployeeId = $deliveries->keyBy(
            fn (EmployeeNotificationDelivery $delivery): int => (int) $delivery->employee_id
        );
        $deviceTokens = EmployeeDeviceToken::query()
            ->whereIn('employee_id', $deliveriesByEmployeeId->keys()->all())
            ->get();

        if ($deviceTokens->isEmpty()) {
            return;
        }

        $dispatches = [];
        foreach ($deviceTokens as $deviceToken) {
            $delivery = $deliveriesByEmployeeId->get((int) $deviceToken->employee_id);

            if (! $delivery instanceof EmployeeNotificationDelivery) {
                continue;
            }

            $dispatches[] = [
                'deviceToken' => $deviceToken,
                'delivery' => $delivery,
            ];
        }

        if ($dispatches === []) {
            return;
        }

        $this->sendToDeviceTokens($dispatches, $notification);
    }

    private function sendToDeviceTokens(array $dispatches, AdminNotification $notification): void
    {
        $projectId = $this->projectId();
        $accessToken = $this->accessToken();

        if ($projectId === '' || $accessToken === null) {
            return;
        }

        $client = $this->httpClient();
        $requests = function () use ($dispatches, $notification, $client, $projectId, $accessToken) {
            foreach ($dispatches as $dispatch) {
                yield function () use ($dispatch, $notification, $client, $projectId, $accessToken) {
                    /** @var EmployeeDeviceToken $deviceToken */
                    $deviceToken = $dispatch['deviceToken'];

                    return $client->postAsync(
                        'https://fcm.googleapis.com/v1/projects/'.$projectId.'/messages:send',
                        [
                            'headers' => [
                                'Authorization' => 'Bearer '.$accessToken,
                                'Content-Type' => 'application/json',
                                'Accept' => 'application/json',
                            ],
                            'http_errors' => false,
                            'json' => [
                                'message' => $this->messagePayload($deviceToken, $notification, $dispatch['delivery']),
                            ],
                            'timeout' => 15,
                        ]
                    );
                };
            }
        };

        $pool = new Pool($client, $requests(), [
            'concurrency' => self::SEND_CONCURRENCY,
            'fulfilled' => function (ResponseInterface $response, int $index) use ($dispatches, $notification): void {
                $dispatch = $dispatches[$index] ?? null;

                if (! is_array($dispatch)) {
                    return;
                }

                $this->handleSendResponse(
                    $dispatch['deviceToken'],
                    $notification,
                    $dispatch['delivery'],
                    $response
                );
            },
            'rejected' => function ($reason, int $index) use ($dispatches, $notification): void {
                $dispatch = $dispatches[$index] ?? null;

                if (! is_array($dispatch)) {
                    return;
                }

                $this->handleSendFailure(
                    $dispatch['deviceToken'],
                    $notification,
                    $dispatch['delivery'],
                    $reason
                );
            },
        ]);

        $pool->promise()->wait();
    }

    private function messagePayload(
        EmployeeDeviceToken $deviceToken,
        AdminNotification $notification,
        EmployeeNotificationDelivery $delivery
    ): array {
        $title = trim((string) $notification->title) !== '' ? trim((string) $notification->title) : 'Attica Pagar';
        $body = trim((string) $notification->body);
        $platform = strtolower(trim((string) $deviceToken->platform));

        $message = [
            'token' => $deviceToken->token,
            'data' => [
                'type' => 'admin_notification',
                'deliveryId' => (string) $delivery->id,
                'notificationId' => (string) $notification->id,
                'title' => $title,
                'body' => $body,
                'sentAt' => optional($notification->sent_at)->toIso8601String() ?? now()->toIso8601String(),
            ],
        ];

        if ($platform === 'android') {
            $message['notification'] = [
                'title' => $title,
                'body' => $body,
            ];
            $message['android'] = [
                'priority' => 'high',
                'ttl' => '86400s',
                'notification' => [
                    'channel_id' => 'admin_push_notifications',
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    'sound' => 'default',
                ],
            ];
        } else {
            $message['notification'] = [
                'title' => $title,
                'body' => $body,
            ];
            $message['android'] = [
                'priority' => 'high',
                'notification' => [
                    'channel_id' => 'admin_push_notifications',
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                ],
            ];
            $message['apns'] = [
                'headers' => [
                    'apns-priority' => '10',
                ],
                'payload' => [
                    'aps' => [
                        'alert' => [
                            'title' => $title,
                            'body' => $body,
                        ],
                        'sound' => 'default',
                    ],
                ],
            ];
        }

        return $message;
    }

    private function handleSendResponse(
        EmployeeDeviceToken $deviceToken,
        AdminNotification $notification,
        EmployeeNotificationDelivery $delivery,
        ResponseInterface $response
    ): void {
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $deviceToken->forceFill(['last_used_at' => now()])->save();

            return;
        }

        $payload = json_decode((string) $response->getBody(), true);
        if ($this->shouldDeleteDeviceToken(is_array($payload) ? $payload : [])) {
            $deviceToken->delete();

            return;
        }

        Log::warning('Failed to send FCM notification.', [
            'notification_id' => $notification->id,
            'delivery_id' => $delivery->id,
            'employee_id' => $deviceToken->employee_id,
            'status' => $response->getStatusCode(),
            'response' => is_array($payload) ? $payload : (string) $response->getBody(),
        ]);
    }

    private function handleSendFailure(
        EmployeeDeviceToken $deviceToken,
        AdminNotification $notification,
        EmployeeNotificationDelivery $delivery,
        $reason
    ): void {
        Log::warning('FCM transport request failed.', [
            'notification_id' => $notification->id,
            'delivery_id' => $delivery->id,
            'employee_id' => $deviceToken->employee_id,
            'reason' => $reason instanceof Throwable ? $reason->getMessage() : (string) $reason,
        ]);
    }

    private function isConfigured(): bool
    {
        return $this->projectId() !== '' && $this->loadServiceAccount() !== null;
    }

    private function accessToken(): ?string
    {
        $serviceAccount = $this->loadServiceAccount();

        if (! is_array($serviceAccount)) {
            return null;
        }

        $cacheKey = 'firebase_fcm_access_token_'.md5(
            ($serviceAccount['client_email'] ?? '').'|'.($serviceAccount['project_id'] ?? '')
        );

        return Cache::remember($cacheKey, now()->addMinutes(50), function () use ($serviceAccount): ?string {
            $assertion = $this->createJwtAssertion($serviceAccount);

            if ($assertion === null) {
                return null;
            }

            $response = $this->httpClient()->post(self::OAUTH_TOKEN_URL, [
                'form_params' => [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $assertion,
                ],
                'http_errors' => false,
                'headers' => [
                    'Accept' => 'application/json',
                ],
                'timeout' => 15,
            ]);

            $payload = json_decode((string) $response->getBody(), true);
            if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                $token = trim((string) ($payload['access_token'] ?? ''));

                return $token !== '' ? $token : null;
            }

            Log::warning('Unable to fetch Firebase OAuth access token.', [
                'status' => $response->getStatusCode(),
                'response' => is_array($payload) ? $payload : (string) $response->getBody(),
            ]);

            return null;
        });
    }

    private function createJwtAssertion(array $serviceAccount): ?string
    {
        $privateKey = trim((string) ($serviceAccount['private_key'] ?? ''));
        $clientEmail = trim((string) ($serviceAccount['client_email'] ?? ''));

        if ($privateKey === '' || $clientEmail === '') {
            return null;
        }

        $issuedAt = time();
        $expiresAt = $issuedAt + 3600;
        $header = $this->base64UrlEncode(json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
        ], JSON_UNESCAPED_SLASHES));
        $payload = $this->base64UrlEncode(json_encode([
            'iss' => $clientEmail,
            'scope' => self::OAUTH_SCOPE,
            'aud' => self::OAUTH_TOKEN_URL,
            'iat' => $issuedAt,
            'exp' => $expiresAt,
        ], JSON_UNESCAPED_SLASHES));

        if ($header === null || $payload === null) {
            return null;
        }

        $signingInput = $header.'.'.$payload;
        $signature = '';
        $signed = openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        if (! $signed) {
            Log::warning('Unable to sign Firebase OAuth JWT assertion.');

            return null;
        }

        return $signingInput.'.'.$this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function loadServiceAccount(): ?array
    {
        if ($this->serviceAccountLoaded) {
            return $this->serviceAccount;
        }

        $this->serviceAccountLoaded = true;
        $path = trim((string) config('services.fcm.service_account'));

        if ($path === '' || ! is_file($path)) {
            Log::warning('Firebase service account file is missing.', ['path' => $path]);

            return $this->serviceAccount = null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (! is_array($decoded)) {
            Log::warning('Firebase service account file could not be decoded.', ['path' => $path]);

            return $this->serviceAccount = null;
        }

        return $this->serviceAccount = $decoded;
    }

    private function projectId(): string
    {
        $configuredProjectId = trim((string) config('services.fcm.project_id'));
        if ($configuredProjectId !== '') {
            return $configuredProjectId;
        }

        $serviceAccount = $this->loadServiceAccount();

        return trim((string) ($serviceAccount['project_id'] ?? ''));
    }

    private function shouldDeleteDeviceToken(array $payload): bool
    {
        $error = $payload['error'] ?? null;
        $status = strtoupper(trim((string) (is_array($error) ? ($error['status'] ?? '') : '')));
        $message = strtolower(trim((string) (is_array($error) ? ($error['message'] ?? '') : '')));

        if (in_array($status, ['UNREGISTERED', 'NOT_FOUND'], true)) {
            return true;
        }

        return str_contains($message, 'registration token is not a valid fcm registration token')
            || str_contains($message, 'requested entity was not found')
            || str_contains($message, 'unregistered');
    }

    private function httpClient(): Client
    {
        return new Client();
    }
}
