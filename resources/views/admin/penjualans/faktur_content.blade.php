@php
use App\Helpers\NumberHelper;

// === Data Lokasi/Dealer ===
$namaDealer = $penjualan->lokasi->nama_lokasi ?? 'Data Lokasi Tidak Ditemukan';
$alamatDealer = ($penjualan->lokasi->alamat && $penjualan->lokasi->alamat !== '\N') ? $penjualan->lokasi->alamat : '-';
$npwpDealer = 'NPWP No.: '; // Ganti dengan data NPWP jika ada

// === Pengaturan Dinamis agar Tetap 1 Lembar A4 ===
$totalDetailsCount = $penjualan->details->count();
$maxItemsPerPage = 28; // jumlah ideal item agar pas 1 halaman A4

// Nilai default
$baseFontSize = 18; // px
$lineHeight = 1.45;
$rowPadding = 5;
$signaturePaddingTop = 70;

// Jika terlalu banyak item, kecilkan font dan jarak baris otomatis
if ($totalDetailsCount > $maxItemsPerPage) {
    $scale = max(0.7, 1 - (($totalDetailsCount - $maxItemsPerPage) * 0.015));
    $baseFontSize = floor(18 * $scale);
    $lineHeight = max(1.1, 1.45 * $scale);
    $rowPadding = max(2, floor(5 * $scale));
    $signaturePaddingTop = max(25, floor(70 * $scale));
}

// CSS dinamis untuk menyesuaikan proporsional tampilan
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

{{-- ========================= CSS INVOICE (SAMA PERSIS DENGAN SERVIS) ========================= --}}
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

    .header-main {
        font-size: 1.3em;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .header-sub {
        font-size: 1.1em;
        font-weight: bold;
    }

    .invoice-box table.info-table {
        line-height: 1.25 !important;
        margin-top: 10px !important;
        margin-bottom: 10px !important;
    }

    .invoice-box table.info-table td {
        padding: 2px 5px !important;
        vertical-align: top;
        border: none !important;
    }

    .items-table th {
        text-align: center;
        border: none !important;
        border-bottom: 1px solid #000 !important;
        font-weight: bold;
    }

    .items-table td {
        vertical-align: top;
        border: none !important;
    }

    .items-table tbody {
        border-bottom: 1px solid #000 !important;
    }

    /* Kita tidak memakai grouping di penjualan, jadi ini bisa dihapus jika mau */
    .items-table tr.separator-row td {
        font-weight: bold;
        text-align: left;
        border-top: 1px solid #000 !important;
        background-color: transparent;
        font-size: 1.05em;
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

    /* Hapus @page A4 karena sudah diatur di PdfController */
    /* @media print { ... } */

    {!! $conditionalStyles !!}
</style>

{{-- ========================= BODY INVOICE (SUDAH DIUBAH) ========================= --}}
<div class="invoice-box">
    {{-- Header --}}
    <table>
        <tr>
            <td style="width: 60%; vertical-align: bottom;">
                {{-- ++ DIUBAH ++ --}}
                <div class="header-main">FAKTUR PENJUALAN</div>
            </td>
            <td style="width: 40%; text-align: right;">
                <div class="header-sub">{{ $namaDealer }}</div>
                <div>{{ $alamatDealer }}</div>
                <div>{{ $npwpDealer }}</div>
            </td>
        </tr>
    </table>

    {{-- Info Pelanggan & Transaksi (Layout diubah total) --}}
    <table class="info-table">
        <tr>
            <td style="width: 15%;"><strong>Tanggal</strong></td>
            <td style="width: 35%;">: {{ $penjualan->tanggal_jual ? $penjualan->tanggal_jual->format('d/m/Y') : '-' }}</td>
            <td style="width: 15%;"><strong>Nama</strong></td>
            <td style="width: 35%;">: {{ $penjualan->konsumen->nama_konsumen ?? '-' }}</td>
        </tr>
        <tr>
            <td><strong>No. Invoice</strong></td>
            <td>: {{ $penjualan->nomor_faktur ?? '-' }}</td>
            <td><strong>Alamat</strong></td>
            <td>: {{ $penjualan->konsumen->alamat ?? '-' }}</td>
        </tr>
        <tr>
            <td><strong>Sales</strong></td>
            <td>: {{ $penjualan->sales->nama ?? '-' }}</td>
            <td><strong>Mobile</strong></td>
            <td>: {{ $penjualan->konsumen->telepon ?? '-' }}</td>
        </tr>
    </table>

    {{-- Hapus Info Kendaraan (tidak ada di penjualan) --}}
    {{-- <table class="info-table"> ... </table> --}}

    {{-- Tabel Item (Logika diubah total) --}}
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%;">No.</th>
                <th style="width: 20%;">Nomor Item</th>
                <th style="width: 40%;">Nama Item</th>
                <th style="width: 15%;">Harga Satuan</th>
                <th style="width: 5%;">Qty</th>
                <th style="width: 15%;">Total</th>
            </tr>
        </thead>
        <tbody>
            @php $itemNumber = 1; @endphp
            @forelse ($penjualan->details as $detail)
                @php $totalItem = ($detail->qty_jual ?? 0) * ($detail->harga_jual ?? 0); @endphp
                <tr>
                    <td class="text-center">{{ $itemNumber++ }}</td>
                    <td>{{ $detail->barang->part_code ?? 'N/A' }}</td>
                    <td>{{ $detail->barang->part_name ?? 'N/A' }}</td>
                    <td class="text-right">{{ number_format($detail->harga_jual ?? 0, 0, ',', '.') }}</td>
                    <td class="text-center">{{ $detail->qty_jual ?? 0 }}</td>
                    <td class="text-right">{{ number_format($totalItem, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center">Tidak ada item detail.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- Footer (Disederhanakan) --}}
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
                    {{-- Hapus total service & part --}}
                    <tr><td><strong>Grand Total:</strong></td><td class="text-right"><strong>Rp {{ number_format($penjualan->total_harga ?? 0, 0, ',', '.') }}</strong></td></tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Tanda Tangan (Label diubah sedikit) --}}
    <table class="signature-box" style="width: 100%;">
        <tr>
            <td class="text-center" style="width: 33%;">Counter,</td>
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
