let currentCropperInstance = null;
let currentFileInputTarget = null;
let currentPreviewImgElement = null;
let currentPlaceholderElement = null;
let currentPreviewDivElement = null;
let currentOriginalFile = null;
window.lastTriggeredImageInputId = null;

function initCropperModal() {
    if (document.getElementById('imageCropModalGlobal')) {
        return;
    }

    const modalHTML = `
        <div id="imageCropModalGlobal" class="fixed inset-0 bg-gray-800 bg-opacity-75 flex items-center justify-center z-50 hidden">
            <div class="bg-white dark:bg-gray-800 p-4 sm:p-6 rounded-lg shadow-xl w-11/12 max-w-lg">
                <h3 class="text-xl font-semibold mb-3 text-gray-800 dark:text-gray-200">${window.translations.cropperModalTitle}</h3>
                <div class="mb-4" style="max-height: 60vh; overflow: hidden;">
                    <img id="imageToCropGlobal" src="#" alt="Image to crop" style="max-width: 100%;" loading="lazy">
                </div>
                <div class="flex flex-col sm:flex-row justify-end space-y-2 sm:space-y-0 sm:space-x-3">
                    <button type="button" id="cancelCropGlobal" class="w-full sm:w-auto px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition-colors dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600">${window.translations.cancelButton}</button>
                    <button type="button" id="applyCropGlobal" class="w-full sm:w-auto px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">${window.translations.applyCropButton}</button>
                </div>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHTML);

    const modal = document.getElementById('imageCropModalGlobal');
    const applyCropBtn = document.getElementById('applyCropGlobal');
    const cancelCropBtn = document.getElementById('cancelCropGlobal');

    cancelCropBtn.addEventListener('click', () => {
        modal.classList.add('hidden');
        if (currentCropperInstance) {
            currentCropperInstance.destroy();
            currentCropperInstance = null;
        }
        if (window.lastTriggeredImageInputId) {
            const triggerInput = document.getElementById(window.lastTriggeredImageInputId);
            if (triggerInput) triggerInput.value = '';
        }
    });

    applyCropBtn.addEventListener('click', () => {
        if (currentCropperInstance && currentOriginalFile) {
            const canvas = currentCropperInstance.getCroppedCanvas({
                imageSmoothingQuality: 'medium',
            });
            canvas.toBlob((blob) => {
                if (blob) {
                    const croppedFile = new File([blob], currentOriginalFile.name, {
                        type: currentOriginalFile.type,
                        lastModified: Date.now()
                    });

                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add(croppedFile);

                    if (currentFileInputTarget === 'profile_picture_final') {
                        const removePfpCheckbox = document.getElementById('remove_profile_picture');
                        if (removePfpCheckbox && removePfpCheckbox.checked) {
                            removePfpCheckbox.checked = false;
                        }
                    }

                    const targetInputElement = document.getElementById(currentFileInputTarget);
                    if (targetInputElement) {
                        targetInputElement.files = dataTransfer.files;
                        if (currentPreviewImgElement) {
                            currentPreviewImgElement.src = URL.createObjectURL(croppedFile);
                            currentPreviewImgElement.classList.remove('hidden');
                        }
                        if (currentPlaceholderElement) {
                            currentPlaceholderElement.classList.add('hidden');
                        }
                        if (currentPreviewDivElement) {
                            currentPreviewDivElement.classList.remove('border-dashed', 'border-red-500', 'hover:border-blue-500');
                            currentPreviewDivElement.classList.add('border-solid', 'border-gray-300');
                            const parentDiv = currentPreviewDivElement.closest('div');
                            if (parentDiv) {
                                const errorSpan = parentDiv.querySelector('.text-red-500.text-sm.mt-1');
                                if (errorSpan) errorSpan.style.display = 'none';
                            }
                        }
                    }
                } else {
                    console.error('Could not create blob from canvas.');
                    if (typeof window.showToast === 'function') {
                        window.showToast(window.translations.errorProcessingCrop, 'error');
                    } else {
                        alert(window.translations.errorProcessingCrop);
                    }
                }
            }, currentOriginalFile.type);

            modal.classList.add('hidden');
            if (currentCropperInstance) {
                currentCropperInstance.destroy();
                currentCropperInstance = null;
            }
        }
    });
}

function openImageCropper(event, targetInputId, previewImgId, placeholderId, previewDivId) {
    const file = event.target.files[0];
    if (!file) return;

    window.lastTriggeredImageInputId = event.target.id;
    currentOriginalFile = file;
    currentFileInputTarget = targetInputId;
    currentPreviewImgElement = document.getElementById(previewImgId);
    currentPlaceholderElement = document.getElementById(placeholderId);
    currentPreviewDivElement = document.getElementById(previewDivId);

    const modal = document.getElementById('imageCropModalGlobal');
    const imageToCropElement = document.getElementById('imageToCropGlobal');

    if (!modal || !imageToCropElement) {
        console.error('Cropper modal elements not found. Was initCropperModal called?');
        if (typeof window.showToast === 'function') {
            window.showToast(window.translations.errorInitTool, 'error');
        } else {
            alert(window.translations.errorInitTool);
        }
        return;
    }


    const reader = new FileReader();
    reader.onload = (e) => {
        imageToCropElement.src = e.target.result;
        modal.classList.remove('hidden');
        if (currentCropperInstance) {
            currentCropperInstance.destroy();
        }
        currentCropperInstance = new Cropper(imageToCropElement, {
            aspectRatio: 1,
            viewMode: 1,
            background: false,
            autoCropArea: 0.85,
            responsive: true,
            checkCrossOrigin: false,
            // guides: false,
            // center: false,
            // cropBoxResizable: false,
            // dragMode: 'move',
        });
    };
    reader.readAsDataURL(file);
}


document.addEventListener('DOMContentLoaded', initCropperModal);
