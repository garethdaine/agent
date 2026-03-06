<script setup>
import Button from '@/Components/ui/Button.vue';
import ChatContextSessionInfo from './ChatContextSessionInfo.vue';
import ChatContextActionList from './ChatContextActionList.vue';
import ChatContextAttachments from './ChatContextAttachments.vue';
import { PanelRightClose, Archive } from 'lucide-vue-next';

const props = defineProps({
    session: { type: Object, default: null },
    actions: { type: Array, default: () => [] },
    actionsLoading: { type: Boolean, default: false },
    messages: { type: Array, default: () => [] },
    relativeTime: { type: Function, required: true },
});

const emit = defineEmits(['close', 'archive']);
</script>

<template>
    <div class="flex w-[320px] shrink-0 flex-col border-l border-border bg-card h-full overflow-hidden">
        <!-- Header -->
        <div class="shrink-0 flex items-center justify-between border-b border-border px-4 py-3">
            <h3 class="text-sm font-semibold text-foreground">Details</h3>
            <div class="flex items-center gap-1">
                <Button
                    v-if="session?.status === 'active'"
                    variant="ghost"
                    size="icon"
                    class="h-7 w-7"
                    title="Archive session"
                    @click="emit('archive', session.id)"
                >
                    <Archive class="h-3.5 w-3.5" />
                </Button>
                <Button
                    variant="ghost"
                    size="icon"
                    class="h-7 w-7"
                    title="Close panel"
                    @click="emit('close')"
                >
                    <PanelRightClose class="h-3.5 w-3.5" />
                </Button>
            </div>
        </div>

        <!-- Scrollable content -->
        <div class="flex-1 overflow-y-auto px-4 py-4 space-y-6">
            <ChatContextSessionInfo
                :session="session"
                :relative-time="relativeTime"
            />

            <div class="border-t border-border" />

            <ChatContextActionList
                :actions="actions"
                :loading="actionsLoading"
            />

            <div class="border-t border-border" />

            <ChatContextAttachments :messages="messages" />
        </div>
    </div>
</template>
