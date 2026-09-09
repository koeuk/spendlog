<?php

namespace App\Http\Resources;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ActivityLog
 */
class ActivityLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'action' => $this->action,
            // "expense", "income", "budget", "category", "savings_plan",
            // "savings_entry" — the kind, not the class.
            'subject' => $this->subjectKind(),
            'label' => $this->subject_label,
            'changes' => $this->changes,
            // Always present, so a client listing everyone's activity need not
            // ask for a second shape.
            'user' => [
                'uuid' => $this->user?->uuid,
                'name' => $this->user?->name,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
