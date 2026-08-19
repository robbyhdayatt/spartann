@inject('layoutHelper', 'JeroenNoten\LaravelAdminLte\Helpers\LayoutHelper')

<nav class="main-header navbar
    {{ config('adminlte.classes_topnav_nav', 'navbar-expand') }}
    {{ config('adminlte.classes_topnav', 'navbar-white navbar-light') }}">

    {{-- Navbar left links --}}
    <ul class="navbar-nav">
        {{-- Left sidebar toggler link --}}
        @if (!config('adminlte.layout_topnav'))
            @include('adminlte::partials.navbar.menu-item-left-sidebar-toggler')
        @endif

        {{-- Configured left links --}}
        @each('adminlte::partials.navbar.menu-item', $adminlte->menu('navbar-left'), 'item')

        {{-- Custom left links --}}
        @yield('content_top_nav_left')
    </ul>

    {{-- Navbar right links --}}
    <ul class="navbar-nav ml-auto align-items-center">
        {{-- Badge Informasi User Aktif & Dealer --}}
        @if(Auth::user())
            <li class="nav-item d-none d-md-inline-flex align-items-center mr-3">
                <div class="user-info-badge">
                    <i class="fas fa-user-circle"></i>
                    <span>{{ Auth::user()->name }}</span>
                    @if(Auth::user()->lokasi)
                        <span class="dealer-tag"><i class="fas fa-store mr-1"></i>{{ Auth::user()->lokasi->nama_lokasi ?? Auth::user()->lokasi->kode_lokasi }}</span>
                    @else
                        <span class="dealer-tag"><i class="fas fa-building mr-1"></i>Pusat / LTI</span>
                    @endif
                </div>
            </li>
        @endif

        {{-- Custom right links --}}
        @yield('content_top_nav_right')

        {{-- Configured right links (seperti fullscreen) --}}
        @each('adminlte::partials.navbar.menu-item', $adminlte->menu('navbar-right'), 'item')

        {{-- Tombol Logout Link --}}
        @if(Auth::user())
            @include('adminlte::partials.navbar.menu-item-logout-link')
        @endif

        {{-- User menu link (dropdown) --}}
        @if(Auth::user() && config('adminlte.usermenu_enabled'))
            @include('adminlte::partials.navbar.menu-item-dropdown-user-menu')
        @endif

        {{-- Right sidebar toggler link --}}
        @if($layoutHelper->isRightSidebarEnabled())
            @include('adminlte::partials.navbar.menu-item-right-sidebar-toggler')
        @endif
    </ul>

</nav>
