<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import Button from '@/Components/ui/Button.vue';
import { ArrowLeft, Building2, AlertTriangle, Maximize2, Minimize2 } from 'lucide-vue-next';
import { usePage } from '@inertiajs/vue3';
import { useOfficeScene } from '@/Composables/Office/useOfficeScene.js';
import { useAgentAvatars } from '@/Composables/Office/useAgentAvatars.js';
import { useOfficeRealtime } from '@/Composables/Office/useOfficeRealtime.js';
import { useOfficeZones } from '@/Composables/Office/useOfficeZones.js';
import { useOfficeInteraction } from '@/Composables/Office/useOfficeInteraction.js';
import { useOfficeEnvironment } from '@/Composables/Office/useOfficeEnvironment.js';
import OfficeDetailPanel from '@/Components/Office/OfficeDetailPanel.vue';
import { buildOfficeFloor, buildOfficeWalls, buildZoneLabels, buildZoneFurniture, ZONE_DEFS, WORKSTATION_POSITIONS } from '@/Support/Office/officeFloorplan.js';
import { ParticleEmitter, SpeechBubble, AmbientAnimations } from '@/Support/Office/animations/visualEffects.js';
import { Vector3 } from 'three';
import {
    Sun, Moon, MonitorCog,
    Cloud, CloudRain, CloudSnow, CloudLightning, CloudFog, CloudSun,
    Thermometer, Wind,
} from 'lucide-vue-next';

const page = usePage();
const containerRef = ref(null);
const loading = ref(true);
const error = ref('');
const webglUnavailable = ref(false);
const agents = ref([]);
const agentThoughts = ref({});
const activeZone = ref(null);

let sceneApi = null;
let avatarApi = null;
let zoneController = null;
let interactionApi = null;
let envApi = null;
let particles = null;
let speechBubbles = null;
let ambientAnims = null;
const realtime = useOfficeRealtime();
const panelVisible = ref(false);
const panelData = ref(null);
const envOverride = ref(null);
const weatherInfo = ref(null);
const wrapperRef = ref(null);
const isFullscreen = ref(false);

function toggleFullscreen() {
    if (!wrapperRef.value) return;
    if (!document.fullscreenElement) {
        wrapperRef.value.requestFullscreen().catch(() => {});
    } else {
        document.exitFullscreen().catch(() => {});
    }
}

function onFullscreenChange() {
    isFullscreen.value = !!document.fullscreenElement;
    requestAnimationFrame(() => sceneApi?.resize());
}

const WEATHER_LABELS = {
    clear: 'Clear', cloudy: 'Cloudy', fog: 'Fog', rain: 'Rain',
    snow: 'Snow', showers: 'Showers', thunderstorm: 'Thunderstorm',
};

function addZoneLighting(api) {
    api.addZoneLight(-10.5, 2.5, -8.5, 0x00ff44, 0.15, 6);
    api.addZoneLight(1, 2.5, -8.5, 0x3388ff, 0.15, 12);
    api.addZoneLight(2, 2.5, -2.5, 0xffeedd, 0.2, 14);
    api.addZoneLight(-11, 2.5, 3, 0x5865f2, 0.15, 5);
    api.addZoneLight(-2, 2.5, 7, 0xffffff, 0.15, 8);
    api.addZoneLight(10, 2.5, 6, 0xffaa44, 0.15, 6);
    api.addZoneLight(-10, 2.5, 7, 0x44aaff, 0.15, 6);
    api.addZoneLight(10, 2.5, 9.5, 0xffcc88, 0.15, 8);
}

function installCeilingLights(api) {
    ZONE_DEFS.forEach((zone) => {
        const cols = Math.max(1, Math.round(zone.w / 4));
        const rows = Math.max(1, Math.round(zone.d / 4));
        for (let r = 0; r < rows; r++) {
            for (let c = 0; c < cols; c++) {
                const x = zone.cx - zone.w / 2 + zone.w * (c + 0.5) / cols;
                const z = zone.cz - zone.d / 2 + zone.d * (r + 0.5) / rows;
                api.addCeilingLight(zone.id, x, z, {
                    color: 0xffeedd,
                    maxIntensity: 40,
                    distance: Math.max(zone.w, zone.d) * 1.2,
                    height: 3.2,
                });
            }
        }
    });
}

function updateZoneOccupancy() {
    if (!sceneApi || !avatarApi) return;
    const occupied = new Set();
    agents.value.forEach((agent) => {
        const group = avatarApi.getAvatarGroup(agent.id);
        if (!group) return;
        const ax = group.position.x;
        const az = group.position.z;
        for (const zone of ZONE_DEFS) {
            const halfW = zone.w / 2 + 0.5;
            const halfD = zone.d / 2 + 0.5;
            if (ax >= zone.cx - halfW && ax <= zone.cx + halfW
                && az >= zone.cz - halfD && az <= zone.cz + halfD) {
                occupied.add(zone.id);
                break;
            }
        }
    });
    ZONE_DEFS.forEach((zone) => {
        sceneApi.setZoneOccupied(zone.id, occupied.has(zone.id));
    });
}

onMounted(async () => {
    try {
        const { data } = await axios.get('/agent/api/v1/org/agents', { params: { with_reporting: true } }).catch(() => ({ data: { data: [] } }));
        agents.value = data?.data ?? [];
        agents.value.forEach((a) => {
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
        let weatherSyncAccum = 0;
        let occupancyAccum = 0;
        let lastCeilingEnabled = null;
        sceneApi = useOfficeScene(containerRef, {
            onFrame(delta, time) {
                if (envApi) {
                    envApi.update(delta);
                    if (envApi.ceilingLightsEnabled !== lastCeilingEnabled) {
                        lastCeilingEnabled = envApi.ceilingLightsEnabled;
                        sceneApi.setCeilingLightsEnabled(lastCeilingEnabled);
                    }
                    weatherSyncAccum += delta;
                    if (weatherSyncAccum > 1) {
                        weatherSyncAccum = 0;
                        const w = envApi.weather;
                        if (w?.temperature !== undefined) {
                            weatherInfo.value = { ...w, weatherClass: envApi.weatherClass };
                        }
                    }
                }
                if (avatarApi) avatarApi.updateAll(delta);
                if (zoneController) zoneController.updateZones(time);
                if (particles) particles.update(delta);
                if (speechBubbles) speechBubbles.update(delta);
                if (ambientAnims) ambientAnims.update(delta);
                occupancyAccum += delta;
                if (occupancyAccum > 0.25) {
                    occupancyAccum = 0;
                    updateZoneOccupancy();
                }
            },
        });

        const { scene } = sceneApi.start();
        if (!scene) {
            loading.value = false;
            return;
        }

        scene.add(buildOfficeFloor());
        scene.add(buildOfficeWalls());
        scene.add(buildZoneFurniture());
        scene.add(buildZoneLabels());

        envApi = useOfficeEnvironment(sceneApi, { override: envOverride });
        envApi.install(scene);

        avatarApi = useAgentAvatars(scene);
        agents.value.forEach((agent, i) => {
            if (i >= WORKSTATION_POSITIONS.length) return;
            const pos = WORKSTATION_POSITIONS[i];
            avatarApi.addAgent(agent, { x: pos.x, z: pos.z + 0.6 }, i);
        });

        addZoneLighting(sceneApi);
        installCeilingLights(sceneApi);

        zoneController = useOfficeZones(sceneApi.scene, realtime.officeState, avatarApi);

        particles = new ParticleEmitter(sceneApi.scene);
        speechBubbles = new SpeechBubble(sceneApi.scene);
        ambientAnims = new AmbientAnimations(sceneApi.scene);

        const getAgentsByWorkstation = () => {
            const map = {};
            agents.value.forEach((agent, i) => {
                if (i < WORKSTATION_POSITIONS.length) map[i] = agent;
            });
            return map;
        };

        interactionApi = useOfficeInteraction(sceneApi.scene, sceneApi.camera, sceneApi.renderer, {
            onAgentClick(data) {
                panelData.value = data;
                panelVisible.value = true;
                sceneApi.focusOnPosition(data.agentId ? 0 : data.x || 0, data.agentId ? 0 : data.z || 0);
            },
            onZoneClick(data) {
                panelData.value = data;
                panelVisible.value = true;
                const zone = ZONE_DEFS.find((z) => z.id === data.zoneId);
                if (zone) sceneApi.focusOnPosition(zone.cx, zone.cz);
            },
            onDeselect() {
                panelVisible.value = false;
                panelData.value = null;
            },
        }, getAgentsByWorkstation);
        interactionApi.attach();

        realtime.start(page.props.auth?.user?.id);

        watch(realtime.officeState, (state, oldState) => {
            if (!state?.agents) return;
            state.agents.forEach((a) => {
                const prev = oldState?.agents?.find((o) => o.id === a.id);
                const activityLabels = {
                    idle: 'Idle',
                    waiting: 'Waiting',
                    writing_code: 'Writing Code',
                    analyzing: 'Analyzing Code',
                    reviewing: 'Reviewing Code',
                    compiling_report: 'Compiling Report',
                    testing: 'Running Tests',
                    refactoring: 'Refactoring',
                    debugging: 'Debugging',
                    planning: 'Planning',
                    working: 'Working',
                    executing_job: 'Running Job',
                    finishing: 'Finishing Up',
                    chatting: 'Discussing',
                };
                const label = activityLabels[a.current_activity] ?? a.current_activity?.replace(/_/g, ' ') ?? 'Idle';
                agentThoughts.value[a.id] = {
                    status: a.status,
                    text: `${a.name} — ${label}`,
                };

                if (!prev || !avatarApi || !particles) return;

                if (prev.zone !== a.zone && a.zone) {
                    avatarApi.moveAgentToZone(a.id, a.zone === 'workstation' ? 'workstations' : a.zone);
                    if (a.zone === 'conference') {
                        avatarApi.setAgentState(a.id, 'walking');
                    }
                }

                if (prev.current_activity !== a.current_activity && a.current_activity === 'chatting') {
                    const group = avatarApi.getAvatarGroup(a.id);
                    if (group && speechBubbles) {
                        speechBubbles.show(a.id, 'Joining deliberation...', group, { duration: 3, color: '#a5b4fc' });
                    }
                }

                if (prev.status !== 'succeeded' && a.status === 'succeeded') {
                    const group = avatarApi.getAvatarGroup(a.id);
                    if (group) {
                        particles.emitSuccess(group.position);
                        speechBubbles.show(a.id, 'Task complete!', group, { duration: 3 });
                    }
                } else if (prev.status !== 'failed' && a.status === 'failed') {
                    const group = avatarApi.getAvatarGroup(a.id);
                    if (group) {
                        particles.emitFailure(group.position);
                        speechBubbles.show(a.id, 'Error occurred', group, { duration: 4, color: '#ff8888' });
                    }
                } else if (a.needs_attention && !prev.needs_attention) {
                    const group = avatarApi.getAvatarGroup(a.id);
                    if (group) {
                        speechBubbles.show(a.id, 'Needs attention', group, { duration: 6, color: '#ffcc44' });
                    }
                }
            });

            if (state.memory?.recent_formations > (oldState?.memory?.recent_formations ?? 0) && particles) {
                particles.emitMemory(new Vector3(10, 1, 6));
            }
        }, { deep: true });

        watch(realtime.agentOutputQueue, (queue) => {
            if (!queue?.length || !avatarApi || !speechBubbles) return;
            const state = realtime.officeState.value;
            if (!state?.agents) return;

            const latest = queue[queue.length - 1];

            if (latest.event_type === 'task_completed') {
                const fromAgent = state.agents.find((a) =>
                    a.current_delegation_task?.name === latest.task_name,
                );
                if (fromAgent) {
                    const fromGroup = avatarApi.getAvatarGroup(fromAgent.id);
                    if (fromGroup) {
                        particles.emitSuccess(fromGroup.position);
                        const nextLabel = latest.next_tasks?.length
                            ? `Done! Handing off to ${latest.next_tasks[0]}`
                            : 'Review complete!';
                        speechBubbles.show(fromAgent.id, nextLabel, fromGroup, {
                            duration: 4,
                            color: '#a5f3c4',
                        });
                    }
                }
                return;
            }

            if (latest.event_type === 'escalation') {
                const escalatedAgent = state.agents.find((a) =>
                    a.current_run?.id === latest.run_id,
                );
                if (escalatedAgent) {
                    escalatedAgent.zone = 'escalation';
                    escalatedAgent.current_activity = 'waiting';
                    escalatedAgent.needs_attention = true;
                    const group = avatarApi.getAvatarGroup(escalatedAgent.id);
                    if (group) {
                        avatarApi.moveAgentToZone(escalatedAgent.id, 'escalation');
                        speechBubbles.show(escalatedAgent.id, latest.summary, group, {
                            duration: 8,
                            color: '#fca5a5',
                            bgColor: 'rgba(80,20,20,0.92)',
                        });
                    }
                }
                return;
            }

            const agent = state.agents.find((a) =>
                a.current_run?.id === latest.run_id
                || a.current_delegation_task?.id === latest.run_id,
            );

            if (!agent) return;

            let displayText = latest.text ?? '';
            if (displayText.startsWith('{') || displayText.startsWith('[')) {
                try {
                    const parsed = JSON.parse(displayText);
                    displayText = parsed?.delta?.text
                        ?? parsed?.event?.delta?.text
                        ?? parsed?.result?.text
                        ?? parsed?.message?.content?.[0]?.text
                        ?? parsed?.text
                        ?? parsed?.message
                        ?? parsed?.content
                        ?? '';
                    if (typeof displayText !== 'string') displayText = '';
                } catch { displayText = ''; }
            }
            // Still looks like JSON/machine data after extraction — suppress it
            if (displayText.startsWith('{') || displayText.startsWith('[') || displayText.startsWith('"type"')) {
                return;
            }
            if (!displayText || displayText.length < 3) return;

            const group = avatarApi.getAvatarGroup(agent.id);
            if (!group) return;

            speechBubbles.show(agent.id, displayText, group, {
                duration: 5,
                color: '#c4d5ff',
                bgColor: 'rgba(20,30,60,0.92)',
                thought: true,
            });

            agentThoughts.value[agent.id] = {
                status: agent.status,
                text: `${agent.name} — ${displayText}`,
            };
        }, { deep: true });

        window.addEventListener('resize', sceneApi.resize);
        document.addEventListener('fullscreenchange', onFullscreenChange);
    } catch (e) {
        webglUnavailable.value = true;
        error.value = e?.message ?? 'WebGL unavailable';
    } finally {
        loading.value = false;
    }
});

onUnmounted(() => {
    window.removeEventListener('resize', sceneApi?.resize);
    document.removeEventListener('fullscreenchange', onFullscreenChange);
    interactionApi?.detach();
    envApi?.dispose();
    particles?.dispose();
    speechBubbles?.dispose();
    ambientAnims?.dispose();
    realtime.stop();
    avatarApi?.dispose();
    sceneApi?.stop();
});
</script>

<template>
    <AppLayout title="Agent Office">
        <Head title="Agent Office" />

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
                    <h2 class="text-base font-semibold text-foreground truncate">
                        Agent Office
                    </h2>
                </div>
                <div class="flex items-center gap-2">
                    <div v-if="agents.length > 0" class="flex items-center gap-1.5 text-xs text-muted-foreground">
                        <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse" />
                        {{ agents.length }} agent{{ agents.length !== 1 ? 's' : '' }}
                    </div>
                </div>
            </div>
        </template>

        <div
            ref="wrapperRef"
            :class="isFullscreen ? 'relative h-screen w-screen bg-[#0a0e1a]' : 'relative h-[calc(100vh-8rem)] w-full px-4 py-4 sm:px-6 lg:px-8'"
        >
            <div
                v-if="webglUnavailable || error"
                class="flex flex-col items-center justify-center gap-4 rounded-lg border border-border bg-muted/30 p-8 text-center h-full"
            >
                <AlertTriangle class="h-12 w-12 text-amber-500" />
                <p class="text-sm text-muted-foreground">
                    3D view unavailable. {{ error || 'WebGL may not be supported on this device.' }}
                </p>
            </div>
            <template v-else>
                <div
                    ref="containerRef"
                    class="h-full w-full min-h-[320px] rounded-lg border border-border bg-[#0a0e1a] focus:outline-none"
                    tabindex="0"
                />
                <div
                    v-if="loading"
                    class="absolute inset-0 flex items-center justify-center rounded-lg bg-[#0a0e1a]/90"
                >
                    <div class="flex flex-col items-center gap-3">
                        <div class="h-8 w-8 animate-spin rounded-full border-2 border-primary/30 border-t-primary" />
                        <p class="text-sm text-muted-foreground">Constructing office…</p>
                    </div>
                </div>
                <button
                    v-if="!loading"
                    @click="toggleFullscreen"
                    class="absolute top-6 right-6 z-10 flex items-center justify-center h-9 w-9 rounded-lg border border-border/50 bg-background/80 backdrop-blur-sm shadow-lg text-muted-foreground hover:text-foreground hover:bg-background/90 transition-colors"
                    :title="isFullscreen ? 'Exit fullscreen' : 'Fullscreen'"
                >
                    <component :is="isFullscreen ? Minimize2 : Maximize2" class="h-4 w-4" />
                </button>
                <div
                    v-if="weatherInfo && !loading"
                    class="absolute top-6 left-6 rounded-xl border border-border/50 bg-background/80 backdrop-blur-sm px-4 py-3 shadow-lg select-none pointer-events-none"
                >
                    <div class="flex items-center gap-3">
                        <component
                            :is="({
                                clear: weatherInfo.isDay ? Sun : Moon,
                                cloudy: weatherInfo.isDay ? CloudSun : Cloud,
                                fog: CloudFog,
                                rain: CloudRain,
                                showers: CloudRain,
                                snow: CloudSnow,
                                thunderstorm: CloudLightning,
                            })[weatherInfo.weatherClass] || Sun"
                            class="h-7 w-7 shrink-0"
                            :class="{
                                'text-amber-400': weatherInfo.weatherClass === 'clear' && weatherInfo.isDay,
                                'text-indigo-300': weatherInfo.weatherClass === 'clear' && !weatherInfo.isDay,
                                'text-slate-400': weatherInfo.weatherClass === 'cloudy' || weatherInfo.weatherClass === 'fog',
                                'text-blue-400': weatherInfo.weatherClass === 'rain' || weatherInfo.weatherClass === 'showers',
                                'text-white': weatherInfo.weatherClass === 'snow',
                                'text-yellow-300': weatherInfo.weatherClass === 'thunderstorm',
                            }"
                        />
                        <div class="flex flex-col gap-0.5">
                            <div class="flex items-baseline gap-1.5">
                                <span class="text-base font-semibold text-foreground tabular-nums">
                                    {{ Math.round(weatherInfo.temperature) }}°C
                                </span>
                                <span class="text-xs text-muted-foreground">
                                    {{ WEATHER_LABELS[weatherInfo.weatherClass] || 'Clear' }}
                                </span>
                            </div>
                            <div class="flex items-center gap-1 text-[10px] text-muted-foreground/70">
                                <Wind class="h-2.5 w-2.5" />
                                <span>{{ Math.round(weatherInfo.windSpeed) }} km/h</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div
                    v-if="agents.length > 0 && !loading"
                    class="absolute bottom-6 left-6 right-6 flex items-center justify-between rounded-xl border border-border/50 bg-background/80 backdrop-blur-sm px-4 py-3 shadow-lg"
                >
                    <div class="flex flex-wrap gap-2">
                        <div
                            v-for="(thought, id) in agentThoughts"
                            :key="id"
                            class="flex items-center gap-2 rounded-lg bg-muted/50 px-3 py-1.5"
                        >
                            <span
                                class="h-2 w-2 rounded-full"
                                :class="{
                                    'bg-emerald-500': thought.status === 'running',
                                    'bg-amber-500': thought.status === 'waiting',
                                    'bg-red-500': thought.status === 'failed',
                                    'bg-blue-400': thought.status === 'idle',
                                }"
                            />
                            <span class="text-xs text-muted-foreground whitespace-nowrap">{{ thought.text }}</span>
                        </div>
                    </div>
                    <div class="hidden sm:flex items-center gap-3 ml-4 shrink-0">
                        <div class="flex items-center gap-1 rounded-lg border border-border/50 bg-muted/30 p-0.5">
                            <button
                                @click="envOverride = null"
                                class="flex items-center gap-1 rounded-md px-2 py-1 text-[10px] font-medium transition-colors"
                                :class="envOverride === null ? 'bg-primary/20 text-primary' : 'text-muted-foreground hover:text-foreground'"
                            >
                                <MonitorCog class="h-3 w-3" />
                                Auto
                            </button>
                            <button
                                @click="envOverride = 'day'"
                                class="flex items-center gap-1 rounded-md px-2 py-1 text-[10px] font-medium transition-colors"
                                :class="envOverride === 'day' ? 'bg-amber-500/20 text-amber-400' : 'text-muted-foreground hover:text-foreground'"
                            >
                                <Sun class="h-3 w-3" />
                                Day
                            </button>
                            <button
                                @click="envOverride = 'night'"
                                class="flex items-center gap-1 rounded-md px-2 py-1 text-[10px] font-medium transition-colors"
                                :class="envOverride === 'night' ? 'bg-indigo-500/20 text-indigo-400' : 'text-muted-foreground hover:text-foreground'"
                            >
                                <Moon class="h-3 w-3" />
                                Night
                            </button>
                        </div>
                        <span class="text-border">|</span>
                        <div class="flex items-center gap-1.5 text-xs text-muted-foreground/70">
                            <kbd class="rounded border border-border/60 bg-muted/40 px-1 py-0.5 font-mono text-[10px]">W</kbd>
                            <kbd class="rounded border border-border/60 bg-muted/40 px-1 py-0.5 font-mono text-[10px]">A</kbd>
                            <kbd class="rounded border border-border/60 bg-muted/40 px-1 py-0.5 font-mono text-[10px]">S</kbd>
                            <kbd class="rounded border border-border/60 bg-muted/40 px-1 py-0.5 font-mono text-[10px]">D</kbd>
                            <span class="ml-0.5">Move</span>
                            <span class="mx-1 text-border">|</span>
                            <kbd class="rounded border border-border/60 bg-muted/40 px-1 py-0.5 font-mono text-[10px]">Q</kbd>
                            <kbd class="rounded border border-border/60 bg-muted/40 px-1 py-0.5 font-mono text-[10px]">E</kbd>
                            <span class="ml-0.5">Height</span>
                        </div>
                    </div>
                </div>
                <OfficeDetailPanel
                    :visible="panelVisible"
                    :data="panelData"
                    :office-state="realtime.officeState.value"
                    @close="panelVisible = false; panelData = null"
                />
            </template>
        </div>
    </AppLayout>
</template>
