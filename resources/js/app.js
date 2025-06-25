import './bootstrap';
import RendererWorker from './workers/renderer.worker.js?worker';


class BadgeCanvasManager {
    constructor() {
        this.container = document.getElementById('badge-container');
        this.canvas = document.getElementById('badge-canvas');

        if (!this.container || !this.canvas) {
            return;
        }

        const badgeRenderArea = { width: 80, height: 80 };
        const originalBadgeLayouts = [
            { key: 'votes', x: 25, y: -15, width: 70, height: 70 },
            { key: 'likes', x: 50, y: 10, width: 70, height: 70 },
            { key: 'posters', x: 90, y: 10, width: 70, height: 70 },
            { key: 'commentators', x: 115, y: -12, width: 70, height: 70 },
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

        const containerSize = { width: 280, height: 155 };

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

        this.worker = new RendererWorker();

        this.worker.onerror = (error) => {
            console.error("❌ An error occurred in the renderer worker:", error.message, error);
        };

        this.worker.onmessage = (event) => {
            if (event.data.type === 'ready') {
                console.log("Badge engine is ready.");
                this.container.style.opacity = 1;
                this.addMouseListener();
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

    addMouseListener() {
        this.container.addEventListener('mousemove', (event) => {
            const containerRect = this.container.getBoundingClientRect();
            const mouseX = event.clientX - containerRect.left;
            const mouseY = event.clientY - containerRect.top;

            let activeBadgeIndex = -1;
            this.badgeLayouts.forEach((layout, index) => {
                if (mouseX >= layout.x && mouseX <= layout.x + layout.width &&
                    mouseY >= layout.y && mouseY <= layout.y + layout.height) {
                    activeBadgeIndex = index;
                }
            });

            this.worker.postMessage({
                type: 'mouseMove',
                payload: {
                    mouseX: (event.clientX / window.innerWidth) * 2 - 1,
                    mouseY: -(event.clientY / window.innerHeight) * 2 + 1,
                    activeBadgeIndex: activeBadgeIndex
                }
            });
        });
    }
}

window.addEventListener('DOMContentLoaded', () => {
    new BadgeCanvasManager();
});
