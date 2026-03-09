import {
    Mesh, SphereGeometry, MeshBasicMaterial,
    CanvasTexture, SpriteMaterial, Sprite,
    Vector3,
} from 'three';

const particlePool = [];

function getParticle(color = 0xffffff) {
    if (particlePool.length > 0) {
        const p = particlePool.pop();
        p.material.color.setHex(color);
        p.visible = true;
        return p;
    }
    const geom = new SphereGeometry(0.04, 4, 4);
    const mat = new MeshBasicMaterial({ color, transparent: true, opacity: 0.8 });
    return new Mesh(geom, mat);
}

function releaseParticle(p) {
    p.visible = false;
    particlePool.push(p);
}

export class ParticleEmitter {
    constructor(scene) {
        this.scene = scene;
        this.particles = [];
    }

    emit(position, options = {}) {
        const { count = 8, color = 0x22c55e, spread = 0.5, lifetime = 1.5, speed = 1.5 } = options;
        for (let i = 0; i < count; i++) {
            const p = getParticle(color);
            p.position.copy(position);
            p.position.x += (Math.random() - 0.5) * spread * 0.3;
            p.position.z += (Math.random() - 0.5) * spread * 0.3;
            this.scene.add(p);
            this.particles.push({
                mesh: p,
                velocity: new Vector3(
                    (Math.random() - 0.5) * spread * speed,
                    Math.random() * speed * 1.5 + 0.5,
                    (Math.random() - 0.5) * spread * speed,
                ),
                lifetime,
                age: 0,
            });
        }
    }

    emitSuccess(position) {
        this.emit(position, { count: 12, color: 0x22c55e, spread: 0.6, lifetime: 1.2 });
    }

    emitFailure(position) {
        this.emit(position, { count: 8, color: 0xef4444, spread: 0.4, lifetime: 1.0, speed: 0.8 });
    }

    emitMemory(position) {
        this.emit(position, { count: 6, color: 0x8b5cf6, spread: 0.3, lifetime: 2.0, speed: 0.5 });
    }

    update(delta) {
        for (let i = this.particles.length - 1; i >= 0; i--) {
            const p = this.particles[i];
            p.age += delta;
            if (p.age >= p.lifetime) {
                this.scene.remove(p.mesh);
                releaseParticle(p.mesh);
                this.particles.splice(i, 1);
                continue;
            }
            const t = p.age / p.lifetime;
            p.mesh.position.add(p.velocity.clone().multiplyScalar(delta));
            p.velocity.y -= delta * 2;
            p.mesh.material.opacity = 1.0 - t;
            const scale = 1.0 - t * 0.5;
            p.mesh.scale.setScalar(scale);
        }
    }

    dispose() {
        this.particles.forEach((p) => {
            this.scene.remove(p.mesh);
            releaseParticle(p.mesh);
        });
        this.particles = [];
    }
}

export class SpeechBubble {
    constructor(scene) {
        this.scene = scene;
        this.bubbles = new Map();
    }

    show(agentId, text, targetGroup, options = {}) {
        const { duration = 4, color = '#e0e8ff', bgColor = 'rgba(15,20,40,0.92)', thought = false } = options;

        this.hide(agentId);

        const canvas = document.createElement('canvas');
        canvas.width = 320;
        canvas.height = 96;
        const ctx = canvas.getContext('2d');

        ctx.fillStyle = bgColor;
        ctx.beginPath();
        ctx.roundRect(8, 4, 304, 64, 14);
        ctx.fill();

        if (thought) {
            ctx.fillStyle = bgColor;
            ctx.beginPath();
            ctx.ellipse(160, 76, 6, 4, 0, 0, Math.PI * 2);
            ctx.fill();
            ctx.beginPath();
            ctx.ellipse(155, 86, 3.5, 2.5, 0, 0, Math.PI * 2);
            ctx.fill();
        } else {
            ctx.beginPath();
            ctx.moveTo(148, 68);
            ctx.lineTo(160, 80);
            ctx.lineTo(172, 68);
            ctx.fillStyle = bgColor;
            ctx.fill();
        }

        ctx.strokeStyle = thought ? 'rgba(140,160,255,0.35)' : 'rgba(100,140,255,0.3)';
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.roundRect(8, 4, 304, 64, 14);
        ctx.stroke();

        ctx.font = '15px system-ui, sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillStyle = color;
        const truncated = text.length > 40 ? text.slice(0, 37) + '...' : text;
        ctx.fillText(truncated, 160, 36);

        const texture = new CanvasTexture(canvas);
        const mat = new SpriteMaterial({ map: texture, depthTest: false, transparent: true });
        const sprite = new Sprite(mat);
        sprite.scale.set(2.0, 0.6, 1);
        sprite.position.set(0, 1.75, 0);

        if (targetGroup) {
            targetGroup.add(sprite);
        } else {
            this.scene.add(sprite);
        }

        this.bubbles.set(agentId, {
            sprite,
            parent: targetGroup || this.scene,
            age: 0,
            duration,
            fadeStart: duration * 0.75,
        });
    }

    hide(agentId) {
        const bubble = this.bubbles.get(agentId);
        if (bubble) {
            bubble.parent.remove(bubble.sprite);
            bubble.sprite.material.dispose();
            this.bubbles.delete(agentId);
        }
    }

    update(delta) {
        this.bubbles.forEach((bubble, agentId) => {
            bubble.age += delta;
            if (bubble.age >= bubble.duration) {
                this.hide(agentId);
                return;
            }
            if (bubble.age > bubble.fadeStart) {
                const fadeT = (bubble.age - bubble.fadeStart) / (bubble.duration - bubble.fadeStart);
                bubble.sprite.material.opacity = 1.0 - fadeT;
            }
            bubble.sprite.position.y = 1.75 + Math.sin(bubble.age * 2) * 0.02;
        });
    }

    dispose() {
        this.bubbles.forEach((_, agentId) => this.hide(agentId));
    }
}

export class AmbientAnimations {
    constructor(scene) {
        this.scene = scene;
        this.time = 0;
        this._floatingObjects = [];
    }

    registerFloater(mesh, amplitude = 0.02, frequency = 1.5) {
        this._floatingObjects.push({
            mesh,
            baseY: mesh.position.y,
            amplitude,
            frequency,
            phase: Math.random() * Math.PI * 2,
        });
    }

    update(delta) {
        this.time += delta;
        this._floatingObjects.forEach((f) => {
            f.mesh.position.y = f.baseY + Math.sin(this.time * f.frequency + f.phase) * f.amplitude;
        });
    }

    dispose() {
        this._floatingObjects = [];
    }
}
