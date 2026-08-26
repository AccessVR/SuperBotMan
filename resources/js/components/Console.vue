<template>
    <div
        v-if="!page"
        class="flex h-dvh items-center justify-center bg-gray-100 text-sm text-gray-500"
    >
        Chat is not available. No page named "{{ pageId }}" is configured.
    </div>
    <div
        v-else
        class="flex h-dvh bg-gray-100"
    >
        <div
            class="hidden md:block shrink-0"
            :style="{ width: sidebarWidth + 'px' }"
        >
            <ConsoleSidebar
                :conversations="conversations"
                :active-id="activeId"
                @new-chat="onNewChat"
                @resume="onResume"
                @delete="onDelete"
            />
        </div>
        <div class="relative hidden md:block w-px shrink-0 bg-gray-200">
            <div
                class="absolute inset-y-0 -left-1 -right-1 cursor-col-resize touch-none"
                @pointerdown="startResize"
            ></div>
        </div>
        <div class="flex-1 flex flex-col min-w-0">
            <div class="shrink-0 flex items-center gap-2 px-2 py-2 bg-white border-b border-gray-200">
                <div class="flex-1 min-w-0 text-sm text-gray-700 text-center truncate">
                    {{ headerTitle }}
                </div>
                <button
                    class="shrink-0 p-1 text-gray-500 hover:text-gray-900 outline-none"
                    @click="onClose"
                    title="Close full-screen chat"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    <span class="sr-only">Close full-screen chat</span>
                </button>
            </div>
            <ChatBody ref="body">
                <div class="max-w-3xl mx-auto w-full min-h-full flex flex-col">
                    <ChatMessages
                        :pageId="pageId"
                        @message="onMessage"
                    />
                </div>
            </ChatBody>
            <div class="w-full px-3 pt-2 pb-3">
                <div class="max-w-3xl mx-auto w-full">
                    <ChatInput
                        v-if="$store.state.showChatInput"
                        ref="chatInput"
                        @submit="onChatInputSubmit"
                    />
                    <p
                        v-if="page.disclaimer"
                        class="text-xs text-gray-500 text-center mt-2"
                    >
                        {{ page.disclaimer }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue'
import { useStore } from 'vuex'
import { useAgentTurns } from '../composables/useAgentTurns'
import { useConversations } from '../composables/useConversations'
import ChatBody from './ChatBody.vue'
import ChatMessages from './ChatMessages.vue'
import ChatInput from './ChatInput.vue'
import ConsoleSidebar from './ConsoleSidebar.vue'

const store = useStore()
const body = ref(null)
const chatInput = ref(null)

const pageId = store.state.config.consolePage || 'chat'
const page = computed(() =>
    store.state.config.pages.find(p => p.id === pageId)
)

const { say, restoreConversation, onResumeConversation } = useAgentTurns(store, {
    fallbackPage: () => pageId,
})

const {
    conversations,
    fetchConversations,
    resumeConversation,
    deleteConversation,
} = useConversations(store, { pageId })

const activeId = computed(() => store.state.conversationId[pageId] || null)

const headerTitle = computed(() => {
    const active = conversations.value.find(c => c.id === activeId.value)
    return active?.title || page.value?.title || store.state.config.title
})

// --- Resizable sidebar --------------------------------------------------
// Dragging the divider lets long conversation titles breathe. The width
// is cosmetic, so persisting it in a console-only key doesn't collide
// with the widget's state slice.

const SIDEBAR_WIDTH_KEY = 'super-botman:console:sidebar-width'
const SIDEBAR_MIN = 200
const SIDEBAR_MAX = 480

const clampSidebarWidth = (width) =>
    Math.min(SIDEBAR_MAX, Math.max(SIDEBAR_MIN, width))

const initialSidebarWidth = () => {
    try {
        const stored = parseInt(window.localStorage?.getItem(SIDEBAR_WIDTH_KEY), 10)
        return Number.isFinite(stored) ? clampSidebarWidth(stored) : 256
    } catch (e) {
        return 256
    }
}

const sidebarWidth = ref(initialSidebarWidth())

const startResize = (event) => {
    event.preventDefault()
    const startX = event.clientX
    const startWidth = sidebarWidth.value

    const onMove = (moveEvent) => {
        sidebarWidth.value = clampSidebarWidth(startWidth + (moveEvent.clientX - startX))
    }
    const onUp = () => {
        window.removeEventListener('pointermove', onMove)
        window.removeEventListener('pointerup', onUp)
        document.body.style.cursor = ''
        document.body.style.userSelect = ''
        try {
            window.localStorage?.setItem(SIDEBAR_WIDTH_KEY, String(sidebarWidth.value))
        } catch (e) {
            // Storage full / disabled / private mode — silently ignore.
        }
    }

    document.body.style.cursor = 'col-resize'
    document.body.style.userSelect = 'none'
    window.addEventListener('pointermove', onMove)
    window.addEventListener('pointerup', onUp)
}

// Continuity rides the URL hash (#/c/{conversationId}) instead of the
// widget's localStorage slice, so the console and the docked widget
// never fight over persisted state.
const conversationIdFromHash = () => {
    const match = window.location.hash.match(/^#\/c\/(.+)$/)
    return match ? match[1] : null
}

watch(activeId, (id) => {
    window.history.replaceState(null, '', id ? `#/c/${id}` : window.location.pathname)
})

// Refresh the sidebar at the end of every turn (the `loading` mutation
// flips false on both the sync and queued reply paths). The queued
// titler backfills the conversation title after the reply broadcast, so
// when the active row is still untitled, fetch once more shortly after.
let titleRetryTimer = null

const refreshSidebar = async () => {
    await fetchConversations()
    clearTimeout(titleRetryTimer)
    const active = conversations.value.find(c => c.id === activeId.value)
    if (activeId.value && active && !active.title) {
        titleRetryTimer = setTimeout(fetchConversations, 3000)
    }
}

store.subscribe((mutation) => {
    if (mutation.type === 'loading' && mutation.payload === false) {
        refreshSidebar()
    }
})

// The console is entered from the widget's expand button in the same
// tab, so closing means going back to wherever the user came from; a
// direct visit with no history lands on the app root instead.
const onClose = () => {
    if (window.history.length > 1) {
        window.history.back()
    } else {
        window.location.assign('/')
    }
}

const onNewChat = () => {
    store.commit('clearConversation', pageId)
    store.commit('error', false)
    nextTick(() => chatInput.value?.focus())
}

const onResume = async (id) => {
    const conversation = await resumeConversation(id)
    if (conversation) {
        onResumeConversation(conversation)
    }
}

const onDelete = async (id) => {
    if (!await deleteConversation(id)) {
        return
    }
    if (id === activeId.value) {
        onNewChat()
    }
}

const onChatInputSubmit = () => {
    if (!store.state.loading && !store.state.waiting) {
        say({ ...store.state.input })
        store.commit('resetInput')
    }
}

const onMessage = () => {
    // Scrolling is handled by ChatBody's MutationObserver
}

onMounted(() => {
    store.commit('page', pageId)

    const conversationId = conversationIdFromHash()
    if (conversationId) {
        restoreConversation(pageId, conversationId)
    }

    fetchConversations()
})
</script>
