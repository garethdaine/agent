<script setup>
import { onMounted, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardDescription from '@/Components/ui/CardDescription.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Table from '@/Components/ui/Table.vue';
import TableHeader from '@/Components/ui/TableHeader.vue';
import TableBody from '@/Components/ui/TableBody.vue';
import TableRow from '@/Components/ui/TableRow.vue';
import TableHead from '@/Components/ui/TableHead.vue';
import TableCell from '@/Components/ui/TableCell.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Skeleton from '@/Components/ui/Skeleton.vue';
import { ArrowLeft, Play, Pause, RefreshCw, Bot } from 'lucide-vue-next';
import HelpHint from '@/Components/HelpHint.vue';

const props = defineProps({
    ritualId: {
        type: String,
        required: true,
    },
});

const ritual = ref(null);
const loading = ref(true);
const error = ref('');

const loadRitual = async () => {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await axios.get(`/agent/api/v1/org/rituals/${props.ritualId}`);
        ritual.value = data.data;
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to load ritual details.';
    } finally {
        loading.value = false;
    }
};

const runNow = async () => {
    if (!ritual.value) {
        return;
    }

    try {
        await axios.post(`/agent/api/v1/org/rituals/${ritual.value.id}/run`);
        await loadRitual();
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to run ritual.';
    }
};

const pause = async () => {
    if (!ritual.value) {
        return;
    }

    try {
        await axios.post(`/agent/api/v1/org/rituals/${ritual.value.id}/pause`);
        await loadRitual();
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to pause ritual.';
    }
};

const resume = async () => {
    if (!ritual.value) {
        return;
    }

    try {
        await axios.post(`/agent/api/v1/org/rituals/${ritual.value.id}/resume`);
        await loadRitual();
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to resume ritual.';
    }
};

onMounted(loadRitual);
</script>

<template>
    <AppLayout title="Ritual Details">
        <Head title="Ritual Details" />

        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('org.rituals.index')">
                    <Button variant="ghost" size="sm">
                        <ArrowLeft class="h-4 w-4" />
                    </Button>
                </Link>
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                    <Bot class="h-5 w-5 text-primary" />
                </div>
                <div class="flex items-center gap-2">
                    <h2 class="text-base font-semibold text-foreground truncate">Ritual Details</h2>
                    <HelpHint
                        ui-key="org.rituals.detail"
                        short-text="Inspect ritual configuration and execution history."
                        learn-more-href="/docs/overview"
                    />
                </div>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="space-y-4">
                <p v-if="error" class="rounded-lg border border-destructive bg-destructive/10 px-4 py-3 text-sm text-destructive">
                    {{ error }}
                </p>

                <Card v-if="loading">
                    <CardContent class="space-y-3 py-8">
                        <Skeleton class="h-4 w-48" />
                        <Skeleton class="h-4 w-64" />
                        <Skeleton class="h-24 w-full" />
                    </CardContent>
                </Card>

                <template v-else-if="ritual">
                    <Card>
                        <CardHeader>
                            <CardTitle>{{ ritual.name }}</CardTitle>
                            <CardDescription>{{ ritual.description || 'No description provided.' }}</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <Badge variant="outline">Cron: {{ ritual.cron_expression }}</Badge>
                                <Badge variant="outline">Timezone: {{ ritual.timezone }}</Badge>
                                <Badge :variant="ritual.is_paused ? 'secondary' : 'default'">
                                    {{ ritual.is_paused ? 'Paused' : 'Active' }}
                                </Badge>
                                <Badge variant="outline">Notification: {{ ritual.notification_level }}</Badge>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <Button @click="runNow">
                                    <Play class="mr-2 h-4 w-4" />
                                    Run Now
                                </Button>
                                <Button v-if="!ritual.is_paused" variant="outline" @click="pause">
                                    <Pause class="mr-2 h-4 w-4" />
                                    Pause
                                </Button>
                                <Button v-else variant="outline" @click="resume">
                                    <RefreshCw class="mr-2 h-4 w-4" />
                                    Resume
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Phase Graph</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Phase ID</TableHead>
                                        <TableHead>Name</TableHead>
                                        <TableHead>Depends On</TableHead>
                                        <TableHead>Role</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="phase in (ritual.phase_graph ?? [])" :key="phase.id">
                                        <TableCell class="font-mono text-xs">{{ phase.id }}</TableCell>
                                        <TableCell>{{ phase.name }}</TableCell>
                                        <TableCell class="text-muted-foreground">
                                            {{ (phase.depends_on ?? []).join(', ') || 'None' }}
                                        </TableCell>
                                        <TableCell class="text-muted-foreground">
                                            {{ ritual.phase_role_mappings?.[phase.id] ?? '-' }}
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Run History</CardTitle>
                        </CardHeader>
                        <CardContent class="p-0">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Run ID</TableHead>
                                        <TableHead>State</TableHead>
                                        <TableHead>Started</TableHead>
                                        <TableHead>Completed</TableHead>
                                        <TableHead class="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow
                                        v-for="run in (ritual.runs ?? [])"
                                        :key="run.id"
                                        class="cursor-pointer"
                                        @click="router.visit(route('org.rituals.runs.show', { ritualId: ritualId, runId: run.id }))"
                                    >
                                        <TableCell class="font-mono text-xs">{{ run.id }}</TableCell>
                                        <TableCell>
                                            <Badge variant="outline">{{ run.state }}</Badge>
                                        </TableCell>
                                        <TableCell class="text-muted-foreground">
                                            {{ run.started_at ? new Date(run.started_at).toLocaleString() : '-' }}
                                        </TableCell>
                                        <TableCell class="text-muted-foreground">
                                            {{ run.completed_at ? new Date(run.completed_at).toLocaleString() : '-' }}
                                        </TableCell>
                                        <TableCell class="text-right">
                                            <Link
                                                :href="route('org.rituals.runs.show', { ritualId: ritualId, runId: run.id })"
                                                @click.stop
                                            >
                                                <Button variant="outline" size="sm">View</Button>
                                            </Link>
                                        </TableCell>
                                    </TableRow>
                                    <TableRow v-if="!ritual.runs || ritual.runs.length === 0">
                                        <TableCell colspan="5" class="pt-16 pb-12 text-center text-muted-foreground">
                                            No ritual runs yet.
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
