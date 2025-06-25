import * as THREE from 'three';
import {BadgeFactory} from '../modules/BadgeFactory.js';

let renderer, scene, pointLight;
let badges = [];
let wasmModule;
let activeBadgeIndex = -1;

self.onmessage = async (event) => {
    const {type, payload} = event.data;

    if (type === 'init') {
        if (!wasmModule && payload.wasm) {
            const wasmFactory = await import(payload.wasm.url);
            wasmModule = await wasmFactory.default();
            BadgeFactory.setWasm(wasmModule);
        }
        init(payload);
        animate();
        self.postMessage({type: 'ready'});
        return;
    }

    if (!renderer) {
        return;
    }

    switch (type) {
        case 'mouseMove':
            updateMouseLight(payload);
            break;
        case 'setActiveBadge':
            activeBadgeIndex = payload.activeBadgeIndex;
            break;
    }
};

function init({canvas, width, height, pixelRatio, layouts}) {
    renderer = new THREE.WebGLRenderer({canvas, antialias: true, alpha: true});
    renderer.setSize(width, height, false);
    renderer.setPixelRatio(pixelRatio);
    renderer.setClearAlpha(0);
    renderer.setScissorTest(true);
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.autoClear = false;

    scene = new THREE.Scene();
    setupLighting();

    layouts.forEach(layout => {
        const badgeModel = BadgeFactory.create(layout.key);
        if (badgeModel) {
            const badgeCamera = new THREE.PerspectiveCamera(50, layout.width / layout.height, 0.1, 1000);

            const baselineHeight = 40;
            const baselineZ = 8;
            const effectiveBadgeHeight = layout.badgeHeight || layout.height;
            badgeCamera.position.z = baselineZ * (effectiveBadgeHeight / baselineHeight);

            badges.push({
                model: badgeModel, layout: layout, camera: badgeCamera,
            });
            scene.add(badgeModel);
        }
    });
}

function setupLighting() {
    scene.add(new THREE.AmbientLight(0xffffff, 0.5));
    pointLight = new THREE.PointLight(0xffffff, 25, 100, 2);
    pointLight.position.set(0, 5, 5);
    scene.add(pointLight);
}

function updateMouseLight({mouseX, mouseY}) {
    if (!pointLight) return;
    const targetX = mouseX * 5;
    const targetY = mouseY * 5;
    const targetPosition = new THREE.Vector3(targetX, targetY, 5);
    pointLight.position.lerp(targetPosition, 0.1);
}

function animate() {
    requestAnimationFrame(animate);

    const time = Date.now() * 0.001;
    const baseScale = new THREE.Vector3(1, 1, 1);
    const hoverScale = new THREE.Vector3(1.15, 1.15, 1.15);

    renderer.setScissor(0, 0, renderer.domElement.width, renderer.domElement.height);
    renderer.clear(true, true, true);

    badges.forEach(b => b.model.visible = false);

    badges.forEach((badge, index) => {
        const {model, layout, camera} = badge;

        const targetScale = (index === activeBadgeIndex) ? hoverScale : baseScale;
        model.scale.lerp(targetScale, 0.15);

        model.visible = true;

        if (model.update) {
            model.update(time, pointLight.position);
        }

        renderer.setViewport(layout.x, layout.y, layout.width, layout.height);
        renderer.setScissor(layout.x, layout.y, layout.width, layout.height);

        renderer.clearDepth();
        renderer.render(scene, camera);

        model.visible = false;
    });
}
