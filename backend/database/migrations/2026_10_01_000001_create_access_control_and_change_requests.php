<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * DB-driven access control:
 *  - roles + permissions with role_permission pivot
 *  - users get role_id (the legacy enum `role` column is kept for
 *    backwards compatibility and kept in sync) + status
 *  - users can receive additional permissions on top of their role
 *    (user_permission pivot)
 *  - profile_change_requests: staff suggest edits to locked profile
 *    fields; an admin approves/rejects and approved values apply
 *    automatically.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('label');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();   // e.g. staff.view
            $table->string('group')->index();   // PERSONNEL / COMPLIANCE / ...
            $table->string('label');
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('permission_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'permission_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('role')->constrained('roles')->nullOnDelete();
            $table->enum('status', ['Pending Invitation', 'Active', 'Suspended', 'Inactive'])
                ->default('Active')->after('role_id');
        });

        // Keep the legacy enum column in sync with the roles table.
        // Sync happens via events too, but backfill existing rows first.
        Schema::create('profile_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('field');                        // camelCase key, e.g. phone
            $table->text('current_value')->nullable();
            $table->text('requested_value')->nullable();
            $table->string('reason')->nullable();
            $table->enum('status', ['Pending', 'Approved', 'Rejected'])->default('Pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        /* ---------------- Seed default roles & permissions ---------------- */

        $now = now();

        $permissions = [
            // group, name, label
            ['Personnel', 'staff.view', 'View staff'],
            ['Personnel', 'staff.view-all', 'View all staff'],
            ['Personnel', 'staff.create', 'Create staff'],
            ['Personnel', 'staff.edit', 'Edit all staff'],
            ['Personnel', 'staff.delete', 'Delete staff'],
            ['Compliance', 'compliance.view-team', 'View team compliance'],
            ['Compliance', 'compliance.manage', 'Manage compliance'],
            ['Compliance', 'compliance.review', 'Review compliance'],
            ['Contracts', 'contracts.view-team', 'View team contracts'],
            ['Contracts', 'contracts.create', 'Create contract'],
            ['Contracts', 'contracts.edit', 'Edit contract'],
            ['Training', 'training.view-team', 'View team training'],
            ['Training', 'training.assign', 'Assign training'],
            ['Training', 'training.create', 'Create training'],
            ['Time Management', 'time.view-team', 'View team timesheets'],
            ['Time Management', 'time.approve-leave', 'Approve leave'],
            ['Time Management', 'time.approve-overtime', 'Approve overtime'],
            ['Requests', 'requests.view-team', 'View team requests'],
            ['Requests', 'requests.approve', 'Approve requests'],
            ['Finance', 'finance.view-all', 'View all finance'],
            ['Finance', 'finance.view-vouchers', 'View vouchers'],
            ['Finance', 'finance.manage', 'Manage finance'],
            ['Reports', 'reports.view-team', 'View team reports'],
            ['Reports', 'reports.view-org', 'View organization reports'],
            ['Access', 'roles.manage', 'Manage roles & permissions'],
            ['Access', 'users.manage', 'Manage users'],
            ['Access', 'audit.view', 'View audit log'],
            ['Access', 'profile.change-requests.review', 'Review profile change requests'],
        ];

        foreach ($permissions as [$group, $name, $label]) {
            DB::table('permissions')->insert([
                'name' => $name, 'group' => $group, 'label' => $label,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $all = collect($permissions)->pluck(1);
        $adminPermissions = $all; // admin gets everything

        // Staff see and manage only their own records — no team visibility.
        $staffPermissions = [];

        // Managers oversee a team: visibility + approvals, no org-wide admin.
        $managerPermissions = [
            'staff.view',
            'compliance.view-team', 'compliance.review',
            'contracts.view-team',
            'training.view-team', 'training.assign',
            'time.view-team', 'time.approve-leave', 'time.approve-overtime',
            'requests.view-team', 'requests.approve',
            'finance.view-vouchers',
            'reports.view-team',
        ];

        $roleRows = [
            ['Admin', 'Admin / HR', 'Full access to the whole portal.'],
            ['Manager', 'Manager', 'Can manage their team and approve requests.'],
            ['Staff', 'Staff', 'Standard staff access to their own records.'],
        ];

        foreach ($roleRows as [$name, $label, $description]) {
            $roleId = DB::table('roles')->insertGetId([
                'name' => $name, 'label' => $label, 'description' => $description,
                'created_at' => $now, 'updated_at' => $now,
            ]);

            $perms = match ($name) {
                'Admin' => $adminPermissions,
                'Manager' => $managerPermissions,
                default => $staffPermissions,
            };

            DB::table('permission_role')->insert(
                collect($perms)->map(fn ($p) => [
                    'role_id' => $roleId,
                    'permission_id' => DB::table('permissions')->where('name', $p)->value('id'),
                ])->all()
            );

            // Attach roles to existing users and backfill status.
            if ($name === 'Admin') {
                DB::table('users')->where('role', 'admin')->update([
                    'role_id' => $roleId, 'status' => 'Active',
                ]);
            } elseif ($name === 'Staff') {
                DB::table('users')->where('role', 'staff')->update([
                    'role_id' => $roleId, 'status' => 'Active',
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['role_id', 'status']));
        Schema::dropIfExists('profile_change_requests');
        Schema::dropIfExists('permission_user');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
