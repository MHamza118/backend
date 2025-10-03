<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\GroupMessage;
use App\Models\Employee;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class GroupMessageController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get all group messages for a conversation
     */
    public function index(Request $request, $conversationId): JsonResponse
    {
        try {
            $user = $request->user();
            $userType = $this->getUserType($user);
            $userId = $this->getUserId($user, $userType);

            // Ensure the conversation is a group conversation
            $conversation = Conversation::where('id', $conversationId)
                ->where('type', 'group')
                ->first();

            if (!$conversation) {
                return $this->errorResponse('Group conversation not found', 404);
            }

            // Check if user is participant in this conversation
            $participant = ConversationParticipant::where('conversation_id', $conversationId)
                ->where('participant_id', $userId)
                ->first();

            if (!$participant) {
                return $this->errorResponse('You are not a participant in this group', 403);
            }

            $messages = GroupMessage::forConversation($conversationId)->get();

            $messageData = $messages->map(function ($message) {
                return [
                    'id' => $message->id,
                    'senderId' => $message->sender_id,
                    'senderName' => $message->sender_name,
                    'senderRole' => $message->sender_type === 'admin' ? 'Admin' : 'Employee',
                    'content' => $message->content,
                    'attachments' => $message->attachments,
                    'hasAttachments' => $message->has_attachments,
                    'textContent' => $message->content,
                    'timestamp' => $message->created_at->toISOString()
                ];
            });

            return $this->successResponse($messageData, 'Group messages retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve group messages: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Send a message to a group conversation
     */
    public function store(Request $request, $conversationId): JsonResponse
    {
        try {
            $request->validate([
                'content' => 'required|string',
                'attachments' => 'nullable|array',
                'has_attachments' => 'nullable|boolean'
            ]);

            $user = $request->user();
            $userType = $this->getUserType($user);
            $userId = $this->getUserId($user, $userType);

            // Ensure the conversation is a group conversation
            $conversation = Conversation::where('id', $conversationId)
                ->where('type', 'group')
                ->first();

            if (!$conversation) {
                return $this->errorResponse('Group conversation not found', 404);
            }

            // Check if user is participant in this conversation
            $participant = ConversationParticipant::where('conversation_id', $conversationId)
                ->where('participant_id', $userId)
                ->first();

            if (!$participant) {
                return $this->errorResponse('You are not a participant in this group', 403);
            }

            // Get sender name
            $senderName = $this->getSenderName($user, $userType);

            // Create the group message
            $message = GroupMessage::create([
                'conversation_id' => $conversationId,
                'sender_id' => $userId,
                'sender_type' => $userType,
                'sender_name' => $senderName,
                'content' => $request->content,
                'attachments' => $request->attachments,
                'has_attachments' => $request->has_attachments ?? false
            ]);

            return $this->successResponse([
                'id' => $message->id,
                'senderId' => $message->sender_id,
                'senderName' => $message->sender_name,
                'senderRole' => $message->sender_type === 'admin' ? 'Admin' : 'Employee',
                'content' => $message->content,
                'attachments' => $message->attachments,
                'hasAttachments' => $message->has_attachments,
                'textContent' => $message->content,
                'timestamp' => $message->created_at->toISOString()
            ], 'Group message sent successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to send group message: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get group message statistics
     */
    public function getGroupStats($conversationId): JsonResponse
    {
        try {
            $conversation = Conversation::where('id', $conversationId)
                ->where('type', 'group')
                ->first();

            if (!$conversation) {
                return $this->errorResponse('Group conversation not found', 404);
            }

            $stats = [
                'total_messages' => GroupMessage::where('conversation_id', $conversationId)->count(),
                'participants_count' => $conversation->participants()->count(),
                'messages_with_attachments' => GroupMessage::where('conversation_id', $conversationId)
                    ->withAttachments()->count(),
                'last_activity' => GroupMessage::where('conversation_id', $conversationId)
                    ->latest()->first()?->created_at?->toISOString()
            ];

            return $this->successResponse($stats, 'Group statistics retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get group statistics: ' . $e->getMessage(), 500);
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
        
        return (string)$user->id;
    }

    /**
     * Helper method to get sender name
     */
    private function getSenderName($user, $userType): string
    {
        if ($userType === 'admin') {
            return 'Management';
        }
        
        // For employees, get name from profile_data or direct fields
        if ($user) {
            $firstName = $user->first_name;
            $lastName = $user->last_name;
            
            // Fallback to profile_data if direct fields are empty
            if (empty($firstName) && !empty($user->profile_data)) {
                $profileData = is_array($user->profile_data) ? $user->profile_data : json_decode($user->profile_data, true);
                $firstName = $profileData['firstName'] ?? $profileData['first_name'] ?? 'Employee';
                $lastName = $profileData['lastName'] ?? $profileData['last_name'] ?? '';
            }
            
            if (!empty($firstName) || !empty($lastName)) {
                return trim(($firstName ?? 'Employee') . ' ' . ($lastName ?? ''));
            }
        }
        
        return 'Employee';
    }
}
