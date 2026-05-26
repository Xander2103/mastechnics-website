<section>
    <p>mastechnics</p>

    <h1>{{ $translation->title }}</h1>

    @if ($translation->intro)
        <p>{{ $translation->intro }}</p>
    @endif

    <a href="#">Offerte aanvragen</a>
    <a href="{{ route('pages.show', ['locale' => $locale, 'slug' => $locale === 'fr' ? 'chauffage' : ($locale === 'en' ? 'heating' : 'verwarming')]) }}">
        Onze diensten
    </a>
</section>

<section>
    <h2>Onze diensten</h2>

    <div>
        <article>
            <h3>Verwarming</h3>
            <p>Onderhoud, herstelling en installatie van verwarmingssystemen.</p>
        </article>

        <article>
            <h3>Airco</h3>
            <p>Installatie, onderhoud en herstelling van airconditioningsystemen.</p>
        </article>

        <article>
            <h3>Sanitair</h3>
            <p>Professionele hulp bij sanitaire installaties en herstellingen.</p>
        </article>

        <article>
            <h3>Ventilatie</h3>
            <p>Ventilatie-oplossingen voor woningen en bedrijven.</p>
        </article>

        <article>
            <h3>Waterverzachters</h3>
            <p>Advies, installatie en onderhoud van waterverzachters.</p>
        </article>

        <article>
            <h3>Koelcellen</h3>
            <p>Koeling en koelcellen voor commerciële en industriële toepassingen.</p>
        </article>
    </div>
</section>

<section>
    <h2>Snelle en gestructureerde opvolging</h2>
    <p>
        Via een duidelijk intakeformulier verzamelen we meteen de juiste informatie,
        zodat aanvragen sneller en professioneler opgevolgd kunnen worden.
    </p>
</section>