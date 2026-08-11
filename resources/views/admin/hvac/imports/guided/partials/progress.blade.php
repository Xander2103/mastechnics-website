{{-- Wizard progress: pass $current (1..5). Short labels, no technical terms. --}}
@php
    $wizardSteps = [1 => 'Bestand', 2 => 'Producten', 3 => 'Controle', 4 => 'Importeren', 5 => 'Resultaat'];
@endphp
<ol style="display:flex;flex-wrap:wrap;gap:0.4rem 1.1rem;list-style:none;padding:0;margin:0 0 1.2rem 0;font-size:0.88rem;">
    @foreach ($wizardSteps as $number => $label)
        <li style="display:flex;align-items:center;gap:0.35rem;{{ $number === $current ? 'font-weight:700;' : ($number < $current ? '' : 'opacity:0.45;') }}">
            @if ($number < $current)
                <span aria-hidden="true" style="color:#1a7f37;">&#10003;</span>
                <span class="sr-only">Afgerond:</span>
            @else
                <span aria-hidden="true">{{ $number }}</span>
            @endif
            <span>{{ $label }}</span>
        </li>
    @endforeach
</ol>
