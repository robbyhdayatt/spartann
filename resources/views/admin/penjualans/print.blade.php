<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Faktur Penjualan: {{ $penjualan->nomor_faktur }}</title>

    {{-- ++ PERBAIKAN: Style Global untuk PDF ++ --}}
    <style>
        /* Mengatur halaman agar mentok ke sisi */
        @page {
            margin: 0;
            padding: 0;
        }

        body {
            margin: 0;
            padding: 0;
            /* Menggunakan font sans-serif umum yang tebal */
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-weight: bold;
            color: #000;
        }

        /* * Catatan untuk "Ukuran Menyesuaikan":
         * CSS/PDF tidak dapat secara otomatis "zoom-out" konten agar pas 1 lembar
         * seperti di Excel. Jika datanya terlalu banyak, data akan terpotong
         * atau pindah ke halaman kedua.
         *
         * Untuk memaksimalkan 1 lembar, pastikan:
         * 1. Ukuran font (di faktur_content) dijaga agar kecil (misal: 10px - 12px).
         * 2. Padding/Margin antar elemen (di faktur_content) diminimalkan.
        */
    </style>
    {{-- ++ AKHIR PERBAIKAN ++ --}}
</head>
<body>
    {{-- Include konten faktur --}}
    @include('admin.penjualans.faktur_content', ['penjualan' => $penjualan])
</body>
</html>
