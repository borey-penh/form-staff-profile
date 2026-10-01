<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Compliance;
use App\Models\ComplianceSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ComplianceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $signedIds = $request->user()->complianceSignatures()->pluck('compliance_id');

        $items = Compliance::where('active', true)
            ->orderBy('title')
            ->get()
            ->map(fn (Compliance $c) => [
                'id' => $c->id,
                'title' => $c->title,
                'description' => $c->description,
                'policyUrl' => $c->policy_path ? '/storage/'.$c->policy_path : null,
                'signed' => $signedIds->contains($c->id),
            ]);

        return response()->json(['data' => $items]);
    }

    public function sign(Request $request): JsonResponse
    {
        $data = $request->validate([
            'complianceId' => ['required', 'integer', 'exists:compliances,id'],
            'signature' => ['required', 'string'],
        ]);

        $user = $request->user();

        $existing = ComplianceSignature::where('compliance_id', $data['complianceId'])
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            return response()->json(['message' => 'Already signed.'], 409);
        }

        [$meta, $content] = explode(',', $data['signature'], 2);
        $ext = str_contains($meta, 'image/jpeg') ? 'jpg' : 'png';
        $path = 'signatures/compliance-'.uniqid().".{$ext}";
        Storage::disk('public')->put($path, base64_decode($content));

        ComplianceSignature::create([
            'compliance_id' => $data['complianceId'],
            'user_id' => $user->id,
            'signature_path' => $path,
            'signed_at' => now(),
        ]);

        $user->activities()->create([
            'icon' => 'compliance',
            'message' => 'You signed a compliance declaration',
        ]);

        return response()->json(['message' => 'Compliance signed.'], 201);
    }
}
