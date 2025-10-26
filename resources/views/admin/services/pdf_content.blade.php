@php
use App\Helpers\NumberHelper;
$detailsGrouped = $service->details->groupBy('service_category_code');

// ... (Variabel Dealer Dinamis tetap sama) ...
$namaDealer = 'Data Lokasi Tidak Ditemukan';
$alamatDealer = 'Alamat Tidak Tersedia';
$npwpDealer = 'NPWP Tidak Tersedia';
if ($service->lokasi) {
    $namaDealer = $service->lokasi->nama_gudang ?? $namaDealer;
    $alamatDealer = $service->lokasi->alamat && $service->lokasi->alamat !== '\N' ? $service->lokasi->alamat : '-';
    // $npwpDealer = $service->lokasi->npwp ?? $npwpDealer;
}
$npwpDealer = 'NPWP No.: ';

// ++ LOGIKA UNTUK SINGLE PAGE & BOLD FONT (PENYESUAIAN) ++
$totalDetailsCount = $service->details->count();
// Naikkan threshold sedikit, misal kembali ke 15 atau 16
$itemThreshold = 16; // <<<< NAIKKAN SEDIKIT (misal: 15 atau 16)

// Ukuran font dasar 14px sepertinya sudah cukup, mari pertahankan
$baseFontSize = '14px'; // Tetap 14px
$itemRowPadding = '2px 4px'; // Padding Y X
$lineHeight = '1.25';
$signaturePaddingTop = '40px'; // Jarak TTD default

if ($totalDetailsCount > $itemThreshold) {
    // Kurangi agresivitas faktor pengecilan, mungkin 0.02 atau 0.018?
    // Juga, set batas minimum reduction factor agar tidak terlalu kecil (misal 0.75 -> ~10.5px)
    $reductionFactor = max(0.75, 1 - (($totalDetailsCount - $itemThreshold) * 0.018)); // <<<< Ubah 0.025 jadi 0.018
    $baseFontSize = floor(14 * $reductionFactor) . 'px'; // Hitung dari 14px
    // Tetap gunakan padding vertikal kecil
    $itemRowPadding = '1px 3px'; // <<<< Padding Y sedikit lebih besar dari 0px
    // Line height sedikit lebih besar saat dikecilkan
    $lineHeight = '1.15'; // <<<< Naikkan sedikit dari 1.1
    $signaturePaddingTop = '25px'; // Jarak TTD saat item banyak
}

// Persiapan style kondisional (sudah termasuk $signaturePaddingTop)
$conditionalStyles = '';
if ($totalDetailsCount > $itemThreshold) {
    $conditionalStyles = "
        .signature-box { margin-top: 10px !important; }
        .signature-box td { font-size: 0.95em !important; }
        .signature-box td[style*=\"padding-top\"] { padding-top: {$signaturePaddingTop} !important; }
    ";
} else {
     $conditionalStyles = "
        /* Tidak perlu !important jika ini style default */
        .signature-box td[style*=\"padding-top\"] { padding-top: {$signaturePaddingTop}; }
    ";
}
// ++ END LOGIKA ++

@endphp

{{-- CSS Kustom untuk Faktur --}}
<style>
    html, body { margin: 0 !important; padding: 0 !important; width: 100%; height: 100%; box-sizing: border-box; }
    *, *:before, *:after { box-sizing: inherit; }

    .invoice-box {
        font-family: 'DejaVu Sans Mono', monospace; /* Tetap pakai DejaVu Sans Mono */
        font-size: {{ $baseFontSize }};
        color: #000;
        padding: 0 !important;
        width: 100%;
        height: 100%;
        font-weight: bold;
    }
    .invoice-box table {
        width: 100%;
        line-height: {{ $lineHeight }};
        text-align: left;
        border-collapse: collapse;
    }
    /* ... (CSS lainnya sebagian besar tetap sama) ... */
     .invoice-box table:not(.info-table):not(.items-table) td,
    .invoice-box table:not(.info-table):not(.items-table) th {
        padding: 2px 4px;
        vertical-align: top;
        border: none !important;
    }
    .header-main { font-size: 1.3em; font-weight: bold; }
    .header-sub { font-size: 1.1em; font-weight: bold; }

    .invoice-box table.info-table { line-height: 1.1 !important; margin-top: 5px !important; margin-bottom: 5px !important; }
    .invoice-box table.info-table td { padding-top: 1px !important; padding-bottom: 1px !important; padding-left: 5px; padding-right: 5px; vertical-align: top; border: none !important; }
    /* .invoice-box table.info-table td:not(:first-child) { font-weight: normal; } */

    .items-table th {
        padding: {{ $itemRowPadding }}; /* Padding dinamis */
        vertical-align: top; text-align: center; border: none !important; border-bottom: 1px solid #000 !important; padding-bottom: 4px; font-weight: bold;
    }
    .items-table td {
         padding: {{ $itemRowPadding }}; /* Padding dinamis */
         vertical-align: top;
         border: none !important;
    }
    .items-table tbody { border-bottom: 1px solid #000 !important; }
    .items-table tr.separator-row td {
         font-weight: bold; text-align: left; padding: 4px 4px 2px 4px;
         border-top: 1px solid #000 !important; background-color: transparent; font-size: 1.05em;
    }
    /* Optional: non-bold value item */
    /* .items-table tbody td { font-weight: normal; } */
    /* .items-table tbody td.text-right,
       .items-table tbody td.text-center { font-weight: normal; } */

    hr, .dotted-hr { display: none; }
    .text-right { text-align: right; }
    .text-center { text-align: center; }
    .font-bold { font-weight: bold; }
    .terbilang-box { padding: 3px 5px; font-style: italic; font-weight: normal; }

    .signature-box { margin-top: 15px; } /* Margin atas default */

    /* Terapkan style kondisional */
    {!! $conditionalStyles !!}

</style>

{{-- Konten HTML (Header, Info, Tabel Item, Footer, Tanda Tangan) TETAP SAMA seperti sebelumnya --}}
<div class="invoice-box">
    {{-- Header --}}
    <table>
        <tr>
            <td style="width: 60%; vertical-align: bottom;"><div class="header-main">FAKTUR SERVICE</div></td>
            <td style="width: 40%; text-align: right;">
                <div class="header-sub">{{ $namaDealer }}</div>
                <div>{{ $alamatDealer }}</div>
                <div>{{ $npwpDealer }}</div>
            </td>
        </tr>
    </table>

    {{-- Info Pelanggan & Kendaraan --}}
    <table class="info-table" style="width: 100%; margin-top: 10px;">
         <tr>
             <td style="width: 15%;"><strong>Tanggal</strong></td>
             <td style="width: 18%;">: {{ $service->reg_date ? \Carbon\Carbon::parse($service->reg_date)->format('d/m/Y') : '-' }}</td>
             <td style="width: 15%;"><strong>Nama</strong></td>
             <td style="width: 18%;">: {{ $service->customer_name ?? '-' }}</td>
             <td style="width: 15%;"><strong>No. Rangka</strong></td>
             <td style="width: 19%;">: {{ $service->mc_frame_no ?? '-' }}</td>
         </tr>
         <tr>
             <td><strong>No. Invoice</strong></td>
             <td>: {{ $service->invoice_no ?? '-' }}</td>
             <td><strong>Alamat</strong></td>
             <td>: -</td>
             <td><strong>No. Mesin</strong></td>
             <td>: -</td>
         </tr>
         <tr>
             <td><strong>Order No.</strong></td>
             <td>: {{ $service->work_order_no ?? '-' }}</td>
             <td><strong>Mobile</strong></td>
             <td>: {{ $service->customer_phone ?? '-' }}</td>
             <td><strong>Tipe Motor</strong></td>
             <td>: {{ $service->mc_model_name ?? '-' }}</td>
         </tr>
    </table>
     <table class="info-table" style="width: 100%;">
         <tr>
             <td style="width: 15%;"><strong>No. Polisi</strong></td>
             <td style="width: 18%;">: {{ $service->plate_no ?? '-' }}</td>
             <td style="width: 15%;"><strong>Technician</strong></td>
             <td style="width: 18%;">: {{ $service->technician_name ?? '-' }}</td>
             <td style="width: 15%;"><strong>YSS Code</strong></td>
             <td style="width: 19%;">: {{ $service->yss ?? '-' }}</td>
         </tr>
         <tr>
             <td><strong>Members</strong></td>
             <td>: -</td>
             <td colspan="4"></td>
         </tr>
    </table>

    {{-- Tabel Item --}}
    <table class="items-table" style="margin-top: 8px;">
        <thead>
            <tr>
                {{-- Lebar kolom dari percobaan sebelumnya --}}
                <th style="width: 3%;">No.</th>
                <th style="width: 22%;">Package</th>
                <th style="width: 15%;">Nomor Item</th>
                <th style="width: 35%;">Nama Item</th>
                <th style="width: 10%;">Harga Satuan</th>
                <th style="width: 5%;">Qty</th>
                <th style="width: 10%;">Total</th>
            </tr>
        </thead>
        <tbody>
            @php $itemNumber = 1; @endphp
            @forelse ($detailsGrouped as $groupCode => $details)
                <tr class="separator-row">
                    <td colspan="7">{{ $groupCode ?: 'Lain-lain' }}</td>
                </tr>
                @php
                    $jasaDetails = $details->where('item_category', 'JASA')->sortByDesc('price');
                    $sparepartDetails = $details->whereIn('item_category', ['PART', 'OLI'])->sortByDesc('price');
                @endphp
                @foreach ($jasaDetails as $detail)
                    @php $totalItem = ($detail->quantity ?? 0) * ($detail->price ?? 0); @endphp
                    <tr>
                        <td class="text-center">{{ $itemNumber++ }}</td>
                        <td>{{ $detail->service_package_name }}</td>
                        <td>{{ $detail->item_code ?? '' }}</td>
                        <td>{{ $detail->item_name ?? '' }}</td>
                        <td class="text-right">{{ number_format($detail->price ?? 0, 0, ',', '.') }}</td>
                        <td class="text-center">{{ $detail->quantity ?? 0 }}</td>
                        <td class="text-right">{{ number_format($totalItem, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
                @if ($sparepartDetails->isNotEmpty())
                    <tr class="separator-row">
                        <td colspan="7">Sparepart</td>
                    </tr>
                    @foreach ($sparepartDetails as $detail)
                        @php $totalItem = ($detail->quantity ?? 0) * ($detail->price ?? 0); @endphp
                        <tr>
                            <td class="text-center">{{ $itemNumber++ }}</td>
                            <td></td>
                            <td>{{ $detail->item_code ?? '' }}</td>
                            <td>{{ $detail->item_name ?? '' }}</td>
                            <td class="text-right">{{ number_format($detail->price ?? 0, 0, ',', '.') }}</td>
                            <td class="text-center">{{ $detail->quantity ?? 0 }}</td>
                            <td class="text-right">{{ number_format($totalItem, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                @endif
            @empty
            @endforelse
            @if($service->details->isEmpty())
                 <tr><td colspan="7" class="text-center">Tidak ada item detail.</td></tr>
            @endif
        </tbody>
    </table>

    {{-- Footer --}}
    <table style="margin-top: 8px;">
        <tr>
            <td style="width: 60%; vertical-align: bottom;">
                <div><strong>Harga sudah termasuk PPN 11%</strong></div>
                <div>
                    <strong>Terbilang:</strong>
                    <div class="terbilang-box"># {{ trim(NumberHelper::terbilang($service->total_payment ?? 0)) }} Rupiah #</div>
                </div>
            </td>
            <td style="width: 40%; vertical-align: top;">
                <table style="width: 100%;">
                     @php
                         $totalService = $service->details->where('item_category', 'JASA')->sum(function($d){ return ($d->quantity ?? 0) * ($d->price ?? 0); });
                         $totalSparepart = $service->details->whereIn('item_category', ['PART', 'OLI'])->sum(function($d){ return ($d->quantity ?? 0) * ($d->price ?? 0); });
                         $grandTotal = $service->total_payment ?? 0;
                     @endphp
                     <tr>
                         <td><strong>Total Service:</strong></td>
                         <td class="text-right">Rp {{ number_format($totalService, 0, ',', '.') }}</td>
                     </tr>
                     <tr>
                         <td><strong>Total Sparepart:</strong></td>
                         <td class="text-right">Rp {{ number_format($totalSparepart, 0, ',', '.') }}</td>
                     </tr>
                     <tr>
                         <td><strong>Grand Total:</strong></td>
                         <td class="text-right"><strong>Rp {{ number_format($grandTotal, 0, ',', '.') }}</strong></td>
                     </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Tiga Tanda Tangan --}}
    <table class="signature-box" style="width: 100%;">
        <tr>
            <td class="text-center" style="width: 33%;">Counter Service,</td>
            <td class="text-center" style="width: 33%;">Konsumen,</td>
            <td class="text-center" style="width: 34%;">Kasir,</td>
        </tr>
        <tr>
            {{-- Padding atas dikontrol oleh $conditionalStyles dan $signaturePaddingTop --}}
            <td class="text-center" style="padding-top: {{$signaturePaddingTop}};">(__________________)</td>
            <td class="text-center" style="padding-top: {{$signaturePaddingTop}};">(__________________)</td>
            <td class="text-center" style="padding-top: {{$signaturePaddingTop}};">(__________________)</td>
        </tr>
    </table>
</div>