import { ref } from 'vue';
import {
    Group, Mesh, BoxGeometry, SphereGeometry, CylinderGeometry,
    MeshStandardMaterial, CanvasTexture, SpriteMaterial, Sprite,
} from 'three';
import { AvatarAnimator, AVATAR_STATES } from '@/Support/Office/animations/avatarStateMachine.js';
import { findPath, getZoneEntryPoint } from '@/Support/Office/animations/pathfinding.js';
import { IdleBehaviorScheduler } from '@/Support/Office/animations/idleBehavior.js';

const AGENT_COLORS = [
    0x3b82f6, 0x10b981, 0xf59e0b, 0x8b5cf6, 0xec4899,
    0x06b6d4, 0xf97316, 0x84cc16, 0xe11d48,
];

function createImprovedAvatar(agent, colorIndex) {
    const group = new Group();
    const color = AGENT_COLORS[colorIndex % AGENT_COLORS.length];
    const bodyMat = new MeshStandardMaterial({ color, roughness: 0.6, metalness: 0.1 });
    const skinMat = new MeshStandardMaterial({ color: 0xddb892, roughness: 0.8 });
    const darkMat = new MeshStandardMaterial({ color: 0x333333 });

    const body = new Mesh(new BoxGeometry(0.35, 0.5, 0.2), bodyMat);
    body.position.y = 0.55;
    body.castShadow = true;
    group.add(body);

    const head = new Mesh(new SphereGeometry(0.14, 12, 8), skinMat);
    head.position.y = 0.95;
    head.castShadow = true;
    group.add(head);

    const leftArm = new Mesh(new BoxGeometry(0.08, 0.4, 0.08), bodyMat);
    leftArm.position.set(-0.24, 0.55, 0);
    leftArm.castShadow = true;
    group.add(leftArm);

    const rightArm = new Mesh(new BoxGeometry(0.08, 0.4, 0.08), bodyMat);
    rightArm.position.set(0.24, 0.55, 0);
    rightArm.castShadow = true;
    group.add(rightArm);

    const leftLeg = new Mesh(new BoxGeometry(0.1, 0.3, 0.1), darkMat);
    leftLeg.position.set(-0.1, 0.15, 0);
    leftLeg.castShadow = true;
    group.add(leftLeg);

    const rightLeg = new Mesh(new BoxGeometry(0.1, 0.3, 0.1), darkMat);
    rightLeg.position.set(0.1, 0.15, 0);
    rightLeg.castShadow = true;
    group.add(rightLeg);

    group.userData = {
        agentId: agent.id,
        agentName: agent.name,
        interactive: true,
        type: 'agent',
    };

    return group;
}

function createNameplate(name) {
    const canvas = document.createElement('canvas');
    canvas.width = 256;
    canvas.height = 64;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = 'rgba(15,20,40,0.85)';
    const r = 10;
    ctx.beginPath();
    ctx.roundRect(4, 4, 248, 56, r);
    ctx.fill();
    ctx.strokeStyle = 'rgba(100,140,255,0.4)';
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.roundRect(4, 4, 248, 56, r);
    ctx.stroke();
    ctx.font = 'bold 22px system-ui, sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillStyle = '#e0e8ff';
    ctx.fillText(name, 128, 32);

    const texture = new CanvasTexture(canvas);
    const spriteMat = new SpriteMaterial({ map: texture, depthTest: false });
    const sprite = new Sprite(spriteMat);
    sprite.scale.set(1.2, 0.3, 1);
    sprite.position.y = 1.3;
    return sprite;
}

export function useAgentAvatars(scene) {
    const avatars = ref(new Map());
    const animators = new Map();
    const idleScheduler = new IdleBehaviorScheduler();

    function addAgent(agent, position, colorIndex) {
        const group = createImprovedAvatar(agent, colorIndex);
        group.position.set(position.x, 0, position.z);

        const nameplate = createNameplate(agent.name || `Agent ${agent.id}`);
        group.add(nameplate);

        scene.add(group);
        avatars.value.set(agent.id, group);

        const animator = new AvatarAnimator(group);
        animators.set(agent.id, animator);

        idleScheduler.register(agent.id, position);

        return { group, animator };
    }

    function removeAgent(agentId) {
        const group = avatars.value.get(agentId);
        if (group) {
            scene.remove(group);
            avatars.value.delete(agentId);
            animators.delete(agentId);
            idleScheduler.unregister(agentId);
        }
    }

    function setAgentState(agentId, state) {
        const animator = animators.get(agentId);
        if (animator) animator.setState(state);
    }

    function moveAgentToZone(agentId, zoneId) {
        const animator = animators.get(agentId);
        const group = avatars.value.get(agentId);
        if (!animator || !group) return;

        const target = getZoneEntryPoint(zoneId);
        const from = { x: group.position.x, z: group.position.z };
        const path = findPath(from, target);
        animator.walkAlongPath(path);
    }

    function moveAgentToPosition(agentId, targetPos) {
        const animator = animators.get(agentId);
        const group = avatars.value.get(agentId);
        if (!animator || !group) return;

        const from = { x: group.position.x, z: group.position.z };
        const path = findPath(from, targetPos);
        animator.walkAlongPath(path);
    }

    function updateAll(delta) {
        animators.forEach((animator) => animator.update(delta));
        idleScheduler.tick(delta, api);
    }

    function getAvatarGroup(agentId) {
        return avatars.value.get(agentId);
    }

    function setAgentBusy(agentId, busy) {
        idleScheduler.setBusy(agentId, busy);
        if (busy) {
            idleScheduler.forceReturn(agentId, api);
        }
    }

    function _getAnimator(agentId) {
        return animators.get(agentId);
    }

    function dispose() {
        avatars.value.forEach((group) => scene.remove(group));
        avatars.value.clear();
        animators.clear();
        idleScheduler.dispose();
    }

    const api = {
        avatars,
        addAgent,
        removeAgent,
        setAgentState,
        setAgentBusy,
        moveAgentToZone,
        moveAgentToPosition,
        updateAll,
        getAvatarGroup,
        _getAnimator,
        dispose,
        AVATAR_STATES,
    };

    return api;
}
