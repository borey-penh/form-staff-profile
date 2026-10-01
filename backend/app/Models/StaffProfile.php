<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'name_en', 'name_kh', 'gender', 'dob', 'pob', 'nid', 'addr',
        'phone', 'email', 'marital', 'spouse_name', 'spouse_occ',
        'children', 'ec_name', 'ec_rel', 'ec_phone', 'ec_addr',
        'beneficiaries', 'confirmed', 'signature',
    ];

    protected $casts = [
        'dob' => 'date',
        'children' => 'array',
        'beneficiaries' => 'array',
        'confirmed' => 'boolean',
    ];
}
