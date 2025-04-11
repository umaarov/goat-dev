window.showToast = function (message) {
    const toast = document.getElementById('global-toast');
    if (!toast) return;

    if (typeof Alpine !== 'undefined' && toast.__x) {
        toast.__x.$data.message = message;
        toast.__x.$data.show = true;
    } else {
        toast.textContent = message;
        toast.style.display = 'block';
        toast.style.opacity = '1';
        toast.style.transition = 'opacity 0.5s';

        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => {
                toast.style.display = 'none';
            }, 500);
        }, 3000);
    }
};
