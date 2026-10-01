<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Email invitations: admin invites a staff member by email, the staff
 * opens a secure magic link (72h), is logged in and completes their
 * profile. Roles/departments are assigned at invite time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('token', 64)->unique();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('require_profile_setup')->default(true);
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->string('status')->default('Pending'); // Pending / Accepted / Revoked
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_invitations');
    }
};
