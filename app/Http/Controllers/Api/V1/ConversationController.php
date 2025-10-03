<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\GroupMessage;
use App\Models\PrivateMessage;
use App\Models\ConversationParticipant;
use App\Models\Employee;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ConversationController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get all conversations for a user (admin or employee)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $userType = $this->getUserType($user);
            $userId = $this->getUserId($user, $userType);

            $conversations = Conversation::whereHas('participants', function ($query) use ($userId) {
                $query->where('participant_id', $userId);
            })
            ->with([
                'participants' => function ($query) {
                    $query->with('employee:id,email,first_name,last_name,profile_data');
                }
            ])
            ->orderBy('updated_at', 'desc')
            ->get();

            $conversationData = $conversations->map(function ($conversation) use ($userId) {
                // Safely compute last message per conversation without eager-loading a possibly null relation
                if ($conversation->type === 'group') {
                    $lastMessage = GroupMessage::where('conversation_id', $conversation->id)
                        ->orderBy('created_at', 'desc')
                        ->first();
                } elseif ($conversation->type === 'private') {
                    $lastMessage = PrivateMessage::where('conversation_id', $conversation->id)
                        ->orderBy('created_at', 'desc')
                        ->first();
                } else {
                    $lastMessage = null;
                }

                $unreadCount = $conversation->getUnreadCount($userId);

                // For private conversations, get the other participant's name
                if ($conversation->type === 'private') {
                    $otherParticipant = $conversation->participants
                        ->where('participant_id', '!=', $userId)
                        ->first();
                    
                    if ($otherParticipant) {
                        if ($otherParticipant->participant_type === 'admin') {
                            $conversation->name = 'Management';
                        } else if ($otherParticipant->employee) {
                            $profileData = $otherParticipant->employee->profile_data ?? [];
                            $firstName = $otherParticipant->employee->first_name ?? ($profileData['firstName'] ?? 'Employee');
                            $lastName = $otherParticipant->employee->last_name ?? ($profileData['lastName'] ?? '');
                            $conversation->name = $firstName . ' ' . $lastName;
                        }
                    }
                }

                return [
                    'id' => $conversation->id,
                    'name' => $conversation->name,
                    'type' => $conversation->type,
                    'lastMessage' => $lastMessage ? [
                        'content' => $lastMessage->content,
                        'timestamp' => $lastMessage->created_at,
                        'sender_name' => $lastMessage->sender_name,
                        'sender_id' => $lastMessage->sender_id
                    ] : null,
                    'unreadCount' => $unreadCount,
                    'members' => $conversation->participants->pluck('participant_id')
                ];
            });

            return $this->successResponse($conversationData, 'Conversations retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve conversations: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create a new group conversation
     */
    public function createGroup(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'employee_ids' => 'required|array',
                'employee_ids.*' => 'required|integer|exists:employees,id'
            ]);

            $user = $request->user();
            $userType = $this->getUserType($user);
            $userId = $this->getUserId($user, $userType);

            // Only admins can create groups
            if ($userType !== 'admin') {
                return $this->errorResponse('Only administrators can create group conversations', 403);
            }

            DB::beginTransaction();

            // Create the conversation
            $conversation = Conversation::create([
                'name' => $request->name,
                'type' => 'group',
                'created_by' => $userId
            ]);

            // Add admin as participant
            ConversationParticipant::create([
                'conversation_id' => $conversation->id,
                'participant_id' => $userId,
                'participant_type' => 'admin',
                'joined_at' => now()
            ]);

            // Add employees as participants
            foreach ($request->employee_ids as $employeeId) {
                ConversationParticipant::create([
                    'conversation_id' => $conversation->id,
                    'participant_id' => (string)$employeeId,
                    'participant_type' => 'employee',
                    'joined_at' => now()
                ]);
            }

            DB::commit();

            return $this->successResponse([
                'id' => $conversation->id,
                'name' => $conversation->name,
                'type' => $conversation->type,
                'members' => array_merge([$userId], $request->employee_ids)
            ], 'Group created successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to create group: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get or create a private conversation between admin and employee
     */
    public function getOrCreatePrivateConversation(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'participant_id' => 'required|string'
            ]);

            $user = $request->user();
            $userType = $this->getUserType($user);
            $userId = $this->getUserId($user, $userType);
            $participantId = $request->participant_id;

            // Determine participant type
            if ($participantId === 'admin') {
                $participantType = 'admin';
            } else {
                // Check if it's a valid employee
                $employee = Employee::find($participantId);
                if (!$employee) {
                    return $this->errorResponse('Invalid participant', 400);
                }
                $participantType = 'employee';
            }

            // Check if private conversation already exists
            $conversation = Conversation::where('type', 'private')
                ->whereHas('participants', function ($query) use ($userId) {
                    $query->where('participant_id', $userId);
                })
                ->whereHas('participants', function ($query) use ($participantId) {
                    $query->where('participant_id', $participantId);
                })
                ->first();

            if (!$conversation) {
                DB::beginTransaction();

                // Create new private conversation
                $conversation = Conversation::create([
                    'name' => 'Private Chat',
                    'type' => 'private',
                    'created_by' => $userId
                ]);

                // Add both participants
                ConversationParticipant::create([
                    'conversation_id' => $conversation->id,
                    'participant_id' => $userId,
                    'participant_type' => $userType,
                    'joined_at' => now()
                ]);

                ConversationParticipant::create([
                    'conversation_id' => $conversation->id,
                    'participant_id' => $participantId,
                    'participant_type' => $participantType,
                    'joined_at' => now()
                ]);

                DB::commit();
            }

            return $this->successResponse([
                'id' => $conversation->id,
                'name' => $conversation->name,
                'type' => $conversation->type
            ], 'Private conversation retrieved/created successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to get/create private conversation: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Add employees to existing group
     */
    public function addParticipants(Request $request, $conversationId): JsonResponse
    {
        try {
            $request->validate([
                'employee_ids' => 'required|array',
                'employee_ids.*' => 'required|integer|exists:employees,id'
            ]);

            $user = $request->user();
            $userType = $this->getUserType($user);

            // Only admins can add participants
            if ($userType !== 'admin') {
                return $this->errorResponse('Only administrators can add participants', 403);
            }

            $conversation = Conversation::findOrFail($conversationId);

            if ($conversation->type !== 'group') {
                return $this->errorResponse('Can only add participants to group conversations', 400);
            }

            DB::beginTransaction();

            foreach ($request->employee_ids as $employeeId) {
                // Check if employee is already a participant
                $exists = ConversationParticipant::where('conversation_id', $conversationId)
                    ->where('participant_id', $employeeId)
                    ->exists();

                if (!$exists) {
                    ConversationParticipant::create([
                        'conversation_id' => $conversationId,
                        'participant_id' => (string)$employeeId,
                        'participant_type' => 'employee',
                        'joined_at' => now()
                    ]);
                }
            }

            DB::commit();

            return $this->successResponse(null, 'Participants added successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to add participants: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Mark messages as read for a user in a conversation
     */
    public function markAsRead(Request $request, $conversationId): JsonResponse
    {
        try {
            $user = $request->user();
            $userType = $this->getUserType($user);
            $userId = $this->getUserId($user, $userType);

            $participant = ConversationParticipant::where('conversation_id', $conversationId)
                ->where('participant_id', $userId)
                ->first();

            if (!$participant) {
                return $this->errorResponse('You are not a participant in this conversation', 403);
            }

            $participant->update(['last_read_at' => now()]);

            return $this->successResponse(null, 'Messages marked as read');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to mark messages as read: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Helper method to determine user type
     */
    private function getUserType($user): string
    {
        if ($user instanceof \App\Models\Admin) {
            return 'admin';
        } elseif ($user instanceof \App\Models\Employee) {
            return 'employee';
        }
        
        // Fallback: check the user's table
        if (get_class($user) === 'App\\Models\\Admin') {
            return 'admin';
        }
        
        return 'employee';
    }

    /**
     * Helper method to get user ID
     */
    private function getUserId($user, $userType): string
    {
        if ($userType === 'admin') {
            return 'admin'; // Use 'admin' as the standard admin ID
        }
        
        return $user->id;
    }
}
