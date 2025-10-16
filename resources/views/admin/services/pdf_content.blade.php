@php
use App\Helpers\NumberHelper;
$detailsGrouped = $service->details->groupBy('service_category_code');
@endphp

{{-- CSS Kustom untuk Faktur --}}
<style>
    /* PERBAIKAN: Pindahkan style font dari 'body' ke '.invoice-box' */
    .invoice-box {
        font-family: 'Courier New', Courier, monospace;
        font-size: 12px;
        color: #000;
        padding: 20px;
    }
    
    body {
        /* Kosongkan atau biarkan hanya untuk pengaturan margin PDF jika diperlukan */
        margin: 0;
        padding: 0;
    }

    .invoice-box table { width: 100%; line-height: 1.2; text-align: left; border-collapse: collapse; }
    .invoice-box table td, .invoice-box table th { padding: 2px 4px; vertical-align: top; }
    .header-main { font-size: 16px; font-weight: bold; }
    .header-sub { font-size: 14px; font-weight: bold; }
    .info-table td { padding-bottom: 3px; }
    .items-table th, .items-table td { border: 1px solid #000; padding: 4px; }
    .items-table th { text-align: center; }
    .text-right { text-align: right; }
    .text-center { text-align: center; }
    .font-bold { font-weight: bold; }
    .terbilang-box { padding: 5px; font-style: italic; }
    .signature-box { margin-top: 20px; }
    hr { border: none; border-top: 1px solid #000; margin: 5px 0; }
    .dotted-hr { border: none; border-top: 1px dotted #000; margin: 3px 0; }
    
    @media print {
        .main-header, .main-sidebar, .main-footer, .btn, .content-header { display: none !important; }
        .content-wrapper, .content { padding: 0 !important; margin: 0 !important; }
        .invoice, .invoice-box { border: none !important; padding: 0 !important; box-shadow: none !important; }
    }
</style>

<div class="invoice-box">
    {{-- Header --}}
    <table>
        <tr>
            <td style="width: 60%;"><div class="header-main">FAKTUR SERVICE</div></td>
            <td style="width: 40%;" class="text-right">
                <div class="header-sub">SENTRAL YAMAHA LAMPUNG</div>
                <div>JL. IKAN TENGGIRI NO. 24</div>
                <div>NPWP No.: 0011176294007000</div>
            </td>
        </tr>
    </table>
    <hr>

    {{-- Info Pelanggan & Kendaraan (Tata Letak Baru) --}}
    <table style="width: 100%;">
        {{-- Baris Pertama --}}
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
    <hr class="dotted-hr">
    {{-- Baris Kedua --}}
    <table style="width: 100%;">
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
    <table class="items-table" style="margin-top: 10px;">
        <thead>
            <tr>
                <th style="width: 3%;">No.</th>
                <th>Package</th>
                <th>Nomor Item</th>
                <th>Nama Item</th>
                <th>Harga Satuan</th>
                <th>Qty</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @php $itemNumber = 1; @endphp
            @forelse ($detailsGrouped as $groupCode => $details)
                <tr><td colspan="7" class="font-bold">{{ $groupCode ?: 'Sparepart' }}</td></tr>
                @foreach ($details as $detail)
                    @php $totalItem = ($detail->quantity ?? 0) * ($detail->price ?? 0); @endphp
                    <tr>
                        <td class="text-center">{{ $itemNumber++ }}</td>
                        <td>{{ $detail->item_category == 'JASA' ? $detail->service_package_name : '' }}</td>
                        <td>{{ $detail->item_code ?? '' }}</td>
                        <td>{{ $detail->item_name ?? '' }}</td>
                        <td class="text-right">{{ number_format($detail->price ?? 0, 0, ',', '.') }}</td>
                        <td class="text-center">{{ $detail->quantity ?? 0 }}</td>
                        <td class="text-right">{{ number_format($totalItem, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            @empty
                <tr><td colspan="7" class="text-center">Tidak ada item detail.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- Footer --}}
    <table style="margin-top: 10px;">
        <tr>
            <td style="width: 60%; vertical-align: top;">
                <div class="font-bold">Harga sudah termasuk PPN 11%</div>
                <div>
                    <span class="font-bold">Terbilang:</span>
                    <div class="terbilang-box"># {{ trim(NumberHelper::terbilang($service->total_payment ?? 0)) }} Rupiah #</div>
                </div>
            </td>
            <td style="width: 40%; vertical-align: top;">
                <table style="width: 100%;">
                    <tr>
                        <td class="font-bold">Total Service:</td>
                        <td class="text-right">Rp {{ number_format($totalService, 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td class="font-bold">Total Sparepart:</td>
                        <td class="text-right">Rp {{ number_format($totalSparepart, 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td class="font-bold">Grand Total:</td>
                        <td class="text-right font-bold">Rp {{ number_format($service->total_payment ?? 0, 0, ',', '.') }}</td>
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
            <td class="text-center" style="padding-top: 40px;">(__________________)</td>
            <td class="text-center" style="padding-top: 40px;">(__________________)</td>
            <td class="text-center" style="padding-top: 40px;">(__________________)</td>
        </tr>
    </table>
</div>