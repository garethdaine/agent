<script setup>
import Badge from '@/Components/ui/Badge.vue';
import { MessageSquare, Clock, Archive } from 'lucide-vue-next';

const props = defineProps({
    session: { type: Object, required: true },
    active: { type: Boolean, default: false },
    relativeTime: { type: Function, required: true },
});

const emit = defineEmits(['select', 'archive']);

const providerLabels = {
    slack: 'Slack',
    telegram: 'Telegram',
    discord: 'Discord',
    whatsapp: 'WhatsApp',
};

const providerLabel = (provider) => providerLabels[provider] ?? provider ?? '?';
</script>

<template>
    <button
        type="button"
        class="group flex w-full flex-col gap-1.5 rounded-lg px-3.5 py-3 text-left transition-colors duration-100"
        :class="[
            active
                ? 'bg-primary/10 border-l-2 border-primary'
                : 'hover:bg-muted border-l-2 border-transparent',
        ]"
        @click="emit('select', session.id)"
    >
        <div class="flex items-center gap-2 min-w-0">
            <Badge
                :variant="session.status === 'active' ? 'default' : 'outline'"
                class="shrink-0 text-[10px] px-1.5 py-0"
            >
                {{ providerLabel(session.connector_account?.provider) }}
            </Badge>
            <span class="truncate text-sm font-medium" :class="active ? 'text-foreground' : 'text-foreground/80'">
                {{ session.session_key || session.channel_id || 'Unnamed' }}
            </span>
        </div>

        <div class="flex items-center gap-3 text-xs text-muted-foreground">
            <span class="flex items-center gap-1">
                <MessageSquare class="h-3 w-3" />
                {{ session.messages_count ?? 0 }}
            </span>
            <span class="flex items-center gap-1">
                <Clock class="h-3 w-3" />
                {{ relativeTime(session.updated_at) }}
            </span>
            <span class="ml-auto flex items-center gap-1">
                <Badge v-if="session.status === 'archived'" variant="outline" class="text-[10px] px-1 py-0">
                    archived
                </Badge>
                <button
                    v-else
                    type="button"
                    class="opacity-0 group-hover:opacity-100 transition-opacity rounded p-0.5 hover:bg-muted-foreground/10"
                    title="Archive session"
                    @click.stop="emit('archive', session.id)"
                >
                    <Archive class="h-3 w-3" />
                </button>
            </span>
        </div>
    </button>
</template>
