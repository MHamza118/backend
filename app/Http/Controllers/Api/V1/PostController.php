<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePostRequest;
use App\Http\Requests\Api\V1\UpdatePostRequest;
use App\Services\PostService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PostController extends Controller
{
    use ApiResponseTrait;

    protected PostService $postService;

    public function __construct(PostService $postService)
    {
        $this->postService = $postService;
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 15);
        $posts = $this->postService->getUserPosts($request->user(), (int) $perPage);

        return $this->successResponse($posts, 'Posts retrieved successfully');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StorePostRequest $request
     * @return JsonResponse
     */
    public function store(StorePostRequest $request): JsonResponse
    {
        $post = $this->postService->createPost($request->user(), $request->validated());

        return $this->successResponse([
            'post' => [
                'id' => $post->id,
                'title' => $post->title,
                'content' => $post->content,
                'status' => $post->status,
                'user_id' => $post->user_id,
                'created_at' => $post->created_at,
                'updated_at' => $post->updated_at,
            ],
        ], 'Post created successfully', Response::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $post = $this->postService->getPostById($request->user(), $id);

        if (! $post) {
            return $this->notFoundResponse('Post not found');
        }

        return $this->successResponse([
            'post' => [
                'id' => $post->id,
                'title' => $post->title,
                'content' => $post->content,
                'status' => $post->status,
                'user_id' => $post->user_id,
                'created_at' => $post->created_at,
                'updated_at' => $post->updated_at,
            ],
        ], 'Post retrieved successfully');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdatePostRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UpdatePostRequest $request, int $id): JsonResponse
    {
        $post = $this->postService->getPostById($request->user(), $id);

        if (! $post) {
            return $this->notFoundResponse('Post not found');
        }

        $updatedPost = $this->postService->updatePost($post, $request->validated());

        return $this->successResponse([
            'post' => [
                'id' => $updatedPost->id,
                'title' => $updatedPost->title,
                'content' => $updatedPost->content,
                'status' => $updatedPost->status,
                'user_id' => $updatedPost->user_id,
                'created_at' => $updatedPost->created_at,
                'updated_at' => $updatedPost->updated_at,
            ],
        ], 'Post updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $post = $this->postService->getPostById($request->user(), $id);

        if (! $post) {
            return $this->notFoundResponse('Post not found');
        }

        $this->postService->deletePost($post);

        return $this->successResponse(null, 'Post deleted successfully');
    }
}
