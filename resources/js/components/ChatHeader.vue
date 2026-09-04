<template>
    <div
        :class="[
            'shrink-0 p-2 relative sbm-header-chrome bg-sbm-main',
            { 'sbm-undocked': !$store.state.docked }
        ]"
    >
        <div
            class="flex-grow text-sbm-on-main text-sm text-center cursor-pointer min-h-5"
        >
            <slot name="content">
                <span>{{ $store.state.title }}</span>
            </slot>
        </div>
        <button
            v-if="$store.getters.showBackButton"
            class="absolute left-2 top-1/2 -translate-y-1/2 outline-none text-sbm-on-main text-sm"
            @click.prevent="emit('back')"
        >
            <span class="icon block h-4 w-4" v-html="$store.state.config.icons.back"></span>
            <span class="sr-only">Back</span>
        </button>
        <button
            v-if="consoleUrl"
            class="sbm-fine-pointer-only absolute right-14 top-1/2 -translate-y-1/2 outline-none text-sbm-on-main text-sm"
            @click.prevent="openConsole"
            title="Open full-screen chat"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 4h6v6" />
                <path d="M20 4l-7 7" />
                <path d="M10 20H4v-6" />
                <path d="M4 20l7-7" />
            </svg>
            <span class="sr-only">Open full-screen chat</span>
        </button>
        <button
            v-if="$store.state.docked && !$store.state.config.embedded"
            class="sbm-fine-pointer-only absolute right-8 top-1/2 -translate-y-1/2 outline-none text-sbm-on-main text-sm"
            @click.prevent="emitMessage('chat.undock')"
            title="Switch to windowed mode"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="16" rx="2" />
                <rect x="12" y="13" width="7" height="5" rx="1" fill="currentColor" stroke="none" />
            </svg>
            <span class="sr-only">Switch to windowed mode</span>
        </button>
        <button
            v-if="!$store.state.docked && !$store.state.config.embedded"
            class="sbm-fine-pointer-only absolute right-8 top-1/2 -translate-y-1/2 outline-none text-sbm-on-main text-sm"
            @click.prevent="emitMessage('chat.dock')"
            title="Dock as sidebar"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="16" rx="2" />
                <rect x="14" y="4" width="7" height="16" rx="1" fill="currentColor" stroke="none" />
            </svg>
            <span class="sr-only">Dock as sidebar</span>
        </button>
        <button
            class="absolute right-2 top-1/2 -translate-y-1/2 outline-none text-sbm-on-main text-sm"
            @click.prevent="emit('close')"
        >
            <span class="icon block h-4 w-4" v-html="$store.state.config.icons.close"></span>
            <span class="sr-only">Close</span>
        </button>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { useStore } from 'vuex'
import { emitMessage } from '../utils'

const store = useStore()

const emit = defineEmits([
    'back',
    'close',
])

// Deep-link into the full-screen console: when the widget is already on
// the console's page with an active conversation, carry it over so the
// new tab resumes the same thread.
const consoleUrl = computed(() => {
    const config = store.state.config
    // Offsite embeds never link to the console — it would navigate the
    // customer's own page to our app.
    if (config.consoleEnabled === false || !config.consoleEndpoint || config.embedded) {
        return null
    }
    const pageId = config.consolePage || 'chat'
    const conversationId = store.state.conversationId?.[pageId]
    return (store.state.page === pageId && conversationId)
        ? `${config.consoleEndpoint}#/c/${conversationId}`
        : config.consoleEndpoint
})

// Navigate the TOP window (we're inside the widget iframe) so the
// console replaces the host page in this tab — its Close button then
// returns here via history.back().
const openConsole = () => window.open(consoleUrl.value, '_top')
</script>
