<script setup>
import { markRaw, ref, computed, onMounted, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import Button from '@/Components/ui/Button.vue';
import { GraphCanvas } from '@/Components/GraphCanvas';
import DelegationTaskNode from '@/Components/Delegation/DelegationTaskNode.vue';
import TaskConfigPanel from '@/Components/Delegation/TaskConfigPanel.vue';
import DelegateeProfileCreateModal from '@/Components/Delegation/DelegateeProfileCreateModal.vue';
import { ArrowLeft, Plus, Save, Play, GitBranch, CheckCircle } from 'lucide-vue-next';
import HelpHint from '@/Components/HelpHint.vue';

const nodeTypes = { delegationTask: markRaw(DelegationTaskNode) };

const props = defineProps({
    graphId: { type: Number, default: null },
});

const name = ref('');
const description = ref('');
const nodes = ref([]);
const edges = ref([]);
const delegateeProfiles = ref([]);
const capabilities = ref([]);
const loading = ref(false);
const saving = ref(false);
const validating = ref(false);
const error = ref('');
const validationError = ref('');
const selectedNodeId = ref(null);
const showCreateProfileModal = ref(false);

const isEditing = computed(() => props.graphId != null);

const loadDelegatees = async () => {
    try {
        const { data } = await axios.get('/agent/api/v1/delegation/delegatee-profiles');
        delegateeProfiles.value = data.data ?? [];
    } catch {
        delegateeProfiles.value = [];
    }
};

const loadCapabilities = async () => {
    try {
        const { data } = await axios.get('/agent/api/v1/delegation/capabilities');
        capabilities.value = data.data ?? [];
    } catch {
        capabilities.value = [];
    }
};

const loadGraph = async () => {
    if (!props.graphId) return;
    loading.value = true;
    error.value = '';
    try {
        const { data } = await axios.get(`/agent/api/v1/delegation/graphs/${props.graphId}`);
        const g = data.data;
        if (g.status !== 'draft') {
            router.visit(route('agent.delegation.show', props.graphId));
            return;
        }
        name.value = g.name ?? '';
        description.value = g.description ?? '';
        const tasks = g.tasks ?? [];

        // Build a delegatee name lookup
        const delegateeMap = new Map();
        delegateeProfiles.value.forEach((p) => delegateeMap.set(p.id, p.name));

        nodes.value = tasks.map((t, i) => ({
            id: String(t.id),
            type: 'delegationTask',
            position: { x: 100 + i * 220, y: 100 },
            data: {
                label: t.name,
                status: t.status,
                capability: t.contract_json?.required_capability,
                contract_json: t.contract_json,
                assigned_delegatee_profile_id: t.assigned_profile_id,
                delegateeProfileName: delegateeMap.get(t.assigned_profile_id) ?? null,
                onDeleteNode: deleteTask,
                ...t,
            },
        }));
        const edgeList = [];
        tasks.forEach((t) => {
            (t.depends_on_task_ids ?? []).forEach((depId) => {
                edgeList.push({
                    id: `e${depId}-${t.id}`,
                    source: String(depId),
                    target: String(t.id),
                });
            });
        });
        edges.value = edgeList;
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to load graph.';
    } finally {
        loading.value = false;
    }
};

onMounted(async () => {
    await Promise.all([loadDelegatees(), loadCapabilities()]);
    if (props.graphId) await loadGraph();
});

watch(() => props.graphId, () => {
    if (props.graphId) loadGraph();
}, { immediate: false });

let taskCounter = 0;
const addTask = () => {
    taskCounter += 1;
    const id = `task-${Date.now()}-${taskCounter}`;
    nodes.value = [
        ...nodes.value,
        {
            id,
            type: 'delegationTask',
            position: { x: 100 + nodes.value.length * 220, y: 100 },
            data: {
                label: `Task ${nodes.value.length + 1}`,
                status: 'pending',
                capability: '',
                onDeleteNode: deleteTask,
            },
        },
    ];
};

const deleteTask = (nodeId) => {
    nodes.value = nodes.value.filter((n) => n.id !== nodeId);
    edges.value = edges.value.filter((e) => e.source !== nodeId && e.target !== nodeId);
    if (selectedNodeId.value === nodeId) {
        selectedNodeId.value = null;
    }
};

const onNodesChange = ({ nodes: next }) => {
    nodes.value = next;
};

const onEdgesChange = ({ edges: next }) => {
    edges.value = next;
};

function wouldCreateCycle(sourceId, targetId, currentEdges) {
    const outgoers = new Map();
    currentEdges.forEach((e) => {
        if (!outgoers.has(e.source)) outgoers.set(e.source, []);
        outgoers.get(e.source).push(e.target);
    });
    if (!outgoers.has(sourceId)) outgoers.set(sourceId, []);
    outgoers.get(sourceId).push(targetId);
    const visited = new Set();
    const queue = [targetId];
    while (queue.length > 0) {
        const u = queue.shift();
        if (u === sourceId) return true;
        if (visited.has(u)) continue;
        visited.add(u);
        (outgoers.get(u) ?? []).forEach((v) => queue.push(v));
    }
    return false;
}

const onConnect = (connection) => {
    const newEdge = { source: connection.source, target: connection.target };
    const edgesWithoutNew = edges.value.filter(
        (e) => !(e.source === newEdge.source && e.target === newEdge.target)
    );
    if (wouldCreateCycle(newEdge.source, newEdge.target, edgesWithoutNew)) {
        edges.value = edgesWithoutNew;
        validationError.value = 'This connection would create a cycle. Delegation graphs must be a DAG.';
    } else {
        validationError.value = '';
    }
};

const selectedNode = computed(() => {
    if (!selectedNodeId.value) return null;
    return nodes.value.find((n) => n.id === selectedNodeId.value) ?? null;
});

const onNodeClick = (node) => {
    selectedNodeId.value = node?.id ?? null;
};

const onTaskConfigUpdate = (data) => {
    const id = selectedNodeId.value;
    if (!id) return;

    // Resolve delegatee name for node info popover
    const delegateeId = data.assigned_delegatee_profile_id;
    const delegateeProfile = delegateeId
        ? delegateeProfiles.value.find((p) => p.id === delegateeId)
        : null;

    nodes.value = nodes.value.map((n) =>
        n.id === id ? { ...n, data: { ...n.data, ...data, delegateeProfileName: delegateeProfile?.name ?? null, onDeleteNode: deleteTask } } : n
    );
};

const saveDraft = async () => {
    saving.value = true;
    error.value = '';
    validationError.value = '';
    try {
        const taskNames = new Map();
        nodes.value.forEach((n, i) => {
            taskNames.set(n.id, n.data?.label ?? n.data?.name ?? `Task ${i + 1}`);
        });
        const tasks = nodes.value.map((n, i) => {
            const taskName = n.data?.label ?? n.data?.name ?? `Task ${i + 1}`;
            const dependsOn = edges.value
                .filter((e) => e.target === n.id)
                .map((e) => taskNames.get(e.source) ?? e.source);
            return {
                name: taskName,
                contract: n.data?.contract_json ?? {
                    required_capability: n.data?.capability ?? 'code_generation',
                    prompt: '',
                },
                depends_on: dependsOn,
            };
        });
        const payload = {
            name: name.value || 'Untitled Graph',
            description: description.value || null,
            format: 'dag',
            tasks,
        };
        if (props.graphId) {
            await axios.put(`/agent/api/v1/delegation/graphs/${props.graphId}`, {
                name: payload.name,
                description: payload.description,
            });
        } else {
            const { data } = await axios.post('/agent/api/v1/delegation/graphs', payload);
            router.visit(route('agent.delegation.graphs.builder.edit', data.data.id));
        }
    } catch (e) {
        if (e?.response?.data?.error?.details) {
            validationError.value = Object.values(e.response.data.error.details).flat().join(' ');
        } else {
            error.value = e?.response?.data?.error?.message ?? 'Failed to save.';
        }
    } finally {
        saving.value = false;
    }
};

const validateGraph = async () => {
    if (!props.graphId) return;
    validating.value = true;
    error.value = '';
    validationError.value = '';
    try {
        const { data } = await axios.post(`/agent/api/v1/delegation/graphs/${props.graphId}/validate`);
        if (data.data?.valid) {
            validationError.value = '';
        } else {
            validationError.value = (data.data?.errors ?? []).join(' ') || 'Validation failed.';
        }
    } catch (e) {
        validationError.value = e?.response?.data?.error?.message ?? 'Validation request failed.';
    } finally {
        validating.value = false;
    }
};

const startGraph = () => {
    if (!props.graphId) return;
    router.visit(route('agent.delegation.show', props.graphId), {
        method: 'get',
        onBefore: () => {
            return axios.post(`/agent/api/v1/delegation/graphs/${props.graphId}/start`).then(() => true).catch(() => false);
        },
    });
};

const onProfileCreated = (profile) => {
    showCreateProfileModal.value = false;
    delegateeProfiles.value = [...delegateeProfiles.value, profile];
};

// --- Autosave: debounce save when graph changes (edit mode only) ---
let autosaveTimer = null;
const scheduleAutosave = () => {
    if (!isEditing.value) return;
    if (autosaveTimer) clearTimeout(autosaveTimer);
    autosaveTimer = setTimeout(() => saveDraft(), 800);
};

watch(
    () => nodes.value.map((n) => {
        const d = n.data ?? {};
        return `${n.id}:${d.label}:${d.capability}:${d.assigned_delegatee_profile_id}`;
    }).join('|'),
    (newVal, oldVal) => {
        if (oldVal && newVal !== oldVal) scheduleAutosave();
    },
);

watch(
    () => edges.value.map((e) => `${e.source}->${e.target}`).sort().join('|'),
    (newVal, oldVal) => {
        if (oldVal && newVal !== oldVal) scheduleAutosave();
    },
);
</script>

<template>
    <AppLayout :title="isEditing ? 'Edit Graph' : 'Visual Graph Builder'">
        <Head :title="isEditing ? 'Edit Graph' : 'Visual Graph Builder'" />

        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="isEditing ? route('agent.delegation.show', graphId) : route('agent.delegation.index')">
                        <Button variant="ghost" size="icon">
                            <ArrowLeft class="h-4 w-4" />
                        </Button>
                    </Link>
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                        <GitBranch class="h-5 w-5 text-primary" />
                    </div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-base font-semibold text-foreground truncate">
                            {{ isEditing ? 'Edit Graph' : 'Visual Graph Builder' }}
                        </h2>
                        <HelpHint
                            ui-key="delegation.graph-builder"
                            short-text="Drag nodes and connect them to build a delegation DAG."
                            learn-more-href="/docs/overview"
                        />
                    </div>
                </div>
            </div>
        </template>

        <div class="flex h-[calc(100vh-8rem)] flex-col px-4 py-4 sm:px-6 lg:px-8">
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <input
                    v-model="name"
                    type="text"
                    placeholder="Graph name"
                    class="rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 max-w-[200px]"
                />
                <Button variant="outline" size="sm" @click="addTask">
                    <Plus class="mr-2 h-4 w-4" />
                    Add task
                </Button>
                <Button v-if="!isEditing" variant="outline" size="sm" :disabled="saving" @click="saveDraft">
                    <Save class="mr-2 h-4 w-4" />
                    Save draft
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="validating || !graphId"
                    :title="!graphId ? 'Save the graph first to validate' : ''"
                    @click="validateGraph"
                >
                    <CheckCircle class="mr-2 h-4 w-4" />
                    Validate
                </Button>
                <Button
                    v-if="isEditing"
                    size="sm"
                    :disabled="loading"
                    @click="startGraph"
                >
                    <Play class="mr-2 h-4 w-4" />
                    Start
                </Button>
                <span v-if="saving && isEditing" class="text-xs text-muted-foreground">Saving...</span>
            </div>

            <p v-if="error" class="mb-2 text-sm text-destructive">{{ error }}</p>
            <p v-if="validationError" class="mb-2 text-sm text-amber-600 dark:text-amber-400">{{ validationError }}</p>

            <div class="relative min-h-0 flex-1 rounded-lg border border-border">
                <GraphCanvas
                    v-if="!loading"
                    v-model:nodes="nodes"
                    v-model:edges="edges"
                    :edit-mode="true"
                    :node-types="nodeTypes"
                    @nodes-change="onNodesChange"
                    @edges-change="onEdgesChange"
                    @connect="onConnect"
                    @node-click="onNodeClick"
                />
                <div v-else class="flex h-full items-center justify-center text-muted-foreground">
                    Loading graph...
                </div>

                <!-- Config panel slides over the graph -->
                <Transition
                    enter-active-class="transition-transform duration-200 ease-out"
                    leave-active-class="transition-transform duration-150 ease-in"
                    enter-from-class="translate-x-full"
                    enter-to-class="translate-x-0"
                    leave-from-class="translate-x-0"
                    leave-to-class="translate-x-full"
                >
                    <aside
                        v-if="selectedNode"
                        class="absolute right-3 top-3 z-10 w-80 max-h-[calc(100%-1.5rem)] overflow-y-auto"
                    >
                        <TaskConfigPanel
                            :node="selectedNode"
                            :delegatee-profiles="delegateeProfiles"
                            :capabilities="capabilities"
                            @close="selectedNodeId = null"
                            @update="onTaskConfigUpdate"
                            @delete="deleteTask"
                        />
                    </aside>
                </Transition>
            </div>
        </div>

        <DelegateeProfileCreateModal
            :show="showCreateProfileModal"
            @close="showCreateProfileModal = false"
            @created="onProfileCreated"
        />
    </AppLayout>
</template>
