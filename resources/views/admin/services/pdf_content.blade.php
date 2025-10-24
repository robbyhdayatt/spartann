@php
use App\Helpers\NumberHelper;
$detailsGrouped = $service->details->groupBy('service_category_code');

// ++ Tambahkan variabel default jika lokasi tidak ada ++
$namaDealer = 'Data Lokasi Tidak Ditemukan';
$alamatDealer = 'Alamat Tidak Tersedia';
$npwpDealer = 'NPWP Tidak Tersedia'; // Asumsi NPWP juga ada di tabel lokasi

// Cek apakah relasi lokasi ada dan datanya terisi
if ($service->lokasi) {
    // Gunakan data dari relasi
    $namaDealer = $service->lokasi->nama_gudang ?? $namaDealer; // Ambil nama_gudang

    // Ganti \N (NULL) dengan string kosong atau strip
    $alamatDealer = $service->lokasi->alamat && $service->lokasi->alamat !== '\N'
                    ? $service->lokasi->alamat
                    : '-';

    // Asumsi ada kolom 'npwp' di tabel 'lokasi'. Jika tidak, hapus/sesuaikan baris ini.
    // $npwpDealer = $service->lokasi->npwp ?? $npwpDealer;
}
// Jika Anda ingin tetap menggunakan NPWP statis:
$npwpDealer = 'NPWP No.: '; // Hapus baris ini jika NPWP dinamis

@endphp

{{-- CSS Kustom untuk Faktur --}}
<style>
    /* ... (Semua style CSS Anda dari sebelumnya tetap sama) ... */
     html, body { margin: 0 !important; padding: 0 !important; width: 100%; height: 100%; box-sizing: border-box; }
    *, *:before, *:after { box-sizing: inherit; }

    .invoice-box {
        font-family: 'Courier New', Courier, monospace;
        font-size: 13px;
        color: #000;
        padding: 0 !important;
        width: 100%;
        height: 100%;
    }
    .invoice-box table { width: 100%; line-height: 1.25; text-align: left; border-collapse: collapse; }
    .invoice-box table:not(.info-table) td,
    .invoice-box table:not(.info-table) th { padding: 2px 4px; vertical-align: top; border: none !important; }
    .header-main { font-size: 18px; font-weight: bold; }
    .header-sub { font-size: 16px; font-weight: bold; }
    .invoice-box table.info-table { line-height: 1.1 !important; margin-top: 5px !important; margin-bottom: 5px !important; }
    .invoice-box table.info-table td { padding-top: 1px !important; padding-bottom: 1px !important; padding-left: 5px; padding-right: 5px; vertical-align: top; border: none !important; }
    .items-table th { padding: 2px 4px; vertical-align: top; text-align: center; border: none !important; border-bottom: 1px solid #000 !important; padding-bottom: 4px; font-weight: bold; }
    .items-table tbody { border-bottom: 1px solid #000 !important; }
    .items-table tr.separator-row td { font-weight: bold; text-align: left; padding: 6px 4px 3px 4px; border-top: 1px solid #000 !important; background-color: transparent; font-size: 14px; }
    hr, .dotted-hr { display: none; }
    .text-right { text-align: right; }
    .text-center { text-align: center; }
    .font-bold { font-weight: bold; }
    .terbilang-box { padding: 5px; font-style: italic; }
    .signature-box { margin-top: 15px; }
</style>

<div class="invoice-box">
    {{-- Header --}}
    <table>
        <tr>
            <td style="width: 60%; vertical-align: bottom;"><div class="header-main">FAKTUR SERVICE</div></td>

            {{-- ++ PERUBAHAN: Header Dealer Dinamis ++ --}}
            <td style="width: 40%; text-align: right;">
                <div class="header-sub">{{ $namaDealer }}</div>
                <div>{{ $alamatDealer }}</div>
                <div>{{ $npwpDealer }}</div>
            </td>
        </tr>
    </table>

    {{-- Info Pelanggan & Kendaraan --}}
    <table class="info-table" style="width: 100%; margin-top: 15px;">
        {{-- ... (Konten Info Pelanggan 1 tetap sama) ... --}}
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
        {{-- ... (Konten Info Pelanggan 2 tetap sama) ... --}}
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
                <th style="width: 25%;">Package</th>
                <th style="width: 15%;">Nomor Item</th>
                <th style="width: 32%;">Nama Item</th>
                <th style="width: 10%;">Harga Satuan</th>
                <th style="width: 5%;">Qty</th>
                <th style="width: 10%;">Total</th>
            </tr>
        </thead>
        <tbody>
            {{-- ... (Looping Tbody tetap sama) ... --}}
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
    <table style="margin-top: 10px;">
        {{-- ... (Konten Footer tetap sama) ... --}}
        <tr>
            <td style="width: 60%; vertical-align: bottom;">
                <div class="font-bold">Harga sudah termasuk PPN 11%</div>
                <div>
                    <span class="font-bold">Terbilang:</span>
                    <div class="terbilang-box"># {{ trim(NumberHelper::terbilang($service->total_payment ?? 0)) }} Rupiah #</div>
                </div>
            </td>
            <td style="width: 40%; vertical-align: top;">
                <table style="width: 100%;">
                     @php
                         $totalService = $service->details->where('item_category', 'JASA')->sum(function($d){ return ($d->quantity ?? 0) * ($d->price ?? 0); });
                         $totalSparepart = $service->details->whereIn('item_category', ['PART', 'OLI'])->sum(function($d){ return ($d->quantity ?? 0) * ($d->price ?? 0); });
                     @endphp
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
       {{-- ... (Konten Tanda Tangan tetap sama) ... --}}
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

