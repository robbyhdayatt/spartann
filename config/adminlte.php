<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Title
    |--------------------------------------------------------------------------
    |
    | Here you can change the default title of your admin panel.
    |
    | For detailed instructions you can look the title section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Basic-Configuration
    |
    */

    'title' => 'SPARTAN',
    'title_prefix' => 'SPARTAN LTI | ',
    'title_postfix' => '',

    /*
    |--------------------------------------------------------------------------
    | Favicon
    |--------------------------------------------------------------------------
    |
    | Here you can activate the favicon.
    |
    | For detailed instructions you can look the favicon section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Basic-Configuration
    |
    */

    'use_ico_only' => false,
    'use_full_favicon' => false,

    /*
    |--------------------------------------------------------------------------
    | Google Fonts
    |--------------------------------------------------------------------------
    |
    | Here you can allow or not the use of external google fonts. Disabling the
    | google fonts may be useful if your admin panel internet access is
    | restricted somehow.
    |
    | For detailed instructions you can look the google fonts section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Basic-Configuration
    |
    */

    'google_fonts' => [
        'allowed' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin Panel Logo
    |--------------------------------------------------------------------------
    |
    | Here you can change the logo of your admin panel.
    |
    | For detailed instructions you can look the logo section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Basic-Configuration
    |
    */

    'logo' => '<b>SPARTAN</b>',
    'logo_img' => 'img/SPARTAN.png', // Ganti dengan nama file logo Anda
    'logo_img_class' => 'brand-image img-circle elevation-3',
    'logo_img_xl' => null,
    'logo_img_xl_class' => 'brand-image-xs',
    'logo_img_alt' => 'Admin Logo',

    /*
    |--------------------------------------------------------------------------
    | Authentication Logo
    |--------------------------------------------------------------------------
    |
    | Here you can setup an alternative logo to use on your login and register
    | screens. When disabled, the admin panel logo will be used instead.
    |
    | For detailed instructions you can look the auth logo section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Basic-Configuration
    |
    */

    'auth_logo' => [
        'enabled' => false, // Aktifkan
        'img' => [
            'path' => 'img/SPARTAN.png', // Ganti dengan logo yang lebih besar
            'alt' => 'SPARTAN Logo',
            'class' => '',
            'width' => 200, // Sesuaikan ukurannya
            'height' => 100, // Sesuaikan ukurannya
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Preloader Animation
    |--------------------------------------------------------------------------
    |
    | Here you can change the preloader animation configuration. Currently, two
    | modes are supported: 'fullscreen' for a fullscreen preloader animation
    | and 'cwrapper' to attach the preloader animation into the content-wrapper
    | element and avoid overlapping it with the sidebars and the top navbar.
    |
    | For detailed instructions you can look the preloader section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Basic-Configuration
    |
    */

    'preloader' => [
        'enabled' => true,
        'mode' => 'fullscreen',
        'img' => [
            'path' => 'img/SPARTAN.png',
            'alt' => 'SPARTAN Logo',
            'effect' => 'animation__shake',
            'width' => 200,
            'height' => 200,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Menu
    |--------------------------------------------------------------------------
    |
    | Here you can activate and change the user menu.
    |
    | For detailed instructions you can look the user menu section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Basic-Configuration
    |
    */

    'usermenu_enabled' => false,
    'usermenu_header' => false,
    'usermenu_header_class' => 'bg-primary',
    'usermenu_image' => false,
    'usermenu_desc' => true,


    /*
    |--------------------------------------------------------------------------
    | Layout
    |--------------------------------------------------------------------------
    |
    | Here we change the layout of your admin panel.
    |
    | For detailed instructions you can look the layout section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Layout-and-Styling-Configuration
    |
    */

    'layout_topnav' => null,
    'layout_boxed' => null,
    'layout_fixed_sidebar' => null,
    'layout_fixed_navbar' => null,
    'layout_fixed_footer' => null,
    'layout_dark_mode' => null,

    /*
    |--------------------------------------------------------------------------
    | Authentication Views Classes
    |--------------------------------------------------------------------------
    |
    | Here you can change the look and behavior of the authentication views.
    |
    | For detailed instructions you can look the auth classes section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Layout-and-Styling-Configuration
    |
    */

    'classes_auth_card' => '',
    'classes_auth_header' => '',
    'classes_auth_body' => '',
    'classes_auth_footer' => '',
    'classes_auth_icon' => '',
    'classes_auth_btn' => 'btn-flat btn-primary',

    /*
    |--------------------------------------------------------------------------
    | Admin Panel Classes
    |--------------------------------------------------------------------------
    |
    | Here you can change the look and behavior of the admin panel.
    |
    | For detailed instructions you can look the admin panel classes here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Layout-and-Styling-Configuration
    |
    */

    'classes_body' => '',
    'classes_brand' => '',
    'classes_brand_text' => '',
    'classes_content_wrapper' => '',
    'classes_content_header' => '',
    'classes_content' => '',
    'classes_sidebar' => 'sidebar-light-yamaha elevation-4',
    'classes_sidebar_nav' => '',
    'classes_topnav'  => 'navbar-white navbar-light',
    'classes_topnav_nav' => 'navbar-expand',
    'classes_topnav_container' => 'container',

    /*
    |--------------------------------------------------------------------------
    | Sidebar
    |--------------------------------------------------------------------------
    |
    | Here we can modify the sidebar of the admin panel.
    |
    | For detailed instructions you can look the sidebar section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Layout-and-Styling-Configuration
    |
    */

    'sidebar_mini' => 'lg',
    'sidebar_collapse' => false,
    'sidebar_collapse_auto_size' => false,
    'sidebar_collapse_remember' => false,
    'sidebar_collapse_remember_no_transition' => true,
    'sidebar_scrollbar_theme' => 'os-theme-light',
    'sidebar_scrollbar_auto_hide' => 'l',
    'sidebar_nav_accordion' => true,
    'sidebar_nav_animation_speed' => 300,

    /*
    |--------------------------------------------------------------------------
    | Control Sidebar (Right Sidebar)
    |--------------------------------------------------------------------------
    |
    | Here we can modify the right sidebar aka control sidebar of the admin panel.
    |
    | For detailed instructions you can look the right sidebar section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Layout-and-Styling-Configuration
    |
    */

    'right_sidebar' => false,
    'right_sidebar_icon' => 'fas fa-cogs',
    'right_sidebar_theme' => 'dark',
    'right_sidebar_slide' => true,
    'right_sidebar_push' => true,
    'right_sidebar_scrollbar_theme' => 'os-theme-light',
    'right_sidebar_scrollbar_auto_hide' => 'l',

    /*
    |--------------------------------------------------------------------------
    | URLs
    |--------------------------------------------------------------------------
    |
    | Here we can modify the url settings of the admin panel.
    |
    | For detailed instructions you can look the urls section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Basic-Configuration
    |
    */

    'use_route_url' => false,
    'dashboard_url' => '#',
    'logout_url' => 'logout',
    'login_url' => 'login',
    'register_url' => 'register',
    'password_reset_url' => 'password/reset',
    'password_email_url' => 'password/email',
    'profile_url' => false,
    'disable_darkmode_routes' => false,

    /*
    |--------------------------------------------------------------------------
    | Laravel Asset Bundling
    |--------------------------------------------------------------------------
    |
    | Here we can enable the Laravel Asset Bundling option for the admin panel.
    | Currently, the next modes are supported: 'mix', 'vite' and 'vite_js_only'.
    | When using 'vite_js_only', it's expected that your CSS is imported using
    | JavaScript. Typically, in your application's 'resources/js/app.js' file.
    | If you are not using any of these, leave it as 'false'.
    |
    | For detailed instructions you can look the asset bundling section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Other-Configuration
    |
    */

    'laravel_asset_bundling' => false,
    'laravel_css_path' => 'css/app.css',
    'laravel_js_path' => 'js/app.js',

    /*
    |--------------------------------------------------------------------------
    | Menu Items
    |--------------------------------------------------------------------------
    |
    | Here we can modify the sidebar/top navigation of the admin panel.
    |
    | For detailed instructions you can look here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Menu-Configuration
    |
    */

    // config/adminlte.php

    'menu' => [
        // Tombol search & fullscreen
        ['search' => false, 'topnav' => true],
        ['type' => 'fullscreen-widget', 'topnav_right' => true],

        // Menu Utama
        [
            'text' => 'Dashboard',
            'route'  => 'admin.home',
            'icon' => 'fas fa-fw fa-tachometer-alt',
        ],
        [
            'text' => 'Profil Saya',
            'route'  => 'admin.profile.show',
            'icon' => 'fas fa-fw fa-user',
        ],

        // ================== MENU PUSAT & GLOBAL ==================
        [
            'header' => 'MASTER & PENGATURAN',
            'can' => 'manage-pic-level', // Hanya SA & PIC
        ],
        [
            'text' => 'Manajemen Lokasi',
            'icon' => 'fas fa-fw fa-map-marked-alt',
            'can'  => 'manage-locations',
            'submenu' => [
                ['text' => 'Gudang & Dealer', 'icon' => 'fas fa-fw fa-warehouse', 'route' => 'admin.lokasi.index'],
                ['text' => 'Master Dealer', 'icon' => 'fas fa-fw fa-store', 'route' => 'admin.dealers.index'],
                ['text' => 'Rak', 'icon' => 'fas fa-fw fa-pallet', 'route' => 'admin.raks.index'],
            ],
        ],
        [
            'text'    => 'Master Data',
            'icon'    => 'fas fa-fw fa-database',
            'can'     => 'view-master-data',
            'submenu' => [
                ['text' => 'Brand', 'route' => 'admin.brands.index'],
                ['text' => 'Kategori', 'route' => 'admin.categories.index'],
                ['text' => 'Supplier', 'route' => 'admin.suppliers.index'],
                ['text' => 'Part', 'route' => 'admin.parts.index'],
                ['text' => 'Konsumen', 'route' => 'admin.konsumens.index'],
            ],
        ],
        [
            'text' => 'Pengguna',
            'route'  => 'admin.users.index',
            'icon' => 'fas fa-fw fa-users-cog',
            'can' => 'manage-users', // Hanya Super Admin
        ],

        // ================== MENU OPERASIONAL ==================
        [
            'header' => 'OPERASIONAL',
        ],
        [
            'text' => 'Transaksi Pusat',
            'icon' => 'fas fa-fw fa-industry',
            'can'  => 'is-pusat-staff',
            'submenu' => [
                ['text' => 'Purchase Order (PO)', 'route' => 'admin.purchase-orders.index', 'can' => 'access-po-module'],
                
                // PERBAIKAN: Hak akses disederhanakan di bawah ini
                ['text' => 'Penerimaan Barang', 'route' => 'admin.receivings.index', 'can' => 'perform-warehouse-ops'],
                ['text' => 'Quality Control (QC)', 'route' => 'admin.qc.index', 'can' => 'perform-warehouse-ops'],
                ['text' => 'Penyimpanan (Putaway)', 'route' => 'admin.putaway.index', 'can' => 'perform-warehouse-ops'],
                
                ['text' => 'Adjusment Stok', 'route' => 'admin.stock-adjustments.index'],
                ['text' => 'Mutasi Stok', 'route' => 'admin.stock-mutations.index'],
                ['text' => 'Penerimaan Mutasi', 'route' => 'admin.mutation-receiving.index'],
                ['text' => 'Stok Karantina', 'route' => 'admin.quarantine-stock.index'],
                ['text' => 'Retur Pembelian', 'route' => 'admin.purchase-returns.index'],
            ],
        ],
        [
            'text' => 'Transaksi Dealer',
            'icon' => 'fas fa-fw fa-store-alt',
            'can'  => 'is-dealer-staff',
            'submenu' => [
                ['text' => 'Penerimaan Mutasi', 'route' => 'admin.mutation-receiving.index'],
                ['text' => 'Adjusment Stok', 'route' => 'admin.stock-adjustments.index'],
                ['text' => 'Mutasi Stok', 'route' => 'admin.stock-mutations.index'],
                ['text' => 'Stok Karantina', 'route' => 'admin.quarantine-stock.index'],
            ],
        ],
        [
            'text'    => 'Penjualan',
            'icon'    => 'fas fa-fw fa-cash-register',
            'can'     => 'access-sales-module',
            'submenu' => [
                ['text' => 'Buat Transaksi', 'route' => 'admin.penjualans.create'],
                ['text' => 'Riwayat Penjualan', 'route' => 'admin.penjualans.index'],
                ['text' => 'Retur Penjualan', 'route' => 'admin.sales-returns.index'],
            ],
        ],
        [
            'text' => 'Data Service',
            'route'  => 'admin.services.index',
            'icon' => 'fas fa-fw fa-wrench',
            'can'  => ['is-super-admin', 'is-pic', 'is-manager'] // Contoh, sesuaikan
        ],

        // ================== MENU ANALISIS ==================
        [
            'header' => 'ANALISIS & MARKETING',
            'can' => ['is-manager', 'is-pic'],
        ],
        [
            'text'    => 'Marketing',
            'icon'    => 'fas fa-fw fa-bullhorn',
            'can'     => 'manage-marketing',
            'submenu' => [
                ['text' => 'Manajemen Campaign', 'route' => 'admin.campaigns.index'],
                ['text' => 'Kategori Diskon', 'route' => 'admin.customer-discount-categories.index'],
                [
                    'text'    => 'Insentif Sales',
                    'submenu' => [
                        ['text' => 'Set Target', 'route' => 'admin.incentives.targets'],
                        ['text' => 'Laporan Insentif', 'route' => 'admin.incentives.report'],
                    ],
                ],
            ],
        ],
        [
            'text'    => 'Laporan',
            'icon'    => 'fas fa-fw fa-chart-pie',
            'can'     => 'view-reports',
            'submenu' => [
                ['text' => 'Kartu Stok', 'route' => 'admin.reports.stock-card'],
                ['text' => 'Stok Per Lokasi', 'route' => 'admin.reports.stock-by-warehouse'],
                ['text' => 'Laporan Stok Total', 'route' => 'admin.reports.stock-report'],
                ['text' => 'Jurnal Penjualan', 'route' => 'admin.reports.sales-journal'],
                ['text' => 'Jurnal Pembelian', 'route' => 'admin.reports.purchase-journal'],
                ['text' => 'Nilai Persediaan', 'route' => 'admin.reports.inventory-value'],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Menu Filters
    |--------------------------------------------------------------------------
    |
    | Here we can modify the menu filters of the admin panel.
    |
    | For detailed instructions you can look the menu filters section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Menu-Configuration
    |
    */

    'filters' => [
        JeroenNoten\LaravelAdminLte\Menu\Filters\GateFilter::class,
        JeroenNoten\LaravelAdminLte\Menu\Filters\HrefFilter::class,
        JeroenNoten\LaravelAdminLte\Menu\Filters\SearchFilter::class,
        JeroenNoten\LaravelAdminLte\Menu\Filters\ActiveFilter::class,
        JeroenNoten\LaravelAdminLte\Menu\Filters\ClassesFilter::class,
        JeroenNoten\LaravelAdminLte\Menu\Filters\LangFilter::class,
        JeroenNoten\LaravelAdminLte\Menu\Filters\DataFilter::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Plugins Initialization
    |--------------------------------------------------------------------------
    |
    | Here we can modify the plugins used inside the admin panel.
    |
    | For detailed instructions you can look the plugins section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Plugins-Configuration
    |
    */

    'plugins' => [
        'Datatables' => [
            'active' => true,
            'files' => [
                [
                    'type' => 'js',
                    'asset' => false,
                    'location' => '//cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js',
                ],
                [
                    'type' => 'js',
                    'asset' => false,
                    'location' => '//cdn.datatables.net/1.10.19/js/dataTables.bootstrap4.min.js',
                ],
                [
                    'type' => 'css',
                    'asset' => false,
                    'location' => '//cdn.datatables.net/1.10.19/css/dataTables.bootstrap4.min.css',
                ],
            ],
        ],
        'Select2' => [
            'active' => true,
            'files' => [
                [
                    'type' => 'js',
                    'asset' => false,
                    'location' => '//cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/select2.min.js',
                ],
                [
                    'type' => 'css',
                    'asset' => false,
                    'location' => '//cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/css/select2.css',
                ],
                [
                    'type' => 'css',
                    'asset' => true, // Ganti dari false ke true jika sebelumnya false
                    'location' => 'vendor/select2-bootstrap4-theme/select2-bootstrap4.min.css',
                ],
            ],
        ],
        'BsCustomFileInput' => [
            'active' => true,
            'files' => [
                [
                    'type' => 'js',
                    'asset' => true,
                    'location' => 'vendor/bs-custom-file-input/bs-custom-file-input.min.js',
                ],
            ],
        ],
        'Chartjs' => [
            'active' => true,
            'files' => [
                [
                    'type' => 'js',
                    'asset' => false,
                    'location' => '//cdnjs.cloudflare.com/ajax/libs/Chart.js/2.7.0/Chart.bundle.min.js',
                ],
            ],
        ],
        'Sweetalert2' => [
            'active' => true,
            'files' => [
                [
                    'type' => 'js',
                    'asset' => false,
                    'location' => '//cdn.jsdelivr.net/npm/sweetalert2@8',
                ],
            ],
        ],
        'Pace' => [
            'active' => false,
            'files' => [
                [
                    'type' => 'css',
                    'asset' => false,
                    'location' => '//cdnjs.cloudflare.com/ajax/libs/pace/1.0.2/themes/blue/pace-theme-center-radar.min.css',
                ],
                [
                    'type' => 'js',
                    'asset' => false,
                    'location' => '//cdnjs.cloudflare.com/ajax/libs/pace/1.0.2/pace.min.js',
                ],
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | IFrame
    |--------------------------------------------------------------------------
    |
    | Here we change the IFrame mode configuration. Note these changes will
    | only apply to the view that extends and enable the IFrame mode.
    |
    | For detailed instructions you can look the iframe mode section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/IFrame-Mode-Configuration
    |
    */

    'iframe' => [
        'default_tab' => [
            'url' => null,
            'title' => null,
        ],
        'buttons' => [
            'close' => true,
            'close_all' => true,
            'close_all_other' => true,
            'scroll_left' => true,
            'scroll_right' => true,
            'fullscreen' => true,
        ],
        'options' => [
            'loading_screen' => 1000,
            'auto_show_new_tab' => true,
            'use_navbar_items' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Livewire
    |--------------------------------------------------------------------------
    |
    | Here we can enable the Livewire support.
    |
    | For detailed instructions you can look the livewire here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Other-Configuration
    |
    */

    'livewire' => false,

];
