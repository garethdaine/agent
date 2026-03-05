<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardDescription from '@/Components/ui/CardDescription.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle, XCircle, StopCircle, Clock, Terminal, Play } from 'lucide-vue-next';
import HelpHint from '@/Components/HelpHint.vue';
import axios from 'axios';
import { ref } from 'vue';

const props = defineProps({
    session: Object,
    pendingApprovals: Array,
});

const processingApproval = ref(null);

const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleString();
};

const getStatusBadgeVariant = (status) => {
    const variants = {
        pending: 'secondary',
        active: 'default',
        completed: 'outline',
        failed: 'destructive',
        stopped: 'outline',
        running: 'default',
        pending_approval: 'secondary',
        approved: 'default',
        denied: 'destructive',
    };
    return variants[status] || 'outline';
};

const getModeBadgeVariant = (mode) => {
    const variants = {
        safe: 'secondary',
        standard: 'default',
        full: 'destructive',
    };
    return variants[mode] || 'outline';
};

const approveToolCall = async (toolCallId) => {
    processingApproval.value = toolCallId;
    try {
        await axios.post(`/agent/api/v1/chat/runtime/tool-calls/${toolCallId}/approve`);
        router.reload({ only: ['session', 'pendingApprovals'] });
    } catch (error) {
        console.error('Failed to approve tool call', error);
    } finally {
        processingApproval.value = null;
    }
};

const denyToolCall = async (toolCallId) => {
    processingApproval.value = toolCallId;
    try {
        await axios.post(`/agent/api/v1/chat/runtime/tool-calls/${toolCallId}/deny`);
        router.reload({ only: ['session', 'pendingApprovals'] });
    } catch (error) {
        console.error('Failed to deny tool call', error);
    } finally {
        processingApproval.value = null;
    }
};

const stopSession = async () => {
    try {
        await axios.post(`/agent/api/v1/chat/runtime/sessions/${props.session.id}/stop`);
        router.reload({ only: ['session'] });
    } catch (error) {
        console.error('Failed to stop session', error);
    }
};
</script>

<template>
    <AppLayout :title="session.title || 'Session Details'">
        <Head :title="session.title || 'Session Details'" />

        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <Link href="/messenger/runtime">
                        <Button variant="outline" size="sm">
                            <ArrowLeft class="h-4 w-4" />
                        </Button>
                    </Link>
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                        <Play class="h-5 w-5 text-primary" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-xl font-semibold leading-tight text-foreground">
                                {{ session.title || 'Session Details' }}
                            </h2>
                            <HelpHint
                                ui-key="sessions.detail"
                                short-text="Inspect session turns, tool calls, and approvals."
                                learn-more-href="/docs/overview"
                            />
                        </div>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Session ID: {{ session.id }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Badge :variant="getStatusBadgeVariant(session.status)">
                        {{ session.status }}
                    </Badge>
                    <Badge :variant="getModeBadgeVariant(session.mode)">
                        {{ session.mode }} mode
                    </Badge>
                    <Button
                        v-if="session.status === 'active'"
                        variant="destructive"
                        size="sm"
                        @click="stopSession"
                    >
                        <StopCircle class="h-4 w-4 mr-1" />
                        Stop Session
                    </Button>
                </div>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl space-y-6">
                <!-- Pending Approvals -->
                <Card v-if="pendingApprovals?.length" class="border-yellow-200 bg-yellow-50/50 dark:border-yellow-900 dark:bg-yellow-950/20">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-yellow-800 dark:text-yellow-200">
                            <Clock class="h-5 w-5" />
                            Pending Approvals
                        </CardTitle>
                        <CardDescription class="text-yellow-700 dark:text-yellow-300">
                            {{ pendingApprovals.length }} tool call(s) awaiting your approval
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-3">
                            <div
                                v-for="approval in pendingApprovals"
                                :key="approval.id"
                                class="flex items-center justify-between rounded-lg border border-yellow-200 bg-white p-3 dark:border-yellow-800 dark:bg-gray-900"
                            >
                                <div class="flex items-center gap-3">
                                    <Terminal class="h-5 w-5 text-muted-foreground" />
                                    <div>
                                        <div class="font-medium">{{ approval.tool_call?.tool_name || 'Unknown Tool' }}</div>
                                        <div class="text-sm text-muted-foreground">
                                            Expires: {{ formatDate(approval.expires_at) }}
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Button
                                        variant="default"
                                        size="sm"
                                        :disabled="processingApproval === approval.runtime_tool_call_id"
                                        @click="approveToolCall(approval.runtime_tool_call_id)"
                                    >
                                        <CheckCircle class="h-4 w-4 mr-1" />
                                        Approve
                                    </Button>
                                    <Button
                                        variant="destructive"
                                        size="sm"
                                        :disabled="processingApproval === approval.runtime_tool_call_id"
                                        @click="denyToolCall(approval.runtime_tool_call_id)"
                                    >
                                        <XCircle class="h-4 w-4 mr-1" />
                                        Deny
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Session Info -->
                <Card>
                    <CardHeader>
                        <CardTitle>Session Information</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                            <div>
                                <dt class="text-sm font-medium text-muted-foreground">Status</dt>
                                <dd class="mt-1">
                                    <Badge :variant="getStatusBadgeVariant(session.status)">
                                        {{ session.status }}
                                    </Badge>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-muted-foreground">Mode</dt>
                                <dd class="mt-1">
                                    <Badge :variant="getModeBadgeVariant(session.mode)">
                                        {{ session.mode }}
                                    </Badge>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-muted-foreground">Started</dt>
                                <dd class="mt-1 text-sm">{{ formatDate(session.started_at) }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-muted-foreground">Ended</dt>
                                <dd class="mt-1 text-sm">{{ formatDate(session.ended_at) }}</dd>
                            </div>
                            <div v-if="session.workspace_root" class="col-span-2 sm:col-span-4">
                                <dt class="text-sm font-medium text-muted-foreground">Workspace Root</dt>
                                <dd class="mt-1 font-mono text-sm text-muted-foreground">{{ session.workspace_root }}</dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                <!-- Turns -->
                <Card>
                    <CardHeader>
                        <CardTitle>Turns</CardTitle>
                        <CardDescription>
                            {{ session.turns?.length || 0 }} turn(s) in this session
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="session.turns?.length" class="space-y-4">
                            <div
                                v-for="turn in session.turns"
                                :key="turn.id"
                                class="rounded-lg border p-4"
                            >
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium">Turn #{{ turn.sequence }}</span>
                                        <Badge :variant="getStatusBadgeVariant(turn.status)">
                                            {{ turn.status }}
                                        </Badge>
                                    </div>
                                </div>

                                <div v-if="turn.summary" class="mb-3 text-sm text-muted-foreground">
                                    {{ turn.summary }}
                                </div>

                                <div v-if="turn.tool_calls?.length" class="space-y-2">
                                    <div class="text-sm font-medium text-muted-foreground">Tool Calls:</div>
                                    <div
                                        v-for="toolCall in turn.tool_calls"
                                        :key="toolCall.id"
                                        class="ml-4 flex items-center gap-2 text-sm"
                                    >
                                        <Terminal class="h-4 w-4 text-muted-foreground" />
                                        <span class="font-mono">{{ toolCall.tool_name }}</span>
                                        <Badge :variant="getStatusBadgeVariant(toolCall.status)" class="text-xs">
                                            {{ toolCall.status }}
                                        </Badge>
                                        <span v-if="toolCall.duration_ms" class="text-muted-foreground">
                                            ({{ toolCall.duration_ms }}ms)
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center text-muted-foreground py-8">
                            No turns recorded yet.
                        </div>
                    </CardContent>
                </Card>

                <!-- Artifacts -->
                <Card v-if="session.artifacts?.length">
                    <CardHeader>
                        <CardTitle>Artifacts</CardTitle>
                        <CardDescription>
                            {{ session.artifacts.length }} artifact(s) generated
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-2">
                            <div
                                v-for="artifact in session.artifacts"
                                :key="artifact.id"
                                class="flex items-center gap-2 rounded border p-2"
                            >
                                <Badge variant="outline">{{ artifact.type }}</Badge>
                                <span class="font-mono text-sm text-muted-foreground">{{ artifact.path }}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
