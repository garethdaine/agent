import { ref, watch } from 'vue';
import { MeshStandardMaterial, CanvasTexture, Color } from 'three';

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

function findMeshByZoneId(scene, zoneId) {
    const results = [];
    scene.traverse((obj) => {
        if (obj.userData?.zoneId === zoneId) results.push(obj);
    });
    return results;
}

function updateDeskLamp(workstation, status) {
    const color = STATUS_COLORS[status] || STATUS_COLORS.idle;
    workstation.traverse((child) => {
        if (child.isMesh && child.material?.emissive) {
            const geom = child.geometry?.parameters;
            if (geom && geom.width < 0.5 && geom.height < 0.4 && geom.depth < 0.02) {
                child.material.emissive.copy(color);
                child.material.emissiveIntensity = status === 'running' ? 0.5 : 0.2;
            }
        }
    });
}

function updateMonitorScreen(workstation, activity, time) {
    workstation.traverse((child) => {
        if (child.isMesh && child.material?.emissive) {
            const geom = child.geometry?.parameters;
            if (geom && geom.depth < 0.01 && geom.width > 0.3) {
                const color = activity === 'writing_code'
                    ? new Color(0x00ff44)
                    : activity === 'waiting'
                        ? new Color(0xffaa00)
                        : new Color(0x003322);
                child.material.emissive.copy(color);
                child.material.emissiveIntensity = activity === 'idle' ? 0.1 : 0.4;
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
    let lastAnimTime = 0;

    function updateZones(time) {
        lastAnimTime = time;
        const state = officeState.value;
        if (!state || !scene) return;

        const agentStates = state.agents ?? [];
        const system = state.system ?? {};
        const delegation = state.delegation ?? {};
        const messenger = state.messenger ?? {};
        const memory = state.memory ?? {};

        // Workstations
        const workstations = findMeshByType(scene, 'workstation');
        workstations.forEach((ws, i) => {
            const agent = agentStates[i];
            if (agent) {
                updateDeskLamp(ws, agent.status);
                updateMonitorScreen(ws, agent.current_activity, time);
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
                avatarApi.setAgentBusy(agent.id, isBusy);

                if (isBusy) {
                    const stateMap = {
                        writing_code: 'typing',
                        reading: 'reading',
                        waiting: 'waiting',
                        finishing: 'typing',
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
