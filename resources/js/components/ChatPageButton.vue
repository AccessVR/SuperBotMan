<template>
    <button class="block w-full bg-sbm-surface text-sbm-ink p-6 rounded-lg shadow-md text-left mb-2 transition-transform hover:-translate-y-1 cursor-pointer">
        <slot name="content">
            <div class="flex items-center">
                <div class="mr-4">
                    <slot name="icon">
                        <div
                            class="h-10 w-10 rounded-full block flex items-center justify-center bg-sbm-main"
                        >
                            <span class="h-5 w-5">
                                <slot name="icon-svg"></slot>
                            </span>
                        </div>
                    </slot>
                </div>
                <div>
                    <slot name="label">
                        <div class="text-sm">
                            <slot name="title"></slot>
                        </div>
                        <div class="text-xs text-sbm-ink-muted">
                            <slot name="description"></slot>
                        </div>
                    </slot>
                </div>
            </div>
        </slot>
    </button>
</template>

<script setup>

</script>