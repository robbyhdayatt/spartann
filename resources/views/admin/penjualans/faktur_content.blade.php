@php
use App\Helpers\NumberHelper;

// === Dealer Info ===
$namaDealer = $penjualan->lokasi->nama_lokasi ?? 'Data Lokasi Tidak Ditemukan';
$namaDealer = str_replace('LTI', 'Lautan Teduh', $namaDealer);
$alamatDealer = ($penjualan->lokasi->alamat && $penjualan->lokasi->alamat !== '\N')
    ? $penjualan->lokasi->alamat
    : 'Alamat Tidak Tersedia';
$npwpDealer = 'NPWP No.: ' . ($penjualan->customer_npwp_no ?? '-');

// === Dynamic Font Scaling ===
$totalDetailsCount = $penjualan->details->count();
$maxItemsPerPage = 28;
$baseFontSize = 20; // diperbesar
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
    .invoice-box {
        font-size: {$baseFontSize}px;
    }
    .invoice-box table {
        line-height: {$lineHeight};
    }
    .items-table td, .items-table th {
        padding: {$rowPadding}px 4px;
    }
    .signature-box td[style*=\"padding-top\"] {
        padding-top: {$signaturePaddingTop}px !important;
    }
";
@endphp

{{-- ========================= CSS INVOICE ========================= --}}
<style>
html, body {
    margin: 0 !important;
    padding: 0 !important;
    width: 100%;
    height: 100%;
    box-sizing: border-box;
    font-family: 'Arial', 'Helvetica', sans-serif !important;
}
*, *:before, *:after { box-sizing: inherit; }

.invoice-box {
    font-family: 'Arial', 'Helvetica', sans-serif !important;
    color: #000;
    padding: 0 !important;
    width: 100%;
    height: 100%;
    font-weight: normal;
    letter-spacing: 0.2px;
}

.invoice-box table {
    width: 100%;
    text-align: left;
    border-collapse: collapse;
}

.invoice-box table:not(.info-table):not(.items-table) td,
.invoice-box table:not(.info-table):not(.items-table) th {
    padding: 3px 5px;
    vertical-align: top;
    border: none !important;
}

/* === HEADER === */
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

.header-sub {
    font-size: 1.2em;
    font-weight: bold;
}

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

/* === ITEM TABLE === */
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

.text-right { text-align: right; }
.text-center { text-align: center; }
.font-bold { font-weight: bold; }

.terbilang-box {
    padding: 4px 5px;
    font-style: italic;
    font-weight: normal;
}

.signature-box {
    margin-top: 20px;
}

{!! $conditionalStyles !!}
</style>

{{-- ========================= BODY INVOICE ========================= --}}
<div class="invoice-box">

    {{-- Header --}}
    <div class="header-main">FAKTUR PENJUALAN</div>

    <table style="width:100%; margin-bottom:5px;">
        <tr>
            <td style="width: 60%; vertical-align: top;">
                <div class="header-sub">{{ $namaDealer }}</div>
                <div>{{ $alamatDealer }}</div>
                <div>{{ $npwpDealer }}</div>
            </td>
            <td style="width: 40%; text-align: right;">
                <strong>Tanggal:</strong>
                {{ $penjualan->tanggal_jual ? \Carbon\Carbon::parse($penjualan->tanggal_jual)->format('d/m/Y') : '-' }}<br>
                <strong>No. Invoice:</strong> {{ $penjualan->nomor_faktur ?? '-' }}
            </td>
        </tr>
    </table>

    {{-- Info Pelanggan --}}
    <table class="info-table" style="margin-top: 5px;">
        <tr>
            <td style="width: 18%;"><strong>Nama</strong></td>
            <td style="width: 32%;">: {{ $penjualan->konsumen->nama_konsumen ?? '-' }}</td>
            <td style="width: 18%;"><strong>Alamat</strong></td>
            <td style="width: 32%;">: {{ $penjualan->konsumen->alamat ?? '-' }}</td>
        </tr>
        <tr>
            <td><strong>Telepon</strong></td>
            <td>: {{ $penjualan->konsumen->telepon ?? '-' }}</td>
            <td><strong>Sales</strong></td>
            <td>: {{ $penjualan->sales->nama ?? '-' }}</td>
        </tr>
    </table>

    {{-- Item Table --}}
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%;">No.</th>
                <th style="width: 20%;">Kode Item</th>
                <th style="width: 40%;">Nama Item</th>
                <th style="width: 15%;">Harga Satuan</th>
                <th style="width: 5%;">Qty</th>
                <th style="width: 15%;">Total</th>
            </tr>
        </thead>
        <tbody>
            @php $itemNumber = 1; @endphp
            @forelse ($penjualan->details->sortByDesc(function($d){ return $d->harga_jual; }) as $detail)
                @php $totalItem = ($detail->qty_jual ?? 0) * ($detail->harga_jual ?? 0); @endphp
                <tr>
                    <td class="text-center">{{ $itemNumber++ }}</td>
                    <td>{{ $detail->barang->part_code ?? '-' }}</td>
                    <td>{{ $detail->barang->part_name ?? '-' }}</td>
                    <td class="text-right">{{ number_format($detail->harga_jual ?? 0, 0, ',', '.') }}</td>
                    <td class="text-center">{{ $detail->qty_jual ?? 0 }}</td>
                    <td class="text-right">{{ number_format($totalItem, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center">Tidak ada item detail.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- Footer --}}
    <table style="margin-top: 10px;">
        <tr>
            <td style="width: 60%; vertical-align: bottom;">
                <div><strong>Harga sudah termasuk PPN 11%</strong></div>
                <div><strong>Terbilang:</strong>
                    <div class="terbilang-box">
                        # {{ trim(NumberHelper::terbilang($penjualan->total_harga ?? 0)) }} Rupiah #
                    </div>
                </div>
            </td>
            <td style="width: 40%; vertical-align: top;">
                <table style="width: 100%;">
                    <tr>
                        <td><strong>Grand Total:</strong></td>
                        <td class="text-right">
                            <strong>Rp {{ number_format($penjualan->total_harga ?? 0, 0, ',', '.') }}</strong>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Signature --}}
    <table class="signature-box" style="width: 100%;">
        <tr>
            <td class="text-center" style="width: 33%;">Sales,</td>
            <td class="text-center" style="width: 33%;">Konsumen,</td>
            <td class="text-center" style="width: 34%;">Kasir,</td>
        </tr>
        <tr>
            <td class="text-center" style="padding-top: {{$signaturePaddingTop}};">(__________________)</td>
            <td class="text-center" style="padding-top: {{$signaturePaddingTop}};">(__________________)</td>
            <td class="text-center" style="padding-top: {{$signaturePaddingTop}};">(__________________)</td>
        </tr>
    </table>

</div>
