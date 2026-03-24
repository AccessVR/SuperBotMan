<template>
    <div
        :class="[
            'shrink-0 p-2 relative',
            {
                'rounded-t-lg': !$store.state.config.isMobile && !$store.state.docked
            }
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
            class="absolute right-8 top-1/2 -translate-y-1/2 outline-none text-white text-sm"
            @click.prevent="emitMessage('chat.undock')"
            title="Undock to popup"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
            </svg>
            <span class="sr-only">Undock</span>
        </button>
        <button
            v-if="!$store.state.docked"
            class="absolute right-8 top-1/2 -translate-y-1/2 outline-none text-white text-sm"
            @click.prevent="emitMessage('chat.dock')"
            title="Dock as sidebar"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
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
