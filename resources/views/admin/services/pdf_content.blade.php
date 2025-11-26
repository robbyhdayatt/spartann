@php
use App\Helpers\NumberHelper;

$detailsGrouped = $service->details->groupBy('service_category_code');

// === Data Dealer ===
$namaDealer = $service->lokasi->nama_lokasi ?? 'Data Lokasi Tidak Ditemukan';
$namaDealer = str_replace('LTI', 'Lautan Teduh', $namaDealer);
$alamatDealer = $service->lokasi->alamat;
if (empty($alamatDealer) || $alamatDealer === '\N') {
    $alamatDealer = 'Alamat Tidak Tersedia';
}
$npwpDealer = 'NPWP No.: ' . ($service->lokasi->npwp ?? $service->customer_npwp_no ?? '-');

// === Jenis Service Order ===
$serviceOrder = $service->service_order ?? 'Walk In Service';
$isPartRetail = stripos($serviceOrder, 'part') !== false;

// === Pengaturan Font & Tampilan (DIPERKECIL) ===
$totalDetailsCount = $service->details->count();
$maxItemsPerPage = 40; // Bisa muat lebih banyak item karena font kecil

// Ukuran Font Standar (Diperkecil)
$baseFontSize = 11;
$lineHeight = 1.2;
$rowPadding = 2;
$signaturePaddingTop = 30;

// Logika Scaling Otomatis (Jika item sangat banyak)
if ($totalDetailsCount > $maxItemsPerPage) {
    $scale = max(0.85, 1 - (($totalDetailsCount - $maxItemsPerPage) * 0.01));
    $baseFontSize = floor(11 * $scale);
    $lineHeight = max(1.0, 1.2 * $scale);
    $rowPadding = max(1, floor(2 * $scale));
    $signaturePaddingTop = max(20, floor(30 * $scale));
}

$conditionalStyles = "
    .invoice-box { font-size: {$baseFontSize}px; }
    .invoice-box table { line-height: {$lineHeight}; }
    .items-table td, .items-table th { padding: {$rowPadding}px 2px; line-height: 1.1; }
    .signature-box td[style*='padding-top'] { padding-top: {$signaturePaddingTop}px !important; }
";
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Invoice {{ $service->invoice_no }}</title>
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
            color: #000;
            width: 100%;
            height: 100%;
            letter-spacing: 0.1px; /* Sedikit dirapatkan */
        }

        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }

        /* Style Header & Footer Tabel agar lebih compact */
        .items-table th {
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            font-weight: bold;
            text-align: center;
        }

        /* Render style dinamis dari PHP */
        {!! $conditionalStyles !!}
    </style>
</head>
<body>
<div class="invoice-box">

    {{-- ================= HEADER ================= --}}
    <table style="width: 100%; margin-bottom: 5px;">
        <tr>
            <td style="text-align: center;">
                <div style="font-size: 1.4em; font-weight: bold; text-transform: uppercase;">
                    {{ $isPartRetail ? 'FAKTUR PENJUALAN' : 'FAKTUR SERVICE' }}
                </div>
            </td>
        </tr>
    </table>

    <table style="width: 100%; margin-bottom: 5px;">
        <tr>
            <td style="width: 60%; font-weight: bold; font-size: 1.1em;">{{ $namaDealer }}</td>
            <td style="width: 40%; text-align: right;">
                <div>{{ $alamatDealer }}</div>
                <div>{{ $npwpDealer }}</div>
            </td>
        </tr>
    </table>

    <hr style="border:0; border-top:1px solid #000; margin: 3px 0;">

    {{-- ================= INFO PELANGGAN ================= --}}
    <table style="width: 100%; line-height: 1.2;">
        <tr>
            <td style="width:13%;"><strong>Tanggal</strong></td>
            <td style="width:22%;">: {{ $service->reg_date ? \Carbon\Carbon::parse($service->reg_date)->format('d/m/Y') : '-' }}</td>
            <td style="width:13%;"><strong>Nama</strong></td>
            <td style="width:20%;">: {{ $service->customer_name ?? '-' }}</td>
            @unless($isPartRetail)
                <td style="width:14%;"><strong>No. Rangka</strong></td>
                <td style="width:18%;">: {{ $service->mc_frame_no ?? '-' }}</td>
            @endunless
        </tr>
        <tr>
            <td><strong>No. Invoice</strong></td>
            <td>: {{ $service->invoice_no ?? '-' }}</td>
            <td><strong>Alamat</strong></td>
            <td colspan="{{ $isPartRetail ? 3 : 3 }}">: {{ $service->customer_address ?? '-' }}</td>
        </tr>
        <tr>
            <td><strong>Order No.</strong></td>
            <td>: {{ $service->work_order_no ?? '-' }}</td>
            <td><strong>Mobile</strong></td>
            <td>: {{ $service->customer_phone ?? '-' }}</td>
            @unless($isPartRetail)
                <td><strong>Tipe Motor</strong></td>
                <td>: {{ $service->mc_model_name ?? '-' }}</td>
            @endunless
        </tr>
        <tr>
            <td><strong>No. Polisi</strong></td>
            <td>: {{ $service->plate_no ?? '-' }}</td>
            <td><strong>Technician</strong></td>
            <td>: {{ $service->technician_name ?? '-' }}</td>
            <td><strong>YSS Code</strong></td>
            <td>: {{ $service->yss ?? '-' }}</td>
        </tr>
    </table>

    <hr style="border:0; border-top:1px solid #000; margin: 5px 0;">

    {{-- ================= ITEM TABLE ================= --}}
    <table class="items-table" style="width: 100%;">
        <thead>
            <tr>
                <th style="width:4%;">No.</th>
                <th style="width:20%;">Package</th>
                <th style="width:15%;">Nomor Item</th>
                <th style="width:35%;">Nama Item</th>
                <th style="width:12%;">Harga Satuan</th>
                <th style="width:4%;">Qty</th>
                <th style="width:10%;">Total</th>
            </tr>
        </thead>
        <tbody>
            @php $itemNumber = 1; @endphp
            @foreach ($detailsGrouped as $groupCode => $details)
                @php
                    $jasaDetails = $details->where('item_category', 'JASA')->sortByDesc('price');
                    $sparepartDetails = $details->whereIn('item_category', ['PART', 'OLI'])->sortByDesc('price');
                @endphp

                @unless($isPartRetail)
                    <tr style="font-weight:bold; border-top:1px solid #ccc;">
                        <td colspan="7" style="padding-top: 5px;">{{ $groupCode ?: 'Lain-lain' }}</td>
                    </tr>
                @endunless

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
                    @unless($isPartRetail)
                        <tr><td colspan="7" style="font-style:italic; font-weight:bold; padding-left: 10px;">Sparepart:</td></tr>
                    @endunless
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
            @endforeach
        </tbody>
    </table>

    <hr style="border:0; border-top:1px solid #000; margin: 5px 0;">

    {{-- ================= FOOTER ================= --}}
    @php
        $totalService = $service->details->where('item_category', 'JASA')->sum(fn($d) => ($d->quantity ?? 0) * ($d->price ?? 0));
        $totalSparepart = $service->details->whereIn('item_category', ['PART', 'OLI'])->sum(fn($d) => ($d->quantity ?? 0) * ($d->price ?? 0));
        $totalDP = $service->total_down_payment ?? 0;
        $benefitAmount = $service->benefit_amount ?? 0;
        $totalPayment = $isPartRetail
            ? ($service->total_amount ?? 0)
            : ($service->total_payment ?? $service->total_amount ?? 0);
    @endphp

    <table style="width: 100%;">
        <tr>
            <td style="width: 60%; vertical-align: top;">
                <div><strong>Harga sudah termasuk PPN 11%</strong></div>
                <div><strong>Terbilang:</strong></div>
                <div style="font-style:italic; text-transform: capitalize;">
                    # {{ trim(NumberHelper::terbilang($totalPayment)) }} Rupiah #
                </div>
            </td>
            <td style="width: 40%; vertical-align: top;">
                <table style="width: 100%;">
                    @if($isPartRetail)
                        <tr><td>Sub Total Spare Parts:</td><td class="text-right">Rp {{ number_format($totalSparepart, 0, ',', '.') }}</td></tr>
                        <tr><td>Down Payment:</td><td class="text-right">Rp {{ number_format($totalDP, 0, ',', '.') }}</td></tr>
                        <tr><td>Member Benefit:</td><td class="text-right">Rp {{ number_format($benefitAmount, 0, ',', '.') }}</td></tr>
                        <tr><td style="border-top:1px solid #000;"><strong>Total Bayar:</strong></td>
                            <td style="border-top:1px solid #000; text-align:right;"><strong>Rp {{ number_format($totalPayment, 0, ',', '.') }}</strong></td></tr>
                    @else
                        <tr><td>Total Service:</td><td class="text-right">Rp {{ number_format($totalService, 0, ',', '.') }}</td></tr>
                        <tr><td>Total Sparepart:</td><td class="text-right">Rp {{ number_format($totalSparepart, 0, ',', '.') }}</td></tr>
                        <tr><td>Member Benefit:</td><td class="text-right">Rp {{ number_format($benefitAmount, 0, ',', '.') }}</td></tr>
                        <tr><td style="border-top:1px solid #000;"><strong>Total Bayar:</strong></td>
                            <td style="border-top:1px solid #000; text-align:right;"><strong>Rp {{ number_format($totalPayment, 0, ',', '.') }}</strong></td></tr>
                        <tr><td>Down Payment:</td><td class="text-right">Rp {{ number_format($totalDP, 0, ',', '.') }}</td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <hr style="border:0; border-top:1px solid #000; margin: 8px 0 2px 0;">

    {{-- ================= SIGNATURE ================= --}}
    <table class="signature-box" style="width: 100%; text-align: center;">
        <tr>
            <td style="width:33%;">Counter Service,</td>
            <td style="width:33%;">Konsumen,</td>
            <td style="width:34%;">Kasir,</td>
        </tr>
        <tr>
            {{-- Padding top diatur oleh variabel $signaturePaddingTop --}}
            <td style="padding-top:30px;">(__________________)</td>
            <td style="padding-top:30px;">(__________________)</td>
            <td style="padding-top:30px;">(__________________)</td>
        </tr>
    </table>

</div>

{{-- ================= PAGE NUMBER ================= --}}
<script type="text/php">
if (isset($pdf)) {
    $font = $fontMetrics->get_font("Arial", "normal");
    $size = 9; // Font size untuk nomor halaman juga diperkecil
    $pageText = "Hal {PAGE_NUM} dari {PAGE_COUNT}";
    $x = $pdf->get_width() - 60;
    $y = $pdf->get_height() - 15;
    $pdf->page_text($x, $y, $pageText, $font, $size, [0,0,0]);
}
</script>

</body>
</html>
