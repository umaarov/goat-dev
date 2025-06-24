import * as THREE from 'three';
import { Pass, FullScreenQuad } from 'three/addons/postprocessing/Pass.js';
import { GodRaysShader } from '../shaders/GodRaysShader.js';

export class GodRaysPass extends Pass {
    constructor(lightSource, camera, width, height) {
        super();
        this.camera = camera;
        this.lightSource = lightSource;
        this.needsSwap = true;

        this.godRaysMaterial = new THREE.ShaderMaterial(GodRaysShader);
        this.fsQuad = new FullScreenQuad(this.godRaysMaterial);

        this.renderTarget = new THREE.WebGLRenderTarget(width, height, {
            type: THREE.HalfFloatType,
        });
    }

    render(renderer, writeBuffer, readBuffer) {
        const lightPosition = new THREE.Vector3().copy(this.lightSource.position);
        const screenLightPosition = lightPosition.project(this.camera);

        this.godRaysMaterial.uniforms.tDiffuse.value = readBuffer.texture;
        this.godRaysMaterial.uniforms.lightPosition.value.set(
            (screenLightPosition.x + 1) / 2,
            (screenLightPosition.y + 1) / 2
        );

        if (this.renderToScreen) {
            renderer.setRenderTarget(null);
            this.fsQuad.render(renderer);
        } else {
            renderer.setRenderTarget(writeBuffer);
            this.fsQuad.render(renderer);
        }
    }

    setSize(width, height) {
        this.renderTarget.setSize(width, height);
    }
}