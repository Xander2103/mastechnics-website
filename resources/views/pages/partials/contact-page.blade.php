@php
    $siteName = config('site.name');
    $siteContact = config('site.contact');

    $labels = [
        'nl' => [
            'badge' => 'Contact',
            'direct_contact' => 'Rechtstreeks contact',
            'phone' => 'Telefoon',
            'email' => 'E-mail',
            'whatsapp' => 'WhatsApp',
            'messenger' => 'Messenger',
            'form_title' => 'Stuur een bericht',
            'form_intro' =>
                'Voor algemene vragen kan je dit formulier gebruiken. Voor technische aanvragen of richtprijzen gebruik je best de slimme aanvraagflow.',
            'name' => 'Naam',
            'email_field' => 'E-mailadres',
            'phone_field' => 'Telefoonnummer',
            'subject' => 'Onderwerp',
            'message' => 'Bericht',
            'button' => 'Bericht versturen',
            'button_sending' => 'Bezig met versturen...',
            'success_title' => 'Uw bericht werd succesvol verzonden.',
            'success_text' => 'U ontvangt ook een bevestiging per e-mail.',
            'privacy_notice' => 'Uw gegevens worden enkel gebruikt om uw aanvraag te beantwoorden.',
            'request_cta_title' => 'Gaat het over een technische aanvraag?',
            'request_cta_text' =>
                'Gebruik dan de slimme aanvraagflow zodat meteen de juiste technische informatie verzameld wordt.',
            'request_cta_button' => 'Start aanvraag',
        ],
        'fr' => [
            'badge' => 'Contact',
            'direct_contact' => 'Contact direct',
            'phone' => 'Téléphone',
            'email' => 'E-mail',
            'whatsapp' => 'WhatsApp',
            'messenger' => 'Messenger',
            'form_title' => 'Envoyer un message',
            'form_intro' =>
                'Pour les questions générales, vous pouvez utiliser ce formulaire. Pour les demandes techniques ou estimations, utilisez plutôt le flux de demande intelligente.',
            'name' => 'Nom',
            'email_field' => 'Adresse e-mail',
            'phone_field' => 'Numéro de téléphone',
            'subject' => 'Sujet',
            'message' => 'Message',
            'button' => 'Envoyer le message',
            'button_sending' => 'Envoi en cours...',
            'success_title' => 'Votre message a été envoyé avec succès.',
            'success_text' => 'Vous recevrez également une confirmation par e-mail.',
            'privacy_notice' => 'Vos données sont uniquement utilisées pour répondre à votre demande.',
            'request_cta_title' => 'Votre demande est technique ?',
            'request_cta_text' =>
                'Utilisez alors le flux de demande intelligente afin de transmettre directement les bonnes informations techniques.',
            'request_cta_button' => 'Démarrer ma demande',
        ],
        'en' => [
            'badge' => 'Contact',
            'direct_contact' => 'Direct contact',
            'phone' => 'Phone',
            'email' => 'Email',
            'whatsapp' => 'WhatsApp',
            'messenger' => 'Messenger',
            'form_title' => 'Send a message',
            'form_intro' =>
                'For general questions, you can use this form. For technical requests or estimates, it is better to use the smart request flow.',
            'name' => 'Name',
            'email_field' => 'Email address',
            'phone_field' => 'Phone number',
            'subject' => 'Subject',
            'message' => 'Message',
            'button' => 'Send message',
            'button_sending' => 'Sending...',
            'success_title' => 'Your message was sent successfully.',
            'success_text' => 'You will also receive a confirmation by email.',
            'privacy_notice' => 'Your details are only used to answer your request.',
            'request_cta_title' => 'Is it a technical request?',
            'request_cta_text' =>
                'Use the smart request flow so the right technical information is collected immediately.',
            'request_cta_button' => 'Start request',
        ],
    ];

    $text = $labels[$locale] ?? $labels['nl'];

    $requestSlug = $locale === 'fr' ? 'demande' : ($locale === 'en' ? 'request' : 'aanvraag');
    $privacySlug = $locale === 'fr' ? 'politique-confidentialite' : ($locale === 'en' ? 'privacy-policy' : 'privacybeleid');
    $privacyLinkLabel = $locale === 'fr' ? 'politique de confidentialité' : ($locale === 'en' ? 'privacy policy' : 'privacybeleid');
@endphp

<section class="section section-white">
    <div class="container">
        <div class="contact-layout">
            <aside class="contact-info-card">
                <h2>{{ $text['direct_contact'] }}</h2>

                <div class="contact-list">
                    <div class="contact-item">
                        <span>{{ $text['phone'] }}</span>
                        <a href="tel:{{ $siteContact['phone_link'] }}">
                            {{ $siteContact['phone_display'] }}
                        </a>
                    </div>

                    <div class="contact-item">
                        <span>{{ $text['email'] }}</span>
                        <a href="mailto:{{ $siteContact['email'] }}">
                            {{ $siteContact['email'] }}
                        </a>
                    </div>

                    <div class="contact-item">
                        <span>{{ $text['whatsapp'] }}</span>
                        <a href="https://wa.me/{{ $siteContact['whatsapp_link'] }}" target="_blank" rel="noopener">
                            {{ $siteContact['whatsapp_display'] }}
                        </a>
                    </div>

                    <div class="contact-item">
                        <a href="https://m.me/mastechnics" target="_blank" rel="noopener noreferrer">
                            Messenger
                        </a>
                    </div>
                </div>

                <div class="contact-request-box">
                    <h3>{{ $text['request_cta_title'] }}</h3>
                    <p>{{ $text['request_cta_text'] }}</p>

                    <a class="button button-primary"
                        href="{{ route('pages.show', [
                            'locale' => $locale,
                            'slug' => $requestSlug,
                        ]) }}">
                        {{ $text['request_cta_button'] }}
                    </a>
                </div>
            </aside>

            <div class="contact-form-card">
                <h2>{{ $text['form_title'] }}</h2>
                <p>{{ $text['form_intro'] }}</p>

                @if (session('success') === 'contact_message_sent')
                    <div class="form-success">
                        {{ $text['success_title'] }}
                        <p>{{ $text['success_text'] }}</p>
                    </div>
                @endif

                @error('rate_limit')
                    <div class="form-error-list">
                        <strong>{{ $message }}</strong>
                    </div>
                @enderror

                <form id="contactForm" method="POST" action="{{ route('contact.store', ['locale' => $locale]) }}">
                    @csrf
                    {{-- Fresh per page-load; lets the server detect and ignore an
                         exact resubmission (double-click, refresh, retry) without
                         relying on JavaScript. See ContactController::firstOrCreateByToken(). --}}
                    <input type="hidden" name="submission_token" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

                    <div class="contact-field-grid">
                        <label class="{{ $errors->has('name') ? 'field-has-error' : '' }}">
                            <span>{{ $text['name'] }}</span>
                            <input type="text" name="name" id="contactName" value="{{ old('name') }}" maxlength="255" required>
                            @error('name')
                                <span class="field-error-text">{{ $message }}</span>
                            @enderror
                        </label>

                        <label class="{{ $errors->has('email') ? 'field-has-error' : '' }}">
                            <span>{{ $text['email_field'] }}</span>
                            <input type="email" name="email" id="contactEmail" value="{{ old('email') }}" maxlength="255" required>
                            @error('email')
                                <span class="field-error-text">{{ $message }}</span>
                            @enderror
                        </label>
                    </div>

                    <div class="contact-field-grid">
                        <label class="{{ $errors->has('phone') ? 'field-has-error' : '' }}">
                            <span>{{ $text['phone_field'] }}</span>
                            <input type="tel" name="phone" id="contactPhone" value="{{ old('phone') }}" maxlength="50">
                            @error('phone')
                                <span class="field-error-text">{{ $message }}</span>
                            @enderror
                        </label>

                        <label class="{{ $errors->has('subject') ? 'field-has-error' : '' }}">
                            <span>{{ $text['subject'] }}</span>
                            <input type="text" name="subject" id="contactSubject" value="{{ old('subject') }}" maxlength="255">
                            @error('subject')
                                <span class="field-error-text">{{ $message }}</span>
                            @enderror
                        </label>
                    </div>

                    <label class="{{ $errors->has('message') ? 'field-has-error' : '' }}">
                        <span>{{ $text['message'] }}</span>
                        <textarea rows="6" name="message" id="contactMessage" maxlength="5000" required>{{ old('message') }}</textarea>
                        @error('message')
                            <span class="field-error-text">{{ $message }}</span>
                        @enderror
                    </label>

                    <div class="button-row">
                        <button class="button button-primary button-large" type="submit" id="contactSubmitBtn" data-label-default="{{ $text['button'] }}" data-label-sending="{{ $text['button_sending'] }}">
                            {{ $text['button'] }}
                        </button>
                    </div>

                    <p class="form-privacy-notice">
                        {{ $text['privacy_notice'] }}
                        <a href="{{ route('pages.show', ['locale' => $locale, 'slug' => $privacySlug]) }}">{{ $privacyLinkLabel }}</a>
                    </p>
                </form>
            </div>
        </div>
    </div>
</section>
