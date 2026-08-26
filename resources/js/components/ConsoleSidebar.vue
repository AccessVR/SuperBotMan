<template>
    <div class="h-full w-full flex flex-col bg-white">
        <div class="p-3">
            <button
                class="w-full flex items-center gap-2 px-3 py-2.5 rounded-lg text-sm font-medium text-gray-900 bg-white border border-gray-200 hover:bg-gray-50 outline-none"
                @click="emit('new-chat')"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                New Conversation
            </button>
        </div>
        <div class="flex-1 overflow-y-auto px-3 pb-3">
            <h3
                v-if="conversations.length"
                class="text-xs font-semibold text-gray-500 uppercase tracking-wide px-1 mb-2"
            >
                Conversations
            </h3>
            <div
                v-for="conversation in conversations"
                :key="conversation.id"
                :class="[
                    'group flex items-center gap-2 px-3 py-2 rounded-lg cursor-pointer mb-1',
                    conversation.id === activeId ? 'bg-gray-100' : 'hover:bg-gray-100',
                ]"
                @click="emit('resume', conversation.id)"
            >
                <div class="flex-1 min-w-0 text-sm text-gray-900 truncate">
                    {{ conversation.title || 'Untitled' }}
                </div>
                <button
                    class="shrink-0 p-1 text-gray-400 hover:text-red-500 opacity-0 group-hover:opacity-100 outline-none"
                    @click.stop="emit('delete', conversation.id)"
                    title="Delete"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    <span class="sr-only">Delete</span>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    conversations: {
        type: Array,
        required: true,
    },
    activeId: {
        type: String,
        default: null,
    },
})

const emit = defineEmits(['new-chat', 'resume', 'delete'])
</script>
