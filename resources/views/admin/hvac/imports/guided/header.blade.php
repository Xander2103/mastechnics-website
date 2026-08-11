@extends('layouts.app')

@section('title', 'Admin | Begeleide import — kolomkoppen')

@section('content')
    <section class="admin-hero">
        <div class="container">
            <span class="eyebrow">Admin — begeleide import</span>
            <h1>Stap 2 — Waar staan de kolomkoppen?</h1>
            <p>{{ $state['original_name'] }}@if ($state['sheet'] && $state['sheet'] !== 'CSV') — werkblad "{{ $state['sheet'] }}"@endif</p>
        </div>
    </section>

    <section class="section section-white">
        <div class="container">
            @include('admin.hvac.imports.guided.partials.wizard-messages')

            <div class="admin-detail-card">
                <p class="hvac-muted" style="margin-bottom:0.6rem;">
                    Rij <strong>{{ $detected['row'] + 1 }}</strong> lijkt de koprij te zijn
                    ({{ $detected['confidence'] === 'high' ? 'hoge' : 'lage' }} zekerheid).
                    Klopt dat niet? Kies hieronder een andere rij.
                </p>
                @if ($hasFormulas)
                    <p class="hvac-assumed" style="margin-bottom:0.6rem;">
                        Dit werkblad bevat formules. Alleen de opgeslagen resultaten worden gelezen — formules worden nooit uitgevoerd.
                    </p>
                @endif

                <form method="POST" action="{{ route('admin.hvac.import.guided.header', $token) }}">
                    @csrf
                    <div class="admin-table-wrapper">
                        <table class="admin-table" style="font-size:0.8rem;">
                            <thead>
                                <tr><th>Koprij?</th><th>#</th><th colspan="10">Inhoud (eerste 20 rijen, eerste 10 kolommen)</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $i => $cells)
                                    <tr>
                                        <td><input type="radio" name="header_row" value="{{ $i + 1 }}" @checked($i === $selected)></td>
                                        <td>{{ $i + 1 }}</td>
                                        @for ($c = 0; $c < 10; $c++)
                                            <td>{{ \Illuminate\Support\Str::limit((string) ($cells[$c] ?? ''), 24) }}</td>
                                        @endfor
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div style="margin-top:1.2rem;display:flex;gap:0.6rem;align-items:center;">
                        <button type="submit" class="button button-primary">Volgende: kolommen koppelen</button>
                        <a class="admin-link" href="{{ route('admin.hvac.import.index') }}">Terug naar importoverzicht</a>
                    </div>
                </form>

                @include('admin.hvac.imports.guided.partials.cancel-form')
            </div>
        </div>
    </section>
@endsection
