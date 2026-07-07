<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;

class MaintenanceController extends Controller
{
    public function test(): string
    {
        return 'Abhiram';
    }

    public function clearCache(): string
    {
        Artisan::call('cache:clear');
        Artisan::call('config:clear');
        Artisan::call('view:clear');

        return 'Cache is cleared';
    }

    public function migrate(): string
    {
        Artisan::call('migrate');

        return 'Migrate Completed!';
    }

    public function optimize(): string
    {
        Artisan::call('optimize');

        return 'optimized!';
    }

    public function optimizeClear(): string
    {
        Artisan::call('optimize:clear');

        return 'optimized!';
    }

    public function resolveMayPfEmployeeIds(): Response
    {
        Artisan::call('payroll:resolve-may-pf-employee-ids');

        return response(trim(Artisan::output()), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
