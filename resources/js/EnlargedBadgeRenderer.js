import * as THREE from 'three';
import {BadgeFactory} from './modules/BadgeFactory.js';

import {EffectComposer} from 'three/addons/postprocessing/EffectComposer.js';
import {RenderPass} from 'three/addons/postprocessing/RenderPass.js';
import {UnrealBloomPass} from 'three/addons/postprocessing/UnrealBloomPass.js';
import {OutputPass} from 'three/addons/postprocessing/OutputPass.js';
import {ShaderPass} from 'three/addons/postprocessing/ShaderPass.js';
import {FXAAShader} from 'three/addons/shaders/FXAAShader.js';
import {ChromaticAberrationPass} from './postprocessing/ChromaticAberrationPass.js';


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
        this.renderer.toneMappingExposure = 1.0;

        this.scene = new THREE.Scene();
        this.camera = new THREE.PerspectiveCamera(50, 1, 0.1, 1000);
        this.camera.position.z = 12;

        this._setupLighting();

        this.composer = new EffectComposer(this.renderer);
        this._setupPostProcessing();

        this.clock = new THREE.Clock();
        this.currentBadge = null;
        this.animationFrameId = null;

        this.onResize = this.onResize.bind(this);
        window.addEventListener('mousemove', this.updateMouseLight.bind(this), false);
    }

    _setupLighting() {
        this.scene.add(new THREE.AmbientLight(0xffffff, 0.2));

        const keyLight = new THREE.DirectionalLight(0xffffff, 0.6);
        keyLight.position.set(-5, 5, 5);
        this.scene.add(keyLight);

        const fillLight = new THREE.PointLight(0x8c9eff, 10, 100, 2);
        fillLight.position.set(5, 0, 5);
        this.scene.add(fillLight);

        this.pointLight = new THREE.PointLight(0xffffff, 15, 100, 2);
        this.pointLight.position.set(0, 0, 8);
        this.scene.add(this.pointLight);
    }

    _setupPostProcessing() {
        const width = window.innerWidth;
        const height = window.innerHeight;

        this.composer.addPass(new RenderPass(this.scene, this.camera));

        const bloomPass = new UnrealBloomPass(new THREE.Vector2(width, height), 0.4, 0.5, 0.85);
        this.composer.addPass(bloomPass);

        this.chromaticAberrationPass = new ChromaticAberrationPass();
        this.composer.addPass(this.chromaticAberrationPass);

        this.fxaaPass = new ShaderPass(FXAAShader);
        const pixelRatio = this.renderer.getPixelRatio();
        this.fxaaPass.material.uniforms['resolution'].value.x = 1 / (width * pixelRatio);
        this.fxaaPass.material.uniforms['resolution'].value.y = 1 / (height * pixelRatio);
        this.composer.addPass(this.fxaaPass);

        this.composer.addPass(new OutputPass());
    }

    onResize() {
        const width = window.innerWidth;
        const height = window.innerHeight;

        this.camera.aspect = width / height;
        this.camera.updateProjectionMatrix();

        this.renderer.setSize(width, height);
        this.composer.setSize(width, height);

        const pixelRatio = this.renderer.getPixelRatio();
        if (this.fxaaPass) {
            this.fxaaPass.material.uniforms['resolution'].value.x = 1 / (width * pixelRatio);
            this.fxaaPass.material.uniforms['resolution'].value.y = 1 / (height * pixelRatio);
        }
    }

    updateMouseLight(event) {
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

        this.currentBadge = BadgeFactory.create(badgeKey, { glowOpacity: 0.1 });
        if (this.currentBadge) {
            this.scene.add(this.currentBadge);
        }

        if (this.animationFrameId) cancelAnimationFrame(this.animationFrameId);
        this.animate();
    }

    animate() {
        this.animationFrameId = requestAnimationFrame(this.animate.bind(this));
        const time = Date.now() * 0.001;

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
