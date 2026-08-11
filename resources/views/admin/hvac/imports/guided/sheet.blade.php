@extends('layouts.app')

@section('title', 'Admin | Import — werkblad kiezen')

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
                <h2>Welk tabblad bevat de producten?</h2>
                <p class="hvac-muted" style="margin:0.4rem 0 1rem 0;">
                    Dit Excel-bestand heeft meerdere tabbladen.
                </p>

                <form method="POST" action="{{ route('admin.hvac.import.guided.sheet', $token) }}">
                    @csrf
                    <div style="display:flex;flex-direction:column;gap:0.5rem;">
                        @foreach ($sheets as $sheet)
                            <label style="display:flex;align-items:center;gap:0.5rem;">
                                <input type="radio" name="sheet" value="{{ $sheet['name'] }}"
                                       @checked($loop->first && ! $sheet['hidden'])>
                                <span>{{ $sheet['name'] }}</span>
                                @if ($sheet['hidden'])
                                    <span class="hvac-assumed">(verborgen tabblad)</span>
                                @endif
                            </label>
                        @endforeach
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
