<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Department;
use App\Models\Role;
use App\Models\StaffInvitation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class InvitationController extends Controller
{
    /* ============================ Public ============================ */

    /** Magic-link landing: invitation info for the accept page. */
    public function show(string $token): JsonResponse
    {
        $invitation = StaffInvitation::with(['role', 'department'])->where('token', $token)->first();

        if (! $invitation || in_array($invitation->status, ['Revoked', 'Accepted'], true)) {
            return response()->json(['message' => 'This invitation is no longer valid.'], 410);
        }
        if ($invitation->isExpired()) {
            return response()->json([
                'message' => 'This invitation has expired. Ask HR to resend it.',
                'expired' => true,
            ], 410);
        }

        $user = User::where('email', $invitation->email)->first();

        return response()->json([
            'invitation' => [
                'email' => $invitation->email,
                'fullName' => trim(($invitation->first_name ?? $user?->first_name ?? '').' '.($invitation->last_name ?? $user?->last_name ?? '')),
                'roleLabel' => $invitation->role?->label ?? $invitation->role?->name,
                'department' => $invitation->department?->name,
                'expiresAt' => $invitation->expires_at->toISOString(),
                'requireProfileSetup' => $invitation->require_profile_setup,
                'profileComplete' => (bool) $user?->signature_path,
            ],
        ]);
    }

    /**
     * Magic-link login: consumes the token, activates the account and
     * returns a portal session token. An initial password may be set here.
     */
    public function claim(Request $request, string $token): JsonResponse
    {
        $data = $request->validate([
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $invitation = StaffInvitation::with('role')->where('token', $token)->first();

        if (! $invitation || $invitation->status !== 'Pending') {
            return response()->json(['message' => 'This invitation is no longer valid.'], 410);
        }
        if ($invitation->isExpired()) {
            return response()->json([
                'message' => 'This invitation has expired. Ask HR to resend it.',
                'expired' => true,
            ], 410);
        }

        $user = DB::transaction(function () use ($invitation, $data) {
            $user = User::where('email', $invitation->email)->first();

            if (! $user) {
                $user = User::create([
                    'staff_id' => 'TEMP-'.strtoupper(Str::random(8)),
                    'first_name' => $invitation->first_name ?? Str::before($invitation->email, '@'),
                    'last_name' => $invitation->last_name ?? '—',
                    'email' => $invitation->email,
                    'password' => Hash::make(Str::random(32)),
                    'role' => ($invitation->role?->name === 'Admin') ? 'admin' : 'staff',
                    'role_id' => $invitation->role_id,
                    'department_id' => $invitation->department_id,
                    'status' => 'Active',
                ]);
                $user->update(['staff_id' => 'ST-'.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT)]);
            } else {
                $user->update([
                    'status' => 'Active',
                    'role_id' => $user->role_id ?? $invitation->role_id,
                    'department_id' => $user->department_id ?? $invitation->department_id,
                ]);
            }

            if (! empty($data['password'])) {
                $user->update(['password' => Hash::make($data['password'])]);
            }

            $invitation->update(['status' => 'Accepted', 'accepted_at' => now()]);

            return $user;
        });

        $user->activities()->create([
            'icon' => 'profile',
            'message' => 'You joined via email invitation — welcome!',
        ]);

        return response()->json([
            'token' => $user->createToken('portal')->plainTextToken,
            'user' => new UserResource($user),
            'requireProfileSetup' => $invitation->require_profile_setup && ! $user->signature_path,
        ]);
    }

    /* ========================= Admin (users.manage) ========================= */

    public function index(): JsonResponse
    {
        $rows = StaffInvitation::with(['role', 'department'])
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn (StaffInvitation $i) => $this->serialize($i));

        return response()->json(['data' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'firstName' => ['nullable', 'string', 'max:255'],
            'lastName' => ['nullable', 'string', 'max:255'],
            'departmentId' => ['nullable', 'integer', 'exists:departments,id'],
            'roleId' => ['required', 'integer', 'exists:roles,id'],
            'requireProfileSetup' => ['boolean'],
        ]);

        $email = strtolower($data['email']);
        $role = Role::findOrFail($data['roleId']);

        $existing = User::where('email', $email)->first();
        if ($existing && $existing->status === 'Active') {
            return response()->json(['message' => 'An active account already exists for this email.'], 422);
        }

        $pending = StaffInvitation::where('email', $email)->where('status', 'Pending')->first();
        if ($pending) {
            return response()->json(['message' => 'An invitation is already pending for this email — resend it instead.'], 422);
        }

        $data['email'] = $email; // normalise case for storage + lookup
        $invitation = $this->sendInvitation($data, $request->user());

        return response()->json([
            'message' => "Invitation sent to {$email}.",
            'invitation' => $this->serialize($invitation),
            'invitationLink' => $this->linkFor($invitation), // handy when mail isn't configured
        ], 201);
    }

    /** Regenerate the token, extend expiry and resend the email. */
    public function resend(Request $request, int $id): JsonResponse
    {
        $invitation = StaffInvitation::findOrFail($id);

        if ($invitation->status !== 'Pending') {
            return response()->json(['message' => 'Only pending invitations can be resent.'], 422);
        }

        $invitation->update([
            'token' => Str::random(48),
            'expires_at' => now()->addHours(72),
            'status' => 'Pending',
        ]);

        $this->mail($invitation);

        return response()->json([
            'message' => "Invitation resent to {$invitation->email}.",
            'invitationLink' => $this->linkFor($invitation),
        ]);
    }

    public function revoke(int $id): JsonResponse
    {
        $invitation = StaffInvitation::findOrFail($id);

        if ($invitation->status === 'Accepted') {
            return response()->json(['message' => 'Accepted invitations cannot be revoked.'], 422);
        }

        $invitation->update(['status' => 'Revoked']);

        User::where('email', $invitation->email)->where('status', 'Pending Invitation')
            ->update(['status' => 'Inactive']);

        return response()->json(['message' => "Invitation for {$invitation->email} revoked."]);
    }

    /* ============================== Helpers ============================== */

    private function sendInvitation(array $data, User $inviter): StaffInvitation
    {
        $invitation = StaffInvitation::create([
            'email' => $data['email'],
            'token' => Str::random(48),
            'first_name' => $data['firstName'] ?? null,
            'last_name' => $data['lastName'] ?? null,
            'department_id' => $data['departmentId'] ?? null,
            'role_id' => $data['roleId'],
            'invited_by' => $inviter->id,
            'require_profile_setup' => $data['requireProfileSetup'] ?? true,
            'expires_at' => now()->addHours(72),
            'status' => 'Pending',
        ]);

        // Provision the account now (Pending Invitation) so the role and
        // department assigned at invite time are visible everywhere.
        $user = User::firstOrCreate(
            ['email' => $data['email']],
            [
                'staff_id' => 'TEMP-'.strtoupper(Str::random(8)),
                'first_name' => $data['firstName'] ?? Str::before($data['email'], '@'),
                'last_name' => $data['lastName'] ?? '—',
                'password' => Hash::make(Str::random(32)),
                'role' => ($invitation->role?->name === 'Admin') ? 'admin' : 'staff',
                'role_id' => $invitation->role_id,
                'department_id' => $invitation->department_id,
                'status' => 'Pending Invitation',
            ]
        );

        if (str_starts_with($user->staff_id, 'TEMP-')) {
            $user->update(['staff_id' => 'ST-'.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT)]);
        }

        $this->mail($invitation);

        $inviter->activities()->create([
            'icon' => 'request',
            'message' => "You invited {$invitation->email} as ".($invitation->role?->label ?? 'Staff'),
        ]);

        return $invitation;
    }

    private function mail(StaffInvitation $invitation): void
    {
        $link = $this->linkFor($invitation);
        $name = trim(($invitation->first_name ?? '').' '.($invitation->last_name ?? ''));
        $role = $invitation->role?->label ?? 'Staff';
        $dept = $invitation->department?->name;
        $deptLine = $dept ? "<br>Department: <strong>{$dept}</strong>" : '';

        $html = <<<HTML
        <div style="max-width:520px;margin:0 auto;font-family:Arial,sans-serif;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden">
          <div style="background:#0e6e66;color:#fff;padding:26px 30px">
            <h2 style="margin:0;font-size:18px">Personnel Portal</h2>
            <p style="margin:6px 0 0;opacity:.85;font-size:13px">Human Resources &amp; Staff Care</p>
          </div>
          <div style="padding:28px 30px">
            <p style="font-size:14.5px">Hello {$name},</p>
            <p style="font-size:14px;color:#334155">You have been invited to join the <strong>Personnel Portal</strong>.<br>
            Role: <strong>{$role}</strong>{$deptLine}</p>
            <p style="text-align:center;margin:26px 0">
              <a href="{$link}" style="background:#0e6e66;color:#fff;text-decoration:none;padding:12px 26px;border-radius:8px;font-weight:bold;display:inline-block">Accept Invitation</a>
            </p>
            <p style="font-size:12.5px;color:#64748b">This invitation expires in 72 hours. If the button doesn't work, paste this link into your browser:<br>{$link}</p>
          </div>
        </div>
        HTML;

        Mail::html($html, function ($message) use ($invitation, $name) {
            $message->to($invitation->email)
                ->subject('You are invited to the Personnel Portal'.($name ? " — {$name}" : ''));
        });
    }

    private function linkFor(StaffInvitation $invitation): string
    {
        return rtrim(config('app.url'), '/').'/invitation/accept/'.$invitation->token;
    }

    private function serialize(StaffInvitation $i): array
    {
        $user = User::where('email', $i->email)->first();

        $status = match (true) {
            $i->status === 'Revoked' => 'Revoked',
            $i->status === 'Accepted' && $user && ! $user->signature_path => 'Onboarding Incomplete',
            $i->status === 'Accepted' => 'Active',
            $i->isExpired() => 'Expired',
            default => 'Pending First Login',
        };

        return [
            'id' => $i->id,
            'email' => $i->email,
            'fullName' => trim(($i->first_name ?? $user?->first_name ?? '').' '.($i->last_name ?? $user?->last_name ?? '')),
            'roleLabel' => $i->role?->label ?? $i->role?->name,
            'roleId' => $i->role_id,
            'department' => $i->department?->name,
            'invitedBy' => $i->inviter?->full_name,
            'invitedAt' => $i->created_at?->toISOString(),
            'expiresAt' => $i->expires_at->toISOString(),
            'status' => $status,
        ];
    }

}
