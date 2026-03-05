import { Group, Mesh, BoxGeometry, PlaneGeometry, MeshStandardMaterial, DoubleSide, CanvasTexture, SpriteMaterial, Sprite } from 'three';
import {
    createWorkstation, createServerRack, createConferenceTable, createConferenceChair,
    createWhiteboard, createBookshelf, createBarrierGate, createTrafficLight,
    createAlarmBell, createMailStation, createToolBench, createPingPongTable,
    createKitchenCounter, createWallPartition, createIdeaBoard, createScreenWall,
    createFilingCabinet,
} from './models/furniture.js';

const FLOOR_WIDTH = 28;
const FLOOR_DEPTH = 22;

export const ZONE_DEFS = [
    { id: 'serverRack',   label: 'Server Room',       cx: -10.5, cz: -8.5, w: 5,  d: 3, tint: 0x12182e },
    { id: 'warRoom',      label: 'War Room',          cx: 1,     cz: -8.5, w: 18, d: 3, tint: 0x141e36 },
    { id: 'securityDesk',  label: 'Security',          cx: -11,   cz: -3.5, w: 4,  d: 5, tint: 0x1a1620 },
    { id: 'workstations',  label: 'Workstations',      cx: 2,     cz: -2.5, w: 22, d: 7, tint: 0x16213e },
    { id: 'mailroom',      label: 'Comms Hub',         cx: -11,   cz: 3,    w: 4,  d: 4, tint: 0x161e30 },
    { id: 'conference',    label: 'Conference Room',   cx: -2,    cz: 7,    w: 12, d: 4, tint: 0x141a30 },
    { id: 'vault',         label: 'Archives',          cx: 10,    cz: 6,    w: 6,  d: 3, tint: 0x18162a },
    { id: 'toolWorkshop',  label: 'Tool Workshop',     cx: -10,   cz: 7,    w: 6,  d: 4, tint: 0x161620 },
    { id: 'breakRoom',     label: 'Break Room',        cx: 10,    cz: 9.5,  w: 6,  d: 3, tint: 0x1a1e22 },
    { id: 'escalation',    label: 'Escalation',        cx: -5,    cz: -9.8, w: 2,  d: 0.5, tint: 0x1a1020 },
];

export const WORKSTATION_POSITIONS = [
    { x: -6, z: -4 }, { x: -3, z: -4 }, { x: 0, z: -4 }, { x: 3, z: -4 },
    { x: -6, z: -1 }, { x: -3, z: -1 }, { x: 0, z: -1 }, { x: 3, z: -1 },
    { x: 6, z: -1 },
];

function makeZoneFloor(zone) {
    const geom = new PlaneGeometry(zone.w, zone.d);
    const matl = new MeshStandardMaterial({ color: zone.tint, side: DoubleSide });
    const mesh = new Mesh(geom, matl);
    mesh.rotation.x = -Math.PI / 2;
    mesh.position.set(zone.cx, 0.005, zone.cz);
    mesh.receiveShadow = true;
    mesh.userData = { zoneId: zone.id };
    return mesh;
}

function makeTextSprite(text, scale = 1.5) {
    const canvas = document.createElement('canvas');
    canvas.width = 512;
    canvas.height = 128;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = 'rgba(0,0,0,0)';
    ctx.fillRect(0, 0, 512, 128);
    ctx.font = 'bold 36px system-ui, sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillStyle = 'rgba(255,255,255,0.5)';
    ctx.fillText(text, 256, 64);

    const texture = new CanvasTexture(canvas);
    const spriteMat = new SpriteMaterial({ map: texture, depthTest: false });
    const sprite = new Sprite(spriteMat);
    sprite.scale.set(scale * 2, scale * 0.5, 1);
    return sprite;
}

export function buildOfficeFloor() {
    const group = new Group();
    group.name = 'officeFloor';

    const baseGeom = new PlaneGeometry(FLOOR_WIDTH + 2, FLOOR_DEPTH + 2);
    const baseMat = new MeshStandardMaterial({ color: 0x0e1629, side: DoubleSide });
    const base = new Mesh(baseGeom, baseMat);
    base.rotation.x = -Math.PI / 2;
    base.position.y = -0.01;
    base.receiveShadow = true;
    group.add(base);

    ZONE_DEFS.forEach((zone) => {
        group.add(makeZoneFloor(zone));
    });

    return group;
}

export function buildOfficeWalls() {
    const group = new Group();
    group.name = 'officeWalls';
    const wallH = 3.2;
    const wallMat = new MeshStandardMaterial({ color: 0x0f3460 });

    const addWall = (w, h, d, x, y, z) => {
        const m = new Mesh(new BoxGeometry(w, h, d), wallMat);
        m.position.set(x, y, z);
        m.castShadow = true;
        m.receiveShadow = true;
        group.add(m);
    };

    addWall(FLOOR_WIDTH + 1, wallH, 0.15, 0, wallH / 2, -FLOOR_DEPTH / 2 - 0.5);
    addWall(0.15, wallH, FLOOR_DEPTH + 1, -FLOOR_WIDTH / 2 - 0.5, wallH / 2, 0);
    addWall(0.15, wallH, FLOOR_DEPTH + 1, FLOOR_WIDTH / 2 + 0.5, wallH / 2, 0);
    addWall(FLOOR_WIDTH + 1, 0.6, 0.15, 0, 0.3, FLOOR_DEPTH / 2 + 0.5);

    const partitions = [
        { x: -8, z: -8.5, w: 0.1, d: 3, glass: true, rY: 0 },
        { x: -8, z: -3.5, w: 0.1, d: 5, glass: true, rY: 0 },
        { x: -8, z: 3, w: 0.1, d: 4, glass: true, rY: 0 },
        { x: -13, z: -6.5, w: 10, d: 0.1, glass: false, rY: 0 },
        { x: 0, z: -6.5, w: 20, d: 0.1, glass: true, rY: 0 },
        { x: -8, z: 5.5, w: 0.1, d: 1, glass: true, rY: 0 },
        { x: 6.5, z: 5, w: 0.1, d: 5, glass: true, rY: 0 },
        { x: 6.5, z: 7.5, w: 0.1, d: 1, glass: true, rY: 0 },
    ];

    partitions.forEach(({ x, z, w, d, glass }) => {
        const p = createWallPartition({ width: Math.max(w, d), height: wallH * 0.85, glass });
        p.position.set(x, 0, z);
        if (d > w) p.rotation.y = Math.PI / 2;
        group.add(p);
    });

    return group;
}

export function buildZoneLabels() {
    const group = new Group();
    group.name = 'zoneLabels';

    ZONE_DEFS.forEach((zone) => {
        const sprite = makeTextSprite(zone.label);
        sprite.position.set(zone.cx, 2.8, zone.cz);
        group.add(sprite);
    });

    return group;
}

export function buildZoneFurniture() {
    const group = new Group();
    group.name = 'zoneFurniture';

    // --- Server Room ---
    for (let i = 0; i < 4; i++) {
        const rack = createServerRack();
        rack.position.set(-12 + i * 1.2, 0, -8.5);
        group.add(rack);
    }
    const tl = createTrafficLight();
    tl.position.set(-8.5, 0, -9);
    group.add(tl);

    // --- War Room / Ops Wall ---
    const screenWall = createScreenWall({ screens: 6, width: 14 });
    screenWall.position.set(1, 0, -9.85);
    group.add(screenWall);

    // --- Security Desk ---
    const barrier = createBarrierGate();
    barrier.position.set(-9.5, 0, -3.5);
    barrier.rotation.y = Math.PI / 2;
    group.add(barrier);
    const secDesk = createWorkstation({ width: 1.0, depth: 0.5, screenColor: 0x1a0000 });
    secDesk.position.set(-11.5, 0, -4.5);
    group.add(secDesk);

    // --- Workstations ---
    const screenColors = [0x001a0a, 0x00101a, 0x0a001a, 0x001a0a, 0x1a0a00, 0x001a0a, 0x0a001a, 0x001a0a, 0x00101a];
    WORKSTATION_POSITIONS.forEach((pos, i) => {
        const ws = createWorkstation({ screenColor: screenColors[i % screenColors.length] });
        ws.position.set(pos.x, 0, pos.z);
        ws.userData.workstationIndex = i;
        group.add(ws);
    });

    // --- Mailroom / Comms Hub ---
    const platforms = [
        { color: 0x5865f2, z: 1.5 },
        { color: 0x4a154b, z: 3.0 },
        { color: 0x0088cc, z: 4.5 },
    ];
    platforms.forEach(({ color, z }) => {
        const ms = createMailStation({ platformColor: color });
        ms.position.set(-11, 0, z);
        group.add(ms);
    });

    // --- Conference Room ---
    const confTable = createConferenceTable({ length: 4, width: 1.4 });
    confTable.position.set(-2, 0, 7);
    group.add(confTable);
    const chairPositions = [
        [-4.5, 7, 0], [-3, 7, 0], [-1.5, 7, 0], [0, 7, 0], [1.5, 7, 0],
    ];
    chairPositions.forEach(([cx, cz, side]) => {
        const c = createConferenceChair();
        c.position.set(cx, 0, cz + (side === 0 ? 1.2 : -1.2));
        c.rotation.y = side === 0 ? 0 : Math.PI;
        group.add(c);
    });
    const wb = createWhiteboard();
    wb.position.set(-2, 1.5, 4.85);
    group.add(wb);

    // --- Vault / Archives ---
    for (let i = 0; i < 3; i++) {
        const shelf = createBookshelf({ fillLevel: 0.3 + Math.random() * 0.6 });
        shelf.position.set(8 + i * 1.5, 0, 5.5);
        group.add(shelf);
    }
    const cabinet = createFilingCabinet();
    cabinet.position.set(12, 0, 6);
    group.add(cabinet);

    // --- Tool Workshop ---
    const toolTypes = ['filesystem', 'shell', 'web', 'mcp'];
    toolTypes.forEach((type, i) => {
        const bench = createToolBench({ toolType: type });
        bench.position.set(-12 + i * 1.6, 0, 7);
        bench.rotation.y = Math.PI;
        group.add(bench);
    });
    const wsCabinet = createFilingCabinet({ color: 0x3a3a3a });
    wsCabinet.position.set(-12, 0, 8.5);
    group.add(wsCabinet);

    // --- Break Room ---
    const counter = createKitchenCounter();
    counter.position.set(10, 0, 8.2);
    group.add(counter);
    const ppt = createPingPongTable();
    ppt.position.set(10, 0, 10.5);
    group.add(ppt);
    const ideaBoard = createIdeaBoard();
    ideaBoard.position.set(12.5, 1.6, 10.85);
    group.add(ideaBoard);

    // --- Escalation Alarm ---
    const alarm = createAlarmBell();
    alarm.position.set(-5, 2.2, -10.7);
    group.add(alarm);

    return group;
}
