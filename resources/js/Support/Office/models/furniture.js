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

    const standZ = centerZ - casingDepth / 2;
    addCylinder(g, 0.03, 0.03, 0.16, mat(0x333333), 0, 0.83, standZ);
    addBox(g, 0.15, 0.01, 0.1, mat(0x333333), 0, 0.76, standZ);
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

// ─── Wall Decorations ───────────────────────────────────────────

export function createWallPicture(options = {}) {
    const { width = 0.5, height = 0.4, frameColor = 0x5c3d1a, canvasColor } = options;
    const g = new Group();
    g.userData = { type: 'wallPicture' };
    // Frame
    addBox(g, width + 0.06, height + 0.06, 0.03, mat(frameColor), 0, 0, 0);
    // Canvas — deterministic color from options or warm random
    const colors = [0x2e5984, 0x6b3a2a, 0x2a5a3a, 0x5a3a5e, 0x8a6530, 0x3a4a6a];
    const cc = canvasColor ?? colors[Math.floor(options.seed ?? 0) % colors.length];
    addBox(g, width, height, 0.005, mat(cc), 0, 0, -0.018);
    // Highlight strip (abstract art feel)
    const stripColor = [0xddaa44, 0xcc6644, 0x44aacc, 0x88bb66][(options.seed ?? 0) % 4];
    addBox(g, width * 0.6, height * 0.08, 0.002, mat(stripColor), 0, height * 0.1, -0.02);
    return g;
}

export function createWallPoster(options = {}) {
    const { width = 0.4, height = 0.6, accentColor } = options;
    const g = new Group();
    g.userData = { type: 'wallPoster' };
    const accents = [0x3b82f6, 0xef4444, 0x10b981, 0xf59e0b, 0x8b5cf6];
    const ac = accentColor ?? accents[(options.seed ?? 0) % accents.length];
    // White border
    addBox(g, width + 0.04, height + 0.04, 0.005, mat(0xeeeeee), 0, 0, 0);
    // Poster face
    addBox(g, width, height, 0.003, mat(ac), 0, 0, -0.004);
    // Text line placeholders
    addBox(g, width * 0.5, 0.02, 0.001, mat(0xffffff), 0, -height * 0.3, -0.006);
    addBox(g, width * 0.3, 0.015, 0.001, mat(0xffffff), 0, -height * 0.35, -0.006);
    return g;
}

export function createWallTV(options = {}) {
    const { width = 0.7, height = 0.45 } = options;
    const g = new Group();
    g.userData = { type: 'wallTV' };
    // Casing
    addBox(g, width + 0.04, height + 0.04, 0.04, mat(0x1a1a1a), 0, 0, 0);
    // Screen
    addBox(g, width, height, 0.005,
        mat(0x001122, { emissive: 0x002244, emissiveIntensity: 0.15 }),
        0, 0, -0.023);
    // Stand arm (wall mount bracket)
    addBox(g, 0.08, 0.08, 0.06, mat(0x333333), 0, 0, 0.04);
    return g;
}

export function createWallClock() {
    const g = new Group();
    g.userData = { type: 'wallClock' };
    // Body
    addCylinder(g, 0.13, 0.13, 0.03, mat(0x333333, { metalness: 0.5 }), 0, 0, 0);
    // Face
    addCylinder(g, 0.11, 0.11, 0.005, mat(0xf5f5f0), 0, 0, -0.016);
    // Center dot
    addSphere(g, 0.01, mat(0x222222), 0, 0, -0.02);
    // Hour hand (simplified)
    addBox(g, 0.01, 0.06, 0.003, mat(0x222222), 0, 0.03, -0.022);
    // Minute hand
    addBox(g, 0.008, 0.08, 0.003, mat(0x222222), 0.02, 0.02, -0.022);
    return g;
}

// ─── Desk Items ─────────────────────────────────────────────────

export function createCoffeeCup(options = {}) {
    const { color = 0xeeeeee } = options;
    const g = new Group();
    g.userData = { type: 'coffeeCup' };
    addCylinder(g, 0.03, 0.025, 0.08, mat(color), 0, 0.04, 0);
    // Coffee inside
    addCylinder(g, 0.026, 0.026, 0.01, mat(0x3b1a05), 0, 0.075, 0);
    // Handle — small torus approximation via box
    addBox(g, 0.015, 0.04, 0.02, mat(color), 0.035, 0.04, 0);
    return g;
}

export function createPaperStack() {
    const g = new Group();
    g.userData = { type: 'paperStack' };
    for (let i = 0; i < 4; i++) {
        const slight = (i % 2 === 0) ? 0.003 : -0.003;
        addBox(g, 0.12, 0.005, 0.15, mat(0xf5f5f0), slight, 0.003 + i * 0.005, 0);
    }
    return g;
}

export function createKeyboardAndMouse() {
    const g = new Group();
    g.userData = { type: 'keyboardMouse' };
    // Keyboard
    addBox(g, 0.28, 0.01, 0.1, mat(0x2a2a2a), 0, 0.005, 0);
    // Keys texture approximation — lighter strip
    addBox(g, 0.24, 0.003, 0.08, mat(0x3a3a3a), 0, 0.012, 0);
    // Mouse
    addBox(g, 0.04, 0.02, 0.06, mat(0x2a2a2a), 0.2, 0.01, 0);
    return g;
}

// ─── Floor-Standing Items ───────────────────────────────────────

export function createWaterCooler() {
    const g = new Group();
    g.userData = { type: 'waterCooler' };
    // Cabinet
    addBox(g, 0.3, 0.8, 0.3, mat(0xdddddd), 0, 0.4, 0);
    // Bottle (translucent blue)
    addCylinder(g, 0.1, 0.1, 0.35, new MeshPhysicalMaterial({
        color: 0x88ccff, transparent: true, opacity: 0.35, roughness: 0.1,
    }), 0, 0.98, 0);
    addCylinder(g, 0.04, 0.1, 0.05, mat(0xcccccc), 0, 0.78, 0);
    // Tap
    addBox(g, 0.03, 0.05, 0.04, mat(0x888888, { metalness: 0.6 }), 0.12, 0.6, -0.12);
    return g;
}

export function createPhotocopier() {
    const g = new Group();
    g.userData = { type: 'photocopier' };
    // Body
    addBox(g, 0.6, 0.7, 0.55, mat(0xcccccc), 0, 0.35, 0);
    // Top scanner bed
    addBox(g, 0.55, 0.04, 0.5, mat(0x888888), 0, 0.72, 0);
    // Paper tray
    addBox(g, 0.3, 0.03, 0.35, mat(0xbbbbbb), 0, 0.15, -0.32);
    // Output tray
    addBox(g, 0.3, 0.02, 0.15, mat(0xbbbbbb), 0, 0.55, -0.35);
    // Small screen
    addBox(g, 0.12, 0.08, 0.01, mat(0x113322, { emissive: 0x224433, emissiveIntensity: 0.2 }),
        -0.18, 0.76, -0.2);
    // Paper sheets in tray
    addBox(g, 0.25, 0.015, 0.3, mat(0xf5f5f0), 0, 0.165, -0.32);
    return g;
}

export function createPrinter() {
    const g = new Group();
    g.userData = { type: 'printer' };
    // Body
    addBox(g, 0.45, 0.25, 0.4, mat(0x444444), 0, 0.125, 0);
    // Top lid
    addBox(g, 0.44, 0.02, 0.38, mat(0x555555), 0, 0.26, 0);
    // Output tray
    addBox(g, 0.25, 0.01, 0.12, mat(0x555555), 0, 0.18, -0.26);
    // Status LED
    addSphere(g, 0.01, mat(0x00ff00, { emissive: 0x00ff00, emissiveIntensity: 0.5 }),
        0.18, 0.22, -0.19);
    return g;
}

export function createFloorPlant(options = {}) {
    const { height = 1.0, leafColor = 0x2d7a2d } = options;
    const g = new Group();
    g.userData = { type: 'floorPlant' };
    // Pot
    addCylinder(g, 0.12, 0.1, 0.2, mat(0xb35c2a), 0, 0.1, 0);
    // Soil
    addCylinder(g, 0.11, 0.11, 0.03, mat(0x3a2510), 0, 0.21, 0);
    // Stem
    addCylinder(g, 0.015, 0.02, height * 0.5, mat(0x3a5a2a), 0, 0.22 + height * 0.25, 0);
    // Leaf clusters
    const leafH = 0.22 + height * 0.5;
    const lm = mat(leafColor);
    addSphere(g, height * 0.18, lm, 0, leafH + height * 0.1, 0);
    addSphere(g, height * 0.14, lm, 0.08, leafH, 0.06);
    addSphere(g, height * 0.14, lm, -0.07, leafH, -0.05);
    addSphere(g, height * 0.12, lm, 0.04, leafH + height * 0.18, -0.04);
    return g;
}

export function createTrashBin() {
    const g = new Group();
    g.userData = { type: 'trashBin' };
    addCylinder(g, 0.13, 0.11, 0.45, mat(0x555555, { metalness: 0.4 }), 0, 0.225, 0);
    // Rim
    addCylinder(g, 0.135, 0.135, 0.02, mat(0x666666, { metalness: 0.5 }), 0, 0.46, 0);
    return g;
}

// ─── Door ───────────────────────────────────────────────────────

export function createOfficeDoor(options = {}) {
    const { width = 1.0, height = 2.4, open = true } = options;
    const g = new Group();
    g.userData = { type: 'officeDoor' };
    // Frame (three sides)
    const frameMat = mat(0x444455, { metalness: 0.3 });
    addBox(g, 0.08, height, 0.12, frameMat, -width / 2 - 0.04, height / 2, 0);
    addBox(g, 0.08, height, 0.12, frameMat, width / 2 + 0.04, height / 2, 0);
    addBox(g, width + 0.16, 0.08, 0.12, frameMat, 0, height + 0.04, 0);
    // Door panel
    const doorPanel = new Group();
    const panelMat = mat(0x6b4226);
    const panel = new Mesh(new BoxGeometry(width, height, 0.06), panelMat);
    panel.position.set(width / 2, height / 2, 0);
    panel.castShadow = true;
    doorPanel.add(panel);
    // Handle
    const handleMat = mat(0xccccaa, { metalness: 0.7 });
    const handle = new Mesh(new BoxGeometry(0.03, 0.12, 0.06), handleMat);
    handle.position.set(width * 0.85, height * 0.45, -0.05);
    doorPanel.add(handle);
    // Pivot at left edge of door
    doorPanel.position.set(-width / 2, 0, 0);
    if (open) doorPanel.rotation.y = -Math.PI * 0.45; // ~80° open
    g.add(doorPanel);
    return g;
}

// ─── Outdoor Items ──────────────────────────────────────────────

export function createCar(options = {}) {
    const { color = 0x3366aa } = options;
    const g = new Group();
    g.userData = { type: 'car' };
    const bodyMat = mat(color, { metalness: 0.6, roughness: 0.35 });
    // Body lower
    addBox(g, 2.0, 0.55, 4.2, bodyMat, 0, 0.45, 0);
    // Cabin upper
    addBox(g, 1.7, 0.5, 2.2, bodyMat, 0, 0.95, -0.3);
    // Windshield
    const glassMaterial = new MeshPhysicalMaterial({
        color: 0x88ccff, transparent: true, opacity: 0.3, roughness: 0.1,
    });
    addBox(g, 1.6, 0.45, 0.02, glassMaterial, 0, 0.95, 0.78);
    // Rear window
    addBox(g, 1.6, 0.4, 0.02, glassMaterial, 0, 0.95, -1.38);
    // Wheels
    const wheelMat = mat(0x222222);
    const wheelPositions = [
        [-0.9, 0.2, 1.3], [0.9, 0.2, 1.3],
        [-0.9, 0.2, -1.3], [0.9, 0.2, -1.3],
    ];
    wheelPositions.forEach(([x, y, z]) => {
        addCylinder(g, 0.2, 0.2, 0.15, wheelMat, x, y, z);
    });
    // Headlights
    addSphere(g, 0.06, mat(0xffffcc, { emissive: 0xffffaa, emissiveIntensity: 0.1 }),
        -0.7, 0.45, 2.1);
    addSphere(g, 0.06, mat(0xffffcc, { emissive: 0xffffaa, emissiveIntensity: 0.1 }),
        0.7, 0.45, 2.1);
    // Tail lights
    addSphere(g, 0.05, mat(0xff2200, { emissive: 0xff0000, emissiveIntensity: 0.1 }),
        -0.7, 0.45, -2.1);
    addSphere(g, 0.05, mat(0xff2200, { emissive: 0xff0000, emissiveIntensity: 0.1 }),
        0.7, 0.45, -2.1);
    return g;
}

export function createFlowerBed(options = {}) {
    const { width = 1.5, depth = 0.6, flowerColor } = options;
    const g = new Group();
    g.userData = { type: 'flowerBed' };
    // Soil bed
    addBox(g, width, 0.2, depth, mat(0x3a2510), 0, 0.1, 0);
    // Border
    addBox(g, width + 0.08, 0.25, 0.04, mat(0x654321), 0, 0.125, -depth / 2 - 0.02);
    addBox(g, width + 0.08, 0.25, 0.04, mat(0x654321), 0, 0.125, depth / 2 + 0.02);
    addBox(g, 0.04, 0.25, depth + 0.08, mat(0x654321), -width / 2 - 0.02, 0.125, 0);
    addBox(g, 0.04, 0.25, depth + 0.08, mat(0x654321), width / 2 + 0.02, 0.125, 0);
    // Flowers
    const flowerColors = [0xff6699, 0xff4444, 0xffcc33, 0xff88cc, 0xaa44ff, 0xff9933];
    const fc = flowerColor ?? flowerColors[(options.seed ?? 0) % flowerColors.length];
    const greenMat = mat(0x2d7a2d);
    for (let i = 0; i < 7; i++) {
        const fx = -width / 2 + 0.15 + (i / 7) * (width - 0.3);
        const fz = (i % 2 === 0) ? 0.08 : -0.08;
        // Stem
        addCylinder(g, 0.008, 0.008, 0.15, greenMat, fx, 0.28, fz);
        // Flower head
        const fCol = flowerColors[(i + (options.seed ?? 0)) % flowerColors.length];
        addSphere(g, 0.04, mat(fCol), fx, 0.38, fz);
    }
    return g;
}

export function createOutdoorBench() {
    const g = new Group();
    g.userData = { type: 'outdoorBench' };
    const woodMat = mat(0x8b6b3d);
    const metalLeg = mat(0x444444, { metalness: 0.6 });
    // Seat slats
    addBox(g, 1.2, 0.04, 0.15, woodMat, 0, 0.42, 0.08);
    addBox(g, 1.2, 0.04, 0.15, woodMat, 0, 0.42, -0.08);
    // Back slats
    addBox(g, 1.2, 0.04, 0.12, woodMat, 0, 0.62, -0.2);
    addBox(g, 1.2, 0.04, 0.12, woodMat, 0, 0.82, -0.2);
    // Legs
    addBox(g, 0.05, 0.42, 0.35, metalLeg, -0.5, 0.21, -0.05);
    addBox(g, 0.05, 0.42, 0.35, metalLeg, 0.5, 0.21, -0.05);
    // Back supports
    addBox(g, 0.05, 0.5, 0.05, metalLeg, -0.5, 0.67, -0.2);
    addBox(g, 0.05, 0.5, 0.05, metalLeg, 0.5, 0.67, -0.2);
    return g;
}

export function createLampPost() {
    const g = new Group();
    g.userData = { type: 'lampPost' };
    // Pole
    addCylinder(g, 0.04, 0.06, 3.0, mat(0x555555, { metalness: 0.5 }), 0, 1.5, 0);
    // Arm
    addBox(g, 0.5, 0.03, 0.03, mat(0x555555, { metalness: 0.5 }), 0.25, 2.95, 0);
    // Light
    addSphere(g, 0.12, mat(0xffeeaa, { emissive: 0xffdd88, emissiveIntensity: 0.6 }),
        0.5, 2.85, 0);
    return g;
}

export function createParkingBollard() {
    const g = new Group();
    g.userData = { type: 'bollard' };
    addCylinder(g, 0.06, 0.06, 0.6, mat(0x555555, { metalness: 0.5 }), 0, 0.3, 0);
    // Reflective band
    addCylinder(g, 0.065, 0.065, 0.06, mat(0xffcc00, { emissive: 0xffaa00, emissiveIntensity: 0.2 }),
        0, 0.5, 0);
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
