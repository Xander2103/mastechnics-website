@extends('layouts.app')

@section('title', 'Admin | Contactberichten')

@section('content')
    <section class="admin-hero">
        <div class="container">
            <span class="eyebrow">Admin</span>
            <h1>Contactberichten</h1>
            <p>Berichten via het contactformulier. Twijfelgevallen van de formulierbeveiliging staan hier zonder dat er mail werd verstuurd.</p>
        </div>
    </section>

    <section class="section section-white">
        <div class="container">
            <div class="admin-panel">
                <form class="admin-filter-form admin-filter-form-inline" method="GET" action="{{ route('admin.contact-submissions.index') }}">
                    <label>
                        <span>Beveiliging</span>
                        <select name="trust" onchange="this.form.submit()">
                            <option value="">Alle berichten</option>
                            @foreach ($trustFilters as $value => $label)
                                <option value="{{ $value }}" @selected($trust === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <div class="admin-filter-actions">
                        <button type="submit" class="button button-primary">Filteren</button>
                        <a class="button button-secondary" href="{{ route('admin.contact-submissions.index') }}">Wissen</a>
                    </div>
                </form>

                <div class="admin-panel-header">
                    <h2>Berichten</h2>
                    <p>{{ $submissions->total() }} {{ $submissions->total() === 1 ? 'bericht' : 'berichten' }} · nieuwste eerst</p>
                </div>

                @if ($submissions->isEmpty())
                    <p class="admin-empty">Geen contactberichten gevonden.</p>
                @else
                    <div class="admin-table-wrapper">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Datum</th>
                                    <th>Naam</th>
                                    <th>E-mail</th>
                                    <th>Onderwerp</th>
                                    <th>Beveiliging</th>
                                    <th>Mail</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($submissions as $submission)
                                    <tr>
                                        <td data-label="Datum">{{ $submission->created_at?->format('d/m/Y H:i') }}</td>
                                        <td data-label="Naam">{{ $submission->name }}</td>
                                        <td data-label="E-mail">{{ $submission->email }}</td>
                                        <td data-label="Onderwerp">{{ $submission->subject }}</td>
                                        <td data-label="Beveiliging">
                                            <span class="admin-trust-badge admin-trust-badge-{{ $submission->trust_verdict }}">
                                                {{ $trustFilters[$submission->trust_verdict] ?? $submission->trust_verdict }}
                                            </span>
                                        </td>
                                        <td data-label="Mail">{{ $submission->mail_sent_at ? 'Verzonden ' . $submission->mail_sent_at->format('d/m H:i') : 'Niet verzonden' }}</td>
                                        <td data-label="">
                                            <a class="admin-link" href="{{ route('admin.contact-submissions.show', $submission) }}">Bekijken</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="admin-security-pagination">
                        {{ $submissions->links() }}
                    </div>
                @endif
            </div>
        </div>
    </section>
@endsection
