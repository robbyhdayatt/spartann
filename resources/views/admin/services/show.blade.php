@php
use App\Helpers\NumberHelper;
$detailsGrouped = $service->details->groupBy('service_category_code');
$subTotal = 0;
@endphp

@extends('adminlte::page')

@section('title', 'Invoice ' . $service->invoice_no)

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1>Faktur Service: {{ $service->invoice_no }}</h1>
        <div>
            @can('manage-service')
                <a href="{{ route('admin.services.edit', $service->id) }}" class="btn btn-warning">
                    <i class="fas fa-edit"></i> Edit / Tambah Part
                </a>
            @endcan
            <a href="{{ route('admin.services.pdf', $service->id) }}" class="btn btn-danger">
                <i class="fas fa-file-pdf"></i> Export PDF
            </a>
        </div>
    </div>
@stop

@section('content')
<div class="invoice p-3 mb-3">
    {{-- CSS Kustom --}}
    <style>
        .invoice-box{border:1px solid #aaa;padding:20px;font-family:'Courier New',Courier,monospace;font-size:12px;color:#000}.invoice-box table{width:100%;line-height:inherit;text-align:left;border-collapse:collapse}.invoice-box table td,.invoice-box table th{padding:2px 4px;vertical-align:top}.header-main{font-size:16px;font-weight:bold}.header-sub{font-size:14px;font-weight:bold}.info-table td{padding-bottom:3px}.items-table th,.items-table td{border:1px solid #000;padding:4px}.items-table th{text-align:center}.text-right{text-align:right}.text-center{text-align:center}.font-bold{font-weight:bold}.terbilang-box{padding:5px;font-style:italic}.signature-box{margin-top:40px}@media print{body{margin:0;padding:0}.main-header,.main-sidebar,.main-footer,.btn,.content-header{display:none!important}.content-wrapper,.content{padding:0!important;margin:0!important}.invoice{border:none!important;padding:0!important;box-shadow:none!important}}
    </style>

    {{-- Konten Faktur --}}
    <section class="invoice-box">
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
        <hr style="border-top: 1px solid #000; margin: 5px 0;">

        {{-- Info Pelanggan & Kendaraan --}}
        <table style="width: 100%;">
            <tr>
                <td style="width: 65%; vertical-align: top;">
                    <table class="info-table">
                        <tr><td style="width: 18%;"><strong>Tanggal</strong></td><td>: {{ $service->reg_date ? \Carbon\Carbon::parse($service->reg_date)->format('d/m/Y') : '-' }}</td></tr>
                        <tr><td><strong>No. Invoice</strong></td><td>: {{ $service->invoice_no ?? '-' }}</td></tr>
                        <tr><td><strong>Order No.</strong></td><td>: {{ $service->work_order_no ?? '-' }}</td></tr>
                        <tr><td colspan="2"><hr style="border-top: 1px dotted #000; margin: 3px 0;"></td></tr>
                        <tr><td><strong>Nama</strong></td><td>: {{ $service->customer_name ?? '-' }}</td></tr>
                        <tr><td><strong>Alamat</strong></td><td>: -</td></tr>
                        <tr><td><strong>Mobile</strong></td><td>: {{ $service->customer_phone ?? '-' }}</td></tr>
                        <tr><td colspan="2"><hr style="border-top: 1px dotted #000; margin: 3px 0;"></td></tr>
                        <tr><td><strong>No. Rangka</strong></td><td>: {{ $service->mc_frame_no ?? '-' }}</td></tr>
                        <tr><td><strong>No. Mesin</strong></td><td>: -</td></tr>
                        <tr><td><strong>Tipe Motor</strong></td><td>: {{ $service->mc_model_name ?? '-' }}</td></tr>
                    </table>
                </td>
                <td style="width: 35%; vertical-align: top;">
                    <table class="info-table">
                        <tr><td style="width: 35%;"><strong>No. Polisi</strong></td><td>: {{ $service->plate_no ?? '-' }}</td></tr>
                        <tr><td colspan="2">&nbsp;</td></tr>
                        <tr><td colspan="2">&nbsp;</td></tr>
                        <tr><td colspan="2"><hr style="border-top: 1px dotted #000; margin: 3px 0;"></td></tr>
                        <tr><td><strong>Technician</strong></td><td>: {{ $service->technician_name ?? '-' }}</td></tr>
                        <tr><td><strong>Members</strong></td><td>: -</td></tr>
                        <tr><td colspan="2">&nbsp;</td></tr>
                        <tr><td colspan="2"><hr style="border-top: 1px dotted #000; margin: 3px 0;"></td></tr>
                        <tr><td><strong>YSS Code</strong></td><td>: {{ $service->yss ?? '-' }}</td></tr>
                    </table>
                </td>
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
                    <th>Discount %</th>
                    <th>Qty</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @php $itemNumber = 1; @endphp
                @forelse ($detailsGrouped as $groupCode => $details)
                    <tr><td colspan="8" class="font-bold">{{ $groupCode ?: 'Lainnya' }}</td></tr>
                    @foreach ($details as $detail)
                        @php $totalItem = ($detail->quantity ?? 0) * ($detail->price ?? 0); $subTotal += $totalItem; @endphp
                        <tr>
                            <td class="text-center">{{ $itemNumber++ }}</td>
                            <td>{{ $detail->item_category == 'JASA' ? $detail->service_package_name : '' }}</td>
                            <td>{{ $detail->item_code ?? '' }}</td>
                            <td>{{ $detail->item_name ?? '' }}</td>
                            <td class="text-right">{{ number_format($detail->price ?? 0, 0, ',', '.') }}</td>
                            <td class="text-center">0.00</td>
                            <td class="text-center">{{ $detail->quantity ?? 0 }}</td>
                            <td class="text-right">{{ number_format($totalItem, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                @empty
                    <tr><td colspan="8" class="text-center">Tidak ada item detail.</td></tr>
                @endforelse
            </tbody>
        </table>

        {{-- Footer --}}
        <table style="margin-top: 10px;">
            <tr>
                <td style="width: 60%; vertical-align: top;">
                    <div>
                        <span class="font-bold">Terbilang:</span>
                        <div class="terbilang-box"># {{ trim(NumberHelper::terbilang($service->total_payment ?? 0)) }} Rupiah #</div>
                    </div>
                    <table class="signature-box" style="width: 100%;">
                        <tr>
                            <td class="text-center" style="width: 50%;">Diterima oleh,</td>
                            <td class="text-center" style="width: 50%;">Hormat Kami,</td>
                        </tr>
                        <tr>
                            <td class="text-center" style="padding-top: 40px;">(__________________)</td>
                            <td class="text-center" style="padding-top: 40px;">(__________________)</td>
                        </tr>
                    </table>
                </td>
                <td style="width: 40%; vertical-align: top;">
                    <table style="width: 100%;">
                        <tr>
                            <td class="font-bold">Sub-Total:</td>
                            <td class="text-right">Rp {{ number_format($subTotal, 0, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td class="font-bold">Discount:</td>
                            <td class="text-right">Rp {{ number_format($service->benefit_amount ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td class="font-bold">Pajak PPN (11%):</td>
                            <td class="text-right">Rp 0</td>
                        </tr>
                        <tr>
                            <td class="font-bold">Grand Total:</td>
                            <td class="text-right font-bold">Rp {{ number_format($service->total_payment ?? 0, 0, ',', '.') }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </section>
</div>
@stop
