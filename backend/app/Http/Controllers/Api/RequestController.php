<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserRequest;
use App\Services\RequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RequestController extends Controller
{
    public function __construct(private RequestService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $q = UserRequest::with('user')->orderByDesc('submitted_at');

        if (! $request->user()->isAdmin()) {
            $q->where('user_id', $request->user()->id);
        }

        if ($type = $request->query('type')) {
            $q->where('type', $type);
        }
        if ($status = $request->query('status')) {
            $q->where('status', $status);
        }

        $requests = $q->limit(100)->get()->map(fn (UserRequest $r) => [
            'id' => $r->id,
            'type' => $r->type,
            'status' => $r->status,
            'submittedAt' => $r->submitted_at?->toISOString(),
            'staff' => $r->user?->full_name,
            'staffId' => $r->user?->staff_id,
        ]);

        return response()->json(['data' => $requests]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:Leave,Overtime,Timesheet,Travel,Fuel,Purchase,Voucher'],
            'data' => ['required', 'array'],
        ]);

        $domain = $this->service->submit(
            $request->user(),
            $data['type'],
            $data['data']
        );

        return response()->json([
            'message' => "{$data['type']} request submitted.",
            'requestId' => $domain->id,
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $r = UserRequest::with('user')->findOrFail($id);

        if (! $request->user()->isAdmin() && $r->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json([
            'request' => [
                'id' => $r->id,
                'type' => $r->type,
                'status' => $r->status,
                'submittedAt' => $r->submitted_at?->toISOString(),
                'staff' => $r->user?->full_name,
                'staffId' => $r->user?->staff_id,
            ],
            'trail' => $this->service->trail($r),
        ]);
    }

    /** Supervisor/HR/Finance workflow actions. */
    public function act(Request $request, int $id): JsonResponse
    {
        $actor = auth('sanctum')->user() ?? $request->user();

        if (! $actor?->isAdmin()) {
            return response()->json(['message' => 'Only HR/Admin can process requests.'], 403);
        }

        $data = $request->validate([
            'action' => ['required', 'in:review,approve,reject,return,complete,cancel,resubmit'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $r = UserRequest::findOrFail($id);
        $this->service->act($r, $actor, $data['action'], $data['note'] ?? null);

        return response()->json(['message' => "Request {$data['action']}d.", 'status' => $r->status]);
    }
}
