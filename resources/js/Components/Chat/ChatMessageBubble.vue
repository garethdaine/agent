<script setup>
import { ref, watch, onMounted } from 'vue';
import MarkdownRenderer from '@/Components/Markdown/MarkdownRenderer.vue';
import ChatCopyButton from './ChatCopyButton.vue';
import ChatMessageActions from './ChatMessageActions.vue';
import ChatMessageAttachments from './ChatMessageAttachments.vue';
import { useCodeHighlighting } from '@/Composables/Chat/useCodeHighlighting';
import { Bot, User } from 'lucide-vue-next';

const props = defineProps({
    message: { type: Object, required: true },
    actions: { type: Array, default: () => [] },
    relativeTime: { type: Function, required: true },
});

const emit = defineEmits(['confirm-action', 'cancel-action']);

const contentRef = ref(null);
const { highlightWithin } = useCodeHighlighting();
const isHovered = ref(false);

const isOutbound = props.message.direction === 'outbound';

const highlightCode = () => {
    if (contentRef.value) {
        highlightWithin(contentRef.value);
    }
};

onMounted(highlightCode);
watch(() => props.message.content, highlightCode);
</script>

<template>
    <div
        class="group flex gap-3 px-2 py-1 relative"
        @mouseenter="isHovered = true"
        @mouseleave="isHovered = false"
    >
        <!-- Avatar -->
        <div class="shrink-0 mt-1">
            <div
                v-if="isOutbound"
                class="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10"
            >
                <Bot class="h-4 w-4 text-primary" />
            </div>
            <div
                v-else
                class="flex h-8 w-8 items-center justify-center rounded-full bg-muted"
            >
                <User class="h-4 w-4 text-muted-foreground" />
            </div>
        </div>

        <!-- Content -->
        <div class="min-w-0 flex-1 max-w-[calc(100%-4rem)]">
            <!-- Sender & Time -->
            <div class="flex items-center gap-2 mb-0.5">
                <span class="text-xs font-semibold" :class="isOutbound ? 'text-primary' : 'text-foreground'">
                    {{ isOutbound ? 'Agent' : 'User' }}
                </span>
                <span class="text-[10px] text-muted-foreground">
                    {{ relativeTime(message.created_at) }}
                </span>

                <!-- Hover actions -->
                <div
                    class="ml-auto flex items-center gap-0.5 transition-opacity duration-100"
                    :class="isHovered ? 'opacity-100' : 'opacity-0'"
                >
                    <ChatCopyButton :content="message.content" />
                </div>
            </div>

            <!-- Message body -->
            <div
                ref="contentRef"
                class="rounded-2xl px-3.5 py-2.5 text-sm"
                :class="[
                    isOutbound
                        ? 'bg-card border border-border rounded-tl-md'
                        : 'bg-muted rounded-tl-md',
                ]"
            >
                <div class="chat-markdown">
                    <MarkdownRenderer :markdown="message.content || ''" :normalize="false" />
                </div>

                <!-- Attachments -->
                <ChatMessageAttachments
                    :attachments="message.attachments ?? []"
                    :attachment-ids="message.attachment_ids ?? []"
                />
            </div>

            <!-- Actions -->
            <ChatMessageActions
                :actions="actions"
                @confirm="emit('confirm-action', $event)"
                @cancel="emit('cancel-action', $event)"
            />
        </div>
    </div>
</template>
