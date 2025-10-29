@php
use App\Helpers\NumberHelper;
@endphp

{{-- CSS Kustom untuk Faktur Penjualan PDF --}}
<style>
    .faktur-box {
        /* Font tebal dan jelas, ukuran dikecilkan agar muat */
        font-family: 'Helvetica', 'Arial', sans-serif;
        font-weight: bold;
        font-size: 10px; /* Ukuran font dikecilkan agar muat di 24x12cm */
        color: #000;
        /* Beri sedikit padding agar tidak terlalu mentok */
        padding: 10px;
    }
    .faktur-box table {
        width: 100%;
        line-height: 1.3;
        text-align: left;
        border-collapse: collapse;
    }
    .faktur-box table td, .faktur-box table th {
        padding: 2px 4px;
        vertical-align: top;
    }
    .header-main { font-size: 14px; font-weight: bold; }
    .header-sub { font-size: 12px; font-weight: bold; }

    /* Tabel Item */
    .items-table {
        margin-top: 10px;
        border: none; /* Hapus border luar tabel */
    }
    .items-table thead th {
        /* Garis pembatas utuh di header tabel */
        border: none;
        border-bottom: 1px solid #000; /* Garis utuh di header */
        padding: 4px;
        text-align: center;
    }
    .items-table tbody tr {
        /* Tidak ada garis di setiap baris part */
        border-bottom: none;
    }
    .items-table td {
        border: none; /* Hapus border sel */
        padding: 3px 4px; /* Perkecil padding baris */
    }

    .text-right { text-align: right; }
    .text-center { text-align: center; }
    .font-bold { font-weight: bold; }
    .signature-box { margin-top: 15px; }

    /* Garis utuh (bukan putus-putus) */
    hr {
        border: none;
        border-top: 1px solid #000;
        margin: 5px 0;
    }
</style>

<div class="faktur-box">
    {{-- Header --}}
    <table>
        <tr>
            <td style="width: 60%;"><div class="header-main">FAKTUR PENJUALAN</div></td>
            <td style="width: 40%;" class="text-right">
                {{-- Ambil dari $penjualan->lokasi --}}
                <div class="header-sub">{{ $penjualan->lokasi->nama_lokasi ?? 'N/A' }}</div>
                <div>{{ $penjualan->lokasi->alamat ?? 'Alamat tidak tersedia' }}</div>
            </td>
        </tr>
    </table>
    <hr> {{-- Ini akan menjadi garis utuh --}}

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
            <td>: {{ $penjualan->lokasi->kode_lokasi }}</td> {{-- Tampilkan kode lokasi --}}
        </tr>
    </table>

    {{-- Tabel Item --}}
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 3%;">No.</th>
                <th style="width: 15%;">Kode Part</th>
                <th>Nama Part</th>
                <th style="width: 15%;" class="text-right">Harga Satuan</th>
                <th style="width: 8%;" class="text-center">Qty</th>
                <th style="width: 15%;" class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($penjualan->details as $detail)
                <tr>
                    <td class="text-center">{{ $loop->iteration }}</td>
                    <td>{{ $detail->part->kode_part ?? '' }}</td>
                    <td>{{ $detail->part->nama_part ?? '' }}</td>
                    {{-- ++ PERBAIKAN: Ganti @rupiah ke PHP standar ++ --}}
                    <td class="text-right">{{ 'Rp ' . number_format($detail->harga_jual, 0, ',', '.') }}</td>
                    <td class="text-center">{{ $detail->qty_jual }}</td>
                    <td class="text-right">{{ 'Rp ' . number_format($detail->subtotal, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center">Tidak ada item detail.</td></tr>
            @endforelse
             {{-- Baris pembatas utuh HANYA di akhir tabel --}}
             <tr>
                 <td colspan="6" style="padding: 1px; border-bottom: 1px solid #000;"></td>
             </tr>
        </tbody>
    </table>

    {{-- Footer --}}
    <table style="margin-top: 5px;">
        <tr>
            <td style="width: 60%; vertical-align: top;">
                <div class="font-bold">Harga sudah termasuk PPN 11%</div>
            </td>
            <td style="width: 40%; vertical-align: top;">
                <table style="width: 100%;">
                    <tr>
                        <td class="font-bold">Grand Total:</td>
                        {{-- ++ PERBAIKAN: Ganti @rupiah ke PHP standar ++ --}}
                        <td class="text-right font-bold">{{ 'Rp ' . number_format($penjualan->total_harga, 0, ',', '.') }}</td>
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
            <td class="text-center" style="padding-top: 30px;">(__________________)</td>
            <td class="text-center" style="padding-top: 30px;">(__________________)</td>
            <td class="text-center" style="padding-top: 30px;">(__________________)</td>
        </tr>
    </table>
</div>
