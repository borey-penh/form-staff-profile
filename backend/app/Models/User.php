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
        'role', 'position', 'department_id', 'phone', 'address',
        'photo_path', 'signature_path', 'dob', 'gender', 'pob',
        'nationality', 'nid', 'marital',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'dob' => 'date',
        'password' => 'hashed',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
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
        return $this->role === 'admin';
    }
}
