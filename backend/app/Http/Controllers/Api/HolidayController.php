<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HolidayController extends Controller
{
    /**
     * Holidays for a year + org work rules (break window, standard hours)
     * so the timesheet can compute hours exactly like the Excel form.
     */
    public function index(Request $request): JsonResponse
    {
        $year = (int) ($request->query('year', now()->year));

        return response()->json([
            'data' => Holiday::whereYear('date', $year)
                ->orderBy('date')
                ->get()
                ->map(fn (Holiday $h) => [
                    'id' => $h->id,
                    'name' => $h->name,
                    'date' => $h->date->toDateString(),
                ]),
            'workRules' => [
                'breakMinutes' => 90,   // unpaid break 12:00 – 13:30
                'breakStart' => '12:00',
                'breakEnd' => '13:30',
            ],
        ]);
    }

    /** Admin: add a holiday (e.g. a newly announced movable holiday). */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
        ]);

        $holiday = Holiday::firstOrCreate(
            ['date' => $data['date'], 'name' => $data['name']],
            ['year' => substr($data['date'], 0, 4)]
        );

        return response()->json([
            'message' => "Holiday \"{$holiday->name}\" ({$holiday->date->toDateString()}) saved.",
            'id' => $holiday->id,
        ], 201);
    }

    /** Admin: remove a holiday. */
    public function destroy(int $id): JsonResponse
    {
        $holiday = Holiday::findOrFail($id);
        $label = "{$holiday->name} ({$holiday->date->toDateString()})";
        $holiday->delete();

        return response()->json(['message' => "Holiday {$label} deleted."]);
    }
}
