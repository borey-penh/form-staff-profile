<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Cambodia public holidays.
 *
 * Fixed-date holidays recur every year (seeded 2026–2030).
 * Movable (lunar-based) holidays — Khmer New Year, Visak Bochea, Royal
 * Plowing, Pchum Ben, Water Festival — are announced by the government
 * each year and must be added per year (seeder seeds 2026; admins can
 * add future years on the Holiday Calendar page).
 */
class HolidaySeeder extends Seeder
{
    private const FIXED = [
        '01-01', 'International New Year',
        '01-07', 'Victory Over Genocide Day',
        '03-08', "International Women's Day",
        '05-01', 'International Labor Day',
        '05-14', "King Norodom Sihamoni's Birthday",
        '06-18', "Queen Norodom Monineath Sihanouk's Birthday",
        '09-24', 'Constitution Day',
        '10-15', "Commemoration Day of King's Father",
        '10-29', "King Norodom Sihamoni's Coronation Day",
        '11-09', 'National Independence Day',
        '12-29', 'Win Win Day',
    ];

    private const MOVABLE_2026 = [
        ['2026-04-14', 'Khmer New Year'],
        ['2026-04-15', 'Khmer New Year'],
        ['2026-04-16', 'Khmer New Year'],
        ['2026-05-05', 'Visak Bochea Day'],
        ['2026-05-05', 'Royal Plowing Ceremony Day'],
        ['2026-10-10', 'Pchum Ben'],
        ['2026-10-11', 'Pchum Ben'],
        ['2026-10-12', 'Pchum Ben'],
        ['2026-11-23', 'Water Festival'],
        ['2026-11-24', 'Water Festival'],
        ['2026-11-25', 'Water Festival'],
    ];

    public function run(): void
    {
        $fixedPairs = [];
        for ($i = 0; $i < count(self::FIXED); $i += 2) {
            $fixedPairs[] = [self::FIXED[$i], self::FIXED[$i + 1]];
        }

        $holidays = [];
        foreach (range(2026, 2030) as $year) {
            foreach ($fixedPairs as [$md, $name]) {
                $holidays[] = ["{$year}-{$md}", $name];
            }
        }

        foreach (self::MOVABLE_2026 as $row) {
            $holidays[] = $row;
        }

        foreach ($holidays as [$date, $name]) {
            DB::table('holidays')->updateOrInsert(
                ['date' => $date, 'name' => $name],
                ['year' => substr($date, 0, 4), 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
