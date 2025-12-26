<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Purchase Order - {{ $purchaseOrder->nomor_po }}</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; margin: 0; padding: 0; }
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .header-table td { vertical-align: top; }
        .title { font-size: 24px; font-weight: bold; color: #333; margin-bottom: 5px; }
        .company-name { font-size: 16px; font-weight: bold; margin-bottom: 10px; }
        .info-label { font-weight: bold; width: 100px; display: inline-block; }
        
        .items-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .items-table th, .items-table td { border: 1px solid #000; padding: 6px; text-align: left; }
        .items-table th { background-color: #f2f2f2; text-align: center; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        
        .footer-section { margin-top: 30px; page-break-inside: avoid; }
        .signature-box { width: 100%; margin-top: 50px; }
        .signature-col { width: 33%; text-align: center; vertical-align: top; }
        .signature-line { margin-top: 60px; border-bottom: 1px solid #000; width: 80%; margin-left: auto; margin-right: auto; }
    </style>
</head>
<body>

    {{-- HEADER --}}
    <table class="header-table">
        <tr>
            <td style="width: 60%">
                <div class="title">PURCHASE ORDER</div>
                <div class="company-name">PT. LAUTAN TEDUH INTERNIAGA</div>
                <div>Jl. Ikan Tenggiri No. 23, Bandar Lampung</div>
                <div>Telp: (0721) 123456 | Email: purchasing@lautanteduh.co.id</div>
            </td>
            <td style="width: 40%; text-align: right;">
                <table style="width: 100%; font-size: 12px;">
                    <tr><td class="text-right"><strong>Nomor PO:</strong></td><td class="text-right">{{ $purchaseOrder->nomor_po }}</td></tr>
                    <tr><td class="text-right"><strong>Tanggal:</strong></td><td class="text-right">{{ $purchaseOrder->tanggal_po->format('d F Y') }}</td></tr>
                    <tr><td class="text-right"><strong>Status:</strong></td><td class="text-right">{{ strtoupper($purchaseOrder->status) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <hr>

    {{-- INFO SUPPLIER / TUJUAN --}}
    <table class="header-table" style="margin-top: 10px;">
        <tr>
            <td style="width: 50%">
                <strong>KEPADA (SUPPLIER/SUMBER):</strong><br>
                @if($purchaseOrder->po_type == 'supplier_po')
                    {{ $purchaseOrder->supplier->nama_supplier }}<br>
                    {{ $purchaseOrder->supplier->alamat ?? '-' }}<br>
                    Telp: {{ $purchaseOrder->supplier->no_telp ?? '-' }}
                @else
                    {{ $purchaseOrder->sumberLokasi->nama_lokasi ?? 'Gudang Pusat' }} (Internal)
                @endif
            </td>
            <td style="width: 50%">
                <strong>DIKIRIM KE (TUJUAN):</strong><br>
                {{ $purchaseOrder->lokasi->nama_lokasi }}<br>
                {{ $purchaseOrder->lokasi->alamat ?? '-' }}
            </td>
        </tr>
    </table>

    {{-- ITEMS TABLE --}}
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%">No</th>
                <th style="width: 15%">Kode Part</th>
                <th>Nama Barang</th>
                <th style="width: 10%">Qty</th>
                @if($purchaseOrder->po_type == 'supplier_po')
                <th style="width: 15%">Harga Satuan</th>
                <th style="width: 15%">Total</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach($purchaseOrder->details as $index => $item)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $item->barang->part_code }}</td>
                <td>{{ $item->barang->part_name }}</td>
                <td class="text-center">{{ $item->qty_pesan }}</td>
                @if($purchaseOrder->po_type == 'supplier_po')
                <td class="text-right">Rp {{ number_format($item->harga_beli, 0, ',', '.') }}</td>
                <td class="text-right">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</td>
                @endif
            </tr>
            @endforeach
        </tbody>
        @if($purchaseOrder->po_type == 'supplier_po')
        <tfoot>
            <tr>
                <td colspan="5" class="text-right"><strong>GRAND TOTAL</strong></td>
                <td class="text-right"><strong>Rp {{ number_format($purchaseOrder->total_amount, 0, ',', '.') }}</strong></td>
            </tr>
        </tfoot>
        @endif
    </table>

    {{-- FOOTER / SIGNATURE --}}
    <div class="footer-section">
        <strong>Catatan:</strong><br>
        {{ $purchaseOrder->catatan ?: '-' }}
        
        <table class="signature-box">
            <tr>
                <td class="signature-col">
                    Dibuat Oleh,<br><br><br><br>
                    <div class="signature-line"></div>
                    {{ $purchaseOrder->createdBy->nama ?? 'Admin' }}
                </td>
                <td class="signature-col">
                    Disetujui Oleh,<br><br><br><br>
                    <div class="signature-line"></div>
                    @if($purchaseOrder->po_type == 'supplier_po')
                        {{ $purchaseOrder->approvedByHead->nama ?? 'Ka. Gudang' }}
                    @else
                        {{ $purchaseOrder->approvedBy->nama ?? 'Admin Gudang' }}
                    @endif
                </td>
                <td class="signature-col">
                    Diterima Oleh,<br><br><br><br>
                    <div class="signature-line"></div>
                    ( Supplier / Logistik )
                </td>
            </tr>
        </table>
    </div>

</body>
</html>