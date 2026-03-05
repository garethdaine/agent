<script setup>
import { ref, computed, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import Button from '@/Components/ui/Button.vue';
import { GraphCanvas } from '@/Components/GraphCanvas';
import OrgAgentNode from '@/Components/Org/OrgAgentNode.vue';
import OrgAgentConfigPanel from '@/Components/Org/OrgAgentConfigPanel.vue';
import { ArrowLeft, Plus, Save, Users } from 'lucide-vue-next';
import HelpHint from '@/Components/HelpHint.vue';

const nodeTypes = { orgAgent: OrgAgentNode };

const nodes = ref([]);
const edges = ref([]);
const delegateeProfiles = ref([]);
const loading = ref(false);
const saving = ref(false);
const error = ref('');
const validationError = ref('');
const selectedNodeId = ref(null);

const loadAgentsAndDelegatees = async () => {
    loading.value = true;
    error.value = '';
    try {
        const [agentsRes, delegateesRes] = await Promise.all([
            axios.get('/agent/api/v1/org/agents', { params: { with_reporting: true } }),
            axios.get('/agent/api/v1/delegation/delegatee-profiles'),
        ]);
        const agents = agentsRes.data?.data ?? [];
        delegateeProfiles.value = delegateesRes.data?.data ?? [];

        nodes.value = agents.map((a, i) => ({
            id: a.id,
            type: 'orgAgent',
            position: { x: 120 + (i % 4) * 200, y: 80 + Math.floor(i / 4) * 120 },
            data: {
                name: a.name,
                role_slug: a.role_slug,
                role_description: a.role_description,
                delegatee_profile_id: a.delegatee_profile_id,
                parent_agent_id: a.reporting_edge?.manager_agent_id ?? a.parent_agent_id ?? null,
                ...a,
            },
        }));

        const edgeList = [];
        agents.forEach((a) => {
            const managerId = a.reporting_edge?.manager_agent_id ?? a.parent_agent_id;
            if (managerId) {
                edgeList.push({
                    id: `e-${a.id}-${managerId}`,
                    source: a.id,
                    target: managerId,
                });
            }
        });
        edges.value = edgeList;
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to load agents.';
    } finally {
        loading.value = false;
    }
};

onMounted(loadAgentsAndDelegatees);

let agentCounter = 0;
const addAgent = () => {
    agentCounter += 1;
    const id = `temp-${Date.now()}-${agentCounter}`;
    nodes.value = [
        ...nodes.value,
        {
            id,
            type: 'orgAgent',
            position: { x: 120 + (nodes.value.length % 4) * 200, y: 80 + Math.floor(nodes.value.length / 4) * 120 },
            data: {
                name: `Agent ${nodes.value.length + 1}`,
                role_slug: 'agent',
                role_description: '',
                delegatee_profile_id: null,
                parent_agent_id: null,
                isNew: true,
            },
        },
    ];
};

const onNodesChange = ({ nodes: next }) => {
    nodes.value = next;
};

const onEdgesChange = ({ edges: next }) => {
    edges.value = next;
};

const onConnect = (connection) => {
    const sourceId = connection.source;
    const newEdge = { id: `e-${sourceId}-${connection.target}`, source: sourceId, target: connection.target };
    edges.value = [
        ...edges.value.filter((e) => e.source !== sourceId),
        newEdge,
    ];
    validationError.value = '';
};

const selectedNode = computed(() => {
    if (!selectedNodeId.value) return null;
    return nodes.value.find((n) => n.id === selectedNodeId.value) ?? null;
});

const agentListForPanel = computed(() =>
    nodes.value.map((n) => ({
        id: n.id,
        name: n.data?.name ?? n.data?.label ?? (n.id.startsWith('temp-') ? `New agent` : n.id),
        data: n.data,
    }))
);

const onNodeClick = (node) => {
    selectedNodeId.value = node?.id ?? null;
};

const onAgentConfigUpdate = (data) => {
    const id = selectedNodeId.value;
    if (!id) return;
    nodes.value = nodes.value.map((n) =>
        n.id === id ? { ...n, data: { ...n.data, ...data } } : n
    );
};

function topoSortForCreate(nodesList, edgesList) {
    const outdegree = new Map();
    const incoming = new Map();
    nodesList.forEach((n) => {
        outdegree.set(n.id, 0);
        incoming.set(n.id, []);
    });
    edgesList.forEach((e) => {
        outdegree.set(e.source, (outdegree.get(e.source) ?? 0) + 1);
        incoming.get(e.target).push(e.source);
    });
    const queue = nodesList.filter((n) => outdegree.get(n.id) === 0).map((n) => n.id);
    const order = [];
    while (queue.length > 0) {
        const u = queue.shift();
        order.push(u);
        (incoming.get(u) ?? []).forEach((v) => {
            const d = outdegree.get(v) - 1;
            outdegree.set(v, d);
            if (d === 0) queue.push(v);
        });
    }
    return order;
}

const save = async () => {
    saving.value = true;
    error.value = '';
    validationError.value = '';
    const tempNodes = nodes.value.filter((n) => n.id.startsWith('temp-'));
    const needDelegatee = tempNodes.some((n) => !n.data?.delegatee_profile_id);
    if (needDelegatee) {
        validationError.value = 'Each new agent must have a delegatee profile. Select one in the config panel.';
        saving.value = false;
        return;
    }

    try {
        const idMapping = new Map();
        nodes.value.forEach((n) => idMapping.set(n.id, n.id));

        const order = topoSortForCreate([...nodes.value], edges.value);
        for (const nodeId of order) {
            const node = nodes.value.find((n) => n.id === nodeId);
            if (!node) continue;
            const managerId = edges.value.find((e) => e.source === nodeId)?.target;
            const resolvedManagerId = managerId ? idMapping.get(managerId) ?? null : null;

            if (node.id.startsWith('temp-')) {
                const payload = {
                    name: node.data?.name ?? 'Agent',
                    role_slug: node.data?.role_slug ?? 'agent',
                    role_description: node.data?.role_description ?? '',
                    delegatee_profile_id: node.data?.delegatee_profile_id,
                    capability_bindings: node.data?.capability_bindings ?? [],
                    authority_overrides: node.data?.authority_overrides ?? [],
                    parent_agent_id: resolvedManagerId || null,
                };
                const { data } = await axios.post('/agent/api/v1/org/agents', payload);
                const newId = data.data?.id;
                if (newId) idMapping.set(node.id, newId);
            } else {
                const payload = {};
                if (node.data?.name != null) payload.name = node.data.name;
                if (node.data?.role_slug != null) payload.role_slug = node.data.role_slug;
                if (node.data?.role_description != null) payload.role_description = node.data.role_description;
                if (node.data?.delegatee_profile_id != null) payload.delegatee_profile_id = node.data.delegatee_profile_id;
                payload.parent_agent_id = resolvedManagerId ?? null;
                await axios.put(`/agent/api/v1/org/agents/${node.id}`, payload);
            }
        }

        await loadAgentsAndDelegatees();
        selectedNodeId.value = null;
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
</script>

<template>
    <AppLayout title="Org Layer Builder">
        <Head title="Org Layer Builder" />

        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="route('org.index')">
                        <Button variant="ghost" size="icon">
                            <ArrowLeft class="h-4 w-4" />
                        </Button>
                    </Link>
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                        <Users class="h-5 w-5 text-primary" />
                    </div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-xl font-semibold leading-tight text-foreground">
                            Org Layer Builder
                        </h2>
                        <HelpHint
                            ui-key="org.layer-builder"
                            short-text="Drag agents and connect them to set reporting (subordinate → manager)."
                            learn-more-href="/docs/overview"
                        />
                    </div>
                </div>
            </div>
        </template>

        <div class="flex h-[calc(100vh-8rem)] flex-col px-4 py-4 sm:px-6 lg:px-8">
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <Button variant="outline" size="sm" @click="addAgent">
                    <Plus class="mr-2 h-4 w-4" />
                    Add agent
                </Button>
                <Button variant="outline" size="sm" :disabled="saving" @click="save">
                    <Save class="mr-2 h-4 w-4" />
                    Save
                </Button>
            </div>

            <p v-if="error" class="mb-2 text-sm text-destructive">{{ error }}</p>
            <p v-if="validationError" class="mb-2 text-sm text-amber-600 dark:text-amber-400">{{ validationError }}</p>

            <div class="flex min-h-0 flex-1 gap-4">
                <div class="min-h-0 flex-1 rounded-lg border border-border">
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
                        Loading agents…
                    </div>
                </div>
                <aside v-if="selectedNode" class="flex shrink-0 flex-col gap-2">
                    <OrgAgentConfigPanel
                        :node="selectedNode"
                        :delegatee-profiles="delegateeProfiles"
                        :agents="agentListForPanel"
                        @close="selectedNodeId = null"
                        @update="onAgentConfigUpdate"
                    />
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
