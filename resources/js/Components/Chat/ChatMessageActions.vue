<script setup>
import { ref } from 'vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Spinner from '@/Components/ui/Spinner.vue';
import {
    CheckCircle2,
    XCircle,
    AlertTriangle,
    Clock,
    Play,
    ChevronDown,
    ChevronUp,
    ShieldAlert,
} from 'lucide-vue-next';

const props = defineProps({
    actions: { type: Array, default: () => [] },
});

const emit = defineEmits(['confirm', 'cancel']);

const expandedResults = ref(new Set());

const toggleResult = (actionId) => {
    if (expandedResults.value.has(actionId)) {
        expandedResults.value.delete(actionId);
    } else {
        expandedResults.value.add(actionId);
    }
};

const statusConfig = {
    pending: { icon: Clock, color: 'text-warning', bgColor: 'border-warning/30 bg-warning/5', badgeVariant: 'outline', label: 'Pending' },
    executing: { icon: Play, color: 'text-primary', bgColor: 'border-primary/30 bg-primary/5', badgeVariant: 'default', label: 'Executing' },
    completed: { icon: CheckCircle2, color: 'text-success', bgColor: 'border-success/30 bg-success/5', badgeVariant: 'outline', label: 'Completed' },
    failed: { icon: XCircle, color: 'text-destructive', bgColor: 'border-destructive/30 bg-destructive/5', badgeVariant: 'destructive', label: 'Failed' },
    cancelled: { icon: XCircle, color: 'text-muted-foreground', bgColor: 'border-border bg-muted/30', badgeVariant: 'outline', label: 'Cancelled' },
    timeout: { icon: Clock, color: 'text-muted-foreground', bgColor: 'border-border bg-muted/30', badgeVariant: 'outline', label: 'Timeout' },
};

const getConfig = (status) => statusConfig[status] ?? statusConfig.pending;

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
    <div v-if="actions.length" class="space-y-2 mt-2">
        <div
            v-for="action in actions"
            :key="action.id"
            class="rounded-lg border p-3 text-xs"
            :class="[
                getConfig(action.status).bgColor,
                action.is_destructive ? 'ring-1 ring-destructive/20' : '',
            ]"
        >
            <!-- Header -->
            <div class="flex items-center gap-2 mb-1.5">
                <component
                    :is="getConfig(action.status).icon"
                    class="h-3.5 w-3.5 shrink-0"
                    :class="getConfig(action.status).color"
                />
                <span class="font-medium text-foreground">
                    {{ formatActionType(action.action_type) }}
                </span>
                <Badge
                    :variant="getConfig(action.status).badgeVariant"
                    class="text-[10px] px-1.5 py-0 ml-auto"
                >
                    {{ getConfig(action.status).label }}
                </Badge>
                <Badge v-if="action.is_destructive" variant="destructive" class="text-[10px] px-1.5 py-0">
                    <ShieldAlert class="h-2.5 w-2.5 mr-0.5" />
                    Destructive
                </Badge>
            </div>

            <!-- Executing spinner -->
            <div v-if="action.is_executing" class="flex items-center gap-2 text-muted-foreground">
                <Spinner class="h-3 w-3" />
                <span>Processing...</span>
            </div>

            <!-- Pending confirmation buttons -->
            <div v-if="action.is_pending && action.requires_confirmation" class="flex items-center gap-2 mt-2">
                <Button
                    size="sm"
                    class="h-7 text-xs"
                    @click="emit('confirm', action.id)"
                >
                    <CheckCircle2 class="h-3 w-3 mr-1" />
                    Confirm
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    class="h-7 text-xs"
                    @click="emit('cancel', action.id)"
                >
                    <XCircle class="h-3 w-3 mr-1" />
                    Cancel
                </Button>
            </div>

            <!-- Error display -->
            <div v-if="action.error" class="mt-2 rounded-md bg-destructive/10 px-2.5 py-1.5 text-destructive">
                {{ action.error }}
            </div>

            <!-- Result display (collapsible) -->
            <div v-if="action.result && action.is_terminal" class="mt-2">
                <button
                    type="button"
                    class="flex items-center gap-1 text-muted-foreground hover:text-foreground transition-colors"
                    @click="toggleResult(action.id)"
                >
                    <ChevronDown v-if="!expandedResults.has(action.id)" class="h-3 w-3" />
                    <ChevronUp v-else class="h-3 w-3" />
                    <span>{{ expandedResults.has(action.id) ? 'Hide' : 'Show' }} result</span>
                </button>
                <div v-if="expandedResults.has(action.id)" class="mt-1.5">
                    <pre class="rounded-md bg-muted/50 border border-border p-2 overflow-x-auto text-[11px] font-mono leading-relaxed"><code>{{ formatJson(action.result) }}</code></pre>
                </div>
            </div>
        </div>
    </div>
</template>
