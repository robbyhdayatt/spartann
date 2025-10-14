@php
use App\Helpers\NumberHelper;

$detailsGrouped = $service->details->groupBy('service_category_code');
$subTotal = 0;
@endphp

{{-- Header Faktur --}}
<table style="width: 100%;">
    <tr>
        <td style="width: 60%; vertical-align: top;">
            <div style="font-size: 24px; font-weight: bold;">FAKTUR SERVICE</div>
        </td>
        <td style="width: 40%; text-align: right; vertical-align: top;">
            <div style="font-size: 20px; font-weight: bold;">SENTRAL YAMAHA LAMPUNG</div>
            <div style="font-size: 14px;">JL. IKAN TENGGIRI NO. 24</div>
            <div style="font-size: 14px;">NPWP No.: 0011176294007000</div>
        </td>
    </tr>
</table>

<hr style="border-top: 1px solid #000; margin: 3px 0;">

{{-- Informasi Pelanggan & Kendaraan --}}
<table style="width: 100%;">
    <tr>
        <td style="width: 65%; vertical-align: top;">
            <table>
                <tr><td style="width: 18%;" class="font-bold">Tanggal</td><td>: {{ $service->reg_date ? \Carbon\Carbon::parse($service->reg_date)->format('d/m/Y') : '-' }}</td></tr>
                <tr><td class="font-bold">No. Invoice</td><td>: {{ $service->invoice_no ?? '-' }}</td></tr>
                <tr><td class="font-bold">Order No.</td><td>: {{ $service->work_order_no ?? '-' }}</td></tr>
                <tr><td colspan="2"><div class="dotted-hr"></div></td></tr>
                <tr><td class="font-bold">Nama</td><td>: {{ $service->customer_name ?? '-' }}</td></tr>
                <tr><td class="font-bold">Alamat</td><td>: -</td></tr>
                <tr><td class="font-bold">Mobile</td><td>: {{ $service->customer_phone ?? '-' }}</td></tr>
                <tr><td colspan="2"><div class="dotted-hr"></div></td></tr>
                <tr><td class="font-bold">No. Rangka</td><td>: {{ $service->mc_frame_no ?? '-' }}</td></tr>
                <tr><td class="font-bold">No. Mesin</td><td>: -</td></tr>
                <tr><td class="font-bold">Tipe Motor</td><td>: {{ $service->mc_model_name ?? '-' }}</td></tr>
            </table>
        </td>

        <td style="width: 35%; vertical-align: top;">
            <table>
                <tr><td style="width: 35%;" class="font-bold">No. Polisi</td><td>: {{ $service->plate_no ?? '-' }}</td></tr>
                <tr><td colspan="2">&nbsp;</td></tr>
                <tr><td colspan="2">&nbsp;</td></tr>
                <tr><td colspan="2"><div class="dotted-hr"></div></td></tr>
                <tr><td class="font-bold">Technician</td><td>: {{ $service->technician_name ?? '-' }}</td></tr>
                <tr><td class="font-bold">Members</td><td>: -</td></tr>
                <tr><td colspan="2">&nbsp;</td></tr>
                <tr><td colspan="2"><div class="dotted-hr"></div></td></tr>
                <tr><td class="font-bold">YSS Code</td><td>: {{ $service->yss ?? '-' }}</td></tr>
            </table>
        </td>
    </tr>
</table>

{{-- Tabel Item --}}
<table class="items-table" style="margin-top: 5px;">
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
                @php
                    $totalItem = ($detail->quantity ?? 0) * ($detail->price ?? 0);
                    $subTotal += $totalItem;
                @endphp
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
            <tr><td colspan="8" class="text-center" style="padding: 10px;">Tidak ada item detail.</td></tr>
        @endforelse
    </tbody>
</table>

{{-- Footer --}}
<table style="margin-top: 10px; width: 100%;">
    <tr>
        <td style="width: 60%; vertical-align: top;">
            <div>
                <span class="font-bold">Terbilang:</span>
                <div class="terbilang-box">
                    # {{ trim(NumberHelper::terbilang($service->total_payment ?? 0)) }} Rupiah #
                </div>
            </div>
            <table class="signature-box" style="width: 100%; margin-top: 40px;"> {{-- Geser tanda tangan ke bawah --}}
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
