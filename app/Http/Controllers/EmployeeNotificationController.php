<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeDeviceToken;
use App\Models\EmployeeNotificationDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $employee = $request->user();

        abort_unless($employee instanceof Employee, 401);

        $deliveries = EmployeeNotificationDelivery::query()
            ->with('notification')
            ->where('employee_id', $employee->id)
            ->latest('id')
            ->limit(100)
            ->get();

        return response()->json([
            'notifications' => $deliveries
                ->filter(fn (EmployeeNotificationDelivery $delivery): bool => $delivery->notification !== null)
                ->map(fn (EmployeeNotificationDelivery $delivery): array => $this->notificationPayload($delivery))
                ->values(),
        ]);
    }

    public function pending(Request $request): JsonResponse
    {
        $employee = $request->user();

        abort_unless($employee instanceof Employee, 401);

        $deliveries = EmployeeNotificationDelivery::query()
            ->with('notification')
            ->where('employee_id', $employee->id)
            ->whereNull('read_at')
            ->latest('id')
            ->limit(20)
            ->get();

        return response()->json([
            'notifications' => $deliveries
                ->filter(fn (EmployeeNotificationDelivery $delivery): bool => $delivery->notification !== null)
                ->map(fn (EmployeeNotificationDelivery $delivery): array => $this->notificationPayload($delivery))
                ->values(),
        ]);
    }

    public function markRead(Request $request): JsonResponse
    {
        $employee = $request->user();

        abort_unless($employee instanceof Employee, 401);

        $data = $request->validate([
            'deliveryIds' => ['required', 'array', 'min:1'],
            'deliveryIds.*' => ['integer'],
        ]);

        EmployeeNotificationDelivery::query()
            ->where('employee_id', $employee->id)
            ->whereIn('id', $data['deliveryIds'])
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'message' => 'Notifications marked as read.',
        ]);
    }

    public function registerDeviceToken(Request $request): JsonResponse
    {
        $employee = $request->user();

        abort_unless($employee instanceof Employee, 401);

        $data = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
            'platform' => ['nullable', 'string', 'in:android,ios,macos,web'],
        ]);

        $token = $this->clean($data['token'] ?? '');
        if ($token === '') {
            return response()->json([
                'message' => 'Device token is required.',
            ], 422);
        }

        EmployeeDeviceToken::query()->updateOrCreate(
            ['token_hash' => EmployeeDeviceToken::hashToken($token)],
            [
                'employee_id' => $employee->id,
                'token' => $token,
                'platform' => $this->clean($data['platform'] ?? ''),
                'last_used_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'Device token registered.',
        ]);
    }

    public function removeDeviceToken(Request $request): JsonResponse
    {
        $employee = $request->user();

        abort_unless($employee instanceof Employee, 401);

        $data = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
        ]);

        $token = $this->clean($data['token'] ?? '');
        if ($token === '') {
            return response()->json([
                'message' => 'Device token is required.',
            ], 422);
        }

        EmployeeDeviceToken::query()
            ->where('employee_id', $employee->id)
            ->where('token_hash', EmployeeDeviceToken::hashToken($token))
            ->delete();

        return response()->json([
            'message' => 'Device token removed.',
        ]);
    }

    private function notificationPayload(EmployeeNotificationDelivery $delivery): array
    {
        return [
            'deliveryId' => $delivery->id,
            'notificationId' => $delivery->admin_notification_id,
            'title' => $delivery->notification->title,
            'body' => $delivery->notification->body,
            'sentAt' => optional($delivery->notification->sent_at)->toIso8601String(),
            'readAt' => optional($delivery->read_at)->toIso8601String(),
        ];
    }

    private function clean($value): string
    {
        return trim((string) $value);
    }
}
