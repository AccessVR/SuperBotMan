<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->string('chat_conversation_id');
            $table->string('role');
            $table->text('content');
            $table->timestamp('created_at')->nullable();

            $table->foreign('chat_conversation_id')
                ->references('id')
                ->on('chat_conversations')
                ->cascadeOnDelete();

            $table->index('chat_conversation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
