<?php

namespace OrchestrateXR\SuperBotMan\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use OrchestrateXR\SuperBotMan\AgentContext;
use OrchestrateXR\SuperBotMan\ClientActionBag;
use OrchestrateXR\SuperBotMan\Contracts\AgentDispatcher;
use OrchestrateXR\SuperBotMan\Facades\SuperBotMan;

/**
 * Interactive CLI for testing a registered agent end-to-end without
 * a browser. Built directly on the AgentDispatcher abstraction so it
 * works against any backend (Laravel AI SDK by default, mock in tests).
 *
 *   php artisan super-botman:chat character-builder
 *   php artisan super-botman:chat character-builder --continue
 *   php artisan super-botman:chat character-builder --conversation-id=01J...
 *   php artisan super-botman:chat character-builder --system="Be terse."
 */
class ChatCommand extends Command
{
    protected $signature = 'super-botman:chat
        {slug : Slug of a registered agent}
        {--continue : Resume the most recent conversation for this agent}
        {--conversation-id= : Resume a specific conversation by id}
        {--system= : Override the system prompt for this run (impl-dependent)}';

    protected $description = 'Start an interactive CLI chat session against a registered SuperBotMan agent.';

    public function handle(AgentDispatcher $dispatcher): int
    {
        $slug = (string) $this->argument('slug');
        $registration = SuperBotMan::registry()->bySlug($slug);

        if (! $registration) {
            $available = implode(', ', SuperBotMan::registry()->slugs()) ?: '(none registered)';
            $this->error("No agent registered for slug '{$slug}'. Available: {$available}");

            return self::FAILURE;
        }

        $user = SuperBotMan::agentUser();
        $conversationId = $this->resolveConversationId($slug, $user);

        $this->printWelcome($registration->slug, $registration->agentClass, $conversationId);

        // Bind request-scoped singletons so any tools the agent uses
        // can emit ClientActions / read AgentContext just like the HTTP path.
        $bag = new ClientActionBag;
        $context = new AgentContext($this->buildCliContext());
        app()->instance(ClientActionBag::class, $bag);
        app()->instance(AgentContext::class, $context);

        while (true) {
            $this->output->write('> ');
            $line = fgets(STDIN);

            if ($line === false) {
                break;
            }

            $text = trim($line);

            if (strtolower($text) === 'exit' || strtolower($text) === 'quit') {
                $this->info('Goodbye!');

                return self::SUCCESS;
            }

            if ($text === '') {
                continue;
            }

            try {
                $result = $dispatcher->dispatch(
                    agentClass: $registration->agentClass,
                    prompt: $text,
                    user: $user,
                    conversationId: $conversationId,
                );
            } catch (\Throwable $e) {
                $this->error($e->getMessage());

                continue;
            }

            $this->line($result->text);

            foreach ($bag->all() as $action) {
                $this->line("  [client_action: {$action->name} ".json_encode($action->payload).']');
            }

            $conversationId = $result->conversationId;
        }

        return self::SUCCESS;
    }

    protected function resolveConversationId(string $slug, $user): ?string
    {
        if ($id = $this->option('conversation-id')) {
            return (string) $id;
        }

        if (! $this->option('continue')) {
            return null;
        }

        $table = config('super-botman.agent_conversations_table', 'agent_conversations');
        $userColumn = config('super-botman.agent_conversations_user_column', 'user_id');

        $row = DB::table($table)
            ->where($userColumn, $user->getAuthIdentifier())
            ->latest('updated_at')
            ->first();

        if ($row) {
            $this->info("Resuming conversation {$row->id}".(isset($row->title) ? " — {$row->title}" : ''));

            return (string) $row->id;
        }

        $this->warn('No previous conversation found. Starting fresh.');

        return null;
    }

    protected function buildCliContext(): array
    {
        $context = ['source' => 'cli'];

        if ($override = $this->option('system')) {
            // Agents that read AgentContext::get('system_prompt_override')
            // can prefer it over their default instructions().
            $context['system_prompt_override'] = $override;
        }

        return $context;
    }

    protected function printWelcome(string $slug, string $agentClass, ?string $conversationId): void
    {
        $this->line("SuperBotMan CLI — agent '{$slug}' ({$agentClass})");
        if ($conversationId) {
            $this->line("Resuming conversation: {$conversationId}");
        }
        $this->line('Type "exit" or "quit" to leave.');
        $this->newLine();
    }
}
