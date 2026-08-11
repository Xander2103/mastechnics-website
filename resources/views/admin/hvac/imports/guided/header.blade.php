@extends('layouts.app')

@section('title', 'Admin | Import — kolommen herkennen')

@section('content')
    <section class="admin-hero">
        <div class="container">
            <span class="eyebrow">Productbestand importeren</span>
            <h1>Bestand controleren</h1>
            <p>{{ $state['original_name'] }}@if ($state['sheet'] && $state['sheet'] !== 'CSV') — werkblad "{{ $state['sheet'] }}"@endif</p>
        </div>
    </section>

    <section class="section section-white">
        <div class="container">
            @include('admin.hvac.imports.guided.partials.progress', ['current' => 1])
            @include('admin.hvac.imports.guided.partials.wizard-messages')

            <div class="admin-detail-card">
                <h2>In welke rij staan de kolomnamen?</h2>
                <p class="hvac-muted" style="margin:0.4rem 0 0.8rem 0;">
                    We vermoeden rij <strong>{{ $detected['row'] + 1 }}</strong>, maar zijn niet helemaal zeker.
                    Kies de rij met de kolomnamen (zoals artikelnummer en omschrijving).
                </p>

                <form method="POST" action="{{ route('admin.hvac.import.guided.header', $token) }}">
                    @csrf
                    <div class="admin-table-wrapper">
                        <table class="admin-table" style="font-size:0.8rem;">
                            <thead>
                                <tr><th>Deze rij</th><th>#</th><th colspan="10">Inhoud van het bestand (begin)</th></tr>
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
                    <div style="margin-top:1.2rem;">
                        <button type="submit" class="button button-primary">Verder</button>
                    </div>
                </form>

                @include('admin.hvac.imports.guided.partials.cancel-form')
            </div>
        </div>
    </section>
@endsection
