<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import HelpHint from '@/Components/HelpHint.vue';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardDescription from '@/Components/ui/CardDescription.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Button from '@/Components/ui/Button.vue';
import Badge from '@/Components/ui/Badge.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, ScrollText } from 'lucide-vue-next';
import axios from 'axios';
import { ref, onMounted } from 'vue';

const loading = ref(false);
const error = ref('');
const entries = ref([]);
const meta = ref(null);
const filter = ref('');
const page = ref(1);

const PRESETS = [
    { label: 'All', value: '' },
    { label: 'Runtime tool calls', value: 'runtime.tool_call' },
    { label: 'Approvals', value: 'runtime.approval' },
    { label: 'Sessions', value: 'runtime.session' },
    { label: 'Jobs', value: 'agent_job.' },
    { label: 'Messenger', value: 'messenger.' },
    { label: 'Memory', value: 'memory.' },
];

const load = async () => {
    loading.value = true;
    error.value = '';
    try {
        const params = { per_page: 50, page: page.value };
        if (filter.value) params.action_prefix = filter.value;
        const { data } = await axios.get('/agent/api/v1/audit-log', { params });
        entries.value = data.data;
        meta.value = data.meta;
    } catch (e) {
        error.value = e?.response?.data?.message ?? 'Failed to load audit log.';
    } finally {
        loading.value = false;
    }
};

const applyFilter = (preset) => {
    filter.value = preset;
    page.value = 1;
    load();
};

const goPage = (p) => {
    page.value = p;
    load();
};

const outcomeVariant = (outcome) => {
    return outcome === 'success' ? 'default' : 'destructive';
};

const fmtDate = (iso) => {
    if (!iso) return '—';
    return new Date(iso).toLocaleString();
};

onMounted(load);
</script>

<template>
    <AppLayout title="Audit Log">
        <Head title="Audit Log" />

        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                        <ScrollText class="h-5 w-5 text-primary" />
                    </div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-xl font-semibold leading-tight text-foreground">
                            Audit Log
                        </h2>
                        <HelpHint
                            ui-key="audit.log"
                            short-text="Immutable audit trail: tool calls, approvals, job mutations, messenger events."
                            learn-more-href="/docs/overview"
                        />
                    </div>
                </div>
                <Link :href="route('tools.index')">
                    <Button variant="outline" size="sm">
                        <ArrowLeft class="h-4 w-4" />
                        Back
                    </Button>
                </Link>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-6xl space-y-4">
                <div v-if="error" class="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                    {{ error }}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Events</CardTitle>
                        <CardDescription>
                            Every runtime tool call, approval decision, job mutation, and system event is logged here.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="flex flex-wrap gap-2">
                            <Button
                                v-for="p in PRESETS"
                                :key="p.value"
                                size="sm"
                                :variant="filter === p.value ? 'default' : 'outline'"
                                @click="applyFilter(p.value)"
                            >
                                {{ p.label }}
                            </Button>
                        </div>

                        <div v-if="entries.length" class="overflow-x-auto rounded-md border">
                            <table class="w-full text-sm">
                                <thead class="border-b bg-muted/50">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium">Time</th>
                                        <th class="px-3 py-2 text-left font-medium">Action</th>
                                        <th class="px-3 py-2 text-left font-medium">Actor</th>
                                        <th class="px-3 py-2 text-left font-medium">Target</th>
                                        <th class="px-3 py-2 text-left font-medium">Outcome</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="e in entries"
                                        :key="e.id"
                                        class="border-b last:border-0"
                                    >
                                        <td class="px-3 py-2 text-muted-foreground whitespace-nowrap text-xs">
                                            {{ fmtDate(e.created_at) }}
                                        </td>
                                        <td class="px-3 py-2 font-mono text-xs">
                                            {{ e.action }}
                                        </td>
                                        <td class="px-3 py-2 text-muted-foreground text-xs">
                                            {{ e.actor_type }}{{ e.actor_id ? ` #${e.actor_id}` : '' }}
                                        </td>
                                        <td class="px-3 py-2 text-muted-foreground text-xs">
                                            {{ e.target_type }} #{{ e.target_id }}
                                        </td>
                                        <td class="px-3 py-2">
                                            <Badge :variant="outcomeVariant(e.outcome)" class="text-xs">
                                                {{ e.outcome }}
                                            </Badge>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <p v-else-if="!loading" class="text-sm text-muted-foreground">
                            No audit log entries{{ filter ? ' for this filter' : '' }}.
                        </p>

                        <p v-if="loading" class="text-sm text-muted-foreground">Loading...</p>

                        <div v-if="meta && meta.last_page > 1" class="flex items-center gap-2">
                            <Button
                                size="sm"
                                variant="outline"
                                :disabled="page <= 1"
                                @click="goPage(page - 1)"
                            >
                                Previous
                            </Button>
                            <span class="text-xs text-muted-foreground">
                                Page {{ meta.current_page }} of {{ meta.last_page }} ({{ meta.total }} total)
                            </span>
                            <Button
                                size="sm"
                                variant="outline"
                                :disabled="page >= meta.last_page"
                                @click="goPage(page + 1)"
                            >
                                Next
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
