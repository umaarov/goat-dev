<style>
    .shimmer-bg {
        animation-duration: 1.5s;
        animation-fill-mode: forwards;
        animation-iteration-count: infinite;
        animation-name: shimmer;
        animation-timing-function: linear;
        background-color: #e2e8f0;
        background-image: linear-gradient(to right, #e2e8f0 0%, #f8fafc 50%, #e2e8f0 100%);
        background-repeat: no-repeat;
        background-size: 200% 100%;
    }

    @keyframes shimmer {
        0% {
            background-position: 200% 0;
        }
        100% {
            background-position: -200% 0;
        }
    }

    .shimmer-card {
        background-color: #fff;
        border-radius: 0.5rem;
        box-shadow: inset 0 0 0 0.5px rgba(0, 0, 0, 0.2);
        margin-bottom: 1rem;
        overflow: hidden;
    }

    .shimmer-divider {
        border-bottom-width: 1px;
        border-color: #e5e7eb;
        width: 100%;
        margin-top: 1rem;
    }

    .dark .shimmer-bg {
        background-color: #374151;
        background-image: linear-gradient(to right, #374151 0%, #4b5563 50%, #374151 100%);
    }

    .dark .shimmer-card {
        background-color: rgb(31 41 55);
        box-shadow: inset 0 0 0 0.5px rgba(255, 255, 255, 0.1);
    }

    .dark .shimmer-divider {
        border-color: #4b5563;
    }

    .shimmer-content {
        padding: 1rem;
    }

    .shimmer-header {
        display: flex;
        align-items: center;
    }

    .shimmer-pfp {
        height: 2.5rem;
        width: 2.5rem;
        border-radius: 9999px;
    }

    .shimmer-info {
        margin-left: 0.75rem;
        flex-grow: 1;
    }

    .shimmer-line {
        border-radius: 0.25rem;
    }

    .shimmer-line-header {
        height: 1rem;
        margin-bottom: 0.5rem;
    }

    .shimmer-line-subheader {
        height: 0.75rem;
    }

    .shimmer-question {
        margin: 1rem auto 0 auto;
        height: 1.25rem;
    }

    .shimmer-images-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
        padding: 1rem;
    }

    .shimmer-image-placeholder {
        aspect-ratio: 1 / 1;
        border-radius: 0.375rem;
        width: 100%;
    }

    .shimmer-buttons-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
        padding: 0 1rem 1rem 1rem;
    }

    .shimmer-button-placeholder {
        height: 2.5rem;
        width: 100%;
        border-radius: 0.375rem;
    }

    .shimmer-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem 2rem;
    }

    .shimmer-footer-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.25rem;
    }

    .shimmer-footer-icon {
        height: 1.5rem;
        width: 1.5rem;
        border-radius: 0.25rem;
    }

    .shimmer-footer-text {
        height: 0.75rem;
        width: 2rem;
        margin-top: 0.25rem;
    }
</style>

<article class="shimmer-card">
    <div class="shimmer-content">
        <header class="shimmer-header">
            <div class="shimmer-pfp shimmer-bg"></div>
            <div class="shimmer-info">
                <div class="shimmer-line shimmer-line-header shimmer-bg" style="width: 33%;"></div>
                <div class="shimmer-line shimmer-line-subheader shimmer-bg" style="width: 25%;"></div>
            </div>
        </header>

        <div class="shimmer-divider"></div>

        <div class="shimmer-line shimmer-question shimmer-bg" style="width: 83%;"></div>

        <div class="shimmer-images-grid">
            <div class="shimmer-image-placeholder shimmer-bg"></div>
            <div class="shimmer-image-placeholder shimmer-bg"></div>
        </div>

        <div class="shimmer-buttons-grid">
            <div class="shimmer-button-placeholder shimmer-bg"></div>
            <div class="shimmer-button-placeholder shimmer-bg"></div>
        </div>

        <div class="shimmer-divider"></div>

        <div class="shimmer-footer">
            <div class="shimmer-footer-item">
                <div class="shimmer-footer-icon shimmer-bg"></div>
                <div class="shimmer-footer-text shimmer-bg" style="width: 1.25rem;"></div>
            </div>
            <div class="shimmer-footer-item">
                <div class="shimmer-line shimmer-bg" style="height: 1.5rem; width: 2.5rem;"></div>
                <div class="shimmer-footer-text shimmer-bg"></div>
            </div>
            <div class="shimmer-footer-item">
                <div class="shimmer-footer-icon shimmer-bg"></div>
                <div class="shimmer-footer-text shimmer-bg" style="width: 1.25rem;"></div>
            </div>
        </div>
    </div>
</article>
