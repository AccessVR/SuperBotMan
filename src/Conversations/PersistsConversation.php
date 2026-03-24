<?php

namespace OrchestrateXR\BotManChatSDK\Conversations;

use Illuminate\Support\Str;
use LLPhant\Chat\Message;
use OrchestrateXR\BotManChatSDK\BotManChat;
use OrchestrateXR\BotManChatSDK\Models\ChatConversation as ChatConversationModel;
use OrchestrateXR\BotManChatSDK\Models\ChatMessage;

trait PersistsConversation
{
    protected ?string $conversationHistoryId = null;

    protected ?string $conversationUserId = null;

    protected string $conversationPageId = 'default';

    protected array $conversationMetadata = [];

    protected int $lastPersistedMessageCount = 0;

    public function forUserId(string $userId): static
    {
        $this->conversationUserId = $userId;

        return $this;
    }

    public function forPage(string $pageId): static
    {
        $this->conversationPageId = $pageId;

        return $this;
    }

    public function withMetadata(array $metadata): static
    {
        $this->conversationMetadata = $metadata;

        return $this;
    }

    public function setConversationHistoryId(?string $id): static
    {
        $this->conversationHistoryId = $id;

        return $this;
    }

    public function loadFromHistory(ChatConversationModel $history): void
    {
        $this->conversationHistoryId = $history->id;
        $this->conversationMetadata = $history->metadata ?? [];

        $this->messages = collect();

        foreach ($history->messages as $message) {
            $this->messages->push(match ($message->role) {
                'user' => Message::user($message->content),
                'assistant' => Message::assistant($message->content),
                'system' => Message::system($message->content),
                default => Message::user($message->content),
            });
        }

        $this->lastPersistedMessageCount = $this->messages->count();
    }

    protected function saveToHistory(): void
    {
        $userId = $this->conversationUserId ?: BotManChat::userId();
        $messages = $this->messages;

        if ($this->conversationHistoryId) {
            $history = ChatConversationModel::find($this->conversationHistoryId);

            if ($history) {
                $this->insertNewMessages($history, $messages);
                $history->touch();

                return;
            }
        }

        $firstUserMessage = $messages->first(fn (Message $m) => $m->role === 'user');
        $title = $firstUserMessage
            ? Str::limit($firstUserMessage->content, 60)
            : 'New Conversation';

        $history = ChatConversationModel::create([
            'user_id' => $userId,
            'page_id' => $this->conversationPageId,
            'title' => $title,
            'metadata' => $this->conversationMetadata ?: null,
        ]);

        $this->conversationHistoryId = $history->id;

        $this->insertNewMessages($history, $messages);

        $this->queueClientAction('setConversationId', [
            'id' => $history->id,
            'title' => $title,
        ]);
    }

    protected function insertNewMessages(ChatConversationModel $history, $messages): void
    {
        $newMessages = $messages->slice($this->lastPersistedMessageCount);

        foreach ($newMessages as $message) {
            ChatMessage::create([
                'chat_conversation_id' => $history->id,
                'role' => $message->role,
                'content' => $message->content,
                'created_at' => now(),
            ]);
        }

        $this->lastPersistedMessageCount = $messages->count();
    }
}
