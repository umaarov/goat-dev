// import './bootstrap';
import RendererWorker from './workers/renderer.worker.js?worker';
import {EnlargedBadgeRenderer} from './EnlargedBadgeRenderer.js';

class BadgeCanvasManager {
    constructor() {
        this.container = document.getElementById('badge-container');
        this.canvas = document.getElementById('badge-canvas');

        if (!this.container || !this.canvas) {
            return;
        }

        console.log('BadgeCanvasManager: Initializing...');

        const badgeRenderArea = {width: 80, height: 80};
        const originalBadgeLayouts = [
            {key: 'votes', x: 15, y: -30, width: 110, height: 110},
            {key: 'likes', x: 55, y: 10, width: 80, height: 80},
            {key: 'posters', x: 95, y: 12, width: 70, height: 70},
            {key: 'commentators', x: 120, y: -12, width: 70, height: 70},
        ];

        this.badgeLayouts = originalBadgeLayouts.map(layout => {
            const offsetX = (badgeRenderArea.width - layout.width) / 2;
            const offsetY = (badgeRenderArea.height - layout.height) / 2;
            return {
                key: layout.key,
                x: layout.x - offsetX,
                y: layout.y - offsetY,
                width: badgeRenderArea.width,
                height: badgeRenderArea.height,
                badgeWidth: layout.width,
                badgeHeight: layout.height
            };
        });

        const containerSize = {width: 280, height: 155};

        let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
        this.badgeLayouts.forEach(layout => {
            minX = Math.min(minX, layout.x);
            minY = Math.min(minY, layout.y);
            maxX = Math.max(maxX, layout.x + layout.width);
            maxY = Math.max(maxY, layout.y + layout.height);
        });

        const groupWidth = maxX - minX;
        const groupHeight = maxY - minY;

        const groupOffsetX = (containerSize.width - groupWidth) / 2 - minX;
        const groupOffsetY = (containerSize.height - groupHeight) / 2 - minY;

        this.badgeLayouts.forEach(layout => {
            layout.x += groupOffsetX;
            layout.y += groupOffsetY;
        });

        console.log('BadgeCanvasManager: Initializing...');
        this.enlargedContainer = document.getElementById('badge-enlarged-container');
        this.enlargedCanvas = document.getElementById('enlarged-badge-canvas');
        this.enlargedBadgeName = document.getElementById('enlarged-badge-name');
        this.closeEnlargedButton = document.getElementById('close-enlarged-badge');

        if (!this.enlargedContainer) {
            console.error('❌ CRITICAL: Enlarged container #badge-enlarged-container NOT FOUND. Check your HTML.');
        } else {
            console.log('✅ Enlarged container found.');
        }

        this.enlargedRenderer = (this.enlargedContainer) ? new EnlargedBadgeRenderer(this.enlargedCanvas) : null;

        if (!this.enlargedRenderer) {
            console.error('❌ CRITICAL: Enlarged renderer was NOT created.');
        }

        this.badgeDetails = {
            'votes': {title: 'The Gilded Horn', glowClass: 'glow-yellow'},
            'likes': {title: 'Heart of the Community', glowClass: 'glow-pink'},
            'posters': {title: 'The Creator\'s Quill', glowClass: 'glow-silver'},
            'commentators': {title: 'The Dialogue Weaver', glowClass: 'glow-purple'}
        };

        this.worker = new RendererWorker();
        this.activeBadgeIndex = -1;

        this.worker.onerror = (error) => {
            console.error("❌ An error occurred in the renderer worker:", error.message, error);
        };

        this.worker.onerror = (error) => {
            console.error("❌ An error occurred in the renderer worker:", error.message, error);
        };

        this.worker.onmessage = (event) => {
            if (event.data.type === 'ready') {
                console.log("Badge engine is ready.");
                this.container.style.opacity = 1;
                this.addMouseListeners();
            }
        };


        this.init();
    }

    init() {
        const offscreen = this.canvas.transferControlToOffscreen();
        const rect = this.container.getBoundingClientRect();

        this.worker.postMessage({
            type: 'init',
            payload: {
                canvas: offscreen,
                width: rect.width,
                height: rect.height,
                pixelRatio: Math.min(window.devicePixelRatio, 2),
                layouts: this.badgeLayouts,
                wasm: {
                    url: '/assets/wasm/geometry_optimizer.js'
                }
            }
        }, [offscreen]);
    }

    addMouseListeners() {
        this.container.addEventListener('mousemove', (event) => {
            const containerRect = this.container.getBoundingClientRect();
            const mouseX = event.clientX - containerRect.left;
            const mouseY = event.clientY - containerRect.top;

            let newActiveBadgeIndex = -1;
            this.badgeLayouts.forEach((layout, index) => {
                const centerX = layout.x + layout.width / 2;
                const centerY = layout.y + layout.height / 2;
                const radius = (layout.badgeWidth || layout.width) * 0.45;
                const distance = Math.sqrt(Math.pow(mouseX - centerX, 2) + Math.pow(mouseY - centerY, 2));

                if (distance < radius) {
                    newActiveBadgeIndex = index;
                }
            });

            if (newActiveBadgeIndex !== this.activeBadgeIndex) {
                this.activeBadgeIndex = newActiveBadgeIndex;
                this.worker.postMessage({
                    type: 'setActiveBadge',
                    payload: {activeBadgeIndex: this.activeBadgeIndex}
                });
            }

            this.worker.postMessage({
                type: 'mouseMove',
                payload: {
                    mouseX: (event.clientX / window.innerWidth) * 2 - 1,
                    mouseY: -(event.clientY / window.innerHeight) * 2 + 1,
                }
            });
        });

        this.container.addEventListener('click', () => {
            console.log(`[Click Event] Fired on badge container. Hovered badge index: ${this.activeBadgeIndex}`);
            if (this.activeBadgeIndex !== -1) {
                const badgeKey = this.badgeLayouts[this.activeBadgeIndex].key;
                console.log(`%c→ Attempting to show enlarged badge for key: '${badgeKey}'`, 'color: #00ffaa');
                this.showEnlargedBadge(badgeKey);
            }
        });

        if (this.enlargedRenderer) {
            this.closeEnlargedButton.addEventListener('click', (e) => {
                e.stopPropagation();
                this.hideEnlargedBadge();
            });
            this.enlargedContainer.addEventListener('click', () => {
                this.hideEnlargedBadge();
            });
        }
    }

    showEnlargedBadge(badgeKey) {
        if (!this.enlargedRenderer) return;

        const details = this.badgeDetails[badgeKey] || {title: 'Badge', glowClass: ''};

        this.enlargedBadgeName.textContent = details.title;
        this.enlargedBadgeName.className = 'text-white text-3xl font-bold mb-4';
        if (details.glowClass) {
            this.enlargedBadgeName.classList.add(details.glowClass);
        }

        this.enlargedRenderer.show(badgeKey);
        this.enlargedContainer.style.display = 'flex';
        setTimeout(() => {
            this.enlargedContainer.classList.add('visible');
        }, 10);
    }

    hideEnlargedBadge() {
        if (!this.enlargedRenderer) return;
        this.enlargedContainer.classList.remove('visible');
        setTimeout(() => {
            this.enlargedContainer.style.display = 'none';
            this.enlargedRenderer.stop();
            this.enlargedBadgeName.className = 'text-white text-3xl font-bold mb-4';
        }, 300);
    }

}

window.addEventListener('DOMContentLoaded', () => {
    new BadgeCanvasManager();
});
