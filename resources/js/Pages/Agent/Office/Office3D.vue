<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import Button from '@/Components/ui/Button.vue';
import { ArrowLeft, Building2, AlertTriangle } from 'lucide-vue-next';
import { useThreeScene } from '@/Composables/useThreeScene.js';
import {
    BoxGeometry,
    MeshStandardMaterial,
    Mesh,
    PlaneGeometry,
    DoubleSide,
    Group,
    BufferGeometry,
    Float32BufferAttribute,
    LineBasicMaterial,
    Line,
} from 'three';
import { createAgentAvatar } from '@/Support/Office/AgentAvatar.js';
import { getAgentPositions } from '@/Support/Office/officeLayout.js';

const props = defineProps({
    graphId: { type: Number, default: null },
    ritualRunId: { type: [Number, String], default: null },
});

const containerRef = ref(null);
const loading = ref(true);
const error = ref('');
const webglUnavailable = ref(false);
const agents = ref([]);
const agentThoughts = ref({});

let sceneApi = null;
let avatarGroups = [];
let connectionLines = [];

function addConnectionLine(scene, fromPos, toPos) {
    const points = [
        fromPos.x, 0.5, fromPos.z,
        (fromPos.x + toPos.x) / 2, 1.2, (fromPos.z + toPos.z) / 2,
        toPos.x, 0.5, toPos.z,
    ];
    const geom = new BufferGeometry();
    geom.setAttribute('position', new Float32BufferAttribute(points, 3));
    const mat = new LineBasicMaterial({ color: 0x6366f1, linewidth: 1 });
    const line = new Line(geom, mat);
    scene.add(line);
    connectionLines.push(line);
}

onMounted(async () => {
    try {
        const { data } = await axios.get('/agent/api/v1/org/agents', { params: { with_reporting: true } }).catch(() => ({ data: { data: [] } }));
        agents.value = data?.data ?? [];
        agents.value.forEach((a, i) => {
            agentThoughts.value[a.id] = { status: 'idle', text: a.name ? `${a.name} — Idle` : 'Agent' };
        });
    } catch {
        agents.value = [];
    }

    if (!containerRef.value) {
        loading.value = false;
        return;
    }
    try {
        const positions = getAgentPositions(agents.value);

        sceneApi = useThreeScene(containerRef, {
            onFrame(delta) {
                avatarGroups.forEach((g, i) => {
                    if (g && g.position) {
                        g.rotation.y += delta * 0.3;
                    }
                });
            },
        });
        const { scene } = sceneApi.start();
        if (!scene) {
            loading.value = false;
            return;
        }

        const floorGeom = new PlaneGeometry(12, 12);
        const floorMat = new MeshStandardMaterial({
            color: 0x16213e,
            side: DoubleSide,
        });
        const floor = new Mesh(floorGeom, floorMat);
        floor.rotation.x = -Math.PI / 2;
        floor.receiveShadow = true;
        scene.add(floor);

        const room = new Group();
        const wallMat = new MeshStandardMaterial({ color: 0x0f3460 });
        const wallGeom = new BoxGeometry(12, 4, 0.3);
        const back = new Mesh(wallGeom, wallMat);
        back.position.set(0, 2, -6);
        back.castShadow = true;
        back.receiveShadow = true;
        room.add(back);
        scene.add(room);

        agents.value.forEach((agent, i) => {
            const pos = positions.get(agent.id);
            if (!pos) return;
            const avatar = createAgentAvatar({
                id: agent.id,
                name: agent.name,
                colorIndex: i,
            });
            avatar.position.set(pos.x, 0, pos.z);
            scene.add(avatar);
            avatarGroups.push(avatar);
        });

        agents.value.forEach((agent) => {
            const managerId = agent.reporting_edge?.manager_agent_id ?? agent.parent_agent_id;
            if (!managerId) return;
            const fromPos = positions.get(agent.id);
            const toPos = positions.get(managerId);
            if (fromPos && toPos) addConnectionLine(scene, fromPos, toPos);
        });

        window.addEventListener('resize', sceneApi.resize);
    } catch (e) {
        webglUnavailable.value = true;
        error.value = e?.message ?? 'WebGL unavailable';
    } finally {
        loading.value = false;
    }
});

onUnmounted(() => {
    window.removeEventListener('resize', sceneApi?.resize);
    avatarGroups = [];
    connectionLines = [];
    sceneApi?.stop();
});
</script>

<template>
    <AppLayout title="3D Office">
        <Head title="3D Office" />

        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="route('dashboard')">
                        <Button variant="ghost" size="icon">
                            <ArrowLeft class="h-4 w-4" />
                        </Button>
                    </Link>
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                        <Building2 class="h-5 w-5 text-primary" />
                    </div>
                    <h2 class="text-xl font-semibold leading-tight text-foreground">
                        3D Office
                    </h2>
                </div>
            </div>
        </template>

        <div class="relative h-[calc(100vh-8rem)] w-full px-4 py-4 sm:px-6 lg:px-8">
            <div
                v-if="webglUnavailable || error"
                class="flex flex-col items-center justify-center gap-4 rounded-lg border border-border bg-muted/30 p-8 text-center"
            >
                <AlertTriangle class="h-12 w-12 text-amber-500" />
                <p class="text-sm text-muted-foreground">
                    3D view unavailable. {{ error || 'WebGL may not be supported on this device.' }}
                </p>
            </div>
            <template v-else>
                <div
                    ref="containerRef"
                    class="h-full w-full min-h-[320px] rounded-lg border border-border bg-background"
                />
                <div
                    v-if="loading"
                    class="absolute inset-0 flex items-center justify-center rounded-lg bg-background/80"
                >
                    <p class="text-sm text-muted-foreground">Loading 3D scene…</p>
                </div>
                <div
                    v-else-if="agents.length > 0"
                    class="absolute bottom-4 left-4 right-4 flex flex-wrap gap-2 rounded-lg border border-border bg-background/90 px-3 py-2 shadow-sm"
                >
                    <span
                        v-for="(thought, id) in agentThoughts"
                        :key="id"
                        class="rounded bg-muted px-2 py-1 text-xs text-muted-foreground"
                    >
                        {{ thought.text }}
                    </span>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
