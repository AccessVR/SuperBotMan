<template>
    <div class="flex-1 flex flex-col">
        <div
            v-if="isEmpty"
            class="flex-1 flex flex-col items-center justify-center px-6 text-center"
        >
            <img
                v-if="page?.avatar"
                :src="page.avatar"
                class="rounded-full w-16 h-16 mb-6"
            />
            <h2 class="text-xl font-bold text-gray-900">
                {{ page?.introMessage }}
            </h2>
        </div>
        <ul
            v-else
            class="flex flex-col p-4 list-none"
        >
            <slot>
                <ChatMessage
                    v-for="message in messages"
                    :key="message.id"
                    :message="message"
                    :pageId="props.pageId"
                    @message="onMessage"
                />
                <ChatMessage
                    v-if="$store.state.loading"
                    :loading="true"
                    :message="{ from: 'chatbot' }"
                    :pageId="props.pageId"
                    @message="onMessage"
                />
                <ChatMessage
                    v-if="$store.state.error"
                    :message="{ from: 'chatbot', text: '<span class=\'text-red-500\'>Oops! Please try again.</span>' }"
                    :pageId="props.pageId"
                    @message="onMessage"
                />
            </slot>
        </ul>
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted } from 'vue'
import { useStore } from 'vuex'
import ChatMessage from './ChatMessage.vue'

const store = useStore()

const emit = defineEmits(['message'])

const props = defineProps({
    pageId: {
        type: String,
        required: true,
    },
})

const page = computed(() =>
    store.state.config.pages.find(p => p.id === props.pageId)
)
const messages = computed(() => store.state.messages[props.pageId] || [])
const isEmpty = computed(() =>
    messages.value.length === 0
        && !store.state.loading
        && !store.state.error
        && !!page.value?.introMessage
)

onMounted(() => store.state.showChatInput = true)
onUnmounted(() => store.state.showChatInput = false)

const onMessage = (message, $el) => {
    emit('message', message, $el)
}
</script>
