<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Compliance;
use App\Models\Contract;
use App\Models\TrainingAssignment;
use App\Models\UserRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Profile completion
        $fields = ['first_name', 'last_name', 'dob', 'gender', 'pob', 'nid', 'marital', 'phone', 'address', 'photo_path', 'signature_path'];
        $filled = collect($fields)->filter(fn ($f) => filled($user->$f))->count();
        $completion = (int) round($filled / count($fields) * 100);

        // Training progress
        $assignments = TrainingAssignment::where('user_id', $user->id)->get();
        $trainingDone = $assignments->where('status', 'Complete')->count();
        $trainingTotal = $assignments->count();

        // Compliance
        $complianceTotal = Compliance::where('active', true)->count();
        $complianceSigned = $user->complianceSignatures()->count();

        // Active contract
        $contract = Contract::where('user_id', $user->id)->where('status', 'Active')->orderByDesc('start_date')->first();

        // Pending + recent requests (the user's own)
        $pending = UserRequest::with('user')
            ->where('user_id', $user->id)
            ->whereIn('status', ['Pending', 'In Review'])
            ->orderByDesc('submitted_at')
            ->limit(6)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'type' => $r->type,
                'status' => $r->status,
                'submittedAt' => $r->submitted_at?->toISOString(),
            ]);

        // Requests awaiting review — only for users allowed to see the team queue
        $pendingApprovals = collect();
        $pendingApprovalsCount = 0;
        if ($user->hasPermission('requests.view-team')) {
            $queue = UserRequest::with('user')
                ->whereIn('status', ['Pending', 'In Review'])
                ->where('user_id', '!=', $user->id) // own requests never appear as "awaiting your approval"
                ->orderByDesc('submitted_at');
            $pendingApprovalsCount = (clone $queue)->count();
            $pendingApprovals = $queue->limit(6)->get()->map(fn ($r) => [
                'id' => $r->id,
                'type' => $r->type,
                'status' => $r->status,
                'staff' => $r->user?->full_name,
                'staffId' => $r->user?->staff_id,
                'submittedAt' => $r->submitted_at?->toISOString(),
            ]);
        }

        // Upcoming trainings (incomplete, by due date)
        $upcoming = TrainingAssignment::with('training')
            ->where('user_id', $user->id)
            ->where('status', 'Pending')
            ->orderBy('due_date')
            ->limit(4)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'title' => $a->training->title,
                'dueDate' => $a->due_date?->toDateString(),
            ]);

        // Leave balances
        $balances = $user->leaveBalances()->get()->map(fn ($b) => [
            'type' => $b->type,
            'entitled' => $b->entitled,
            'used' => $b->used,
            'remaining' => max(0, $b->entitled - $b->used),
        ]);

        // Recent activities
        $activities = $user->activities()->take(6)->get()->map(fn ($a) => [
            'id' => $a->id,
            'icon' => $a->icon,
            'message' => $a->message,
            'at' => $a->created_at?->toISOString(),
        ]);

        return response()->json([
            'user' => new UserResource($user),
            'stats' => [
                'profileCompletion' => $completion,
                'training' => ['done' => $trainingDone, 'total' => $trainingTotal],
                'compliance' => ['signed' => $complianceSigned, 'total' => $complianceTotal],
                'contract' => $contract ? [
                    'type' => $contract->type,
                    'startDate' => $contract->start_date->toDateString(),
                    'endDate' => $contract->end_date?->toDateString(),
                ] : null,
            ],
            'pendingRequests' => $pending,
            'pendingApprovals' => $pendingApprovals,
            'pendingApprovalsCount' => $pendingApprovalsCount,
            'upcomingTrainings' => $upcoming,
            'leaveBalances' => $balances,
            'activities' => $activities,
        ]);
    }
}
