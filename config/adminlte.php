<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Title
    |--------------------------------------------------------------------------
    */

    'title' => 'SPARTAN',
    'title_prefix' => 'SPARTAN LTI | ',
    'title_postfix' => '',

    /*
    |--------------------------------------------------------------------------
    | Favicon
    |--------------------------------------------------------------------------
    */

    'use_ico_only' => false,
    'use_full_favicon' => false,

    /*
    |--------------------------------------------------------------------------
    | Google Fonts
    |--------------------------------------------------------------------------
    */

    'google_fonts' => [
        'allowed' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin Panel Logo
    |--------------------------------------------------------------------------
    */

    'logo' => '<b>SPARTAN</b>',
    'logo_img' => 'img/SPARTAN.png',
    'logo_img_class' => 'brand-image img-circle elevation-3',
    'logo_img_xl' => null,
    'logo_img_xl_class' => 'brand-image-xs',
    'logo_img_alt' => 'Admin Logo',

    /*
    |--------------------------------------------------------------------------
    | Authentication Logo
    |--------------------------------------------------------------------------
    */

    'auth_logo' => [
        'enabled' => true,
        'img' => [
            'path' => 'img/SPARTAN.png',
            'alt' => 'SPARTAN Logo',
            'class' => '',
            'width' => 200,
            'height' => 100,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Preloader Animation
    |--------------------------------------------------------------------------
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
    */

    'usermenu_enabled' => true, // Biarkan true jika ingin dropdown user tetap ada
    'usermenu_header' => false,
    'usermenu_header_class' => 'bg-primary',
    'usermenu_image' => false,
    'usermenu_desc' => true,

    /*
    |--------------------------------------------------------------------------
    | Layout
    |--------------------------------------------------------------------------
    */

    'layout_topnav' => null,
    'layout_boxed' => null,
    'layout_fixed_sidebar' => true,
    'layout_fixed_navbar' => true,
    'layout_fixed_footer' => null,
    'layout_dark_mode' => null,

    /*
    |--------------------------------------------------------------------------
    | Authentication Views Classes
    |--------------------------------------------------------------------------
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
    */

    'classes_body' => '',
    'classes_brand' => '',
    'classes_brand_text' => '',
    'classes_content_wrapper' => '',
    'classes_content_header' => '',
    'classes_content' => '',
    'classes_sidebar' => 'sidebar-light-primary elevation-4',
    'classes_sidebar_nav' => '',
    'classes_topnav'  => 'navbar-white navbar-light',
    'classes_topnav_nav' => 'navbar-expand',
    'classes_topnav_container' => 'container',

    /*
    |--------------------------------------------------------------------------
    | Sidebar
    |--------------------------------------------------------------------------
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
    */

    'use_route_url' => false,
    'dashboard_url' => 'admin/home',
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
    */

    'laravel_asset_bundling' => false,
    'laravel_css_path' => 'css/app.css',
    'laravel_js_path' => 'js/app.js',

    'menu' => [
        // Navbar items:
        ['type' => 'fullscreen-widget', 'topnav_right' => true],
        [
            'type'         => 'logout-link',
            'icon'         => 'fas fa-sign-out-alt',
            'topnav_right' => true,
        ],

        // Sidebar items:
        [
            'text' => 'Dashboard',
            'route'  => 'admin.home',
            'icon' => 'fas fa-fw fa-tachometer-alt',
        ],
        [
            'text' => 'Profil Saya',
            'route'  => 'admin.profile.show',
            'icon' => 'fas fa-fw fa-user-circle',
        ],

        // ================== MASTER & PENGATURAN ==================
        [
            'header' => 'MASTER & PENGATURAN',
            'can'    => ['manage-locations', 'view-master-data', 'manage-users', 'manage-converts', 'manage-barangs'],
        ],
        // 1. MANAJEMEN LOKASI
        [
            'text' => 'Manajemen Lokasi',
            'icon' => 'fas fa-fw fa-map-marked-alt',
            'can'  => 'manage-locations-sa-only',
            'submenu' => [
                ['text' => 'Lokasi', 'icon' => 'fas fa-fw fa-warehouse', 'route' => 'admin.lokasi.index'],
                ['text' => 'Rak', 'icon' => 'fas fa-fw fa-pallet', 'route' => 'admin.raks.index'],
            ],
        ],
        [
            'text'    => 'Master Data',
            'icon'    => 'fas fa-fw fa-database',
            'can'     => ['view-master-data', 'manage-converts', 'manage-barangs',],
            'submenu' => [
                ['text' => 'Supplier', 'icon' => 'fas fa-fw fa-people-carry', 'route' => 'admin.suppliers.index', 'can' => 'view-master-data'],
                ['text' => 'Master Convert','route'=> 'admin.converts.index','icon' => 'fas fa-fw fa-exchange-alt','can'  => 'manage-converts',],
                ['text' => 'Item','icon' => 'fas fa-fw fa-box-open','route' => 'admin.barangs.index','can'  => 'manage-barangs'],
            ],
        ],
        [
            'text' => 'Pengguna',
            'route'  => 'admin.users.index',
            'icon' => 'fas fa-fw fa-users-cog',
            'can' => 'manage-users',
        ],

        // ================== OPERASIONAL ==================
        [
            'header' => 'OPERASIONAL',
            'can' => ['is-pusat-staff', 'is-dealer-staff', 'is-pic'],
        ],

        // --- PEMBELIAN (PO) ---
        [
            'text'    => 'Pembelian (PO)',
            'icon'    => 'fas fa-shopping-cart',
            'can'     => 'view-po-module',
            'submenu' => [
                [
                    'text' => 'Buat Request PO',
                    'url'  => 'admin/purchase-orders/create',
                    'icon' => 'fas fa-plus',
                    'can'  => 'create-po',
                ],
                [
                    'text' => 'Daftar PO',
                    'url'  => 'admin/purchase-orders',
                    'icon' => 'fas fa-list',
                ],
                [
                    'text' => 'Retur Pembelian',
                    'icon' => 'fas fa-fw fa-undo-alt',
                    'route' => 'admin.purchase-returns.index',
                    'can' => 'manage-purchase-returns'
                ],
            ],
        ],

        // --- PENERIMAAN (INBOUND) ---
        [
            'text'    => 'Penerimaan (Inbound)',
            'icon'    => 'fas fa-truck-loading',
            'can'     => 'perform-warehouse-ops-exclude-kg', 
            'submenu' => [
                [
                    'text'  => 'Receiving PO', 
                    'route' => 'admin.receivings.index',
                    'icon'  => 'fas fa-dolly',
                ],
                [
                    'text' => 'Receiving Mutasi',
                    'icon' => 'fas fa-fw fa-truck-moving',
                    'route' => 'admin.mutation-receiving.index',
                ],
                [
                    'text'  => 'Quality Control (QC)',
                    'route' => 'admin.qc.index',
                    'icon'  => 'fas fa-check-double',
                    'can'   => 'view-qc-menu',
                ],
                [
                    'text'  => 'Putaway (Rak)',
                    'route' => 'admin.putaway.index',
                    'icon'  => 'fas fa-box-open',
                ],
                [
                    'text'  => 'Stok Karantina',
                    'route' => 'admin.quarantine-stock.index',
                    'icon'  => 'fas fa-ban',
                    'can'   => 'manage-quarantine-stock',
                ],
            ],
        ],

        // --- TRANSAKSI STOK ---
        [
            'text'    => 'Transaksi Stok',
            'icon'    => 'fas fa-exchange-alt',
            'can'     => ['view-stock-mutations-menu', 'view-stock-adjustments-menu'], 
            'submenu' => [
                [
                    'text' => 'Mutasi Stok',
                    'route'  => 'admin.stock-mutations.index',
                    'icon' => 'fas fa-random',
                    'can'  => 'view-stock-mutations-menu',
                ],
                [
                    'text' => 'Penyesuaian Stok',
                    'route'  => 'admin.stock-adjustments.index',
                    'icon' => 'fas fa-sliders-h',
                    'can'  => 'view-stock-adjustments-menu',
                ],
            ],
        ],
        // --- SERVICE & PENJUALAN (DEALER ONLY) ---
        [
            'text' => 'Service & Penjualan',
            'icon' => 'fas fa-fw fa-store-alt',
            'can'  => ['view-sales', 'view-service'],
            'submenu' => [
                 [
                    'text' => 'Data Service',
                    'icon' => 'fas fa-fw fa-wrench',
                    'route' => 'admin.services.index',
                    'can' => 'view-service'
                 ],
                 [
                    'text' => 'Buat Penjualan',
                    'icon' => 'fas fa-fw fa-plus-circle',
                    'route' => 'admin.penjualans.create',
                    'can' => 'create-sale'
                 ],
                 [
                    'text' => 'Riwayat Penjualan',
                    'icon' => 'fas fa-fw fa-history',
                    'route' => 'admin.penjualans.index',
                    'can' => 'view-sales'
                 ],
            ],
        ],

        // ================== ANALISIS & MARKETING ==================
        [
            'header' => 'ANALISIS LAPORAN',
            'can' => ['view-reports'],
        ],
        [
            'text'    => 'Laporan',
            'icon'    => 'fas fa-fw fa-chart-pie',
            'can'     => 'view-reports',
            'submenu' => [
                [
                    'text' => 'Kartu Stok', 
                    'icon' => 'fas fa-fw fa-clipboard-list', 
                    'route' => 'admin.reports.stock-card',
                    'can'  => 'view-stock-card'
                ],
                [
                    'text' => 'Stok Per Lokasi', 
                    'icon' => 'fas fa-fw fa-boxes', 
                    'route' => 'admin.reports.stock-by-warehouse', 
                    'can'  => 'view-stock-by-warehouse'
                ],
                [
                    'text' => 'Laporan Stok Total', 
                    'icon' => 'fas fa-fw fa-archive', 
                    'route' => 'admin.reports.stock-report', 
                    'can'  => 'view-stock-report-global'
                ],
                [
                    'text' => 'Laporan Penjualan', 
                    'icon' => 'fas fa-fw fa-chart-line', 
                    'route' => 'admin.reports.sales-summary',
                    'can'  => 'view-sales-summary'
                ],
                [
                    'text' => 'Laporan Service', 
                    'icon' => 'fas fa-fw fa-wrench', 
                    'route' => 'admin.reports.service-summary',
                    'can'  => 'view-service-summary'
                ],
                [
                    'text' => 'Jurnal Pembelian', 
                    'icon' => 'fas fa-fw fa-book', 
                    'route' => 'admin.reports.purchase-journal',
                    'can'  => 'view-purchase-journal'
                ],
                [
                    'text' => 'Nilai Persediaan', 
                    'icon' => 'fas fa-fw fa-dollar-sign', 
                    'route' => 'admin.reports.inventory-value',
                    'can'  => 'view-inventory-value'
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Menu Filters
    |--------------------------------------------------------------------------
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
    */

    'plugins' => [
        'Datatables' => [
            'active' => true,
            'files' => [
                ['type' => 'js', 'asset' => true, 'location' => 'vendor/datatables/js/jquery.dataTables.min.js'],
                ['type' => 'js', 'asset' => true, 'location' => 'vendor/datatables/js/dataTables.bootstrap4.min.js'],
                ['type' => 'css', 'asset' => true, 'location' => 'vendor/datatables/css/dataTables.bootstrap4.min.css'],
            ],
        ],
        'Select2' => [
            'active' => true,
            'files' => [
                ['type' => 'js', 'asset' => true, 'location' => 'vendor/select2/js/select2.full.min.js'],
                ['type' => 'css', 'asset' => true, 'location' => 'vendor/select2/css/select2.min.css'],
                ['type' => 'css', 'asset' => true, 'location' => 'vendor/select2-bootstrap4-theme/select2-bootstrap4.min.css'],
            ],
        ],
        'BsCustomFileInput' => [
            'active' => true,
            'files' => [
                ['type' => 'js', 'asset' => true, 'location' => 'vendor/bs-custom-file-input/bs-custom-file-input.min.js'],
            ],
        ],
        'Chartjs' => [
            'active' => false,
            'files' => [
                ['type' => 'js', 'asset' => true, 'location' => 'vendor/chart.js/Chart.bundle.min.js'],
            ],
        ],
        'Sweetalert2' => [
            'active' => false,
            'files' => [
                ['type' => 'js', 'asset' => true, 'location' => 'vendor/sweetalert2/sweetalert2.min.js'],
                ['type' => 'css', 'asset' => true, 'location' => 'vendor/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css'],
            ],
        ],
        'Pace' => [
            'active' => false,
            'files' => [
                ['type' => 'css', 'asset' => true, 'location' => 'vendor/pace-progress/themes/blue/pace-theme-center-radar.min.css'],
                ['type' => 'js', 'asset' => true, 'location' => 'vendor/pace-progress/pace.min.js'],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | IFrame
    |--------------------------------------------------------------------------
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
    */

    'livewire' => false,
];
