<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'staffId' => $this->staff_id,
            'firstName' => $this->first_name,
            'lastName' => $this->last_name,
            'nameKh' => $this->name_kh,
            'fullName' => $this->full_name,
            'email' => $this->email,
            'role' => $this->role,
            'roleLabel' => $this->roleModel?->label ?? ucfirst($this->role),
            'status' => $this->status ?? 'Active',
            'permissions' => $this->allPermissions(),
            'position' => $this->position,
            'department' => $this->department?->name,
            'phone' => $this->phone,
            'phoneAlt' => $this->phone_alt,
            'emailAlt' => $this->email_alt,
            'address' => $this->address,
            'addrHouse' => $this->addr_house,
            'addrStreet' => $this->addr_street,
            'addrVillage' => $this->addr_village,
            'addrCommune' => $this->addr_commune,
            'addrDistrict' => $this->addr_district,
            'addrProvince' => $this->addr_province,
            'addrPostal' => $this->addr_postal,
            'notesToOrg' => $this->notes_to_org,
            'declaredAt' => $this->declaration_accepted_at?->toISOString(),
            'photoUrl' => $this->photo_path ? '/storage/'.$this->photo_path : null,
            'signatureUrl' => $this->signature_path ? '/storage/'.$this->signature_path : null,
            'dob' => $this->dob?->toDateString(),
            'gender' => $this->gender,
            'pob' => $this->pob,
            'nationality' => $this->nationality,
            'nid' => $this->nid,
            'marital' => $this->marital,
        ];
    }
}
