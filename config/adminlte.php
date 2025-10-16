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

    'usermenu_enabled' => true, // Diaktifkan untuk menampilkan nama user & logout
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
    'layout_fixed_sidebar' => true, // Dibuat fixed agar tidak ikut scroll
    'layout_fixed_navbar' => true, // Dibuat fixed agar tidak ikut scroll
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
    'classes_sidebar' => 'sidebar-light-primary elevation-4', // Warna disesuaikan
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

    /*
    |--------------------------------------------------------------------------
    | Menu Items
    |--------------------------------------------------------------------------
    */

    'menu' => [
        // Navbar items:
        ['type' => 'fullscreen-widget', 'topnav_right' => true],

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

        // ================== MENU PUSAT & GLOBAL ==================
        [
            'header' => 'MASTER & PENGATURAN',
            'can' => 'view-master-data', 
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
                ['text' => 'Brand', 'icon' => 'far fa-fw fa-copyright', 'route' => 'admin.brands.index'],
                ['text' => 'Kategori', 'icon' => 'far fa-fw fa-folder-open', 'route' => 'admin.categories.index'],
                ['text' => 'Supplier', 'icon' => 'fas fa-fw fa-people-carry', 'route' => 'admin.suppliers.index'],
                ['text' => 'Part', 'icon' => 'fas fa-fw fa-cogs', 'route' => 'admin.parts.index'],
                ['text' => 'Konsumen', 'icon' => 'fas fa-fw fa-user-friends', 'route' => 'admin.konsumens.index'],
            ],
        ],
        [
            'text' => 'Pengguna',
            'route'  => 'admin.users.index',
            'icon' => 'fas fa-fw fa-users-cog',
            'can' => 'manage-users',
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
                ['text' => 'Purchase Order (PO)', 'icon' => 'fas fa-fw fa-shopping-cart', 'route' => 'admin.purchase-orders.index', 'can' => 'access-po-module'],
                ['text' => 'Penerimaan Barang', 'icon' => 'fas fa-fw fa-box-open', 'route' => 'admin.receivings.index', 'can' => 'perform-warehouse-ops'],
                ['text' => 'Quality Control (QC)', 'icon' => 'fas fa-fw fa-check-double', 'route' => 'admin.qc.index', 'can' => 'perform-warehouse-ops'],
                ['text' => 'Penyimpanan (Putaway)', 'icon' => 'fas fa-fw fa-dolly', 'route' => 'admin.putaway.index', 'can' => 'perform-warehouse-ops'],      
                ['text' => 'Adjusment Stok', 'icon' => 'fas fa-fw fa-sliders-h', 'route' => 'admin.stock-adjustments.index'],
                ['text' => 'Mutasi Stok', 'icon' => 'fas fa-fw fa-people-arrows', 'route' => 'admin.stock-mutations.index'],
                ['text' => 'Penerimaan Mutasi', 'icon' => 'fas fa-fw fa-truck-loading', 'route' => 'admin.mutation-receiving.index', 'can' => 'view-mutation-receiving'],
                ['text' => 'Stok Karantina', 'icon' => 'fas fa-fw fa-biohazard', 'route' => 'admin.quarantine-stock.index', 'can' => 'view-quarantine-stock'],
                ['text' => 'Retur Pembelian', 'icon' => 'fas fa-fw fa-undo-alt', 'route' => 'admin.purchase-returns.index', 'can' => 'manage-purchase-returns'],
            ],   
        ],
        [
            'text' => 'Transaksi Dealer',
            'icon' => 'fas fa-fw fa-store-alt',
            'can'  => 'is-dealer-staff',
            'submenu' => [
                ['text' => 'Penerimaan Mutasi', 'icon' => 'fas fa-fw fa-truck-loading', 'route' => 'admin.mutation-receiving.index', 'can' => 'view-mutation-receiving'],
                ['text' => 'Adjusment Stok', 'icon' => 'fas fa-fw fa-sliders-h', 'route' => 'admin.stock-adjustments.index', 'can' => 'create-stock-adjustment'],
                ['text' => 'Mutasi Stok', 'icon' => 'fas fa-fw fa-people-arrows', 'route' => 'admin.stock-mutations.index', 'can' => 'create-stock-adjustment'],
                ['text' => 'Stok Karantina', 'icon' => 'fas fa-fw fa-biohazard', 'route' => 'admin.quarantine-stock.index', 'can' => 'manage-quarantine-stock'],
                ['text' => 'Data Service', 'icon' => 'fas fa-fw fa-wrench', 'route' => 'admin.services.index', 'can' => 'view-service'],
            ],
        ],
        [
            'text'    => 'Penjualan',
            'icon'    => 'fas fa-fw fa-cash-register',
            'can'     => 'view-sales',
            'submenu' => [
                ['text' => 'Buat Transaksi', 'icon' => 'fas fa-fw fa-plus-circle', 'route' => 'admin.penjualans.create', 'can' => 'create-sale'],
                ['text' => 'Riwayat Penjualan', 'icon' => 'fas fa-fw fa-history', 'route' => 'admin.penjualans.index'],
                ['text' => 'Retur Penjualan', 'icon' => 'fas fa-fw fa-exchange-alt', 'route' => 'admin.sales-returns.index', 'can' => 'create-sale'],
            ],
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
                ['text' => 'Manajemen Campaign', 'icon' => 'fas fa-fw fa-tags', 'route' => 'admin.campaigns.index'],
                ['text' => 'Kategori Diskon', 'icon' => 'fas fa-fw fa-percent', 'route' => 'admin.customer-discount-categories.index'],
                [
                    'text'    => 'Insentif Sales',
                    'icon'    => 'fas fa-fw fa-gift',
                    'submenu' => [
                        ['text' => 'Set Target', 'icon' => 'fas fa-fw fa-bullseye', 'route' => 'admin.incentives.targets'],
                        ['text' => 'Laporan Insentif', 'icon' => 'fas fa-fw fa-file-invoice-dollar', 'route' => 'admin.incentives.report'],
                    ],
                ],
            ],
        ],
        [
            'text'    => 'Laporan',
            'icon'    => 'fas fa-fw fa-chart-pie',
            'can'     => 'view-reports',
            'submenu' => [
                ['text' => 'Kartu Stok', 'icon' => 'fas fa-fw fa-clipboard-list', 'route' => 'admin.reports.stock-card'],
                ['text' => 'Stok Per Lokasi', 'icon' => 'fas fa-fw fa-boxes', 'route' => 'admin.reports.stock-by-warehouse'],
                ['text' => 'Laporan Stok Total', 'icon' => 'fas fa-fw fa-archive', 'route' => 'admin.reports.stock-report'],
                ['text' => 'Jurnal Penjualan', 'icon' => 'fas fa-fw fa-book-open', 'route' => 'admin.reports.sales-journal'],
                ['text' => 'Jurnal Pembelian', 'icon' => 'fas fa-fw fa-book', 'route' => 'admin.reports.purchase-journal'],
                ['text' => 'Nilai Persediaan', 'icon' => 'fas fa-fw fa-dollar-sign', 'route' => 'admin.reports.inventory-value'],
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
            'active' => false,
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