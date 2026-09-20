@php
    $flashMessages = [
        'hvac_rule_validated'       => 'Instelling bevestigd. Uw naam, het tijdstip en de bevestigde waarde zijn bewaard.',
        'hvac_rule_unvalidated'     => 'Bevestiging ingetrokken. De instelling staat opnieuw op "Controleren".',
        'hvac_rule_draft_created'   => 'Concept (versie ' . session('draft_version') . ') aangemaakt. Wijzigingen in dit concept veranderen niets aan lopende of bestaande offertes tot u het concept activeert.',
        'hvac_rule_draft_exists'    => 'Er bestaat al een concept. U werkt daarin verder.',
        'hvac_rule_value_updated'   => 'Waarde gewijzigd in het concept. Bevestig de nieuwe waarde voordat u het concept activeert.',
        'hvac_rule_value_unchanged' => 'De waarde is dezelfde als voorheen — er is niets gewijzigd.',
        'hvac_rule_draft_discarded' => 'Concept geannuleerd. De actieve instellingen zijn niet gewijzigd.',
        'hvac_rule_set_activated'   => 'Nieuwe instellingen geactiveerd. Alleen nieuwe berekeningen gebruiken deze versie; bestaande berekeningen en offertes blijven ongewijzigd.',
    ];
@endphp

@if (session('success') && isset($flashMessages[session('success')]))
    <div class="form-success" role="status">{{ $flashMessages[session('success')] }}</div>
@endif

@if ($errors->any())
    <div class="form-error-list" role="alert">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
