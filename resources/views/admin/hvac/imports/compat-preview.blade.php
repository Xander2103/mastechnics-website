@extends('layouts.app')

@section('title', 'Admin | Compatibiliteitsimport voorbeeld')

@section('content')
    <section class="admin-hero">
        <div class="container">
            <span class="eyebrow">Admin</span>
            <h1>Compatibiliteit — voorbeeld en bevestiging</h1>
        </div>
    </section>

    <section class="section section-white">
        <div class="container">
            @include('admin.hvac.partials.nav')

            <div class="admin-detail-card">
                <p>
                    <strong>{{ $totalRows }}</strong> rijen gelezen:
                    <strong>{{ $validCount }}</strong> geldig,
                    <strong style="{{ $errorCount > 0 ? 'color:#dc2626;' : '' }}">{{ $errorCount }}</strong> met fouten
                    (rijen met fouten worden nooit geïmporteerd).
                </p>

                @foreach ($globalErrors as $globalError)
                    <p class="field-error-text">{{ $globalError }}</p>
                @endforeach

                <div class="admin-table-wrapper" style="margin-top:1rem;">
                    <table class="admin-table">
                        <thead>
                            <tr><th>Lijn</th><th>Actie</th><th>Hoofd-SKU</th><th>Compatibel SKU</th><th>Type</th><th>Max. units</th><th>Fouten</th></tr>
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
                                    <td>{{ $row['data']['parent_sku'] }}</td>
                                    <td>{{ $row['data']['compatible_sku'] }}</td>
                                    <td class="hvac-muted">{{ $row['data']['compatibility_type'] }}</td>
                                    <td>{{ $row['data']['maximum_units'] ?? '—' }}</td>
                                    <td style="color:#dc2626;font-size:0.8rem;">{{ implode(' | ', $row['errors']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div style="margin-top:1.25rem;display:flex;gap:0.75rem;flex-wrap:wrap;">
                    <form method="POST" action="{{ route('admin.hvac.import.compat.confirm') }}">
                        @csrf
                        <input type="hidden" name="token" value="{{ $token }}">
                        <button type="submit" class="button button-primary">
                            Import bevestigen ({{ $validCount }} geldige rijen)
                        </button>
                    </form>
                    <a class="button button-secondary" href="{{ route('admin.hvac.import.index') }}">Annuleren</a>
                </div>
            </div>
        </div>
    </section>
@endsection
