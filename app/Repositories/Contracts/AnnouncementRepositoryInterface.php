<?php

namespace App\Repositories\Contracts;

use App\Models\Announcement;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

interface AnnouncementRepositoryInterface
{
    /**
     * Get all active announcements
     */
    public function getActiveAnnouncements(): Collection;

    /**
     * Get announcements for specific customer
     */
    public function getActiveAnnouncementsForCustomer(Customer $customer): Collection;

    /**
     * Find announcement by ID
     */
    public function findById(string $id): ?Announcement;

    /**
     * Create new announcement
     */
    public function create(array $data): Announcement;

    /**
     * Update announcement
     */
    public function update(string $id, array $data): ?Announcement;

    /**
     * Delete announcement
     */
    public function delete(string $id): bool;

    /**
     * Get announcements by type
     */
    public function getByType(string $type): Collection;
}
