<template>
    <div v-if="conversations.length" class="mt-6">
        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide px-1 mb-2">
            Recent Conversations
        </h3>
        <div
            v-for="conversation in conversations"
            :key="conversation.id"
            class="flex items-center gap-2 px-3 py-2.5 rounded-lg cursor-pointer hover:bg-gray-50 border border-gray-200 bg-white mb-2"
            @click="onResume(conversation.id)"
        >
            <div class="shrink-0 text-gray-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-sm font-medium text-gray-900 truncate">
                    {{ conversation.title || 'Untitled' }}
                </div>
                <div class="text-xs text-gray-500">
                    {{ formatTime(conversation.updated_at) }}
                </div>
            </div>
            <button
                class="shrink-0 p-1 text-gray-400 hover:text-red-500"
                @click.stop="deleteConversation(conversation.id)"
                title="Delete"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>
</template>

<script setup>
import { onMounted, watch } from 'vue'
import { useStore } from 'vuex'
import { useConversations } from '../composables/useConversations'

const emit = defineEmits(['resume'])

const store = useStore()

const {
    conversations,
    fetchConversations,
    resumeConversation,
    deleteConversation,
    formatTime,
} = useConversations(store)

watch(() => store.state.page, (newPage) => {
    if (newPage === 'home') {
        fetchConversations()
    }
})

const onResume = async (id) => {
    const conversation = await resumeConversation(id)
    if (conversation) {
        emit('resume', conversation)
    }
}

onMounted(fetchConversations)
</script>
