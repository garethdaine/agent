import {
    Scene,
    PerspectiveCamera,
    WebGLRenderer,
    Color,
    AmbientLight,
    DirectionalLight,
    PCFSoftShadowMap,
} from 'three';
/**
 * Composable to init and run a Three.js scene in a container.
 * Handles scene, camera, renderer, lights, resize, animation loop, and disposal.
 *
 * @param {Ref<HTMLElement|null>} containerRef
 * @param {{ onFrame?: (delta: number) => void }} [options]
 * @returns {{ scene: import('three').Scene, camera: import('three').PerspectiveCamera, renderer: import('three').WebGLRenderer, controls: OrbitControls | null, start: () => void, stop: () => void, resize: () => void }}
 */
export function useThreeScene(containerRef, options = {}) {
    const { onFrame } = options;
    let lastTime = 0;
    let scene = null;
    let camera = null;
    let renderer = null;
    let controls = null;
    let animationFrameId = null;

    async function init() {
        const container = containerRef?.current ?? containerRef?.value;
        if (!container) return;

        scene = new Scene();
        scene.background = new Color(0x1a1a2e);

        const aspect = container.clientWidth / container.clientHeight || 1;
        camera = new PerspectiveCamera(50, aspect, 0.1, 1000);
        camera.position.set(8, 6, 8);

        renderer = new WebGLRenderer({ antialias: true, alpha: false });
        renderer.setSize(container.clientWidth, container.clientHeight);
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        renderer.shadowMap.enabled = true;
        renderer.shadowMap.type = PCFSoftShadowMap;
        container.appendChild(renderer.domElement);

        const ambient = new AmbientLight(0x404060, 0.6);
        scene.add(ambient);
        const dir = new DirectionalLight(0xffffff, 0.8);
        dir.position.set(5, 10, 5);
        dir.castShadow = true;
        scene.add(dir);

        try {
            const { OrbitControls } = await import('three/examples/jsm/controls/OrbitControls.js');
            controls = new OrbitControls(camera, renderer.domElement);
            controls.enableDamping = true;
            controls.dampingFactor = 0.05;
            controls.target.set(0, 0, 0);
        } catch {
            controls = null;
        }

        return { scene, camera, renderer, controls };
    }

    function resize() {
        const container = containerRef?.current ?? containerRef?.value;
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
        if (typeof onFrame === 'function') onFrame(delta);
        if (controls) controls.update();
        if (renderer && scene && camera) renderer.render(scene, camera);
    }

    async function start() {
        const out = await init();
        if (out) animate();
        return out;
    }

    function stop() {
        if (animationFrameId != null) {
            cancelAnimationFrame(animationFrameId);
            animationFrameId = null;
        }
        if (controls) {
            controls.dispose();
            controls = null;
        }
        if (renderer) {
            const container = containerRef?.current ?? containerRef?.value;
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
        get scene() {
            return scene;
        },
        get camera() {
            return camera;
        },
        get renderer() {
            return renderer;
        },
        get controls() {
            return controls;
        },
        start,
        stop,
        resize,
    };
}
