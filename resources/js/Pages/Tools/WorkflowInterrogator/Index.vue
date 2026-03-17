<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import HelpHint from '@/Components/HelpHint.vue';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardDescription from '@/Components/ui/CardDescription.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Button from '@/Components/ui/Button.vue';
import Skeleton from '@/Components/ui/Skeleton.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Plus, RefreshCw, Waypoints, ArrowRight } from 'lucide-vue-next';
import axios from 'axios';
import { computed, onMounted, ref } from 'vue';
import { formatDateTime } from '@/Utils/formatDate';

const sessions = ref([]);
const loading = ref(false);
const error = ref('');

const load = async () => {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await axios.get('/agent/api/v1/workflow-interrogator/sessions');
        sessions.value = Array.isArray(data?.data) ? data.data : [];
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to load workflow interrogation sessions.';
    } finally {
        loading.value = false;
    }
};

const activeSessions = computed(() => sessions.value.filter((session) => !session?.deleted_at));

onMounted(load);
</script>

<template>
    <AppLayout title="Workflow Interrogator">
        <Head title="Workflow Interrogator" />

        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                        <Waypoints class="h-5 w-5 text-primary" />
                    </div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-base font-semibold text-foreground truncate">Workflow Interrogator</h2>
                        <HelpHint
                            ui-key="workflow-interrogator.index"
                            short-text="Interrogate operational workflows in iterative batches until ambiguity is exhausted."
                            learn-more-href="/docs/overview"
                        />
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Button variant="outline" size="sm" @click="load" :disabled="loading">
                        <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': loading }" />
                        Refresh
                    </Button>
                    <Link :href="route('tools.workflow-interrogator.create')">
                        <Button size="sm">
                            <Plus class="h-4 w-4" />
                            New Session
                        </Button>
                    </Link>
                </div>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-6xl space-y-4">
                <Card class="border-primary/20 bg-linear-to-br from-primary/[0.08] via-background to-background">
                    <CardHeader>
                        <CardTitle>Standalone workflow discovery</CardTitle>
                        <CardDescription>
                            This feature is separate from Requirements Discovery. It runs finite question batches, reassesses ambiguity after each round, and continues until the workflow is clear enough to plan without silent assumptions.
                        </CardDescription>
                    </CardHeader>
                </Card>

                <div v-if="error" class="rounded-md border border-destructive/50 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                    {{ error }}
                </div>

                <div v-if="loading" class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <Card v-for="index in 4" :key="index">
                        <CardContent class="space-y-3 pt-6">
                            <Skeleton class="h-5 w-1/2" />
                            <Skeleton class="h-4 w-full" />
                            <Skeleton class="h-4 w-4/5" />
                        </CardContent>
                    </Card>
                </div>

                <div v-else-if="activeSessions.length === 0" class="rounded-xl border border-dashed border-border bg-muted/20 p-8 text-center">
                    <h3 class="text-sm font-semibold text-foreground">No workflow interrogation sessions yet</h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Start with a company context and workflow brief, then drive iterative interrogation until ambiguity is materially exhausted.
                    </p>
                    <div class="mt-4">
                        <Link :href="route('tools.workflow-interrogator.create')">
                            <Button>
                                <Plus class="h-4 w-4" />
                                Create first session
                            </Button>
                        </Link>
                    </div>
                </div>

                <div v-else class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <Card v-for="session in activeSessions" :key="session.id" class="h-full">
                        <CardHeader class="space-y-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <CardTitle class="truncate">
                                        {{ session.name || session.workflow_title || `Workflow Session #${session.id}` }}
                                    </CardTitle>
                                    <CardDescription class="mt-1">
                                        {{ session.company_name }} · {{ session.runner_type }}{{ session.model ? `:${session.model}` : '' }} · round {{ session.current_round }}
                                    </CardDescription>
                                </div>
                                <span class="inline-flex shrink-0 rounded-full border border-border bg-muted px-2.5 py-1 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                    {{ session.status }}
                                </span>
                            </div>
                            <p class="text-sm text-muted-foreground line-clamp-3">
                                {{ session.workflow_brief || 'No workflow brief stored.' }}
                            </p>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <dl class="grid grid-cols-1 gap-2 text-sm text-muted-foreground sm:grid-cols-2">
                                <div>
                                    <dt class="font-medium text-foreground">Mode</dt>
                                    <dd>{{ session.interrogation_mode }}</dd>
                                </div>
                                <div>
                                    <dt class="font-medium text-foreground">Updated</dt>
                                    <dd>{{ formatDateTime(session.updated_at) }}</dd>
                                </div>
                                <div class="sm:col-span-2">
                                    <dt class="font-medium text-foreground">Project directory</dt>
                                    <dd class="truncate">{{ session.project_directory }}</dd>
                                </div>
                            </dl>

                            <div class="flex items-center justify-end">
                                <Link :href="route('tools.workflow-interrogator.wizard', session.id)">
                                    <Button size="sm">
                                        Open session
                                        <ArrowRight class="h-4 w-4" />
                                    </Button>
                                </Link>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
