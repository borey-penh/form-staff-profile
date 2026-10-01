<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Compliance;
use App\Models\ComplianceSignature;
use App\Models\Contract;
use App\Models\Document;
use App\Models\Training;
use App\Models\TrainingAssignment;
use App\Models\User;
use App\Models\UserRequest;
use App\Models\Voucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function dashboard(): JsonResponse
    {
        return response()->json([
            'totalStaff' => User::where('role', 'staff')->count(),
            'activeContracts' => Contract::where('status', 'Active')->count(),
            'pendingRequests' => UserRequest::whereIn('status', ['Pending', 'In Review'])->count(),
            'expiringContracts' => Contract::with('user')
                ->where('status', 'Active')
                ->whereBetween('end_date', [now(), now()->addDays(60)])
                ->orderBy('end_date')
                ->limit(6)
                ->get()
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'staff' => $c->user?->full_name,
                    'staffId' => $c->user?->staff_id,
                    'endDate' => $c->end_date?->toDateString(),
                ]),
            'pendingByType' => UserRequest::whereIn('status', ['Pending', 'In Review'])
                ->selectRaw('type, count(*) as total')
                ->groupBy('type')
                ->pluck('total', 'type'),
        ]);
    }

    /* ---------------- Personnel ---------------- */

    public function staffIndex(Request $request): JsonResponse
    {
        $q = User::with(['department', 'roleModel.permissions', 'permissions'])->orderBy('staff_id');

        if ($search = $request->query('search')) {
            $q->where(fn ($w) => $w
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('staff_id', 'like', "%{$search}%"));
        }

        return response()->json([
            'data' => $q->limit(200)->get()->map(fn (User $u) => new UserResource($u)),
        ]);
    }

    public function staffStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'staffId' => ['required', 'string', 'max:32', 'unique:users,staff_id'],
            'firstName' => ['required', 'string', 'max:255'],
            'lastName' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'role' => ['required', 'in:staff,admin'],
            'position' => ['nullable', 'string', 'max:255'],
            'departmentId' => ['nullable', 'integer', 'exists:departments,id'],
        ]);

        $user = User::create([
            'staff_id' => $data['staffId'],
            'first_name' => $data['firstName'],
            'last_name' => $data['lastName'],
            'email' => $data['email'],
            'password' => 'password',
            'role' => $data['role'],
            'position' => $data['position'] ?? null,
            'department_id' => $data['departmentId'] ?? null,
        ]);

        return response()->json([
            'message' => "Staff {$user->full_name} created. Default password: password",
            'user' => new UserResource($user),
        ], 201);
    }

    public function staffShow(Request $request, int $id): JsonResponse
    {
        $user = User::with(['qualifications', 'children', 'documents', 'contracts'])->findOrFail($id);

        return response()->json([
            'user' => new UserResource($user),
            'qualifications' => $user->qualifications,
            'children' => $user->children,
            'documents' => $user->documents->map(fn ($d) => [
                'id' => $d->id,
                'type' => $d->type,
                'originalName' => $d->original_name,
                'status' => $d->status,
                'url' => '/storage/'.$d->file_path,
            ]),
            'contracts' => $user->contracts->map(fn ($c) => [
                'id' => $c->id,
                'type' => $c->type,
                'position' => $c->position,
                'startDate' => $c->start_date->toDateString(),
                'endDate' => $c->end_date?->toDateString(),
                'status' => $c->status,
            ]),
        ]);
    }

    public function departments(): JsonResponse
    {
        return response()->json(['data' => \App\Models\Department::orderBy('name')->get()]);
    }

    /* ---------------- Compliances (dynamic) ---------------- */

    public function complianceIndex(): JsonResponse
    {
        return response()->json([
            'data' => Compliance::orderBy('title')->get()->map(fn ($c) => [
                'id' => $c->id,
                'title' => $c->title,
                'description' => $c->description,
                'active' => $c->active,
                'signatures' => $c->signatures()->count(),
            ]),
        ]);
    }

    public function complianceStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $compliance = Compliance::create($data);

        return response()->json(['message' => "Compliance \"{$compliance->title}\" created.", 'id' => $compliance->id], 201);
    }

    public function complianceUpdate(Request $request, int $id): JsonResponse
    {
        $compliance = Compliance::findOrFail($id);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $compliance->update($data);

        return response()->json(['message' => 'Compliance updated.']);
    }

    public function complianceDestroy(int $id): JsonResponse
    {
        Compliance::findOrFail($id)->delete();

        return response()->json(['message' => 'Compliance deleted.']);
    }

    /* ---------------- Trainings (dynamic) ---------------- */

    public function trainingIndex(): JsonResponse
    {
        return response()->json([
            'data' => Training::orderBy('title')->get()->map(fn ($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'description' => $t->description,
                'passScore' => $t->pass_score,
                'active' => $t->active,
                'assignments' => $t->assignments()->count(),
                'completions' => $t->assignments()->where('status', 'Complete')->count(),
            ]),
        ]);
    }

    public function trainingStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sections' => ['required', 'array', 'min:1'],
            'sections.*.title' => ['required', 'string'],
            'sections.*.body' => ['nullable', 'string'],
            'quiz' => ['required', 'array', 'min:1'],
            'quiz.*.question' => ['required', 'string'],
            'quiz.*.options' => ['required', 'array', 'min:2'],
            'quiz.*.answer' => ['required', 'integer'],
            'passScore' => ['nullable', 'integer', 'min:50', 'max:100'],
        ]);

        $training = Training::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'sections' => $data['sections'],
            'quiz' => $data['quiz'],
            'pass_score' => $data['passScore'] ?? 80,
        ]);

        return response()->json(['message' => "Training \"{$training->title}\" created.", 'id' => $training->id], 201);
    }

    /** Assign training to one or all staff. */
    public function trainingAssign(Request $request): JsonResponse
    {
        $data = $request->validate([
            'trainingId' => ['required', 'integer', 'exists:trainings,id'],
            'userId' => ['nullable', 'integer', 'exists:users,id'],
            'dueDate' => ['nullable', 'date'],
        ]);

        $query = isset($data['userId'])
            ? User::where('id', $data['userId'])
            : User::where('role', 'staff');

        $count = 0;
        $query->chunk(100, function ($users) use ($data, &$count) {
            foreach ($users as $user) {
                TrainingAssignment::firstOrCreate([
                    'training_id' => $data['trainingId'],
                    'user_id' => $user->id,
                ], [
                    'due_date' => $data['dueDate'] ?? null,
                ]);
                $count++;
            }
        });

        return response()->json(['message' => "Assigned to {$count} staff."]);
    }

    /* ---------------- Documents ---------------- */

    public function documentVerify(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:Pending,Verified,Rejected'],
        ]);

        $doc = Document::findOrFail($id);
        $doc->update(['status' => $data['status']]);

        return response()->json(['message' => "Document marked {$data['status']}."]);
    }

    /* ---------------- Contracts ---------------- */

    public function contractStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'userId' => ['required', 'integer', 'exists:users,id'],
            'type' => ['required', Rule::in(['Probationary', 'Full-Time', 'Part-Time', 'Intermittent', 'Volunteer', 'Internship'])],
            'position' => ['required', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'startDate' => ['required', 'date'],
            'endDate' => ['nullable', 'date'],
            'salary' => ['nullable', 'numeric', 'min:0'],
        ]);

        $contract = Contract::create([
            'user_id' => $data['userId'],
            'type' => $data['type'],
            'position' => $data['position'],
            'department' => $data['department'] ?? null,
            'start_date' => $data['startDate'],
            'end_date' => $data['endDate'] ?? null,
            'salary' => $data['salary'] ?? null,
            'status' => 'Active',
        ]);

        return response()->json(['message' => 'Contract created.', 'id' => $contract->id], 201);
    }

    /* ---------------- Vouchers / Finance ---------------- */

    public function voucherIndex(): JsonResponse
    {
        return response()->json([
            'data' => Voucher::orderByDesc('created_at')->limit(100)->get()->map(fn ($v) => [
                'id' => $v->id,
                'voucherNo' => $v->voucher_no,
                'date' => $v->date?->toDateString(),
                'expenseType' => $v->expense_type,
                'total' => $v->total,
                'paymentMethod' => $v->payment_method,
                'paymentStatus' => $v->payment_status,
            ]),
        ]);
    }

    public function voucherPay(Request $request, int $id): JsonResponse
    {
        $voucher = Voucher::findOrFail($id);
        $voucher->update(['payment_status' => 'Paid']);

        return response()->json(['message' => "Voucher {$voucher->voucher_no} marked paid."]);
    }

    /* ---------------- Reports ---------------- */

    public function reports(): JsonResponse
    {
        return response()->json([
            'personnel' => [
                'total' => User::count(),
                'byDepartment' => User::selectRaw('COALESCE(departments.name, "Unassigned") as name, count(*) as total')
                    ->leftJoin('departments', 'departments.id', '=', 'users.department_id')
                    ->groupBy('departments.name')
                    ->pluck('total', 'name'),
            ],
            'training' => [
                'completions' => TrainingAssignment::where('status', 'Complete')->count(),
                'outstanding' => TrainingAssignment::where('status', 'Pending')->count(),
            ],
            'leave' => [
                'approvedThisYear' => UserRequest::where('type', 'Leave')->where('status', 'Approved')
                    ->whereYear('decided_at', now()->year)->count(),
                'pending' => UserRequest::where('type', 'Leave')->whereIn('status', ['Pending', 'In Review'])->count(),
            ],
            'contract' => [
                'active' => Contract::where('status', 'Active')->count(),
                'expiring30' => Contract::where('status', 'Active')->whereBetween('end_date', [now(), now()->addDays(30)])->count(),
            ],
            'finance' => [
                'vouchersPaid' => Voucher::where('payment_status', 'Paid')->count(),
                'vouchersUnpaid' => Voucher::where('payment_status', 'Unpaid')->count(),
                'totalPaid' => (float) Voucher::where('payment_status', 'Paid')->sum('total'),
            ],
        ]);
    }
}
