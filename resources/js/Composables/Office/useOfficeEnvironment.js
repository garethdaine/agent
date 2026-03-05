import {
    Color, Vector3, MathUtils,
    BufferGeometry, Float32BufferAttribute, Points, PointsMaterial,
    Mesh, PlaneGeometry, MeshStandardMaterial,
} from 'three';
import { Sky } from 'three/addons/objects/Sky.js';
import { startWeatherPolling, classifyWeather } from '@/Support/Office/weatherService.js';

const DAY_BG = new Color(0x87ceeb);
const NIGHT_BG = new Color(0x0a0e1a);
const SUNSET_BG = new Color(0x2a1a3a);

const DAY_FOG = new Color(0x9bb8d0);
const NIGHT_FOG = new Color(0x0a0e1a);

const OFFICE_HALF_W = 14.5;
const OFFICE_HALF_D = 11.5;

function isInsideOffice(x, z) {
    return Math.abs(x) < OFFICE_HALF_W && Math.abs(z) < OFFICE_HALF_D;
}

function noRaycast() {}

function disableRaycast(obj) {
    if (!obj) return;
    obj.raycast = noRaycast;
    if (obj.children) obj.children.forEach(disableRaycast);
}

export function useOfficeEnvironment(sceneApi, options = {}) {
    const { override } = options;

    let sky = null;
    let stars = null;
    let grassPlane = null;
    let rainPoints = null;
    let snowPoints = null;
    let stopWeatherPoll = null;

    let ceilingLightsEnabled = false;
    let sunRef = null;
    let ambientRef = null;
    let hemiRef = null;
    let fillRef = null;

    let weather = { weatherCode: 0, isDay: true, temperature: 15, windSpeed: 5 };
    let weatherClass = 'clear';

    function getSunPosition(timeOfDay) {
        const noon = 12;
        const hourAngle = (timeOfDay - noon) / 12 * Math.PI;
        const elevation = Math.cos(hourAngle) * 70;
        const azimuth = Math.sin(hourAngle) * 180;
        return { elevation, azimuth };
    }

    function getTimeOfDay() {
        const now = new Date();
        return now.getHours() + now.getMinutes() / 60;
    }

    function getEffectiveMode() {
        const mode = override?.value;
        if (mode === 'day') return 'day';
        if (mode === 'night') return 'night';
        const tod = getTimeOfDay();
        const { elevation } = getSunPosition(tod);
        if (elevation > 5) return 'day';
        if (elevation < -5) return 'night';
        return 'twilight';
    }

    function getDayFactor() {
        const mode = getEffectiveMode();
        if (mode === 'day') return 1;
        if (mode === 'night') return 0;
        const tod = getTimeOfDay();
        const { elevation } = getSunPosition(tod);
        return MathUtils.clamp((elevation + 5) / 10, 0, 1);
    }

    function createSky(scene) {
        sky = new Sky();
        sky.scale.setScalar(450000);
        sky.name = 'envSky';
        scene.add(sky);

        sky.material.uniforms.turbidity.value = 2;
        sky.material.uniforms.rayleigh.value = 1;
        sky.material.uniforms.mieCoefficient.value = 0.005;
        sky.material.uniforms.mieDirectionalG.value = 0.8;
    }

    function createStars(scene) {
        const count = 6000;
        const positions = new Float32Array(count * 3);
        const radius = 400;

        for (let i = 0; i < count * 3; i += 3) {
            const r = radius * (0.8 + Math.random() * 0.2);
            const theta = Math.random() * Math.PI * 2;
            const phi = Math.acos(2 * Math.random() - 1);
            positions[i] = r * Math.sin(phi) * Math.cos(theta);
            positions[i + 1] = Math.abs(r * Math.cos(phi));
            positions[i + 2] = r * Math.sin(phi) * Math.sin(theta);
        }

        const geom = new BufferGeometry();
        geom.setAttribute('position', new Float32BufferAttribute(positions, 3));

        const mat = new PointsMaterial({
            color: 0xffffff,
            size: 1.2,
            sizeAttenuation: true,
            transparent: true,
            opacity: 0,
        });

        stars = new Points(geom, mat);
        stars.name = 'envStars';
        scene.add(stars);
    }

    function createGrass(scene) {
        const geom = new PlaneGeometry(120, 120);
        const mat = new MeshStandardMaterial({
            color: 0x2d5a27,
            roughness: 0.9,
        });
        grassPlane = new Mesh(geom, mat);
        grassPlane.rotation.x = -Math.PI / 2;
        grassPlane.position.y = -0.02;
        grassPlane.receiveShadow = true;
        grassPlane.name = 'envGrass';
        scene.add(grassPlane);
    }

    function randomOutdoorPosition() {
        let x, z;
        do {
            x = (Math.random() - 0.5) * 80;
            z = (Math.random() - 0.5) * 80;
        } while (isInsideOffice(x, z));
        return { x, z };
    }

    function createWeatherParticles(scene) {
        const count = 2000;
        const positions = new Float32Array(count * 3);
        for (let i = 0; i < count * 3; i += 3) {
            const { x, z } = randomOutdoorPosition();
            positions[i] = x;
            positions[i + 1] = Math.random() * 20;
            positions[i + 2] = z;
        }

        const rainGeom = new BufferGeometry();
        rainGeom.setAttribute('position', new Float32BufferAttribute(positions.slice(), 3));
        rainPoints = new Points(rainGeom, new PointsMaterial({
            color: 0xaaccff, size: 0.15, transparent: true, opacity: 0, sizeAttenuation: true,
        }));
        rainPoints.name = 'envRain';
        scene.add(rainPoints);

        const snowGeom = new BufferGeometry();
        snowGeom.setAttribute('position', new Float32BufferAttribute(positions.slice(), 3));
        snowPoints = new Points(snowGeom, new PointsMaterial({
            color: 0xffffff, size: 0.3, transparent: true, opacity: 0, sizeAttenuation: true,
        }));
        snowPoints.name = 'envSnow';
        scene.add(snowPoints);
    }

    function findLights(scene) {
        scene.traverse((obj) => {
            if (obj.isDirectionalLight && obj.castShadow) sunRef = obj;
            else if (obj.isAmbientLight) ambientRef = obj;
            else if (obj.isHemisphereLight) hemiRef = obj;
            else if (obj.isDirectionalLight && !obj.castShadow) fillRef = obj;
        });
    }

    function install(scene) {
        findLights(scene);
        createSky(scene);
        createStars(scene);
        createGrass(scene);
        createWeatherParticles(scene);

        [sky, stars, grassPlane, rainPoints, snowPoints].forEach(disableRaycast);

        stopWeatherPoll = startWeatherPolling((w) => {
            weather = w;
            weatherClass = classifyWeather(w.weatherCode);
        });
    }

    function updateSkyUniforms(dayFactor) {
        if (!sky) return;
        const tod = getTimeOfDay();
        const { elevation, azimuth } = getSunPosition(
            override?.value === 'day' ? 12 : override?.value === 'night' ? 0 : tod,
        );

        const phi = MathUtils.degToRad(90 - elevation);
        const theta = MathUtils.degToRad(azimuth);
        const sunPos = new Vector3().setFromSphericalCoords(1, phi, theta);
        sky.material.uniforms.sunPosition.value.copy(sunPos);

        const cloudyDim = (weatherClass === 'cloudy' || weatherClass === 'rain' || weatherClass === 'showers' || weatherClass === 'thunderstorm') ? 0.4 : 0;
        sky.material.uniforms.turbidity.value = 2 + cloudyDim * 8;
        sky.material.uniforms.rayleigh.value = 1 + cloudyDim * 2;

        sky.visible = dayFactor > 0.01;
        sky.material.uniforms.sunPosition.value.y = Math.max(sky.material.uniforms.sunPosition.value.y, -0.05);
    }

    function update(delta) {
        const scene = sceneApi?.scene;
        if (!scene) return;

        const dayFactor = getDayFactor();
        const nightFactor = 1 - dayFactor;
        ceilingLightsEnabled = dayFactor < 0.5;

        updateSkyUniforms(dayFactor);

        if (stars) {
            stars.material.opacity = nightFactor * 0.9;
            stars.rotation.y += delta * 0.0005;
        }

        const fogDim = weatherClass === 'fog' ? 0.5 : 0;
        const targetBg = dayFactor > 0.5
            ? (dayFactor > 0.8 ? DAY_BG : SUNSET_BG)
            : NIGHT_BG;

        if (scene.background) scene.background.lerp(targetBg, delta * 2);
        if (scene.fog) {
            scene.fog.color.lerp(dayFactor > 0.5 ? DAY_FOG : NIGHT_FOG, delta * 2);
            scene.fog.near = MathUtils.lerp(scene.fog.near, dayFactor > 0.5 ? 40 : 35, delta);
            scene.fog.far = MathUtils.lerp(scene.fog.far, (dayFactor > 0.5 ? 140 : 60) * (1 - fogDim * 0.5), delta);
        }

        if (sunRef) {
            sunRef.intensity = MathUtils.lerp(sunRef.intensity, dayFactor * 1.2 * (1 - fogDim * 0.4), delta * 2);
            const sunHeight = 5 + dayFactor * 25;
            sunRef.position.y = MathUtils.lerp(sunRef.position.y, sunHeight, delta);
        }
        if (ambientRef) {
            ambientRef.intensity = MathUtils.lerp(ambientRef.intensity, 0.15 + dayFactor * 0.45, delta * 2);
        }
        if (hemiRef) {
            hemiRef.intensity = MathUtils.lerp(hemiRef.intensity, 0.2 + dayFactor * 0.5, delta * 2);
        }
        if (fillRef) {
            fillRef.intensity = MathUtils.lerp(fillRef.intensity, dayFactor * 0.3, delta * 2);
        }

        if (grassPlane) {
            const grassGreen = dayFactor > 0.3 ? 0x3a7a30 : 0x1a3a18;
            const snowTint = weatherClass === 'snow' ? 0.3 : 0;
            grassPlane.material.color.lerp(
                new Color(grassGreen).lerp(new Color(0xddeeff), snowTint),
                delta * 2,
            );
        }

        updateWeatherParticles(delta);
    }

    function updateWeatherParticles(delta) {
        const isRain = weatherClass === 'rain' || weatherClass === 'showers' || weatherClass === 'thunderstorm';
        const isSnow = weatherClass === 'snow';

        if (rainPoints) {
            const targetOpacity = isRain ? 0.6 : 0;
            rainPoints.material.opacity = MathUtils.lerp(rainPoints.material.opacity, targetOpacity, delta * 3);
            if (rainPoints.material.opacity > 0.01) {
                const pos = rainPoints.geometry.attributes.position;
                const speed = weatherClass === 'thunderstorm' ? 25 : 15;
                for (let i = 0; i < pos.count; i++) {
                    let y = pos.getY(i) - speed * delta;
                    if (y < 0 || isInsideOffice(pos.getX(i), pos.getZ(i))) {
                        y = 15 + Math.random() * 5;
                        const { x, z } = randomOutdoorPosition();
                        pos.setX(i, x);
                        pos.setZ(i, z);
                    }
                    pos.setY(i, y);
                }
                pos.needsUpdate = true;
            }
        }

        if (snowPoints) {
            const targetOpacity = isSnow ? 0.7 : 0;
            snowPoints.material.opacity = MathUtils.lerp(snowPoints.material.opacity, targetOpacity, delta * 3);
            if (snowPoints.material.opacity > 0.01) {
                const pos = snowPoints.geometry.attributes.position;
                for (let i = 0; i < pos.count; i++) {
                    let y = pos.getY(i) - 2 * delta;
                    const drift = Math.sin(Date.now() * 0.001 + i) * 0.3 * delta;
                    const nx = pos.getX(i) + drift;
                    if (y < 0 || isInsideOffice(nx, pos.getZ(i))) {
                        y = 15 + Math.random() * 5;
                        const { x, z } = randomOutdoorPosition();
                        pos.setX(i, x);
                        pos.setZ(i, z);
                    } else {
                        pos.setX(i, nx);
                    }
                    pos.setY(i, y);
                }
                pos.needsUpdate = true;
            }
        }

        if (weatherClass === 'thunderstorm' && sunRef) {
            if (Math.random() < 0.003) {
                sunRef.intensity = 3;
                if (ambientRef) ambientRef.intensity = 2;
            }
        }
    }

    function dispose() {
        if (stopWeatherPoll) stopWeatherPoll();
        const scene = sceneApi?.scene;
        if (!scene) return;
        [sky, stars, grassPlane, rainPoints, snowPoints].forEach((obj) => {
            if (obj) scene.remove(obj);
        });
    }

    return {
        install,
        update,
        dispose,
        get ceilingLightsEnabled() { return ceilingLightsEnabled; },
        get weatherClass() { return weatherClass; },
        get weather() { return weather; },
    };
}
