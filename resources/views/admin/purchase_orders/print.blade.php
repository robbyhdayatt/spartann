<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Order - {{ $purchaseOrder->nomor_po }}</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            font-family: 'Times New Roman', Times, serif;
            color: #000;
        }
        .container {
            max-width: 800px;
        }
        .header, .footer {
            text-align: center;
        }
        .header h4, .header p {
            margin: 0;
        }
        .table th, .table td {
            vertical-align: middle;
            padding: 0.5rem;
            border: 1px solid #000 !important;
        }
        .signature-area {
            margin-top: 80px;
        }
        .signature-box {
            display: inline-block;
            width: 200px;
            text-align: center;
        }
        .signature-line {
            border-bottom: 1px solid #000;
            height: 60px;
            margin-bottom: 5px;
        }
        @media print {
            body { -webkit-print-color-adjust: exact; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="header mb-4">
            <h4>PURCHASE ORDER</h4>
            <p>PT Lautan Teduh Interniaga</p> {{-- Ganti dengan nama perusahaan Anda --}}
            <p>Jl. Ikan Tenggiri No.24, Pesawahan, Teluk Betung Selatan, Bandar Lampung</p> {{-- Ganti dengan alamat perusahaan --}}
        </div>
        <hr style="border-top: 2px solid #000;">

        <div class="row">
            <div class="col-6">
                <strong>Kepada Yth:</strong><br>
                {{ $purchaseOrder->supplier->nama_supplier }}<br>
                {{ $purchaseOrder->supplier->alamat ?? 'Alamat tidak tersedia' }}
            </div>
            <div class="col-6 text-right">
                <strong>Nomor PO:</strong> {{ $purchaseOrder->nomor_po }}<br>
                <strong>Tanggal:</strong> {{ $purchaseOrder->tanggal_po->format('d F Y') }}
            </div>
        </div>

        <p class="mt-4">Mohon disediakan barang-barang berikut ini:</p>

        <table class="table table-bordered mt-3">
            <thead class="bg-light">
                <tr class="text-center">
                    <th>No.</th>
                    <th>Kode Part</th>
                    <th>Nama Part</th>
                    <th>Qty</th>
                    <th>Harga Satuan</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($purchaseOrder->details as $index => $detail)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $detail->part->kode_part }}</td>
                    <td>{{ $detail->part->nama_part }}</td>
                    <td class="text-center">{{ $detail->qty_pesan }}</td>
                    <td class="text-right">Rp {{ number_format($detail->harga_beli, 0, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format($detail->subtotal, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="5" class="text-right">Subtotal</th>
                    <td class="text-right">Rp {{ number_format($purchaseOrder->subtotal, 0, ',', '.') }}</td>
                </tr>
                 <tr>
                    <th colspan="5" class="text-right">PPN (11%)</th>
                    <td class="text-right">Rp {{ number_format($purchaseOrder->pajak, 0, ',', '.') }}</td>
                </tr>
                 <tr>
                    <th colspan="5" class="text-right font-weight-bold">Grand Total</th>
                    <td class="text-right font-weight-bold">Rp {{ number_format($purchaseOrder->total_amount, 0, ',', '.') }}</td>
                </tr>
            </tfoot>
        </table>

        <div class="row signature-area">
            <div class="col-4">
                <div class="signature-box">
                    Dibuat Oleh,
                    <div class="signature-line"></div>
                    <div class="signature-name">({{ $purchaseOrder->createdBy->nama ?? 'N/A' }})</div>
                </div>
            </div>
            <div class="col-4">
                <div class="signature-box">
                    Disetujui Oleh,
                    <div class="signature-line"></div>
                    <div class="signature-name">({{ $purchaseOrder->approvedBy->nama ?? '(_________________)' }})</div>
                </div>
            </div>
             <div class="col-4">
                <div class="signature-box">
                    Hormat Kami,
                    <div class="signature-line"></div>
                    <div class="signature-name">(_________________)</div>
                </div>
            </div>
        </div>

    </div>

    <script>
        // Otomatis memicu dialog cetak saat halaman dimuat
        window.onload = function() {
            window.print();
        }
    </script>
</body>
</html>
