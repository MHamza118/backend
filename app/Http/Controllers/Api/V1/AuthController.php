<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Services\AuthService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\Customer;

class AuthController extends Controller
{
    use ApiResponseTrait;

    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Register a new user.
     *
     * @param RegisterRequest $request
     * @return JsonResponse
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->register($request->validated());

            return $this->successResponse([
                'user' => [
                    'id' => $result['user']->id,
                    'name' => $result['user']->name,
                    'email' => $result['user']->email,
                    'email_verified_at' => $result['user']->email_verified_at,
                    'created_at' => $result['user']->created_at,
                    'updated_at' => $result['user']->updated_at,
                ],
                'token' => $result['token'],
            ], 'User registered successfully', Response::HTTP_CREATED);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), $e->getMessage());
        }
    }

    /**
     * Login user.
     *
     * @param LoginRequest $request
     * @return JsonResponse
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->login($request->validated());

            return $this->successResponse([
                'user' => [
                    'id' => $result['user']->id,
                    'name' => $result['user']->name,
                    'email' => $result['user']->email,
                    'email_verified_at' => $result['user']->email_verified_at,
                    'created_at' => $result['user']->created_at,
                    'updated_at' => $result['user']->updated_at,
                ],
                'token' => $result['token'],
            ], 'Login successful');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), $e->getMessage());
        }
    }

    /**
     * Customer login.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function customerLogin(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required|string',
            ]);

            $customer = Customer::where('email', $request->email)->first();

            if (!$customer || !Hash::check($request->password, $customer->password)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid email or password'
                ], 401);
            }

            // Create authentication token
            $token = $customer->createToken('customer-token')->plainTextToken;

            // Load customer with relationships
            $customer->load('locations');

            return response()->json([
                'status' => 'success',
                'message' => 'Login successful',
                'data' => [
                    'customer' => [
                        'id' => $customer->id,
                        'name' => $customer->name,
                        'first_name' => $customer->first_name,
                        'last_name' => $customer->last_name,
                        'email' => $customer->email,
                        'phone' => $customer->phone,
                        'home_location' => $customer->home_location,
                        'loyalty_points' => $customer->loyalty_points,
                        'loyalty_tier' => $customer->loyalty_tier,
                        'total_orders' => $customer->total_orders,
                        'total_spent' => number_format($customer->total_spent, 2),
                        'preferences' => $customer->preferences ?? ['notifications' => true, 'marketing' => false],
                        'status' => $customer->status,
                        'last_visit' => $customer->last_visit?->toISOString(),
                        'created_at' => $customer->created_at->toISOString(),
                        'locations' => $customer->locations,
                    ],
                    'token' => $token,
                    'token_type' => 'Bearer'
                ]
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Login failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Logout user or customer.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Handle both User and Customer models
            if ($user instanceof \App\Models\User) {
                $this->authService->logout($user);
            } else {
                // For Customer or other authenticated models, just delete the current token
                $user->currentAccessToken()->delete();
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Logout successful'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Logout failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
