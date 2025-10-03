<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\GroupMessage;
use App\Models\PrivateMessage;
use App\Models\Employee;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MessageController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get all messages for a conversation (routes to appropriate message type)
     */
    public function index(Request $request, $conversationId): JsonResponse
    {
        try {
            $user = $request->user();
            $userType = $this->getUserType($user);
            $userId = $this->getUserId($user, $userType);

            // Get conversation to determine type
            $conversation = Conversation::find($conversationId);
            if (!$conversation) {
                return $this->errorResponse('Conversation not found', 404);
            }

            // Check if user is participant in this conversation
            $participant = ConversationParticipant::where('conversation_id', $conversationId)
                ->where('participant_id', $userId)
                ->first();

            if (!$participant) {
                return $this->errorResponse('You are not a participant in this conversation', 403);
            }

            // Route to appropriate message type based on conversation type
            if ($conversation->type === 'group') {
                $messages = GroupMessage::where('conversation_id', $conversationId)
                    ->orderBy('created_at', 'asc')
                    ->get();
            } elseif ($conversation->type === 'private') {
                $messages = PrivateMessage::where('conversation_id', $conversationId)
                    ->orderBy('created_at', 'asc')
                    ->get();
            } else {
                return $this->errorResponse('Invalid conversation type', 400);
            }

            $messageData = $messages->map(function ($message) {
                return [
                    'id' => $message->id,
                    'senderId' => $message->sender_id,
                    'senderName' => $message->sender_name,
                    'senderRole' => $message->sender_type === 'admin' ? 'Admin' : 'Employee',
                    'content' => $message->content,
                    'attachments' => $message->attachments,
                    'hasAttachments' => $message->has_attachments,
                    'textContent' => $message->content, // For compatibility with frontend
                    'timestamp' => $message->created_at->toISOString()
                ];
            });

            return $this->successResponse($messageData, 'Messages retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve messages: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Send a message to a conversation (routes to appropriate message type)
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

            // Get conversation to determine type
            $conversation = Conversation::find($conversationId);
            if (!$conversation) {
                return $this->errorResponse('Conversation not found', 404);
            }

            // Check if user is participant in this conversation
            $participant = ConversationParticipant::where('conversation_id', $conversationId)
                ->where('participant_id', $userId)
                ->first();

            if (!$participant) {
                return $this->errorResponse('You are not a participant in this conversation', 403);
            }

            // Get sender name
            $senderName = $this->getSenderName($user, $userType);

            // Route to appropriate message type based on conversation type
            if ($conversation->type === 'group') {
                $message = GroupMessage::create([
                    'conversation_id' => $conversationId,
                    'sender_id' => $userId,
                    'sender_type' => $userType,
                    'sender_name' => $senderName,
                    'content' => $request->content,
                    'attachments' => $request->attachments,
                    'has_attachments' => $request->has_attachments ?? false
                ]);
            } elseif ($conversation->type === 'private') {
                $message = PrivateMessage::create([
                    'conversation_id' => $conversationId,
                    'sender_id' => $userId,
                    'sender_type' => $userType,
                    'sender_name' => $senderName,
                    'content' => $request->content,
                    'attachments' => $request->attachments,
                    'has_attachments' => $request->has_attachments ?? false,
                    'is_read' => false
                ]);
            } else {
                return $this->errorResponse('Invalid conversation type', 400);
            }

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
            ], 'Message sent successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to send message: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Send a group message
     */
    public function sendGroupMessage(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'conversation_id' => 'required|exists:conversations,id',
                'content' => 'required|string',
                'attachments' => 'nullable|array',
                'has_attachments' => 'nullable|boolean'
            ]);

            return $this->store($request, $request->conversation_id);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to send group message: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Send a private message (creates conversation if doesn't exist)
     */
    public function sendPrivateMessage(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'recipient_id' => 'required|string',
                'content' => 'required|string',
                'attachments' => 'nullable|array',
                'has_attachments' => 'nullable|boolean'
            ]);

            $user = $request->user();
            $userType = $this->getUserType($user);
            $userId = $this->getUserId($user, $userType);
            $recipientId = $request->recipient_id;

            // Determine recipient type
            if ($recipientId === 'admin') {
                $recipientType = 'admin';
            } else {
                // Check if it's a valid employee
                $employee = Employee::find($recipientId);
                if (!$employee) {
                    return $this->errorResponse('Invalid recipient', 400);
                }
                $recipientType = 'employee';
            }

            // Check if private conversation already exists
            $conversation = Conversation::where('type', 'private')
                ->whereHas('participants', function ($query) use ($userId) {
                    $query->where('participant_id', $userId);
                })
                ->whereHas('participants', function ($query) use ($recipientId) {
                    $query->where('participant_id', $recipientId);
                })
                ->first();

            if (!$conversation) {
                // Create new private conversation
                $conversation = Conversation::create([
                    'name' => 'Private Chat',
                    'type' => 'private',
                    'created_by' => $userId
                ]);

                // Add both participants
                ConversationParticipant::create([
                    'conversation_id' => $conversation->id,
                    'participant_id' => (string)$userId,
                    'participant_type' => $userType,
                    'joined_at' => now()
                ]);

                ConversationParticipant::create([
                    'conversation_id' => $conversation->id,
                    'participant_id' => (string)$recipientId,
                    'participant_type' => $recipientType,
                    'joined_at' => now()
                ]);
            }

            // Create the private message
            $senderName = $this->getSenderName($user, $userType);

            $message = PrivateMessage::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $userId,
                'sender_type' => $userType,
                'sender_name' => $senderName,
                'content' => $request->content,
                'attachments' => $request->attachments,
                'has_attachments' => $request->has_attachments ?? false,
                'is_read' => false
            ]);

            return $this->successResponse([
                'id' => $message->id,
                'conversationId' => $conversation->id,
                'senderId' => $message->sender_id,
                'senderName' => $message->sender_name,
                'senderRole' => $message->sender_type === 'admin' ? 'Admin' : 'Employee',
                'content' => $message->content,
                'attachments' => $message->attachments,
                'hasAttachments' => $message->has_attachments,
                'textContent' => $message->content,
                'timestamp' => $message->created_at->toISOString()
            ], 'Private message sent successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to send private message: ' . $e->getMessage(), 500);
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
