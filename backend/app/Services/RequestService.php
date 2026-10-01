<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\FuelLogsheet;
use App\Models\Leave;
use App\Models\Overtime;
use App\Models\Purchase;
use App\Models\RequestAction;
use App\Models\Timesheet;
use App\Models\Travel;
use App\Models\User;
use App\Models\UserRequest;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;

class RequestService
{
    /**
     * Domain model + field map for each request type.
     */
    private const DOMAIN = [
        'Leave' => [Leave::class, ['type', 'start_date', 'end_date', 'days', 'reason', 'file_path']],
        'Overtime' => [Overtime::class, ['date', 'start_time', 'end_time', 'hours', 'reason', 'supervisor_id']],
        'Timesheet' => [Timesheet::class, ['user_id', 'month', 'year', 'entries', 'total_hours']],
        'Travel' => [Travel::class, ['purpose', 'destination', 'start_date', 'end_date', 'transport', 'accommodation', 'costs', 'total_cost', 'file_path']],
        'Fuel' => [FuelLogsheet::class, ['user_id', 'vehicle_id', 'month', 'year', 'records', 'total_liters', 'total_cost']],
        'Purchase' => [Purchase::class, ['purpose', 'department', 'required_date', 'items', 'total', 'justification', 'file_path']],
        'Voucher' => [Voucher::class, ['voucher_no', 'date', 'expense_type', 'description', 'lines', 'total', 'payment_method', 'file_path']],
    ];

    /**
     * Submit a request: creates the domain record + workflow record atomically.
     */
    public function submit(User $user, string $type, array $data): UserRequest
    {
        if (! isset(self::DOMAIN[$type])) {
            throw new \InvalidArgumentException("Unknown request type [{$type}].");
        }

        [$model, $fields] = self::DOMAIN[$type];

        // Accept camelCase (JS) or snake_case (PHP) payloads
        $normalized = [];
        foreach ($data as $key => $value) {
            $normalized[\Illuminate\Support\Str::snake($key)] = $value;
        }

        $payload = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $normalized)) {
                $payload[$field] = $normalized[$field];
            }
        }

        // Auto-computed domain fields
        $payload = $this->hydrate($type, $user, $payload);

        return DB::transaction(function () use ($user, $type, $model, $payload) {
            $domain = $model::create($payload);

            $request = UserRequest::create([
                'user_id' => $user->id,
                'type' => $type,
                'status' => 'Pending',
                'related_id' => $domain->id,
                'submitted_at' => now(),
            ]);

            RequestAction::create([
                'request_id' => $request->id,
                'user_id' => $user->id,
                'action' => 'submitted',
                'note' => null,
            ]);

            Activity::create([
                'user_id' => $user->id,
                'icon' => 'request',
                'message' => "You submitted a {$type} request",
            ]);

            return $request;
        });
    }

    /**
     * Supervisor/HR/Finance action on a request.
     */
    public function act(UserRequest $request, User $actor, string $action, ?string $note = null): UserRequest
    {
        $statusMap = [
            'review' => 'In Review',
            'approve' => 'Approved',
            'reject' => 'Rejected',
            'return' => 'Returned',
            'complete' => 'Completed',
            'cancel' => 'Cancelled',
            'resubmit' => 'Pending',
        ];

        if (! isset($statusMap[$action])) {
            throw new \InvalidArgumentException("Unknown action [{$action}].");
        }

        DB::transaction(function () use ($request, $actor, $action, $note, $statusMap) {
            $request->update([
                'status' => $statusMap[$action],
                'decided_at' => in_array($action, ['approve', 'reject', 'complete', 'cancel']) ? now() : null,
            ]);

            RequestAction::create([
                'request_id' => $request->id,
                'user_id' => $actor->id,
                'action' => $action,
                'note' => $note,
            ]);

            Activity::create([
                'user_id' => $request->user_id,
                'icon' => 'request',
                'message' => "Your {$request->type} request was {$statusMap[$action]}",
            ]);
        });

        return $request->fresh();
    }

    /**
     * Full workflow trail for one request.
     */
    public function trail(UserRequest $request): array
    {
        return $request->actions->map(fn (RequestAction $a) => [
            'id' => $a->id,
            'action' => $a->action,
            'note' => $a->note,
            'by' => $a->user?->full_name,
            'at' => $a->created_at?->toISOString(),
        ])->all();
    }

    private function hydrate(string $type, User $user, array $payload): array
    {
        switch ($type) {
            case 'Leave':
                $payload['days'] ??= max(1, now()->parse($payload['start_date'])->diffInDays(now()->parse($payload['end_date'])) + 1);
                break;

            case 'Overtime':
                if (isset($payload['start_time'], $payload['end_time'])) {
                    $start = now()->parse($payload['start_time']);
                    $end = now()->parse($payload['end_time']);
                    if ($end < $start) {
                        $end = $end->addDay();
                    }
                    $payload['hours'] ??= round($start->diffInMinutes($end) / 60, 2);
                }
                break;

            case 'Travel':
                if (isset($payload['costs']) && is_array($payload['costs'])) {
                    $payload['total_cost'] ??= array_sum(array_map('floatval', $payload['costs']));
                }
                break;

            case 'Purchase':
                if (isset($payload['items']) && is_array($payload['items'])) {
                    $payload['total'] ??= collect($payload['items'])
                        ->sum(fn ($i) => ($i['qty'] ?? 0) * ($i['unitCost'] ?? $i['unit_cost'] ?? 0));
                }
                break;

            case 'Fuel':
                $payload['user_id'] = $user->id;
                if (isset($payload['records']) && is_array($payload['records'])) {
                    $payload['total_liters'] ??= collect($payload['records'])->sum(fn ($r) => (float) ($r['liters'] ?? 0));
                    $payload['total_cost'] ??= collect($payload['records'])->sum(fn ($r) => (float) ($r['cost'] ?? 0));
                }
                break;

            case 'Voucher':
                $payload['voucher_no'] ??= 'PV-'.now()->format('Y').'-'.str_pad((string) (Voucher::count() + 1), 5, '0', STR_PAD_LEFT);
                if (isset($payload['lines']) && is_array($payload['lines'])) {
                    $payload['total'] ??= collect($payload['lines'])->sum(fn ($l) => (float) ($l['amount'] ?? 0));
                }
                break;

            case 'Timesheet':
                $payload['user_id'] = $user->id;
                break;
        }

        return $payload;
    }
}
