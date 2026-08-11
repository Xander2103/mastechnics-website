@extends('layouts.app')

@section('title', 'Admin | Begeleide import — werkblad')

@section('content')
    <section class="admin-hero">
        <div class="container">
            <span class="eyebrow">Admin — begeleide import</span>
            <h1>Stap 1 — Kies het werkblad</h1>
            <p>{{ $state['original_name'] }}</p>
        </div>
    </section>

    <section class="section section-white">
        <div class="container">
            @include('admin.hvac.imports.guided.partials.wizard-messages')

            <div class="admin-detail-card">
                <p class="hvac-muted" style="margin-bottom:1rem;">
                    Dit werkboek bevat meerdere werkbladen. Kies het blad met de productgegevens.
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
                                    <span class="hvac-assumed">(verborgen werkblad)</span>
                                @endif
                            </label>
                        @endforeach
                    </div>
                    <div style="margin-top:1.2rem;display:flex;gap:0.6rem;">
                        <button type="submit" class="button button-primary">Volgende: kolomkoppen</button>
                    </div>
                </form>

                @include('admin.hvac.imports.guided.partials.cancel-form')
            </div>
        </div>
    </section>
@endsection
