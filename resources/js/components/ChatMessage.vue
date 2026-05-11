<template>
    <li
        ref="message"
        v-if="visible" 
        :data-message-id="props.message.id"
        :class="[
            'flex mb-2 :last:mb-0',
            {
                'max-w-[85%] ml-auto': props.message.from === 'visitor',
                'w-full': props.message.from !== 'visitor',    
            }
        ]"
    >
        <div
            v-if="props.message.from !== 'visitor'" 
            class="flex-shrink-0 mt-1.5 mr-2"
        >
            <div 
                class="w-6 h-6 rounded-full flex items-center justify-center"
                :style="{
                    backgroundColor: store.state.config.mainColor,
                }"
            >
                <img
                    v-if="avatar"
                    :src="avatar"
                    class="w-full h-full object-cover rounded-full"
                >
                <span
                    v-else
                    class="icon w-4 h-4"
                >
                    <span v-html="icon"></span>
                </span>
            </div>
        </div>
        <div 
            v-if="loading || props.message.type === MessageTypes.TypingIndicator"
            class="py-2 text-sm"
        >
            <div class="my-1 w-3 h-3 rounded-full bg-black animate-pulse"></div>
        </div>
        <div 
            v-else-if="props.message.from === 'chatbot'"
            :class="[
                'message-text py-2 px-4 rounded-lg text-sm',
                {
                    'bg-gray-200': props.message.from === 'visitor',
                    'bg-white': props.message.from !== 'visitor',
                }
            ]"
            v-html="props.message.text"
        ></div>
        <div 
            v-else
            :class="[
                'message-text py-2 px-4 rounded-lg text-sm',
                {
                    'bg-gray-200': props.message.from === 'visitor',
                    'bg-white': props.message.from !== 'visitor',
                }
            ]"
        >
            {{ props.message.text }}
        </div>
    </li>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useStore } from 'vuex'
import { MessageTypes } from '../utils'

const message = ref(null)

const emit = defineEmits(['message'])

const props = defineProps({
    message: {
        type: Object,
        required: true
    },
    loading: {
        type: Boolean,
        default: false,
    },
    pageId: {
        type: String,
        default: null,
    },
})

let visible = ref(props.message.type === MessageTypes.TypingIndicator)

const store = useStore()

const icon = computed(() => {
    if (props.message.additionalParameters?.icon) {
        return store.state.config.icons[props.message.additionalParameters.icon]
    } else if (props.message.from === 'visitor') {
        return store.state.config.icons.user
    } else {
        return store.state.config.icons.bot
    }
})

const pageAvatar = computed(() => {
    if (!props.pageId) return null
    const page = (store.state.config.pages || []).find(p => p.id === props.pageId)
    return page?.avatar || null
})

const avatar = computed(() => {
    if (props.message.additionalParameters?.avatar) {
        return props.message.additionalParameters.avatar
    }
    if (props.message.from !== 'visitor') {
        return pageAvatar.value
    }
    return null
})

onMounted(() => {
    setTimeout(() => {
        visible.value = props.message.type !== MessageTypes.TypingIndicator
        emit('message', props.message, message.value?.$el) 
    }, (props.message.timeout || 0) * 1000)
})
</script>