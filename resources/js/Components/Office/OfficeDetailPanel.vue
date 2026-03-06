<script setup>
import { computed, ref, onUnmounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import {
    X, User, Server, Users, Mail, BookOpen, Shield, Wrench, Coffee,
    AlertTriangle, Monitor, Play, CheckCircle, XCircle, Clock, Zap,
    Activity, ChevronRight, Pause, Cpu, ExternalLink, Radio, BarChart3,
} from 'lucide-vue-next';

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
    workstations: Cpu,
    mailroom: Mail,
    conference: Users,
    vault: BookOpen,
    toolWorkshop: Wrench,
    breakRoom: Coffee,
    escalation: AlertTriangle,
};

const ZONE_DESCRIPTIONS = {
    serverRack: 'System infrastructure and scheduler health',
    warRoom: 'Active runs monitoring and operations',
    securityDesk: 'Runtime security policies and mode',
    workstations: 'Agent roster and job management',
    mailroom: 'Messenger channels and integrations',
    conference: 'Delegation graphs and task orchestration',
    vault: 'Memory store and knowledge formation',
    toolWorkshop: 'Tool usage, approvals, and runtime calls',
    breakRoom: 'Idle agents and system uptime',
    escalation: 'Incidents and escalations requiring action',
};

const ZONE_ROUTES = {
    warRoom: 'agent.monitor.index',
    conference: 'agent.delegation.index',
    mailroom: 'tools.messenger.index',
    vault: 'tools.memory.index',
    workstations: 'agent.jobs.index',
    securityDesk: 'tools.security.index',
    escalation: 'agent.escalations.index',
};

const now = ref(Date.now());
let tickInterval = null;
tickInterval = setInterval(() => { now.value = Date.now(); }, 1000);
onUnmounted(() => clearInterval(tickInterval));

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

const panelDescription = computed(() => {
    if (props.data?.type === 'zone') return ZONE_DESCRIPTIONS[props.data.zoneId] || '';
    return '';
});

const agentDetail = computed(() => {
    if (props.data?.type !== 'agent' || !props.officeState?.agents) return null;
    return props.officeState.agents.find((a) => a.id === props.data.agentId);
});

const linkedRoute = computed(() => {
    if (!props.data) return null;
    return ZONE_ROUTES[props.data.zoneId] || null;
});

function statusColor(status) {
    const map = {
        running: 'text-emerald-400',
        idle: 'text-blue-400',
        waiting: 'text-amber-400',
        failed: 'text-red-400',
        succeeded: 'text-emerald-400',
        queued: 'text-violet-400',
        starting: 'text-cyan-400',
        stopping: 'text-orange-400',
    };
    return map[status] || 'text-muted-foreground';
}

function statusDot(status) {
    const map = {
        running: 'bg-emerald-500',
        idle: 'bg-blue-400',
        waiting: 'bg-amber-500',
        failed: 'bg-red-500',
        succeeded: 'bg-emerald-500',
        queued: 'bg-violet-400',
        starting: 'bg-cyan-400',
        stopping: 'bg-orange-400',
    };
    return map[status] || 'bg-muted-foreground';
}

function elapsedSince(isoString) {
    if (!isoString) return '—';
    const ms = now.value - new Date(isoString).getTime();
    if (ms < 0) return '0s';
    const s = Math.floor(ms / 1000);
    if (s < 60) return `${s}s`;
    const m = Math.floor(s / 60);
    if (m < 60) return `${m}m ${s % 60}s`;
    const h = Math.floor(m / 60);
    return `${h}h ${m % 60}m`;
}

function formatDuration(ms) {
    if (!ms) return '—';
    if (ms < 1000) return `${ms}ms`;
    const s = Math.floor(ms / 1000);
    if (s < 60) return `${s}s`;
    const m = Math.floor(s / 60);
    return `${m}m ${s % 60}s`;
}

function timeAgo(isoString) {
    if (!isoString) return '';
    const ms = now.value - new Date(isoString).getTime();
    const s = Math.floor(ms / 1000);
    if (s < 60) return 'just now';
    const m = Math.floor(s / 60);
    if (m < 60) return `${m}m ago`;
    const h = Math.floor(m / 60);
    if (h < 24) return `${h}h ago`;
    return `${Math.floor(h / 24)}d ago`;
}

const state = computed(() => props.officeState);
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
            class="absolute right-4 top-4 bottom-20 w-[22rem] rounded-xl border border-border/50 bg-background/95 backdrop-blur-md shadow-2xl overflow-hidden flex flex-col z-10"
        >
            <!-- Header -->
            <div class="flex items-center justify-between px-4 py-3 border-b border-border/50 bg-muted/30">
                <div class="flex items-center gap-2.5 min-w-0">
                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                        <component :is="panelIcon" class="h-3.5 w-3.5 text-primary" />
                    </div>
                    <div class="min-w-0">
                        <span class="text-sm font-semibold text-foreground block truncate">{{ panelTitle }}</span>
                        <span v-if="panelDescription" class="text-[10px] text-muted-foreground block truncate">{{ panelDescription }}</span>
                    </div>
                </div>
                <button
                    class="rounded-md p-1 text-muted-foreground hover:text-foreground hover:bg-muted/50 transition-colors shrink-0"
                    @click="emit('close')"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>

            <!-- Content -->
            <div class="flex-1 overflow-y-auto p-4 space-y-3">

                <!-- ═══════════ AGENT DETAIL ═══════════ -->
                <template v-if="data.type === 'agent' && agentDetail">
                    <div class="flex items-center gap-3 rounded-lg bg-muted/30 p-3">
                        <div class="relative">
                            <div class="h-10 w-10 rounded-full bg-primary/20 flex items-center justify-center">
                                <User class="h-5 w-5 text-primary" />
                            </div>
                            <span class="absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full border-2 border-background" :class="statusDot(agentDetail.status)" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-medium text-foreground truncate">{{ agentDetail.name }}</div>
                            <div class="flex items-center gap-1.5">
                                <span class="text-xs capitalize" :class="statusColor(agentDetail.status)">{{ agentDetail.status }}</span>
                                <span class="text-[10px] text-muted-foreground">·</span>
                                <span class="text-xs text-muted-foreground capitalize">{{ agentDetail.role }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg bg-muted/30 p-3">
                        <div class="flex items-center gap-1.5 text-xs text-muted-foreground mb-2">
                            <Activity class="h-3 w-3" />
                            Activity
                        </div>
                        <div class="text-sm text-foreground capitalize">{{ agentDetail.current_activity?.replace(/_/g, ' ') || 'Idle' }}</div>
                    </div>

                    <div v-if="agentDetail.current_run" class="rounded-lg border border-emerald-500/20 bg-emerald-500/5 p-3 space-y-2">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1.5 text-xs text-emerald-400">
                                <Play class="h-3 w-3" />
                                Active Run
                            </div>
                            <span class="text-[10px] font-mono text-muted-foreground">
                                {{ elapsedSince(agentDetail.current_run.started_at) }}
                            </span>
                        </div>
                        <div class="text-sm font-medium text-foreground">{{ agentDetail.current_run.job_name }}</div>
                        <div class="flex items-center gap-2">
                            <span class="h-1.5 w-1.5 rounded-full animate-pulse" :class="statusDot(agentDetail.current_run.status)" />
                            <span class="text-xs capitalize" :class="statusColor(agentDetail.current_run.status)">
                                {{ agentDetail.current_run.status }}
                            </span>
                        </div>
                    </div>

                    <div v-if="agentDetail.tools_active?.length > 0" class="rounded-lg bg-muted/30 p-3">
                        <div class="flex items-center gap-1.5 text-xs text-muted-foreground mb-2">
                            <Wrench class="h-3 w-3" />
                            Active Tools
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            <span
                                v-for="tool in agentDetail.tools_active"
                                :key="tool"
                                class="rounded-md bg-primary/10 px-2 py-0.5 text-xs text-primary font-mono"
                            >{{ tool }}</span>
                        </div>
                    </div>

                    <div v-if="agentDetail.needs_attention" class="rounded-lg border border-amber-500/20 bg-amber-500/5 p-3">
                        <div class="flex items-center gap-2">
                            <AlertTriangle class="h-4 w-4 text-amber-400" />
                            <span class="text-sm text-amber-400 font-medium">Needs Attention</span>
                        </div>
                    </div>
                </template>

                <!-- ═══════════ SERVER RACK ═══════════ -->
                <template v-else-if="data.type === 'zone' && data.zoneId === 'serverRack'">
                    <div class="grid grid-cols-2 gap-2">
                        <div class="rounded-lg bg-muted/30 p-3">
                            <div class="text-[10px] uppercase tracking-wider text-muted-foreground mb-1">Scheduler</div>
                            <div class="flex items-center gap-1.5">
                                <span class="h-2 w-2 rounded-full" :class="state?.system?.scheduler_healthy ? 'bg-emerald-500' : 'bg-red-500'" />
                                <span class="text-sm font-medium">{{ state?.system?.scheduler_healthy ? 'Healthy' : 'Down' }}</span>
                            </div>
                        </div>
                        <div class="rounded-lg bg-muted/30 p-3">
                            <div class="text-[10px] uppercase tracking-wider text-muted-foreground mb-1">Active Runs</div>
                            <div class="text-xl font-bold text-foreground">{{ state?.system?.active_runs ?? 0 }}</div>
                        </div>
                        <div class="rounded-lg bg-muted/30 p-3">
                            <div class="text-[10px] uppercase tracking-wider text-muted-foreground mb-1">Queue Lag</div>
                            <div class="text-sm font-medium" :class="(state?.system?.queue_lag_seconds ?? 0) > 30 ? 'text-amber-400' : 'text-foreground'">
                                {{ state?.system?.queue_lag_seconds ?? 0 }}s
                            </div>
                        </div>
                        <div class="rounded-lg bg-muted/30 p-3">
                            <div class="text-[10px] uppercase tracking-wider text-muted-foreground mb-1">Rate Limited</div>
                            <div class="text-sm font-medium" :class="state?.system?.rate_limited ? 'text-red-400' : 'text-emerald-400'">
                                {{ state?.system?.rate_limited ? 'Yes' : 'No' }}
                            </div>
                        </div>
                    </div>
                    <div class="rounded-lg bg-muted/30 p-3">
                        <div class="text-[10px] uppercase tracking-wider text-muted-foreground mb-1">Runtime Mode</div>
                        <div class="flex items-center gap-2">
                            <Shield class="h-3.5 w-3.5 text-primary" />
                            <span class="text-sm font-medium text-foreground capitalize">{{ state?.system?.runtime_mode ?? 'standard' }}</span>
                        </div>
                    </div>
                </template>

                <!-- ═══════════ WAR ROOM ═══════════ -->
                <template v-else-if="data.type === 'zone' && data.zoneId === 'warRoom'">
                    <div class="grid grid-cols-2 gap-2">
                        <div class="rounded-lg bg-muted/30 p-3">
                            <div class="text-[10px] uppercase tracking-wider text-muted-foreground mb-1">Active Runs</div>
                            <div class="text-xl font-bold text-foreground">{{ state?.system?.active_runs ?? 0 }}</div>
                        </div>
                        <div class="rounded-lg bg-muted/30 p-3">
                            <div class="text-[10px] uppercase tracking-wider text-muted-foreground mb-1">Scheduler</div>
                            <div class="flex items-center gap-1.5">
                                <span class="h-2 w-2 rounded-full" :class="state?.system?.scheduler_healthy ? 'bg-emerald-500 animate-pulse' : 'bg-red-500'" />
                                <span class="text-sm">{{ state?.system?.scheduler_healthy ? 'Live' : 'Down' }}</span>
                            </div>
                        </div>
                    </div>

                    <div v-if="state?.recent_activity?.length" class="space-y-1.5">
                        <div class="flex items-center gap-1.5 text-[10px] uppercase tracking-wider text-muted-foreground">
                            <Clock class="h-3 w-3" />
                            Recent Activity
                        </div>
                        <div
                            v-for="(item, i) in state.recent_activity.slice(0, 6)"
                            :key="i"
                            class="flex items-center gap-2 rounded-lg bg-muted/20 px-3 py-2"
                        >
                            <component
                                :is="item.type === 'success' ? CheckCircle : item.type === 'failure' ? XCircle : Clock"
                                class="h-3.5 w-3.5 shrink-0"
                                :class="item.type === 'success' ? 'text-emerald-400' : item.type === 'failure' ? 'text-red-400' : 'text-blue-400'"
                            />
                            <div class="min-w-0 flex-1">
                                <div class="text-xs text-foreground truncate">{{ item.job_name }}</div>
                                <div class="flex items-center gap-2 text-[10px] text-muted-foreground">
                                    <span class="capitalize">{{ item.status?.replace(/_/g, ' ') }}</span>
                                    <span v-if="item.duration_ms">· {{ formatDuration(item.duration_ms) }}</span>
                                </div>
                            </div>
                            <span class="text-[10px] text-muted-foreground shrink-0">{{ timeAgo(item.timestamp) }}</span>
                        </div>
                    </div>
                    <div v-else class="text-xs text-muted-foreground text-center py-4">No recent activity</div>
                </template>

                <!-- ═══════════ WORKSTATIONS ═══════════ -->
                <template v-else-if="data.type === 'zone' && data.zoneId === 'workstations'">
                    <div class="grid grid-cols-3 gap-2">
                        <div class="rounded-lg bg-muted/30 p-3 text-center">
                            <div class="text-xl font-bold text-foreground">{{ state?.jobs?.total ?? 0 }}</div>
                            <div class="text-[10px] text-muted-foreground">Jobs</div>
                        </div>
                        <div class="rounded-lg bg-emerald-500/10 p-3 text-center">
                            <div class="text-xl font-bold text-emerald-400">{{ state?.jobs?.enabled ?? 0 }}</div>
                            <div class="text-[10px] text-muted-foreground">Active</div>
                        </div>
                        <div class="rounded-lg bg-muted/30 p-3 text-center">
                            <div class="text-xl font-bold text-muted-foreground">{{ state?.jobs?.disabled ?? 0 }}</div>
                            <div class="text-[10px] text-muted-foreground">Disabled</div>
                        </div>
                    </div>

                    <div v-if="state?.jobs?.governance_paused > 0" class="rounded-lg border border-amber-500/20 bg-amber-500/5 p-3">
                        <div class="flex items-center gap-2">
                            <Pause class="h-3.5 w-3.5 text-amber-400" />
                            <span class="text-xs text-amber-400">{{ state.jobs.governance_paused }} governance paused</span>
                        </div>
                    </div>

                    <div v-if="state?.agents?.length" class="space-y-1.5">
                        <div class="text-[10px] uppercase tracking-wider text-muted-foreground">Agent Roster</div>
                        <div
                            v-for="agent in state.agents"
                            :key="agent.id"
                            class="flex items-center gap-2 rounded-lg bg-muted/20 px-3 py-2"
                        >
                            <span class="h-2 w-2 rounded-full shrink-0" :class="statusDot(agent.status)" />
                            <span class="text-xs text-foreground flex-1 truncate">{{ agent.name }}</span>
                            <span class="text-[10px] capitalize" :class="statusColor(agent.status)">{{ agent.status }}</span>
                        </div>
                    </div>

                    <div v-if="state?.jobs?.list?.length" class="space-y-1.5">
                        <div class="text-[10px] uppercase tracking-wider text-muted-foreground">Jobs</div>
                        <div
                            v-for="job in state.jobs.list"
                            :key="job.id"
                            class="flex items-center gap-2 rounded-lg bg-muted/20 px-3 py-2"
                        >
                            <span class="h-2 w-2 rounded-full shrink-0" :class="job.enabled ? 'bg-emerald-500' : 'bg-muted-foreground'" />
                            <span class="text-xs text-foreground flex-1 truncate">{{ job.name }}</span>
                            <span class="text-[10px] text-muted-foreground font-mono">{{ job.runner_type }}</span>
                        </div>
                    </div>
                </template>

                <!-- ═══════════ CONFERENCE (Delegation) ═══════════ -->
                <template v-else-if="data.type === 'zone' && data.zoneId === 'conference'">
                    <div class="grid grid-cols-3 gap-2">
                        <div class="rounded-lg bg-muted/30 p-3 text-center">
                            <div class="text-xl font-bold text-foreground">{{ state?.delegation?.active_graphs ?? 0 }}</div>
                            <div class="text-[10px] text-muted-foreground">Graphs</div>
                        </div>
                        <div class="rounded-lg bg-emerald-500/10 p-3 text-center">
                            <div class="text-xl font-bold text-emerald-400">{{ state?.delegation?.tasks_running ?? 0 }}</div>
                            <div class="text-[10px] text-muted-foreground">Running</div>
                        </div>
                        <div class="rounded-lg bg-amber-500/10 p-3 text-center">
                            <div class="text-xl font-bold text-amber-400">{{ state?.delegation?.tasks_pending_verification ?? 0 }}</div>
                            <div class="text-[10px] text-muted-foreground">Verifying</div>
                        </div>
                    </div>

                    <div v-if="(state?.delegation?.tasks_pending_verification ?? 0) > 0" class="rounded-lg border border-amber-500/20 bg-amber-500/5 p-3">
                        <div class="flex items-center gap-2">
                            <AlertTriangle class="h-3.5 w-3.5 text-amber-400" />
                            <span class="text-xs text-amber-300">{{ state.delegation.tasks_pending_verification }} task{{ state.delegation.tasks_pending_verification !== 1 ? 's' : '' }} awaiting verification</span>
                        </div>
                    </div>

                    <div v-if="!state?.delegation?.active_graphs" class="text-xs text-muted-foreground text-center py-4">
                        No active delegation graphs
                    </div>
                </template>

                <!-- ═══════════ MAILROOM (Messenger) ═══════════ -->
                <template v-else-if="data.type === 'zone' && data.zoneId === 'mailroom'">
                    <div v-if="state?.messenger?.channels?.length" class="space-y-2">
                        <div
                            v-for="channel in state.messenger.channels"
                            :key="channel.id"
                            class="flex items-center gap-3 rounded-lg bg-muted/30 p-3"
                        >
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                                <Radio class="h-4 w-4 text-primary" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-medium text-foreground capitalize truncate">{{ channel.platform }}</div>
                                <div class="text-xs text-muted-foreground truncate">{{ channel.name }}</div>
                            </div>
                            <div class="flex items-center gap-1.5 shrink-0">
                                <span class="h-2 w-2 rounded-full" :class="channel.status === 'connected' ? 'bg-emerald-500 animate-pulse' : 'bg-red-500'" />
                                <span class="text-[10px]" :class="channel.status === 'connected' ? 'text-emerald-400' : 'text-red-400'">
                                    {{ channel.status }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-6">
                        <Mail class="h-8 w-8 text-muted-foreground/30 mx-auto mb-2" />
                        <div class="text-xs text-muted-foreground">No channels connected</div>
                    </div>
                </template>

                <!-- ═══════════ VAULT (Memory) ═══════════ -->
                <template v-else-if="data.type === 'zone' && data.zoneId === 'vault'">
                    <div class="grid grid-cols-2 gap-2">
                        <div class="rounded-lg bg-muted/30 p-3">
                            <div class="text-[10px] uppercase tracking-wider text-muted-foreground mb-1">Total Memories</div>
                            <div class="text-xl font-bold text-foreground">{{ (state?.memory?.total_entries ?? 0).toLocaleString() }}</div>
                        </div>
                        <div class="rounded-lg bg-muted/30 p-3">
                            <div class="text-[10px] uppercase tracking-wider text-muted-foreground mb-1">Formed (1h)</div>
                            <div class="text-xl font-bold" :class="(state?.memory?.recent_formations ?? 0) > 0 ? 'text-emerald-400' : 'text-muted-foreground'">
                                {{ state?.memory?.recent_formations ?? 0 }}
                            </div>
                        </div>
                    </div>
                    <div class="rounded-lg bg-muted/30 p-3">
                        <div class="flex items-center gap-2">
                            <BookOpen class="h-3.5 w-3.5 text-primary" />
                            <span class="text-xs text-muted-foreground">Memory formation pipeline</span>
                        </div>
                        <div class="mt-2 h-1.5 rounded-full bg-muted/50 overflow-hidden">
                            <div
                                class="h-full rounded-full bg-primary/60 transition-all duration-700"
                                :style="{ width: (state?.memory?.recent_formations ?? 0) > 0 ? '60%' : '0%' }"
                            />
                        </div>
                        <div class="text-[10px] text-muted-foreground mt-1">
                            {{ (state?.memory?.recent_formations ?? 0) > 0 ? 'Actively forming' : 'Idle' }}
                        </div>
                    </div>
                </template>

                <!-- ═══════════ SECURITY DESK ═══════════ -->
                <template v-else-if="data.type === 'zone' && data.zoneId === 'securityDesk'">
                    <div class="rounded-lg bg-muted/30 p-3">
                        <div class="text-[10px] uppercase tracking-wider text-muted-foreground mb-2">Runtime Mode</div>
                        <div class="flex items-center gap-2">
                            <Shield class="h-4 w-4" :class="{
                                'text-emerald-400': state?.system?.runtime_mode === 'standard',
                                'text-amber-400': state?.system?.runtime_mode === 'safe',
                                'text-red-400': state?.system?.runtime_mode === 'full',
                            }" />
                            <span class="text-sm font-semibold text-foreground capitalize">{{ state?.system?.runtime_mode ?? 'standard' }}</span>
                        </div>
                    </div>

                    <div class="rounded-lg bg-muted/30 p-3 space-y-2">
                        <div class="text-[10px] uppercase tracking-wider text-muted-foreground">Tool Approvals</div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-muted-foreground">Pending</span>
                            <span class="text-sm font-medium" :class="(state?.tools?.pending_approvals ?? 0) > 0 ? 'text-amber-400' : 'text-foreground'">
                                {{ state?.tools?.pending_approvals ?? 0 }}
                            </span>
                        </div>
                    </div>

                    <div class="rounded-lg bg-muted/30 p-3 space-y-2">
                        <div class="text-[10px] uppercase tracking-wider text-muted-foreground">System Health</div>
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-muted-foreground">Scheduler</span>
                                <span class="h-2 w-2 rounded-full" :class="state?.system?.scheduler_healthy ? 'bg-emerald-500' : 'bg-red-500'" />
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-muted-foreground">Rate Limited</span>
                                <span class="h-2 w-2 rounded-full" :class="state?.system?.rate_limited ? 'bg-red-500' : 'bg-emerald-500'" />
                            </div>
                        </div>
                    </div>
                </template>

                <!-- ═══════════ TOOL WORKSHOP ═══════════ -->
                <template v-else-if="data.type === 'zone' && data.zoneId === 'toolWorkshop'">
                    <div class="grid grid-cols-3 gap-2">
                        <div class="rounded-lg bg-muted/30 p-3 text-center">
                            <div class="text-xl font-bold text-foreground">{{ state?.tools?.total_calls_24h ?? 0 }}</div>
                            <div class="text-[10px] text-muted-foreground">Calls (24h)</div>
                        </div>
                        <div class="rounded-lg bg-muted/30 p-3 text-center">
                            <div class="text-xl font-bold text-foreground">{{ state?.tools?.unique_tools_24h ?? 0 }}</div>
                            <div class="text-[10px] text-muted-foreground">Tools Used</div>
                        </div>
                        <div class="rounded-lg p-3 text-center" :class="(state?.tools?.pending_approvals ?? 0) > 0 ? 'bg-amber-500/10' : 'bg-muted/30'">
                            <div class="text-xl font-bold" :class="(state?.tools?.pending_approvals ?? 0) > 0 ? 'text-amber-400' : 'text-foreground'">
                                {{ state?.tools?.pending_approvals ?? 0 }}
                            </div>
                            <div class="text-[10px] text-muted-foreground">Pending</div>
                        </div>
                    </div>

                    <div v-if="state?.tools?.recent_calls?.length" class="space-y-1.5">
                        <div class="flex items-center gap-1.5 text-[10px] uppercase tracking-wider text-muted-foreground">
                            <Zap class="h-3 w-3" />
                            Recent Tool Calls
                        </div>
                        <div
                            v-for="call in state.tools.recent_calls.slice(0, 8)"
                            :key="call.id"
                            class="flex items-center gap-2 rounded-lg bg-muted/20 px-3 py-2"
                        >
                            <Wrench class="h-3 w-3 text-muted-foreground shrink-0" />
                            <span class="text-xs font-mono text-foreground flex-1 truncate">{{ call.tool_name }}</span>
                            <span v-if="call.duration_ms" class="text-[10px] text-muted-foreground">{{ formatDuration(call.duration_ms) }}</span>
                            <span class="text-[10px]" :class="{
                                'text-emerald-400': call.status === 'completed' || call.status === 'approved',
                                'text-amber-400': call.status === 'pending_approval',
                                'text-red-400': call.status === 'denied' || call.status === 'failed',
                                'text-muted-foreground': true,
                            }">{{ timeAgo(call.timestamp) }}</span>
                        </div>
                    </div>
                    <div v-else class="text-center py-6">
                        <Wrench class="h-8 w-8 text-muted-foreground/30 mx-auto mb-2" />
                        <div class="text-xs text-muted-foreground">No recent tool activity</div>
                    </div>
                </template>

                <!-- ═══════════ BREAK ROOM ═══════════ -->
                <template v-else-if="data.type === 'zone' && data.zoneId === 'breakRoom'">
                    <div class="rounded-lg bg-muted/30 p-3">
                        <div class="text-[10px] uppercase tracking-wider text-muted-foreground mb-2">Idle Agents</div>
                        <div v-if="state?.agents?.filter(a => a.status === 'idle').length" class="space-y-2">
                            <div
                                v-for="agent in state.agents.filter(a => a.status === 'idle')"
                                :key="agent.id"
                                class="flex items-center gap-2"
                            >
                                <Coffee class="h-3 w-3 text-amber-400" />
                                <span class="text-xs text-foreground">{{ agent.name }}</span>
                            </div>
                        </div>
                        <div v-else class="text-xs text-muted-foreground">All agents are busy</div>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div class="rounded-lg bg-muted/30 p-3 text-center">
                            <div class="text-xl font-bold text-foreground">{{ state?.agents?.length ?? 0 }}</div>
                            <div class="text-[10px] text-muted-foreground">Total Agents</div>
                        </div>
                        <div class="rounded-lg bg-muted/30 p-3 text-center">
                            <div class="text-xl font-bold text-blue-400">{{ state?.agents?.filter(a => a.status === 'idle').length ?? 0 }}</div>
                            <div class="text-[10px] text-muted-foreground">On Break</div>
                        </div>
                    </div>
                </template>

                <!-- ═══════════ ESCALATION ═══════════ -->
                <template v-else-if="data.type === 'zone' && data.zoneId === 'escalation'">
                    <div class="grid grid-cols-2 gap-2">
                        <div class="rounded-lg p-3 text-center" :class="(state?.escalations?.open_incidents ?? 0) > 0 ? 'bg-red-500/10' : 'bg-muted/30'">
                            <div class="text-xl font-bold" :class="(state?.escalations?.open_incidents ?? 0) > 0 ? 'text-red-400' : 'text-foreground'">
                                {{ state?.escalations?.open_incidents ?? 0 }}
                            </div>
                            <div class="text-[10px] text-muted-foreground">Open Incidents</div>
                        </div>
                        <div class="rounded-lg p-3 text-center" :class="(state?.escalations?.pending_org_escalations ?? 0) > 0 ? 'bg-amber-500/10' : 'bg-muted/30'">
                            <div class="text-xl font-bold" :class="(state?.escalations?.pending_org_escalations ?? 0) > 0 ? 'text-amber-400' : 'text-foreground'">
                                {{ state?.escalations?.pending_org_escalations ?? 0 }}
                            </div>
                            <div class="text-[10px] text-muted-foreground">Pending Escalations</div>
                        </div>
                    </div>

                    <div v-if="state?.escalations?.recent_items?.length" class="space-y-1.5">
                        <div class="text-[10px] uppercase tracking-wider text-muted-foreground">Recent</div>
                        <div
                            v-for="item in state.escalations.recent_items"
                            :key="item.id"
                            class="flex items-center gap-2 rounded-lg bg-muted/20 px-3 py-2"
                        >
                            <AlertTriangle class="h-3 w-3 shrink-0" :class="{
                                'text-amber-400': item.state === 'pending',
                                'text-emerald-400': item.state === 'approved',
                                'text-red-400': item.state === 'rejected' || item.state === 'timed_out',
                            }" />
                            <div class="min-w-0 flex-1">
                                <div class="text-xs text-foreground capitalize">{{ item.type }}</div>
                                <div v-if="item.agent" class="text-[10px] text-muted-foreground">→ {{ item.agent }}</div>
                            </div>
                            <span class="text-[10px] px-1.5 py-0.5 rounded capitalize" :class="{
                                'bg-amber-500/10 text-amber-400': item.state === 'pending',
                                'bg-emerald-500/10 text-emerald-400': item.state === 'approved',
                                'bg-red-500/10 text-red-400': item.state === 'rejected' || item.state === 'timed_out',
                            }">{{ item.state }}</span>
                        </div>
                    </div>
                    <div v-else class="text-center py-6">
                        <CheckCircle class="h-8 w-8 text-emerald-500/30 mx-auto mb-2" />
                        <div class="text-xs text-muted-foreground">No active escalations</div>
                    </div>
                </template>

                <!-- ═══════════ FALLBACK ═══════════ -->
                <template v-else>
                    <div class="text-center py-8">
                        <BarChart3 class="h-8 w-8 text-muted-foreground/30 mx-auto mb-2" />
                        <div class="text-sm text-muted-foreground">Click an agent or zone for details</div>
                    </div>
                </template>
            </div>

            <!-- Footer -->
            <div v-if="linkedRoute" class="border-t border-border/50 p-3">
                <Link
                    :href="route(linkedRoute)"
                    class="flex items-center justify-center gap-2 w-full rounded-lg bg-primary/10 px-4 py-2.5 text-sm font-medium text-primary hover:bg-primary/20 transition-colors"
                >
                    <ExternalLink class="h-3.5 w-3.5" />
                    Open Full View
                </Link>
            </div>
        </div>
    </Transition>
</template>
