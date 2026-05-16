<template>
    <div
        :class="[
            'shrink-0 p-2 relative -mb-px sbm-header-chrome',
            { 'sbm-undocked': !$store.state.docked }
        ]"
        :style="{
            backgroundColor: $store.state.config.mainColor
        }"
    >
        <div
            class="flex-grow text-white text-sm text-center cursor-pointer min-h-5"
        >
            <slot name="content">
                <span>{{ $store.state.title }}</span>
            </slot>
        </div>
        <button
            v-if="$store.getters.showBackButton"
            class="absolute left-2 top-1/2 -translate-y-1/2 outline-none text-white text-sm"
            @click.prevent="emit('back')"
        >
            <span class="icon block h-4 w-4" v-html="$store.state.config.icons.back"></span>
            <span class="sr-only">Back</span>
        </button>
        <button
            v-if="$store.state.docked"
            class="sbm-fine-pointer-only absolute right-8 top-1/2 -translate-y-1/2 outline-none text-white text-sm"
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
            v-if="!$store.state.docked"
            class="sbm-fine-pointer-only absolute right-8 top-1/2 -translate-y-1/2 outline-none text-white text-sm"
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
            class="absolute right-2 top-1/2 -translate-y-1/2 outline-none text-white text-sm"
            @click.prevent="emit('close')"
        >
            <span class="icon block h-4 w-4" v-html="$store.state.config.icons.close"></span>
            <span class="sr-only">Close</span>
        </button>
    </div>
</template>

<script setup>
import { emitMessage } from '../utils'

const emit = defineEmits([
    'back',
    'close',
])
</script>
