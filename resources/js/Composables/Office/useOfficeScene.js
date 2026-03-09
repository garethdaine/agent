import {
    Scene, PerspectiveCamera, WebGLRenderer, Color, Vector3,
    AmbientLight, DirectionalLight, HemisphereLight, PointLight, SpotLight,
    PCFSoftShadowMap, ACESFilmicToneMapping, Fog,
    Mesh, BoxGeometry, MeshStandardMaterial,
} from 'three';
const WASD_SPEED = 12;
const SHIFT_MULTIPLIER = 2.2;

export function useOfficeScene(containerRef, options = {}) {
    const { onFrame } = options;
    let lastTime = 0;
    let scene = null;
    let camera = null;
    let renderer = null;
    let controls = null;
    let animationFrameId = null;
    let zoneLights = [];

    const keysDown = new Set();
    const _forward = new Vector3();
    const _right = new Vector3();
    const _move = new Vector3();

    const ceilingLights = new Map();
    let _ceilingLightsEnabled = true;

    async function init() {
        const container = containerRef?.value ?? containerRef?.current;
        if (!container) return null;

        scene = new Scene();
        scene.background = new Color(0x0a0e1a);
        scene.fog = new Fog(0x0a0e1a, 35, 60);

        const aspect = container.clientWidth / container.clientHeight || 1;
        camera = new PerspectiveCamera(45, aspect, 0.1, 200);
        camera.position.set(22, 18, 22);

        renderer = new WebGLRenderer({ antialias: true, alpha: false, powerPreference: 'high-performance' });
        renderer.setSize(container.clientWidth, container.clientHeight);
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        renderer.shadowMap.enabled = true;
        renderer.shadowMap.type = PCFSoftShadowMap;
        renderer.toneMapping = ACESFilmicToneMapping;
        renderer.toneMappingExposure = 1.4;
        container.appendChild(renderer.domElement);

        const hemi = new HemisphereLight(0x445577, 0x111122, 0.5);
        scene.add(hemi);

        const ambient = new AmbientLight(0x404060, 0.35);
        scene.add(ambient);

        const sun = new DirectionalLight(0xffeedd, 0.8);
        sun.position.set(15, 25, 10);
        sun.castShadow = true;
        sun.shadow.mapSize.width = 1024;
        sun.shadow.mapSize.height = 1024;
        sun.shadow.camera.near = 0.5;
        sun.shadow.camera.far = 60;
        sun.shadow.camera.left = -20;
        sun.shadow.camera.right = 20;
        sun.shadow.camera.top = 20;
        sun.shadow.camera.bottom = -20;
        sun.shadow.bias = -0.001;
        scene.add(sun);

        const fill = new DirectionalLight(0x4466aa, 0.25);
        fill.position.set(-10, 8, -5);
        scene.add(fill);

        try {
            const { OrbitControls } = await import('three/examples/jsm/controls/OrbitControls.js');
            controls = new OrbitControls(camera, renderer.domElement);
            controls.enableDamping = true;
            controls.dampingFactor = 0.08;
            controls.target.set(0, 0, 0);
            controls.minDistance = 8;
            controls.maxDistance = 50;
            controls.maxPolarAngle = Math.PI / 2.2;
            controls.minPolarAngle = 0.2;
        } catch {
            controls = null;
        }

        window.addEventListener('keydown', onKeyDown);
        window.addEventListener('keyup', onKeyUp);

        return { scene, camera, renderer, controls };
    }

    function onKeyDown(e) {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.isContentEditable) return;
        const k = e.key.toLowerCase();
        if (['w', 'a', 's', 'd', 'q', 'e', 'shift'].includes(k)) {
            keysDown.add(k);
            e.preventDefault();
        }
    }

    function onKeyUp(e) {
        keysDown.delete(e.key.toLowerCase());
    }

    function processWASD(delta) {
        if (!camera || !controls || keysDown.size === 0) return;

        _forward.set(0, 0, 0);
        _right.set(0, 0, 0);
        _move.set(0, 0, 0);

        camera.getWorldDirection(_forward);
        _forward.y = 0;
        _forward.normalize();

        _right.crossVectors(_forward, camera.up).normalize();

        let speed = WASD_SPEED * delta;
        if (keysDown.has('shift')) speed *= SHIFT_MULTIPLIER;

        if (keysDown.has('w')) { _move.x += _forward.x * speed; _move.z += _forward.z * speed; }
        if (keysDown.has('s')) { _move.x -= _forward.x * speed; _move.z -= _forward.z * speed; }
        if (keysDown.has('a')) { _move.x -= _right.x * speed; _move.z -= _right.z * speed; }
        if (keysDown.has('d')) { _move.x += _right.x * speed; _move.z += _right.z * speed; }

        if (keysDown.has('q')) camera.position.y = Math.max(4, camera.position.y - speed * 0.5);
        if (keysDown.has('e')) camera.position.y = Math.min(40, camera.position.y + speed * 0.5);

        if (_move.lengthSq() > 0) {
            camera.position.add(_move);
            controls.target.add(_move);
        }
    }

    function addZoneLight(x, y, z, color = 0xffffff, intensity = 0.3, distance = 8) {
        if (!scene) return;
        const light = new PointLight(color, intensity, distance);
        light.position.set(x, y, z);
        scene.add(light);
        zoneLights.push(light);
        return light;
    }

    function addCeilingLight(zoneId, x, z, options = {}) {
        if (!scene) return;
        const { color = 0xffeedd, maxIntensity = 40, distance = 12, height = 3.0 } = options;

        const spot = new SpotLight(color, 0, distance, Math.PI / 3, 0.6, 2);
        spot.position.set(x, height, z);
        spot.target.position.set(x, 0, z);
        spot.castShadow = false;
        scene.add(spot);
        scene.add(spot.target);

        const fixtureGeo = new BoxGeometry(0.4, 0.06, 0.4);
        const fixtureMat = new MeshStandardMaterial({ color: 0x333340 });
        const fixture = new Mesh(fixtureGeo, fixtureMat);
        fixture.position.set(x, height + 0.03, z);
        scene.add(fixture);

        const bulbMat = new MeshStandardMaterial({
            color, emissive: color, emissiveIntensity: 0,
        });
        const bulb = new Mesh(new BoxGeometry(0.25, 0.04, 0.25), bulbMat);
        bulb.position.set(x, height - 0.02, z);
        scene.add(bulb);

        const entry = { spot, bulb, bulbMat, maxIntensity, currentIntensity: 0, targetIntensity: 0 };

        if (!ceilingLights.has(zoneId)) ceilingLights.set(zoneId, []);
        ceilingLights.get(zoneId).push(entry);
    }

    function setCeilingLightsEnabled(enabled) {
        if (_ceilingLightsEnabled !== enabled) {
            _ceilingLightsEnabled = enabled;
            _ceilingLightsSettled = false;
        }
    }

    function setZoneOccupied(zoneId, occupied) {
        const lights = ceilingLights.get(zoneId);
        if (!lights) return;
        lights.forEach((entry) => {
            const newTarget = !_ceilingLightsEnabled ? 0 : (occupied ? entry.maxIntensity : entry.maxIntensity * 0.3);
            if (entry.targetIntensity !== newTarget) {
                entry.targetIntensity = newTarget;
                _ceilingLightsSettled = false;
            }
        });
    }

    let _ceilingLightsSettled = false;

    function updateCeilingLights(delta) {
        if (_ceilingLightsSettled) return;
        const lerpSpeed = 3.0;
        let allSettled = true;
        ceilingLights.forEach((lights) => {
            lights.forEach((entry) => {
                const diff = entry.targetIntensity - entry.currentIntensity;
                if (Math.abs(diff) < 0.01) {
                    entry.currentIntensity = entry.targetIntensity;
                } else {
                    entry.currentIntensity += diff * Math.min(lerpSpeed * delta, 1);
                    allSettled = false;
                }
                entry.spot.intensity = entry.currentIntensity;
                const t = entry.maxIntensity > 0 ? entry.currentIntensity / entry.maxIntensity : 0;
                entry.bulbMat.emissiveIntensity = t * 2.5;
            });
        });
        _ceilingLightsSettled = allSettled;
    }

    function resize() {
        const container = containerRef?.value ?? containerRef?.current;
        if (!container || !camera || !renderer) return;
        const w = container.clientWidth;
        const h = container.clientHeight;
        camera.aspect = w / h;
        camera.updateProjectionMatrix();
        renderer.setSize(w, h);
    }

    function animate(time = 0) {
        animationFrameId = requestAnimationFrame(animate);
        const delta = lastTime ? (time - lastTime) / 1000 : 0;
        lastTime = time;
        processWASD(delta);
        if (typeof onFrame === 'function') onFrame(delta, time / 1000);
        updateCeilingLights(delta);
        if (controls) controls.update();
        if (renderer && scene && camera) renderer.render(scene, camera);
    }

    function focusOnPosition(x, z, duration = 1.0) {
        if (!controls || !camera) return;
        const startTarget = controls.target.clone();
        const startPos = camera.position.clone();
        const targetPos = { x, y: 0, z };
        const dist = camera.position.distanceTo(controls.target);
        let elapsed = 0;
        const step = () => {
            elapsed += 0.016;
            const t = Math.min(elapsed / duration, 1);
            const ease = t < 0.5 ? 2 * t * t : -1 + (4 - 2 * t) * t;
            controls.target.set(
                startTarget.x + (targetPos.x - startTarget.x) * ease,
                startTarget.y,
                startTarget.z + (targetPos.z - startTarget.z) * ease,
            );
            const dx = startPos.x + (targetPos.x + dist * 0.6 - startPos.x) * ease;
            const dz = startPos.z + (targetPos.z + dist * 0.6 - startPos.z) * ease;
            camera.position.set(dx, camera.position.y, dz);
            if (t < 1) requestAnimationFrame(step);
        };
        step();
    }

    async function start() {
        const out = await init();
        if (out) animate();
        return out ?? {};
    }

    function stop() {
        if (animationFrameId != null) {
            cancelAnimationFrame(animationFrameId);
            animationFrameId = null;
        }
        window.removeEventListener('keydown', onKeyDown);
        window.removeEventListener('keyup', onKeyUp);
        keysDown.clear();
        zoneLights = [];
        ceilingLights.clear();
        if (controls) {
            controls.dispose();
            controls = null;
        }
        if (renderer) {
            const container = containerRef?.value ?? containerRef?.current;
            if (container && renderer.domElement?.parentNode === container) {
                container.removeChild(renderer.domElement);
            }
            renderer.dispose();
            renderer.forceContextLoss();
            renderer = null;
        }
        scene = null;
        camera = null;
    }

    return {
        get scene() { return scene; },
        get camera() { return camera; },
        get renderer() { return renderer; },
        get controls() { return controls; },
        start,
        stop,
        resize,
        addZoneLight,
        addCeilingLight,
        setZoneOccupied,
        setCeilingLightsEnabled,
        focusOnPosition,
    };
}
