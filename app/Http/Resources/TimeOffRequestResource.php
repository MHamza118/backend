<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimeOffRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee_name' => $this->when($this->relationLoaded('employee'), function () {
                return $this->employee ? trim("{$this->employee->first_name} {$this->employee->last_name}") : null;
            }),
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date->toDateString(),
            'type' => $this->type,
            'type_label' => \App\Models\TimeOffRequest::TYPES[$this->type] ?? $this->type,
            'reason' => $this->reason,
            'notes' => $this->notes,
            'status' => $this->status,
            'status_label' => \App\Models\TimeOffRequest::STATUSES[$this->status] ?? $this->status,
            'submitted_at' => $this->submitted_at?->toISOString(),
            'decided_at' => $this->decided_at?->toISOString(),
            'approved_by' => $this->approved_by,
            'decision_notes' => $this->decision_notes,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'employee' => $this->when($this->relationLoaded('employee') && $this->employee, [
                'id' => $this->employee->id,
                'name' => trim("{$this->employee->first_name} {$this->employee->last_name}"),
                'email' => $this->employee->email,
                'location' => $this->employee->location,
            ]),
        ];
    }
}
