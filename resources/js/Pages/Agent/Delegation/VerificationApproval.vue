<script setup>
import { ref, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import Button from '@/Components/ui/Button.vue';
import Skeleton from '@/Components/ui/Skeleton.vue';
import Spinner from '@/Components/ui/Spinner.vue';
import { ArrowLeft, Check, X, GitBranch } from 'lucide-vue-next';
import HelpHint from '@/Components/HelpHint.vue';

const props = defineProps({
    graphId: {
        type: Number,
        required: true,
    },
    taskId: {
        type: Number,
        required: true,
    },
});

const task = ref(null);
const loading = ref(true);
const submitting = ref(false);
const error = ref('');

const verdict = ref('passed');
const notes = ref('');

const load = async () => {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await axios.get(`/agent/api/v1/delegation/graphs/${props.graphId}/tasks/${props.taskId}`);
        task.value = data.data;
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to load task.';
    } finally {
        loading.value = false;
    }
};

const submit = async () => {
    submitting.value = true;
    error.value = '';

    try {
        await axios.post(`/agent/api/v1/delegation/graphs/${props.graphId}/tasks/${props.taskId}/verification/resolve`, {
            verdict: verdict.value,
            evidence_json: {
                notes: notes.value,
                reviewed_at: new Date().toISOString(),
            },
        });
        router.visit(route('agent.delegation.task', { graphId: props.graphId, taskId: props.taskId }));
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to submit verification.';
    } finally {
        submitting.value = false;
    }
};

const latestAttempt = () => {
    if (!task.value?.attempts || task.value.attempts.length === 0) return null;
    return task.value.attempts[task.value.attempts.length - 1];
};

onMounted(load);
</script>

<template>
    <AppLayout title="Verification Approval">
        <Head title="Verification Approval" />

        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('agent.delegation.task', { graphId: graphId, taskId: taskId })">
                    <Button variant="ghost" size="icon">
                        <ArrowLeft class="h-4 w-4" />
                    </Button>
                </Link>
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                    <GitBranch class="h-5 w-5 text-primary" />
                </div>
                <div>
                    <div class="text-sm text-muted-foreground mb-1">
                        <Link :href="route('agent.delegation.task', { graphId: graphId, taskId: taskId })" class="hover:text-foreground transition-colors">
                            Back to Task
                        </Link>
                    </div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-xl font-semibold leading-tight text-foreground">Human Verification Approval</h2>
                        <HelpHint
                            ui-key="delegation.verification"
                            short-text="Review and approve delegation verification results."
                            learn-more-href="/docs/overview"
                        />
                    </div>
                </div>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-4xl space-y-6">
                <p v-if="error" class="rounded-lg border border-destructive bg-destructive/10 px-4 py-3 text-sm text-destructive">
                    {{ error }}
                </p>

                <div v-if="loading" class="flex flex-col items-center justify-center py-12 gap-4">
                    <Skeleton class="h-4 w-48" />
                    <Skeleton class="h-4 w-32" />
                </div>

                <template v-else-if="task">
                    <Card>
                        <CardHeader>
                            <CardTitle>Task Context</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div>
                                <span class="text-sm font-medium text-muted-foreground">Task Name</span>
                                <p class="mt-1 text-foreground">{{ task.name }}</p>
                            </div>
                            <div>
                                <span class="text-sm font-medium text-muted-foreground">Contract</span>
                                <pre class="mt-2 overflow-x-auto rounded-lg bg-muted p-4 text-xs text-foreground font-mono">{{ JSON.stringify(task.contract_json, null, 2) }}</pre>
                            </div>
                        </CardContent>
                    </Card>

                    <Card v-if="latestAttempt()">
                        <CardHeader>
                            <CardTitle>Latest Attempt Output</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div class="flex items-center gap-4 text-sm text-muted-foreground">
                                <span>Attempt #{{ latestAttempt().attempt_number }}</span>
                                <span>Status: {{ latestAttempt().status }}</span>
                                <span v-if="latestAttempt().duration_ms">Duration: {{ latestAttempt().duration_ms }}ms</span>
                            </div>
                            <div v-if="latestAttempt().metadata_json" class="mt-2">
                                <pre class="overflow-x-auto rounded-lg bg-muted p-4 text-xs text-foreground font-mono">{{ JSON.stringify(latestAttempt().metadata_json, null, 2) }}</pre>
                            </div>
                            <p v-else class="text-sm text-muted-foreground">No output metadata available.</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Your Decision</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <div>
                                <label class="block text-sm font-medium text-foreground mb-3">Verdict</label>
                                <div class="flex gap-4">
                                    <label
                                        class="flex items-center gap-2 cursor-pointer rounded-lg border px-4 py-3 transition-colors"
                                        :class="verdict === 'passed' ? 'border-primary bg-primary/10 text-primary' : 'border-border text-muted-foreground hover:bg-muted'"
                                    >
                                        <input v-model="verdict" type="radio" value="passed" class="sr-only" />
                                        <Check class="h-4 w-4" />
                                        <span class="text-sm font-medium">Approve</span>
                                    </label>
                                    <label
                                        class="flex items-center gap-2 cursor-pointer rounded-lg border px-4 py-3 transition-colors"
                                        :class="verdict === 'failed' ? 'border-destructive bg-destructive/10 text-destructive' : 'border-border text-muted-foreground hover:bg-muted'"
                                    >
                                        <input v-model="verdict" type="radio" value="failed" class="sr-only" />
                                        <X class="h-4 w-4" />
                                        <span class="text-sm font-medium">Reject</span>
                                    </label>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-foreground mb-2">Notes (optional)</label>
                                <Textarea v-model="notes" :rows="4" placeholder="Add any notes about your decision..." />
                            </div>
                        </CardContent>
                    </Card>

                    <div class="flex items-center justify-end gap-3">
                        <Link :href="route('agent.delegation.task', { graphId: graphId, taskId: taskId })">
                            <Button variant="outline">Cancel</Button>
                        </Link>
                        <Button
                            :variant="verdict === 'passed' ? 'default' : 'destructive'"
                            :disabled="submitting"
                            @click="submit"
                        >
                            <Spinner v-if="submitting" size="sm" class="mr-2" />
                            <Check v-else-if="verdict === 'passed'" class="mr-2 h-4 w-4" />
                            <X v-else class="mr-2 h-4 w-4" />
                            {{ submitting ? 'Submitting...' : (verdict === 'passed' ? 'Approve' : 'Reject') }}
                        </Button>
                    </div>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
