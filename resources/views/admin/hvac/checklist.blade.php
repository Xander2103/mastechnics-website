@extends('layouts.app')

@section('title', 'Admin | HVAC-acceptatiechecklist')

@section('content')
    <section class="admin-hero">
        <div class="container">
            <span class="eyebrow">Admin</span>
            <h1>Acceptatiechecklist HVAC-voorcalculatie</h1>
            <p>Doorloop deze stappen vóór het eerste echte gebruik en bij elke nieuwe leverancierscatalogus.</p>
        </div>
    </section>

    <section class="section section-white">
        <div class="container">
            @include('admin.hvac.partials.nav')

            <div class="admin-detail-card">
                <ol style="line-height:2;font-size:0.95rem;padding-left:1.4rem;">
                    <li>Importeer de leverancierscatalogus via <a class="admin-link" href="{{ route('admin.hvac.import.index') }}">Import</a> (producten eerst, daarna compatibiliteit).</li>
                    <li>Controleer het importrapport en los rijfouten op.</li>
                    <li>Voeg ontbrekende compatibiliteit toe (productpagina of compatibiliteits-CSV).</li>
                    <li>Valideer de <a class="admin-link" href="{{ route('admin.hvac.rules.index') }}">berekeningsregels</a> — alle kritieke regels moeten gevalideerd zijn.</li>
                    <li>Open een (test)airco-aanvraag in <a class="admin-link" href="{{ route('admin.requests.index') }}">Aanvragen</a>.</li>
                    <li>Voer de voorcalculatie uit.</li>
                    <li>Controleer elke kamer (afmetingen, isolatie, ligging, aannames).</li>
                    <li>Controleer alle waarschuwingen.</li>
                    <li>Controleer de productcombinatie en compatibiliteit.</li>
                    <li>Controleer leiding- en elektrische limieten.</li>
                    <li>Controleer de toebehoren en hoeveelheden.</li>
                    <li>Controleer de arbeidsuren.</li>
                    <li>Controleer de aankoopprijzen.</li>
                    <li>Controleer verkoopprijzen en marge.</li>
                    <li>Keur de gekozen optie goed.</li>
                    <li>Zet ze om naar een conceptofferte.</li>
                    <li>Controleer de offerte-PDF.</li>
                    <li>Verstuur pas na uitdrukkelijke, handmatige bevestiging — het systeem mailt nooit automatisch.</li>
                </ol>

                <p class="hvac-muted" style="margin-top:1rem;">
                    De volledige handleiding staat in <code>docs/hvac/klantenhandleiding-mastechnics.md</code>;
                    deze checklist ook in <code>docs/hvac/acceptatiechecklist-martin.md</code>.
                </p>
            </div>
        </div>
    </section>
@endsection
