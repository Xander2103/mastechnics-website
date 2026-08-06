@extends('layouts.app')

@section('title', 'Admin | HVAC-leveranciers')

@section('content')
    <section class="admin-hero">
        <div class="container">
            <span class="eyebrow">Admin</span>
            <h1>Leveranciers</h1>
        </div>
    </section>

    <section class="section section-white">
        <div class="container">
            @include('admin.hvac.partials.nav')

            @if (session('success'))
                <div class="form-success">Opgeslagen.</div>
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

            <div class="admin-detail-card">
                <form method="POST" action="{{ route('admin.hvac.suppliers.store') }}" class="hvac-form-grid" style="margin-bottom:1.25rem;">
                    @csrf
                    <label><span>Naam *</span><input type="text" name="name" required maxlength="150"></label>
                    <label><span>Code</span><input type="text" name="code" maxlength="50"></label>
                    <label><span>E-mail</span><input type="email" name="email" maxlength="255"></label>
                    <label><span>Telefoon</span><input type="text" name="phone" maxlength="50"></label>
                    <label><span>&nbsp;</span><button type="submit" class="button button-primary">+ Leverancier toevoegen</button></label>
                </form>

                @if ($suppliers->isEmpty())
                    <p class="admin-muted-text">Nog geen leveranciers.</p>
                @else
                    <div class="admin-table-wrapper">
                        <table class="admin-table">
                            <thead>
                                <tr><th>Naam</th><th>Code</th><th>E-mail</th><th>Telefoon</th><th>Producten</th><th>Status</th><th></th></tr>
                            </thead>
                            <tbody>
                                @foreach ($suppliers as $supplier)
                                    <tr class="{{ $supplier->is_active ? '' : 'hvac-inactive-row' }}">
                                        <td>{{ $supplier->name }}</td>
                                        <td>{{ $supplier->code ?? '—' }}</td>
                                        <td>{{ $supplier->email ?? '—' }}</td>
                                        <td>{{ $supplier->phone ?? '—' }}</td>
                                        <td>{{ $supplier->products_count }}</td>
                                        <td>{{ $supplier->is_active ? 'Actief' : 'Inactief' }}</td>
                                        <td>
                                            <form method="POST" action="{{ route('admin.hvac.suppliers.toggle', $supplier) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="admin-link" style="border:0;background:none;cursor:pointer;">
                                                    {{ $supplier->is_active ? 'Deactiveren' : 'Activeren' }}
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </section>
@endsection
