uniform float uTime;
uniform vec3 uColor;
uniform vec3 uLightPosition;

varying vec3 vNormal;
varying vec3 vPosition;

void main() {
    vec3 lightDir = normalize(uLightPosition - vPosition);
    float diffuse = max(dot(vNormal, lightDir), 0.0);

    float fresnel = 1.0 - dot(vNormal, vec3(0.0, 0.0, 1.0));
    fresnel = pow(fresnel, 3.0);
    vec3 fresnelColor = vec3(1.0, 0.5, 0.8) * fresnel;

    float glow = sin(uTime * 2.0) * 0.5 + 0.5;
    vec3 finalColor = uColor * diffuse + fresnelColor + uColor * glow * 0.3;

    gl_FragColor = vec4(finalColor, 1.0);
}