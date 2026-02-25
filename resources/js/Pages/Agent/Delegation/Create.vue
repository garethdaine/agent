<script setup>
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';

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
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Create Delegation Graph</h2>
        </template>

        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-4xl space-y-6">
                <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
                            <input v-model="name" type="text" class="mt-1 block w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" placeholder="My Delegation Graph" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description (optional)</label>
                            <textarea v-model="description" rows="2" class="mt-1 block w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" placeholder="Description of what this graph does..." />
                        </div>

                        <div class="flex gap-4 border-b border-gray-200 dark:border-gray-700">
                            <button
                                :class="mode === 'linear' ? 'border-b-2 border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'text-gray-500 dark:text-gray-400'"
                                class="px-4 py-2 text-sm font-medium"
                                @click="mode = 'linear'"
                            >
                                Linear Chain
                            </button>
                            <button
                                :class="mode === 'dag' ? 'border-b-2 border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'text-gray-500 dark:text-gray-400'"
                                class="px-4 py-2 text-sm font-medium"
                                @click="mode = 'dag'"
                            >
                                DAG JSON
                            </button>
                        </div>

                        <div v-if="mode === 'linear'" class="space-y-4">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Tasks will be executed sequentially in the order below.</p>

                            <div v-for="(task, index) in linearTasks" :key="index" class="rounded-md border border-gray-200 p-4 dark:border-gray-700">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Task {{ index + 1 }}</span>
                                    <button v-if="linearTasks.length > 1" class="text-sm text-red-600 hover:text-red-800 dark:text-red-400" @click="removeLinearTask(index)">Remove</button>
                                </div>
                                <div class="space-y-2">
                                    <input v-model="task.name" type="text" class="block w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" :placeholder="`task_${index + 1}`" />
                                    <textarea v-model="task.contract" rows="6" class="block w-full rounded-md border-gray-300 font-mono text-xs dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" />
                                </div>
                            </div>

                            <button class="text-sm text-indigo-600 hover:text-indigo-800 dark:text-indigo-400" @click="addLinearTask">+ Add Task</button>
                        </div>

                        <div v-else class="space-y-2">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Define tasks as a directed acyclic graph in JSON format.</p>
                            <textarea v-model="dagJson" rows="20" class="block w-full rounded-md border-gray-300 font-mono text-xs dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" />
                        </div>
                    </div>
                </div>

                <div v-if="validationErrors.length > 0" class="rounded-md border border-red-300 bg-red-50 p-4 dark:border-red-700 dark:bg-red-900/20">
                    <h4 class="text-sm font-medium text-red-800 dark:text-red-300">Validation Errors</h4>
                    <ul class="mt-2 list-disc list-inside text-sm text-red-700 dark:text-red-400">
                        <li v-for="(err, i) in validationErrors" :key="i">{{ err }}</li>
                    </ul>
                </div>

                <div v-if="validationWarnings.length > 0" class="rounded-md border border-yellow-300 bg-yellow-50 p-4 dark:border-yellow-700 dark:bg-yellow-900/20">
                    <h4 class="text-sm font-medium text-yellow-800 dark:text-yellow-300">Warnings</h4>
                    <ul class="mt-2 list-disc list-inside text-sm text-yellow-700 dark:text-yellow-400">
                        <li v-for="(warn, i) in validationWarnings" :key="i">{{ warn }}</li>
                    </ul>
                </div>

                <p v-if="error" class="rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700">
                    {{ error }}
                </p>

                <div class="flex items-center justify-end gap-3">
                    <button
                        class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                        :disabled="validating"
                        @click="validate"
                    >
                        {{ validating ? 'Validating...' : 'Validate' }}
                    </button>
                    <button
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50"
                        :disabled="loading || !name"
                        @click="create"
                    >
                        {{ loading ? 'Creating...' : 'Create Graph' }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
