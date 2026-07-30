@extends('layouts.app')

@section('title', 'Admin | Geblokkeerde e-mails')

@section('content')
    <section class="admin-hero">
        <div class="container">
            <span class="eyebrow">Admin</span>
            <h1>Geblokkeerde e-mails</h1>
            <p>Beheer e-mailadressen die het contactformulier niet meer mogen gebruiken.</p>
        </div>
    </section>

    <section class="section section-white">
        <div class="container">

            @if (session('success') === 'email_blocked')
                <div class="form-success">E-mailadres werd geblokkeerd voor het contactformulier.</div>
            @endif

            @if (session('success') === 'email_unblocked')
                <div class="form-success">E-mailadres werd gedeblokkeerd en kan het contactformulier opnieuw gebruiken.</div>
            @endif

            @if ($errors->any())
                <div class="form-error-list">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="admin-detail-layout">
                <div class="admin-detail-card">
                    <h2>E-mailadres blokkeren</h2>

                    <p style="font-size: 14px; color: #6b7c8f;">
                        Een geblokkeerd adres krijgt bij het contactformulier een neutrale melding en
                        er wordt geen e-mail meer verstuurd. Het slimme aanvraagformulier blijft
                        buiten deze blokkering.
                    </p>

                    <form method="POST" action="{{ route('admin.blocked-emails.store') }}" id="block-email-form">
                        @csrf

                        <label>
                            <span>E-mailadres</span>
                            <input type="email" name="email" value="{{ old('email') }}" maxlength="255" required>
                        </label>

                        <label>
                            <span>Interne reden (optioneel)</span>
                            <input type="text" name="reason" value="{{ old('reason') }}" maxlength="500">
                        </label>

                        <label>
                            <span>Duur</span>
                            <select name="duration" required>
                                <option value="permanent" @selected(old('duration', 'permanent') === 'permanent')>Permanent</option>
                                <option value="24h" @selected(old('duration') === '24h')>24 uur</option>
                                <option value="7d" @selected(old('duration') === '7d')>7 dagen</option>
                                <option value="30d" @selected(old('duration') === '30d')>30 dagen</option>
                            </select>
                        </label>

                        <button
                            type="button"
                            class="button admin-button-danger"
                            data-confirm-modal
                            data-confirm-title="E-mailadres blokkeren?"
                            data-confirm-body="Dit adres kan daarna geen berichten meer versturen via het contactformulier. De afzender krijgt hierover geen specifieke melding."
                            data-confirm-label="Blokkeren"
                            data-confirm-form="#block-email-form"
                        >Blokkeren</button>
                    </form>
                </div>

                <div class="admin-detail-card">
                    <h2>Geblokkeerde adressen</h2>

                    @if ($blockedEmails->isEmpty())
                        <p class="admin-muted-text">Er zijn nog geen geblokkeerde e-mailadressen.</p>
                    @else
                        <div class="admin-table-wrapper">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>E-mailadres</th>
                                        <th>Geblokkeerd op</th>
                                        <th>Reden</th>
                                        <th>Door</th>
                                        <th>Status</th>
                                        <th>Verloopt</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($blockedEmails as $blocked)
                                        @php
                                            $stateLabel = $blocked->isCurrentlyActive()
                                                ? 'Actief'
                                                : ($blocked->is_active ? 'Verlopen' : 'Inactief');
                                        @endphp
                                        <tr>
                                            <td data-label="E-mailadres">{{ $blocked->email }}</td>
                                            <td data-label="Geblokkeerd op">{{ $blocked->created_at->format('d/m/Y H:i') }}</td>
                                            <td data-label="Reden">{{ $blocked->reason ?: '-' }}</td>
                                            <td data-label="Door">{{ $blocked->blocked_by }}</td>
                                            <td data-label="Status">
                                                <span class="admin-status admin-blocked-state-{{ strtolower($stateLabel) }}">{{ $stateLabel }}</span>
                                            </td>
                                            <td data-label="Verloopt">
                                                {{ $blocked->expires_at ? $blocked->expires_at->format('d/m/Y H:i') : 'Nooit' }}
                                            </td>
                                            <td data-label="">
                                                @if ($blocked->isCurrentlyActive())
                                                    <form
                                                        method="POST"
                                                        action="{{ route('admin.blocked-emails.unblock', $blocked) }}"
                                                        id="unblock-email-{{ $blocked->id }}"
                                                        class="admin-delete-form"
                                                    >
                                                        @csrf
                                                        @method('PATCH')
                                                    </form>
                                                    <button
                                                        type="button"
                                                        class="button button-secondary admin-unblock-btn"
                                                        data-confirm-modal
                                                        data-confirm-title="E-mailadres deblokkeren?"
                                                        data-confirm-body="Weet u zeker dat u {{ $blocked->email }} wilt deblokkeren? Dit adres kan daarna opnieuw berichten versturen via het contactformulier."
                                                        data-confirm-label="Deblokkeren"
                                                        data-confirm-form="#unblock-email-{{ $blocked->id }}"
                                                    >Deblokkeren</button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    @include('partials.admin-confirm-modal')
@endsection
