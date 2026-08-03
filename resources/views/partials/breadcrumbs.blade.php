{{-- Visible breadcrumb trail. Mirrors exactly the BreadcrumbList emitted in
     the <head>; Google only shows breadcrumb rich results when the markup
     matches something the visitor can actually see. Renders nothing on the
     homepage, where a trail would be a self-reference. --}}
@if (!empty($crumbs))
    <nav class="breadcrumbs" aria-label="{{ $locale === 'fr' ? 'Fil d\'Ariane' : ($locale === 'en' ? 'Breadcrumb' : 'Kruimelpad') }}">
        <div class="container">
            <ol class="breadcrumbs-list">
                @foreach ($crumbs as $crumb)
                    <li class="breadcrumbs-item">
                        @if (!empty($crumb['url']))
                            <a href="{{ $crumb['url'] }}">{{ $crumb['name'] }}</a>
                            <span class="breadcrumbs-separator" aria-hidden="true">›</span>
                        @else
                            <span aria-current="page">{{ $crumb['name'] }}</span>
                        @endif
                    </li>
                @endforeach
            </ol>
        </div>
    </nav>
@endif
