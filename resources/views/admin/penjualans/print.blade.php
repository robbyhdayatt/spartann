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
            margin: 0 !important;
            padding: 0 !important;
            width: 100%;
            height: 100%;
            box-sizing: border-box;
            font-family: 'Arial', 'Helvetica', sans-serif !important;
        }
        *, *:before, *:after { box-sizing: inherit; }

    </style>
</head>
<body>
    {{-- Include konten faktur --}}
    @include('admin.penjualans.faktur_content', ['penjualan' => $penjualan])
</body>
</html>
