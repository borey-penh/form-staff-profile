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
use Illuminate\Support\Facades\Storage;

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

        [$model] = self::DOMAIN[$type];

        $payload = $this->extractPayload($type, $data);

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
     * Edit the requester's own submission — possible until a reviewer
     * decides the request (approves, rejects or completes it).
     */
    public function update(UserRequest $request, User $user, array $data): UserRequest
    {
        if (! isset(self::DOMAIN[$request->type])) {
            throw new \InvalidArgumentException("Unknown request type [{$request->type}].");
        }

        [$model] = self::DOMAIN[$request->type];

        $payload = $this->extractPayload($request->type, $data);
        $payload = $this->hydrate($request->type, $user, $payload);

        // The voucher number is assigned at creation — never regenerated on edit.
        unset($payload['voucher_no']);

        DB::transaction(function () use ($request, $user, $model, $payload) {
            $record = $model::findOrFail($request->related_id);

            // Swapping an attachment deletes the replaced file.
            if (array_key_exists('file_path', $payload) && $record->file_path && $record->file_path !== $payload['file_path']) {
                Storage::disk('public')->delete($record->file_path);
            }

            $record->update($payload);

            RequestAction::create([
                'request_id' => $request->id,
                'user_id' => $user->id,
                'action' => 'updated',
                'note' => null,
            ]);
        });

        return $request;
    }

    /** True once a reviewer has decided (approve / reject / complete) — the submission is then locked. */
    public function hasDecision(UserRequest $request): bool
    {
        return $request->actions()
            ->whereIn('action', ['approve', 'reject', 'complete'])
            ->exists();
    }

    /**
     * Raw domain data (camelCased) for prefilling the request form in edit mode.
     */
    public function formPayload(UserRequest $request): array
    {
        [$model, $fields] = self::DOMAIN[$request->type];
        $record = $request->related_id ? $model::find($request->related_id) : null;
        if (! $record) {
            return [];
        }

        $out = [];
        foreach ($fields as $field) {
            $value = $record->{$field} ?? null;
            $out[\Illuminate\Support\Str::camel($field)] = $value instanceof \DateTimeInterface
                ? $value->format('Y-m-d')
                : $value;
        }

        return $out;
    }

    /** Accept camelCase (JS) or snake_case (PHP) keys, keep only the type's fields. */
    private function extractPayload(string $type, array $data): array
    {
        [, $fields] = self::DOMAIN[$type];

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

        return $payload;
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
     * Human-readable detail rows for the request's domain record
     * (dates, reasons, items, totals…) shown in the detail modal.
     */
    public function details(UserRequest $request): array
    {
        [$model] = self::DOMAIN[$request->type];
        $record = $request->related_id ? $model::find($request->related_id) : null;
        if (! $record) {
            return [];
        }

        $out = [];
        foreach (self::DOMAIN[$request->type][1] as $field) {
            if ($field === 'user_id') {
                continue;
            }

            $value = $record->{$field} ?? null;

            // Attachment: only shown when a file was uploaded, as a download link.
            if ($field === 'file_path') {
                if (! $value) {
                    continue;
                }
                $out[] = [
                    'label' => 'Attachment',
                    'kind' => 'file',
                    'value' => '/storage/'.$value,
                    'name' => basename($value),
                ];
                continue;
            }

            $out[] = match ($field) {
                'entries' => $this->tableDetail('Daily Entries', ['Date', 'Day', 'Hours', 'Leave'], $this->entryRows($value)),
                'items' => $this->tableDetail('Items', ['Item', 'Qty', 'Unit Cost', 'Total'], $this->itemRows($value)),
                'lines' => $this->tableDetail('Lines', ['Description', 'Amount'], $this->lineRows($value)),
                'costs' => $this->tableDetail('Cost Breakdown', ['Item', 'Amount'], $this->costRows($value)),
                'records' => $this->tableDetail('Fuel Records', ['Date', 'Odometer', 'Liters', 'Cost'], $this->fuelRows($value)),
                'month' => [
                    'label' => 'Month',
                    'kind' => 'text',
                    'value' => \Carbon\Carbon::create()->month((int) $record->month)->translatedFormat('F'),
                ],
                default => [
                    'label' => $this->fieldLabel($field),
                    'kind' => 'text',
                    'value' => $this->formatValue($value),
                ],
            };
        }

        return $out;
    }

    private function tableDetail(string $label, array $columns, array $rows): array
    {
        return ['label' => $label, 'kind' => 'table', 'columns' => $columns, 'rows' => $rows];
    }

    /** Timesheet entries — only days with hours or leave, so the list stays readable. */
    private function entryRows(?array $entries): array
    {
        $rows = [];
        foreach ($entries ?? [] as $e) {
            $hours = (float) ($e['hours'] ?? 0);
            $leave = $e['leave'] ?? null;
            if ($hours <= 0 && ! $leave) {
                continue;
            }
            $rows[] = [
                $this->fmtDate($e['date'] ?? ''),
                $e['day'] ?? '—',
                $hours > 0 ? number_format($hours, 2) : '—',
                $leave ?: '—',
            ];
        }

        return $rows;
    }

    private function itemRows(?array $items): array
    {
        $rows = [];
        foreach ($items ?? [] as $i) {
            $qty = (float) ($i['qty'] ?? 0);
            $unit = (float) ($i['unitCost'] ?? $i['unit_cost'] ?? 0);
            $rows[] = [
                $i['name'] ?? '—',
                (string) $qty,
                '$'.number_format($unit, 2),
                '$'.number_format($qty * $unit, 2),
            ];
        }

        return $rows;
    }

    private function lineRows(?array $lines): array
    {
        $rows = [];
        foreach ($lines ?? [] as $l) {
            $rows[] = [
                $l['description'] ?? '—',
                '$'.number_format((float) ($l['amount'] ?? 0), 2),
            ];
        }

        return $rows;
    }

    private function costRows(?array $costs): array
    {
        $rows = [];
        foreach ($costs ?? [] as $item => $amount) {
            $rows[] = [\Illuminate\Support\Str::headline((string) $item), '$'.number_format((float) $amount, 2)];
        }

        return $rows;
    }

    private function fuelRows(?array $records): array
    {
        $rows = [];
        foreach ($records ?? [] as $r) {
            $rows[] = [
                $this->fmtDate($r['date'] ?? ''),
                (string) ($r['mileage'] ?? '—'),
                number_format((float) ($r['liters'] ?? 0), 2),
                '$'.number_format((float) ($r['cost'] ?? 0), 2),
            ];
        }

        return $rows;
    }

    private function fmtDate(?string $date): string
    {
        if (! $date) {
            return '—';
        }

        try {
            return now()->parse($date)->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $date;
        }
    }

    private function fieldLabel(string $field): string
    {
        return match ($field) {
            'type' => 'Type',
            'start_date' => 'Start Date',
            'end_date' => 'End Date',
            'days' => 'Days',
            'reason' => 'Reason',
            'justification' => 'Justification',
            'date' => 'Date',
            'start_time' => 'Start Time',
            'end_time' => 'End Time',
            'hours' => 'Hours',
            'purpose' => 'Purpose',
            'destination' => 'Destination',
            'transport' => 'Transportation',
            'accommodation' => 'Accommodation',
            'total_cost' => 'Total Cost',
            'total' => 'Total',
            'required_date' => 'Required Date',
            'department' => 'Department',
            'items' => 'Items',
            'costs' => 'Costs',
            'lines' => 'Lines',
            'entries' => 'Entries',
            'records' => 'Records',
            'total_hours' => 'Total Hours',
            'total_liters' => 'Total Liters',
            'total_cost_currency' => 'Total Cost',
            'month' => 'Month',
            'year' => 'Year',
            'voucher_no' => 'Voucher No.',
            'expense_type' => 'Expense Type',
            'description' => 'Description',
            'payment_method' => 'Payment Method',
            'mileage' => 'Mileage',
            'liters' => 'Liters',
            'cost' => 'Cost',
            'vehicle_id' => 'Vehicle',
            'supervisor_id' => 'Supervisor',
            default => \Illuminate\Support\Str::headline($field),
        };
    }

    private function formatValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('d/m/Y');
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        if (is_array($value)) {
            return collect($value)
                ->map(fn ($v) => is_array($v)
                    ? collect($v)->map(fn ($x, $k) => "{$k}: {$x}")->implode(' · ')
                    : (string) $v)
                ->implode("\n");
        }

        return (string) $value;
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

    /**
     * Count leave days excluding weekends and public holidays.
     */
    private function countWorkingDays($start, $end): int
    {
        $from = now()->parse($start)->startOfDay();
        $to = now()->parse($end)->startOfDay();
        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        $holidays = \App\Models\Holiday::datesForYear((int) $from->year)
            + ($to->year !== $from->year ? \App\Models\Holiday::datesForYear((int) $to->year) : []);

        $days = 0;
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $isWeekend = $d->isWeekend();
            $isHoliday = isset($holidays[$d->toDateString()]);
            if (! $isWeekend && ! $isHoliday) {
                $days++;
            }
        }

        return max(1, $days);
    }

    private function hydrate(string $type, User $user, array $payload): array
    {
        switch ($type) {
            case 'Leave':
                $payload['days'] ??= $this->countWorkingDays($payload['start_date'], $payload['end_date']);
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
