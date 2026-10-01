<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Unified request pattern: every staff request (leave, overtime, travel,
 * fuel, purchase, voucher) gets a row in `requests` for the shared approval
 * workflow (status, tracking) while its own table stores domain fields.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['Leave', 'Overtime', 'Timesheet', 'Travel', 'Fuel', 'Purchase', 'Voucher']);
            $table->enum('status', ['Draft', 'Pending', 'In Review', 'Approved', 'Rejected', 'Returned', 'Completed', 'Cancelled'])
                ->default('Pending');
            $table->foreignId('current_handler_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('related_id')->nullable();   // polymorphic-lite FK to domain table
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['type', 'status']);
        });

        Schema::create('request_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('action');      // submitted, reviewed, approved, rejected, returned, completed
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('leaves', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['Annual', 'Sick', 'Unpaid', 'Maternity', 'Other']);
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('days');
            $table->text('reason')->nullable();
            $table->string('file_path')->nullable();
            $table->timestamps();
        });

        Schema::create('overtimes', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->decimal('hours', 4, 2);
            $table->text('reason')->nullable();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('timesheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->json('entries');       // [{date, timeIn, timeOut, hours}]
            $table->decimal('total_hours', 6, 2)->default(0);
            $table->unique(['user_id', 'month', 'year']);
            $table->timestamps();
        });

        Schema::create('travels', function (Blueprint $table) {
            $table->id();
            $table->string('purpose');
            $table->string('destination');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('transport');   // Car, Motorbike, Bus, Airplane, Other
            $table->string('accommodation')->nullable();
            $table->json('costs');         // {transport, accommodation, meals, other}
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->string('file_path')->nullable();
            $table->timestamps();
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('fuel_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('mileage');
            $table->decimal('liters', 6, 2);
            $table->decimal('cost', 10, 2);
            $table->timestamps();
        });

        Schema::create('fuel_logsheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->json('records');       // [{date, mileage, liters, cost}]
            $table->decimal('total_liters', 8, 2)->default(0);
            $table->decimal('total_cost', 10, 2)->default(0);
            $table->unique(['user_id', 'vehicle_id', 'month', 'year']);
            $table->timestamps();
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('purpose');
            $table->string('department');
            $table->date('required_date');
            $table->json('items');         // [{name, qty, unitCost}]
            $table->decimal('total', 12, 2)->default(0);
            $table->text('justification')->nullable();
            $table->string('file_path')->nullable();
            $table->timestamps();
        });

        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_no')->unique();
            $table->date('date');
            $table->string('expense_type');
            $table->text('description')->nullable();
            $table->json('lines');         // [{description, amount}]
            $table->decimal('total', 12, 2)->default(0);
            $table->enum('payment_method', ['Cash', 'Bank Transfer']);
            $table->string('file_path')->nullable();
            $table->enum('payment_status', ['Unpaid', 'Paid'])->default('Unpaid');
            $table->timestamps();
        });

        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['Annual', 'Sick', 'Other']);
            $table->unsignedSmallInteger('entitled')->default(0);
            $table->unsignedSmallInteger('used')->default(0);
            $table->unique(['user_id', 'type']);
            $table->timestamps();
        });

        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('icon')->default('doc');
            $table->text('message');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('vouchers');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('fuel_logsheets');
        Schema::dropIfExists('fuel_logs');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('travels');
        Schema::dropIfExists('timesheets');
        Schema::dropIfExists('overtimes');
        Schema::dropIfExists('leaves');
        Schema::dropIfExists('request_actions');
        Schema::dropIfExists('requests');
    }
};
