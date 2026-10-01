<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Permission;
use App\Models\ProfileChangeRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AccessController extends Controller
{
    /* ======================= Roles & Permissions ======================= */

    public function roles(): JsonResponse
    {
        return response()->json([
            'data' => Role::withCount('users')->get()->map(fn (Role $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'label' => $r->label,
                'description' => $r->description,
                'users' => $r->users_count,
                'permissions' => $r->permissions->pluck('name'),
            ]),
            'allPermissions' => Permission::orderBy('group')->orderBy('label')->get()
                ->map(fn (Permission $p) => [
                    'name' => $p->name,
                    'group' => $p->group,
                    'label' => $p->label,
                ]),
            'departments' => \App\Models\Department::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function roleStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:64', 'unique:roles,name'],
            'label' => ['required', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $role = Role::create([
            'name' => $data['name'],
            'label' => $data['label'],
            'description' => $data['description'] ?? null,
        ]);

        $this->logAudit($request, 'role.created', "Created role {$role->label}");

        return response()->json(['message' => "Role \"{$role->label}\" created.", 'id' => $role->id], 201);
    }

    public function roleShow(Role $role): JsonResponse
    {
        return response()->json([
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'label' => $role->label,
                'description' => $role->description,
                'permissions' => $role->permissions->pluck('name'),
            ],
            'allPermissions' => Permission::orderBy('group')->orderBy('label')->get()
                ->map(fn (Permission $p) => [
                    'name' => $p->name, 'group' => $p->group, 'label' => $p->label,
                ]),
        ]);
    }

    public function roleUpdate(Request $request, Role $role): JsonResponse
    {
        $data = $request->validate([
            'label' => ['sometimes', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:1000'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $before = $role->permissions->pluck('name')->sort()->values()->all();

        if (isset($data['label']) || array_key_exists('description', $data)) {
            $role->update([
                'label' => $data['label'] ?? $role->label,
                'description' => $data['description'] ?? $role->description,
            ]);
        }

        if (isset($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        $after = $role->permissions()->pluck('name')->sort()->values()->all();
        if ($before !== $after) {
            $added = array_diff($after, $before);
            $removed = array_diff($before, $after);
            $this->logAudit($request, 'role.permissions_changed',
                'Changed permissions of role '.$role->label
                .($added ? ' — added: '.implode(', ', $added) : '')
                .($removed ? ' — removed: '.implode(', ', $removed) : ''));
        }

        return response()->json(['message' => 'Role saved.']);
    }

    public function roleDestroy(Request $request, Role $role): JsonResponse
    {
        if ($role->name === 'Admin') {
            return response()->json(['message' => 'The Admin role cannot be deleted.'], 422);
        }

        if ($role->users()->exists()) {
            return response()->json(['message' => 'Reassign users before deleting this role.'], 422);
        }

        $label = $role->label;
        $role->delete();
        $this->logAudit($request, 'role.deleted', "Deleted role {$label}");

        return response()->json(['message' => "Role \"{$label}\" deleted."]);
    }

    /* ========================== User management ========================= */

    public function users(Request $request): JsonResponse
    {
        $q = User::with(['department', 'roleModel'])
            ->withCount('permissions as extraPermissionsCount')
            ->orderBy('staff_id');

        if ($search = $request->query('search')) {
            $q->where(fn ($w) => $w
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('staff_id', 'like', "%{$search}%"));
        }
        if ($roleId = $request->query('roleId')) {
            $q->where('role_id', $roleId);
        }
        if ($status = $request->query('status')) {
            $q->where('status', $status);
        }

        return response()->json([
            'data' => $q->limit(300)->get()->map(fn (User $u) => $this->serializeManagedUser($u)),
        ]);
    }

    public function userShow(User $user): JsonResponse
    {
        $user->load(['department', 'roleModel.permissions', 'permissions']);

        return response()->json([
            'user' => $this->serializeManagedUser($user),
            'allPermissions' => Permission::orderBy('group')->orderBy('label')->get()
                ->map(fn (Permission $p) => [
                    'name' => $p->name, 'group' => $p->group, 'label' => $p->label,
                ]),
            'roles' => Role::orderBy('name')->get(['id', 'name', 'label']),
        ]);
    }

    public function userUpdate(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'roleId' => ['sometimes', 'integer', 'exists:roles,id'],
            'departmentId' => ['nullable', 'integer', 'exists:departments,id'],
            'position' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'in:Pending Invitation,Active,Suspended,Inactive'],
            'additionalPermissions' => ['sometimes', 'array'],
            'additionalPermissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $before = $user->status;
        $changes = [];

        DB::transaction(function () use ($data, $user, &$changes) {
            if (isset($data['roleId'])) {
                $newRole = Role::find($data['roleId']);
                if ($newRole && $newRole->id !== $user->role_id) {
                    $changes[] = "role: {$user->roleModel?->label} → {$newRole->label}";
                    $user->role_id = $newRole->id;
                    $user->role = $newRole->name === 'Admin' ? 'admin' : 'staff';
                }
            }

            if (array_key_exists('departmentId', $data)) {
                $user->department_id = $data['departmentId'];
            }
            if (array_key_exists('position', $data)) {
                $user->position = $data['position'];
            }

            if (isset($data['status']) && $data['status'] !== $user->status) {
                $changes[] = "status: {$user->status} → {$data['status']}";
                $user->status = $data['status'];
            }

            $user->save();

            if (isset($data['additionalPermissions'])) {
                $current = $user->permissions->pluck('name')->sort()->values()->all();
                $next = collect($data['additionalPermissions'])->sort()->values()->all();
                if ($current !== $next) {
                    $changes[] = 'additional permissions updated';
                    $ids = Permission::whereIn('name', $data['additionalPermissions'])->pluck('id');
                    $user->permissions()->sync($ids);
                }
            }
        });

        if ($changes) {
            $this->logAudit($request, 'user.updated', 'Updated '.$user->full_name.' — '.implode('; ', $changes));
        }

        $user->load(['department', 'roleModel.permissions', 'permissions']);

        return response()->json([
            'message' => 'User saved.',
            'user' => $this->serializeManagedUser($user),
        ]);
    }

    /* ====================== Profile change requests ===================== */

    public function changeRequestIndex(Request $request): JsonResponse
    {
        $q = ProfileChangeRequest::with(['user', 'reviewer'])->orderByDesc('created_at');

        if ($status = $request->query('status')) {
            $q->where('status', $status);
        }

        return response()->json([
            'data' => $q->limit(200)->get()->map(fn (ProfileChangeRequest $r) => $this->serializeChangeRequest($r)),
        ]);
    }

    /** Admin approves or rejects a suggested profile change. */
    public function changeRequestReview(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:approve,reject'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $change = ProfileChangeRequest::with('user')->findOrFail($id);

        if ($change->status !== 'Pending') {
            return response()->json(['message' => 'This request was already reviewed.'], 422);
        }

        // Don't allow approving your own pending change request.
        if ($change->user_id === $request->user()->id && $data['action'] === 'approve') {
            return response()->json(['message' => 'You cannot approve your own change request.'], 422);
        }

        DB::transaction(function () use ($data, $change, $request) {
            if ($data['action'] === 'approve') {
                $field = $change->field;
                $user = $change->user;

                // camelCase request key → snake_case column
                $column = Str::snake($field);
                if ($user->isFillable($column)) {
                    $user->forceFill([$column => $change->requested_value]);
                    $user->save();
                }

                $change->update([
                    'status' => 'Approved',
                    'reviewed_by' => $request->user()->id,
                    'reviewed_at' => now(),
                    'review_note' => $data['note'] ?? null,
                ]);
            } else {
                $change->update([
                    'status' => 'Rejected',
                    'reviewed_by' => $request->user()->id,
                    'reviewed_at' => now(),
                    'review_note' => $data['note'] ?? null,
                ]);
            }

            $user = $change->user;
            $user->activities()->create([
                'icon' => 'profile',
                'message' => $data['action'] === 'approve'
                    ? "Your profile change to {$change->field} was approved"
                    : "Your profile change to {$change->field} was rejected",
            ]);

            $this->logAudit($request, 'profile_change_request.'.($data['action'] === 'approve' ? 'approved' : 'rejected'),
                ($data['action'] === 'approve' ? 'Approved' : 'Rejected')." {$user->full_name}'s change to {$change->field}");
        });

        return response()->json(['message' => "Change request {$data['action']}d.", 'status' => $change->status]);
    }

    private function serializeManagedUser(User $u): array
    {
        $extras = $u->relationLoaded('permissions')
            ? $u->permissions->pluck('name')->values()->all()
            : $u->permissions()->pluck('name')->values()->all();

        return [
            'id' => $u->id,
            'staffId' => $u->staff_id,
            'fullName' => $u->full_name,
            'nameKh' => $u->name_kh,
            'email' => $u->email,
            'role' => $u->role,
            'roleId' => $u->role_id,
            'roleLabel' => $u->roleModel?->label,
            'status' => $u->status,
            'position' => $u->position,
            'department' => $u->department?->name,
            'departmentId' => $u->department_id,
            'extraPermissions' => $extras,
            'extraPermissionsCount' => $u->extra_permissions_count ?? count($extras),
            'joinedAt' => $u->created_at?->toDateString(),
        ];
    }

    private function serializeChangeRequest(ProfileChangeRequest $r): array
    {
        return [
            'id' => $r->id,
            'userId' => $r->user_id,
            'staff' => $r->user?->full_name,
            'staffId' => $r->user?->staff_id,
            'field' => $r->field,
            'fieldLabel' => $this->fieldLabel($r->field),
            'currentValue' => $r->current_value,
            'requestedValue' => $r->requested_value,
            'reason' => $r->reason,
            'status' => $r->status,
            'reviewedBy' => $r->reviewer?->full_name,
            'reviewedAt' => $r->reviewed_at?->toISOString(),
            'reviewNote' => $r->review_note,
            'submittedAt' => $r->created_at?->toISOString(),
        ];
    }

    private function fieldLabel(string $field): string
    {
        return match ($field) {
            'firstName' => 'First Name', 'lastName' => 'Last Name',
            'nameKh' => 'Name (Khmer)', 'dob' => 'Date of Birth',
            'gender' => 'Sex', 'pob' => 'Place of Birth',
            'nationality' => 'Nationality', 'nid' => 'ID / Passport Number',
            'marital' => 'Marital Status', 'phone' => 'Phone Number',
            'email' => 'Email', 'address' => 'Current Address',
            default => Str::headline($field),
        };
    }

    private function logAudit(Request $request, string $action, string $message): void
    {
        \App\Models\Activity::create([
            'user_id' => $request->user()->id,
            'icon' => 'audit',
            'message' => "[{$action}] {$message}",
        ]);
    }
}
