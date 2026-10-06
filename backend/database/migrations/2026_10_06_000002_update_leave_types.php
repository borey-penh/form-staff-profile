<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * New leave type list for the Leave Application form:
 * Annual, Sick Leave, Special, Compassionate, Time in Lieu, Paternity, Unpaid, Study.
 * Legacy values (Sick, Maternity, Other) stay allowed in the database so old
 * rows remain readable and editable, but they are no longer offered in the UI.
 */
return new class extends Migration
{
    public const LEAVE_TYPES = [
        'Annual', 'Sick Leave', 'Special', 'Compassionate', 'Time in Lieu',
        'Paternity', 'Unpaid', 'Study',
        // legacy values kept for existing rows
        'Sick', 'Maternity', 'Other',
    ];

    public function up(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            $table->enum('type', self::LEAVE_TYPES)->change();
        });

        Schema::table('leave_balances', function (Blueprint $table) {
            $table->enum('type', self::LEAVE_TYPES)->change();
        });
    }

    public function down(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            $table->enum('type', ['Annual', 'Sick', 'Unpaid', 'Maternity', 'Other'])->change();
        });

        Schema::table('leave_balances', function (Blueprint $table) {
            $table->enum('type', ['Annual', 'Sick', 'Other'])->change();
        });
    }
};
