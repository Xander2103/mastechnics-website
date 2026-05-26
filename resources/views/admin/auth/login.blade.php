@extends('layouts.app')

@section('title', 'Admin | Login')

@section('content')
    <section class="admin-hero">
        <div class="container">
            <span class="eyebrow">Admin</span>
            <h1>Admin login</h1>
            <p>Log in om aanvragen te bekijken en op te volgen.</p>
        </div>
    </section>

    <section class="section section-white">
        <div class="container">
            <div class="admin-login-card">
                <h2>Inloggen</h2>

                @if ($errors->any())
                    <div class="form-error-list">
                        <strong>Controleer je gegevens.</strong>

                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.login.submit') }}">
                    @csrf

                    <label>
                        <span>E-mailadres</span>
                        <input type="email" name="email" value="{{ old('email') }}" required>
                    </label>

                    <label>
                        <span>Wachtwoord</span>
                        <input type="password" name="password" required>
                    </label>

                    <button class="button button-primary button-large" type="submit">
                        Inloggen
                    </button>
                </form>
            </div>
        </div>
    </section>
@endsection