import * as THREE from 'three';

export const GodRaysShader = {
    uniforms: {
        tDiffuse: { value: null },
        lightPosition: { value: new THREE.Vector2(0.5, 0.5) },
        exposure: { value: 0.6 },
        decay: { value: 0.95 },
        density: { value: 0.9 },
        weight: { value: 0.4 },
        clampMax: { value: 1.0 },
    },

    vertexShader: /* glsl */`
        varying vec2 vUv;
        void main() {
            vUv = uv;
            gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);
        }`,

    fragmentShader: /* glsl */`
        varying vec2 vUv;
        uniform sampler2D tDiffuse;
        uniform vec2 lightPosition;
        uniform float exposure;
        uniform float decay;
        uniform float density;
        uniform float weight;
        uniform float clampMax;

        const int SAMPLES = 100;

        void main() {
            vec2 delta = vUv - lightPosition;
            vec2 texCoord = vUv;
            delta *= 1.0 / float(SAMPLES) * density;
            float illuminationDecay = 1.0;
            vec4 c = vec4(0.0);

            for(int i = 0; i < SAMPLES; i++) {
                texCoord -= delta;
                vec4 s = texture2D(tDiffuse, texCoord);
                s.rgb *= illuminationDecay * weight;
                c += s;
                illuminationDecay *= decay;
            }

            c.rgb *= exposure;
            c.rgb = clamp(c.rgb, 0.0, clampMax);
            gl_FragColor = c;
        }`
};