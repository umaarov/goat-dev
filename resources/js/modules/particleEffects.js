import * as THREE from 'three';

const createParticleMaterial = (color, size = 0.08) => {
    return new THREE.PointsMaterial({
        color: color,
        size: size,
        transparent: true,
        blending: THREE.AdditiveBlending,
        depthWrite: false,
        sizeAttenuation: true,
    });
};

export function createParticles(type) {
    const particlesGroup = new THREE.Group();
    let geometry, material, count;

    switch (type) {
        case 'votes':
            count = 200;
            geometry = new THREE.BufferGeometry();
            const votesVertices = [];
            for (let i = 0; i < count; i++) {
                const x = (Math.random() * 2 - 1) * 6;
                const y = (Math.random() * 2 - 1) * 6;
                const z = (Math.random() * 2 - 1) * 6;
                votesVertices.push(x, y, z);
            }
            geometry.setAttribute('position', new THREE.Float32BufferAttribute(votesVertices, 3));
            material = createParticleMaterial(0xffd700);
            break;

        case 'posters':
            count = 150;
            geometry = new THREE.BufferGeometry();
            const postersVertices = [];
            for (let i = 0; i < count; i++) {
                const x = (Math.random() - 0.5) * 0.2;
                const y = (Math.random() * 10) - 2.5;
                const z = (Math.random() - 0.5) * 0.2;
                postersVertices.push(x, y, z);
            }
            geometry.setAttribute('position', new THREE.Float32BufferAttribute(postersVertices, 3));
            material = createParticleMaterial(0xcccccc, 0.05);
            break;

        case 'likes':
            count = 100;
            geometry = new THREE.BufferGeometry();
            const likesVertices = [];
            const heartShape = new THREE.Shape();
            heartShape.moveTo(2.5, 2.5);
            heartShape.bezierCurveTo(2.5, 2.5, 2, 0, 0, 0);
            heartShape.bezierCurveTo(-3, 0, -3, 3.5, -3, 3.5);
            heartShape.bezierCurveTo(-3, 5.5, -1, 7.7, 2.5, 9.5);
            heartShape.bezierCurveTo(6, 7.7, 8, 5.5, 8, 3.5);
            heartShape.bezierCurveTo(8, 3.5, 8, 0, 5, 0);
            heartShape.bezierCurveTo(3.5, 0, 2.5, 2.5, 2.5, 2.5);
            const points = heartShape.getPoints(50);
            for (let i = 0; i < count; i++) {
                const p = points[i % points.length];
                const x = (p.x - 2.5) * 0.4 + (Math.random() - 0.5) * 1.5;
                const y = (p.y - 4.5) * 0.4 + (Math.random() - 0.5) * 1.5;
                const z = (Math.random() - 0.5) * 1.5;
                likesVertices.push(x, y, z);
            }
            geometry.setAttribute('position', new THREE.Float32BufferAttribute(likesVertices, 3));
            material = createParticleMaterial(0xff0055, 0.1);
            break;

        case 'commentators':
            count = 300;
            geometry = new THREE.BufferGeometry();
            const commentatorsVertices = [];
            for (let i = 0; i < count; i++) {
                const radius = 3.5;
                const theta = Math.random() * Math.PI * 2;
                const phi = Math.acos(2 * Math.random() - 1);
                const x = radius * Math.sin(phi) * Math.cos(theta);
                const y = radius * Math.sin(phi) * Math.sin(theta);
                const z = radius * Math.cos(phi);
                commentatorsVertices.push(x, y, z);
            }
            geometry.setAttribute('position', new THREE.Float32BufferAttribute(commentatorsVertices, 3));
            material = createParticleMaterial(0x8888ff, 0.06);
            break;

        default:
            geometry = new THREE.BufferGeometry();
            material = createParticleMaterial(0xffffff);
            break;
    }

    const particles = new THREE.Points(geometry, material);
    particlesGroup.add(particles);
    return particlesGroup;
}