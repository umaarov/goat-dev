uniform float uTime;
varying vec3 vNormal;
varying vec3 vPosition;

void main() {
    vNormal = normalize(normalMatrix * normal);
    vPosition = (modelMatrix * vec4(position, 1.0)).xyz;

    float pulse = sin(uTime * 3.0 + position.y * 2.0) * 0.05;
    vec3 displacedPosition = position + normal * pulse;

    gl_Position = projectionMatrix * modelViewMatrix * vec4(displacedPosition, 1.0);
}