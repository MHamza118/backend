<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrivateMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'sender_type',
        'sender_name',
        'content',
        'attachments',
        'has_attachments',
        'is_read',
        'read_at'
    ];

    protected $casts = [
        'attachments' => 'array',
        'has_attachments' => 'boolean',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    /**
     * Get the conversation that this message belongs to
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the sender (polymorphic relationship)
     */
    public function sender()
    {
        if ($this->sender_type === 'employee') {
            return $this->belongsTo(Employee::class, 'sender_id');
        }
        
        if ($this->sender_type === 'admin') {
            return $this->belongsTo(Admin::class, 'sender_id');
        }
        
        return null;
    }

    /**
     * Scope to get messages for a specific private conversation
     */
    public function scopeForConversation($query, $conversationId)
    {
        return $query->where('conversation_id', $conversationId)
                    ->orderBy('created_at', 'asc');
    }

    /**
     * Scope to get unread messages
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope to get unread messages for a specific recipient
     */
    public function scopeUnreadForRecipient($query, $recipientId, $recipientType)
    {
        return $query->where('is_read', false)
                    ->where(function($q) use ($recipientId, $recipientType) {
                        // Messages where the recipient is NOT the sender
                        $q->where('sender_id', '!=', $recipientId)
                          ->orWhere('sender_type', '!=', $recipientType);
                    });
    }

    /**
     * Scope to get recent messages (last N messages)
     */
    public function scopeRecent($query, $limit = 50)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }

    /**
     * Scope to get messages from a specific sender
     */
    public function scopeFromSender($query, $senderId, $senderType)
    {
        return $query->where('sender_id', $senderId)
                    ->where('sender_type', $senderType);
    }

    /**
     * Scope to get messages with attachments
     */
    public function scopeWithAttachments($query)
    {
        return $query->where('has_attachments', true);
    }

    /**
     * Mark this message as read
     */
    public function markAsRead()
    {
        $this->update([
            'is_read' => true,
            'read_at' => now()
        ]);
    }

    /**
     * Mark multiple messages as read
     */
    public static function markMultipleAsRead(array $messageIds)
    {
        return self::whereIn('id', $messageIds)->update([
            'is_read' => true,
            'read_at' => now()
        ]);
    }
}
