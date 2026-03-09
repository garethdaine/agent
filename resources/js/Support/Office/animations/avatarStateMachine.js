export const AVATAR_STATES = {
    IDLE: 'idle',
    WALKING: 'walking',
    TYPING: 'typing',
    READING: 'reading',
    WAITING: 'waiting',
    CHATTING: 'chatting',
};

export class AvatarAnimator {
    constructor(avatarGroup) {
        this.group = avatarGroup;
        this.state = AVATAR_STATES.IDLE;
        this.stateTime = 0;
        this.targetPosition = null;
        this.walkSpeed = 2.5;
        this.walkPath = [];
        this.pathIndex = 0;
        this.onArrive = null;
        this._headRef = null;
        this._bodyRef = null;
        this._armsRef = { left: null, right: null };
        this._legsRef = { left: null, right: null };
        this._baseY = {};
        this._identifyParts();
    }

    _identifyParts() {
        const sorted = [...this.group.children]
            .filter((c) => c.isMesh)
            .sort((a, b) => b.position.y - a.position.y);

        for (const child of sorted) {
            const h = child.geometry?.parameters?.height;
            if (!this._headRef && child.position.y > 0.7) {
                this._headRef = child;
                this._baseY.head = child.position.y;
            } else if (!this._bodyRef && h && h > 0.3 && h < 0.7 && child.position.y > 0.3) {
                this._bodyRef = child;
                this._baseY.body = child.position.y;
            }
        }

        const remaining = sorted.filter((c) => c !== this._bodyRef && c !== this._headRef);
        const arms = remaining.filter((c) => c.position.y > 0.4);
        const legs = remaining.filter((c) => c.position.y <= 0.4);

        if (arms[0]) { this._armsRef.left = arms[0]; this._baseY.leftArm = arms[0].position.y; }
        if (arms[1]) { this._armsRef.right = arms[1]; this._baseY.rightArm = arms[1].position.y; }
        if (legs[0]) { this._legsRef.left = legs[0]; this._baseY.leftLeg = legs[0].position.y; }
        if (legs[1]) { this._legsRef.right = legs[1]; this._baseY.rightLeg = legs[1].position.y; }
    }

    setState(newState) {
        if (this.state === newState) return;
        this._resetPose();
        this.state = newState;
        this.stateTime = 0;
    }

    walkTo(targetPos) {
        this.targetPosition = { x: targetPos.x, z: targetPos.z };
        this.setState(AVATAR_STATES.WALKING);
    }

    walkAlongPath(path) {
        if (!path || path.length === 0) return;

        const cur = this.group.position;
        const filtered = path.filter((wp) => {
            const dx = wp.x - cur.x;
            const dz = wp.z - cur.z;
            return Math.sqrt(dx * dx + dz * dz) > 0.1;
        });
        if (filtered.length === 0) {
            this._resetPose();
            this.setState(AVATAR_STATES.IDLE);
            if (this.onArrive) this.onArrive();
            return;
        }

        this.walkPath = filtered;
        this.pathIndex = 0;
        this.targetPosition = { x: filtered[0].x, z: filtered[0].z };
        this.setState(AVATAR_STATES.WALKING);
    }

    get isWalking() {
        return this.state === AVATAR_STATES.WALKING;
    }

    update(delta) {
        if (Number.isNaN(this.group.position.x) || Number.isNaN(this.group.position.z)) {
            this.group.position.x = 0;
            this.group.position.z = 0;
            this.targetPosition = null;
            this.walkPath = [];
            this._resetPose();
            this.state = AVATAR_STATES.IDLE;
            return;
        }

        this.stateTime += delta;

        switch (this.state) {
            case AVATAR_STATES.IDLE:
                this._animateIdle(delta);
                break;
            case AVATAR_STATES.WALKING:
                this._animateWalking(delta);
                break;
            case AVATAR_STATES.TYPING:
                this._animateTyping(delta);
                break;
            case AVATAR_STATES.READING:
                this._animateReading(delta);
                break;
            case AVATAR_STATES.WAITING:
                this._animateWaiting(delta);
                break;
            case AVATAR_STATES.CHATTING:
                this._animateChatting(delta);
                break;
        }
    }

    _animateIdle(_delta) {
        const bob = Math.sin(this.stateTime * 1.5) * 0.01;
        if (this._bodyRef) this._bodyRef.position.y = this._baseY.body + bob;
        if (this._headRef) this._headRef.position.y = this._baseY.head + bob * 1.2;
    }

    _animateWalking(delta) {
        if (!this.targetPosition) {
            this._resetPose();
            this.setState(AVATAR_STATES.IDLE);
            return;
        }

        const dx = this.targetPosition.x - this.group.position.x;
        const dz = this.targetPosition.z - this.group.position.z;
        const dist = Math.sqrt(dx * dx + dz * dz);

        if (dist < 0.15 || dist === 0) {
            this.group.position.x = this.targetPosition.x;
            this.group.position.z = this.targetPosition.z;

            if (this.walkPath.length > 0 && this.pathIndex < this.walkPath.length - 1) {
                this.pathIndex++;
                this.targetPosition = { x: this.walkPath[this.pathIndex].x, z: this.walkPath[this.pathIndex].z };
                return;
            }

            this.targetPosition = null;
            this.walkPath = [];
            this._resetPose();
            this.setState(AVATAR_STATES.IDLE);
            if (this.onArrive) this.onArrive();
            return;
        }

        const step = Math.min(this.walkSpeed * delta, dist);
        const nx = dx / dist;
        const nz = dz / dist;
        this.group.position.x += nx * step;
        this.group.position.z += nz * step;
        this.group.rotation.y = Math.atan2(dx, dz);

        const walkBob = Math.abs(Math.sin(this.stateTime * 8)) * 0.06;
        if (this._bodyRef) this._bodyRef.position.y = this._baseY.body + walkBob;
        if (this._headRef) this._headRef.position.y = this._baseY.head + walkBob;

        if (this._armsRef.left) {
            this._armsRef.left.rotation.x = Math.sin(this.stateTime * 8) * 0.4;
        }
        if (this._armsRef.right) {
            this._armsRef.right.rotation.x = -Math.sin(this.stateTime * 8) * 0.4;
        }
    }

    _applySittingPose() {
        const drop = 0.08;
        if (this._bodyRef) this._bodyRef.position.y = this._baseY.body - drop;
        if (this._headRef) this._headRef.position.y = this._baseY.head - drop;
        if (this._armsRef.left) this._armsRef.left.position.y = (this._baseY.leftArm ?? 0.55) - drop;
        if (this._armsRef.right) this._armsRef.right.position.y = (this._baseY.rightArm ?? 0.55) - drop;
        if (this._legsRef.left) {
            this._legsRef.left.rotation.x = -Math.PI / 2.2;
            this._legsRef.left.position.y = (this._baseY.leftLeg ?? 0.15) + 0.12;
            this._legsRef.left.position.z = 0.12;
        }
        if (this._legsRef.right) {
            this._legsRef.right.rotation.x = -Math.PI / 2.2;
            this._legsRef.right.position.y = (this._baseY.rightLeg ?? 0.15) + 0.12;
            this._legsRef.right.position.z = 0.12;
        }
        this.group.rotation.y = Math.PI;
    }

    _animateTyping(_delta) {
        this._applySittingPose();
        if (this._armsRef.left) {
            this._armsRef.left.rotation.x = -0.7 + Math.sin(this.stateTime * 12) * 0.08;
        }
        if (this._armsRef.right) {
            this._armsRef.right.rotation.x = -0.7 + Math.cos(this.stateTime * 14) * 0.08;
        }
        if (this._headRef) {
            this._headRef.rotation.x = -0.1 + Math.sin(this.stateTime * 0.5) * 0.02;
        }
    }

    _animateReading(_delta) {
        this._applySittingPose();
        if (this._headRef) {
            this._headRef.rotation.x = -0.15;
            this._headRef.rotation.y = Math.sin(this.stateTime * 0.8) * 0.1;
        }
    }

    _animateWaiting(_delta) {
        const pulse = (Math.sin(this.stateTime * 3) + 1) * 0.5;
        if (this._bodyRef?.material) {
            this._bodyRef.material.emissiveIntensity = pulse * 0.3;
        }
        if (this._bodyRef) {
            this._bodyRef.position.x = Math.sin(this.stateTime * 0.8) * 0.03;
        }
    }

    _animateChatting(_delta) {
        const gesture = Math.sin(this.stateTime * 2.5);
        if (this._armsRef.left) {
            this._armsRef.left.rotation.x = -0.3 + gesture * 0.25;
            this._armsRef.left.rotation.z = -0.15;
        }
        if (this._armsRef.right) {
            this._armsRef.right.rotation.x = -0.3 - gesture * 0.2;
            this._armsRef.right.rotation.z = 0.15;
        }
        if (this._headRef) {
            this._headRef.rotation.y = Math.sin(this.stateTime * 1.2) * 0.12;
            this._headRef.rotation.x = Math.sin(this.stateTime * 0.8) * 0.04;
        }
        const sway = Math.sin(this.stateTime * 1.0) * 0.008;
        if (this._bodyRef) this._bodyRef.position.y = this._baseY.body + sway;
    }

    _resetPose() {
        if (this._armsRef.left) {
            this._armsRef.left.rotation.set(0, 0, 0);
            this._armsRef.left.position.y = this._baseY.leftArm ?? 0.55;
        }
        if (this._armsRef.right) {
            this._armsRef.right.rotation.set(0, 0, 0);
            this._armsRef.right.position.y = this._baseY.rightArm ?? 0.55;
        }
        if (this._legsRef.left) {
            this._legsRef.left.rotation.set(0, 0, 0);
            this._legsRef.left.position.y = this._baseY.leftLeg ?? 0.15;
            this._legsRef.left.position.z = 0;
        }
        if (this._legsRef.right) {
            this._legsRef.right.rotation.set(0, 0, 0);
            this._legsRef.right.position.y = this._baseY.rightLeg ?? 0.15;
            this._legsRef.right.position.z = 0;
        }
        if (this._headRef) {
            this._headRef.rotation.set(0, 0, 0);
            this._headRef.position.y = this._baseY.head ?? 0.95;
        }
        if (this._bodyRef) {
            this._bodyRef.position.x = 0;
            this._bodyRef.position.y = this._baseY.body ?? 0.55;
            if (this._bodyRef.material) {
                this._bodyRef.material.emissiveIntensity = 0;
            }
        }
    }
}
