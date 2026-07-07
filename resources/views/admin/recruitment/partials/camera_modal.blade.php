<div class="modal fade" id="cameraCaptureModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Capture Image</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="ratio ratio-16x9 bg-dark rounded overflow-hidden mb-3">
                    <video id="cameraCaptureVideo" autoplay playsinline muted style="width: 100%; height: 100%; object-fit: cover;"></video>
                </div>
                <div id="cameraCaptureError" class="alert alert-danger d-none mb-0"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="cameraCaptureButton">Capture</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const modalElement = document.getElementById('cameraCaptureModal');

        if (!modalElement) {
            return;
        }

        const modal = window.bootstrap ? new bootstrap.Modal(modalElement) : null;
        const video = document.getElementById('cameraCaptureVideo');
        const captureButton = document.getElementById('cameraCaptureButton');
        const errorBox = document.getElementById('cameraCaptureError');
        let activeField = null;
        let activeStream = null;

        function stopCamera() {
            if (activeStream) {
                activeStream.getTracks().forEach(track => track.stop());
                activeStream = null;
            }
            if (video) {
                video.srcObject = null;
            }
        }

        async function openCamera(button) {
            activeField = {
                inputId: button.dataset.cameraInput,
                previewId: button.dataset.cameraPreview,
                placeholderId: button.dataset.cameraPlaceholder,
                title: button.dataset.cameraTitle || 'Capture Image',
                facing: button.dataset.cameraFacing || 'environment',
            };

            modalElement.querySelector('.modal-title').textContent = activeField.title;
            errorBox.classList.add('d-none');
            errorBox.textContent = '';

            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                errorBox.textContent = 'Camera access is not supported in this browser.';
                errorBox.classList.remove('d-none');
                modal?.show();
                return;
            }

            try {
                stopCamera();
                activeStream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: {
                            ideal: activeField.facing,
                        },
                    },
                    audio: false,
                });
                video.srcObject = activeStream;
                modal?.show();
            } catch (error) {
                try {
                    activeStream = await navigator.mediaDevices.getUserMedia({
                        video: true,
                        audio: false,
                    });
                    video.srcObject = activeStream;
                    modal?.show();
                } catch (fallbackError) {
                    errorBox.textContent = 'Unable to access the camera. Please allow camera access and try again.';
                    errorBox.classList.remove('d-none');
                    modal?.show();
                }
            }
        }

        document.querySelectorAll('.js-open-camera').forEach(button => {
            button.addEventListener('click', function () {
                openCamera(this);
            });
        });

        captureButton?.addEventListener('click', function () {
            if (!activeField || !video || video.readyState < 2) {
                return;
            }

            const canvas = document.createElement('canvas');
            const sourceWidth = video.videoWidth || 1280;
            const sourceHeight = video.videoHeight || 720;
            const maxWidth = 1280;
            const scale = sourceWidth > maxWidth ? maxWidth / sourceWidth : 1;

            canvas.width = Math.round(sourceWidth * scale);
            canvas.height = Math.round(sourceHeight * scale);

            const context = canvas.getContext('2d');
            context.drawImage(video, 0, 0, canvas.width, canvas.height);

            const dataUrl = canvas.toDataURL('image/jpeg', 0.85);
            const input = document.getElementById(activeField.inputId);
            const preview = document.getElementById(activeField.previewId);
            const placeholder = document.getElementById(activeField.placeholderId);

            if (input) {
                input.value = dataUrl;
            }

            if (preview) {
                preview.src = dataUrl;
                preview.style.display = 'block';
            }

            if (placeholder) {
                placeholder.style.display = 'none';
            }

            modal?.hide();
        });

        modalElement.addEventListener('hidden.bs.modal', stopCamera);
    });
</script>
