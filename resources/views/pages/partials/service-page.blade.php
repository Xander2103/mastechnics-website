<section>
    <p>Service</p>

    <h1>{{ $translation->title }}</h1>

    @if ($translation->intro)
        <p>{{ $translation->intro }}</p>
    @endif

    <a href="#">Offerte aanvragen</a>
</section>

<section>
    <h2>Wat kunnen we voor u doen?</h2>

    @if ($translation->content)
        <p>{{ $translation->content }}</p>
    @endif
</section>

<section>
    <h2>Waarom kiezen voor ons?</h2>

    <ul>
        <li>Snelle opvolging van aanvragen</li>
        <li>Professionele service voor particulieren en bedrijven</li>
        <li>Duidelijke communicatie en gestructureerde intake</li>
    </ul>
</section>