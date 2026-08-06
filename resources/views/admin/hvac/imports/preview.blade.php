@extends('layouts.app')

@section('title', 'Admin | HVAC-import voorbeeld')

@section('content')
    <section class="admin-hero">
        <div class="container">
            <span class="eyebrow">Admin</span>
            <h1>Stap 2 — Voorbeeld en bevestiging</h1>
        </div>
    </section>

    <section class="section section-white">
        <div class="container">
            @include('admin.hvac.partials.nav')

            <div class="admin-detail-card">
                <p>
                    <strong>{{ $totalRows }}</strong> rijen gelezen:
                    <strong>{{ $createCount }}</strong> nieuw,
                    <strong>{{ $updateCount }}</strong> bij te werken,
                    <strong style="{{ $errorCount > 0 ? 'color:#dc2626;' : '' }}">{{ $errorCount }}</strong> met fouten
                    (rijen met fouten worden nooit geïmporteerd).
                </p>

                @foreach ($globalErrors as $globalError)
                    <p class="field-error-text">{{ $globalError }}</p>
                @endforeach

                <div class="admin-table-wrapper" style="margin-top:1rem;">
                    <table class="admin-table">
                        <thead>
                            <tr><th>Lijn</th><th>Actie</th><th>SKU</th><th>Model</th><th>Type</th><th>Koelvermogen</th><th>Fouten</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    <td>{{ $row['line'] }}</td>
                                    <td>
                                        @if ($row['errors'] !== [])
                                            <span style="color:#dc2626;">fout</span>
                                        @else
                                            {{ $row['action'] === 'update' ? 'bijwerken' : 'nieuw' }}
                                        @endif
                                    </td>
                                    <td>{{ $row['data']['sku'] }}</td>
                                    <td>{{ $row['data']['model'] }}</td>
                                    <td class="hvac-muted">{{ $row['data']['product_type'] }}</td>
                                    <td>{{ $row['data']['cooling_capacity_kw'] ?? '—' }}</td>
                                    <td style="color:#dc2626;font-size:0.8rem;">{{ implode(' | ', $row['errors']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($totalRows > $rows->count())
                    <p class="hvac-muted" style="margin-top:0.5rem;">Voorbeeld beperkt tot de eerste {{ $rows->count() }} rijen; de import verwerkt alle {{ $totalRows }} rijen.</p>
                @endif

                <div style="margin-top:1.25rem;display:flex;gap:0.75rem;flex-wrap:wrap;">
                    <form method="POST" action="{{ route('admin.hvac.import.confirm') }}">
                        @csrf
                        <input type="hidden" name="token" value="{{ $token }}">
                        <button type="submit" class="button button-primary">
                            Import bevestigen ({{ $createCount + $updateCount }} geldige rijen)
                        </button>
                    </form>
                    <a class="button button-secondary" href="{{ route('admin.hvac.import.index') }}">Annuleren</a>
                </div>
            </div>
        </div>
    </section>
@endsection
