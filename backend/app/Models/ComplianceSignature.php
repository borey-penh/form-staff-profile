<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComplianceSignature extends Model
{
    protected $fillable = ['compliance_id', 'user_id', 'signature_path', 'signed_at'];

    protected $casts = ['signed_at' => 'date'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function compliance()
    {
        return $this->belongsTo(Compliance::class);
    }
}
