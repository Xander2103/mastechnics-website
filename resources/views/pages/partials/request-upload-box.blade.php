{{-- Upload / info box of a wizard step, driven by the step's `helper_box`
     config. Every box in the form feeds the same attachments[] field, so a
     step may render one before or after its fields without a second upload
     implementation. Expects: $box, $locale, $text, $errors. --}}
<div class="upload-box {{ $errors->has('attachments') || $errors->has('attachments.*') ? 'field-has-error' : '' }}"
     @if (!empty($boxStyle)) style="{{ $boxStyle }}" @endif>
    <strong>
        {{ $box['title'][$locale] ?? $box['title']['nl'] }}
    </strong>

    <p>
        {{ $box['text'][$locale] ?? $box['text']['nl'] }}
    </p>

    @if ($box['render_upload'] ?? true)
        <label class="upload-file-control">
            <span>
                {{ $text['choose_files'] }}
            </span>

            <input
                type="file"
                name="attachments[]"
                multiple
                accept=".jpg,.jpeg,.png,.webp,.pdf"
                class="js-attachment-input"
            >
        </label>

        <div class="selected-attachments js-attachment-list"
             data-remove-label="{{ $locale === 'fr' ? 'Supprimer' : ($locale === 'en' ? 'Remove' : 'Verwijder') }}"></div>

        @error('attachments')
            <p class="field-error-text">{{ $message }}</p>
        @enderror

        @error('attachments.*')
            <p class="field-error-text">{{ $message }}</p>
        @enderror
    @endif
</div>
