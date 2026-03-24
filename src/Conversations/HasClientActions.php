<?php

namespace OrchestrateXR\BotManChatSDK\Conversations;

use OrchestrateXR\BotManChatSDK\Extras\ClientAction;

trait HasClientActions
{
    /** @var ClientAction[] */
    protected array $pendingClientActions = [];

    protected function queueClientAction(string $action, array $payload = []): string
    {
        $this->pendingClientActions[] = ClientAction::make($action, $payload);

        return "Action '{$action}' has been queued for the client.";
    }

    protected function flushClientActions(): void
    {
        foreach ($this->pendingClientActions as $action) {
            $this->say($action);
        }

        $this->pendingClientActions = [];
    }
}
