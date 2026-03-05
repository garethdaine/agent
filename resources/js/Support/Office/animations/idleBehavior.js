import { AVATAR_STATES } from './avatarStateMachine.js';

const WANDER_ZONES = [
    { zoneId: 'breakRoom',    activity: AVATAR_STATES.CHATTING, weight: 3, minStay: 6, maxStay: 15 },
    { zoneId: 'conference',   activity: AVATAR_STATES.CHATTING, weight: 2, minStay: 5, maxStay: 12 },
    { zoneId: 'mailroom',     activity: AVATAR_STATES.READING,  weight: 2, minStay: 4, maxStay: 8 },
    { zoneId: 'vault',        activity: AVATAR_STATES.READING,  weight: 1, minStay: 5, maxStay: 10 },
    { zoneId: 'toolWorkshop', activity: AVATAR_STATES.TYPING,   weight: 1, minStay: 4, maxStay: 8 },
    { zoneId: 'warRoom',      activity: AVATAR_STATES.READING,  weight: 1, minStay: 3, maxStay: 7 },
];

const WORKSTATION_IDLE_MIN = 8;
const WORKSTATION_IDLE_MAX = 25;

function rand(min, max) {
    return min + Math.random() * (max - min);
}

function pickWeightedZone(exclude) {
    const candidates = WANDER_ZONES.filter((z) => z.zoneId !== exclude);
    const totalWeight = candidates.reduce((sum, z) => sum + z.weight, 0);
    let roll = Math.random() * totalWeight;
    for (const zone of candidates) {
        roll -= zone.weight;
        if (roll <= 0) return zone;
    }
    return candidates[candidates.length - 1];
}

export class IdleBehaviorScheduler {
    constructor() {
        this._agents = new Map();
    }

    register(agentId, homePosition) {
        this._agents.set(agentId, {
            agentId,
            home: { x: homePosition.x, z: homePosition.z },
            phase: 'at_desk',
            timer: rand(2, WORKSTATION_IDLE_MAX),
            currentZone: null,
            busy: false,
        });
    }

    unregister(agentId) {
        this._agents.delete(agentId);
    }

    setBusy(agentId, busy) {
        const entry = this._agents.get(agentId);
        if (!entry) return;
        const wasBusy = entry.busy;
        entry.busy = busy;

        if (busy && !wasBusy) {
            entry.phase = 'returning';
            entry.timer = 0;
            entry.currentZone = null;
        } else if (!busy && wasBusy) {
            entry.phase = 'at_desk';
            entry.timer = rand(WORKSTATION_IDLE_MIN, WORKSTATION_IDLE_MAX);
        }
    }

    tick(delta, avatarApi) {
        this._agents.forEach((entry) => {
            if (entry.busy) return;

            entry.timer -= delta;
            if (entry.timer > 0) return;

            switch (entry.phase) {
                case 'at_desk': {
                    const dest = pickWeightedZone(entry.currentZone);
                    entry.currentZone = dest.zoneId;
                    entry.phase = 'walking_to';
                    entry.pendingActivity = dest.activity;
                    entry.pendingStay = rand(dest.minStay, dest.maxStay);
                    entry.timer = 999;

                    const animator = avatarApi._getAnimator(entry.agentId);
                    if (animator) {
                        animator.onArrive = () => {
                            animator.onArrive = null;
                            entry.phase = 'at_zone';
                            entry.timer = entry.pendingStay;
                            avatarApi.setAgentState(entry.agentId, entry.pendingActivity);
                        };
                    }
                    avatarApi.moveAgentToZone(entry.agentId, dest.zoneId);
                    break;
                }

                case 'at_zone': {
                    const goHome = Math.random() < 0.5;
                    if (goHome) {
                        entry.phase = 'returning';
                        entry.timer = 999;
                        const animator = avatarApi._getAnimator(entry.agentId);
                        if (animator) {
                            animator.onArrive = () => {
                                animator.onArrive = null;
                                entry.phase = 'at_desk';
                                entry.timer = rand(WORKSTATION_IDLE_MIN, WORKSTATION_IDLE_MAX);
                                entry.currentZone = null;
                                avatarApi.setAgentState(entry.agentId, AVATAR_STATES.IDLE);
                            };
                        }
                        avatarApi.moveAgentToPosition(entry.agentId, entry.home);
                    } else {
                        const dest = pickWeightedZone(entry.currentZone);
                        entry.currentZone = dest.zoneId;
                        entry.phase = 'walking_to';
                        entry.pendingActivity = dest.activity;
                        entry.pendingStay = rand(dest.minStay, dest.maxStay);
                        entry.timer = 999;

                        const animator = avatarApi._getAnimator(entry.agentId);
                        if (animator) {
                            animator.onArrive = () => {
                                animator.onArrive = null;
                                entry.phase = 'at_zone';
                                entry.timer = entry.pendingStay;
                                avatarApi.setAgentState(entry.agentId, entry.pendingActivity);
                            };
                        }
                        avatarApi.moveAgentToZone(entry.agentId, dest.zoneId);
                    }
                    break;
                }

                case 'walking_to':
                case 'returning':
                    break;
            }
        });
    }

    forceReturn(agentId, avatarApi) {
        const entry = this._agents.get(agentId);
        if (!entry) return;

        const animator = avatarApi._getAnimator(agentId);
        if (animator) {
            animator.onArrive = () => {
                animator.onArrive = null;
                entry.phase = 'at_desk';
                entry.currentZone = null;
            };
        }
        entry.phase = 'returning';
        entry.timer = 999;
        avatarApi.moveAgentToPosition(agentId, entry.home);
    }

    dispose() {
        this._agents.clear();
    }
}
