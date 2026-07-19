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
            'button' => 'Bericht voorbereiden',
            'default_subject' => 'Contactaanvraag via website',
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
            'button' => 'Préparer le message',
            'default_subject' => 'Demande de contact via le site web',
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
            'button' => 'Prepare message',
            'default_subject' => 'Contact request via website',
            'request_cta_title' => 'Is it a technical request?',
            'request_cta_text' =>
                'Use the smart request flow so the right technical information is collected immediately.',
            'request_cta_button' => 'Start request',
        ],
    ];

    $text = $labels[$locale] ?? $labels['nl'];

    $requestSlug = $locale === 'fr' ? 'demande' : ($locale === 'en' ? 'request' : 'aanvraag');
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

                <form id="contactForm"
                      data-mailto="{{ $siteContact['email'] }}"
                      data-default-subject="{{ $text['default_subject'] }}"
                      data-label-name="{{ $text['name'] }}"
                      data-label-email="{{ $text['email_field'] }}"
                      data-label-phone="{{ $text['phone_field'] }}">
                    <div class="contact-field-grid">
                        <label>
                            <span>{{ $text['name'] }}</span>
                            <input type="text" name="name" id="contactName" required>
                        </label>

                        <label>
                            <span>{{ $text['email_field'] }}</span>
                            <input type="email" name="email" id="contactEmail" required>
                        </label>
                    </div>

                    <div class="contact-field-grid">
                        <label>
                            <span>{{ $text['phone_field'] }}</span>
                            <input type="tel" name="phone" id="contactPhone">
                        </label>

                        <label>
                            <span>{{ $text['subject'] }}</span>
                            <input type="text" name="subject" id="contactSubject">
                        </label>
                    </div>

                    <label>
                        <span>{{ $text['message'] }}</span>
                        <textarea rows="6" name="message" id="contactMessage" required></textarea>
                    </label>

                    <div class="button-row">
                        <button class="button button-primary button-large" type="button" id="contactSubmitBtn">
                            {{ $text['button'] }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
