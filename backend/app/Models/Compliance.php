<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Compliance extends Model
{
    protected $fillable = ['title', 'description', 'policy_path', 'active'];

    public function signatures()
    {
        return $this->hasMany(ComplianceSignature::class);
    }
}
