<template>
    <div :class="$slots.heading ? '' : 'h-full flex flex-col'">
        <slot name="heading"></slot>
        <div
            :class="$slots.heading
                ? 'relative w-full border-t-[50px]'
                : 'relative w-full flex-1 flex flex-col'"
            :style="$slots.heading ? { borderTopColor: $store.state.config.mainColor } : {}"
        >
            <div
                :class="$slots.heading
                    ? 'relative w-full -top-[50px]'
                    : 'relative w-full flex-1 flex flex-col'"
            >
                <slot></slot>
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { useStore } from 'vuex'

const store = useStore()

const props = defineProps({
    id: {
        type: String,
        required: true,
    },
    title: {
        type: String,
        default: null,
    },
    description: {
        type: String,
        default: null,
    }
})

onMounted(() => {
    store.state.title = props.title
})
</script>
