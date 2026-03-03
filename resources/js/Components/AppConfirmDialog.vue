<script setup>
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import Button from '@/Components/ui/Button.vue';
import {
    acceptConfirmDialog,
    confirmDialogState,
    dismissConfirmDialog,
} from '@/Support/confirmDialog';

const close = () => {
    dismissConfirmDialog();
};

const confirm = () => {
    acceptConfirmDialog();
};
</script>

<template>
    <ConfirmationModal
        :show="confirmDialogState.show"
        :closeable="confirmDialogState.closeable"
        @close="close"
    >
        <template #title>
            {{ confirmDialogState.title }}
        </template>

        <template #content>
            <p class="whitespace-pre-wrap break-words">
                {{ confirmDialogState.message }}
            </p>
        </template>

        <template #footer>
            <Button variant="outline" @click="close">
                {{ confirmDialogState.cancelText }}
            </Button>
            <Button :variant="confirmDialogState.confirmVariant" class="ml-3" @click="confirm">
                {{ confirmDialogState.confirmText }}
            </Button>
        </template>
    </ConfirmationModal>
</template>
