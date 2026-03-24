<?php

namespace OrchestrateXR\BotManChatSDK\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use OrchestrateXR\BotManChatSDK\BotManChat;
use OrchestrateXR\BotManChatSDK\Models\ChatConversation;

class ConversationHistoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = BotManChat::userId();

        $query = ChatConversation::forUser($userId)
            ->withCount('messages')
            ->orderByDesc('updated_at');

        if ($request->has('page_id')) {
            $query->forPage($request->input('page_id'));
        }

        $conversations = $query->get()->map(fn (ChatConversation $c) => [
            'id' => $c->id,
            'title' => $c->title,
            'page_id' => $c->page_id,
            'updated_at' => $c->updated_at->toISOString(),
            'message_count' => $c->messages_count,
        ]);

        return response()->json($conversations);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $userId = BotManChat::userId();

        $conversation = ChatConversation::forUser($userId)
            ->with('messages')
            ->findOrFail($id);

        return response()->json([
            'id' => $conversation->id,
            'title' => $conversation->title,
            'page_id' => $conversation->page_id,
            'metadata' => $conversation->metadata,
            'messages' => $conversation->messages->map(fn ($m) => [
                'role' => $m->role,
                'content' => $m->content,
                'created_at' => $m->created_at?->toISOString(),
            ]),
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $userId = BotManChat::userId();

        $conversation = ChatConversation::forUser($userId)->findOrFail($id);
        $conversation->delete();

        return response()->json(null, 204);
    }
}
