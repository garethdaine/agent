<script setup>
import Badge from '@/Components/ui/Badge.vue';
import { Hash, Radio, Clock, Calendar, MessageSquare } from 'lucide-vue-next';
import { formatDateTime, relativeTime as sharedRelativeTime } from '@/Utils/formatDate';

const props = defineProps({
    session: { type: Object, default: null },
    relativeTime: { type: Function, default: null },
});

const providerLabels = {
    slack: 'Slack',
    telegram: 'Telegram',
    discord: 'Discord',
    whatsapp: 'WhatsApp',
};

const fmtRelative = (ts) => (props.relativeTime ?? sharedRelativeTime)(ts);
</script>

<template>
    <div v-if="session" class="space-y-3">
        <h4 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
            Session Info
        </h4>

        <div class="space-y-2.5">
            <div class="flex items-center justify-between text-sm">
                <span class="text-muted-foreground flex items-center gap-1.5">
                    <Radio class="h-3 w-3" />
                    Status
                </span>
                <Badge :variant="session.status === 'active' ? 'default' : 'outline'">
                    {{ session.status }}
                </Badge>
            </div>

            <div class="flex items-center justify-between text-sm">
                <span class="text-muted-foreground flex items-center gap-1.5">
                    <MessageSquare class="h-3 w-3" />
                    Provider
                </span>
                <span class="text-foreground font-medium text-xs">
                    {{ providerLabels[session.connector_account?.provider] ?? session.provider ?? '—' }}
                </span>
            </div>

            <div class="flex items-center justify-between text-sm">
                <span class="text-muted-foreground flex items-center gap-1.5">
                    <Hash class="h-3 w-3" />
                    Channel
                </span>
                <span class="text-foreground font-mono text-xs truncate max-w-[140px]" :title="session.channel_id">
                    {{ session.channel_id || '—' }}
                </span>
            </div>

            <div v-if="session.thread_id" class="flex items-center justify-between text-sm">
                <span class="text-muted-foreground flex items-center gap-1.5">
                    <Hash class="h-3 w-3" />
                    Thread
                </span>
                <span class="text-foreground font-mono text-xs truncate max-w-[140px]" :title="session.thread_id">
                    {{ session.thread_id }}
                </span>
            </div>

            <div class="flex items-center justify-between text-sm">
                <span class="text-muted-foreground flex items-center gap-1.5">
                    <MessageSquare class="h-3 w-3" />
                    Messages
                </span>
                <span class="text-foreground font-medium text-xs">
                    {{ session.messages_count ?? 0 }}
                </span>
            </div>

            <div class="flex items-center justify-between text-sm">
                <span class="text-muted-foreground flex items-center gap-1.5">
                    <Calendar class="h-3 w-3" />
                    Created
                </span>
                <span class="text-foreground text-xs">
                    {{ formatDateTime(session.created_at) }}
                </span>
            </div>

            <div class="flex items-center justify-between text-sm">
                <span class="text-muted-foreground flex items-center gap-1.5">
                    <Clock class="h-3 w-3" />
                    Updated
                </span>
                <span class="text-foreground text-xs">
                    {{ fmtRelative(session.updated_at) }}
                </span>
            </div>
        </div>
    </div>
</template>
