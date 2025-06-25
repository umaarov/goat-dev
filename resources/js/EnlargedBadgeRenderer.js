import * as THREE from 'three';
import { BadgeFactory } from './modules/BadgeFactory.js';

import GUI from 'lil-gui';
import Stats from 'stats.js';
import { RGBELoader } from 'three/addons/loaders/RGBELoader.js';
import { EffectComposer } from 'three/addons/postprocessing/EffectComposer.js';
import { RenderPass } from 'three/addons/postprocessing/RenderPass.js';
import { SSRPass } from 'three/addons/postprocessing/SSRPass.js';
import { SSAOPass } from 'three/addons/postprocessing/SSAOPass.js';
import { UnrealBloomPass } from 'three/addons/postprocessing/UnrealBloomPass.js';
import { OutputPass } from 'three/addons/postprocessing/OutputPass.js';

export class EnlargedBadgeRenderer {
    constructor(canvas) {
        this.canvas = canvas;

        // --- ADDED: Check URL for a `?debug=true` parameter ---
        this.isDebug = new URLSearchParams(window.location.search).has('debug');

        this.renderer = new THREE.WebGLRenderer({
            canvas: this.canvas,
            antialias: false,
            alpha: true,
            powerPreference: 'high-performance'
        });

        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 1));
        this.renderer.toneMapping = THREE.ACESFilmicToneMapping;
        this.renderer.toneMappingExposure = 1.0;
        this.renderer.shadowMap.enabled = true;
        this.renderer.shadowMap.type = THREE.PCFSoftShadowMap;

        this.scene = new THREE.Scene();
        this.camera = new THREE.PerspectiveCamera(50, 1, 0.1, 1000);
        this.camera.position.z = 12;

        this.gui = null;
        this.stats = null;
        this.passes = {};
        this.lights = {};
        this.perf = { FPS: 0, 'Draw Calls': 0, Triangles: 0 };

        this.composer = new EffectComposer(this.renderer);
        this.clock = new THREE.Clock();
        this.currentBadge = null;
        this.animationFrameId = null;

        this.onResize = this.onResize.bind(this);
        window.addEventListener('mousemove', this.updateMouseLight.bind(this), false);

        this._setupLighting();
        this._setupAndLoadAssets();
    }

    async _setupAndLoadAssets() {
        await this._setupEnvironment();
        this._setupPostProcessing();

        // --- MODIFIED: Only set up the debug UI if in debug mode ---
        if (this.isDebug) {
            this._setupDebugUI();
        }
    }

    async _setupEnvironment() {
        const pmremGenerator = new THREE.PMREMGenerator(this.renderer);
        pmremGenerator.compileEquirectangularShader();
        try {
            const rgbeLoader = new RGBELoader();
            const hdrUrl = new URL('/public/assets/kloofendal_48d_partly_cloudy_puresky_2k.hdr', import.meta.url).href;
            const texture = await rgbeLoader.loadAsync(hdrUrl);
            this.scene.environment = pmremGenerator.fromEquirectangular(texture).texture;
            texture.dispose();
            pmremGenerator.dispose();
        } catch (e) {
            console.error("❌ Failed to load HDRI environment:", e);
        }
    }

    _setupLighting() {
        this.lights.directional = new THREE.DirectionalLight(0xffffff, 2.5);
        this.lights.directional.position.set(5, 10, 7.5);
        this.lights.directional.castShadow = true;
        this.lights.directional.shadow.mapSize.width = 1024;
        this.lights.directional.shadow.mapSize.height = 1024;
        this.lights.directional.shadow.camera.near = 0.5;
        this.lights.directional.shadow.camera.far = 50;
        this.lights.directional.shadow.bias = -0.0001;
        this.scene.add(this.lights.directional);

        this.lights.point = new THREE.PointLight(0xffffff, 20, 100, 1.8);
        this.scene.add(this.lights.point);
    }

    _setupPostProcessing() {
        this.composer.addPass(new RenderPass(this.scene, this.camera));

        this.passes.ssr = new SSRPass({ renderer: this.renderer, scene: this.scene, camera: this.camera, width: window.innerWidth, height: window.innerHeight, groundReflector: null, selects: null });
        this.passes.ssr.enabled = false;
        this.composer.addPass(this.passes.ssr);

        this.passes.ssao = new SSAOPass(this.scene, this.camera, window.innerWidth, window.innerHeight);
        this.passes.ssao.kernelRadius = 8;
        this.composer.addPass(this.passes.ssao);

        this.passes.bloom = new UnrealBloomPass(new THREE.Vector2(window.innerWidth, window.innerHeight), 0.5, 0.4, 0.85);
        this.composer.addPass(this.passes.bloom);

        this.composer.addPass(new OutputPass());
    }

    _setupDebugUI() {
        this.gui = new GUI();
        this.gui.hide();

        this.stats = new Stats();
        this.stats.dom.style.display = 'none';
        document.body.appendChild(this.stats.dom);

        const perfFolder = this.gui.addFolder('Performance').close();
        perfFolder.add(this.perf, 'FPS').listen();
        perfFolder.add(this.perf, 'Draw Calls').listen();
        perfFolder.add(this.perf, 'Triangles').listen();

        const rendererFolder = this.gui.addFolder('Renderer & Display');
        rendererFolder.add(this.renderer, 'toneMappingExposure', 0, 2).name('Exposure');
        rendererFolder.add(this.renderer, 'pixelRatio', 0.5, 2).name('Pixel Ratio');

        const shadowFolder = this.gui.addFolder('Shadows');
        shadowFolder.add(this.lights.directional, 'castShadow').name('Enable Shadows');
        shadowFolder.add(this.lights.directional.shadow, 'bias', -0.001, 0.001).name('Shadow Bias');

        const postFolder = this.gui.addFolder('Post-Processing');
        postFolder.add(this.passes.bloom, 'enabled').name('Bloom');
        postFolder.add(this.passes.ssao, 'enabled').name('SSAO');
        postFolder.add(this.passes.ssr, 'enabled').name('SSR (Expensive)');
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
        if (!this.lights.point) return;
        const mouseX = (event.clientX / window.innerWidth) * 2 - 1;
        const mouseY = -(event.clientY / window.innerHeight) * 2 + 1;
        const vector = new THREE.Vector3(mouseX, mouseY, 0.5).unproject(this.camera);
        const dir = vector.sub(this.camera.position).normalize();
        const distance = -this.camera.position.z / dir.z;
        const pos = this.camera.position.clone().add(dir.multiplyScalar(distance));
        this.lights.point.position.lerp(pos, 0.1);
    }

    show(badgeKey) {
        if (this.currentBadge) this.cleanup();
        this.onResize();
        window.addEventListener('resize', this.onResize);

        if (this.isDebug) {
            if (this.gui) this.gui.show();
            if (this.stats) this.stats.dom.style.display = 'block';
        }

        this.currentBadge = BadgeFactory.create(badgeKey, { glowOpacity: 0.1 });
        if (this.currentBadge) {
            this.currentBadge.traverse((child) => {
                if (child.isMesh) {
                    child.castShadow = true;
                    child.receiveShadow = true;
                }
            });
            this.scene.add(this.currentBadge);

            if (this.passes.ssr) {
                const reflectiveMeshes = [];
                this.currentBadge.traverse(child => {
                    if (child.isMesh && child.material instanceof THREE.MeshPhysicalMaterial) {
                        reflectiveMeshes.push(child);
                    }
                });
                this.passes.ssr.selects = reflectiveMeshes;
            }
        }
        if (this.animationFrameId) cancelAnimationFrame(this.animationFrameId);
        this.animate();
    }

    animate() {
        this.animationFrameId = requestAnimationFrame(this.animate.bind(this));

        if (this.isDebug && this.stats) this.stats.begin();

        const time = this.clock.getElapsedTime();
        if (this.currentBadge?.update) {
            this.currentBadge.update(time, this.lights.point.position);
        }
        this.composer.render();

        if (this.isDebug && this.stats) this.stats.end();
        if (this.isDebug && this.gui) {
            this.perf.FPS = Math.round(1 / this.clock.getDelta());
            this.perf['Draw Calls'] = this.renderer.info.render.calls;
            this.perf.Triangles = this.renderer.info.render.triangles;
        }
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

        if (this.isDebug) {
            if (this.gui) this.gui.hide();
            if (this.stats) this.stats.dom.style.display = 'none';
        }

        this.cleanup();
    }
}
