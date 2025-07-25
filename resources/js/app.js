document.addEventListener('DOMContentLoaded', () => {

    if (document.getElementById('badge-container')) {
        import('./modules/BadgeCanvasManager.js')
            .then(({default: BadgeCanvasManager}) => {
                new BadgeCanvasManager();
            })
            .catch(error => console.error('Failed to load BadgeCanvasManager:', error));
    }
});
