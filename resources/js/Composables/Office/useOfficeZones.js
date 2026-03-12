import { CanvasTexture, Color } from 'three';

const STATUS_COLORS = {
    running: new Color(0x10b981),
    idle: new Color(0x3b82f6),
    waiting: new Color(0xf59e0b),
    failed: new Color(0xef4444),
    succeeded: new Color(0x22c55e),
    queued: new Color(0x6366f1),
    starting: new Color(0x06b6d4),
};

function findMeshByType(scene, type) {
    const results = [];
    scene.traverse((obj) => {
        if (obj.userData?.type === type) results.push(obj);
    });
    return results;
}

function updateDeskLamp(workstation, status) {
    const color = STATUS_COLORS[status] || STATUS_COLORS.idle;
    workstation.traverse((child) => {
        if (child.isMesh && child.material?.emissive) {
            const geom = child.geometry?.parameters;
            if (geom && geom.width < 0.5 && geom.height < 0.4 && geom.depth < 0.02
                && !(geom.depth < 0.01 && geom.width > 0.3)) {
                child.material.emissive.copy(color);
                child.material.emissiveIntensity = status === 'running' ? 0.5 : 0.2;
            }
        }
    });
}

const monitorCanvases = new Map();
const MONITOR_CANVAS_W = 128;
const MONITOR_CANVAS_H = 96;

function getOrCreateMonitorCanvas(workstationIndex) {
    if (monitorCanvases.has(workstationIndex)) return monitorCanvases.get(workstationIndex);
    const canvas = document.createElement('canvas');
    canvas.width = MONITOR_CANVAS_W;
    canvas.height = MONITOR_CANVAS_H;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#000000';
    ctx.fillRect(0, 0, MONITOR_CANVAS_W, MONITOR_CANVAS_H);
    const texture = new CanvasTexture(canvas);
    monitorCanvases.set(workstationIndex, { canvas, ctx, texture });
    return { canvas, ctx, texture };
}

function animateCodeCanvas(ctx, canvas, activity) {
    // Scroll existing content up; faster for debugging/testing
    const scrollSpeed = (activity === 'debugging' || activity === 'testing') ? 3 : 2;
    const imgData = ctx.getImageData(0, scrollSpeed, canvas.width, canvas.height - scrollSpeed);
    ctx.putImageData(imgData, 0, 0);

    // Clear new rows with dark background
    ctx.fillStyle = '#0d1117';
    ctx.fillRect(0, canvas.height - scrollSpeed, canvas.width, scrollSpeed);

    // Activity-specific colour palettes
    const palettes = {
        reviewing: ['#88aaff', '#66ccff', '#aaccff', '#ff8866'],
        analyzing: ['#88aaff', '#66ccff', '#aaccff', '#ffcc44'],
        compiling_report: ['#ffaa44', '#ffcc66', '#ffffff', '#88ccff'],
        debugging: ['#ff6666', '#ff8844', '#ffcc44', '#88ff88'],
        testing: ['#66dd88', '#44cc66', '#ffcc44', '#ff6666'],
        default: ['#66dd88', '#88aaff', '#aaddaa', '#dd8844'],
    };
    const palette = palettes[activity] || palettes.default;

    // Generate 2-3 code lines per frame (thicker for visibility at 3D scale)
    const lineCount = 1 + Math.floor(Math.random() * 2);
    for (let line = 0; line < lineCount; line++) {
        const y = canvas.height - scrollSpeed + line;
        if (y >= canvas.height) break;

        if (Math.random() < 0.8) {
            const indent = Math.floor(Math.random() * 4) * 10;
            const lineLen = 15 + Math.floor(Math.random() * 55);
            ctx.fillStyle = palette[Math.floor(Math.random() * palette.length)];
            // Draw 2px tall lines for better visibility
            ctx.fillRect(indent, y, lineLen, 2);

            // Secondary token
            if (Math.random() < 0.5) {
                ctx.fillStyle = palette[Math.floor(Math.random() * palette.length)];
                ctx.fillRect(indent + lineLen + 5, y, 12 + Math.floor(Math.random() * 30), 2);
            }

            // Occasional cursor blink
            if (Math.random() < 0.2) {
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(indent + lineLen + 2, y, 3, 2);
            }
        }
    }
}

function updateMonitorScreen(workstation, activity, _time) {
    const wsIndex = workstation.userData?.workstationIndex;

    workstation.traverse((child) => {
        if (child.isMesh && child.material?.emissive) {
            const geom = child.geometry?.parameters;
            if (geom && geom.depth < 0.01 && geom.width > 0.3) {
                const screenActiveActivities = [
                    'writing_code', 'analyzing', 'reviewing', 'compiling_report',
                    'testing', 'refactoring', 'debugging', 'planning',
                    'working', 'executing_job', 'running', 'finishing',
                    'reading', 'chatting',
                ];
                if (screenActiveActivities.includes(activity) && wsIndex !== undefined) {
                    const mc = getOrCreateMonitorCanvas(wsIndex);
                    animateCodeCanvas(mc.ctx, mc.canvas, activity);
                    mc.texture.needsUpdate = true;
                    child.material.map = mc.texture;
                    // Set base color to white so texture colors render unmodified
                    // (PBR multiplies map × color, so dark base color makes texture invisible)
                    child.material.color.setHex(0xffffff);
                    const screenColor = activity === 'reviewing' || activity === 'analyzing'
                        ? new Color(0x0088ff)
                        : activity === 'compiling_report'
                            ? new Color(0xff8800)
                            : activity === 'debugging'
                                ? new Color(0xff4444)
                                : new Color(0x00ff44);
                    child.material.emissive.copy(screenColor);
                    child.material.emissiveIntensity = 0.15;
                } else {
                    child.material.map = null;
                    // Restore dark base color when no texture
                    child.material.color.setHex(0x001a0a);
                    const color = activity === 'waiting'
                        ? new Color(0xffaa00)
                        : new Color(0x003322);
                    child.material.emissive.copy(color);
                    child.material.emissiveIntensity = activity === 'idle' ? 0.05 : 0.4;
                }
                child.material.needsUpdate = true;
            }
        }
    });
}

function updateServerRackLeds(scene, systemState, time) {
    const racks = findMeshByType(scene, 'serverRack');
    racks.forEach((rack) => {
        rack.traverse((child) => {
            if (child.isMesh && child.material?.emissive) {
                const isLed = child.geometry?.type === 'SphereGeometry';
                if (isLed) {
                    if (systemState.scheduler_healthy) {
                        child.material.emissiveIntensity = 0.5 + Math.sin(time * 4 + child.position.y * 10) * 0.3;
                    } else {
                        child.material.emissive.setHex(0xff0000);
                        child.material.emissiveIntensity = Math.sin(time * 8) > 0 ? 0.8 : 0.1;
                    }
                }
            }
        });
    });
}

function updateTrafficLight(scene, systemState) {
    const lights = findMeshByType(scene, 'trafficLight');
    lights.forEach((tl) => {
        let ledIndex = 0;
        tl.traverse((child) => {
            if (child.isMesh && child.geometry?.type === 'SphereGeometry') {
                const isRed = ledIndex === 0;
                const isAmber = ledIndex === 1;
                const isGreen = ledIndex === 2;

                if (systemState.rate_limited) {
                    child.material.emissiveIntensity = isRed ? 0.8 : 0.05;
                } else if (systemState.active_runs > 3) {
                    child.material.emissiveIntensity = isAmber ? 0.8 : 0.05;
                } else {
                    child.material.emissiveIntensity = isGreen ? 0.8 : 0.05;
                }
                ledIndex++;
            }
        });
    });
}

function updateScreenWall(scene, systemState, time) {
    const walls = findMeshByType(scene, 'screenWall');
    walls.forEach((wall) => {
        wall.traverse((child) => {
            if (child.isMesh && child.material?.emissive) {
                const geom = child.geometry?.parameters;
                if (geom && geom.depth < 0.01) {
                    const intensity = systemState.active_runs > 0
                        ? 0.3 + Math.sin(time * 2 + child.position.x) * 0.1
                        : 0.1;
                    child.material.emissiveIntensity = intensity;
                    if (systemState.active_runs > 0) {
                        child.material.emissive.setHex(0x003355);
                    } else {
                        child.material.emissive.setHex(0x001122);
                    }
                }
            }
        });
    });
}

function updateBarrier(scene, systemState) {
    const barriers = findMeshByType(scene, 'barrier');
    barriers.forEach((barrier) => {
        barrier.traverse((child) => {
            if (child.isMesh && child.geometry?.type === 'SphereGeometry') {
                const mode = systemState.runtime_mode;
                const color = mode === 'full'
                    ? 0x00ff00
                    : mode === 'standard'
                        ? 0xffaa00
                        : 0xff0000;
                child.material.emissive.setHex(color);
                child.material.emissiveIntensity = 0.6;
            }
        });
    });
}

function updateBookshelfFill(scene, memoryState) {
    const shelves = findMeshByType(scene, 'bookshelf');
    shelves.forEach((shelf) => {
        if (memoryState.recent_formations > 0) {
            shelf.traverse((child) => {
                if (child.isMesh && child.material?.emissive) {
                    child.material.emissiveIntensity = 0.15;
                }
            });
        }
    });
}

function updateAlarmBell(scene, systemState, time) {
    const alarms = findMeshByType(scene, 'alarmBell');
    const hasEscalation = systemState.rate_limited || systemState.active_runs > 5;
    alarms.forEach((alarm) => {
        alarm.traverse((child) => {
            if (child.isMesh && child.material?.emissive) {
                if (hasEscalation) {
                    child.material.emissiveIntensity = 0.4 + Math.sin(time * 6) * 0.4;
                } else {
                    child.material.emissiveIntensity = 0.1;
                }
            }
        });
    });
}

function updateMailStations(scene, messengerState) {
    const stations = findMeshByType(scene, 'mailStation');
    const channels = messengerState?.channels ?? [];
    stations.forEach((station, i) => {
        const channel = channels[i];
        station.traverse((child) => {
            if (child.isMesh && child.material?.emissive) {
                if (channel?.status === 'connected') {
                    child.material.emissiveIntensity = 0.3;
                } else {
                    child.material.emissiveIntensity = 0.02;
                }
            }
        });
    });
}

function updateConferenceRoom(scene, delegationState) {
    const whiteboards = findMeshByType(scene, 'whiteboard');
    const active = delegationState.active_graphs > 0;
    whiteboards.forEach((wb) => {
        wb.traverse((child) => {
            if (child.isMesh) {
                const geom = child.geometry?.parameters;
                if (geom && geom.depth < 0.02 && child.material?.color) {
                    if (active) {
                        child.material.emissive = child.material.emissive || new Color();
                        child.material.emissive.setHex(0xffffff);
                        child.material.emissiveIntensity = 0.15;
                    } else {
                        if (child.material.emissive) {
                            child.material.emissiveIntensity = 0;
                        }
                    }
                }
            }
        });
    });
}

function updateToolBenches(scene, agentStates) {
    const benches = findMeshByType(scene, 'toolBench');
    const activeTools = new Set();
    (agentStates ?? []).forEach((a) => {
        (a.tools_active ?? []).forEach((t) => activeTools.add(t));
    });

    benches.forEach((bench) => {
        const toolType = bench.userData?.toolType;
        const isActive = activeTools.has(toolType);
        bench.traverse((child) => {
            if (child.isMesh && child.material?.emissive) {
                child.material.emissiveIntensity = isActive ? 0.5 : 0.1;
            }
        });
    });
}

export function useOfficeZones(scene, officeState, avatarApi) {
    function updateZones(time) {
        const state = officeState.value;
        if (!state || !scene) return;

        const agentStates = state.agents ?? [];
        const system = state.system ?? {};
        const delegation = state.delegation ?? {};
        const messenger = state.messenger ?? {};
        const memory = state.memory ?? {};

        const workstations = findMeshByType(scene, 'workstation');
        workstations.forEach((ws) => {
            const wsIdx = ws.userData?.workstationIndex;
            if (wsIdx === undefined) return;
            const agent = agentStates[wsIdx];
            if (agent) {
                updateDeskLamp(ws, agent.status);
                updateMonitorScreen(ws, agent.current_activity, time);
            } else {
                updateDeskLamp(ws, 'idle');
                updateMonitorScreen(ws, 'idle', time);
            }
        });

        // Server Room
        updateServerRackLeds(scene, system, time);
        updateTrafficLight(scene, system);

        // War Room
        updateScreenWall(scene, system, time);

        // Security Desk
        updateBarrier(scene, system);

        // Archives
        updateBookshelfFill(scene, memory);

        // Escalation
        updateAlarmBell(scene, system, time);

        // Mailroom
        updateMailStations(scene, messenger);

        // Conference
        updateConferenceRoom(scene, delegation);

        // Tool Workshop
        updateToolBenches(scene, agentStates);

        if (avatarApi) {
            agentStates.forEach((agent) => {
                const isBusy = agent.current_activity !== 'idle';
                const inNonDeskZone = agent.zone && agent.zone !== 'workstation' && agent.zone !== 'workstations';

                if (isBusy && !inNonDeskZone) {
                    avatarApi.setAgentBusy(agent.id, true);
                } else if (!isBusy) {
                    avatarApi.setAgentBusy(agent.id, false);
                }

                if (isBusy) {
                    const stateMap = {
                        writing_code: 'typing',
                        analyzing: 'reading',
                        reviewing: 'reading',
                        compiling_report: 'typing',
                        testing: 'typing',
                        refactoring: 'typing',
                        debugging: 'reading',
                        planning: 'reading',
                        working: 'typing',
                        executing_job: 'typing',
                        reading: 'reading',
                        waiting: 'waiting',
                        finishing: 'typing',
                        chatting: 'chatting',
                    };
                    const avatarState = stateMap[agent.current_activity] || 'typing';
                    avatarApi.setAgentState(agent.id, avatarState);
                }
            });
        }
    }

    return {
        updateZones,
    };
}
