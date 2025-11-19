@php
use App\Helpers\NumberHelper;
@endphp

{{-- CSS Kustom untuk PO, meniru Faktur Service --}}
<style>
    .po-box {
        font-family: 'Courier New', Courier, monospace;
        font-size: 12px;
        color: #000;
        padding: 20px;
    }
    .po-box table { width: 100%; line-height: 1.2; text-align: left; border-collapse: collapse; }
    .po-box table td, .po-box table th { padding: 2px 4px; vertical-align: top; }
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

<div class="po-box">
    {{-- Header --}}
    <table>
        <tr>
            <td style="width: 60%;"><div class="header-main">PURCHASE ORDER</div></td>
            <td style="width: 40%;" class="text-right">
                {{-- Menampilkan Lokasi Tujuan (Yang Request) --}}
                <div class="header-sub">{{ $purchaseOrder->lokasi->nama_lokasi ?? 'GUDANG PEMESAN' }}</div>
                <div>{{ $purchaseOrder->lokasi->alamat ?? '-' }}</div>
            </td>
        </tr>
    </table>
    <hr>

    {{-- Info Supplier/Sumber & PO --}}
    <table style="width: 100%;">
        <tr>
            <td style="width: 15%;"><strong>Tanggal</strong></td>
            <td style="width: 18%;">: {{ $purchaseOrder->tanggal_po ? $purchaseOrder->tanggal_po->format('d/m/Y') : '-' }}</td>
            <td style="width: 15%;"><strong>Kepada Yth.</strong></td>
            <td style="width: 52%;">
                {{-- PERBAIKAN LOGIKA DISPLAY SUPPLIER/INTERNAL --}}
                :
                @if($purchaseOrder->supplier)
                    {{ $purchaseOrder->supplier->nama_supplier }}
                @elseif($purchaseOrder->sumberLokasi)
                    {{ $purchaseOrder->sumberLokasi->nama_lokasi }} (Internal Transfer)
                @else
                    -
                @endif
            </td>
        </tr>
        <tr>
            <td><strong>Nomor PO</strong></td>
            <td>: {{ $purchaseOrder->nomor_po }}</td>
            <td><strong>Alamat</strong></td>
            <td>
                :
                @if($purchaseOrder->supplier)
                    {{ $purchaseOrder->supplier->alamat ?? '-' }}
                @elseif($purchaseOrder->sumberLokasi)
                    {{ $purchaseOrder->sumberLokasi->alamat ?? '-' }}
                @else
                    -
                @endif
            </td>
        </tr>
        <tr>
            <td><strong>Status</strong></td>
            <td>: {{ $purchaseOrder->status_badge ?? $purchaseOrder->status }}</td>
            <td><strong>Telepon</strong></td>
            <td>
                :
                @if($purchaseOrder->supplier)
                    {{ $purchaseOrder->supplier->telepon ?? '-' }}
                @elseif($purchaseOrder->sumberLokasi)
                    {{ $purchaseOrder->sumberLokasi->telepon ?? '-' }}
                @else
                    -
                @endif
            </td>
        </tr>
        @if($purchaseOrder->request_group_id)
        <tr>
            <td><strong>Group ID</strong></td>
            <td colspan="3">: {{ $purchaseOrder->request_group_id }}</td>
        </tr>
        @endif
    </table>

    {{-- Tabel Item --}}
    <table class="items-table" style="margin-top: 10px;">
        <thead>
            <tr>
                <th style="width: 5%;">No.</th>
                <th style="width: 20%;">Nomor Part</th>
                <th>Nama Part</th>
                {{-- Harga hanya relevan jika beli ke Supplier, transfer internal mungkin 0 atau HPP --}}
                <th style="width: 15%;">Harga Satuan</th>
                <th style="width: 10%;">Qty</th>
                <th style="width: 15%;">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($purchaseOrder->details as $detail)
                <tr>
                    <td class="text-center">{{ $loop->iteration }}</td>
                    {{-- Menggunakan relasi 'barang' (sebelumnya part) --}}
                    <td>{{ $detail->barang->part_code ?? $detail->part->part_code ?? '-' }}</td>
                    <td>{{ $detail->barang->part_name ?? $detail->part->part_name ?? '-' }}</td>
                    <td class="text-right">@rupiah($detail->harga_beli)</td>
                    <td class="text-center">{{ $detail->qty_pesan }}</td>
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
                <div class="font-bold">Catatan:</div>
                <div style="font-style: italic;">
                    {{ $purchaseOrder->catatan ?: '-' }}
                </div>
            </td>
            <td style="width: 40%; vertical-align: top;">
                <table style="width: 100%;">
                    <tr>
                        <td class="font-bold">Grand Total:</td>
                        <td class="text-right font-bold">@rupiah($purchaseOrder->total_amount)</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Tiga Tanda Tangan --}}
    <table class="signature-box" style="width: 100%;">
        <tr>
            <td class="text-center" style="width: 33%;">Dibuat Oleh,</td>
            <td class="text-center" style="width: 33%;">Disetujui Oleh,</td>
            <td class="text-center" style="width: 34%;">Diterima Oleh,</td>
        </tr>
        <tr>
            <td class="text-center" style="padding-top: 40px;">
                ({{ $purchaseOrder->createdBy->name ?? $purchaseOrder->createdBy->nama ?? '____________' }})
            </td>
            <td class="text-center" style="padding-top: 40px;">
                ({{ $purchaseOrder->approvedBy->name ?? $purchaseOrder->approvedBy->nama ?? '____________' }})
            </td>
            <td class="text-center" style="padding-top: 40px;">(__________________)</td>
        </tr>
    </table>
</div>
