import * as THREE from "three";
import heartVertexShader from '../shaders/likes_badge/vertex.glsl?raw';
import heartFragmentShader from '../shaders/likes_badge/fragment.glsl?raw';
import inkblotVertexShader from '../shaders/ink_blot/vertex.glsl?raw';
import inkblotFragmentShader from '../shaders/ink_blot/fragment.glsl?raw';
import {createParticles} from './particleEffects.js';

let wasmModule;

function createGlowSpriteMaterial(color) {
    const canvas = new OffscreenCanvas(128, 128);
    const context = canvas.getContext('2d');
    const gradient = context.createRadialGradient(canvas.width / 2, canvas.height / 2, 0, canvas.width / 2, canvas.height / 2, canvas.width / 2);
    gradient.addColorStop(0.2, 'rgba(255, 255, 255, 1.0)');
    gradient.addColorStop(1.0, 'rgba(255, 255, 255, 0)');

    context.fillStyle = gradient;
    context.fillRect(0, 0, canvas.width, canvas.height);

    const texture = new THREE.CanvasTexture(canvas);
    return new THREE.SpriteMaterial({
        map: texture,
        color: color,
        transparent: true,
        blending: THREE.AdditiveBlending,
        depthTest: false,
        opacity: 0.1
    });
}

export class BadgeFactory {
    static setWasm(module) {
        wasmModule = module;
    }

    static create(badgeName) {
        switch (badgeName) {
            case 'votes':
                return this.createVotesBadge();
            case 'posters':
                return this.createPostersBadge();
            case 'likes':
                return this.createLikesBadge();
            case 'commentators':
                return this.createCommentatorsBadge();
            default:
                return null;
        }
    }

    static createVotesBadge() {
        const group = new THREE.Group();
        const hornMaterial = new THREE.MeshPhysicalMaterial({
            color: 0xffd700, metalness: 1.0, roughness: 0.1, sheen: 1, sheenColor: 0xffeec9
        });
        const curve = new THREE.CatmullRomCurve3([new THREE.Vector3(0, -3.5, 0), new THREE.Vector3(0, 0, 1.2), new THREE.Vector3(1.8, 2.5, 0), new THREE.Vector3(0, 3.5, -1.2), new THREE.Vector3(-1.8, 4.5, 0)]);
        const hornGeometry = new THREE.TubeGeometry(curve, 128, 0.3, 16, false);
        const horn1 = new THREE.Mesh(hornGeometry, hornMaterial);
        const horn2 = horn1.clone();
        horn2.scale.x = -1;
        const ringGeo = new THREE.TorusGeometry(2.5, 0.05, 16, 100);
        const ringMat = new THREE.MeshBasicMaterial({
            color: 0xffe79e, transparent: true, opacity: 0.7, side: THREE.DoubleSide
        });
        const ring1 = new THREE.Mesh(ringGeo, ringMat);
        ring1.rotation.x = Math.PI / 2.2;
        const ring2 = ring1.clone();
        ring2.rotation.x = Math.PI / 1.8;
        ring2.scale.set(0.8, 0.8, 0.8);

        const externalGlow = new THREE.Sprite(createGlowSpriteMaterial(0xffd700));

        externalGlow.scale.set(11, 11, 1);

        group.add(externalGlow, horn1, horn2, ring1, ring2);

        group.scale.set(0.55, 0.55, 0.55);

        group.update = (time) => {
            group.rotation.y = time * 0.2;
            ring1.rotation.z = -time * 0.4;
            ring2.rotation.z = time * 0.5;
        };
        return group;
    }

    static createPostersBadge() {
        const group = new THREE.Group();
        const quillMaterial = new THREE.MeshPhysicalMaterial({
            color: 0xdddddd,
            metalness: 1.0,
            roughness: 0.15,
            iridescence: 1,
            iridescenceIOR: 1.9,
            emissive: 0xffffff,
            emissiveIntensity: 0.05
        });
        const quillBody = new THREE.Mesh(new THREE.CylinderGeometry(0.05, 0.2, 4.5, 32), quillMaterial);
        const quillTip = new THREE.Mesh(new THREE.ConeGeometry(0.05, 0.5, 32), quillMaterial);
        quillTip.position.y = -2.5;
        const quillGroup = new THREE.Group();
        quillGroup.add(quillBody, quillTip);
        const inkBlotMat = new THREE.ShaderMaterial({
            vertexShader: inkblotVertexShader,
            fragmentShader: inkblotFragmentShader,
            uniforms: {uTime: {value: 0}},
            transparent: true,
            blending: THREE.AdditiveBlending,
            depthWrite: false,
        });
        const inkBlotGeo = new THREE.PlaneGeometry(8, 8);
        const inkBlot = new THREE.Mesh(inkBlotGeo, inkBlotMat);
        inkBlot.position.z = -2;
        const ringGeo = new THREE.TorusGeometry(1, 0.03, 16, 100);
        const ringMat = new THREE.MeshBasicMaterial({color: 0xffffff, side: THREE.DoubleSide});
        const orbitRing = new THREE.Mesh(ringGeo, ringMat);
        orbitRing.rotation.x = Math.PI / 2;

        const externalGlow = new THREE.Sprite(createGlowSpriteMaterial(0xaaaaff));
        externalGlow.scale.set(9, 9, 1);

        group.add(externalGlow, quillGroup, inkBlot, orbitRing);
        group.rotation.z = Math.PI / 8;
        group.update = (time) => {
            group.rotation.y = time * 0.1;
            inkBlotMat.uniforms.uTime.value = time;
            quillGroup.position.y = Math.sin(time * 3) * 0.1;
            orbitRing.position.y = Math.cos(time * 3) * 0.1;
            orbitRing.rotation.z = time * 0.2;
        };
        return group;
    }

    static createLikesBadge() {
        const group = new THREE.Group();
        const heartShape = new THREE.Shape();
        heartShape.moveTo(2.5, 2.5);
        heartShape.bezierCurveTo(2.5, 2.5, 2, 0, 0, 0);
        heartShape.bezierCurveTo(-3, 0, -3, 3.5, -3, 3.5);
        heartShape.bezierCurveTo(-3, 5.5, -1, 7.7, 2.5, 9.5);
        heartShape.bezierCurveTo(6, 7.7, 8, 5.5, 8, 3.5);
        heartShape.bezierCurveTo(8, 3.5, 8, 0, 5, 0);
        heartShape.bezierCurveTo(3.5, 0, 2.5, 2.5, 2.5, 2.5);
        const heartGeom = new THREE.ExtrudeGeometry(heartShape, {
            depth: 1.2, bevelEnabled: true, bevelSegments: 4, steps: 2, bevelSize: 0.6, bevelThickness: 0.6
        }).center().scale(0.35, 0.35, 0.35);
        const heartMat = new THREE.ShaderMaterial({
            vertexShader: heartVertexShader, fragmentShader: heartFragmentShader, uniforms: {
                uTime: {value: 0},
                uColor: {value: new THREE.Color(0xff0055)},
                uLightPosition: {value: new THREE.Vector3(0, 0, 8)}
            }
        });
        const heart = new THREE.Mesh(heartGeom, heartMat);
        heart.rotation.z = Math.PI;

        const particles = createParticles('likes');
        const externalGlow = new THREE.Sprite(createGlowSpriteMaterial(0xff0055));
        externalGlow.scale.set(9, 9, 1);

        group.add(externalGlow, heart, particles);
        group.update = (time, lightPosition) => {
            heartMat.uniforms.uTime.value = time;
            if (lightPosition) {
                heartMat.uniforms.uLightPosition.value.copy(lightPosition);
            }
            group.rotation.y = -time * 0.15;
            const scale = 1 + Math.sin(time * 2.0) * 0.05;
            particles.scale.set(scale, scale, scale);
            particles.rotation.y = time * 0.2;
        };
        return group;
    }

    static createCommentatorsBadge() {
        if (!wasmModule) return new THREE.Group();
        const group = new THREE.Group();
        const weaverMat = new THREE.MeshPhysicalMaterial({
            color: 0x777799, metalness: 1.0, roughness: 0.25, emissive: 0x5555ff, emissiveIntensity: 0.1
        });
        const createWeaver = wasmModule.cwrap('createComplexWeaverGeometry', 'number', ['number', 'number', 'number', 'number', 'number', 'number']);
        const sizePtr = wasmModule._malloc(4);
        const verticesPtr = createWeaver(12, 1.6, 0.12, 5, 7, sizePtr);
        const size = wasmModule.getValue(sizePtr, 'i32');
        const weaverVerticesRaw = new Float32Array(wasmModule.wasmMemory.buffer, verticesPtr, size);
        const points = [];
        for (let i = 0; i < weaverVerticesRaw.length; i += 3) {
            points.push(new THREE.Vector3(weaverVerticesRaw[i], weaverVerticesRaw[i + 1], weaverVerticesRaw[i + 2]));
        }
        wasmModule._free(verticesPtr);
        wasmModule._free(sizePtr);
        const curve = new THREE.CatmullRomCurve3(points, true);
        const weaverGeom = new THREE.TubeGeometry(curve, 256, 0.35, 20, true);
        const weaver = new THREE.Mesh(weaverGeom, weaverMat);

        const coreGeo = new THREE.IcosahedronGeometry(0.5, 1);
        const coreMat = new THREE.MeshBasicMaterial({color: 0x9999ff, transparent: true, opacity: 0.8});
        const core = new THREE.Mesh(coreGeo, coreMat);

        const externalGlow = new THREE.Sprite(createGlowSpriteMaterial(0x6c5ce7));
        externalGlow.scale.set(7, 7, 1);

        group.add(externalGlow, weaver, core);
        group.update = (time) => {
            group.rotation.x = time * 0.1;
            group.rotation.y = time * 0.15;
            const scale = 1 + Math.sin(time * 4) * 0.2;
            core.scale.set(scale, scale, scale);
        };
        return group;
    }
}
