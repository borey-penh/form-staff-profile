<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FuelLogsheet extends Model
{
    protected $fillable = [
        'user_id', 'vehicle_id', 'month', 'year',
        'records', 'total_liters', 'total_cost',
    ];

    protected $casts = ['records' => 'array', 'total_liters' => 'float', 'total_cost' => 'float'];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }
}
