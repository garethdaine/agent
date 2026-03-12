import { Group, Mesh, BoxGeometry, PlaneGeometry, MeshStandardMaterial, MeshPhysicalMaterial, DoubleSide, CanvasTexture, SpriteMaterial, Sprite } from 'three';
import {
    createWorkstation, createServerRack, createConferenceTable, createConferenceChair,
    createWhiteboard, createBookshelf, createBarrierGate, createTrafficLight,
    createAlarmBell, createMailStation, createToolBench, createPingPongTable,
    createKitchenCounter, createWallPartition, createIdeaBoard, createScreenWall,
    createFilingCabinet,
    createWallPicture, createWallPoster, createWallTV, createWallClock,
    createCoffeeCup, createPaperStack, createKeyboardAndMouse,
    createWaterCooler, createPhotocopier, createPrinter, createFloorPlant, createTrashBin,
    createOfficeDoor,
    createCar, createFlowerBed, createOutdoorBench, createLampPost, createParkingBollard,
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
    { id: 'escalation',    label: 'Escalation',        cx: -8,    cz: 1,    w: 2,  d: 2,   tint: 0x1a1020 },
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

function buildWallWithGaps(totalLength, wallH, wallThickness, fixedCoord, gapCenters, gapWidth, axis) {
    const halfTotal = totalLength / 2;
    const gaps = gapCenters.map((c) => ({ start: c - gapWidth / 2, end: c + gapWidth / 2 })).sort((a, b) => a.start - b.start);

    const segments = [];
    let cursor = -halfTotal;

    gaps.forEach((gap) => {
        if (gap.start > cursor) {
            const segW = gap.start - cursor;
            const segCenter = cursor + segW / 2;
            segments.push(axis === 'x'
                ? { w: segW, x: segCenter }
                : { w: segW, z: segCenter },
            );
        }
        cursor = gap.end;
    });

    if (cursor < halfTotal) {
        const segW = halfTotal - cursor;
        const segCenter = cursor + segW / 2;
        segments.push(axis === 'x'
            ? { w: segW, x: segCenter }
            : { w: segW, z: segCenter },
        );
    }

    return segments;
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
    const frameMat = new MeshStandardMaterial({ color: 0x222233 });
    const glassMat = new MeshPhysicalMaterial({
        transmission: 0.85, roughness: 0.1, thickness: 0.1,
        color: 0xaaddff, transparent: true, opacity: 0.3,
    });

    const addWall = (w, h, d, x, y, z) => {
        const m = new Mesh(new BoxGeometry(w, h, d), wallMat);
        m.position.set(x, y, z);
        m.castShadow = true;
        m.receiveShadow = true;
        group.add(m);
    };

    const addWindow = (x, y, z, w, h, d, rotY = 0) => {
        const glass = new Mesh(new BoxGeometry(w, h, d), glassMat);
        glass.position.set(x, y, z);
        glass.rotation.y = rotY;
        glass.userData = { type: 'window' };
        glass.raycast = () => {};
        group.add(glass);

        const frameW = 0.06;
        const addFrame = (fw, fh, fd, fx, fy, fz) => {
            const f = new Mesh(new BoxGeometry(fw, fh, fd), frameMat);
            f.position.set(fx, fy, fz);
            f.rotation.y = rotY;
            group.add(f);
        };
        addFrame(w + frameW, frameW, d + 0.02, x, y + h / 2, z);
        addFrame(w + frameW, frameW, d + 0.02, x, y - h / 2, z);
        if (rotY === 0) {
            addFrame(frameW, h, d + 0.02, x - w / 2, y, z);
            addFrame(frameW, h, d + 0.02, x + w / 2, y, z);
        } else {
            addFrame(d + 0.02, h, frameW, x, y, z - w / 2);
            addFrame(d + 0.02, h, frameW, x, y, z + w / 2);
        }

        const belowH = y - h / 2;
        if (belowH > 0.01) {
            if (rotY === 0) {
                addWall(w, belowH, 0.15, x, belowH / 2, z);
            } else {
                addWall(0.15, belowH, w, x, belowH / 2, z);
            }
        }
        const aboveBottom = y + h / 2;
        const aboveH = wallH - aboveBottom;
        if (aboveH > 0.01) {
            if (rotY === 0) {
                addWall(w, aboveH, 0.15, x, aboveBottom + aboveH / 2, z);
            } else {
                addWall(0.15, aboveH, w, x, aboveBottom + aboveH / 2, z);
            }
        }
    };

    const winH = 1.5;
    const winY = 1.8;
    const winD = 0.15;
    const northZ = -FLOOR_DEPTH / 2 - 0.5;
    const eastX = FLOOR_WIDTH / 2 + 0.5;

    const northWindows = [-9, -3, 3, 9];
    const northWinW = 2.5;
    const northWallSegments = buildWallWithGaps(
        FLOOR_WIDTH + 1, wallH, 0.15, northZ,
        northWindows, northWinW, 'x',
    );
    northWallSegments.forEach(({ w, x }) => addWall(w, wallH, 0.15, x, wallH / 2, northZ));
    northWindows.forEach((wx) => addWindow(wx, winY, northZ, northWinW, winH, winD));

    const eastWindows = [-6, 0, 6];
    const eastWinW = 2.5;
    const eastWallSegments = buildWallWithGaps(
        FLOOR_DEPTH + 1, wallH, 0.15, eastX,
        eastWindows, eastWinW, 'z',
    );
    eastWallSegments.forEach(({ w, z }) => addWall(0.15, wallH, w, eastX, wallH / 2, z));
    eastWindows.forEach((wz) => addWindow(eastX, winY, wz, eastWinW, winH, winD, Math.PI / 2));

    addWall(0.15, wallH, FLOOR_DEPTH + 1, -FLOOR_WIDTH / 2 - 0.5, wallH / 2, 0);

    const southZ = FLOOR_DEPTH / 2 + 0.5;
    const southWindows = [-9, -3, 3, 9];
    const southWinW = 2.5;
    const southWallSegments = buildWallWithGaps(
        FLOOR_WIDTH + 1, wallH, 0.15, southZ,
        southWindows, southWinW, 'x',
    );
    southWallSegments.forEach(({ w, x }) => {
        // Split the rightmost segment to create a door gap at x≈12
        const doorCenterX = 12;
        const doorW = 1.3;
        const doorH = 2.5;
        const segLeft = x - w / 2;
        const segRight = x + w / 2;
        const doorLeft = doorCenterX - doorW / 2;
        const doorRight = doorCenterX + doorW / 2;

        if (doorLeft > segLeft && doorRight < segRight) {
            // Segment contains the door gap — split into left, above-door, and right
            const leftW = doorLeft - segLeft;
            addWall(leftW, wallH, 0.15, segLeft + leftW / 2, wallH / 2, southZ);
            const rightW = segRight - doorRight;
            addWall(rightW, wallH, 0.15, doorRight + rightW / 2, wallH / 2, southZ);
            // Above-door segment
            const aboveDoorH = wallH - doorH;
            if (aboveDoorH > 0.01) {
                addWall(doorW, aboveDoorH, 0.15, doorCenterX, doorH + aboveDoorH / 2, southZ);
            }
        } else {
            addWall(w, wallH, 0.15, x, wallH / 2, southZ);
        }
    });
    southWindows.forEach((wx) => addWindow(wx, winY, southZ, southWinW, winH, winD));

    const partitions = [
        { x: -8, z: -8.5, w: 0.1, d: 3, glass: true, rY: 0 },
        { x: -8, z: -3.5, w: 0.1, d: 5, glass: true, rY: 0 },
        { x: -8, z: 3, w: 0.1, d: 4, glass: true, rY: 0 },
        { x: -11.25, z: -6.5, w: 6.5, d: 0.1, glass: false, rY: 0 },
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
    const confChairs = [
        { x: -3.2, z: 8.0, ry: Math.PI },
        { x: -2,   z: 8.0, ry: Math.PI },
        { x: -0.8, z: 8.0, ry: Math.PI },
        { x: -3.2, z: 6.0, ry: 0 },
        { x: -2,   z: 6.0, ry: 0 },
        { x: -0.8, z: 6.0, ry: 0 },
        { x: -4.3, z: 7.0, ry: Math.PI / 2 },
        { x:  0.3, z: 7.0, ry: -Math.PI / 2 },
    ];
    confChairs.forEach(({ x, z, ry }) => {
        const c = createConferenceChair();
        c.position.set(x, 0, z);
        c.rotation.y = ry;
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
    alarm.position.set(-8.1, 2.2, 1);
    alarm.userData.zoneId = 'escalation';
    group.add(alarm);

    return group;
}

// ─── Wall Decorations ──────────────────────────────────────────

export function buildWallDecorations() {
    const group = new Group();
    group.name = 'wallDecorations';

    const halfW = FLOOR_WIDTH / 2 + 0.5;   // ±14.5
    const halfD = FLOOR_DEPTH / 2 + 0.5;   // ±11.5
    const wallOffset = 0.1; // offset from wall surface

    // North wall (z ≈ -11.5, items face +z)
    [
        { fn: createWallPicture, x: -6, y: 2.0, seed: 0 },
        { fn: createWallPicture, x: 0, y: 2.2, seed: 1 },
        { fn: createWallPicture, x: 6, y: 1.9, seed: 2 },
        { fn: createWallClock, x: 10, y: 2.4 },
    ].forEach(({ fn, x, y, seed }) => {
        const item = seed !== undefined ? fn({ seed }) : fn();
        item.position.set(x, y, -halfD + wallOffset);
        group.add(item);
    });

    // South wall (z ≈ 11.5, items face -z) — rotate π on Y
    [
        { fn: createWallPoster, x: -4, y: 2.0, seed: 0 },
        { fn: createWallTV, x: 0, y: 2.0 },
        { fn: createWallPoster, x: 4, y: 2.1, seed: 1 },
    ].forEach(({ fn, x, y, seed }) => {
        const item = seed !== undefined ? fn({ seed }) : fn();
        item.position.set(x, y, halfD - wallOffset);
        item.rotation.y = Math.PI;
        group.add(item);
    });

    // East wall (x ≈ 14.5, items face -x) — rotate -π/2 on Y
    [
        { fn: createWallPicture, z: -4, y: 2.0, seed: 3 },
        { fn: createWallPoster, z: 0, y: 2.1, seed: 2 },
        { fn: createWallPicture, z: 4, y: 1.8, seed: 4 },
    ].forEach(({ fn, z, y, seed }) => {
        const item = seed !== undefined ? fn({ seed }) : fn();
        item.position.set(halfW - wallOffset, y, z);
        item.rotation.y = -Math.PI / 2;
        group.add(item);
    });

    // West wall (x ≈ -14.5, items face +x) — rotate π/2 on Y
    [
        { fn: createWallTV, z: -6, y: 2.0 },
        { fn: createWallPoster, z: 2, y: 2.0, seed: 3 },
        { fn: createWallClock, z: 8, y: 2.3 },
    ].forEach(({ fn, z, y, seed }) => {
        const item = seed !== undefined ? fn({ seed }) : fn();
        item.position.set(-halfW + wallOffset, y, z);
        item.rotation.y = Math.PI / 2;
        group.add(item);
    });

    return group;
}

// ─── Office Decorations (floor items + desk clutter) ───────────

export function buildOfficeDecorations() {
    const group = new Group();
    group.name = 'officeDecorations';

    // Floor plants — spread around zone edges
    const plantPositions = [
        { x: -7.5, z: -6, h: 1.1 },
        { x: 12.5, z: -6, h: 0.9 },
        { x: -7.5, z: 5, h: 1.0 },
        { x: 13, z: 4.5, h: 1.2 },
        { x: -12.5, z: 9, h: 0.8 },
        { x: 5.5, z: 9, h: 1.0 },
    ];
    plantPositions.forEach(({ x, z, h }) => {
        const plant = createFloorPlant({ height: h });
        plant.position.set(x, 0, z);
        group.add(plant);
    });

    // Water cooler — near break room
    const cooler = createWaterCooler();
    cooler.position.set(8, 0, 8.5);
    group.add(cooler);

    // Photocopier — corridor area
    const copier = createPhotocopier();
    copier.position.set(-8.5, 0, -0.5);
    copier.rotation.y = Math.PI / 2;
    group.add(copier);

    // Printer — near workstations
    const printer = createPrinter();
    printer.position.set(5.5, 0, -5.5);
    group.add(printer);

    // Trash bins
    const bin1 = createTrashBin();
    bin1.position.set(12, 0, 9);
    group.add(bin1);

    const bin2 = createTrashBin();
    bin2.position.set(-5, 0, 8.5);
    group.add(bin2);

    // Desk clutter — keyboards on all desks, cups & papers on some
    WORKSTATION_POSITIONS.forEach((pos, i) => {
        // Keyboard + mouse on every desk (on desk surface at y≈0.76)
        const kb = createKeyboardAndMouse();
        kb.position.set(pos.x - 0.15, 0.76, pos.z + 0.08);
        group.add(kb);

        // Coffee cup — ~40% of desks (deterministic)
        if ((i * 7 + 3) % 5 < 2) {
            const cup = createCoffeeCup();
            cup.position.set(pos.x + 0.45, 0.76, pos.z - 0.15);
            group.add(cup);
        }

        // Paper stack — ~35% of desks
        if ((i * 11 + 1) % 6 < 2) {
            const papers = createPaperStack();
            papers.position.set(pos.x + 0.35, 0.76, pos.z + 0.2);
            group.add(papers);
        }
    });

    return group;
}

// ─── Office Door ───────────────────────────────────────────────

export function buildOfficeDoor() {
    const group = new Group();
    group.name = 'officeDoor';

    const door = createOfficeDoor({ open: true });
    door.position.set(12, 0, FLOOR_DEPTH / 2 + 0.5);
    group.add(door);

    return group;
}

// ─── Outdoor Environment ───────────────────────────────────────

export function buildOutdoorEnvironment() {
    const group = new Group();
    group.name = 'outdoorEnvironment';

    // Parking lot ground plane
    const parkingGeom = new PlaneGeometry(16, 14);
    const parkingMat = new MeshStandardMaterial({ color: 0x444444, side: DoubleSide });
    const parking = new Mesh(parkingGeom, parkingMat);
    parking.rotation.x = -Math.PI / 2;
    parking.position.set(23, 0.001, -1);
    parking.receiveShadow = true;
    group.add(parking);

    // Parking lines (white strips)
    const lineMat = new MeshStandardMaterial({ color: 0xdddddd });
    for (let i = 0; i < 5; i++) {
        const lineGeom = new PlaneGeometry(0.08, 5);
        const line = new Mesh(lineGeom, lineMat);
        line.rotation.x = -Math.PI / 2;
        line.position.set(16.5 + i * 3.5, 0.003, -1);
        group.add(line);
    }

    // Walkway — connecting door to parking
    const walkGeom = new PlaneGeometry(3, 4);
    const walkMat = new MeshStandardMaterial({ color: 0x777777, side: DoubleSide });
    const walk = new Mesh(walkGeom, walkMat);
    walk.rotation.x = -Math.PI / 2;
    walk.position.set(14, 0.002, 13);
    walk.receiveShadow = true;
    group.add(walk);

    // Cars — 4 in a 2×2 grid
    const carColors = [0x3366aa, 0xcc3333, 0x222222, 0xdddddd];
    const carPositions = [
        { x: 19, z: -4 }, { x: 19, z: 2 },
        { x: 25, z: -4 }, { x: 25, z: 2 },
    ];
    carPositions.forEach(({ x, z }, i) => {
        const car = createCar({ color: carColors[i] });
        car.position.set(x, 0, z);
        car.rotation.y = Math.PI / 2;
        group.add(car);
    });

    // Flower beds — along the building exterior (north side)
    [-4, 2, 8].forEach((x, i) => {
        const bed = createFlowerBed({ seed: i });
        bed.position.set(x, 0, -13);
        group.add(bed);
    });

    // Additional flower beds along south wall near door
    [8, 14].forEach((x, i) => {
        const bed = createFlowerBed({ seed: i + 3, width: 1.2, depth: 0.5 });
        bed.position.set(x, 0, 13);
        group.add(bed);
    });

    // Outdoor benches
    const bench1 = createOutdoorBench();
    bench1.position.set(0, 0, -14);
    group.add(bench1);

    const bench2 = createOutdoorBench();
    bench2.position.set(16, 0, 4);
    bench2.rotation.y = Math.PI / 2;
    group.add(bench2);

    // Lamp posts
    const lamp1 = createLampPost();
    lamp1.position.set(16, 0, -7);
    group.add(lamp1);

    const lamp2 = createLampPost();
    lamp2.position.set(30, 0, -7);
    group.add(lamp2);

    const lamp3 = createLampPost();
    lamp3.position.set(23, 0, 6);
    group.add(lamp3);

    // Bollards marking parking boundary
    const bollardPositions = [
        { x: 15.5, z: -7 }, { x: 15.5, z: -3 },
        { x: 15.5, z: 1 }, { x: 15.5, z: 5 },
    ];
    bollardPositions.forEach(({ x, z }) => {
        const b = createParkingBollard();
        b.position.set(x, 0, z);
        group.add(b);
    });

    return group;
}
