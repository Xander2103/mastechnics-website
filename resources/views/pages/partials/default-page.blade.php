<section>
    <h1>{{ $translation->title }}</h1>

    @if ($translation->intro)
        <p>{{ $translation->intro }}</p>
    @endif

    @if ($translation->content)
        <div>
            {{ $translation->content }}
        </div>
    @endif
</section>