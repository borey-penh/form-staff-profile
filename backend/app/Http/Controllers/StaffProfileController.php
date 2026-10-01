<?php

namespace App\Http\Controllers;

use App\Models\StaffProfile;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StaffProfileController extends Controller
{
    public function create()
    {
        return Inertia::render('StaffProfile/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nameEn' => ['required', 'string', 'max:255'],
            'nameKh' => ['nullable', 'string', 'max:255'],
            'gender' => ['required', 'in:Male,Female,Other'],
            'dob' => ['required', 'date', 'before:today'],
            'pob' => ['required', 'string', 'max:255'],
            'nid' => ['required', 'string', 'max:255'],
            'addr' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:32'],
            'email' => ['required', 'email', 'max:255'],
            'marital' => ['required', 'in:Married,Single'],
            'spouseName' => ['required_if:marital,Married', 'nullable', 'string', 'max:255'],
            'spouseOcc' => ['nullable', 'string', 'max:255'],
            'children' => ['nullable', 'array'],
            'children.*.name' => ['required_with:children', 'string', 'max:255'],
            'children.*.rel' => ['required_with:children', 'in:Son,Daughter'],
            'children.*.dob' => ['required_with:children', 'date'],
            'ecName' => ['required', 'string', 'max:255'],
            'ecRel' => ['required', 'string', 'max:255'],
            'ecPhone' => ['required', 'string', 'max:32'],
            'ecAddr' => ['required', 'string', 'max:255'],
            'beneficiaries' => ['required', 'array', 'min:1'],
            'beneficiaries.*.name' => ['required', 'string', 'max:255'],
            'beneficiaries.*.rel' => ['required', 'string', 'max:255'],
            'beneficiaries.*.dob' => ['nullable', 'date'],
            'beneficiaries.*.cert' => ['nullable', 'string', 'max:255'],
            'beneficiaries.*.contact' => ['nullable', 'string', 'max:32'],
            'beneficiaries.*.addr' => ['nullable', 'string', 'max:255'],
            'beneficiaries.*.share' => ['required', 'numeric', 'min:0', 'max:100'],
            'confirmed' => ['required', 'boolean', 'accepted'],
            'signature' => ['nullable', 'string'],
        ]);

        // Beneficiary shares must total exactly 100%
        $total = array_sum(array_column($validated['beneficiaries'], 'share'));
        if (abs($total - 100) > 0.01) {
            return back()->withErrors([
                'beneficiaries' => "Beneficiary shares must total exactly 100% (currently {$total}%).",
            ]);
        }

        $profile = StaffProfile::create([
            'name_en' => $validated['nameEn'],
            'name_kh' => $validated['nameKh'] ?? null,
            'gender' => $validated['gender'],
            'dob' => $validated['dob'],
            'pob' => $validated['pob'],
            'nid' => $validated['nid'],
            'addr' => $validated['addr'],
            'phone' => $validated['phone'],
            'email' => $validated['email'],
            'marital' => $validated['marital'],
            'spouse_name' => $validated['spouseName'] ?? null,
            'spouse_occ' => $validated['spouseOcc'] ?? null,
            'children' => $validated['children'] ?? [],
            'ec_name' => $validated['ecName'],
            'ec_rel' => $validated['ecRel'],
            'ec_phone' => $validated['ecPhone'],
            'ec_addr' => $validated['ecAddr'],
            'beneficiaries' => $validated['beneficiaries'],
            'confirmed' => $validated['confirmed'],
            'signature' => $validated['signature'] ?? null,
        ]);

        return redirect()
            ->route('staff-profiles.create')
            ->with('success', "The staff profile for {$profile->name_en} has been saved to your employment record.");
    }
}
