<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Faktur Penjualan: {{ $penjualan->nomor_faktur }}</title>
</head>
<body>
    {{-- Langsung include konten yang sudah memiliki style sendiri --}}
    @include('admin.penjualans.faktur_content', ['penjualan' => $penjualan])
</body>
</html>