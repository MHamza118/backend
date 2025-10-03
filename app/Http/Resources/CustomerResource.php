<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
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
            'name' => $this->name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'home_location' => $this->home_location,
            'loyalty_points' => $this->loyalty_points,
            'loyalty_tier' => $this->loyalty_tier,
            'total_orders' => $this->total_orders,
            'total_spent' => number_format($this->total_spent, 2),
            'preferences' => $this->preferences ?? [
                'notifications' => true,
                'marketing' => false
            ],
            'status' => $this->status,
            'last_visit' => $this->last_visit?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            
            // Location data for frontend compatibility
            'locations' => $this->when($this->relationLoaded('locations'), function () {
                return $this->locations->pluck('id')->toArray();
            }, []),
            'homeLocation' => $this->when($this->relationLoaded('locations'), function () {
                return $this->locations->where('is_home', true)->first()?->id;
            }),
            'location_details' => $this->when($this->relationLoaded('locations'), function () {
                return \App\Http\Resources\CustomerLocationResource::collection($this->locations);
            }, []),
            
            // Additional conditional includes
            'favorite_items' => $this->whenLoaded('favoriteItems'),
            'recent_orders' => $this->whenLoaded('orders'),
            'rewards' => $this->whenLoaded('rewards'),
        ];
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
            ],
        ];
    }
}
