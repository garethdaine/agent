<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { X, User, Server, Users, Mail, BookOpen, Shield, Wrench, Coffee, AlertTriangle, Monitor } from 'lucide-vue-next';

const props = defineProps({
    visible: { type: Boolean, default: false },
    data: { type: Object, default: null },
    officeState: { type: Object, default: null },
});

const emit = defineEmits(['close']);

const ZONE_ICONS = {
    serverRack: Server,
    warRoom: Monitor,
    securityDesk: Shield,
    workstations: User,
    mailroom: Mail,
    conference: Users,
    vault: BookOpen,
    toolWorkshop: Wrench,
    breakRoom: Coffee,
    escalation: AlertTriangle,
};

const ZONE_ROUTES = {
    warRoom: 'agent.monitor.index',
    conference: 'agent.delegation.index',
    mailroom: 'tools.messenger.index',
    vault: 'tools.memory.index',
};

const panelIcon = computed(() => {
    if (!props.data) return null;
    if (props.data.type === 'agent') return User;
    return ZONE_ICONS[props.data.zoneId] || Server;
});

const panelTitle = computed(() => {
    if (!props.data) return '';
    if (props.data.type === 'agent') return props.data.agentName || 'Agent';
    return props.data.zoneName || props.data.zoneId || 'Zone';
});

const agentDetail = computed(() => {
    if (props.data?.type !== 'agent' || !props.officeState?.agents) return null;
    return props.officeState.agents.find((a) => a.id === props.data.agentId);
});

const zoneDetail = computed(() => {
    if (props.data?.type !== 'zone' || !props.officeState) return null;
    const id = props.data.zoneId;
    const state = props.officeState;

    switch (id) {
        case 'serverRack':
        case 'warRoom':
            return { section: 'system', data: state.system };
        case 'conference':
            return { section: 'delegation', data: state.delegation };
        case 'mailroom':
            return { section: 'messenger', data: state.messenger };
        case 'vault':
            return { section: 'memory', data: state.memory };
        case 'securityDesk':
            return { section: 'security', data: { mode: state.system?.runtime_mode } };
        default:
            return null;
    }
});

const linkedRoute = computed(() => {
    if (!props.data) return null;
    return ZONE_ROUTES[props.data.zoneId] || null;
});

function statusColor(status) {
    const map = { running: 'text-emerald-400', idle: 'text-blue-400', waiting: 'text-amber-400', failed: 'text-red-400', succeeded: 'text-emerald-400' };
    return map[status] || 'text-muted-foreground';
}
</script>

<template>
    <Transition
        enter-active-class="transition-transform duration-300 ease-out"
        enter-from-class="translate-x-full"
        enter-to-class="translate-x-0"
        leave-active-class="transition-transform duration-200 ease-in"
        leave-from-class="translate-x-0"
        leave-to-class="translate-x-full"
    >
        <div
            v-if="visible && data"
            class="absolute right-4 top-4 bottom-20 w-80 rounded-xl border border-border/50 bg-background/95 backdrop-blur-md shadow-2xl overflow-hidden flex flex-col z-10"
        >
            <div class="flex items-center justify-between px-4 py-3 border-b border-border/50 bg-muted/30">
                <div class="flex items-center gap-2.5">
                    <component
                        :is="panelIcon"
                        class="h-4 w-4 text-primary"
                    />
                    <span class="text-sm font-semibold text-foreground">{{ panelTitle }}</span>
                </div>
                <button
                    class="rounded-md p-1 text-muted-foreground hover:text-foreground hover:bg-muted/50 transition-colors"
                    @click="emit('close')"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>

            <div class="flex-1 overflow-y-auto p-4 space-y-4">
                <!-- Agent Detail -->
                <template v-if="data.type === 'agent' && agentDetail">
                    <div class="space-y-3">
                        <div class="flex items-center gap-2">
                            <span class="h-2.5 w-2.5 rounded-full" :class="{
                                'bg-emerald-500': agentDetail.status === 'running',
                                'bg-amber-500': agentDetail.status === 'waiting',
                                'bg-red-500': agentDetail.status === 'failed',
                                'bg-blue-400': agentDetail.status === 'idle',
                            }" />
                            <span class="text-sm capitalize" :class="statusColor(agentDetail.status)">
                                {{ agentDetail.status }}
                            </span>
                        </div>

                        <div class="rounded-lg bg-muted/30 p-3 space-y-2">
                            <div class="text-xs text-muted-foreground">Role</div>
                            <div class="text-sm text-foreground capitalize">{{ agentDetail.role }}</div>
                        </div>

                        <div class="rounded-lg bg-muted/30 p-3 space-y-2">
                            <div class="text-xs text-muted-foreground">Activity</div>
                            <div class="text-sm text-foreground">{{ agentDetail.current_activity?.replace(/_/g, ' ') || 'Idle' }}</div>
                        </div>

                        <div v-if="agentDetail.current_run" class="rounded-lg bg-muted/30 p-3 space-y-2">
                            <div class="text-xs text-muted-foreground">Current Run</div>
                            <div class="text-sm text-foreground">{{ agentDetail.current_run.job_name }}</div>
                            <div class="text-xs text-muted-foreground">Status: {{ agentDetail.current_run.status }}</div>
                        </div>

                        <div v-if="agentDetail.tools_active?.length > 0" class="rounded-lg bg-muted/30 p-3 space-y-2">
                            <div class="text-xs text-muted-foreground">Active Tools</div>
                            <div class="flex flex-wrap gap-1">
                                <span
                                    v-for="tool in agentDetail.tools_active"
                                    :key="tool"
                                    class="rounded bg-primary/10 px-2 py-0.5 text-xs text-primary"
                                >
                                    {{ tool }}
                                </span>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Zone Detail -->
                <template v-else-if="data.type === 'zone' && zoneDetail">
                    <template v-if="zoneDetail.section === 'system'">
                        <div class="space-y-3">
                            <div class="rounded-lg bg-muted/30 p-3 space-y-2">
                                <div class="text-xs text-muted-foreground">Scheduler</div>
                                <div class="flex items-center gap-2">
                                    <span class="h-2 w-2 rounded-full" :class="zoneDetail.data?.scheduler_healthy ? 'bg-emerald-500' : 'bg-red-500'" />
                                    <span class="text-sm">{{ zoneDetail.data?.scheduler_healthy ? 'Healthy' : 'Unhealthy' }}</span>
                                </div>
                            </div>
                            <div class="rounded-lg bg-muted/30 p-3 space-y-2">
                                <div class="text-xs text-muted-foreground">Active Runs</div>
                                <div class="text-2xl font-bold text-foreground">{{ zoneDetail.data?.active_runs ?? 0 }}</div>
                            </div>
                            <div class="rounded-lg bg-muted/30 p-3 space-y-2">
                                <div class="text-xs text-muted-foreground">Rate Limited</div>
                                <div class="text-sm" :class="zoneDetail.data?.rate_limited ? 'text-red-400' : 'text-emerald-400'">
                                    {{ zoneDetail.data?.rate_limited ? 'Yes' : 'No' }}
                                </div>
                            </div>
                        </div>
                    </template>

                    <template v-else-if="zoneDetail.section === 'delegation'">
                        <div class="space-y-3">
                            <div class="rounded-lg bg-muted/30 p-3 space-y-2">
                                <div class="text-xs text-muted-foreground">Active Graphs</div>
                                <div class="text-2xl font-bold text-foreground">{{ zoneDetail.data?.active_graphs ?? 0 }}</div>
                            </div>
                            <div class="rounded-lg bg-muted/30 p-3 space-y-2">
                                <div class="text-xs text-muted-foreground">Running Tasks</div>
                                <div class="text-sm text-foreground">{{ zoneDetail.data?.tasks_running ?? 0 }}</div>
                            </div>
                            <div class="rounded-lg bg-muted/30 p-3 space-y-2">
                                <div class="text-xs text-muted-foreground">Pending Verification</div>
                                <div class="text-sm text-foreground">{{ zoneDetail.data?.tasks_pending_verification ?? 0 }}</div>
                            </div>
                        </div>
                    </template>

                    <template v-else-if="zoneDetail.section === 'messenger'">
                        <div class="space-y-3">
                            <div
                                v-for="channel in (zoneDetail.data?.channels ?? [])"
                                :key="channel.platform"
                                class="rounded-lg bg-muted/30 p-3 flex items-center justify-between"
                            >
                                <div>
                                    <div class="text-sm font-medium text-foreground capitalize">{{ channel.platform }}</div>
                                    <div class="text-xs text-muted-foreground">{{ channel.unread }} unread</div>
                                </div>
                                <span class="h-2.5 w-2.5 rounded-full" :class="channel.status === 'connected' ? 'bg-emerald-500' : 'bg-red-500'" />
                            </div>
                            <div v-if="!zoneDetail.data?.channels?.length" class="text-sm text-muted-foreground text-center py-4">
                                No channels connected
                            </div>
                        </div>
                    </template>

                    <template v-else-if="zoneDetail.section === 'memory'">
                        <div class="space-y-3">
                            <div class="rounded-lg bg-muted/30 p-3 space-y-2">
                                <div class="text-xs text-muted-foreground">Total Memories</div>
                                <div class="text-2xl font-bold text-foreground">{{ zoneDetail.data?.total_entries?.toLocaleString() ?? 0 }}</div>
                            </div>
                            <div class="rounded-lg bg-muted/30 p-3 space-y-2">
                                <div class="text-xs text-muted-foreground">Recent Formations (1h)</div>
                                <div class="text-sm text-foreground">{{ zoneDetail.data?.recent_formations ?? 0 }}</div>
                            </div>
                        </div>
                    </template>

                    <template v-else-if="zoneDetail.section === 'security'">
                        <div class="space-y-3">
                            <div class="rounded-lg bg-muted/30 p-3 space-y-2">
                                <div class="text-xs text-muted-foreground">Runtime Mode</div>
                                <div class="text-sm font-medium text-foreground capitalize">{{ zoneDetail.data?.mode ?? 'standard' }}</div>
                            </div>
                        </div>
                    </template>
                </template>

                <!-- Fallback -->
                <template v-else>
                    <div class="text-sm text-muted-foreground text-center py-8">
                        Click an agent or zone for details
                    </div>
                </template>
            </div>

            <div v-if="linkedRoute" class="border-t border-border/50 p-3">
                <Link
                    :href="route(linkedRoute)"
                    class="block w-full rounded-lg bg-primary/10 px-4 py-2 text-center text-sm font-medium text-primary hover:bg-primary/20 transition-colors"
                >
                    Open Full View
                </Link>
            </div>
        </div>
    </Transition>
</template>
