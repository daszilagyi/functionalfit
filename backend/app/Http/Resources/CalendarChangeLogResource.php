<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Calendar Change Log Resource (compact for list view)
 */
class CalendarChangeLogResource extends JsonResource
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
            'changed_at' => $this->changed_at?->toIso8601String(),
            'action' => $this->action,
            'actor' => [
                'id' => $this->actor_user_id,
                'name' => $this->actor_name,
                'role' => $this->actor_role,
            ],
            'site' => $this->getSiteName(),
            'room' => $this->room_id ? [
                'id' => $this->room_id,
                'name' => $this->room_name,
            ] : null,
            'event_time' => [
                'starts_at' => $this->starts_at?->toIso8601String(),
                'ends_at' => $this->ends_at?->toIso8601String(),
            ],
            'summary' => $this->getChangeSummary(),
        ];
    }

    /**
     * Get the site name, looking it up from the room if needed.
     */
    protected function getSiteName(): ?string
    {
        // First return the stored site value if available
        if ($this->site) {
            return $this->site;
        }

        // Otherwise, try to look up from the room relationship
        if ($this->room_id) {
            $room = \App\Models\Room::with('site')->find($this->room_id);
            if ($room) {
                // Try site relationship first (using site_id)
                // Use getRelationValue to explicitly access the relationship, not the attribute
                $siteRelation = $room->getRelationValue('site');
                if ($siteRelation instanceof \App\Models\Site) {
                    return $siteRelation->name;
                }

                // If relationship not loaded but site_id exists, lookup directly
                if ($room->site_id) {
                    $site = \App\Models\Site::find($room->site_id);
                    if ($site) {
                        return $site->name;
                    }
                }

                // Fallback to legacy site string attribute
                $legacySite = $room->getAttribute('site');
                if ($legacySite && is_string($legacySite)) {
                    return $legacySite;
                }
            }
        }

        return null;
    }
}
