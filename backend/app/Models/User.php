<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'staff_id', 'first_name', 'last_name', 'name_kh', 'email', 'password',
        'role', 'role_id', 'status', 'position', 'department_id', 'phone', 'phone_alt',
        'email_alt', 'address', 'addr_house', 'addr_street', 'addr_village',
        'addr_commune', 'addr_district', 'addr_province', 'addr_postal',
        'notes_to_org', 'declaration_accepted_at',
        'photo_path', 'signature_path', 'dob', 'gender', 'pob',
        'nationality', 'nid', 'marital',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'dob' => 'date',
        'declaration_accepted_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function roleModel()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class);
    }

    public function profileChangeRequests()
    {
        return $this->hasMany(ProfileChangeRequest::class);
    }

    private ?array $resolvedPermissions = null;

    /** All permission names from the role + any additional per-user grants. */
    public function allPermissions(): array
    {
        if ($this->resolvedPermissions !== null) {
            return $this->resolvedPermissions;
        }

        $rolePerms = $this->roleModel?->permissions->pluck('name') ?? collect();
        $extraPerms = $this->relationLoaded('permissions')
            ? $this->permissions
            : $this->permissions()->get();

        return $this->resolvedPermissions = $rolePerms
            ->merge($extraPerms->pluck('name'))
            ->unique()
            ->values()
            ->all();
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->allPermissions(), true);
    }

    /** Grant an additional permission on top of the role defaults. */
    public function grantPermission(string $name): void
    {
        $id = Permission::where('name', $name)->value('id');
        if ($id && ! $this->permissions()->where('permission_id', $id)->exists()) {
            $this->permissions()->attach($id);
        }
    }

    public function revokePermission(string $name): void
    {
        $id = Permission::where('name', $name)->value('id');
        if ($id) {
            $this->permissions()->detach($id);
        }
    }

    public function setStatus(string $status): void
    {
        $this->update(['status' => $status]);
    }

    public function isActive(): bool
    {
        return $this->status === 'Active';
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function qualifications()
    {
        return $this->hasMany(Qualification::class);
    }

    public function children()
    {
        return $this->hasMany(Child::class);
    }

    public function emergencyContacts()
    {
        return $this->hasMany(EmergencyContact::class);
    }

    public function spouse()
    {
        return $this->hasOne(Spouse::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function expertises()
    {
        return $this->hasMany(Expertise::class);
    }

    public function languageSkills()
    {
        return $this->hasMany(LanguageSkill::class);
    }

    public function geographicExperiences()
    {
        return $this->hasMany(GeographicExperience::class);
    }

    public function beneficiaries()
    {
        return $this->hasMany(Beneficiary::class);
    }

    public function contracts()
    {
        return $this->hasMany(Contract::class)->orderByDesc('start_date');
    }

    public function activeContract()
    {
        return $this->hasOne(Contract::class)->where('status', 'Active')->latestOfMany('start_date');
    }

    public function trainingAssignments()
    {
        return $this->hasMany(TrainingAssignment::class);
    }

    public function complianceSignatures()
    {
        return $this->hasMany(ComplianceSignature::class);
    }

    public function requests()
    {
        return $this->hasMany(UserRequest::class);
    }

    public function activities()
    {
        return $this->hasMany(Activity::class)->latest()->limit(8);
    }

    public function leaveBalances()
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function isAdmin(): bool
    {
        if ($this->role_id && $this->roleModel) {
            return $this->roleModel->name === 'Admin';
        }

        return $this->role === 'admin';
    }
}
