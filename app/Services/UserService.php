<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepositoryInterface;
use Illuminate\Validation\ValidationException;

class UserService
{
    protected UserRepositoryInterface $userRepository;

    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * Get user profile.
     *
     * @param User $user
     * @return User
     */
    public function getProfile(User $user): User
    {
        return $user;
    }

    /**
     * Update user profile.
     *
     * @param User $user
     * @param array $data
     * @return User
     * @throws ValidationException
     */
    public function updateProfile(User $user, array $data): User
    {
        // Check if email is being updated and if it's already taken by another user
        if (isset($data['email']) && $data['email'] !== $user->email) {
            $existingUser = $this->userRepository->findByEmail($data['email']);
            if ($existingUser && $existingUser->id !== $user->id) {
                throw ValidationException::withMessages([
                    'email' => ['The email has already been taken.']
                ]);
            }
        }

        return $this->userRepository->update($user, $data);
    }
}
