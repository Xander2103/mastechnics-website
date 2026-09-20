{{-- Tabs between the five sections. $current = section key or null, $concept = draft rule set or null --}}
<nav class="qs-subnav" aria-label="Onderdelen van de offerte-instellingen">
    <a href="{{ route('admin.hvac.rules.index') }}" @if (($current ?? null) === null) class="is-active" aria-current="page" @endif>Overzicht</a>
    @foreach (\App\Services\Hvac\HvacSettingsGuide::SECTIONS as $key => $definition)
        <a href="{{ route('admin.hvac.rules.section', array_filter(['section' => $key, 'concept' => ($concept ?? null)?->id])) }}"
           @if (($current ?? null) === $key) class="is-active" aria-current="page" @endif>
            {{ $definition['number'] }}. {{ $definition['title'] }}
        </a>
    @endforeach
    <a href="{{ route('admin.hvac.rules.quick-estimate') }}" @if (($current ?? null) === 'quick-estimate') class="is-active" aria-current="page" @endif>Snelle inschatting</a>
    <a href="{{ route('admin.hvac.rules.example') }}" @if (($current ?? null) === 'example') class="is-active" aria-current="page" @endif>Bekijk voorbeeld</a>
    <a href="{{ route('admin.hvac.rules.advanced') }}" @if (($current ?? null) === 'advanced') class="is-active" aria-current="page" @endif>Geavanceerd</a>
</nav>
