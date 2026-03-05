import { ref } from 'vue';
import { Raycaster, Vector2, Color } from 'three';
import { ZONE_DEFS } from '@/Support/Office/officeFloorplan.js';

const HIGHLIGHT_COLOR = new Color(0x4488ff);
const SELECTION_COLOR = new Color(0x66aaff);

export function useOfficeInteraction(scene, camera, renderer, options = {}, agentsByWorkstation = {}) {
    const hoveredObject = ref(null);
    const selectedObject = ref(null);
    const selectedType = ref(null);
    const selectedData = ref(null);
    const cursorStyle = ref('default');

    const raycaster = new Raycaster();
    const mouse = new Vector2();
    let lastHovered = null;
    let highlightedMeshes = new Set();
    let selectedMeshes = new Set();
    let lastSelected3D = null;

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

    function applyEmissiveOverride(child, color, intensity, trackingSet) {
        if (!trackingSet.has(child)) {
            child._savedEmissive = child.material.emissive.clone();
            child._savedEmissiveIntensity = child.material.emissiveIntensity ?? 0;
            child._origMaterial = child.material;
            child.material = child.material.clone();
            trackingSet.add(child);
        }
        child.material.emissive.copy(color);
        child.material.emissiveIntensity = intensity;
    }

    function removeEmissiveOverride(child, trackingSet) {
        if (trackingSet.has(child) && child._origMaterial) {
            child.material.dispose();
            child.material = child._origMaterial;
            delete child._origMaterial;
            delete child._savedEmissive;
            delete child._savedEmissiveIntensity;
            trackingSet.delete(child);
        }
    }

    function setHighlight(object, on) {
        if (!object) return;
        object.traverse((child) => {
            if (!child.isMesh || !child.material?.emissive) return;
            if (selectedMeshes.has(child)) return;

            if (on) {
                applyEmissiveOverride(child, HIGHLIGHT_COLOR, 0.35, highlightedMeshes);
            } else {
                removeEmissiveOverride(child, highlightedMeshes);
            }
        });
    }

    function setSelectionHighlight(object, on) {
        if (!object) return;
        object.traverse((child) => {
            if (!child.isMesh || !child.material?.emissive) return;

            if (on) {
                removeEmissiveOverride(child, highlightedMeshes);
                applyEmissiveOverride(child, SELECTION_COLOR, 0.45, selectedMeshes);
            } else {
                removeEmissiveOverride(child, selectedMeshes);
            }
        });
    }

    function clearSelection() {
        if (lastSelected3D) {
            setSelectionHighlight(lastSelected3D, false);
            lastSelected3D = null;
        }
    }

    function clearHighlight() {
        if (lastHovered) {
            setHighlight(lastHovered, false);
            lastHovered = null;
        }
        hoveredObject.value = null;
        cursorStyle.value = 'default';
        if (renderer?.domElement) {
            renderer.domElement.style.cursor = 'default';
        }
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

    function onPointerLeave() {
        clearHighlight();
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

        clearSelection();

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
            const target = interactive || intersects[0].object;
            selectedObject.value = target;
            selectedType.value = 'agent';
            selectedData.value = {
                type: 'agent',
                agentId: wsAgent.id,
                agentName: wsAgent.name,
            };
            lastSelected3D = target;
            setSelectionHighlight(target, true);
            if (options.onAgentClick) options.onAgentClick(selectedData.value);
        } else if (interactive) {
            selectedObject.value = interactive;
            const type = interactive.userData.type;
            selectedType.value = type;

            lastSelected3D = interactive;
            setSelectionHighlight(interactive, true);

            if (type === 'agent') {
                selectedData.value = {
                    type: 'agent',
                    agentId: interactive.userData.agentId,
                    agentName: interactive.userData.agentName,
                };
                if (options.onAgentClick) options.onAgentClick(selectedData.value);
            } else {
                const explicitZoneId = interactive.userData.zoneId;
                const zone = explicitZoneId
                    ? ZONE_DEFS.find((z) => z.id === explicitZoneId)
                    : resolveZoneFromPosition(interactive.position);
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
        el.addEventListener('pointerleave', onPointerLeave);
    }

    function detach() {
        const el = renderer?.domElement;
        if (!el) return;
        el.removeEventListener('pointermove', onPointerMove);
        el.removeEventListener('pointerdown', onPointerDown);
        el.removeEventListener('pointerup', onPointerUp);
        el.removeEventListener('pointerleave', onPointerLeave);
        clearHighlight();
        clearSelection();
        pointerDownPos = null;
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
