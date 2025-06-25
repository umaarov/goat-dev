import * as THREE from 'three';
import {BadgeFactory} from './modules/BadgeFactory.js';

import {RGBELoader} from 'three/addons/loaders/RGBELoader.js';
import {EffectComposer} from 'three/addons/postprocessing/EffectComposer.js';
import {RenderPass} from 'three/addons/postprocessing/RenderPass.js';
// import { SSRPass } from 'three/addons/postprocessing/SSRPass.js';
import {SSAOPass} from 'three/addons/postprocessing/SSAOPass.js';
import {UnrealBloomPass} from 'three/addons/postprocessing/UnrealBloomPass.js';
import {OutputPass} from 'three/addons/postprocessing/OutputPass.js';

export class EnlargedBadgeRenderer {
    constructor(canvas) {
        this.canvas = canvas;

        this.renderer = new THREE.WebGLRenderer({
            canvas: this.canvas,
            antialias: false,
            alpha: true,
            powerPreference: 'high-performance'
        });
        // --- OPTIMIZATION 1: Cap pixel ratio at 1. The biggest performance win. ---
        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 1));
        this.renderer.toneMapping = THREE.ACESFilmicToneMapping;
        this.renderer.toneMappingExposure = 1.0;
        this.renderer.shadowMap.enabled = true;
        this.renderer.shadowMap.type = THREE.PCFSoftShadowMap;

        this.scene = new THREE.Scene();
        this.camera = new THREE.PerspectiveCamera(50, 1, 0.1, 1000);
        this.camera.position.z = 12;

        this._setupLighting();

        this.composer = new EffectComposer(this.renderer);

        this.clock = new THREE.Clock();
        this.currentBadge = null;
        this.animationFrameId = null;

        this.onResize = this.onResize.bind(this);
        window.addEventListener('mousemove', this.updateMouseLight.bind(this), false);

        this._setupEnvironment();
    }

    async _setupEnvironment() {
        const pmremGenerator = new THREE.PMREMGenerator(this.renderer);
        pmremGenerator.compileEquirectangularShader();
        try {
            const rgbeLoader = new RGBELoader();
            const texture = await rgbeLoader.loadAsync('/assets/kloofendal_48d_partly_cloudy_puresky_2k.hdr');
            this.scene.environment = pmremGenerator.fromEquirectangular(texture).texture;
            texture.dispose();
            pmremGenerator.dispose();
        } catch (e) {
            console.error('Failed to load HDRI environment:', e);
        }
        this._setupPostProcessing();
    }

    _setupLighting() {
        const directionalLight = new THREE.DirectionalLight(0xffffff, 2.5);
        directionalLight.position.set(5, 10, 7.5);
        directionalLight.castShadow = true;
        directionalLight.shadow.mapSize.width = 1024;
        directionalLight.shadow.mapSize.height = 1024;
        directionalLight.shadow.camera.near = 0.5;
        directionalLight.shadow.camera.far = 50;
        directionalLight.shadow.bias = -0.0001;
        this.scene.add(directionalLight);

        this.pointLight = new THREE.PointLight(0xffffff, 20, 100, 1.8);
        this.scene.add(this.pointLight);
    }

    _setupPostProcessing() {
        const renderPass = new RenderPass(this.scene, this.camera);
        this.composer.addPass(renderPass);

        const ssaoPass = new SSAOPass(this.scene, this.camera, window.innerWidth, window.innerHeight);
        ssaoPass.kernelRadius = 8;
        ssaoPass.minDistance = 0.005;
        ssaoPass.maxDistance = 0.1;
        this.composer.addPass(ssaoPass);

        const bloomPass = new UnrealBloomPass(new THREE.Vector2(window.innerWidth, window.innerHeight), 0.5, 0.4, 0.85);
        this.composer.addPass(bloomPass);

        const outputPass = new OutputPass();
        this.composer.addPass(outputPass);
    }

    onResize() {
        const width = window.innerWidth;
        const height = window.innerHeight;
        this.camera.aspect = width / height;
        this.camera.updateProjectionMatrix();
        this.renderer.setSize(width, height);
        this.composer.setSize(width, height);
    }

    updateMouseLight(event) {
        if (!this.pointLight) return;
        const mouseX = (event.clientX / window.innerWidth) * 2 - 1;
        const mouseY = -(event.clientY / window.innerHeight) * 2 + 1;
        const vector = new THREE.Vector3(mouseX, mouseY, 0.5).unproject(this.camera);
        const dir = vector.sub(this.camera.position).normalize();
        const distance = -this.camera.position.z / dir.z;
        const pos = this.camera.position.clone().add(dir.multiplyScalar(distance));
        this.pointLight.position.lerp(pos, 0.1);
    }

    show(badgeKey) {
        if (this.currentBadge) this.cleanup();
        this.onResize();
        window.addEventListener('resize', this.onResize);
        this.currentBadge = BadgeFactory.create(badgeKey, {glowOpacity: 0.1});
        if (this.currentBadge) {
            this.currentBadge.traverse((child) => {
                if (child.isMesh) {
                    child.castShadow = true;
                    child.receiveShadow = true;
                }
            });
            this.scene.add(this.currentBadge);
        }
        if (this.animationFrameId) cancelAnimationFrame(this.animationFrameId);
        this.animate();
    }

    animate() {
        this.animationFrameId = requestAnimationFrame(this.animate.bind(this));
        const time = this.clock.getElapsedTime();
        if (this.currentBadge?.update) {
            this.currentBadge.update(time, this.pointLight.position);
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
        window.removeEventListener('resize', this.onResize);
        this.cleanup();
    }
}
