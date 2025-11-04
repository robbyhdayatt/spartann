@php
use App\Helpers\NumberHelper;
$detailsGrouped = $service->details->groupBy('service_category_code');

// === Data Dealer ===
$namaDealer = $service->lokasi->nama_lokasi ?? 'Data Lokasi Tidak Ditemukan';
$alamatDealer = ($service->lokasi->alamat && $service->lokasi->alamat !== '\N') ? $service->lokasi->alamat : '-';
$npwpDealer = 'NPWP No.: ';

// === Pengaturan Dinamis agar Tetap 1 Lembar A4 ===
$totalDetailsCount = $service->details->count();
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

    @media print {
        @page {
            size: A4 portrait;
            margin: 10mm;
        }
        html, body {
            height: 297mm;
            overflow: hidden; /* pastikan tidak lebih dari 1 halaman */
        }
    }

    {!! $conditionalStyles !!}
</style>

{{-- ========================= BODY INVOICE ========================= --}}
<div class="invoice-box">
    {{-- Header --}}
    <table>
        <tr>
            <td style="width: 60%; vertical-align: bottom;">
                <div class="header-main">FAKTUR SERVICE</div>
            </td>
            <td style="width: 40%; text-align: right;">
                <div class="header-sub">{{ $namaDealer }}</div>
                <div>{{ $alamatDealer }}</div>
                <div>{{ $npwpDealer }}</div>
            </td>
        </tr>
    </table>

    {{-- Info Pelanggan & Kendaraan --}}
    <table class="info-table">
        <tr>
            <td><strong>Tanggal</strong></td>
            <td>: {{ $service->reg_date ? \Carbon\Carbon::parse($service->reg_date)->format('d/m/Y') : '-' }}</td>
            <td><strong>Nama</strong></td>
            <td>: {{ $service->customer_name ?? '-' }}</td>
            <td><strong>No. Rangka</strong></td>
            <td>: {{ $service->mc_frame_no ?? '-' }}</td>
        </tr>
        <tr>
            <td><strong>No. Invoice</strong></td>
            <td>: {{ $service->invoice_no ?? '-' }}</td>
            <td><strong>Alamat</strong></td>
            <td>: -</td>
            <td colspan="2"></td>
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

    <table class="info-table">
        <tr>
            <td><strong>No. Polisi</strong></td>
            <td>: {{ $service->plate_no ?? '-' }}</td>
            <td><strong>Technician</strong></td>
            <td>: {{ $service->technician_name ?? '-' }}</td>
            <td><strong>YSS Code</strong></td>
            <td>: {{ $service->yss ?? '-' }}</td>
        </tr>
    </table>

    {{-- Tabel Item --}}
    <table class="items-table">
        <thead>
            <tr>
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
                    <tr class="separator-row"><td colspan="7">Sparepart</td></tr>
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
                <tr><td colspan="7" class="text-center">Tidak ada item detail.</td></tr>
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
                        # {{ trim(NumberHelper::terbilang($service->total_amount ?? 0)) }} Rupiah #
                    </div>
                </div>
            </td>
            <td style="width: 40%; vertical-align: top;">
                <table style="width: 100%;">
                    @php
                        $totalService = $service->details->where('item_category', 'JASA')
                            ->sum(fn($d) => ($d->quantity ?? 0) * ($d->price ?? 0));
                        $totalSparepart = $service->details->whereIn('item_category', ['PART', 'OLI'])
                            ->sum(fn($d) => ($d->quantity ?? 0) * ($d->price ?? 0));
                        $grandTotal = $service->total_amount ?? 0;
                        $totalDP = $service->total_down_payment ?? 0;
                        $benefitAmount = $service->benefit_amount ?? 0;
                    @endphp
                    <tr><td><strong>Total Service:</strong></td><td class="text-right">Rp {{ number_format($totalService, 0, ',', '.') }}</td></tr>
                    <tr><td><strong>Total Sparepart:</strong></td><td class="text-right">Rp {{ number_format($totalSparepart, 0, ',', '.') }}</td></tr>
                    <tr><td><strong>Total Down Payment (DP):</strong></td><td class="text-right">Rp {{ number_format($totalDP, 0, ',', '.') }}</td></tr>
                    <tr><td><strong>Benefit Amount:</strong></td><td class="text-right">Rp {{ number_format($benefitAmount, 0, ',', '.') }}</td></tr>
                    <tr><td><strong>Grand Total:</strong></td><td class="text-right"><strong>Rp {{ number_format($grandTotal, 0, ',', '.') }}</strong></td></tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Tanda Tangan --}}
    <table class="signature-box" style="width: 100%;">
        <tr>
            <td class="text-center" style="width: 33%;">Counter Service,</td>
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
