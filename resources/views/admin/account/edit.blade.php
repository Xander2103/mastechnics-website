@extends('layouts.app')

@section('title', 'Admin | Account')

@section('content')
    <section class="admin-hero">
        <div class="container">
            <span class="eyebrow">Admin</span>
            <h1>Account</h1>
            <p>Beheer het e-mailadres en wachtwoord van je adminaccount.</p>
        </div>
    </section>

    <section class="section section-white">
        <div class="container">

            @if (session('success') === 'account_email_updated')
                <div class="form-success">E-mailadres werd bijgewerkt. Je blijft ingelogd met het nieuwe adres.</div>
            @endif

            @if (session('success') === 'account_password_updated')
                <div class="form-success">Wachtwoord werd bijgewerkt.</div>
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
                    <h2>E-mailadres wijzigen</h2>

                    <p>Huidig e-mailadres: <strong>{{ session('admin_user_email') }}</strong></p>

                    <form method="POST" action="{{ route('admin.account.email.update') }}">
                        @csrf
                        @method('PATCH')

                        <label>
                            <span>Nieuw e-mailadres</span>
                            <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
                        </label>

                        <label>
                            <span>Huidig wachtwoord</span>
                            <input type="password" name="current_password" required autocomplete="current-password">
                        </label>

                        <button type="submit" class="button button-primary">Opslaan</button>
                    </form>

                    <p style="margin-top: 12px; font-size: 13px; color: #6b7c8f;">
                        Historische vermeldingen (zoals wie een standaardantwoord verstuurde of een notitie schreef) behouden bewust het oude e-mailadres.
                    </p>
                </div>

                <div class="admin-detail-card">
                    <h2>Wachtwoord wijzigen</h2>

                    <form method="POST" action="{{ route('admin.account.password.update') }}">
                        @csrf
                        @method('PATCH')

                        <label>
                            <span>Huidig wachtwoord</span>
                            <input type="password" name="current_password" required autocomplete="current-password">
                        </label>

                        <label>
                            <span>Nieuw wachtwoord (minstens 12 tekens, met letters en cijfers)</span>
                            <input type="password" name="password" required minlength="12" autocomplete="new-password">
                        </label>

                        <label>
                            <span>Bevestig nieuw wachtwoord</span>
                            <input type="password" name="password_confirmation" required minlength="12" autocomplete="new-password">
                        </label>

                        <button type="submit" class="button button-primary">Opslaan</button>
                    </form>
                </div>

                <div class="admin-detail-card">
                    <h2>Beheerders</h2>

                    <p style="font-size: 14px; color: #6b7c8f;">
                        Elke beheerder heeft een eigen account en wijzigt hier enkel de eigen gegevens.
                        Nieuwe accounts worden veilig aangemaakt via <code>php artisan admin:create</code>.
                    </p>

                    <div class="admin-table-wrapper">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Naam</th>
                                    <th>E-mailadres</th>
                                    <th>Aangemaakt op</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($admins as $admin)
                                    <tr>
                                        <td data-label="Naam">{{ $admin->name }}</td>
                                        <td data-label="E-mailadres">
                                            {{ $admin->email }}
                                            @if ($admin->email === session('admin_user_email'))
                                                <span class="admin-status admin-status-new">Ingelogd</span>
                                            @endif
                                        </td>
                                        <td data-label="Aangemaakt op">{{ $admin->created_at?->format('d/m/Y') ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
