<script setup>
import { VueFlow, applyNodeChanges, applyEdgeChanges, addEdge } from '@vue-flow/core';
import { Background } from '@vue-flow/background';
import { Controls } from '@vue-flow/controls';
import '@vue-flow/core/dist/style.css';
import '@vue-flow/core/dist/theme-default.css';

const props = defineProps({
    nodes: {
        type: Array,
        default: () => [],
    },
    edges: {
        type: Array,
        default: () => [],
    },
    editMode: {
        type: Boolean,
        default: true,
    },
    fitView: {
        type: Boolean,
        default: false,
    },
    nodeTypes: {
        type: Object,
        default: () => ({}),
    },
    edgeTypes: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits([
    'update:nodes',
    'update:edges',
    'nodes-change',
    'edges-change',
    'connect',
    'node-click',
    'edge-click',
]);

function onNodesChange(changes) {
    const next = applyNodeChanges(changes, props.nodes);
    emit('update:nodes', next);
    emit('nodes-change', { changes, nodes: next });
}

function onEdgesChange(changes) {
    const next = applyEdgeChanges(changes, props.edges);
    emit('update:edges', next);
    emit('edges-change', { changes, edges: next });
}

function onConnect(connection) {
    const next = addEdge(connection, props.edges);
    emit('update:edges', next);
    emit('connect', connection);
}

function onNodeClick({ node }) {
    emit('node-click', node);
}

function onEdgeClick({ edge }) {
    emit('edge-click', edge);
}
</script>

<template>
    <div class="graph-canvas-container h-full w-full min-h-[400px] rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
        <VueFlow
            :nodes="nodes"
            :edges="edges"
            :node-types="nodeTypes"
            :edge-types="edgeTypes"
            :nodes-draggable="editMode"
            :nodes-connectable="editMode"
            :elements-selectable="editMode"
            :deletable="editMode"
            :fit-view-on-init="fitView"
            connect-on-click
            @nodes-change="onNodesChange"
            @edges-change="onEdgesChange"
            @connect="onConnect"
            @node-click="onNodeClick"
            @edge-click="onEdgeClick"
        >
            <Background pattern-color="#94a3b8" :gap="16" />
            <Controls />
        </VueFlow>
    </div>
</template>
