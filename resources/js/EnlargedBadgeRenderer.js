// public/js/EnlargedBadgeRenderer.js (New File)

import * as THREE from 'three';
import {BadgeFactory} from './modules/BadgeFactory.js';
import {EffectComposer} from 'three/addons/postprocessing/EffectComposer.js';
import {RenderPass} from 'three/addons/postprocessing/RenderPass.js';
import {UnrealBloomPass} from 'three/addons/postprocessing/UnrealBloomPass.js';
import {OutputPass} from 'three/addons/postprocessing/OutputPass.js';

export class EnlargedBadgeRenderer {
    constructor(canvas) {
        this.canvas = canvas;
        this.renderer = new THREE.WebGLRenderer({
            canvas: this.canvas,
            antialias: true,
            alpha: true
        });
        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        this.renderer.toneMapping = THREE.ACESFilmicToneMapping;
        this.renderer.toneMappingExposure = 1.2;

        this.scene = new THREE.Scene();
        this.camera = new THREE.PerspectiveCamera(50, 1, 0.1, 1000);
        this.camera.position.z = 10;

        this.pointLight = new THREE.PointLight(0xffffff, 50, 100, 2);
        this.pointLight.position.set(0, 3, 8);
        this.scene.add(new THREE.AmbientLight(0xffffff, 0.7));
        this.scene.add(this.pointLight);

        this.composer = new EffectComposer(this.renderer);
        this._setupPostProcessing();

        this.clock = new THREE.Clock();
        this.currentBadge = null;
        this.animationFrameId = null;

        window.addEventListener('mousemove', this.updateMouseLight.bind(this), false);
    }

    _setupPostProcessing() {
        const renderPass = new RenderPass(this.scene, this.camera);
        const bloomPass = new UnrealBloomPass(new THREE.Vector2(this.canvas.width, this.canvas.height), 1.0, 0.6, 0);
        const outputPass = new OutputPass();

        this.composer.addPass(renderPass);
        this.composer.addPass(bloomPass);
        this.composer.addPass(outputPass);
    }

    updateMouseLight(event) {
        if (!this.currentBadge) return;
        const mouseX = (event.clientX / window.innerWidth) * 2 - 1;
        const mouseY = -(event.clientY / window.innerHeight) * 2 + 1;
        const vector = new THREE.Vector3(mouseX, mouseY, 0.5);
        vector.unproject(this.camera);
        const dir = vector.sub(this.camera.position).normalize();
        const distance = -this.camera.position.z / dir.z;
        const pos = this.camera.position.clone().add(dir.multiplyScalar(distance));
        this.pointLight.position.lerp(pos, 0.1);
    }

    show(badgeKey) {
        if (this.currentBadge) this.cleanup();

        const size = Math.min(window.innerWidth * 0.8, window.innerHeight * 0.8, 600);
        this.renderer.setSize(size, size);
        this.composer.setSize(size, size);
        this.camera.aspect = 1;
        this.camera.updateProjectionMatrix();

        this.currentBadge = BadgeFactory.create(badgeKey);
        if (this.currentBadge) {
            this.currentBadge.scale.set(1.5, 1.5, 1.5);
            this.scene.add(this.currentBadge);
        }

        if (this.animationFrameId) cancelAnimationFrame(this.animationFrameId);
        this.animate();
    }

    animate() {
        this.animationFrameId = requestAnimationFrame(this.animate.bind(this));
        const elapsedTime = this.clock.getElapsedTime();
        if (this.currentBadge?.update) {
            this.currentBadge.update(elapsedTime, this.pointLight.position);
        }
        this.composer.render();
    }

    cleanup() {
        if (this.currentBadge) {
            this.scene.remove(this.currentBadge);
            this.currentBadge.traverse(child => {
                if (child.isMesh) {
                    child.geometry.dispose();
                    if (Array.isArray(child.material)) {
                        child.material.forEach(material => material.dispose());
                    } else if (child.material) {
                        child.material.dispose();
                    }
                }
            });
            this.currentBadge = null;
        }
    }

    stop() {
        if (this.animationFrameId) {
            cancelAnimationFrame(this.animationFrameId);
            this.animationFrameId = null;
        }
        this.cleanup();
    }
}
