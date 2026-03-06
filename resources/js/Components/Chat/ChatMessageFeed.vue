<script setup>
import { ref, watch, nextTick, onMounted } from 'vue';
import ChatMessageBubble from './ChatMessageBubble.vue';
import Skeleton from '@/Components/ui/Skeleton.vue';
import { ArrowDown } from 'lucide-vue-next';

const props = defineProps({
    messages: { type: Array, default: () => [] },
    actions: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    relativeTime: { type: Function, required: true },
});

const emit = defineEmits(['confirm-action', 'cancel-action']);

const feedRef = ref(null);
const isAtBottom = ref(true);

const scrollToBottom = (smooth = false) => {
    if (!feedRef.value) return;
    feedRef.value.scrollTo({
        top: feedRef.value.scrollHeight,
        behavior: smooth ? 'smooth' : 'instant',
    });
};

const handleScroll = () => {
    if (!feedRef.value) return;
    const { scrollTop, scrollHeight, clientHeight } = feedRef.value;
    isAtBottom.value = scrollHeight - scrollTop - clientHeight < 60;
};

const actionsForMessage = (messageId) => {
    return props.actions.filter((a) => a.chat_message_id === messageId);
};

// Group messages by date
const groupedItems = () => {
    const groups = [];
    let currentDate = null;

    for (const msg of props.messages) {
        const date = new Date(msg.created_at).toLocaleDateString('en-US', {
            weekday: 'long',
            month: 'long',
            day: 'numeric',
        });

        if (date !== currentDate) {
            currentDate = date;
            groups.push({ type: 'date', label: date, key: `date-${date}` });
        }

        groups.push({ type: 'message', data: msg, key: msg.id });
    }

    return groups;
};

// Auto-scroll on new messages
watch(
    () => props.messages.length,
    async () => {
        if (isAtBottom.value) {
            await nextTick();
            scrollToBottom();
        }
    },
);

onMounted(() => {
    nextTick(() => scrollToBottom());
});

defineExpose({ feedRef, scrollToBottom });
</script>

<template>
    <div class="relative flex-1 overflow-hidden">
        <!-- Scrollable feed -->
        <div
            ref="feedRef"
            class="h-full overflow-y-auto px-4 py-4 scroll-smooth"
            @scroll="handleScroll"
        >
            <!-- Loading skeleton -->
            <div v-if="loading && messages.length === 0" class="space-y-4 px-2">
                <div v-for="i in 4" :key="i" class="flex gap-3">
                    <Skeleton class="h-8 w-8 rounded-full shrink-0" />
                    <div class="space-y-2 flex-1">
                        <Skeleton class="h-3 w-20" />
                        <Skeleton class="h-16 rounded-2xl" :class="i % 2 === 0 ? 'w-3/4' : 'w-2/3'" />
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <div v-else-if="messages.length === 0" class="flex h-full items-center justify-center">
                <p class="text-sm text-muted-foreground">No messages yet.</p>
            </div>

            <!-- Messages -->
            <div v-else class="space-y-1">
                <template v-for="item in groupedItems()" :key="item.key">
                    <!-- Date separator -->
                    <div v-if="item.type === 'date'" class="flex items-center gap-3 py-3">
                        <div class="flex-1 border-t border-border" />
                        <span class="text-[10px] font-medium uppercase tracking-wider text-muted-foreground">
                            {{ item.label }}
                        </span>
                        <div class="flex-1 border-t border-border" />
                    </div>

                    <!-- Message bubble -->
                    <ChatMessageBubble
                        v-else
                        :message="item.data"
                        :actions="actionsForMessage(item.data.id)"
                        :relative-time="relativeTime"
                        @confirm-action="emit('confirm-action', $event)"
                        @cancel-action="emit('cancel-action', $event)"
                    />
                </template>
            </div>

            <!-- Bottom sentinel -->
            <div class="h-1" />
        </div>

        <!-- Scroll to bottom FAB -->
        <Transition
            enter-active-class="transition-all duration-200 ease-out"
            enter-from-class="opacity-0 translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition-all duration-150 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 translate-y-2"
        >
            <button
                v-if="!isAtBottom && messages.length > 0"
                type="button"
                class="absolute bottom-4 right-6 flex h-8 w-8 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-lg transition-transform hover:scale-105 focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                title="Scroll to bottom"
                @click="scrollToBottom(true)"
            >
                <ArrowDown class="h-4 w-4" />
            </button>
        </Transition>
    </div>
</template>
