<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Retur Pembelian: {{ $purchaseReturn->nomor_retur }}</title>

    @php
        // === DATA HEADER ===
        $lokasiGudang = $purchaseReturn->receiving->lokasi;
        $namaLokasi   = $lokasiGudang->nama_lokasi ?? 'Lautan Teduh Interniaga';
        $namaLokasi   = str_replace('LTI', 'Lautan Teduh', $namaLokasi); 
        $alamatLokasi = ($lokasiGudang->alamat && $lokasiGudang->alamat !== '\N') ? $lokasiGudang->alamat : 'Alamat Tidak Tersedia';
        $npwpLokasi   = $lokasiGudang->npwp ? 'NPWP No.: ' . $lokasiGudang->npwp : '';

        // Data Supplier
        $supplierNama   = $purchaseReturn->supplier->nama_supplier ?? 'Internal / Umum';
        $supplierAlamat = $purchaseReturn->supplier->alamat ?? '-';

        // === LOGIKA SCALING FONT ===
        $details = $purchaseReturn->details;
        $totalDetailsCount = $details->count();
        
        $maxItemsPerPage = 28; 
        $baseFontSize = 20;    
        $lineHeight = 1.4;
        $rowPadding = 6;
        $signaturePaddingTop = 70;

        if ($totalDetailsCount > $maxItemsPerPage) {
            $scale = max(0.7, 1 - (($totalDetailsCount - $maxItemsPerPage) * 0.015));
            $baseFontSize = floor(20 * $scale);
            $lineHeight = max(1.1, 1.4 * $scale);
            $rowPadding = max(2, floor(6 * $scale));
            $signaturePaddingTop = max(25, floor(70 * $scale));
        }

        $conditionalStyles = "
            .invoice-box { font-size: {$baseFontSize}px; }
            .invoice-box table { line-height: {$lineHeight}; }
            .items-table td, .items-table th { padding: {$rowPadding}px 4px; }
            .signature-box td[style*=\"padding-top\"] { padding-top: {$signaturePaddingTop}px !important; }
        ";
    @endphp

    <style>
        /* === RESET MARGIN (PENTING AGAR FIT DI KERTAS CUSTOM) === */
        @page { margin: 0; padding: 0; }
        html, body {
            margin: 0 !important;
            padding: 0 !important;
            width: 100%;
            height: 100%;
            box-sizing: border-box;
            font-family: 'Arial', 'Helvetica', sans-serif !important;
        }
        *, *:before, *:after { box-sizing: inherit; }

        /* === CONTAINER === */
        .invoice-box {
            font-family: 'Arial', 'Helvetica', sans-serif !important;
            color: #000;
            padding: 0 !important;
            width: 100%;
            height: 100%;
            font-weight: normal;
            letter-spacing: 0.2px;
        }

        /* === TABLES === */
        .invoice-box table {
            width: 100%;
            text-align: left;
            border-collapse: collapse;
        }

        /* Tabel Header & Info (Tanpa Border) */
        .invoice-box table:not(.info-table):not(.items-table) td,
        .invoice-box table:not(.info-table):not(.items-table) th {
            padding: 3px 5px;
            vertical-align: top;
            border: none !important;
        }

        /* === HEADER UTAMA === */
        .header-main {
            font-size: 1.6em;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 4px;
            margin-bottom: 5px;
            /* color: #d9534f; Hapus warna jika ingin hitam polos seperti faktur */ 
        }
        .header-sub { font-size: 1.2em; font-weight: bold; }

        /* === INFO TABLE === */
        .invoice-box table.info-table {
            line-height: 1.15 !important;
            margin-top: 5px !important;
            margin-bottom: 5px !important;
        }
        .invoice-box table.info-table td {
            padding: 1px 5px !important;
            vertical-align: top;
            border: none !important;
            font-size: 0.95em;
        }

        /* === ITEMS TABLE === */
        .items-table th {
            text-align: center;
            border: none !important;
            border-bottom: 1px solid #000 !important;
            font-weight: bold;
            font-size: 1em;
        }
        .items-table td {
            vertical-align: top;
            border: none !important;
        }
        .items-table tbody {
            border-bottom: 1px solid #000 !important;
        }

        /* === UTILS === */
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        
        .signature-box { margin-top: 20px; }

        /* === INJECT DYNAMIC STYLES === */
        {!! $conditionalStyles !!}
    </style>
</head>
<body>

    <div class="invoice-box">

        {{-- JUDUL DOKUMEN --}}
        <div class="header-main">BUKTI RETUR PEMBELIAN</div>

        {{-- HEADER: PENGIRIM (KIRI) & INFO DOKUMEN (KANAN) --}}
        <table style="width:100%; margin-bottom:5px;">
            <tr>
                <td style="width: 60%; vertical-align: top;">
                    <div class="header-sub">{{ $namaLokasi }}</div>
                    <div>{{ $alamatLokasi }}</div>
                    <div>{{ $npwpLokasi }}</div>
                </td>
                <td style="width: 40%; text-align: right;">
                    <strong>Tanggal:</strong> {{ $purchaseReturn->tanggal_retur->format('d/m/Y') }}<br>
                    <strong>No. Retur:</strong> {{ $purchaseReturn->nomor_retur }}<br>
                    <strong>Ref. Receive:</strong> {{ $purchaseReturn->receiving->nomor_penerimaan ?? '-' }}
                </td>
            </tr>
        </table>

        {{-- INFO TABLE: TUJUAN (SUPPLIER) --}}
        <table class="info-table">
            <tr>
                <td style="width: 18%;"><strong>Kepada</strong></td>
                <td style="width: 32%;">: {{ $supplierNama }}</td>
                <td style="width: 18%;"><strong>Alamat</strong></td>
                <td style="width: 32%;">: {{ \Illuminate\Support\Str::limit($supplierAlamat, 40) }}</td>
            </tr>
            <tr>
                <td><strong>Operator</strong></td>
                <td>: {{ $purchaseReturn->createdBy->nama ?? 'Admin' }}</td>
                <td><strong>Keterangan</strong></td>
                <td>: {{ $purchaseReturn->catatan ?? '-' }}</td>
            </tr>
        </table>

        {{-- ITEMS TABLE --}}
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 5%;">No.</th>
                    <th style="width: 20%;">Kode Item</th>
                    <th style="width: 40%;">Nama Item</th>
                    <th style="width: 25%;">Alasan</th>
                    <th style="width: 10%;">Qty</th>
                </tr>
            </thead>
            <tbody>
                @php $no = 1; @endphp
                @foreach($details as $item)
                    <tr>
                        <td class="text-center">{{ $no++ }}</td>
                        <td>{{ $item->barang->part_code ?? '-' }}</td>
                        <td>{{ $item->barang->part_name ?? '-' }}</td>
                        <td>{{ $item->alasan ?? '-' }}</td>
                        <td class="text-center font-bold">{{ $item->qty_retur }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- FOOTER / SIGNATURE --}}
        <table class="signature-box" style="width: 100%;">
            <tr>
                <td class="text-center" style="width: 33%;">Dibuat Oleh,</td>
                <td class="text-center" style="width: 33%;">Disetujui Oleh,</td>
                <td class="text-center" style="width: 34%;">Diterima Supplier,</td>
            </tr>
            <tr>
                <td class="text-center" style="padding-top: {{$signaturePaddingTop}}px;">
                    ( {{ $purchaseReturn->createdBy->nama ?? 'Admin' }} )
                </td>
                <td class="text-center" style="padding-top: {{$signaturePaddingTop}}px;">
                    ( Kepala Gudang )
                </td>
                <td class="text-center" style="padding-top: {{$signaturePaddingTop}}px;">(__________________)</td>
            </tr>
        </table>

    </div>
</body>
</html>