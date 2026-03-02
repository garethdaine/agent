<script setup>
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router, Link } from '@inertiajs/vue3';
import axios from 'axios';
import Card from '@/Components/ui/Card.vue';
import CardHeader from '@/Components/ui/CardHeader.vue';
import CardTitle from '@/Components/ui/CardTitle.vue';
import CardDescription from '@/Components/ui/CardDescription.vue';
import CardContent from '@/Components/ui/CardContent.vue';
import CardFooter from '@/Components/ui/CardFooter.vue';
import Input from '@/Components/ui/Input.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import Button from '@/Components/ui/Button.vue';
import Spinner from '@/Components/ui/Spinner.vue';
import { Plus, Trash2, AlertCircle, AlertTriangle, ArrowLeft } from 'lucide-vue-next';

const mode = ref('linear');
const name = ref('');
const description = ref('');
const loading = ref(false);
const validating = ref(false);
const error = ref('');
const validationErrors = ref([]);
const validationWarnings = ref([]);

// Linear chain mode
const linearTasks = ref([
    { name: '', contract: '{\n  "required_capability": "code_execution",\n  "prompt": ""\n}' }
]);

// DAG JSON mode
const dagJson = ref('{\n  "tasks": [\n    {\n      "name": "task_1",\n      "contract": {\n        "required_capability": "code_execution",\n        "prompt": ""\n      },\n      "depends_on": []\n    }\n  ]\n}');

const addLinearTask = () => {
    linearTasks.value.push({ name: '', contract: '{\n  "required_capability": "code_execution",\n  "prompt": ""\n}' });
};

const removeLinearTask = (index) => {
    if (linearTasks.value.length > 1) {
        linearTasks.value.splice(index, 1);
    }
};

const buildPayload = () => {
    if (mode.value === 'linear') {
        return {
            name: name.value,
            description: description.value,
            tasks: linearTasks.value.map((t, i) => ({
                name: t.name || `task_${i + 1}`,
                contract: JSON.parse(t.contract),
            })),
        };
    } else {
        const parsed = JSON.parse(dagJson.value);
        return {
            name: name.value,
            description: description.value,
            ...parsed,
        };
    }
};

const validate = async () => {
    validating.value = true;
    validationErrors.value = [];
    validationWarnings.value = [];
    error.value = '';

    try {
        const payload = buildPayload();
        const { data } = await axios.post('/agent/api/v1/delegation/graphs/validate', payload);
        validationWarnings.value = data.warnings || [];
        if (data.valid) {
            error.value = '';
        }
    } catch (e) {
        if (e?.response?.data?.error?.details) {
            validationErrors.value = Object.values(e.response.data.error.details).flat();
        } else {
            error.value = e?.response?.data?.error?.message ?? 'Validation failed.';
        }
    } finally {
        validating.value = false;
    }
};

const create = async () => {
    loading.value = true;
    error.value = '';
    validationErrors.value = [];

    try {
        const payload = buildPayload();
        const { data } = await axios.post('/agent/api/v1/delegation/graphs', payload);
        router.visit(route('agent.delegation.show', data.data.id));
    } catch (e) {
        if (e?.response?.data?.error?.details) {
            validationErrors.value = Object.values(e.response.data.error.details).flat();
        } else {
            error.value = e?.response?.data?.error?.message ?? 'Failed to create graph.';
        }
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <AppLayout title="Create Delegation Graph">
        <Head title="Create Delegation Graph" />

        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('agent.delegation.index')">
                    <Button variant="ghost" size="icon">
                        <ArrowLeft class="h-4 w-4" />
                    </Button>
                </Link>
                <h2 class="text-xl font-semibold leading-tight text-foreground">Create Delegation Graph</h2>
            </div>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-4xl space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Graph Details</CardTitle>
                        <CardDescription>Define your delegation graph name and tasks</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-foreground">Name</label>
                            <Input v-model="name" type="text" placeholder="My Delegation Graph" />
                        </div>

                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-foreground">Description (optional)</label>
                            <Textarea v-model="description" :rows="2" placeholder="Description of what this graph does..." />
                        </div>

                        <div class="flex gap-4 border-b border-border">
                            <button
                                :class="mode === 'linear' ? 'border-b-2 border-primary text-primary' : 'text-muted-foreground hover:text-foreground'"
                                class="px-4 py-2 text-sm font-medium transition-colors"
                                @click="mode = 'linear'"
                            >
                                Linear Chain
                            </button>
                            <button
                                :class="mode === 'dag' ? 'border-b-2 border-primary text-primary' : 'text-muted-foreground hover:text-foreground'"
                                class="px-4 py-2 text-sm font-medium transition-colors"
                                @click="mode = 'dag'"
                            >
                                DAG JSON
                            </button>
                        </div>

                        <div v-if="mode === 'linear'" class="space-y-4">
                            <p class="text-sm text-muted-foreground">Tasks will be executed sequentially in the order below.</p>

                            <div v-for="(task, index) in linearTasks" :key="index" class="rounded-lg border border-border p-4">
                                <div class="flex items-center justify-between mb-3">
                                    <span class="text-sm font-medium text-foreground">Task {{ index + 1 }}</span>
                                    <Button
                                        v-if="linearTasks.length > 1"
                                        variant="ghost"
                                        size="sm"
                                        class="text-destructive hover:text-destructive"
                                        @click="removeLinearTask(index)"
                                    >
                                        <Trash2 class="mr-1 h-3 w-3" />
                                        Remove
                                    </Button>
                                </div>
                                <div class="space-y-3">
                                    <Input v-model="task.name" type="text" :placeholder="`task_${index + 1}`" />
                                    <textarea
                                        v-model="task.contract"
                                        rows="6"
                                        class="block w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-xs text-foreground placeholder:text-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/20"
                                    />
                                </div>
                            </div>

                            <Button variant="ghost" size="sm" @click="addLinearTask">
                                <Plus class="mr-1 h-4 w-4" />
                                Add Task
                            </Button>
                        </div>

                        <div v-else class="space-y-2">
                            <p class="text-sm text-muted-foreground">Define tasks as a directed acyclic graph in JSON format.</p>
                            <textarea
                                v-model="dagJson"
                                rows="20"
                                class="block w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-xs text-foreground placeholder:text-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/20"
                            />
                        </div>
                    </CardContent>
                </Card>

                <div v-if="validationErrors.length > 0" class="rounded-lg border border-destructive bg-destructive/10 p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <AlertCircle class="h-4 w-4 text-destructive" />
                        <h4 class="text-sm font-medium text-destructive">Validation Errors</h4>
                    </div>
                    <ul class="mt-2 list-disc list-inside text-sm text-destructive/80">
                        <li v-for="(err, i) in validationErrors" :key="i">{{ err }}</li>
                    </ul>
                </div>

                <div v-if="validationWarnings.length > 0" class="rounded-lg border border-warning bg-warning/10 p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <AlertTriangle class="h-4 w-4 text-warning" />
                        <h4 class="text-sm font-medium text-warning">Warnings</h4>
                    </div>
                    <ul class="mt-2 list-disc list-inside text-sm text-warning/80">
                        <li v-for="(warn, i) in validationWarnings" :key="i">{{ warn }}</li>
                    </ul>
                </div>

                <p v-if="error" class="rounded-lg border border-destructive bg-destructive/10 px-4 py-3 text-sm text-destructive">
                    {{ error }}
                </p>

                <div class="flex items-center justify-end gap-3">
                    <Button
                        variant="outline"
                        :disabled="validating"
                        @click="validate"
                    >
                        <Spinner v-if="validating" size="sm" class="mr-2" />
                        {{ validating ? 'Validating...' : 'Validate' }}
                    </Button>
                    <Button
                        :disabled="loading || !name"
                        @click="create"
                    >
                        <Spinner v-if="loading" size="sm" class="mr-2" />
                        {{ loading ? 'Creating...' : 'Create Graph' }}
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
