import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';

const input = document.getElementById('profile_picture');
const modalElement = document.getElementById('profile-picture-crop-modal');

if (input && modalElement && window.bootstrap) {
    const cropImage = document.getElementById('profile-picture-crop-image');
    const preview = document.getElementById('profile-picture-preview');
    const placeholder = document.getElementById('profile-picture-placeholder');
    const error = document.getElementById('profile-picture-crop-error');
    const applyButton = document.getElementById('profile-picture-crop-apply');
    const cropModal = window.bootstrap.Modal.getOrCreateInstance(modalElement);
    let cropper;
    let sourceUrl;
    let previewUrl;

    const clearSource = () => {
        cropper?.destroy();
        cropper = undefined;
        if (sourceUrl) URL.revokeObjectURL(sourceUrl);
        sourceUrl = undefined;
    };

    input.addEventListener('change', () => {
        const file = input.files?.[0];
        input.value = '';
        error.textContent = '';

        if (!file) return;

        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
            error.textContent = 'Choose a JPG, PNG, or WebP image.';
            return;
        }

        if (file.size > 2 * 1024 * 1024) {
            error.textContent = 'The image must be 2 MB or smaller.';
            return;
        }

        clearSource();
        sourceUrl = URL.createObjectURL(file);
        cropImage.src = sourceUrl;
        cropModal.show();
    });

    modalElement.addEventListener('shown.bs.modal', () => {
        if (!sourceUrl) return;

        cropper = new Cropper(cropImage, {
            aspectRatio: 1,
            viewMode: 1,
            dragMode: 'move',
            autoCropArea: 0.9,
            responsive: true,
            background: false,
            movable: true,
            zoomable: true,
            rotatable: false,
            scalable: false,
        });
    });

    modalElement.addEventListener('hidden.bs.modal', clearSource);

    modalElement.querySelectorAll('[data-crop-zoom]').forEach((button) => {
        button.addEventListener('click', () => cropper?.zoom(Number(button.dataset.cropZoom)));
    });

    document.getElementById('profile-picture-crop-reset')?.addEventListener('click', () => cropper?.reset());

    applyButton?.addEventListener('click', () => {
        const canvas = cropper?.getCroppedCanvas({
            width: 600,
            height: 600,
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high',
        });

        if (!canvas) return;

        applyButton.disabled = true;
        canvas.toBlob((blob) => {
            applyButton.disabled = false;

            if (!blob) {
                error.textContent = 'The cropped image could not be created. Please try again.';
                cropModal.hide();
                return;
            }

            const croppedFile = new File([blob], 'profile-picture.jpg', { type: 'image/jpeg' });
            const transfer = new DataTransfer();
            transfer.items.add(croppedFile);
            input.files = transfer.files;

            if (previewUrl) URL.revokeObjectURL(previewUrl);
            previewUrl = URL.createObjectURL(croppedFile);
            preview.src = previewUrl;
            preview.classList.remove('d-none');
            placeholder?.classList.add('d-none');
            cropModal.hide();
        }, 'image/jpeg', 0.92);
    });
}