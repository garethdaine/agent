const WAYPOINTS = {
    // Main corridor (z=-2.5) - one per desk column + edges
    mcW:  { x: -7,  z: -2.5 },
    mc6:  { x: -6,  z: -2.5 },
    mc3:  { x: -3,  z: -2.5 },
    mcC:  { x: 0,   z: -2.5 },
    mc3E: { x: 3,   z: -2.5 },
    mc6E: { x: 6,   z: -2.5 },

    // North aisle (z=-5.5, south of z=-6.5 wall)
    nW:  { x: -7, z: -5.5 },
    nC:  { x: 0,  z: -5.5 },
    nE:  { x: 6,  z: -5.5 },
    nEE: { x: 10, z: -5.5 },

    // South aisle (z=0.5) - one per desk column + edges
    sW:  { x: -7, z: 0.5 },
    s6:  { x: -6, z: 0.5 },
    s3:  { x: -3, z: 0.5 },
    sC:  { x: 0,  z: 0.5 },
    s3E: { x: 3,  z: 0.5 },
    s6E: { x: 6,  z: 0.5 },

    // Open south area
    southW: { x: -5, z: 3 },
    southC: { x: 0,  z: 3 },
    southE: { x: 5,  z: 3 },

    // Doorways at x=-8 wall (gap z=-7 to z=-6)
    doorA: { x: -8, z: -6.5 },
    // Doorway at x=-8 wall (gap z=-1 to z=1)
    doorB: { x: -8, z: 0 },
    // Doorway at z=-6.5 wall (gap at x>10)
    doorC: { x: 10.5, z: -6.5 },
    // Doorway at x=6.5 wall (gap below z=2.5)
    doorD: { x: 6.5, z: 2 },
    // Doorway at x=6.5 wall (gap above z=8)
    doorE: { x: 6.5, z: 8.5 },

    // West corridor (west of x=-8 wall)
    westN: { x: -10, z: -6.5 },
    westC: { x: -10, z: 0 },
    westS: { x: -10, z: 5.5 },

    // East corridor (east of x=6.5 wall)
    eastN: { x: 8, z: 3 },
    eastC: { x: 8, z: 6 },
    eastS: { x: 8, z: 8.5 },

    // Conference approach
    confApproach: { x: -2, z: 5.5 },

    // Room interiors
    serverRoom:   { x: -10.5, z: -8.5 },
    warRoomW:     { x: -2,    z: -8.5 },
    warRoomE:     { x: 8,     z: -8.5 },
    securityDesk: { x: -11,   z: -3.5 },
    mailroom:     { x: -11,   z: 3 },
    conference:   { x: -3,    z: 8.2 },
    vault:        { x: 9,     z: 6 },
    toolWorkshop: { x: -10,   z: 7.5 },
    breakRoom:    { x: 9,     z: 9.5 },
    escalation:   { x: -5,    z: -9 },
};

const EDGES = [
    // === Main corridor (z=-2.5) east-west ===
    ['mcW', 'mc6'],
    ['mc6', 'mc3'],
    ['mc3', 'mcC'],
    ['mcC', 'mc3E'],
    ['mc3E', 'mc6E'],

    // === North aisle (z=-5.5) east-west ===
    ['nW', 'nC'],
    ['nC', 'nE'],
    ['nE', 'nEE'],

    // === South aisle (z=0.5) east-west ===
    ['sW', 's6'],
    ['s6', 's3'],
    ['s3', 'sC'],
    ['sC', 's3E'],
    ['s3E', 's6E'],

    // === North-south links (perpendicular only, same x column) ===
    ['nW', 'mcW'],   // x=-7
    ['nC', 'mcC'],   // x=0
    ['nE', 'mc6E'],  // x=6
    ['mcW', 'sW'],   // x=-7
    ['mc6', 's6'],   // x=-6
    ['mc3', 's3'],   // x=-3
    ['mcC', 'sC'],   // x=0
    ['mc3E', 's3E'], // x=3
    ['mc6E', 's6E'], // x=6

    // === South aisle to open south area ===
    ['sW', 'southW'],
    ['sC', 'southC'],
    ['s6E', 'southE'],
    ['southW', 'southC'],
    ['southC', 'southE'],

    // === Door A: x=-8 wall, gap z=-7 to z=-6 ===
    ['nW', 'doorA'],
    ['doorA', 'westN'],

    // === Door B: x=-8 wall, gap z=-1 to z=1 ===
    ['sW', 'doorB'],
    ['doorB', 'westC'],

    // === Door C: z=-6.5 wall, gap x>10 ===
    ['nEE', 'doorC'],
    ['doorC', 'warRoomE'],

    // === Door D: x=6.5 wall, gap below z=2.5 ===
    ['southE', 'doorD'],
    ['doorD', 'eastN'],

    // === Door E: x=6.5 wall, gap above z=8 ===
    ['doorE', 'eastS'],

    // === West corridor (all west of x=-8) ===
    ['westN', 'westC'],
    ['westC', 'westS'],
    ['westN', 'serverRoom'],
    ['westC', 'securityDesk'],
    ['westC', 'mailroom'],
    ['westS', 'toolWorkshop'],

    // === East corridor (all east of x=6.5) ===
    ['eastN', 'eastC'],
    ['eastC', 'eastS'],
    ['eastC', 'vault'],
    ['eastS', 'breakRoom'],

    // === War room (north of z=-6.5 wall, accessed via doorC) ===
    ['warRoomW', 'warRoomE'],
    ['warRoomW', 'escalation'],

    // === Conference (open from south, no wall blocking) ===
    ['southW', 'confApproach'],
    ['southC', 'confApproach'],
    ['confApproach', 'conference'],

    // === Connect east door E to south area for continuity ===
    ['southE', 'doorE'],
];

let _adjacencyCache = null;

function buildAdjacency() {
    if (_adjacencyCache) return _adjacencyCache;
    const adj = {};
    Object.keys(WAYPOINTS).forEach((k) => { adj[k] = []; });
    EDGES.forEach(([a, b]) => {
        if (adj[a] && adj[b]) {
            adj[a].push(b);
            adj[b].push(a);
        }
    });
    _adjacencyCache = adj;
    return adj;
}

function wpDist(a, b) {
    const dx = WAYPOINTS[a].x - WAYPOINTS[b].x;
    const dz = WAYPOINTS[a].z - WAYPOINTS[b].z;
    return Math.sqrt(dx * dx + dz * dz);
}

function nearestWaypoint(pos) {
    let best = null;
    let bestDist = Infinity;
    Object.entries(WAYPOINTS).forEach(([key, wp]) => {
        const d = Math.sqrt((pos.x - wp.x) ** 2 + (pos.z - wp.z) ** 2);
        if (d < bestDist) {
            bestDist = d;
            best = key;
        }
    });
    return best;
}

export function findPath(fromPos, toPos) {
    const startWP = nearestWaypoint(fromPos);
    const endWP = nearestWaypoint(toPos);
    if (!startWP || !endWP) return [toPos];
    if (startWP === endWP) return [toPos];

    const adj = buildAdjacency();
    const open = [startWP];
    const closed = new Set();
    const gScore = { [startWP]: 0 };
    const fScore = { [startWP]: wpDist(startWP, endWP) };
    const cameFrom = {};

    while (open.length > 0) {
        open.sort((a, b) => (fScore[a] ?? Infinity) - (fScore[b] ?? Infinity));
        const current = open.shift();

        if (current === endWP) {
            const path = [];
            let node = current;
            while (node) {
                path.unshift(WAYPOINTS[node]);
                node = cameFrom[node];
            }
            path.push(toPos);
            return path;
        }

        closed.add(current);

        for (const neighbor of (adj[current] || [])) {
            if (closed.has(neighbor)) continue;
            const tentativeG = (gScore[current] ?? Infinity) + wpDist(current, neighbor);
            if (tentativeG < (gScore[neighbor] ?? Infinity)) {
                cameFrom[neighbor] = current;
                gScore[neighbor] = tentativeG;
                fScore[neighbor] = tentativeG + wpDist(neighbor, endWP);
                if (!open.includes(neighbor)) open.push(neighbor);
            }
        }
    }

    return [toPos];
}

export function getZoneEntryPoint(zoneId) {
    const map = {
        serverRack:   WAYPOINTS.serverRoom,
        warRoom:      WAYPOINTS.warRoomW,
        securityDesk: WAYPOINTS.securityDesk,
        workstations: WAYPOINTS.mcC,
        mailroom:     WAYPOINTS.mailroom,
        conference:   WAYPOINTS.conference,
        vault:        WAYPOINTS.vault,
        toolWorkshop: WAYPOINTS.toolWorkshop,
        breakRoom:    WAYPOINTS.breakRoom,
        escalation:   WAYPOINTS.escalation,
    };
    return map[zoneId] ?? WAYPOINTS.mcC;
}

export { WAYPOINTS };
