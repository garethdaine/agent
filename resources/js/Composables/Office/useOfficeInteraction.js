import { ref } from 'vue';
import { Raycaster, Vector2 } from 'three';
import { ZONE_DEFS } from '@/Support/Office/officeFloorplan.js';

export function useOfficeInteraction(scene, camera, renderer, options = {}, agentsByWorkstation = {}) {
    const hoveredObject = ref(null);
    const selectedObject = ref(null);
    const selectedType = ref(null);
    const selectedData = ref(null);
    const cursorStyle = ref('default');

    const raycaster = new Raycaster();
    const mouse = new Vector2();
    let lastHovered = null;
    let originalMaterials = new WeakMap();

    const DRAG_THRESHOLD_PX = 5;
    let pointerDownPos = null;

    function getInteractiveAncestor(object) {
        let current = object;
        while (current) {
            if (current.userData?.interactive) return current;
            current = current.parent;
        }
        return null;
    }

    function resolveWorkstationAgent(object) {
        let current = object;
        while (current) {
            if (current.userData?.type === 'workstation' && current.userData?.workstationIndex !== undefined) {
                const wsIdx = current.userData.workstationIndex;
                const agentMap = typeof agentsByWorkstation === 'function' ? agentsByWorkstation() : agentsByWorkstation;
                return agentMap?.[wsIdx] ?? null;
            }
            if (current.userData?.type === 'monitor') {
                let parent = current.parent;
                while (parent) {
                    if (parent.userData?.workstationIndex !== undefined) {
                        const wsIdx = parent.userData.workstationIndex;
                        const agentMap = typeof agentsByWorkstation === 'function' ? agentsByWorkstation() : agentsByWorkstation;
                        return agentMap?.[wsIdx] ?? null;
                    }
                    parent = parent.parent;
                }
            }
            current = current.parent;
        }
        return null;
    }

    function resolveZoneFromPosition(pos) {
        for (const zone of ZONE_DEFS) {
            const halfW = zone.w / 2;
            const halfD = zone.d / 2;
            if (pos.x >= zone.cx - halfW && pos.x <= zone.cx + halfW &&
                pos.z >= zone.cz - halfD && pos.z <= zone.cz + halfD) {
                return zone;
            }
        }
        return null;
    }

    function setHighlight(object, on) {
        if (!object) return;
        object.traverse((child) => {
            if (child.isMesh && child.material) {
                if (on) {
                    if (!originalMaterials.has(child)) {
                        originalMaterials.set(child, child.material.emissiveIntensity ?? 0);
                    }
                    if (child.material.emissive) {
                        child.material.emissiveIntensity = (originalMaterials.get(child) ?? 0) + 0.2;
                    }
                } else {
                    if (originalMaterials.has(child) && child.material.emissive) {
                        child.material.emissiveIntensity = originalMaterials.get(child);
                    }
                }
            }
        });
    }

    function onPointerMove(event) {
        if (!renderer?.domElement) return;
        const rect = renderer.domElement.getBoundingClientRect();
        mouse.x = ((event.clientX - rect.left) / rect.width) * 2 - 1;
        mouse.y = -((event.clientY - rect.top) / rect.height) * 2 + 1;

        raycaster.setFromCamera(mouse, camera);
        const intersects = raycaster.intersectObjects(scene.children, true);

        const interactive = intersects.length > 0 ? getInteractiveAncestor(intersects[0].object) : null;

        if (interactive !== lastHovered) {
            setHighlight(lastHovered, false);
            setHighlight(interactive, true);
            lastHovered = interactive;
            hoveredObject.value = interactive;
            cursorStyle.value = interactive ? 'pointer' : 'default';
            if (renderer?.domElement) {
                renderer.domElement.style.cursor = cursorStyle.value;
            }
        }
    }

    function onPointerDown(event) {
        pointerDownPos = { x: event.clientX, y: event.clientY };
    }

    function onPointerUp(event) {
        if (!pointerDownPos) return;
        const dx = event.clientX - pointerDownPos.x;
        const dy = event.clientY - pointerDownPos.y;
        const dist = Math.sqrt(dx * dx + dy * dy);
        pointerDownPos = null;

        if (dist > DRAG_THRESHOLD_PX) return;

        handleClick(event);
    }

    function handleClick(event) {
        if (!renderer?.domElement) return;
        const rect = renderer.domElement.getBoundingClientRect();
        mouse.x = ((event.clientX - rect.left) / rect.width) * 2 - 1;
        mouse.y = -((event.clientY - rect.top) / rect.height) * 2 + 1;

        raycaster.setFromCamera(mouse, camera);
        const intersects = raycaster.intersectObjects(scene.children, true);

        if (intersects.length === 0) {
            selectedObject.value = null;
            selectedType.value = null;
            selectedData.value = null;
            if (options.onDeselect) options.onDeselect();
            return;
        }

        const interactive = getInteractiveAncestor(intersects[0].object);

        const wsAgent = resolveWorkstationAgent(intersects[0].object);

        if (wsAgent) {
            selectedObject.value = interactive || intersects[0].object;
            selectedType.value = 'agent';
            selectedData.value = {
                type: 'agent',
                agentId: wsAgent.id,
                agentName: wsAgent.name,
            };
            if (options.onAgentClick) options.onAgentClick(selectedData.value);
        } else if (interactive) {
            selectedObject.value = interactive;
            const type = interactive.userData.type;
            selectedType.value = type;

            if (type === 'agent') {
                selectedData.value = {
                    type: 'agent',
                    agentId: interactive.userData.agentId,
                    agentName: interactive.userData.agentName,
                };
                if (options.onAgentClick) options.onAgentClick(selectedData.value);
            } else {
                const zone = resolveZoneFromPosition(interactive.position);
                selectedData.value = {
                    type: 'zone',
                    zoneId: zone?.id ?? type,
                    zoneName: zone?.label ?? type,
                    objectType: type,
                };
                if (options.onZoneClick) options.onZoneClick(selectedData.value);
            }
        } else {
            const hitPoint = intersects[0].point;
            const zone = resolveZoneFromPosition(hitPoint);
            if (zone) {
                selectedData.value = {
                    type: 'zone',
                    zoneId: zone.id,
                    zoneName: zone.label,
                };
                if (options.onZoneClick) options.onZoneClick(selectedData.value);
            }
        }
    }

    function attach() {
        const el = renderer?.domElement;
        if (!el) return;
        el.addEventListener('pointermove', onPointerMove);
        el.addEventListener('pointerdown', onPointerDown);
        el.addEventListener('pointerup', onPointerUp);
    }

    function detach() {
        const el = renderer?.domElement;
        if (!el) return;
        el.removeEventListener('pointermove', onPointerMove);
        el.removeEventListener('pointerdown', onPointerDown);
        el.removeEventListener('pointerup', onPointerUp);
        pointerDownPos = null;
        if (el) el.style.cursor = 'default';
    }

    return {
        hoveredObject,
        selectedObject,
        selectedType,
        selectedData,
        cursorStyle,
        attach,
        detach,
    };
}
