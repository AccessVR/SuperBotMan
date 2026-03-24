<?php

namespace OrchestrateXR\BotManChatSDK\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ChatConversation extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'page_id',
        'title',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $conversation) {
            if (empty($conversation->id)) {
                $conversation->id = Str::ulid()->toBase32();
            }
        });
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class)->orderBy('id');
    }

    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForPage($query, string $pageId)
    {
        return $query->where('page_id', $pageId);
    }

    public function generateTitle(): string
    {
        $firstUserMessage = $this->messages()
            ->where('role', 'user')
            ->first();

        if (! $firstUserMessage) {
            return 'New Conversation';
        }

        return Str::limit($firstUserMessage->content, 60);
    }
}
