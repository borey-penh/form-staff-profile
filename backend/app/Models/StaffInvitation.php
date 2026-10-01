<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffInvitation extends Model
{
    protected $fillable = [
        'email', 'token', 'first_name', 'last_name',
        'department_id', 'role_id', 'invited_by',
        'require_profile_setup', 'expires_at', 'accepted_at', 'status',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'require_profile_setup' => 'boolean',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function inviter()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isExpired(): bool
    {
        return $this->status === 'Pending' && $this->expires_at->isPast();
    }
}
