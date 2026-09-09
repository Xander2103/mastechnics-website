{{--
    Bot-challenge widget for the public forms. Provider-agnostic: renders the
    active CaptchaVerifier's script + container (Cloudflare Turnstile or Google
    reCAPTCHA v2). The token the widget injects into the form is verified
    server-side; without a valid token nothing is stored or mailed.

    Parameters:
      $form    'contact' | 'request'  (error key is always 'captcha')
      $render  'auto' (render as soon as the script loads, contact form) or
               'manual' (the wizard calls window.mtRenderCaptcha(el) when the
               last step opens — a widget rendered inside a hidden step is
               unreliable for interactive challenges).

    No SRI hash on the script: both providers rotate the build behind a fixed
    URL and document that integrity attributes are unsupported.
--}}
@php
    $captcha = app(\App\Services\Spam\PublicFormGuard::class)->captcha();
    $render = $render ?? 'auto';
@endphp
@if ($captcha->enabled() && $captcha->siteKey() !== '')
    @once
        <script>
            (function () {
                function api(provider) {
                    return provider === 'recaptcha' ? window.grecaptcha : window.turnstile;
                }

                window.mtRenderCaptcha = function (el) {
                    if (!el || el.dataset.rendered === '1') { return; }
                    var provider = el.dataset.provider;
                    var lib = api(provider);
                    if (!lib || typeof lib.render !== 'function') {
                        el.dataset.pending = '1';
                        return;
                    }
                    var options = { sitekey: el.dataset.sitekey };
                    if (provider === 'turnstile') {
                        // Echoed by siteverify as "action"; the server refuses a
                        // token issued for another form.
                        options.action = el.dataset.action;
                        options.language = el.dataset.language || 'auto';
                        options.theme = 'light';
                        options.size = 'flexible';
                    }
                    lib.render(el, options);
                    el.dataset.rendered = '1';
                    el.dataset.pending = '0';
                };

                window.mtCaptchaReady = function () {
                    Array.prototype.forEach.call(document.querySelectorAll('.mt-captcha'), function (el) {
                        if (el.dataset.render === 'auto' || el.dataset.pending === '1') {
                            window.mtRenderCaptcha(el);
                        }
                    });
                };
            })();
        </script>
        <script src="{{ $captcha->scriptUrl() }}?render=explicit&onload=mtCaptchaReady" async defer></script>
    @endonce
    <div class="captcha-field {{ $errors->has('captcha') ? 'field-has-error' : '' }}">
        <div class="mt-captcha"
             id="captcha-{{ $form }}"
             data-provider="{{ $captcha->provider() }}"
             data-sitekey="{{ $captcha->siteKey() }}"
             data-action="{{ $form }}"
             data-language="{{ $locale ?? app()->getLocale() }}"
             data-render="{{ $render }}"></div>
        @error('captcha')
            <p class="field-error-text">{{ $message }}</p>
        @enderror
    </div>
@elseif ($errors->has('captcha'))
    <div class="captcha-field field-has-error">
        <p class="field-error-text">{{ $errors->first('captcha') }}</p>
    </div>
@endif
