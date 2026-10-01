<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContractController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = Contract::where('user_id', $request->user()->id)
            ->orderByDesc('start_date')
            ->get()
            ->map(fn (Contract $c) => [
                'id' => $c->id,
                'type' => $c->type,
                'position' => $c->position,
                'department' => $c->department,
                'startDate' => $c->start_date->toDateString(),
                'endDate' => $c->end_date?->toDateString(),
                'salary' => $c->salary,
                'status' => $c->status,
                'fileUrl' => $c->file_path ? '/storage/'.$c->file_path : null,
            ]);

        return response()->json(['data' => $items]);
    }
}
