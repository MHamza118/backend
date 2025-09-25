<?php

namespace App\Repositories;

use App\Models\Post;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PostRepositoryInterface
{
    /**
     * Get all posts for a user with pagination.
     *
     * @param User $user
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getUserPosts(User $user, int $perPage = 15): LengthAwarePaginator;

    /**
     * Create a new post.
     *
     * @param User $user
     * @param array $data
     * @return Post
     */
    public function create(User $user, array $data): Post;

    /**
     * Find post by ID for a specific user.
     *
     * @param User $user
     * @param int $id
     * @return Post|null
     */
    public function findByIdForUser(User $user, int $id): ?Post;

    /**
     * Update post.
     *
     * @param Post $post
     * @param array $data
     * @return Post
     */
    public function update(Post $post, array $data): Post;

    /**
     * Delete post.
     *
     * @param Post $post
     * @return bool
     */
    public function delete(Post $post): bool;
}
