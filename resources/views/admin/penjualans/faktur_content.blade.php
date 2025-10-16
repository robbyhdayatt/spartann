@php
use App\Helpers\NumberHelper;
@endphp

{{-- CSS Kustom untuk Faktur Penjualan --}}
<style>
    .faktur-box {
        font-family: 'Courier New', Courier, monospace;
        font-size: 12px;
        color: #000;
        padding: 20px;
    }
    .faktur-box table { width: 100%; line-height: 1.2; text-align: left; border-collapse: collapse; }
    .faktur-box table td, .faktur-box table th { padding: 2px 4px; vertical-align: top; }
    .header-main { font-size: 16px; font-weight: bold; }
    .header-sub { font-size: 14px; font-weight: bold; }
    .items-table th, .items-table td { border: 1px solid #000; padding: 4px; }
    .items-table th { text-align: center; }
    .text-right { text-align: right; }
    .text-center { text-align: center; }
    .font-bold { font-weight: bold; }
    .signature-box { margin-top: 20px; }
    hr { border: none; border-top: 1px solid #000; margin: 5px 0; }
    .dotted-hr { border: none; border-top: 1px dotted #000; margin: 3px 0; }
</style>

<div class="faktur-box">
    {{-- Header --}}
    <table>
        <tr>
            <td style="width: 60%;"><div class="header-main">FAKTUR PENJUALAN</div></td>
            <td style="width: 40%;" class="text-right">
                <div class="header-sub">SENTRAL YAMAHA LAMPUNG</div>
                <div>JL. IKAN TENGGIRI NO. 24</div>
            </td>
        </tr>
    </table>
    <hr>

    {{-- Info Pelanggan & Transaksi --}}
    <table style="width: 100%;">
        <tr>
            <td style="width: 15%;"><strong>Tanggal</strong></td>
            <td style="width: 35%;">: {{ $penjualan->tanggal_jual->format('d/m/Y') }}</td>
            <td style="width: 15%;"><strong>Konsumen</strong></td>
            <td style="width: 35%;">: {{ $penjualan->konsumen->nama_konsumen }}</td>
        </tr>
        <tr>
            <td><strong>No. Faktur</strong></td>
            <td>: {{ $penjualan->nomor_faktur }}</td>
            <td><strong>Telepon</strong></td>
            <td>: {{ $penjualan->konsumen->telepon ?? '-' }}</td>
        </tr>
        <tr>
            <td><strong>Sales</strong></td>
            <td>: {{ $penjualan->sales->nama ?? 'N/A' }}</td>
            <td><strong>Lokasi</strong></td>
            <td>: {{ $penjualan->lokasi->nama_gudang }}</td>
        </tr>
    </table>

    {{-- Tabel Item --}}
    <table class="items-table" style="margin-top: 10px;">
        <thead>
            <tr>
                <th style="width: 3%;">No.</th>
                <th style="width: 15%;">Kode Part</th>
                <th>Nama Part</th>
                <th style="width: 15%;">Harga Satuan</th>
                <th style="width: 8%;">Qty</th>
                <th style="width: 15%;">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($penjualan->details as $detail)
                <tr>
                    <td class="text-center">{{ $loop->iteration }}</td>
                    <td>{{ $detail->part->kode_part ?? '' }}</td>
                    <td>{{ $detail->part->nama_part ?? '' }}</td>
                    <td class="text-right">@rupiah($detail->harga_jual)</td>
                    <td class="text-center">{{ $detail->qty_jual }}</td>
                    <td class="text-right">@rupiah($detail->subtotal)</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center">Tidak ada item detail.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- Footer --}}
    <table style="margin-top: 10px;">
        <tr>
            <td style="width: 60%; vertical-align: top;">
                <div class="font-bold">Harga sudah termasuk PPN 11%</div>
            </td>
            <td style="width: 40%; vertical-align: top;">
                <table style="width: 100%;">
                    <tr>
                        <td class="font-bold">Grand Total:</td>
                        <td class="text-right font-bold">@rupiah($penjualan->total_harga)</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    
    {{-- Tiga Tanda Tangan --}}
    <table class="signature-box" style="width: 100%;">
        <tr>
            <td class="text-center" style="width: 33%;">Counter,</td>
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