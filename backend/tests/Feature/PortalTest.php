<?php

namespace Tests\Feature;

use App\Models\Compliance;
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
            default => $this->getJson($uri, $headers),
        };

        auth()->forgetGuards();

        return $res;
    }

    private function makeUser(string $role): User
    {
        return User::create([
            'staff_id' => ($role === 'admin' ? 'HR-' : 'ST-').strtoupper(substr(uniqid(), -6)),
            'first_name' => 'Test',
            'last_name' => ucfirst($role),
            'email' => $role.'.'.uniqid().'@portal.test',
            'password' => Hash::make('password'),
            'role' => $role,
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

        // Trail has both actions
        $res = $this->req('get', '/api/requests/1', token: $sToken);
        $res->assertOk();
        $this->assertCount(2, $res->json('trail'));
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
}
