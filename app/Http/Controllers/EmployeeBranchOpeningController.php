<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use App\Services\EmployeeBranchOpeningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeBranchOpeningController extends Controller
{
    public function __construct(
        private readonly EmployeeBranchOpeningService $employeeBranchOpeningService
    ) {
    }

    public function markOpened(Request $request): JsonResponse
    {
        $employee = $request->user();

        abort_unless($employee instanceof Employee, 401);

        return response()->json(
            $this->employeeBranchOpeningService->markOpened(
                $this->resolveBranchId($employee, $request),
                $employee
            )
        );
    }

    public function markClosed(Request $request): JsonResponse
    {
        $employee = $request->user();

        abort_unless($employee instanceof Employee, 401);

        return response()->json(
            $this->employeeBranchOpeningService->markClosed(
                $this->resolveBranchId($employee, $request),
                $employee
            )
        );
    }

    private function resolveBranchId(Employee $employee, Request $request): string
    {
        $branchId = $this->branchIdFromToken($request);

        if ($branchId !== '') {
            return $branchId;
        }

        $attendance = Attendance::query()
            ->where('empId', $this->clean($employee->empId))
            ->latest('id')
            ->first(['check_in_branch_id', 'check_out_branch_id']);

        if (! $attendance) {
            return '';
        }

        $checkOutBranchId = $this->clean($attendance->check_out_branch_id);

        return $checkOutBranchId !== ''
            ? $checkOutBranchId
            : $this->clean($attendance->check_in_branch_id);
    }

    private function branchIdFromToken(Request $request): string
    {
        $token = $request->user()?->currentAccessToken();
        $abilities = is_array($token?->abilities) ? $token->abilities : [];

        foreach ($abilities as $ability) {
            if (str_starts_with((string) $ability, 'branch:')) {
                return $this->clean(substr((string) $ability, 7));
            }
        }

        return '';
    }

    private function clean($value): string
    {
        return trim((string) $value);
    }
}
