import { Group, BoxGeometry, CylinderGeometry, MeshStandardMaterial, Mesh } from 'three';

const COLORS = [0x3b82f6, 0x10b981, 0xf59e0b, 0x8b5cf6, 0xec4899];

/**
 * Create a simple 3D avatar (capsule-like: box body + small head) for an agent.
 *
 * @param {object} options
 * @param {string} [options.id]
 * @param {string} [options.name]
 * @param {number} [options.colorIndex]
 * @returns {Group}
 */
export function createAgentAvatar({ id = '', name = '', colorIndex = 0 } = {}) {
    const group = new Group();
    group.userData = { id, name };

    const color = COLORS[colorIndex % COLORS.length];
    const bodyGeom = new BoxGeometry(0.4, 0.6, 0.25);
    const bodyMat = new MeshStandardMaterial({ color });
    const body = new Mesh(bodyGeom, bodyMat);
    body.position.y = 0.35;
    body.castShadow = true;
    group.add(body);

    const headGeom = new CylinderGeometry(0.15, 0.15, 0.2, 8);
    const headMat = new MeshStandardMaterial({ color });
    const head = new Mesh(headGeom, headMat);
    head.position.y = 0.85;
    head.castShadow = true;
    group.add(head);

    return group;
}
