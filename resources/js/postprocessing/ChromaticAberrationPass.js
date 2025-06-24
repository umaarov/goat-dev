import * as THREE from 'three';
import { Pass, FullScreenQuad } from 'three/addons/postprocessing/Pass.js';

export class ChromaticAberrationPass extends Pass {
    constructor(amount = 0.005, radialModulation = true, startRadius = 0, endRadius = 0.7) {
        super();
        this.shader = {
            uniforms: {
                'tDiffuse': { value: null },
                'amount': { value: amount },
                'radialModulation': { value: radialModulation ? 1.0 : 0.0 },
                'startRadius': { value: startRadius },
                'endRadius': { value: endRadius },
                'resolution': { value: new THREE.Vector2() }
            },
            vertexShader: /* glsl */`
                varying vec2 vUv;
                void main() {
                    vUv = uv;
                    gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);
                }`,
            fragmentShader: /* glsl */`
                precision highp float;

                uniform sampler2D tDiffuse;
                uniform float amount;
                uniform float radialModulation;
                uniform float startRadius;
                uniform float endRadius;
                uniform vec2 resolution;
                varying vec2 vUv;

                void main() {
                    vec2 center = vec2(0.5, 0.5);
                    vec2 p = vUv - center;
                    float d = length(p);
                    float mod = 1.0;

                    if (radialModulation > 0.5) {
                        mod = smoothstep(startRadius, endRadius, d);
                    }

                    vec4 originalColor = texture(tDiffuse, vUv);
                    vec2 R_uv = vUv + p * amount * mod;
                    vec2 B_uv = vUv - p * amount * mod;
                    
                    float r = texture(tDiffuse, R_uv).r;
                    float g = originalColor.g;
                    float b = texture(tDiffuse, B_uv).b;

                    gl_FragColor = vec4(r, g, b, originalColor.a);
                }`
        };

        this.material = new THREE.ShaderMaterial(this.shader);
        this.fsQuad = new FullScreenQuad(this.material);
    }

    render(renderer, writeBuffer, readBuffer) {
        this.material.uniforms['tDiffuse'].value = readBuffer.texture;
        this.material.uniforms['resolution'].value.set(renderer.domElement.width, renderer.domElement.height);

        if (this.renderToScreen) {
            renderer.setRenderTarget(null);
            this.fsQuad.render(renderer);
        } else {
            renderer.setRenderTarget(writeBuffer);
            this.fsQuad.render(renderer);
        }
    }

    setSize(width, height) {
        this.material.uniforms['resolution'].value.set(width, height);
    }
}