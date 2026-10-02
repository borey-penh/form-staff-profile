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

        $user = $request->user();
        // "mine=1" forces own-requests-only (My Requests page) — even for
        // managers/admins, who otherwise see the whole team queue.
        if ($request->boolean('mine') || (! $user->isAdmin() && ! $user->hasPermission('requests.view-team'))) {
            $q->where('user_id', $user->id);
        } else {
            // Team queue (Approval Queue): managers/admins review everyone else's
            // requests here. Their own submissions only appear in My Requests.
            $q->where('user_id', '!=', $user->id);
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
            'own' => $r->user_id === $user->id,
        ]);

        return response()->json(['data' => $requests]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:Leave,Overtime,Timesheet,Travel,Fuel,Purchase,Voucher'],
            'data' => ['required', 'array'],
            'attachment' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx'],
        ]);

        // Optional supporting document (Leave, Travel, Purchase, Voucher).
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $name = time().'-'.preg_replace('/[^\w\-.]/', '_', $file->getClientOriginalName());
            $data['data']['file_path'] = $file->storeAs('requests', $name, 'public');
        }

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

        $user = $request->user();
        if (! $user->isAdmin() && ! $user->hasPermission('requests.view-team') && $r->user_id !== $user->id) {
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
            'details' => $this->service->details($r),
            'trail' => $this->service->trail($r),
            'form' => $this->service->formPayload($r),
            'editable' => $r->user_id === $user->id && ! $this->service->hasDecision($r),
        ]);
    }

    /** Edit an own submission — possible until someone approves/rejects it. */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $r = UserRequest::findOrFail($id);

        if ($r->user_id !== $user->id) {
            return response()->json(['message' => 'You can only edit your own requests.'], 403);
        }

        if ($this->service->hasDecision($r)) {
            return response()->json([
                'message' => 'This request was already approved or rejected and can no longer be edited.',
            ], 403);
        }

        $data = $request->validate([
            'type' => ['required', 'in:Leave,Overtime,Timesheet,Travel,Fuel,Purchase,Voucher'],
            'data' => ['required', 'array'],
            'attachment' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx'],
        ]);

        if ($data['type'] !== $r->type) {
            return response()->json(['message' => 'The request type cannot be changed.'], 422);
        }

        // Optional replacement attachment.
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $name = time().'-'.preg_replace('/[^\w\-.]/', '_', $file->getClientOriginalName());
            $data['data']['file_path'] = $file->storeAs('requests', $name, 'public');
        }

        $this->service->update($r, $user, $data['data']);

        return response()->json(['message' => "{$r->type} request updated."]);
    }

    /** Supervisor/HR/Finance workflow actions. */
    public function act(Request $request, int $id): JsonResponse
    {
        $actor = auth('sanctum')->user() ?? $request->user();

        if (! $actor?->isAdmin() && ! $actor?->hasPermission('requests.approve')) {
            return response()->json(['message' => 'You do not have permission to process requests.'], 403);
        }

        $data = $request->validate([
            'action' => ['required', 'in:review,approve,reject,return,complete,cancel,resubmit'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $r = UserRequest::findOrFail($id);

        // No self-approval: decision actions are reserved for other people's requests.
        $ownRequest = $r->user_id === $actor->id;
        $decisions = ['approve', 'reject', 'return', 'complete'];
        if ($ownRequest && in_array($data['action'], $decisions, true)) {
            return response()->json([
                'message' => 'You cannot approve, reject or return your own request.',
            ], 403);
        }
        // Managers may still progress/cancel their own submissions.
        if ($ownRequest && ! in_array($data['action'], ['review', 'cancel', 'resubmit'], true)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $this->service->act($r, $actor, $data['action'], $data['note'] ?? null);

        return response()->json(['message' => "Request {$data['action']}d.", 'status' => $r->status]);
    }
}
