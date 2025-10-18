import * as THREE from 'three';
import {BadgeFactory} from './modules/BadgeFactory.js';

class SssBadgeRenderer {
    constructor() {
        this.canvases = document.querySelectorAll('.badge-showcase-canvas');
        if (this.canvases.length === 0) {return;}

        this.scenes = [];
        this.clock = new THREE.Clock();
        this.mouse = new THREE.Vector2();

        this._initAll();
        this._addEventListeners();
    }

    _initAll() {
        this.canvases.forEach(canvas => {
            const badgeKey = canvas.dataset.badgeKey;
            if (!badgeKey) {return;}

            const scene = new THREE.Scene();
            const renderer = new THREE.WebGLRenderer({
                canvas: canvas, antialias: true, alpha: true, powerPreference: 'high-performance'
            });
            renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
            renderer.toneMapping = THREE.ACESFilmicToneMapping;

            const camera = new THREE.PerspectiveCamera(45, canvas.clientWidth / canvas.clientHeight, 0.1, 100);

            const ambientLight = new THREE.AmbientLight(0xffffff, 2.0);
            scene.add(ambientLight);
            const directionalLight = new THREE.DirectionalLight(0xffffff, 2.5);
            directionalLight.position.set(3, 5, 4);
            scene.add(directionalLight);

            const badgeModel = BadgeFactory.create(badgeKey, {});
            scene.add(badgeModel);

            this._frameObject(badgeModel, camera, canvas);

            this.scenes.push({
                renderer, scene, camera, badgeModel, canvas, isHovered: false
            });
        });

        this._animate();
    }

    _frameObject(object, camera, canvas) {
        const box = new THREE.Box3().setFromObject(object);
        const size = box.getSize(new THREE.Vector3());
        const center = box.getCenter(new THREE.Vector3());

        object.position.x += (object.position.x - center.x);
        object.position.y += (object.position.y - center.y);
        object.position.z += (object.position.z - center.z);

        const maxDim = Math.max(size.x, size.y, size.z);
        const fov = camera.fov * (Math.PI / 180);
        let cameraZ = Math.abs(maxDim / 2 / Math.tan(fov / 2));

        cameraZ *= 1.8;

        camera.position.z = cameraZ;

        camera.near = cameraZ * 0.1;
        camera.far = cameraZ * 2;
        camera.updateProjectionMatrix();
    }

    _addEventListeners() {
        window.addEventListener('mousemove', (event) => {
            this.mouse.x = (event.clientX / window.innerWidth) * 2 - 1;
            this.mouse.y = -(event.clientY / window.innerHeight) * 2 + 1;
        });

        this.scenes.forEach((sceneData, index) => {
            const cardElement = sceneData.canvas.parentElement.parentElement;

            cardElement.addEventListener('mouseenter', () => {
                this.scenes[index].isHovered = true;
            });

            cardElement.addEventListener('mouseleave', () => {
                this.scenes[index].isHovered = false;
            });
        });
    }

    _animate() {
        requestAnimationFrame(() => this._animate());

        const elapsedTime = this.clock.getElapsedTime();

        this.scenes.forEach(s => {
            const {renderer, scene, camera, badgeModel, canvas, isHovered} = s;

            const width = canvas.clientWidth;
            const height = canvas.clientHeight;
            if (canvas.width !== width || canvas.height !== height) {
                renderer.setSize(width, height, false);
                camera.aspect = width / height;
                camera.updateProjectionMatrix();
            }

            const targetScale = isHovered ? 1.15 : 1.0;
            badgeModel.scale.lerp(new THREE.Vector3(targetScale, targetScale, targetScale), 0.1);

            const targetRotation = new THREE.Quaternion();
            const lookAtPosition = new THREE.Vector3(this.mouse.x * 2, this.mouse.y * 2, camera.position.z);
            const tempMatrix = new THREE.Matrix4().lookAt(badgeModel.position, lookAtPosition, badgeModel.up);
            targetRotation.setFromRotationMatrix(tempMatrix);

            const baseRotation = new THREE.Quaternion().setFromEuler(new THREE.Euler(0, elapsedTime * 0.1, 0));
            targetRotation.multiply(baseRotation);

            badgeModel.quaternion.slerp(targetRotation, 0.05);

            if (badgeModel.update) {
                badgeModel.update(elapsedTime);
            }

            renderer.render(scene, camera);
        });
    }
}

export default SssBadgeRenderer;
