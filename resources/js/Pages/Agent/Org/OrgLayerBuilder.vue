<script setup>
import { markRaw, ref, computed, onMounted, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import Button from '@/Components/ui/Button.vue';
import { GraphCanvas } from '@/Components/GraphCanvas';
import OrgAgentNode from '@/Components/Org/OrgAgentNode.vue';
import OrgAgentConfigPanel from '@/Components/Org/OrgAgentConfigPanel.vue';
import DelegateeProfileCreateModal from '@/Components/Delegation/DelegateeProfileCreateModal.vue';
import NlOrgChat from '@/Components/Org/NlOrgChat.vue';
import { ArrowLeft, Plus, Users, MessageSquare } from 'lucide-vue-next';
import HelpHint from '@/Components/HelpHint.vue';

const nodeTypes = { orgAgent: markRaw(OrgAgentNode) };

const nodes = ref([]);
const edges = ref([]);
const delegateeProfiles = ref([]);
const loading = ref(false);
const saving = ref(false);
const error = ref('');
const validationError = ref('');
const selectedNodeId = ref(null);
const showCreateProfileModal = ref(false);
const showNlSidebar = ref(false);
const pendingChangeset = ref(null);

/**
 * Compute hierarchical positions for org nodes.
 * Roots (no parent / managers) are placed at the top, leaves (subordinates) below.
 * Separate connected components are laid out side by side, largest first.
 */
function computeHierarchicalPositions(nodesList, edgesList) {
    if (nodesList.length === 0) return new Map();

    const NODE_W = 220;
    const GAP_X = 40;
    const GAP_Y = 140;
    const PADDING = 80;
    const COMPONENT_GAP = 80;

    // edges: source = subordinate, target = manager (parent)
    const parentOf = new Map();
    const childrenOf = new Map();
    nodesList.forEach((n) => childrenOf.set(n.id, []));
    edgesList.forEach((e) => {
        parentOf.set(e.source, e.target);
        if (childrenOf.has(e.target)) childrenOf.get(e.target).push(e.source);
    });

    // Find connected components via undirected BFS
    const adjacency = new Map();
    nodesList.forEach((n) => adjacency.set(n.id, []));
    edgesList.forEach((e) => {
        adjacency.get(e.source)?.push(e.target);
        adjacency.get(e.target)?.push(e.source);
    });

    const visited = new Set();
    const components = [];
    nodesList.forEach((n) => {
        if (visited.has(n.id)) return;
        const component = [];
        const q = [n.id];
        visited.add(n.id);
        while (q.length > 0) {
            const id = q.shift();
            component.push(id);
            for (const neighbor of adjacency.get(id) ?? []) {
                if (!visited.has(neighbor)) {
                    visited.add(neighbor);
                    q.push(neighbor);
                }
            }
        }
        components.push(component);
    });

    // Sort components largest first so the main org is on the left
    components.sort((a, b) => b.length - a.length);

    // Layout each component independently, then place side by side
    const positions = new Map();
    let offsetX = PADDING;

    for (const component of components) {
        const compSet = new Set(component);

        // BFS from roots within this component
        const roots = component.filter((id) => !parentOf.has(id));
        const depthOf = new Map();
        const bfsQueue = [...roots];
        roots.forEach((id) => depthOf.set(id, 0));
        let maxDepth = 0;

        while (bfsQueue.length > 0) {
            const id = bfsQueue.shift();
            const d = depthOf.get(id);
            for (const cid of childrenOf.get(id) ?? []) {
                if (compSet.has(cid) && !depthOf.has(cid)) {
                    depthOf.set(cid, d + 1);
                    if (d + 1 > maxDepth) maxDepth = d + 1;
                    bfsQueue.push(cid);
                }
            }
        }

        // Orphans within component
        component.forEach((id) => {
            if (!depthOf.has(id)) depthOf.set(id, 0);
        });

        // Group by depth
        const levels = new Map();
        component.forEach((id) => {
            const d = depthOf.get(id);
            if (!levels.has(d)) levels.set(d, []);
            levels.get(d).push(id);
        });

        // Widest level in this component
        let maxLevelWidth = 0;
        levels.forEach((ids) => {
            const w = ids.length * NODE_W + (ids.length - 1) * GAP_X;
            if (w > maxLevelWidth) maxLevelWidth = w;
        });

        // Position nodes within this component
        levels.forEach((ids, d) => {
            const row = d;
            const y = PADDING + row * GAP_Y;
            const levelWidth = ids.length * NODE_W + (ids.length - 1) * GAP_X;
            const startX = offsetX + (maxLevelWidth - levelWidth) / 2;

            ids.forEach((id, i) => {
                positions.set(id, { x: startX + i * (NODE_W + GAP_X), y });
            });
        });

        offsetX += maxLevelWidth + COMPONENT_GAP;
    }

    return positions;
}

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

        // Build edges first so we can compute hierarchical positions
        const edgeList = [];
        agents.forEach((a) => {
            const managerId = a.reporting_edge?.manager_agent_id ?? a.parent_agent_id;
            if (managerId) {
                edgeList.push({
                    id: `e-${a.id}-${managerId}`,
                    source: a.id,
                    target: managerId,
                    sourceHandle: 'top-source',
                    targetHandle: 'bottom-target',
                });
            }
        });

        const stubNodes = agents.map((a) => ({ id: a.id }));
        const posMap = computeHierarchicalPositions(stubNodes, edgeList);

        // Build lookup maps for resolved names
        const agentNameMap = new Map(agents.map((a) => [a.id, a.name]));
        const profileNameMap = new Map(delegateeProfiles.value.map((p) => [p.id, p.name]));

        nodes.value = agents.map((a, i) => {
            const parentId = a.reporting_edge?.manager_agent_id ?? a.parent_agent_id ?? null;
            return {
                id: a.id,
                type: 'orgAgent',
                position: posMap.get(a.id) ?? { x: 120 + (i % 3) * 240, y: 80 + Math.floor(i / 3) * 160 },
                data: {
                    name: a.name,
                    role_slug: a.role_slug,
                    role_description: a.role_description,
                    delegatee_profile_id: a.delegatee_profile_id,
                    parent_agent_id: parentId,
                    parentAgentName: parentId ? agentNameMap.get(parentId) ?? null : null,
                    delegateeProfileName: a.delegatee_profile_id ? profileNameMap.get(a.delegatee_profile_id) ?? null : null,
                    onDeleteNode: deleteAgent,
                    ...a,
                },
            };
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
            position: { x: 120 + (nodes.value.length % 4) * 260, y: 80 },
            data: {
                name: `Agent ${nodes.value.length + 1}`,
                role_slug: 'agent',
                role_description: '',
                delegatee_profile_id: null,
                parent_agent_id: null,
                isNew: true,
                onDeleteNode: deleteAgent,
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
    // GraphCanvas already added the new edge (with handle info) via addEdge.
    // Enforce one outgoing "reports-to" edge per source agent.
    edges.value = edges.value.filter(
        (e) => e.source !== connection.source || e.target === connection.target
    );

    nodes.value = nodes.value.map((n) =>
        n.id === connection.source
            ? { ...n, data: { ...n.data, parent_agent_id: connection.target } }
            : n
    );
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

    const oldParent = nodes.value.find((n) => n.id === id)?.data?.parent_agent_id ?? null;
    const newParent = data.parent_agent_id ?? null;

    nodes.value = nodes.value.map((n) =>
        n.id === id ? { ...n, data: { ...n.data, ...data } } : n
    );

    if (oldParent !== newParent) {
        const filtered = edges.value.filter((e) => e.source !== id);

        if (newParent) {
            filtered.push({
                id: `e-${id}-${newParent}`,
                source: id,
                target: newParent,
                sourceHandle: 'top-source',
                targetHandle: 'bottom-target',
            });
        }

        edges.value = filtered;
    }
};

const onProfileCreated = (profile) => {
    delegateeProfiles.value = [...delegateeProfiles.value, profile];
    onAgentConfigUpdate({ delegatee_profile_id: profile.id });
};

const deleteAgent = async (nodeId) => {
    error.value = '';

    if (nodeId.startsWith('temp-')) {
        nodes.value = nodes.value.filter((n) => n.id !== nodeId);
        edges.value = edges.value.filter((e) => e.source !== nodeId && e.target !== nodeId);
        if (selectedNodeId.value === nodeId) selectedNodeId.value = null;
        return;
    }

    try {
        await axios.delete(`/agent/api/v1/org/agents/${nodeId}`);
        nodes.value = nodes.value.filter((n) => n.id !== nodeId);
        edges.value = edges.value.filter((e) => e.source !== nodeId && e.target !== nodeId);
        if (selectedNodeId.value === nodeId) selectedNodeId.value = null;
    } catch (e) {
        error.value = e?.response?.data?.error?.message ?? 'Failed to delete agent.';
    }
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
    if (saving.value) return;
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

// --- Autosave: debounce save when graph changes ---
let autosaveTimer = null;
const scheduleAutosave = () => {
    if (autosaveTimer) clearTimeout(autosaveTimer);
    autosaveTimer = setTimeout(() => save(), 800);
};

// Watch for config panel updates and connection changes to trigger autosave
watch(
    () => nodes.value.map((n) => {
        const d = n.data ?? {};
        return `${n.id}:${d.name}:${d.role_slug}:${d.role_description}:${d.delegatee_profile_id}:${d.parent_agent_id}`;
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

// --- NL Sidebar: diff preview handlers ---
const originalNodeStyles = ref(new Map());

const onPreviewChangeset = (changeset) => {
    // Save original node data so we can restore on discard
    originalNodeStyles.value = new Map(
        nodes.value.map((n) => [n.id, { ...n.data }])
    );

    const overlayNodes = [];
    const overlayEdges = [];

    for (const entry of changeset) {
        if (entry.entity_type === 'org_agent_profile') {
            if (entry.operation === 'add') {
                const tempId = entry.temp_id ?? `nl-temp-${Date.now()}-${Math.random()}`;
                overlayNodes.push({
                    id: tempId,
                    type: 'orgAgent',
                    position: { x: 120 + (nodes.value.length + overlayNodes.length) % 4 * 260, y: 80 },
                    draggable: false,
                    data: {
                        ...entry.payload,
                        name: entry.payload.name ?? 'New Agent',
                        role_slug: entry.payload.role_slug ?? 'agent',
                        diffState: 'add',
                    },
                });
            } else if (entry.operation === 'update') {
                const targetId = entry.payload.id;
                nodes.value = nodes.value.map((n) =>
                    n.id === targetId
                        ? { ...n, data: { ...n.data, diffState: 'update' } }
                        : n
                );
            } else if (entry.operation === 'remove') {
                const targetId = entry.payload.id;
                nodes.value = nodes.value.map((n) =>
                    n.id === targetId
                        ? { ...n, data: { ...n.data, diffState: 'remove' } }
                        : n
                );
            }
        } else if (entry.entity_type === 'org_reporting_edge') {
            if (entry.operation === 'add') {
                overlayEdges.push({
                    id: `nl-edge-${Date.now()}-${Math.random()}`,
                    source: entry.payload.subordinate_agent_id,
                    target: entry.payload.manager_agent_id,
                    sourceHandle: 'top-source',
                    targetHandle: 'bottom-target',
                    style: { stroke: '#22c55e', strokeDasharray: '5 5' },
                });
            } else if (entry.operation === 'remove') {
                const src = entry.payload.subordinate_agent_id;
                const tgt = entry.payload.manager_agent_id;
                edges.value = edges.value.map((e) =>
                    e.source === src && e.target === tgt
                        ? { ...e, style: { stroke: '#ef4444', strokeDasharray: '5 5' } }
                        : e
                );
            }
        }
    }

    if (overlayNodes.length > 0) {
        nodes.value = [...nodes.value, ...overlayNodes];
    }
    if (overlayEdges.length > 0) {
        edges.value = [...edges.value, ...overlayEdges];
    }

    pendingChangeset.value = changeset;
};

const onApplyChangeset = () => {
    pendingChangeset.value = null;
    originalNodeStyles.value = new Map();
    loadAgentsAndDelegatees();
};

const onDiscardChangeset = () => {
    // Remove overlay nodes (nl-temp-*) and restore original node styles
    nodes.value = nodes.value
        .filter((n) => !String(n.id).startsWith('nl-temp-'))
        .map((n) => {
            const orig = originalNodeStyles.value.get(n.id);
            if (orig) {
                const { diffState, ...rest } = orig;
                return { ...n, data: { ...rest, onDeleteNode: deleteAgent } };
            }
            return n;
        });

    // Remove overlay edges and restore original edge styles
    edges.value = edges.value
        .filter((e) => !String(e.id).startsWith('nl-edge-'))
        .map((e) => {
            const { style, ...rest } = e;
            return rest;
        });

    pendingChangeset.value = null;
    originalNodeStyles.value = new Map();
};
</script>

<template>
    <AppLayout title="Workforce">
        <Head title="Workforce" />

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
                        <h2 class="text-base font-semibold text-foreground truncate">
                            Workforce
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

        <div class="relative flex h-[calc(100vh-8rem)] flex-col px-4 py-4 sm:px-6 lg:px-8">
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <Button variant="outline" size="sm" @click="addAgent">
                    <Plus class="mr-2 h-4 w-4" />
                    Add agent
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    :class="showNlSidebar ? 'bg-primary/10' : ''"
                    @click="showNlSidebar = !showNlSidebar"
                >
                    <MessageSquare class="mr-2 h-4 w-4" />
                    Workforce Chat
                </Button>
                <span v-if="saving" class="text-xs text-muted-foreground">Saving…</span>
            </div>

            <p v-if="error" class="mb-2 text-sm text-destructive">{{ error }}</p>
            <p v-if="validationError" class="mb-2 text-sm text-amber-600 dark:text-amber-400">{{ validationError }}</p>

            <div class="relative min-h-0 flex-1">
                <div class="relative h-full rounded-lg border border-border">
                    <GraphCanvas
                        v-if="!loading"
                        v-model:nodes="nodes"
                        v-model:edges="edges"
                        :edit-mode="true"
                        :fit-view="true"
                        :node-types="nodeTypes"
                        @nodes-change="onNodesChange"
                        @edges-change="onEdgesChange"
                        @connect="onConnect"
                        @node-click="onNodeClick"
                    />
                    <div v-else class="flex h-full items-center justify-center text-muted-foreground">
                        Loading agents…
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
                            <OrgAgentConfigPanel
                                :node="selectedNode"
                                :delegatee-profiles="delegateeProfiles"
                                :agents="agentListForPanel"
                                @close="selectedNodeId = null"
                                @update="onAgentConfigUpdate"
                                @delete="deleteAgent"
                                @create-profile="showCreateProfileModal = true"
                            />
                        </aside>
                    </Transition>

                    <!-- Workforce Chat panel floats over the graph -->
                    <Transition
                        enter-active-class="transition-transform duration-200 ease-out"
                        leave-active-class="transition-transform duration-150 ease-in"
                        enter-from-class="translate-x-full"
                        enter-to-class="translate-x-0"
                        leave-from-class="translate-x-0"
                        leave-to-class="translate-x-full"
                    >
                        <aside
                            v-if="showNlSidebar"
                            class="absolute right-3 top-3 bottom-3 z-10 w-96"
                        >
                            <NlOrgChat
                                @close="showNlSidebar = false"
                                @preview-changeset="onPreviewChangeset"
                                @apply-changeset="onApplyChangeset"
                                @discard-changeset="onDiscardChangeset"
                            />
                        </aside>
                    </Transition>
                </div>
            </div>
        </div>

        <DelegateeProfileCreateModal
            :show="showCreateProfileModal"
            @close="showCreateProfileModal = false"
            @created="onProfileCreated"
        />
    </AppLayout>
</template>
