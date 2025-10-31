<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Faktur Penjualan: {{ $penjualan->nomor_faktur }}</title>

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

    </style>
</head>
<body>
    {{-- Include konten faktur --}}
    @include('admin.penjualans.faktur_content', ['penjualan' => $penjualan])
</body>
</html>
