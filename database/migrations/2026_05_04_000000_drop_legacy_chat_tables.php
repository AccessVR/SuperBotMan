<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_conversations');
    }

    public function down(): void
    {
        // No-op. SuperBotMan no longer uses these tables — recreating them
        // would not restore the deleted models or controllers, so rolling
        // back this migration is intentionally a no-op.
    }
};
