@extends('layouts.app')

@section('title', 'Admin | HVAC-merken')

@section('content')
    <section class="admin-hero">
        <div class="container">
            <span class="eyebrow">Admin</span>
            <h1>Merken</h1>
        </div>
    </section>

    <section class="section section-white">
        <div class="container">
            @include('admin.hvac.partials.nav')
            @include('admin.hvac.partials.catalog-tabs', ['activeTab' => 'brands'])

            @if (session('success'))
                <div class="form-success">Opgeslagen.</div>
            @endif

            <div class="admin-detail-card">
                <form method="POST" action="{{ route('admin.hvac.brands.store') }}" style="display:flex;gap:0.75rem;flex-wrap:wrap;margin-bottom:1.25rem;">
                    @csrf
                    <input type="text" name="name" placeholder="Merknaam" required maxlength="100"
                           style="padding:0.45rem 0.6rem;border:1px solid #d1d5db;border-radius:6px;">
                    <button type="submit" class="button button-primary">+ Merk toevoegen</button>
                </form>
                @error('name')
                    <p class="field-error-text">{{ $message }}</p>
                @enderror

                @if ($brands->isEmpty())
                    <p class="admin-muted-text">Nog geen merken.</p>
                @else
                    <div class="admin-table-wrapper">
                        <table class="admin-table">
                            <thead>
                                <tr><th>Naam</th><th>Producten</th><th>Status</th><th></th></tr>
                            </thead>
                            <tbody>
                                @foreach ($brands as $brand)
                                    <tr class="{{ $brand->is_active ? '' : 'hvac-inactive-row' }}">
                                        <td>{{ $brand->name }}</td>
                                        <td>{{ $brand->products_count }}</td>
                                        <td>{{ $brand->is_active ? 'Actief' : 'Inactief' }}</td>
                                        <td>
                                            <form method="POST" action="{{ route('admin.hvac.brands.toggle', $brand) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="admin-link" style="border:0;background:none;cursor:pointer;">
                                                    {{ $brand->is_active ? 'Deactiveren' : 'Activeren' }}
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
