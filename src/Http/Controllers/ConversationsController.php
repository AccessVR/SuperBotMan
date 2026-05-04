<?php

namespace OrchestrateXR\SuperBotMan\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $user = SuperBotMan::agentUser();

        $rows = DB::table($this->table())
            ->where($this->userColumn(), $user->getAuthIdentifier())
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get(['id', 'title', 'updated_at']);

        return new JsonResponse($rows);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $slug = (string) $request->route('slug');
        $this->ensureRegistered($slug);

        $user = SuperBotMan::agentUser();

        $conversation = DB::table($this->table())
            ->where('id', $id)
            ->where($this->userColumn(), $user->getAuthIdentifier())
            ->first();

        if (! $conversation) {
            throw new HttpException(404);
        }

        $messages = DB::table($this->messagesTable())
            ->where($this->messagesForeignKey(), $id)
            ->orderBy('created_at')
            ->get(['id', 'role', 'content', 'created_at']);

        return new JsonResponse([
            'id' => $conversation->id,
            'title' => $conversation->title ?? null,
            'updated_at' => $conversation->updated_at,
            'messages' => $messages,
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $slug = (string) $request->route('slug');
        $this->ensureRegistered($slug);

        $user = SuperBotMan::agentUser();

        $deleted = DB::table($this->table())
            ->where('id', $id)
            ->where($this->userColumn(), $user->getAuthIdentifier())
            ->delete();

        if (! $deleted) {
            throw new HttpException(404);
        }

        return new JsonResponse(['deleted' => true]);
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
