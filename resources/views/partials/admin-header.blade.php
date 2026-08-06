{{--
    Dedicated admin top navigation. Rendered by layouts/app.blade.php instead
    of the public marketing header when the request targets an admin.* route
    and an admin session is active. Admin UI is Dutch-only by design.
--}}
<header class="admin-header">
    <div class="container admin-header-container">
        <a class="site-logo admin-header-logo" href="{{ route('admin.requests.index') }}">
            <img
                src="{{ asset('assets/images/Logo.webp') }}"
                alt="MAS Technics"
                class="site-logo-img"
                width="176"
                height="44"
            >
        </a>

        <span class="admin-header-badge">Admin</span>

        <button
            class="admin-nav-toggle"
            type="button"
            aria-label="Adminmenu openen"
            aria-haspopup="true"
            aria-expanded="false"
            aria-controls="adminHeaderMenu"
        >
            <span></span>
            <span></span>
            <span></span>
        </button>

        <nav class="admin-nav" id="adminHeaderMenu" aria-label="Adminnavigatie">
            <a
                href="{{ route('admin.requests.index') }}"
                @if (request()->routeIs('admin.requests.*')) class="is-active" aria-current="page" @endif
            >Aanvragen</a>

            <a
                href="{{ route('admin.hvac.products.index') }}"
                @if (request()->routeIs('admin.hvac.*')) class="is-active" aria-current="page" @endif
            >HVAC-producten</a>

            <a
                href="{{ route('admin.account.edit') }}"
                @if (request()->routeIs('admin.account.*')) class="is-active" aria-current="page" @endif
            >Account</a>

            <a
                href="{{ route('admin.blocked-emails.index') }}"
                @if (request()->routeIs('admin.blocked-emails.*')) class="is-active" aria-current="page" @endif
            >Geblokkeerde e-mails</a>

            <form method="POST" action="{{ route('admin.logout') }}" class="admin-nav-logout">
                @csrf
                <button type="submit">Uitloggen</button>
            </form>
        </nav>
    </div>
</header>
