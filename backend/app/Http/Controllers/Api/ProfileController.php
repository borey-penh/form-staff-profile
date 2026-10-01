<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Child;
use App\Models\EmergencyContact;
use App\Models\Qualification;
use App\Models\Spouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /** Full profile payload for the wizard. */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load(['qualifications', 'children', 'emergencyContacts', 'spouse', 'documents']);

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
        ]);
    }

    /** Step 1 — personal info. */
    public function updatePersonal(Request $request): UserResource
    {
        $data = $request->validate([
            'firstName' => ['required', 'string', 'max:255'],
            'lastName' => ['required', 'string', 'max:255'],
            'nameKh' => ['nullable', 'string', 'max:255'],
            'dob' => ['required', 'date'],
            'gender' => ['required', 'in:Male,Female,Other'],
            'pob' => ['required', 'string', 'max:255'],
            'nationality' => ['required', 'string', 'max:255'],
            'nid' => ['required', 'string', 'max:64'],
            'marital' => ['required', 'in:Single,Married,Divorced,Widowed'],
            'phone' => ['required', 'string', 'max:32'],
            'email' => ['required', 'email'],
            'address' => ['required', 'string', 'max:1000'],
        ]);

        $map = [
            'firstName' => 'first_name', 'lastName' => 'last_name', 'nameKh' => 'name_kh',
            'dob' => 'dob', 'gender' => 'gender', 'pob' => 'pob', 'nationality' => 'nationality',
            'nid' => 'nid', 'marital' => 'marital', 'phone' => 'phone', 'email' => 'email',
            'address' => 'address',
        ];

        $payload = [];
        foreach ($map as $camel => $snake) {
            $payload[$snake] = $data[$camel];
        }

        $request->user()->update($payload);

        return new UserResource($request->user()->fresh());
    }

    /** Step 2 — qualifications (replace-all sync). */
    public function saveQualifications(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.type' => ['required', 'in:education,experience'],
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

    /** Step 3 — family (spouse, children, emergency contacts). */
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
            'emergencyContacts.*.addr' => ['nullable', 'string', 'max:500'],
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
                'addr' => $e['addr'] ?? null,
            ]);
        }

        return response()->json(['message' => 'Family information saved.']);
    }

    /** Step 4 — upload a supporting document. */
    public function uploadDocument(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['required', 'string', 'max:255'],
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $path = $request->file('file')->store('documents', 'public');

        $doc = $request->user()->documents()->create([
            'type' => $request->input('type'),
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

    /** Step 5 — submit declaration with signature. */
    public function declare(Request $request): JsonResponse
    {
        $data = $request->validate([
            'signature' => ['required', 'string'], // data URL
        ]);

        $path = $this->storeDataUrl($data['signature'], 'signatures/declaration');

        $request->user()->update(['signature_path' => $path]);

        $request->user()->activities()->create([
            'icon' => 'profile',
            'message' => 'You submitted your personnel declaration',
        ]);

        return response()->json(['message' => 'Declaration submitted.']);
    }

    /** Delete a document. */
    public function deleteDocument(Request $request, int $id): JsonResponse
    {
        $doc = $request->user()->documents()->findOrFail($id);
        Storage::disk('public')->delete($doc->file_path);
        $doc->delete();

        return response()->json(['message' => 'Document deleted.']);
    }

    private function storeDataUrl(string $dataUrl, string $prefix): string
    {
        [$meta, $content] = explode(',', $dataUrl, 2);
        $extension = str_contains($meta, 'image/jpeg') ? 'jpg' : 'png';
        $path = "{$prefix}-".now()->format('YmdHis').'-'.uniqid().".{$extension}";

        Storage::disk('public')->put($path, base64_decode($content));

        return $path;
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
            'originalName' => $d->original_name,
            'status' => $d->status,
            'url' => '/storage/'.$d->file_path,
        ];
    }
}
