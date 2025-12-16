<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassTemplateResource extends JsonResource
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
            'name' => $this->title, // Map title to name for frontend
            'description' => $this->description,
            'duration_minutes' => $this->duration_min, // Map duration_min to duration_minutes
            'capacity' => $this->capacity,
            'credits_required' => $this->credits_required ?? 1, // Default to 1 if not set
            'tags' => $this->tags,
            'status' => $this->status,
            'color_hex' => $this->color_hex ?? $this->getDefaultColor(), // Generate color based on title
            'weekly_rrule' => $this->weekly_rrule,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Get default color based on class title
     */
    private function getDefaultColor(): string
    {
        $colorMap = [
            'Functional Training' => '#3B82F6', // Blue
            'Yoga' => '#10B981', // Green
            'HIIT' => '#EF4444', // Red
            'Pilates' => '#8B5CF6', // Purple
            'Spinning' => '#F59E0B', // Orange
        ];

        return $colorMap[$this->title] ?? '#6B7280'; // Gray as default
    }
}
