<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Beneficiary;
use App\Models\Child;
use App\Models\EmergencyContact;
use App\Models\Expertise;
use App\Models\GeographicExperience;
use App\Models\LanguageSkill;
use App\Models\ProfileChangeRequest;
use App\Models\Qualification;
use App\Models\Spouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /**
     * Identity fields staff cannot edit directly — they must send a
     * change request to HR/Admin instead. Contact fields stay editable.
     */
    public const LOCKED_FIELDS = [
        'firstName', 'lastName', 'nameKh', 'dob', 'gender',
        'pob', 'nationality', 'nid', 'marital',
    ];

    /** Full profile payload for the wizard. */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load([
            'qualifications', 'children', 'emergencyContacts', 'spouse', 'documents',
            'expertises', 'languageSkills', 'geographicExperiences', 'beneficiaries',
        ]);

        $changes = $user->profileChangeRequests()->orderByDesc('created_at')->limit(30)->get();

        return response()->json([
            'user' => new UserResource($user),
            'qualifications' => $user->qualifications->map(fn ($q) => $this->serializeQualification($q)),
            'children' => $user->children->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'dob' => $c->dob?->toDateString(),
                'gender' => $c->gender,
                'status' => $c->status,
            ]),
            'spouse' => $user->spouse ? [
                'name' => $user->spouse->name,
                'occupation' => $user->spouse->occupation,
                'phone' => $user->spouse->phone,
            ] : null,
            'emergencyContacts' => $user->emergencyContacts->map(fn ($e) => [
                'id' => $e->id,
                'name' => $e->name,
                'relationship' => $e->relationship,
                'phone' => $e->phone,
                'addr' => $e->addr,
            ]),
            'documents' => $user->documents->map(fn ($d) => $this->serializeDocument($d)),
            'expertise' => $user->expertises->map(fn ($e) => [
                'id' => $e->id,
                'name' => $e->name,
            ]),
            'motherTongues' => $user->languageSkills->where('is_mother_tongue', true)->values()->map(fn ($l) => [
                'id' => $l->id,
                'language' => $l->language,
            ]),
            'languages' => $user->languageSkills->where('is_mother_tongue', false)->values()->map(fn ($l) => [
                'id' => $l->id,
                'language' => $l->language,
                'reading' => $l->reading,
                'writing' => $l->writing,
                'speaking' => $l->speaking,
                'understanding' => $l->understanding,
            ]),
            'geography' => $user->geographicExperiences->map(fn ($g) => [
                'id' => $g->id,
                'country' => $g->country,
                'province' => $g->province,
            ]),
            'beneficiaries' => $user->beneficiaries->map(fn ($b) => $this->serializeBeneficiary($b)),
            'lockedFields' => self::LOCKED_FIELDS,
            'changeRequests' => $changes->map(fn (ProfileChangeRequest $r) => [
                'id' => $r->id,
                'field' => $r->field,
                'fieldLabel' => $this->fieldLabel($r->field),
                'currentValue' => $r->current_value,
                'requestedValue' => $r->requested_value,
                'reason' => $r->reason,
                'status' => $r->status,
                'reviewNote' => $r->review_note,
                'submittedAt' => $r->created_at?->toISOString(),
            ]),
        ]);
    }

    /** Tab 1 — personal info. */
    public function updatePersonal(Request $request): UserResource
    {
        $data = $request->validate([
            'firstName' => ['required', 'string', 'max:255'],
            'lastName' => ['required', 'string', 'max:255'],
            'nameKh' => ['nullable', 'string', 'max:255'],
            'dob' => ['required', 'date'],
            'gender' => ['required', 'in:Male,Female,Other,Non-binary,Prefer not to say'],
            'pob' => ['required', 'string', 'max:255'],
            'nationality' => ['nullable', 'string', 'max:255'], // citizenship (if foreigner)
            'nid' => ['required', 'string', 'max:64'],
            'phone' => ['required', 'string', 'max:32'],
            'phoneAlt' => ['nullable', 'string', 'max:32'],
            'email' => ['required', 'email'],
            'emailAlt' => ['nullable', 'email', 'max:255'],
            'addrHouse' => ['nullable', 'string', 'max:64'],
            'addrStreet' => ['nullable', 'string', 'max:128'],
            'addrVillage' => ['nullable', 'string', 'max:128'],
            'addrCommune' => ['nullable', 'string', 'max:128'],
            'addrDistrict' => ['nullable', 'string', 'max:128'],
            'addrProvince' => ['nullable', 'string', 'max:128'],
            'addrPostal' => ['nullable', 'string', 'max:16'],
            // Marital is edited from the Family tab / change requests; accepted for legacy clients only.
            'marital' => ['nullable', 'in:Single,Married,Divorced,Widowed'],
        ]);

        $map = [
            'firstName' => 'first_name', 'lastName' => 'last_name', 'nameKh' => 'name_kh',
            'dob' => 'dob', 'gender' => 'gender', 'pob' => 'pob', 'nationality' => 'nationality',
            'nid' => 'nid', 'phone' => 'phone', 'phoneAlt' => 'phone_alt', 'email' => 'email',
            'emailAlt' => 'email_alt', 'addrHouse' => 'addr_house', 'addrStreet' => 'addr_street',
            'addrVillage' => 'addr_village', 'addrCommune' => 'addr_commune',
            'addrDistrict' => 'addr_district', 'addrProvince' => 'addr_province',
            'addrPostal' => 'addr_postal',
        ];

        $payload = [];
        foreach ($map as $camel => $snake) {
            if (in_array($camel, self::LOCKED_FIELDS, true)) {
                continue; // locked — only HR/Admin can change these
            }
            $payload[$snake] = $data[$camel] ?? null;
        }

        // Keep the flat `address` column in sync for the rest of the app.
        $payload['address'] = collect([
            $data['addrHouse'] ?? null, $data['addrStreet'] ?? null, $data['addrVillage'] ?? null,
            $data['addrCommune'] ?? null, $data['addrDistrict'] ?? null,
            $data['addrProvince'] ?? null, $data['addrPostal'] ?? null,
        ])->filter()->implode(', ');

        $request->user()->update($payload);

        return new UserResource($request->user()->fresh());
    }

    /** Staff suggests an edit to a locked field; HR/Admin reviews it. */
    public function submitChangeRequest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'field' => ['required', 'string', 'in:'.implode(',', self::LOCKED_FIELDS)],
            'requestedValue' => ['required', 'string', 'max:1000'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $user = $request->user();

        $pending = $user->profileChangeRequests()
            ->where('field', $data['field'])
            ->where('status', 'Pending')
            ->exists();

        if ($pending) {
            return response()->json([
                'message' => 'You already have a pending change request for this field.',
            ], 422);
        }

        $column = \Illuminate\Support\Str::snake($data['field']);
        $current = (string) ($user->{$column} ?? '');

        if ($current === $data['requestedValue']) {
            return response()->json(['message' => 'The requested value is the same as the current one.'], 422);
        }

        $change = ProfileChangeRequest::create([
            'user_id' => $user->id,
            'field' => $data['field'],
            'current_value' => $current,
            'requested_value' => $data['requestedValue'],
            'reason' => $data['reason'],
            'status' => 'Pending',
        ]);

        $user->activities()->create([
            'icon' => 'profile',
            'message' => "You requested a profile change to {$this->fieldLabel($data['field'])}",
        ]);

        return response()->json([
            'message' => 'Change request submitted. HR will review your suggestion.',
            'id' => $change->id,
        ], 201);
    }

    /** Cancel the staff member's own pending change request. */
    public function cancelChangeRequest(Request $request, int $id): JsonResponse
    {
        $change = $request->user()->profileChangeRequests()->findOrFail($id);

        if ($change->status !== 'Pending') {
            return response()->json(['message' => 'Only pending requests can be cancelled.'], 422);
        }

        $change->delete();

        return response()->json(['message' => 'Change request cancelled.']);
    }

    private function fieldLabel(string $field): string
    {
        return match ($field) {
            'firstName' => 'First Name', 'lastName' => 'Last Name',
            'nameKh' => 'Name (Khmer)', 'dob' => 'Date of Birth',
            'gender' => 'Sex', 'pob' => 'Place of Birth',
            'nationality' => 'Citizenship', 'nid' => 'ID / Passport Number',
            'marital' => 'Marital Status', 'phone' => 'Phone Number',
            'email' => 'Email', 'address' => 'Current Address',
            default => \Illuminate\Support\Str::headline($field),
        };
    }

    /** Tab 2 — education, training, employment history and memberships (replace-all sync). */
    public function saveQualifications(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.type' => ['required', 'in:education,experience,training,membership'],
            'items.*.title' => ['required', 'string', 'max:255'],
            'items.*.institution' => ['required', 'string', 'max:255'],
            'items.*.field' => ['nullable', 'string', 'max:255'],
            'items.*.startDate' => ['nullable', 'date'],
            'items.*.endDate' => ['nullable', 'date'],
            'items.*.description' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $user->qualifications()->delete();

        foreach ($data['items'] as $item) {
            Qualification::create([
                'user_id' => $user->id,
                'type' => $item['type'],
                'title' => $item['title'],
                'institution' => $item['institution'],
                'field' => $item['field'] ?? null,
                'start_date' => $item['startDate'] ?? null,
                'end_date' => $item['endDate'] ?? null,
                'description' => $item['description'] ?? null,
            ]);
        }

        return response()->json(['message' => 'Qualifications saved.']);
    }

    /** Tab 2 — expertise, mother tongues, language proficiency and geographic experience. */
    public function saveSkills(Request $request): JsonResponse
    {
        $data = $request->validate([
            'expertise' => ['present', 'array'],
            'expertise.*.name' => ['required', 'string', 'max:255'],
            'motherTongues' => ['present', 'array'],
            'motherTongues.*.language' => ['required', 'string', 'max:64'],
            'languages' => ['present', 'array'],
            'languages.*.language' => ['required', 'string', 'max:64'],
            'languages.*.reading' => ['nullable', 'in:Fluent,Good,Fair,Basic'],
            'languages.*.writing' => ['nullable', 'in:Fluent,Good,Fair,Basic'],
            'languages.*.speaking' => ['nullable', 'in:Fluent,Good,Fair,Basic'],
            'languages.*.understanding' => ['nullable', 'in:Fluent,Good,Fair,Basic'],
            'geography' => ['present', 'array'],
            'geography.*.country' => ['required', 'string', 'max:128'],
            'geography.*.province' => ['nullable', 'string', 'max:128'],
        ]);

        $user = $request->user();

        $user->expertises()->delete();
        foreach ($data['expertise'] as $e) {
            Expertise::create(['user_id' => $user->id, 'name' => $e['name']]);
        }

        $user->languageSkills()->delete();
        foreach ($data['motherTongues'] as $l) {
            LanguageSkill::create([
                'user_id' => $user->id,
                'language' => $l['language'],
                'is_mother_tongue' => true,
            ]);
        }
        foreach ($data['languages'] as $l) {
            LanguageSkill::create([
                'user_id' => $user->id,
                'language' => $l['language'],
                'is_mother_tongue' => false,
                'reading' => $l['reading'] ?? null,
                'writing' => $l['writing'] ?? null,
                'speaking' => $l['speaking'] ?? null,
                'understanding' => $l['understanding'] ?? null,
            ]);
        }

        $user->geographicExperiences()->delete();
        foreach ($data['geography'] as $g) {
            GeographicExperience::create([
                'user_id' => $user->id,
                'country' => $g['country'],
                'province' => $g['province'] ?? null,
            ]);
        }

        return response()->json(['message' => 'Skills and qualifications saved.']);
    }

    /** Tab 3 — family (spouse, children, emergency contacts, beneficiaries). */
    public function saveFamily(Request $request): JsonResponse
    {
        $data = $request->validate([
            'spouse' => ['nullable', 'array'],
            'spouse.name' => ['nullable', 'string', 'max:255'],
            'spouse.occupation' => ['nullable', 'string', 'max:255'],
            'spouse.phone' => ['nullable', 'string', 'max:32'],
            'children' => ['nullable', 'array'],
            'children.*.name' => ['required_with:children', 'string', 'max:255'],
            'children.*.dob' => ['nullable', 'date'],
            'children.*.gender' => ['nullable', 'in:Male,Female,Other'],
            'children.*.status' => ['nullable', 'in:Alive,Deceased'],
            'emergencyContacts' => ['required', 'array', 'min:1'],
            'emergencyContacts.*.name' => ['required', 'string', 'max:255'],
            'emergencyContacts.*.relationship' => ['required', 'string', 'max:255'],
            'emergencyContacts.*.phone' => ['required', 'string', 'max:32'],
            'emergencyContacts.*.email' => ['nullable', 'email', 'max:255'],
            'emergencyContacts.*.addr' => ['nullable', 'string', 'max:500'],
            'beneficiaries' => ['nullable', 'array'],
            'beneficiaries.*.fullName' => ['required_with:beneficiaries', 'string', 'max:255'],
            'beneficiaries.*.dob' => ['nullable', 'date'],
            'beneficiaries.*.idNumber' => ['nullable', 'string', 'max:64'],
            'beneficiaries.*.relationship' => ['nullable', 'string', 'max:255'],
            'beneficiaries.*.contact' => ['nullable', 'string', 'max:32'],
            'beneficiaries.*.address' => ['nullable', 'string', 'max:500'],
            'beneficiaries.*.share' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $user = $request->user();

        $user->spouse()->delete();
        if (! empty($data['spouse']['name'])) {
            Spouse::create([
                'user_id' => $user->id,
                'name' => $data['spouse']['name'] ?? null,
                'occupation' => $data['spouse']['occupation'] ?? null,
                'phone' => $data['spouse']['phone'] ?? null,
            ]);
        }

        $user->children()->delete();
        foreach ($data['children'] ?? [] as $c) {
            Child::create([
                'user_id' => $user->id,
                'name' => $c['name'],
                'dob' => $c['dob'] ?? null,
                'gender' => $c['gender'] ?? null,
                'status' => $c['status'] ?? 'Alive',
            ]);
        }

        $user->emergencyContacts()->delete();
        foreach ($data['emergencyContacts'] as $e) {
            EmergencyContact::create([
                'user_id' => $user->id,
                'name' => $e['name'],
                'relationship' => $e['relationship'],
                'phone' => $e['phone'],
                'email' => $e['email'] ?? null,
                'addr' => $e['addr'] ?? null,
            ]);
        }

        $user->beneficiaries()->delete();
        foreach ($data['beneficiaries'] ?? [] as $b) {
            Beneficiary::create([
                'user_id' => $user->id,
                'full_name' => $b['fullName'],
                'dob' => $b['dob'] ?? null,
                'id_number' => $b['idNumber'] ?? null,
                'relationship' => $b['relationship'] ?? null,
                'contact' => $b['contact'] ?? null,
                'address' => $b['address'] ?? null,
                'share' => $b['share'] ?? 0,
            ]);
        }

        return response()->json(['message' => 'Family information saved.']);
    }

    /** Tab 4 — upload a supporting document (PDF, JPG or PNG). */
    public function uploadDocument(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'remark' => ['nullable', 'string', 'max:1000'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $path = $request->file('file')->store('documents', 'public');

        $doc = $request->user()->documents()->create([
            'type' => $request->input('type'),
            'description' => $request->input('description'),
            'remark' => $request->input('remark'),
            'file_path' => $path,
            'original_name' => $request->file('file')->getClientOriginalName(),
            'status' => 'Pending',
        ]);

        return response()->json([
            'message' => 'Document uploaded.',
            'document' => [
                'id' => $doc->id,
                'type' => $doc->type,
                'originalName' => $doc->original_name,
                'status' => $doc->status,
                'url' => '/storage/'.$doc->file_path,
            ],
        ], 201);
    }

    /** Edit a document's title, description or remark. */
    public function updateDocument(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'remark' => ['nullable', 'string', 'max:1000'],
        ]);

        $doc = $request->user()->documents()->findOrFail($id);
        $doc->update($data);

        return response()->json([
            'message' => 'Document updated.',
            'document' => $this->serializeDocument($doc->fresh()),
        ]);
    }

    /** Tab 4 — free-form notes to the organization. */
    public function saveNotes(Request $request): JsonResponse
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $request->user()->update(['notes_to_org' => $data['notes'] ?? null]);

        return response()->json(['message' => 'Notes saved.']);
    }

    /** Tab 5 — submit the declaration. */
    public function declare(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->update(['declaration_accepted_at' => now()]);

        $user->activities()->create([
            'icon' => 'profile',
            'message' => 'You submitted your personnel declaration',
        ]);

        return response()->json(['message' => 'Declaration submitted. Thank you.']);
    }

    /** Upload or replace the profile photo (shown as the avatar). */
    public function uploadPhoto(Request $request): JsonResponse
    {
        $data = $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $user = $request->user();

        if ($user->photo_path) {
            Storage::disk('public')->delete($user->photo_path); // replace the old file
        }

        $file = $data['photo'];
        $name = now()->format('YmdHis').'-'.uniqid().'.'.strtolower($file->getClientOriginalExtension());
        $user->update(['photo_path' => $file->storeAs('photos', $name, 'public')]);

        $user->activities()->create([
            'icon' => 'profile',
            'message' => 'You updated your profile photo',
        ]);

        return response()->json([
            'message' => 'Profile photo updated.',
            'user' => new UserResource($user->fresh()),
        ]);
    }

    /** Remove the profile photo (the avatar falls back to initials). */
    public function deletePhoto(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->photo_path) {
            Storage::disk('public')->delete($user->photo_path);
            $user->update(['photo_path' => null]);
        }

        return response()->json([
            'message' => 'Profile photo removed.',
            'user' => new UserResource($user->fresh()),
        ]);
    }

    /** Delete a document. */
    public function deleteDocument(Request $request, int $id): JsonResponse
    {
        $doc = $request->user()->documents()->findOrFail($id);
        Storage::disk('public')->delete($doc->file_path);
        $doc->delete();

        return response()->json(['message' => 'Document deleted.']);
    }

    private function serializeQualification(Qualification $q): array
    {
        return [
            'id' => $q->id,
            'type' => $q->type,
            'title' => $q->title,
            'institution' => $q->institution,
            'field' => $q->field,
            'startDate' => $q->start_date?->toDateString(),
            'endDate' => $q->end_date?->toDateString(),
            'description' => $q->description,
        ];
    }

    private function serializeDocument($d): array
    {
        return [
            'id' => $d->id,
            'type' => $d->type,
            'description' => $d->description,
            'remark' => $d->remark,
            'originalName' => $d->original_name,
            'status' => $d->status,
            'url' => '/storage/'.$d->file_path,
            'uploadedAt' => $d->created_at?->toISOString(),
        ];
    }

    private function serializeBeneficiary(Beneficiary $b): array
    {
        return [
            'id' => $b->id,
            'fullName' => $b->full_name,
            'dob' => $b->dob?->toDateString(),
            'idNumber' => $b->id_number,
            'relationship' => $b->relationship,
            'contact' => $b->contact,
            'address' => $b->address,
            'share' => $b->share !== null ? (float) $b->share : null,
        ];
    }
}
