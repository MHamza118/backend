<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerDashboardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'customer' => new CustomerResource($this->resource['customer']),
            'loyalty_tier' => $this->resource['loyalty_tier'],
            'recent_orders' => $this->resource['recent_orders'] ?? [],
            'favorite_items' => $this->resource['favorite_items'] ?? [],
            'available_rewards' => $this->resource['available_rewards'] ?? [],
            'announcements' => AnnouncementResource::collection($this->resource['announcements'] ?? []),
            'event_notifications' => AnnouncementResource::collection($this->resource['event_notifications'] ?? []),
            'stats' => [
                'points_to_next_tier' => $this->getPointsToNextTier(),
                'tier_progress' => $this->getTierProgress(),
            ]
        ];
    }

    /**
     * Calculate points needed for next tier
     */
    private function getPointsToNextTier(): int
    {
        $currentPoints = $this->resource['customer']->loyalty_points;
        $currentTier = $this->resource['loyalty_tier'];

        switch (strtolower($currentTier)) {
            case 'bronze':
                return 500 - $currentPoints;
            case 'silver':
                return 1000 - $currentPoints;
            case 'gold':
                return 2500 - $currentPoints;
            case 'platinum':
                return 0; // Already at highest tier
            default:
                return 500 - $currentPoints;
        }
    }

    /**
     * Calculate tier progress percentage
     */
    private function getTierProgress(): float
    {
        $currentPoints = $this->resource['customer']->loyalty_points;
        $currentTier = $this->resource['loyalty_tier'];

        switch (strtolower($currentTier)) {
            case 'bronze':
                return min(($currentPoints / 500) * 100, 100);
            case 'silver':
                return min((($currentPoints - 500) / 500) * 100, 100);
            case 'gold':
                return min((($currentPoints - 1000) / 1500) * 100, 100);
            case 'platinum':
                return 100;
            default:
                return min(($currentPoints / 500) * 100, 100);
        }
    }

    /**
     * Get additional data that should be returned with the resource array.
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'version' => '1.0',
                'timestamp' => now()->toISOString(),
                'dashboard_type' => 'customer',
            ],
        ];
    }
}
