<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'created_by'
    ];

    public function participants()
    {
        return $this->hasMany(ConversationParticipant::class);
    }


    // New separate message relations
    public function groupMessages()
    {
        return $this->hasMany(GroupMessage::class)->orderBy('created_at', 'asc');
    }

    public function privateMessages()
    {
        return $this->hasMany(PrivateMessage::class)->orderBy('created_at', 'asc');
    }

    // Removed dynamic lastMessage method that caused eager loading issues
    // Use ConversationController's direct queries instead

    public function getUnreadCount($participantId)
    {
        $participant = $this->participants()->where('participant_id', $participantId)->first();
        
        if (!$participant) {
            return 0;
        }

        if ($this->type === 'group') {
            return $this->groupMessages()
                ->where('sender_id', '!=', $participantId)
                ->where('created_at', '>', $participant->last_read_at ?? '1970-01-01')
                ->count();
        } elseif ($this->type === 'private') {
            return $this->privateMessages()
                ->unreadForRecipient($participantId, $participant->participant_type)
                ->count();
        }
        
        return 0; // Invalid conversation type
    }

    // Helper methods
    public function isGroup()
    {
        return $this->type === 'group';
    }

    public function isPrivate()
    {
        return $this->type === 'private';
    }
}
