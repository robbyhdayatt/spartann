<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>PO - {{ $purchaseOrder->nomor_po }}</title>
</head>
<body>
    {{-- Langsung include konten yang sudah memiliki style sendiri --}}
    @include('admin.purchase_orders.po_content', ['purchaseOrder' => $purchaseOrder])
</body>
</html>