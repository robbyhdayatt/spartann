<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Purchase Order: {{ $purchaseOrder->nomor_po }}</title>

    @php
        use App\Helpers\NumberHelper;

        // === 1. DATA HEADER (PEMBUAT PO / GUDANG KITA) ===
        $namaLokasi = $purchaseOrder->lokasi->nama_lokasi ?? 'Lautan Teduh Interniaga';
        $alamatLokasi = ($purchaseOrder->lokasi->alamat && $purchaseOrder->lokasi->alamat !== '\N') 
                        ? $purchaseOrder->lokasi->alamat 
                        : 'Alamat Tidak Tersedia';
        // Menggunakan NPWP Lokasi jika ada, jika tidak kosongkan atau pakai default
        $npwpLokasi   = $purchaseOrder->lokasi->npwp ? 'NPWP: ' . $purchaseOrder->lokasi->npwp : '';

        // === 2. DATA TUJUAN (SUPPLIER / GUDANG SUMBER) ===
        if($purchaseOrder->po_type == 'supplier_po') {
            $tujuanNama   = $purchaseOrder->supplier->nama_supplier ?? 'Supplier Umum';
            // $tujuanAlamat = $purchaseOrder->supplier->alamat ?? '-';
            // $tujuanTelp   = $purchaseOrder->supplier->no_telp ?? '-';
        } else {
            // Internal Transfer (Dealer Request)
            $tujuanNama   = $purchaseOrder->sumberLokasi->nama_lokasi ?? 'Gudang Pusat';
            // $tujuanAlamat = 'Internal Transfer';
            // $tujuanTelp   = '-';
        }

        // === 3. LOGIKA SCALING FONT (Sama seperti Penjualan) ===
        $details = $purchaseOrder->details;
        $totalDetailsCount = $details->count();
        
        $maxItemsPerPage = 28; // Batas item sebelum font mengecil
        $baseFontSize = 20;    // Ukuran font dasar
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
        /* === RESET & BASE === */
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

        /* Tabel non-border (Header/Info) */
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
        }
        .header-sub { font-size: 1.2em; font-weight: bold; }

        /* === INFO TABLE (Pelanggan/Supplier) === */
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

        /* === ITEMS TABLE (Barang) === */
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
        
        .terbilang-box {
            padding: 4px 5px;
            font-style: italic;
            font-weight: normal;
        }
        .signature-box { margin-top: 20px; }

        /* === INJECT DYNAMIC STYLES === */
        {!! $conditionalStyles !!}
    </style>
</head>
<body>

    <div class="invoice-box">

        {{-- JUDUL DOKUMEN --}}
        <div class="header-main">
            {{ $purchaseOrder->po_type == 'supplier_po' ? 'PURCHASE ORDER' : 'FORM ORDER' }}
        </div>

        {{-- HEADER: PENGIRIM (KITA/LOKASI) & INFO DOKUMEN --}}
        <table style="width:100%; margin-bottom:5px;">
            <tr>
                <td style="width: 60%; vertical-align: top;">
                    <div class="header-sub">{{ $namaLokasi }}</div>
                    <div>{{ $alamatLokasi }}</div>
                    <div>{{ $npwpLokasi }}</div>
                </td>
                <td style="width: 40%; text-align: right;">
                    <strong>Tanggal:</strong> {{ $purchaseOrder->tanggal_po->format('d/m/Y') }}<br>
                    <strong>No. PO:</strong> {{ $purchaseOrder->nomor_po }}<br>
                    <strong>Status:</strong> {{ strtoupper($purchaseOrder->status) }}
                </td>
            </tr>
        </table>

        {{-- INFO TABLE: TUJUAN (SUPPLIER / SUMBER) --}}
        <table class="info-table">
            <tr>
                <td style="width: 18%;"><strong>Tertuju</strong></td>
                <td style="width: 32%;">: {{ $tujuanNama }}</td>
                {{-- <td style="width: 18%;"><strong>Alamat</strong></td> --}}
                {{-- <td style="width: 32%;">: {{ Str::limit($tujuanAlamat, 40) }}</td> --}}
            </tr>
            <tr>
                {{-- <td><strong>Telepon</strong></td>
                <td>: {{ $tujuanTelp }}</td> --}}
                <td><strong>Pembuat</strong></td>
                <td>: {{ $purchaseOrder->createdBy->nama ?? 'Admin' }}</td>
            </tr>
            {{-- @if($purchaseOrder->catatan)
            <tr>
                <td><strong>Catatan</strong></td>
                <td colspan="3">: {{ $purchaseOrder->catatan }}</td>
            </tr>
            @endif --}}
        </table>

        {{-- ITEMS TABLE --}}
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 5%;">No.</th>
                    <th style="width: 20%;">Kode Part</th>
                    <th style="width: 40%;">Nama Barang</th>
                    
                    {{-- Kondisi Kolom: Jika Supplier PO ada Harga, Jika Internal Cuma Qty --}}
                    @if($purchaseOrder->po_type == 'supplier_po')
                        <th style="width: 15%;">Harga Satuan</th>
                        <th style="width: 5%;">Qty</th>
                        <th style="width: 15%;">Total</th>
                    @else
                        <th style="width: 15%;"></th> {{-- Spacer --}}
                        <th style="width: 10%;">Qty Request</th>
                        <th style="width: 10%;">Qty Approve</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @php $no = 1; @endphp
                @foreach($details as $item)
                    <tr>
                        <td class="text-center">{{ $no++ }}</td>
                        <td>{{ $item->barang->part_code ?? '-' }}</td>
                        <td>{{ $item->barang->part_name ?? '-' }}</td>

                        @if($purchaseOrder->po_type == 'supplier_po')
                            <td class="text-right">{{ number_format($item->harga_beli, 0, ',', '.') }}</td>
                            <td class="text-center">{{ $item->qty_pesan }}</td>
                            <td class="text-right">{{ number_format($item->subtotal, 0, ',', '.') }}</td>
                        @else
                            <td></td>
                            <td class="text-center">{{ $item->qty_pesan }}</td>
                            <td class="text-center font-bold">
                                {{-- Jika belum diapprove, tampilkan strip atau qty pesan --}}
                                {{ $purchaseOrder->status == 'PENDING_APPROVAL' ? '-' : $item->qty_pesan }}
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- FOOTER & TOTAL (Hanya Untuk Supplier PO) --}}
        <table style="margin-top: 10px;">
            <tr>
                {{-- Kiri: Terbilang / Note --}}
                <td style="width: 60%; vertical-align: bottom;">
                    @if($purchaseOrder->po_type == 'supplier_po')
                        <div><strong>Terbilang:</strong>
                            <div class="terbilang-box">
                                # {{ trim(NumberHelper::terbilang($purchaseOrder->total_amount ?? 0)) }} Rupiah #
                            </div>
                        </div>
                    @else
                        <div style="font-style: italic;">Dokumen ini sah digunakan sebagai bukti permintaan barang internal antar gudang/dealer.</div>
                    @endif
                </td>

                {{-- Kanan: Grand Total --}}
                <td style="width: 40%; vertical-align: top;">
                    @if($purchaseOrder->po_type == 'supplier_po')
                        <table style="width: 100%;">
                            @if(($purchaseOrder->ppn_amount ?? 0) > 0)
                            <tr>
                                <td>DPP:</td>
                                <td class="text-right">Rp {{ number_format($purchaseOrder->total_amount - $purchaseOrder->ppn_amount, 0, ',', '.') }}</td>
                            </tr>
                            <tr>
                                <td>PPN 11%:</td>
                                <td class="text-right">Rp {{ number_format($purchaseOrder->ppn_amount, 0, ',', '.') }}</td>
                            </tr>
                            @endif
                            <tr>
                                <td><strong>Grand Total:</strong></td>
                                <td class="text-right">
                                    <strong>Rp {{ number_format($purchaseOrder->total_amount ?? 0, 0, ',', '.') }}</strong>
                                </td>
                            </tr>
                        </table>
                    @endif
                </td>
            </tr>
        </table>

        {{-- SIGNATURE --}}
        <table class="signature-box" style="width: 100%;">
            <tr>
                <td class="text-center" style="width: 33%;">Dibuat Oleh,</td>
                <td class="text-center" style="width: 33%;">Disetujui Oleh,</td>
                <td class="text-center" style="width: 34%;">
                    {{ $purchaseOrder->po_type == 'supplier_po' ? 'Supplier / Sales,' : 'Penerima Barang,' }}
                </td>
            </tr>
            <tr>
                <td class="text-center" style="padding-top: {{$signaturePaddingTop}}px;">
                    ( {{ $purchaseOrder->createdBy->nama ?? 'Admin' }} )
                </td>
                <td class="text-center" style="padding-top: {{$signaturePaddingTop}}px;">
                    @if($purchaseOrder->po_type == 'supplier_po')
                        ( {{ $purchaseOrder->approvedByHead->nama ?? 'Kepala Gudang' }} )
                    @else
                        ( {{ $purchaseOrder->approvedBy->nama ?? 'Admin Gudang' }} )
                    @endif
                </td>
                <td class="text-center" style="padding-top: {{$signaturePaddingTop}}px;">(__________________)</td>
            </tr>
        </table>

    </div>
</body>
</html>