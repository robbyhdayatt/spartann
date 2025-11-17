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
    <ul class="navbar-nav ml-auto">
        {{-- Custom right links --}}
        @yield('content_top_nav_right')

        {{-- Configured right links (seperti fullscreen) --}}
        @each('adminlte::partials.navbar.menu-item', $adminlte->menu('navbar-right'), 'item')

        {{-- ++ PERUBAHAN: Tambahkan Logout Link Secara Eksplisit di Sini ++ --}}
        {{-- Ini akan muncul meskipun usermenu_enabled=true, karena kita includekan langsung --}}
        @if(Auth::user())
            @include('adminlte::partials.navbar.menu-item-logout-link')
        @endif

        {{-- User menu link (dropdown) --}}
        {{-- Biarkan logika ini jika Anda masih ingin dropdown user muncul di paling kanan --}}
        @if(Auth::user() && config('adminlte.usermenu_enabled'))
            @include('adminlte::partials.navbar.menu-item-dropdown-user-menu')
        @endif

        {{-- Right sidebar toggler link --}}
        @if($layoutHelper->isRightSidebarEnabled())
            @include('adminlte::partials.navbar.menu-item-right-sidebar-toggler')
        @endif
    </ul>

</nav>
