<?php

namespace OrchestrateXR\SuperBotMan\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OrchestrateXR\SuperBotMan\Facades\SuperBotMan;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * List, fetch, and delete conversations for a single registered
 * agent slug, scoped to the current configurator-resolved user.
 *
 * Reads agent_conversations / agent_conversation_messages directly
 * via the DB facade so SuperBotMan doesn't take an Eloquent model
 * dependency on laravel/ai's internals. Table + column names are
 * config-overridable.
 */
class ConversationsController
{
    public function index(Request $request): JsonResponse
    {
        $slug = (string) $request->route('slug');
        $this->ensureRegistered($slug);

        $userId = SuperBotMan::agentUserId();

        if ($userId === null) {
            return new JsonResponse([]);
        }

        $rows = DB::table($this->table())
            ->where($this->userColumn(), $userId)
            ->when($this->softDeletes(), fn ($query) => $query->whereNull('deleted_at'))
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get(['id', 'title', 'updated_at']);

        $agents = DB::table($this->messagesTable())
            ->whereIn($this->messagesForeignKey(), $rows->pluck('id'))
            ->orderByDesc('created_at')
            ->get([$this->messagesForeignKey().' as cid', 'agent'])
            ->groupBy('cid')
            ->map(fn ($group) => $group->first()->agent);

        $rows->each(function ($row) use ($agents) {
            $row->page_id = $this->pageIdForAgent($agents[$row->id] ?? null);
        });

        return new JsonResponse($rows);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $slug = (string) $request->route('slug');
        $this->ensureRegistered($slug);

        $userId = SuperBotMan::agentUserId();

        $conversation = $userId === null ? null : DB::table($this->table())
            ->where('id', $id)
            ->where($this->userColumn(), $userId)
            ->when($this->softDeletes(), fn ($query) => $query->whereNull('deleted_at'))
            ->first();

        if (! $conversation) {
            throw new HttpException(404);
        }

        $messages = DB::table($this->messagesTable())
            ->where($this->messagesForeignKey(), $id)
            ->orderBy('created_at')
            ->get(['id', 'role', 'content', 'created_at'])
            ->map(fn ($message) => $this->renderMessage($message));

        $agent = DB::table($this->messagesTable())
            ->where($this->messagesForeignKey(), $id)
            ->orderByDesc('created_at')
            ->value('agent');

        return new JsonResponse([
            'id' => $conversation->id,
            'title' => $conversation->title ?? null,
            'updated_at' => $conversation->updated_at,
            // Lets the widget resume the conversation on its owning
            // channel (Chat.vue::onResumeConversation reads page_id).
            'page_id' => $this->pageIdForAgent($agent) ?? $slug,
            'messages' => $messages,
        ]);
    }

    /**
     * Apply role-specific rendering hooks before returning persisted
     * messages to the widget on resume:
     *
     * - Assistant text runs through renderAssistantText so markdown
     *   stored at write time becomes HTML on read.
     * - User text runs through renderUserText so any annotations the
     *   host appended in renderUserPrompt (page URL, etc.) are stripped
     *   before the user sees their own message in the bubble.
     *
     * Tool-call payloads (JSON-encoded arrays/objects) are left alone
     * so structured content isn't mangled.
     */
    protected function renderMessage(object $message): object
    {
        if (! is_string($message->content)) {
            return $message;
        }

        $trimmed = ltrim($message->content);
        if ($trimmed === '' || $trimmed[0] === '[' || $trimmed[0] === '{') {
            return $message;
        }

        if ($message->role === 'assistant') {
            $message->content = SuperBotMan::renderAssistantText($message->content);
        } elseif ($message->role === 'user') {
            $message->content = SuperBotMan::renderUserText($message->content);
        }

        return $message;
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $slug = (string) $request->route('slug');
        $this->ensureRegistered($slug);

        $userId = SuperBotMan::agentUserId();

        $query = $userId === null ? null : DB::table($this->table())
            ->where('id', $id)
            ->where($this->userColumn(), $userId);

        // With soft deletes on (host opts in after adding a deleted_at
        // column), "delete" hides the conversation from the user but keeps
        // the history recoverable by admins; without it, the row is gone.
        $deleted = match (true) {
            $query === null => 0,
            $this->softDeletes() => $query->whereNull('deleted_at')->update(['deleted_at' => now()]),
            default => $query->delete(),
        };

        if (! $deleted) {
            throw new HttpException(404);
        }

        return new JsonResponse(['deleted' => true]);
    }

    protected function softDeletes(): bool
    {
        return (bool) SuperBotMan::config('conversationSoftDeletes');
    }

    /**
     * Mint a conversation before its first message so the widget can
     * subscribe to per-conversation broadcast channels (agent activity)
     * from the very first turn. The title is intentionally empty — it is
     * backfilled after the first reply (see ConversationTitler), which
     * also moves title generation off the first turn's critical path.
     */
    public function prepare(Request $request): JsonResponse
    {
        $slug = (string) $request->route('slug');
        $this->ensureRegistered($slug);

        $user = SuperBotMan::agentUser();

        $conversationId = (string) Str::uuid7();

        DB::table($this->table())->insert([
            'id' => $conversationId,
            $this->userColumn() => $user->getAuthIdentifier(),
            'title' => '',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return new JsonResponse(['conversationId' => $conversationId], 201);
    }

    /**
     * Map a persisted message `agent` (the agent class the SDK records
     * per message) to the registered slug, so the widget can resume a
     * conversation on the channel that owns it. Null when unknown.
     */
    protected function pageIdForAgent(?string $agent): ?string
    {
        if (! $agent) {
            return null;
        }

        foreach (SuperBotMan::registry()->all() as $registration) {
            if ($registration->agentClass === $agent) {
                return $registration->slug;
            }
        }

        return null;
    }

    protected function ensureRegistered(string $slug): void
    {
        if (! SuperBotMan::registry()->bySlug($slug)) {
            throw new HttpException(404, "No agent registered for slug '{$slug}'.");
        }
    }

    protected function table(): string
    {
        return config('super-botman.agent_conversations_table', 'agent_conversations');
    }

    protected function messagesTable(): string
    {
        return config('super-botman.agent_conversation_messages_table', 'agent_conversation_messages');
    }

    protected function userColumn(): string
    {
        return config('super-botman.agent_conversations_user_column', 'user_id');
    }

    protected function messagesForeignKey(): string
    {
        return config('super-botman.agent_conversation_messages_foreign_key', 'conversation_id');
    }
}
