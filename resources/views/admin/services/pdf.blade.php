@php
// Kalkulasi Total Service dan Sparepart
$totalService = $service->details->where('item_category', 'JASA')->sum(function ($item) {
    return $item->quantity * $item->price;
});
$totalSparepart = $service->details->whereIn('item_category', ['PART', 'OLI'])->sum(function ($item) {
    return $item->quantity * $item->price;
});
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Invoice {{ $service->invoice_no }}</title>
</head>
<body>
    {{-- Langsung include konten yang sudah diperbaiki --}}
    @include('admin.services.pdf_content', [
        'service' => $service,
        'totalService' => $totalService,
        'totalSparepart' => $totalSparepart
    ])
</body>
</html>