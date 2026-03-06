<script setup>
import { ref } from 'vue';
import Badge from '@/Components/ui/Badge.vue';
import { relativeTime, formatDateTime } from '@/Utils/formatDate';
import {
    CheckCircle2,
    XCircle,
    Clock,
    Play,
    ChevronDown,
    ChevronUp,
    ShieldAlert,
    Zap,
} from 'lucide-vue-next';

const props = defineProps({
    actions: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
});

const expandedAction = ref(null);

const toggleExpand = (actionId) => {
    expandedAction.value = expandedAction.value === actionId ? null : actionId;
};

const statusIcons = {
    pending: Clock,
    executing: Play,
    completed: CheckCircle2,
    failed: XCircle,
    cancelled: XCircle,
    timeout: Clock,
};

const statusColors = {
    pending: 'text-warning',
    executing: 'text-primary',
    completed: 'text-success',
    failed: 'text-destructive',
    cancelled: 'text-muted-foreground',
    timeout: 'text-muted-foreground',
};

const formatActionType = (type) => {
    if (!type) return 'Unknown';
    return type.replace(/[._]/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
};

const formatJson = (data) => {
    try {
        return JSON.stringify(data, null, 2);
    } catch {
        return String(data);
    }
};

</script>

<template>
    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <h4 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                <Zap class="h-3 w-3" />
                Actions
            </h4>
            <Badge v-if="actions.length" variant="outline" class="text-[10px]">
                {{ actions.length }}
            </Badge>
        </div>

        <div v-if="loading" class="text-xs text-muted-foreground">Loading actions...</div>

        <div v-else-if="actions.length === 0" class="text-xs text-muted-foreground">
            No actions in this session.
        </div>

        <div v-else class="space-y-1">
            <div
                v-for="action in actions"
                :key="action.id"
                class="rounded-lg border border-border"
            >
                <button
                    type="button"
                    class="flex w-full items-center gap-2 px-2.5 py-2 text-left text-xs hover:bg-muted/50 transition-colors rounded-lg"
                    @click="toggleExpand(action.id)"
                >
                    <component
                        :is="statusIcons[action.status] ?? Clock"
                        class="h-3 w-3 shrink-0"
                        :class="statusColors[action.status]"
                    />
                    <span class="truncate font-medium text-foreground">
                        {{ formatActionType(action.action_type) }}
                    </span>
                    <ShieldAlert v-if="action.is_destructive" class="h-3 w-3 text-destructive shrink-0" />
                    <span class="ml-auto text-[10px] text-muted-foreground shrink-0">
                        {{ relativeTime(action.created_at) }}
                    </span>
                    <ChevronDown v-if="expandedAction !== action.id" class="h-3 w-3 text-muted-foreground shrink-0" />
                    <ChevronUp v-else class="h-3 w-3 text-muted-foreground shrink-0" />
                </button>

                <div v-if="expandedAction === action.id" class="border-t border-border px-2.5 py-2 text-xs space-y-1.5">
                    <div class="flex items-center justify-between">
                        <span class="text-muted-foreground">Status</span>
                        <Badge variant="outline" class="text-[10px]">{{ action.status }}</Badge>
                    </div>
                    <div v-if="action.requires_confirmation" class="flex items-center justify-between">
                        <span class="text-muted-foreground">Confirmation</span>
                        <span class="text-foreground">{{ action.confirmed_at ? 'Confirmed' : 'Required' }}</span>
                    </div>
                    <div v-if="action.executed_at" class="flex items-center justify-between">
                        <span class="text-muted-foreground">Executed</span>
                        <span class="text-foreground">{{ relativeTime(action.executed_at) }}</span>
                    </div>
                    <div v-if="action.parameters" class="mt-1">
                        <span class="text-muted-foreground block mb-1">Parameters</span>
                        <pre class="rounded bg-muted/50 border border-border p-1.5 overflow-x-auto text-[10px] font-mono"><code>{{ formatJson(action.parameters) }}</code></pre>
                    </div>
                    <div v-if="action.result" class="mt-1">
                        <span class="text-muted-foreground block mb-1">Result</span>
                        <pre class="rounded bg-muted/50 border border-border p-1.5 overflow-x-auto text-[10px] font-mono max-h-[200px]"><code>{{ formatJson(action.result) }}</code></pre>
                    </div>
                    <div v-if="action.error" class="mt-1">
                        <span class="text-destructive block mb-1">Error</span>
                        <div class="rounded bg-destructive/10 px-2 py-1 text-destructive">
                            {{ action.error }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
