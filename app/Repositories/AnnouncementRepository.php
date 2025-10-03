<?php

namespace App\Repositories;

use App\Models\Announcement;
use App\Models\Customer;
use App\Repositories\Contracts\AnnouncementRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class AnnouncementRepository implements AnnouncementRepositoryInterface
{
    protected $model;

    public function __construct(Announcement $model)
    {
        $this->model = $model;
    }

    /**
     * Get all active announcements
     */
    public function getActiveAnnouncements(): Collection
    {
        return $this->model->active()
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get announcements for specific customer
     */
    public function getActiveAnnouncementsForCustomer(Customer $customer): Collection
    {
        $announcements = $this->model->active()
            ->forCustomer($customer)
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        // Filter out dismissed announcements
        $dismissedIds = $customer->dismissedAnnouncements()->pluck('announcements.id')->toArray();
        
        $filtered = $announcements->filter(function ($announcement) use ($dismissedIds) {
            return !in_array($announcement->id, $dismissedIds);
        });
        
        // Convert back to Eloquent Collection to match return type
        return new Collection($filtered->values()->all());
    }

    /**
     * Find announcement by ID
     */
    public function findById(string $id): ?Announcement
    {
        return $this->model->find($id);
    }

    /**
     * Create new announcement
     */
    public function create(array $data): Announcement
    {
        return $this->model->create($data);
    }

    /**
     * Update announcement
     */
    public function update(string $id, array $data): ?Announcement
    {
        $announcement = $this->findById($id);
        if (!$announcement) {
            return null;
        }

        $announcement->update($data);
        return $announcement->fresh();
    }

    /**
     * Delete announcement
     */
    public function delete(string $id): bool
    {
        $announcement = $this->findById($id);
        return $announcement ? $announcement->delete() : false;
    }

    /**
     * Get announcements by type
     */
    public function getByType(string $type): Collection
    {
        return $this->model->active()->byType($type)->get();
    }
}
