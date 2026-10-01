<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = ['name', 'label', 'description'];

    public function permissions()
    {
        return $this->belongsToMany(Permission::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    /** Default permissions newly-created users get, plus extras. */
    public function syncPermissions(array $names): void
    {
        $ids = Permission::whereIn('name', $names)->pluck('id');
        $this->permissions()->sync($ids);
    }
}
