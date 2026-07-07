<?php


use Illuminate\Database\Migrations\Migration;

use Illuminate\Database\Schema\Blueprint;

use Illuminate\Support\Facades\Schema;


return new class extends Migration

{

public function up(): void

{

if (! Schema::hasTable('employee_advance_requests')) {

Schema::create('employee_advance_requests', function (Blueprint $table): void {

$table->id();

$table->unsignedInteger('employee_id')->index();

$table->string('emp_id', 100)->index();

$table->date('request_date');

$table->decimal('amount', 12, 2);

$table->text('request_note')->nullable();

$table->string('status', 30)->default('pending')->index();

$table->text('admin_note')->nullable();

$table->string('verified_by')->nullable();

$table->timestamp('verified_at')->nullable();

$table->string('rejected_by')->nullable();

$table->timestamp('rejected_at')->nullable();

$table->timestamps();

});

}

}


public function down(): void

{

Schema::dropIfExists('employee_advance_requests');

}

};