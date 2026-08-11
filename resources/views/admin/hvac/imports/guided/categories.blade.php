@extends('layouts.app')

@section('title', 'Admin | Import — producten kiezen')

@section('content')
    <section class="admin-hero">
        <div class="container">
            <span class="eyebrow">Productbestand importeren</span>
            <h1>Welke producten wilt u importeren?</h1>
            <p>{{ $state['original_name'] }}</p>
        </div>
    </section>

    <section class="section section-white">
        <div class="container">
            @include('admin.hvac.imports.guided.partials.progress', ['current' => 2])
            @include('admin.hvac.imports.guided.partials.wizard-messages')

            <div class="admin-detail-card">
                <p class="hvac-muted" style="margin-bottom:0.4rem;">
                    Dit bestand bevat productgroepen die niets met klimatisatie te maken hebben.
                    Vink aan wat u wilt importeren — de rest wordt niet ingelezen.
                </p>
                <p class="hvac-muted" style="margin-bottom:1rem;">
                    Groepen die op klimatisatie lijken staan al aangevinkt.
                </p>

                <form method="POST" action="{{ route('admin.hvac.import.guided.categories', $token) }}">
                    @csrf

                    <div style="display:flex;flex-wrap:wrap;gap:0.6rem;align-items:center;margin-bottom:0.8rem;">
                        <input type="search" id="category-search" placeholder="Zoeken in groepen…"
                               style="flex:1 1 14rem;max-width:20rem;padding:0.45rem 0.6rem;">
                        <button type="button" class="button" id="select-all">Alles selecteren</button>
                        <button type="button" class="button" id="select-none">Alles wissen</button>
                    </div>

                    <p style="margin-bottom:0.8rem;font-weight:600;" id="selection-count"
                       data-total="{{ $total }}">&nbsp;</p>

                    <div id="category-list"
                         style="display:flex;flex-direction:column;gap:0.35rem;max-height:24rem;overflow-y:auto;border:1px solid #e5e5e5;border-radius:8px;padding:0.8rem;">
                        @foreach ($values as $value => $count)
                            <label class="category-row" data-name="{{ mb_strtolower($value) }}"
                                   style="display:flex;align-items:center;gap:0.5rem;">
                                <input type="checkbox" name="categories[]" value="{{ $value }}"
                                       data-count="{{ $count }}"
                                       @checked(in_array($value, $preselected, true))>
                                <span style="flex:1;">{{ $value }}</span>
                                <span class="hvac-muted">{{ number_format($count, 0, ',', '.') }} producten</span>
                            </label>
                        @endforeach
                    </div>

                    <div style="margin-top:1.2rem;">
                        <button type="submit" class="button button-primary">Verder naar controle</button>
                    </div>
                </form>

                @include('admin.hvac.imports.guided.partials.cancel-form')
            </div>
        </div>
    </section>

    <script>
        (function () {
            const boxes = () => Array.from(document.querySelectorAll('#category-list input[type="checkbox"]'));
            const countEl = document.getElementById('selection-count');
            const total = parseInt(countEl.dataset.total, 10);

            function refresh() {
                let products = 0;
                boxes().forEach(b => { if (b.checked) products += parseInt(b.dataset.count, 10); });
                countEl.textContent = products.toLocaleString('nl-BE') + ' van ' + total.toLocaleString('nl-BE') + ' producten geselecteerd';
            }

            document.getElementById('select-all').addEventListener('click', () => {
                boxes().forEach(b => { if (b.closest('.category-row').style.display !== 'none') b.checked = true; });
                refresh();
            });
            document.getElementById('select-none').addEventListener('click', () => {
                boxes().forEach(b => b.checked = false);
                refresh();
            });
            document.getElementById('category-search').addEventListener('input', function () {
                const q = this.value.trim().toLowerCase();
                document.querySelectorAll('#category-list .category-row').forEach(row => {
                    row.style.display = q === '' || row.dataset.name.includes(q) ? '' : 'none';
                });
            });
            boxes().forEach(b => b.addEventListener('change', refresh));
            refresh();
        })();
    </script>
@endsection
