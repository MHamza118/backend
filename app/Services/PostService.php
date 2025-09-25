<?php

namespace App\Services;

use App\Models\Post;
use App\Models\User;
use App\Repositories\PostRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PostService
{
    protected PostRepositoryInterface $postRepository;

    public function __construct(PostRepositoryInterface $postRepository)
    {
        $this->postRepository = $postRepository;
    }

    /**
     * Get all posts for a user.
     *
     * @param User $user
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getUserPosts(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return $this->postRepository->getUserPosts($user, $perPage);
    }

    /**
     * Create a new post.
     *
     * @param User $user
     * @param array $data
     * @return Post
     */
    public function createPost(User $user, array $data): Post
    {
        return $this->postRepository->create($user, $data);
    }

    /**
     * Get post by ID for a specific user.
     *
     * @param User $user
     * @param int $id
     * @return Post|null
     */
    public function getPostById(User $user, int $id): ?Post
    {
        return $this->postRepository->findByIdForUser($user, $id);
    }

    /**
     * Update post.
     *
     * @param Post $post
     * @param array $data
     * @return Post
     */
    public function updatePost(Post $post, array $data): Post
    {
        return $this->postRepository->update($post, $data);
    }

    /**
     * Delete post.
     *
     * @param Post $post
     * @return bool
     */
    public function deletePost(Post $post): bool
    {
        return $this->postRepository->delete($post);
    }
}
