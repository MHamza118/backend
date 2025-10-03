<?php

namespace App\Repositories;

use App\Models\Post;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PostRepository implements PostRepositoryInterface
{
    /**
     * Get all posts for a user with pagination.
     *
     * @param User $user
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getUserPosts(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return $user->posts()
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Create a new post.
     *
     * @param User $user
     * @param array $data
     * @return Post
     */
    public function create(User $user, array $data): Post
    {
        return $user->posts()->create($data);
    }

    /**
     * Find post by ID for a specific user.
     *
     * @param User $user
     * @param int $id
     * @return Post|null
     */
    public function findByIdForUser(User $user, int $id): ?Post
    {
        return $user->posts()->find($id);
    }

    /**
     * Update post.
     *
     * @param Post $post
     * @param array $data
     * @return Post
     */
    public function update(Post $post, array $data): Post
    {
        $updateData = [];

        if (isset($data['title'])) {
            $updateData['title'] = $data['title'];
        }

        if (isset($data['content'])) {
            $updateData['content'] = $data['content'];
        }

        if (isset($data['status'])) {
            $updateData['status'] = $data['status'];
        }

        $post->update($updateData);

        return $post->fresh();
    }

    /**
     * Delete post.
     *
     * @param Post $post
     * @return bool
     */
    public function delete(Post $post): bool
    {
        return $post->delete();
    }
}
