<?php

namespace App\Services;

use App\Models\AdminNotification;
use App\Models\Employee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EmployeeNotificationDispatchService
{
    public function __construct(
        private readonly FirebaseCloudMessagingService $firebaseMessagingService
    ) {
    }

    public function sendToEmployees(
        Collection $employees,
        string $title,
        string $body,
        ?int $sentBy = null,
        string $audienceType = 'employee',
        ?string $audienceValue = null
    ): ?AdminNotification {
        $targets = $employees
            ->filter(fn ($employee): bool => $employee instanceof Employee && (int) $employee->id > 0)
            ->unique(fn (Employee $employee): int => (int) $employee->id)
            ->values();

        if ($targets->isEmpty()) {
            return null;
        }

        $notification = DB::transaction(function () use ($targets, $title, $body, $sentBy, $audienceType, $audienceValue): AdminNotification {
            $notification = AdminNotification::query()->create([
                'audience_type' => $audienceType,
                'audience_value' => $audienceValue !== null
                    ? trim($audienceValue)
                    : ($audienceType === 'all' ? null : $this->audienceValue($targets)),
                'title' => trim($title) !== '' ? trim($title) : 'Attica Pagar',
                'body' => trim($body),
                'sent_by' => $sentBy,
                'sent_at' => Carbon::now(config('app.timezone', 'Asia/Kolkata')),
            ]);

            $now = now();
            $deliveries = $targets
                ->map(fn (Employee $employee): array => [
                    'admin_notification_id' => $notification->id,
                    'employee_id' => $employee->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all();

            DB::table('employee_notification_deliveries')->insertOrIgnore($deliveries);

            return $notification;
        });

        $this->firebaseMessagingService->sendAdminNotification($notification);

        return $notification;
    }

    public function sendToEmployee(Employee $employee, string $title, string $body, ?int $sentBy = null): ?AdminNotification
    {
        return $this->sendToEmployees(collect([$employee]), $title, $body, $sentBy);
    }

    private function audienceValue(Collection $employees): string
    {
        if ($employees->count() === 1) {
            /** @var Employee|null $employee */
            $employee = $employees->first();

            if ($employee instanceof Employee) {
                return trim((string) $employee->empId).' - '.trim((string) $employee->name);
            }
        }

        return 'Selected employees ('.$employees->count().')';
    }
}
