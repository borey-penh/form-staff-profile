<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    protected $fillable = [
        'voucher_no', 'date', 'expense_type', 'description',
        'lines', 'total', 'payment_method', 'file_path', 'payment_status',
    ];

    protected $casts = ['date' => 'date', 'lines' => 'array', 'total' => 'float'];
}
