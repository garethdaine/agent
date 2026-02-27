<script setup>
import Modal from './Modal.vue';
import { AlertTriangle } from 'lucide-vue-next';

const emit = defineEmits(['close']);

defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    maxWidth: {
        type: String,
        default: '2xl',
    },
    closeable: {
        type: Boolean,
        default: true,
    },
});

const close = () => {
    emit('close');
};
</script>

<template>
    <Modal
        :show="show"
        :max-width="maxWidth"
        :closeable="closeable"
        @close="close"
    >
        <div class="bg-card px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
            <div class="sm:flex sm:items-start">
                <div class="mx-auto shrink-0 flex items-center justify-center size-12 rounded-full bg-destructive/10 sm:mx-0 sm:size-10">
                    <AlertTriangle class="size-6 text-destructive" />
                </div>

                <div class="mt-3 text-center sm:mt-0 sm:ms-4 sm:text-start">
                    <h3 class="text-lg font-medium text-foreground">
                        <slot name="title" />
                    </h3>

                    <div class="mt-4 text-sm text-muted-foreground">
                        <slot name="content" />
                    </div>
                </div>
            </div>
        </div>

        <div class="flex flex-row justify-end px-6 py-4 bg-muted border-t border-border text-end">
            <slot name="footer" />
        </div>
    </Modal>
</template>
