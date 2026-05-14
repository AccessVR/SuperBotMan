<template>
    <div class="relative bg-white rounded-3xl shadow-sm">
        <textarea
            ref="textarea"
            rows="1"
            class="block w-full pl-4 pr-12 py-3 bg-transparent border-none outline-none focus:outline-none focus:ring-0 active:outline-none resize-none text-sm leading-5 max-h-32 overflow-y-auto rounded-3xl placeholder:text-gray-400"
            @input="onInput"
            @keydown="onKeyDown"
            :placeholder="$store.state.config.placeholderText"
            v-model="$store.state.input.text"
        ></textarea>
        <button
            type="button"
            class="absolute right-2 bottom-2 w-7 h-7 rounded-full text-white flex items-center justify-center transition-opacity disabled:opacity-40 disabled:cursor-not-allowed"
            :style="{ backgroundColor: $store.state.config.mainColor }"
            :disabled="!canSend"
            @click="onSubmit"
            title="Send (Ctrl/⌘+Enter)"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 19V5m0 0l-7 7m7-7l7 7" />
            </svg>
            <span class="sr-only">Send</span>
        </button>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import { useStore } from 'vuex'

const store = useStore()
const emit = defineEmits(['submit'])

const textarea = ref(null)

const resize = () => {
    const el = textarea.value
    if (!el) return
    if (!el.value) {
        // Empty — let rows="1" provide the natural one-line height.
        // The widget's chat iframe is display:none until the parent
        // page calls open(), so scrollHeight read at mount time can
        // come back at zero on mobile WebKit; writing that as an
        // inline height collapses the field until the first keystroke
        // re-runs resize.
        el.style.height = ''
        return
    }
    el.style.height = 'auto'
    el.style.height = Math.min(el.scrollHeight, 128) + 'px'
}

const focus = () => {
    nextTick(() => {
        textarea.value?.focus()
        // If the input was preloaded programmatically, the height
        // hasn't been recalculated yet — do it now so wrapped text
        // isn't clipped at the single-line default.
        resize()
    })
}

onMounted(focus)

// External writes to input.text (e.g. startConversation preload)
// don't trigger onInput, so recompute height when text changes from
// outside this component too.
watch(
    () => store.state.input.text,
    () => nextTick(resize)
)

defineExpose({ focus })

const canSend = computed(() =>
    !!store.state.input.text?.trim()
        && !store.state.loading
        && !store.state.waiting
)

const onInput = () => resize()

const onKeyDown = (event) => {
    if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) {
        event.preventDefault()
        onSubmit()
    }
}

const onSubmit = () => {
    if (canSend.value) {
        emit('submit')
    }
}
</script>
