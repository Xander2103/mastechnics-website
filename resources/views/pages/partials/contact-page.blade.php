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
            'error_name' => 'Vul je naam in.',
            'error_message' => 'Vul een bericht in.',
            'error_contact' => 'Vul een e-mailadres of telefoonnummer in.',
            'error_email_format' => 'Vul een geldig e-mailadres in.',
            'default_subject' => 'Bericht via website',
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
            'error_name' => 'Indiquez votre nom.',
            'error_message' => 'Rédigez un message.',
            'error_contact' => 'Indiquez une adresse e-mail ou un numéro de téléphone.',
            'error_email_format' => 'Indiquez une adresse e-mail valide.',
            'default_subject' => 'Message via le site web',
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
            'error_name' => 'Please enter your name.',
            'error_message' => 'Please enter a message.',
            'error_contact' => 'Please provide an email address or phone number.',
            'error_email_format' => 'Please enter a valid email address.',
            'default_subject' => 'Message via website',
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

                <form id="contactForm" data-mailto="{{ $siteContact['email'] }}">
                    <div class="contact-field-grid">
                        <label>
                            <span>{{ $text['name'] }}</span>
                            <input type="text" name="name" required>
                        </label>

                        <label>
                            <span>{{ $text['email_field'] }}</span>
                            <input type="email" name="email">
                        </label>
                    </div>

                    <div class="contact-field-grid">
                        <label>
                            <span>{{ $text['phone_field'] }}</span>
                            <input type="tel" name="phone">
                        </label>

                        <label>
                            <span>{{ $text['subject'] }}</span>
                            <input type="text" name="subject">
                        </label>
                    </div>

                    <label>
                        <span>{{ $text['message'] }}</span>
                        <textarea rows="6" name="message" required></textarea>
                    </label>

                    <div class="button-row">
                        <button class="button button-primary button-large" type="button" id="contactPrepareBtn">
                            {{ $text['button'] }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<script>
(function () {
    'use strict';

    var form = document.getElementById('contactForm');
    if (!form) return;

    var button       = document.getElementById('contactPrepareBtn');
    var mailto       = form.dataset.mailto;
    var nameInput    = form.querySelector('[name="name"]');
    var emailInput   = form.querySelector('[name="email"]');
    var phoneInput   = form.querySelector('[name="phone"]');
    var subjectInput = form.querySelector('[name="subject"]');
    var messageInput = form.querySelector('[name="message"]');

    var errors = {
        name: @json($text['error_name']),
        message: @json($text['error_message']),
        contact: @json($text['error_contact']),
        email: @json($text['error_email_format']),
    };

    var defaultSubject = @json($text['default_subject']);

    function clearError(input) {
        var label = input.closest('label');
        if (!label) return;
        label.classList.remove('field-has-error');
        var err = label.querySelector('.field-error-text');
        if (err) err.remove();
    }

    function showError(input, message) {
        var label = input.closest('label');
        if (!label) return;
        label.classList.add('field-has-error');
        var err = label.querySelector('.field-error-text');
        if (!err) {
            err = document.createElement('p');
            err.className = 'field-error-text';
            label.appendChild(err);
        }
        err.textContent = message;
    }

    function isValidEmail(value) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
    }

    nameInput.addEventListener('input', function () { clearError(nameInput); });
    messageInput.addEventListener('input', function () { clearError(messageInput); });
    emailInput.addEventListener('input', function () { clearError(emailInput); });
    phoneInput.addEventListener('input', function () { clearError(emailInput); });

    button.addEventListener('click', function () {
        var name    = nameInput.value.trim();
        var email   = emailInput.value.trim();
        var phone   = phoneInput.value.trim();
        var subject = subjectInput.value.trim();
        var message = messageInput.value.trim();
        var valid   = true;

        if (!name) {
            showError(nameInput, errors.name);
            valid = false;
        } else {
            clearError(nameInput);
        }

        if (!message) {
            showError(messageInput, errors.message);
            valid = false;
        } else {
            clearError(messageInput);
        }

        if (!email && !phone) {
            showError(emailInput, errors.contact);
            valid = false;
        } else if (email && !isValidEmail(email)) {
            showError(emailInput, errors.email);
            valid = false;
        } else {
            clearError(emailInput);
        }

        if (!valid) return;

        var bodyLines = [name];
        if (phone) bodyLines.push(phone);
        if (email) bodyLines.push(email);
        bodyLines.push('');
        bodyLines.push(message);

        window.location.href = 'mailto:' + mailto
            + '?subject=' + encodeURIComponent(subject || defaultSubject)
            + '&body=' + encodeURIComponent(bodyLines.join('\n'));
    });
}());
</script>
