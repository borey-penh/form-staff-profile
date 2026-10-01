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
            'address' => $this->address,
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
