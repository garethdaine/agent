import {
    Group, Mesh, BoxGeometry, CylinderGeometry, SphereGeometry,
    MeshStandardMaterial, MeshPhysicalMaterial,
} from 'three';

const mat = (color, opts = {}) => new MeshStandardMaterial({ color, ...opts });
const glassMat = (color = 0x88ccff) => new MeshPhysicalMaterial({
    color, transparent: true, opacity: 0.15, roughness: 0.1, metalness: 0.1,
});

function addBox(group, w, h, d, material, x = 0, y = 0, z = 0) {
    const m = new Mesh(new BoxGeometry(w, h, d), material);
    m.position.set(x, y, z);
    m.castShadow = true;
    m.receiveShadow = true;
    group.add(m);
    return m;
}

function addCylinder(group, rTop, rBot, h, material, x = 0, y = 0, z = 0) {
    const m = new Mesh(new CylinderGeometry(rTop, rBot, h, 12), material);
    m.position.set(x, y, z);
    m.castShadow = true;
    m.receiveShadow = true;
    group.add(m);
    return m;
}

function addSphere(group, r, material, x = 0, y = 0, z = 0) {
    const m = new Mesh(new SphereGeometry(r, 12, 8), material);
    m.position.set(x, y, z);
    m.castShadow = true;
    group.add(m);
    return m;
}

function addLegs(group, w, d, h, legMat) {
    const inset = 0.04;
    const r = 0.03;
    const positions = [
        [-w / 2 + inset, h / 2, -d / 2 + inset],
        [w / 2 - inset, h / 2, -d / 2 + inset],
        [-w / 2 + inset, h / 2, d / 2 - inset],
        [w / 2 - inset, h / 2, d / 2 - inset],
    ];
    positions.forEach(([x, y, z]) => addCylinder(group, r, r, h, legMat, x, y, z));
}

// ─── Furniture Factories ────────────────────────────────────────

export function createDesk(options = {}) {
    const { width = 1.4, depth = 0.7, color = 0x8B7355 } = options;
    const g = new Group();
    g.userData = { type: 'desk' };
    const wood = mat(color);
    const metal = mat(0x555555, { metalness: 0.6 });
    addBox(g, width, 0.04, depth, wood, 0, 0.74, 0);
    addLegs(g, width, depth, 0.72, metal);
    addBox(g, width * 0.4, 0.25, depth * 0.85, mat(0x444444), width * 0.2, 0.5, 0);
    return g;
}

export function createChair(options = {}) {
    const { color = 0x333333 } = options;
    const g = new Group();
    g.userData = { type: 'chair' };
    const fabric = mat(color);
    const metal = mat(0x666666, { metalness: 0.7 });
    addBox(g, 0.4, 0.06, 0.4, fabric, 0, 0.44, 0);
    addBox(g, 0.4, 0.4, 0.04, fabric, 0, 0.67, -0.18);
    addCylinder(g, 0.04, 0.04, 0.42, metal, 0, 0.22, 0);
    const arms = [[-0.18, 0, 0], [0.18, 0, 0], [0, 0, -0.18], [0, 0, 0.18]];
    arms.forEach(([x, , z]) => addCylinder(g, 0.02, 0.04, 0.04, metal, x, 0.02, z));
    return g;
}

export function createMonitor(options = {}) {
    const { screenColor = 0x001a0a, width = 0.5, height = 0.35 } = options;
    const g = new Group();
    g.userData = { type: 'monitor' };

    const casingMat = mat(0x1a1a1a);
    const casingDepth = 0.05;
    const centerY = 0.94;
    const centerZ = -0.1;

    addBox(g, width, height, casingDepth, casingMat, 0, centerY, centerZ);
    addBox(g, width - 0.03, height - 0.03, 0.003,
        mat(screenColor, { emissive: screenColor, emissiveIntensity: 0.3 }),
        0, centerY, centerZ + casingDepth / 2 + 0.002);

    addCylinder(g, 0.03, 0.03, 0.16, mat(0x333333), 0, 0.83, centerZ);
    addBox(g, 0.15, 0.01, 0.1, mat(0x333333), 0, 0.76, centerZ);
    return g;
}

export function createWorkstation(options = {}) {
    const g = new Group();
    g.userData = { type: 'workstation', interactive: true };
    const desk = createDesk(options);
    g.add(desk);
    const monitor = createMonitor({ screenColor: options.screenColor || 0x001a0a });
    g.add(monitor);
    const chair = createChair();
    chair.position.set(0, 0, 0.6);
    chair.rotation.y = Math.PI;
    g.add(chair);
    return g;
}

export function createServerRack(options = {}) {
    const { height = 2.0, ledColor = 0x00ff44 } = options;
    const g = new Group();
    g.userData = { type: 'serverRack', interactive: true };
    const body = mat(0x2a2a2a, { metalness: 0.8, roughness: 0.3 });
    addBox(g, 0.6, height, 0.5, body, 0, height / 2, 0);
    addBox(g, 0.52, height - 0.1, 0.02, mat(0x1a1a1a), 0, height / 2, -0.24);
    for (let i = 0; i < 5; i++) {
        const y = 0.4 + i * 0.32;
        addBox(g, 0.48, 0.02, 0.4, mat(0x333333), 0, y, 0);
        addSphere(g, 0.02, mat(ledColor, { emissive: ledColor, emissiveIntensity: 0.8 }), -0.2, y + 0.06, -0.24);
        addSphere(g, 0.015, mat(0x00aaff, { emissive: 0x00aaff, emissiveIntensity: 0.5 }), -0.14, y + 0.06, -0.24);
    }
    return g;
}

export function createConferenceTable(options = {}) {
    const { length = 3.5, width = 1.2 } = options;
    const g = new Group();
    g.userData = { type: 'conferenceTable', interactive: true };
    const wood = mat(0x5c3d1a);
    const metal = mat(0x555555, { metalness: 0.6 });
    addBox(g, length, 0.06, width, wood, 0, 0.74, 0);
    addCylinder(g, 0.06, 0.06, 0.72, metal, -length / 2 + 0.3, 0.36, 0);
    addCylinder(g, 0.06, 0.06, 0.72, metal, length / 2 - 0.3, 0.36, 0);
    return g;
}

export function createConferenceChair() {
    const g = new Group();
    const fabric = mat(0x2a2a4a);
    const metal = mat(0x666666, { metalness: 0.7 });
    addBox(g, 0.35, 0.05, 0.35, fabric, 0, 0.44, 0);
    addBox(g, 0.35, 0.35, 0.03, fabric, 0, 0.64, -0.16);
    addCylinder(g, 0.03, 0.03, 0.42, metal, 0, 0.22, 0);
    return g;
}

export function createWhiteboard(options = {}) {
    const { width = 2.5, height = 1.4 } = options;
    const g = new Group();
    g.userData = { type: 'whiteboard', interactive: true };
    addBox(g, width + 0.1, height + 0.1, 0.04, mat(0x444444), 0, 0, 0);
    addBox(g, width, height, 0.01, mat(0xeeeeee), 0, 0, -0.02);
    addBox(g, width + 0.1, 0.06, 0.08, mat(0x555555), 0, -height / 2 - 0.02, -0.04);
    return g;
}

export function createBookshelf(options = {}) {
    const { width = 1.2, height = 2.0, fillLevel = 0.7 } = options;
    const g = new Group();
    g.userData = { type: 'bookshelf', interactive: true };
    const wood = mat(0x654321);
    addBox(g, 0.04, height, 0.3, wood, -width / 2, height / 2, 0);
    addBox(g, 0.04, height, 0.3, wood, width / 2, height / 2, 0);
    addBox(g, width, 0.03, 0.3, wood, 0, 0.01, 0);
    const shelves = 4;
    const spacing = height / (shelves + 1);
    for (let i = 1; i <= shelves; i++) {
        const y = i * spacing;
        addBox(g, width - 0.04, 0.02, 0.3, wood, 0, y, 0);
        if (i / shelves <= fillLevel) {
            const bookCount = 3 + Math.floor(Math.random() * 4);
            const bw = (width - 0.12) / bookCount;
            for (let b = 0; b < bookCount; b++) {
                const bh = spacing * 0.6 + Math.random() * spacing * 0.25;
                const colors = [0x8b0000, 0x00008b, 0x006400, 0x8b8b00, 0x4b0082, 0xcc6600];
                const bc = colors[Math.floor(Math.random() * colors.length)];
                const bx = -width / 2 + 0.08 + b * bw + bw / 2;
                addBox(g, bw * 0.85, bh, 0.22, mat(bc), bx, y + 0.02 + bh / 2, 0);
            }
        }
    }
    return g;
}

export function createCoffeeMachine() {
    const g = new Group();
    g.userData = { type: 'coffeeMachine' };
    addBox(g, 0.25, 0.4, 0.3, mat(0x222222), 0, 0.2, 0);
    addBox(g, 0.2, 0.08, 0.25, mat(0x1a1a1a), 0, 0.42, 0);
    addSphere(g, 0.02, mat(0x00ff00, { emissive: 0x00ff00, emissiveIntensity: 0.6 }), 0.08, 0.35, -0.14);
    return g;
}

export function createFilingCabinet(options = {}) {
    const { color = 0x555555 } = options;
    const g = new Group();
    g.userData = { type: 'filingCabinet' };
    const body = mat(color, { metalness: 0.5 });
    addBox(g, 0.5, 1.3, 0.45, body, 0, 0.65, 0);
    for (let i = 0; i < 3; i++) {
        addBox(g, 0.44, 0.01, 0.005, mat(0x333333), 0, 0.3 + i * 0.4, -0.22);
        addBox(g, 0.08, 0.02, 0.02, mat(0x888888, { metalness: 0.8 }), 0, 0.3 + i * 0.4, -0.24);
    }
    return g;
}

export function createBarrierGate() {
    const g = new Group();
    g.userData = { type: 'barrier', interactive: true };
    addCylinder(g, 0.05, 0.06, 1.0, mat(0x444444, { metalness: 0.6 }), 0, 0.5, 0);
    addBox(g, 1.6, 0.06, 0.06, mat(0xcc0000), 0.8, 1.0, 0);
    addSphere(g, 0.04, mat(0xff0000, { emissive: 0xff0000, emissiveIntensity: 0.4 }), 0, 1.1, 0);
    return g;
}

export function createTrafficLight() {
    const g = new Group();
    g.userData = { type: 'trafficLight', interactive: true };
    addBox(g, 0.15, 0.5, 0.08, mat(0x333333), 0, 0.25, 0);
    const lights = [
        { y: 0.38, color: 0xff0000, on: false },
        { y: 0.25, color: 0xffaa00, on: false },
        { y: 0.12, color: 0x00ff00, on: true },
    ];
    lights.forEach(({ y, color, on }) => {
        const intensity = on ? 0.8 : 0.05;
        addSphere(g, 0.035, mat(color, { emissive: color, emissiveIntensity: intensity }), 0, y, -0.04);
    });
    return g;
}

export function createAlarmBell() {
    const g = new Group();
    g.userData = { type: 'alarmBell', interactive: true };
    addBox(g, 0.2, 0.2, 0.08, mat(0x333333), 0, 0, 0);
    addCylinder(g, 0.08, 0.06, 0.06, mat(0xcc0000), 0, 0, -0.06);
    addSphere(g, 0.015, mat(0xff0000, { emissive: 0xff0000, emissiveIntensity: 0.6 }), 0.06, 0.06, -0.05);
    return g;
}

export function createMailStation(options = {}) {
    const { platformColor = 0x5865f2 } = options;
    const g = new Group();
    g.userData = { type: 'mailStation', interactive: true };
    const desk = createDesk({ width: 1.0, depth: 0.5, color: 0x6b6b6b });
    g.add(desk);
    addBox(g, 0.3, 0.08, 0.2, mat(platformColor, { emissive: platformColor, emissiveIntensity: 0.2 }), -0.2, 0.78, 0);
    addBox(g, 0.25, 0.15, 0.15, mat(0xdddddd), 0.2, 0.82, 0);
    addBox(g, 0.22, 0.01, 0.12, mat(0xffeecc), 0.2, 0.91, 0);
    return g;
}

export function createToolBench(options = {}) {
    const { toolType = 'generic' } = options;
    const g = new Group();
    g.userData = { type: 'toolBench', toolType, interactive: true };
    const desk = createDesk({ width: 1.2, depth: 0.5, color: 0x5a5a5a });
    g.add(desk);
    const accentColors = {
        filesystem: 0x3b82f6,
        shell: 0x10b981,
        web: 0xf59e0b,
        mcp: 0x8b5cf6,
        generic: 0x6b7280,
    };
    const accent = accentColors[toolType] || accentColors.generic;
    addBox(g, 0.3, 0.3, 0.02, mat(0x222222), 0, 0.92, -0.15);
    addBox(g, 0.26, 0.26, 0.005, mat(accent, { emissive: accent, emissiveIntensity: 0.3 }), 0, 0.92, -0.14);
    return g;
}

export function createPingPongTable() {
    const g = new Group();
    g.userData = { type: 'pingPongTable' };
    const green = mat(0x1a6b3c);
    const metal = mat(0x666666, { metalness: 0.6 });
    addBox(g, 1.6, 0.04, 0.9, green, 0, 0.76, 0);
    addLegs(g, 1.4, 0.7, 0.74, metal);
    addBox(g, 0.02, 0.12, 0.9, mat(0xffffff), 0, 0.82, 0);
    return g;
}

export function createKitchenCounter() {
    const g = new Group();
    g.userData = { type: 'kitchenCounter' };
    addBox(g, 2.0, 0.05, 0.6, mat(0x999999), 0, 0.9, 0);
    addBox(g, 2.0, 0.88, 0.55, mat(0x444444), 0, 0.44, 0.02);
    const coffee = createCoffeeMachine();
    coffee.position.set(-0.5, 0.92, 0);
    g.add(coffee);
    return g;
}

export function createWallPartition(options = {}) {
    const { width = 4, height = 3, glass = false } = options;
    const g = new Group();
    if (glass) {
        addBox(g, width, 0.3, 0.06, mat(0x334455), 0, 0.15, 0);
        const glassPanel = new Mesh(
            new BoxGeometry(width, height - 0.6, 0.03),
            glassMat()
        );
        glassPanel.position.set(0, height / 2 + 0.1, 0);
        glassPanel.castShadow = false;
        glassPanel.receiveShadow = false;
        g.add(glassPanel);
        addBox(g, width, 0.06, 0.06, mat(0x334455), 0, height - 0.03, 0);
    } else {
        addBox(g, width, height, 0.12, mat(0x0f3460), 0, height / 2, 0);
    }
    return g;
}

export function createIdeaBoard() {
    const g = new Group();
    g.userData = { type: 'ideaBoard' };
    addBox(g, 1.5, 1.0, 0.04, mat(0x7b6b4e));
    const noteColors = [0xffeb3b, 0xff9800, 0x4caf50, 0x2196f3, 0xe91e63];
    for (let i = 0; i < 6; i++) {
        const nx = -0.5 + Math.random() * 1.0;
        const ny = -0.35 + Math.random() * 0.7;
        const nc = noteColors[Math.floor(Math.random() * noteColors.length)];
        addBox(g, 0.12, 0.12, 0.005, mat(nc), nx, ny, -0.025);
    }
    return g;
}

export function createScreenWall(options = {}) {
    const { screens = 4, width = 4 } = options;
    const g = new Group();
    g.userData = { type: 'screenWall', interactive: true };
    addBox(g, width, 2.0, 0.08, mat(0x1a1a2e), 0, 1.0, 0);
    const sw = (width - 0.4) / screens;
    for (let i = 0; i < screens; i++) {
        const sx = -width / 2 + 0.2 + sw * i + sw / 2;
        addBox(g, sw - 0.1, 0.8, 0.01, mat(0x222222), sx, 1.2, -0.04);
        addBox(g, sw - 0.2, 0.7, 0.005, mat(0x001122, {
            emissive: 0x003344, emissiveIntensity: 0.2,
        }), sx, 1.2, -0.05);
    }
    return g;
}
