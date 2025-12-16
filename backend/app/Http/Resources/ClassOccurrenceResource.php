<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassOccurrenceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Calculate booked count - use 'booked' and 'attended' status values
        $bookedCount = $this->registrations()->whereIn('status', ['booked', 'attended'])->count();
        $availableSpots = $this->capacity - $bookedCount;

        return [
            'id' => $this->id,
            'template_id' => $this->template_id,
            'class_template' => new ClassTemplateResource($this->whenLoaded('template')), // Map template to class_template
            'trainer_id' => $this->trainer_id,
            'trainer' => new StaffProfileResource($this->whenLoaded('trainer')), // Trainer with user data
            'room_id' => $this->room_id,
            'room' => $this->whenLoaded('room', function () {
                // Use getRelationValue to avoid conflict with 'site' string attribute on Room model
                $siteRelation = $this->room->getRelationValue('site');
                $siteData = null;

                if ($siteRelation instanceof \App\Models\Site) {
                    $siteData = [
                        'id' => $siteRelation->id,
                        'name' => $siteRelation->name,
                    ];
                } elseif ($this->room->site_id) {
                    // Fallback: lookup site directly if relationship not loaded properly
                    $site = \App\Models\Site::find($this->room->site_id);
                    if ($site) {
                        $siteData = [
                            'id' => $site->id,
                            'name' => $site->name,
                        ];
                    }
                }

                return [
                    'id' => $this->room->id,
                    'name' => $this->room->name,
                    'capacity' => $this->room->capacity,
                    'site' => $siteData,
                ];
            }),
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'status' => $this->status,
            'capacity' => $this->capacity,
            'booked_count' => $bookedCount,
            'available_spots' => $availableSpots,
            'is_full' => $availableSpots <= 0,
            'google_event_id' => $this->google_event_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
