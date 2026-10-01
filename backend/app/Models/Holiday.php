<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    protected $fillable = ['name', 'date', 'year'];

    protected $casts = ['date' => 'date'];

    /** Set of holiday dates (Y-m-d) for a given year, for fast lookup. */
    public static function datesForYear(int $year): array
    {
        return static::query()
            ->whereYear('date', $year)
            ->pluck('date')
            ->map(fn ($d) => $d instanceof \DateTimeInterface ? $d->format('Y-m-d') : (string) $d)
            ->flip()
            ->all();
    }
}
