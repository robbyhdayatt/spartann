<div class="invoice p-3 mb-3">
    {{-- Header Invoice & Info Pengirim/Penerima (TETAP SAMA SEPERTI SEBELUMNYA) --}}
    <div class="row">
        <div class="col-12">
            <h4>
                <i class="fas fa-globe text-primary"></i> PT. Lautan Teduh Interniaga
                <small class="float-right">Date: {{ $purchaseOrder->tanggal_po->format('d/m/Y') }}</small>
            </h4>
        </div>
    </div>

    <div class="row invoice-info mt-4">
        {{-- ... (Bagian Alamat biarkan kode yang terakhir saya berikan) ... --}}
        <div class="col-sm-4 invoice-col">
            <strong>Diterbitkan Oleh / Dikirim Ke:</strong>
            <address>
                <strong>{{ $purchaseOrder->lokasi->nama_lokasi ?? 'Gudang/Dealer' }}</strong><br>
                {{ $purchaseOrder->lokasi->alamat ?? 'Alamat Cabang/Gudang' }}<br>
            </address>
        </div>
        <div class="col-sm-4 invoice-col">
            <strong>Kepada Yth (Supplier / Sumber):</strong>
            <address>
                <strong>
                    @if($purchaseOrder->po_type == 'supplier_po') {{ $purchaseOrder->supplier->nama_supplier }} 
                    @else {{ $purchaseOrder->sumberLokasi->nama_lokasi }} @endif
                </strong><br>
                @if($purchaseOrder->po_type == 'supplier_po') {{ $purchaseOrder->supplier->alamat ?? '' }} @else Gudang Pusat Distribusi @endif
            </address>
        </div>
        <div class="col-sm-4 invoice-col">
            <b>Nomor PO: #{{ $purchaseOrder->nomor_po }}</b><br>
            <b>Status:</b> <span class="badge {{ $purchaseOrder->status == 'APPROVED' ? 'badge-success' : ($purchaseOrder->status == 'REJECTED' ? 'badge-danger' : 'badge-warning') }}">{{ $purchaseOrder->status }}</span>
        </div>
    </div>

    {{-- ALERT INSTRUKSI --}}
    @if($purchaseOrder->po_type == 'dealer_request' && $purchaseOrder->status == 'PENDING_APPROVAL')
        @can('approve-po', $purchaseOrder)
        <div class="alert alert-info mt-3 shadow-sm">
            <h5><i class="icon fas fa-edit"></i> Penyesuaian Stok</h5>
            Silakan sesuaikan <strong>Qty Disetujui</strong> jika stok fisik tidak mencukupi. Sistem akan memotong stok sesuai angka yang Anda input.
        </div>
        @endcan
    @endif

    {{-- FORM WRAPPER (Hanya aktif jika Dealer Request & Pending & User Berhak Approve) --}}
    @php
        $isEditable = ($purchaseOrder->po_type == 'dealer_request' && $purchaseOrder->status == 'PENDING_APPROVAL' && auth()->user()->can('approve-po', $purchaseOrder));
    @endphp

    @if($isEditable)
    <form action="{{ route('admin.purchase-orders.approve', $purchaseOrder->id) }}" method="POST" id="approve-form">
        @csrf
    @endif

    {{-- Tabel Item --}}
    <div class="row mt-4">
        <div class="col-12 table-responsive">
            <table class="table table-striped table-bordered" id="items-table">
                <thead class="thead-light">
                <tr>
                    <th style="width: 25%">Nama Barang</th>
                    <th style="width: 15%">Kode Part</th>
                    
                    @if($purchaseOrder->po_type == 'dealer_request')
                        <th class="text-center" style="width: 10%">Qty Minta</th>
                        
                        {{-- Jika mode edit, tampilkan kolom input --}}
                        @if($isEditable)
                            <th class="text-center bg-warning text-dark" style="width: 15%">Qty Disetujui</th>
                        @else
                            <th class="text-center" style="width: 10%">Qty Disetujui</th>
                        @endif

                        <th class="text-center bg-white border-left" style="width: 10%">Stok Gudang</th>
                        <th class="text-center bg-white" style="width: 10%">Prediksi Sisa</th>
                        <th class="text-center bg-white" style="width: 10%">Status</th>
                    @else
                        <th class="text-center">Qty</th>
                    @endif

                    @if($purchaseOrder->po_type == 'supplier_po')
                        <th class="text-right">Harga Beli</th>
                        <th class="text-right">Subtotal</th>
                    @endif
                </tr>
                </thead>
                <tbody>
                @foreach($purchaseOrder->details as $detail)
                    @php
                        $stokGudang = $detail->stok_aktual_gudang ?? 0;
                        $stokMin = $detail->stok_minimum_barang ?? 0;
                        $qtyReq = $detail->qty_pesan;
                        
                        // Default sisa berdasarkan request awal
                        $sisaAwal = $stokGudang - $qtyReq;
                        $isSafeAwal = ($sisaAwal >= $stokMin && $stokGudang >= $qtyReq);
                        
                        $rowClass = '';
                        if($purchaseOrder->po_type == 'dealer_request' && !$isSafeAwal) {
                            $rowClass = 'table-danger'; // Merah jika awal tidak aman
                        }
                    @endphp

                <tr class="item-row {{ $rowClass }}" data-stok="{{ $stokGudang }}" data-min="{{ $stokMin }}">
                    <td>{{ $detail->barang->part_name }}</td>
                    <td>{{ $detail->barang->part_code }}</td>

                    @if($purchaseOrder->po_type == 'dealer_request')
                        {{-- Qty Minta (Readonly) --}}
                        <td class="text-center text-muted">{{ $detail->qty_pesan }}</td>

                        {{-- Qty Disetujui (Input / Text) --}}
                        <td class="text-center font-weight-bold">
                            @if($isEditable)
                                <input type="number" 
                                       name="qty_approved[{{ $detail->id }}]" 
                                       class="form-control text-center font-weight-bold qty-approve-input" 
                                       value="{{ $detail->qty_pesan }}" 
                                       min="0" 
                                       max="{{ $detail->qty_pesan }}" {{-- Tidak boleh lebih dari request --}}
                                       style="font-size: 1.1em;">
                            @else
                                {{ $detail->qty_pesan }}
                            @endif
                        </td>

                        <td class="text-center border-left font-weight-bold">{{ $stokGudang }}</td>
                        
                        <td class="text-center font-weight-bold sisa-prediksi">
                            {{ $sisaAwal }}
                        </td>
                        
                        <td class="text-center status-col">
                            @if($isSafeAwal)
                                <span class="badge badge-success">AMAN</span>
                            @else
                                <span class="badge badge-danger">KURANG</span>
                            @endif
                        </td>
                    @else
                        <td class="text-center">{{ $detail->qty_pesan }}</td>
                    @endif

                    @if($purchaseOrder->po_type == 'supplier_po')
                        <td class="text-right">Rp {{ number_format($detail->harga_beli, 0, ',', '.') }}</td>
                        <td class="text-right">Rp {{ number_format($detail->subtotal, 0, ',', '.') }}</td>
                    @endif
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Footer Total (Supplier PO Only) --}}
    @if($purchaseOrder->po_type == 'supplier_po')
        {{-- ... (Kode Total Amount tetap sama) ... --}}
    @endif
    
    {{-- Tanda Tangan (Tetap sama) --}}
    <div class="row mt-5">
        {{-- ... --}}
    </div>

    {{-- ACTION BUTTONS --}}
    <div class="row no-print mt-5 pt-3 border-top">
        <div class="col-12 text-right">
            @if($purchaseOrder->status == 'PENDING_APPROVAL')
                @can('approve-po', $purchaseOrder)
                    
                    <button type="button" class="btn btn-danger mr-2" data-toggle="modal" data-target="#rejectModal">
                        <i class="fas fa-times-circle mr-1"></i> Tolak Seluruhnya
                    </button>

                    {{-- Tombol Submit Form --}}
                    @if($isEditable)
                        <button type="submit" class="btn btn-success" id="btn-approve-submit" onclick="return confirm('Apakah Anda yakin? Stok akan dipotong sesuai jumlah yang Anda input (FIFO).')">
                            <i class="fas fa-check-circle mr-1"></i> Setujui & Potong Stok
                        </button>
                    @endif

                @endcan
            @endif
        </div>
    </div>

    @if($isEditable)
    </form> {{-- Tutup Form --}}
    @endif
</div>

{{-- SCRIPT VALIDASI REAL-TIME --}}
@if($isEditable)
<script>
document.addEventListener('DOMContentLoaded', function() {
    const inputs = document.querySelectorAll('.qty-approve-input');
    
    function validateRow(row) {
        const input = row.querySelector('.qty-approve-input');
        const stok = parseInt(row.getAttribute('data-stok'));
        const min = parseInt(row.getAttribute('data-min'));
        const sisaCell = row.querySelector('.sisa-prediksi');
        const statusCell = row.querySelector('.status-col');
        
        let qty = parseInt(input.value) || 0;
        let sisa = stok - qty;
        
        // Update teks sisa
        sisaCell.textContent = sisa;
        
        // Cek Logika Aman
        let isSafe = true;
        let statusHtml = '';

        if (qty > stok) {
            isSafe = false;
            statusHtml = '<span class="badge badge-danger">STOK KURANG</span>';
            input.classList.add('is-invalid');
        } else if (sisa < min) {
            // Peringatan tapi boleh lanjut jika urgent (atau blokir tergantung kebijakan)
            // Disini kita buat warning saja
            isSafe = true; 
            statusHtml = '<span class="badge badge-warning">MELEWATI MINIMUM</span>';
            input.classList.remove('is-invalid');
        } else {
            statusHtml = '<span class="badge badge-success">AMAN</span>';
            input.classList.remove('is-invalid');
        }

        statusCell.innerHTML = statusHtml;

        // Ubah warna baris
        if (!isSafe) {
            row.classList.add('table-danger');
            row.classList.remove('table-warning');
        } else if (sisa < min) {
            row.classList.remove('table-danger');
            row.classList.add('table-warning');
        } else {
            row.classList.remove('table-danger', 'table-warning');
        }

        return isSafe;
    }

    function validateAll() {
        let allSafe = true;
        inputs.forEach(input => {
            const row = input.closest('tr');
            if (!validateRow(row)) {
                allSafe = false;
            }
        });
        
        // Disable tombol jika ada yang stoknya benar-benar minus (qty > stok fisik)
        const btn = document.getElementById('btn-approve-submit');
        if(btn) {
            btn.disabled = !allSafe;
            if(!allSafe) {
                btn.innerHTML = '<i class="fas fa-ban"></i> Stok Fisik Kurang';
                btn.classList.add('btn-secondary');
                btn.classList.remove('btn-success');
            } else {
                btn.innerHTML = '<i class="fas fa-check-circle"></i> Setujui & Potong Stok';
                btn.classList.add('btn-success');
                btn.classList.remove('btn-secondary');
            }
        }
    }

    // Event Listeners
    inputs.forEach(input => {
        input.addEventListener('input', validateAll);
        input.addEventListener('change', validateAll);
        
        // UX: Auto select saat klik
        input.addEventListener('focus', function() {
            this.select();
        });
    });

    // Run once on load
    validateAll();
});
</script>
@endif