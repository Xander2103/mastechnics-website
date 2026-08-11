@extends('layouts.app')

@section('title', 'Admin | Import — kolommen herkennen')

@section('content')
    <section class="admin-hero">
        <div class="container">
            <span class="eyebrow">Productbestand importeren</span>
            <h1>Bestand controleren</h1>
            <p>{{ $state['original_name'] }}</p>
        </div>
    </section>

    <section class="section section-white">
        <div class="container">
            @include('admin.hvac.imports.guided.partials.progress', ['current' => 1])
            @include('admin.hvac.imports.guided.partials.wizard-messages')

            <div class="admin-detail-card">
                <h2>Hoe zijn de kolommen gescheiden?</h2>
                <p class="hvac-muted" style="margin-bottom:1rem;">
                    We konden dit niet met zekerheid herkennen. Kies hieronder — u ziet daarna meteen het resultaat.
                </p>

                <form method="POST" action="{{ route('admin.hvac.import.guided.delimiter', $token) }}">
                    @csrf
                    <div style="display:flex;flex-direction:column;gap:0.5rem;max-width:22rem;">
                        <label style="display:flex;align-items:center;gap:0.5rem;">
                            <input type="radio" name="delimiter" value="auto" checked> <span>Automatisch (beste gok)</span>
                        </label>
                        <label style="display:flex;align-items:center;gap:0.5rem;">
                            <input type="radio" name="delimiter" value="semicolon"> <span>Puntkomma (;)</span>
                        </label>
                        <label style="display:flex;align-items:center;gap:0.5rem;">
                            <input type="radio" name="delimiter" value="comma"> <span>Komma (,)</span>
                        </label>
                        <label style="display:flex;align-items:center;gap:0.5rem;">
                            <input type="radio" name="delimiter" value="tab"> <span>Tab</span>
                        </label>
                        <label style="display:flex;align-items:center;gap:0.5rem;">
                            <input type="radio" name="delimiter" value="pipe"> <span>Anders (|)</span>
                        </label>
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
