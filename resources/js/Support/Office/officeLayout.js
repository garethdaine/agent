/**
 * Default grid positions for agents in the 3D office (x, z).
 * Index or id can map to a desk position.
 */
const GRID_SPACING = 2.2;
const DEFAULT_POSITIONS = [
    [ -2, -2 ],
    [ 0, -2 ],
    [ 2, -2 ],
    [ -2, 0 ],
    [ 0, 0 ],
    [ 2, 0 ],
    [ -2, 2 ],
    [ 0, 2 ],
    [ 2, 2 ],
];

/**
 * @param {Array<{ id: string }>} agents
 * @returns {Map<string, { x: number, z: number }>}
 */
export function getAgentPositions(agents) {
    const map = new Map();
    (agents || []).forEach((agent, i) => {
        const pos = DEFAULT_POSITIONS[i % DEFAULT_POSITIONS.length];
        map.set(agent.id, { x: pos[0] * GRID_SPACING, z: pos[1] * GRID_SPACING });
    });
    return map;
}

export const GRID_SPACING_EXPORT = GRID_SPACING;
