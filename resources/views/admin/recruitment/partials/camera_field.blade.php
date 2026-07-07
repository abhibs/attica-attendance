@php
    $previewSrc = trim((string) ($previewSrc ?? ''));
    $buttonLabel = $buttonLabel ?? 'Open Camera';
    $facingMode = $facingMode ?? 'environment';
    $showPreview = $showPreview ?? true;
@endphp

<div class="camera-capture-card border rounded-3 p-3 h-100">
    <div class="d-flex flex-column gap-2">
        <div class="d-flex align-items-center justify-content-between gap-2">
            <label class="form-label mb-0">{{ $label }}</label>
            <button
                type="button"
                class="btn btn-outline-primary btn-sm js-open-camera"
                data-camera-input="{{ $fieldId }}"
                data-camera-preview="{{ $fieldId }}_preview"
                data-camera-placeholder="{{ $fieldId }}_placeholder"
                data-camera-title="{{ $label }}"
                data-camera-facing="{{ $facingMode }}">
                {{ $buttonLabel }}
            </button>
        </div>

        <input type="hidden" name="{{ $inputName }}" id="{{ $fieldId }}" value="">

        @if ($showPreview)
            <div class="border rounded-3 bg-light d-flex align-items-center justify-content-center overflow-hidden" style="min-height: 220px;">
                <img
                    id="{{ $fieldId }}_preview"
                    src="{{ $previewSrc !== '' ? asset('public/'.$previewSrc) : '' }}"
                    alt="{{ $label }}"
                    style="max-width: 100%; max-height: 220px; {{ $previewSrc !== '' ? '' : 'display:none;' }}">
                <div id="{{ $fieldId }}_placeholder" class="text-muted text-center px-3" style="{{ $previewSrc !== '' ? 'display:none;' : '' }}">
                    No camera image captured yet.
                </div>
            </div>
        @endif
    </div>
</div>
