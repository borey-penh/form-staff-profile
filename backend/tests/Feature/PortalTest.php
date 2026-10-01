<?php

namespace Tests\Feature;

use App\Models\Compliance;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PortalTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Request helper that forgets cached auth guards after every call.
     * (Sanctum's RequestGuard caches the user per guard instance, and guard
     * instances persist across requests inside one test process.)
     */
    private function req(string $method, string $uri, array $data = [], ?string $token = null): TestResponse
    {
        $headers = $token ? ['Authorization' => "Bearer {$token}"] : [];

        $res = match ($method) {
            'post' => $this->postJson($uri, $data, $headers),
            'put' => $this->putJson($uri, $data, $headers),
            'delete' => $this->deleteJson($uri, $data, $headers),
            default => $this->getJson($uri, $headers),
        };

        auth()->forgetGuards();

        return $res;
    }

    private function makeUser(string $role): User
    {
        $roleModel = Role::where('name', $role === 'admin' ? 'Admin' : ucfirst($role))->first();

        return User::create([
            'staff_id' => ($role === 'admin' ? 'HR-' : 'ST-').strtoupper(substr(uniqid(), -6)),
            'first_name' => 'Test',
            'last_name' => ucfirst($role),
            'email' => $role.'.'.uniqid().'@portal.test',
            'password' => Hash::make('password'),
            'role' => $role,
            'role_id' => $roleModel?->id,
            'status' => 'Active',
        ]);
    }

    public function test_login_returns_token_and_user(): void
    {
        $user = $this->makeUser('staff');

        $res = $this->req('post', '/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $res->assertOk();
        $res->assertJsonStructure(['token', 'user' => ['id', 'staffId', 'fullName', 'role']]);
    }

    public function test_login_rejects_bad_password(): void
    {
        $user = $this->makeUser('staff');

        $this->req('post', '/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong',
        ])->assertStatus(422);
    }

    public function test_staff_can_submit_leave_and_admin_approves(): void
    {
        $staff = $this->makeUser('staff');
        $admin = $this->makeUser('admin');

        $sToken = $staff->createToken('t')->plainTextToken;
        $aToken = $admin->createToken('t')->plainTextToken;

        // Submit with staff token
        $this->req('post', '/api/requests', [
            'type' => 'Leave',
            'data' => ['type' => 'Annual', 'startDate' => '2026-11-02', 'endDate' => '2026-11-06', 'reason' => 'Test'],
        ], $sToken)->assertCreated();

        // Days auto-computed, workflow record created for the right user
        $this->assertDatabaseHas('leaves', ['days' => 5]);
        $this->assertDatabaseHas('requests', ['user_id' => $staff->id, 'type' => 'Leave', 'status' => 'Pending']);

        // Staff cannot approve
        $this->req('post', '/api/requests/1/act', ['action' => 'approve'], $sToken)->assertStatus(403);

        // Admin approves
        $this->req('post', '/api/requests/1/act', ['action' => 'approve', 'note' => 'OK'], $aToken)
            ->assertOk()
            ->assertJsonPath('status', 'Approved');

        // Trail has both actions, and the full request details
        $res = $this->req('get', '/api/requests/1', token: $sToken);
        $res->assertOk();
        $this->assertCount(2, $res->json('trail'));

        $details = collect($res->json('details'))->pluck('value', 'label');
        $this->assertSame('Annual', $details['Type']);
        $this->assertSame('02/11/2026', $details['Start Date']);
        $this->assertSame('5', $details['Days']);
    }

    public function test_compliance_signing_is_one_per_user(): void
    {
        $staff = $this->makeUser('staff');
        $token = $staff->createToken('t')->plainTextToken;
        Compliance::create(['title' => 'Code of Conduct', 'description' => 'Test policy']);

        $sig = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUg==';

        $this->req('post', '/api/compliances/sign', ['complianceId' => 1, 'signature' => $sig], $token)
            ->assertCreated();

        // Duplicate rejected
        $this->req('post', '/api/compliances/sign', ['complianceId' => 1, 'signature' => $sig], $token)
            ->assertStatus(409);
    }

    public function test_overtime_hours_are_auto_calculated(): void
    {
        $staff = $this->makeUser('staff');
        $token = $staff->createToken('t')->plainTextToken;

        $this->req('post', '/api/requests', [
            'type' => 'Overtime',
            'data' => ['date' => '2026-09-29', 'startTime' => '17:00', 'endTime' => '20:30', 'reason' => 'Project'],
        ], $token)->assertCreated();

        $this->assertDatabaseHas('overtimes', ['hours' => 3.5]);
    }

    public function test_admin_endpoints_reject_staff(): void
    {
        $staff = $this->makeUser('staff');
        $token = $staff->createToken('t')->plainTextToken;

        $this->req('get', '/api/admin/dashboard', token: $token)->assertStatus(403);
    }

    public function test_locked_profile_fields_cannot_be_edited_directly(): void
    {
        $staff = $this->makeUser('staff');
        $token = $staff->createToken('t')->plainTextToken;

        // nid is locked — update request must be ignored
        $this->req('put', '/api/profile/personal', [
            'firstName' => 'Borey', 'lastName' => 'Penh', 'dob' => '1992-08-15',
            'gender' => 'Male', 'pob' => 'Phnom Penh', 'nationality' => 'Khmer',
            'nid' => 'HACKED-999', 'marital' => 'Single', 'phone' => '012345678',
            'email' => $staff->email, 'address' => 'Phnom Penh',
        ], $token)->assertOk();

        $this->assertDatabaseHas('users', ['id' => $staff->id, 'nid' => $staff->nid]);
    }

    public function test_staff_change_request_flow_end_to_end(): void
    {
        $staff = $this->makeUser('staff');
        $admin = $this->makeUser('admin');
        $sToken = $staff->createToken('t')->plainTextToken;
        $aToken = $admin->createToken('t')->plainTextToken;

        // Staff suggests an edit to a locked field
        $this->req('post', '/api/profile/change-requests', [
            'field' => 'nid', 'requestedValue' => '010892416', 'reason' => 'Renewed ID',
        ], $sToken)->assertCreated();

        $requestId = 1;

        // Staff cannot review their own request
        $this->req('post', "/api/access/change-requests/{$requestId}/review", ['action' => 'approve'], $sToken)
            ->assertStatus(403);

        // Duplicate pending requests for the same field are blocked while one is open
        $this->req('post', '/api/profile/change-requests', [
            'field' => 'nid', 'requestedValue' => 'X', 'reason' => 'Again',
        ], $sToken)->assertStatus(422);

        // Admin reviews and approves
        $this->req('post', "/api/access/change-requests/{$requestId}/review", ['action' => 'approve'], $aToken)
            ->assertOk()
            ->assertJsonPath('status', 'Approved');

        // The value is applied to the staff record
        $this->assertDatabaseHas('users', ['id' => $staff->id, 'nid' => '010892416']);
    }

    public function test_manager_role_permission_gates_request_approval(): void
    {
        $staff = $this->makeUser('staff');
        $manager = $this->makeUser('staff');

        // Give the second user the Manager role (has requests.approve but is not admin)
        $manager->update(['role_id' => Role::where('name', 'Manager')->value('id')]);

        $sToken = $staff->createToken('t')->plainTextToken;
        $mToken = $manager->createToken('t')->plainTextToken;

        $this->req('post', '/api/requests', [
            'type' => 'Leave',
            'data' => ['type' => 'Annual', 'startDate' => '2026-11-02', 'endDate' => '2026-11-03', 'reason' => 'Test'],
        ], $sToken)->assertCreated();

        // A plain staff user without the permission is rejected
        $staffToken2 = $this->makeUser('staff')->createToken('t')->plainTextToken;
        $this->req('post', '/api/requests/1/act', ['action' => 'approve'], $staffToken2)->assertStatus(403);

        // A manager WITH requests.approve permission is allowed
        $this->req('post', '/api/requests/1/act', ['action' => 'approve'], $mToken)
            ->assertOk()
            ->assertJsonPath('status', 'Approved');
    }

    public function test_roles_endpoints_enforce_permissions(): void
    {
        $staff = $this->makeUser('staff');
        $admin = $this->makeUser('admin');

        // Staff cannot list roles
        $this->req('get', '/api/access/roles', token: $staff->createToken('t')->plainTextToken)
            ->assertStatus(403);

        // Admin can list roles and sees seeded permissions
        $res = $this->req('get', '/api/access/roles', token: $admin->createToken('t')->plainTextToken);
        $res->assertOk();
        $this->assertGreaterThanOrEqual(3, count($res->json('data')));
        $this->assertGreaterThan(0, count($res->json('allPermissions')));
    }

    public function test_suspended_user_cannot_login(): void
    {
        $user = $this->makeUser('staff');
        $user->update(['status' => 'Suspended']);

        $this->req('post', '/api/auth/login', [
            'email' => $user->email, 'password' => 'password',
        ])->assertStatus(422);
    }

    public function test_admin_can_manage_user_role_and_status(): void
    {
        $admin = $this->makeUser('admin');
        $staff = $this->makeUser('staff');
        $aToken = $admin->createToken('t')->plainTextToken;

        $managerRoleId = Role::where('name', 'Manager')->value('id');

        $this->req('put', "/api/access/users/{$staff->id}", [
            'roleId' => $managerRoleId,
            'status' => 'Suspended',
        ], $aToken)->assertOk();

        $this->assertDatabaseHas('users', ['id' => $staff->id, 'role_id' => $managerRoleId, 'status' => 'Suspended']);
    }

    public function test_leave_day_count_skips_weekends_and_holidays(): void
    {
        // Khmer New Year 2026: Apr 14–16 (Tue–Thu) are holidays.
        // Fri 17 = working, Sat 18 / Sun 19 = weekend, Mon 20 = working.
        foreach ([
            ['2026-04-14', 'Khmer New Year'],
            ['2026-04-15', 'Khmer New Year'],
            ['2026-04-16', 'Khmer New Year'],
        ] as [$d, $n]) {
            \App\Models\Holiday::create(['name' => $n, 'date' => $d, 'year' => 2026]);
        }

        $staff = $this->makeUser('staff');
        $token = $staff->createToken('t')->plainTextToken;

        // Apr 14 (holiday) → Apr 20: only Fri 17 + Mon 20 are working days
        $this->req('post', '/api/requests', [
            'type' => 'Leave',
            'data' => ['type' => 'Annual', 'startDate' => '2026-04-14', 'endDate' => '2026-04-20', 'reason' => 'Test'],
        ], $token)->assertCreated();

        $this->assertDatabaseHas('leaves', ['days' => 2]);
    }

    public function test_holidays_endpoint_returns_work_rules(): void
    {
        \App\Models\Holiday::create(['name' => 'Pchum Ben', 'date' => '2026-10-12', 'year' => 2026]);

        $staff = $this->makeUser('staff');
        $res = $this->req('get', '/api/holidays?year=2026', token: $staff->createToken('t')->plainTextToken);

        $res->assertOk();
        $this->assertCount(1, $res->json('data'));
        $this->assertSame(90, $res->json('workRules.breakMinutes'));
    }

    public function test_invitation_flow_end_to_end(): void
    {
        $admin = $this->makeUser('admin');
        $aToken = $admin->createToken('t')->plainTextToken;
        $staffRoleId = Role::where('name', 'Staff')->value('id');

        // Staff cannot invite
        $this->req('post', '/api/access/invitations', [
            'email' => 'newhire@portal.test', 'roleId' => $staffRoleId,
        ], $this->makeUser('staff')->createToken('t')->plainTextToken)->assertStatus(403);

        // Admin invites
        $res = $this->req('post', '/api/access/invitations', [
            'email' => 'NewHire@Portal.test',
            'firstName' => 'New', 'lastName' => 'Hire',
            'roleId' => $staffRoleId,
            'requireProfileSetup' => true,
        ], $aToken)->assertCreated();

        $invitationId = $res->json('invitation.id');
        $this->assertNotNull($res->json('invitationLink'));

        // Account provisioned as Pending Invitation — blocked from normal login
        $user = User::where('email', 'newhire@portal.test')->first();
        $this->assertSame('Pending Invitation', $user->status);
        $this->req('post', '/api/auth/login', ['email' => 'newhire@portal.test', 'password' => 'password'])
            ->assertStatus(422);

        $token = $res->json('invitationLink');
        $magicToken = substr($token, (int) strrpos($token, '/') + 1);

        // Public magic-link landing shows the invitation info
        $this->req('get', "/api/invitations/{$magicToken}")->assertOk()
            ->assertJsonPath('invitation.email', 'newhire@portal.test');

        // Claim logs the staff in, activates the account
        $claimed = $this->req('post', "/api/invitations/{$magicToken}/claim", [
            'password' => 'secret1234',
        ])->assertOk();

        $this->assertNotNull($claimed->json('token'));
        $this->assertTrue((bool) $claimed->json('requireProfileSetup'));
        $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => 'Active']);

        // They can now log in with the password they chose
        $this->req('post', '/api/auth/login', ['email' => 'newhire@portal.test', 'password' => 'secret1234'])
            ->assertOk();

        // Token cannot be reused
        $this->req('post', "/api/invitations/{$magicToken}/claim")->assertStatus(410);
    }

    public function test_invitation_resend_and_revoke(): void
    {
        $admin = $this->makeUser('admin');
        $aToken = $admin->createToken('t')->plainTextToken;
        $staffRoleId = Role::where('name', 'Staff')->value('id');

        $res = $this->req('post', '/api/access/invitations', [
            'email' => 'leaver@portal.test', 'roleId' => $staffRoleId,
        ], $aToken)->assertCreated();

        $id = $res->json('invitation.id');

        $this->req('post', "/api/access/invitations/{$id}/resend", token: $aToken)->assertOk();
        $this->req('delete', "/api/access/invitations/{$id}", token: $aToken)->assertOk();

        // Revoked token no longer works
        $this->req('post', "/api/access/invitations/{$id}/resend", token: $aToken)->assertStatus(422);
    }

    public function test_manager_sees_pending_approvals_on_dashboard(): void
    {
        $staff = $this->makeUser('staff');
        $manager = $this->makeUser('staff');
        $manager->update(['role_id' => Role::where('name', 'Manager')->value('id')]);
        $mToken = $manager->createToken('t')->plainTextToken;

        // Staff submits a request
        $this->req('post', '/api/requests', [
            'type' => 'Leave',
            'data' => ['type' => 'Annual', 'startDate' => '2026-11-02', 'endDate' => '2026-11-03', 'reason' => 'Test'],
        ], $staff->createToken('t')->plainTextToken)->assertCreated();

        // Manager's dashboard flags it as awaiting approval
        $res = $this->req('get', '/api/dashboard', token: $mToken)->assertOk();
        $this->assertSame(1, $res->json('pendingApprovalsCount'));
        $this->assertSame('Leave', $res->json('pendingApprovals.0.type'));
        $this->assertSame($staff->full_name, $res->json('pendingApprovals.0.staff'));

        // Plain staff has an empty approvals list (not a reviewer)
        $res2 = $this->req('get', '/api/dashboard', token: $staff->createToken('t')->plainTextToken);
        $this->assertSame(0, $res2->json('pendingApprovalsCount'));
        $this->assertSame([], $res2->json('pendingApprovals'));
    }
}
