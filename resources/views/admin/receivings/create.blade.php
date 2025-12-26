@extends('adminlte::page')

@section('title', 'Catat Penerimaan Barang')
@section('plugins.Select2', true)

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1><i class="fas fa-truck-loading text-success mr-2"></i> Catat Penerimaan Barang</h1>
    </div>
@stop

@section('content')
<div class="row justify-content-center">
    <div class="col-12">
        
        {{-- CEK ROLE USER --}}
        @php
            // Cek apakah user adalah Dealer (PC, KC, KSR, AD)
            // Sesuaikan dengan AuthServiceProvider yang sudah kita buat
            $isDealer = auth()->user()->hasRole(['PC', 'KC', 'KSR', 'AD']);
        @endphp

        {{-- Progress Wizard --}}
        <div class="card mb-3">
            <div class="card-body p-3">
                <div class="progress" style="height: 25px;">
                    @if($isDealer)
                        {{-- TAMPILAN DEALER / PART COUNTER (2 TAHAP) --}}
                        {{-- QC Dihilangkan secara visual --}}
                        <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" role="progressbar" style="width: 50%">
                            <strong>1. Receiving (Sedang Proses)</strong>
                        </div>
                        <div class="progress-bar bg-secondary" role="progressbar" style="width: 50%">
                            2. Putaway (Penyimpanan)
                        </div>
                    @else
                        {{-- TAMPILAN PUSAT (3 TAHAP - NORMAL) --}}
                        <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" role="progressbar" style="width: 33%">
                            1. Receiving (Sedang Proses)
                        </div>
                        <div class="progress-bar bg-secondary" role="progressbar" style="width: 34%">
                            2. Quality Control
                        </div>
                        <div class="progress-bar bg-secondary" role="progressbar" style="width: 33%">
                            3. Putaway
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="card card-outline card-success shadow-sm">
            {{-- ... Form di bawah ini tetap sama seperti sebelumnya ... --}}
            <form action="{{ route('admin.receivings.store') }}" method="POST">
                @csrf
                <div class="card-body">
                    @if ($errors->any())
                        <div class="alert alert-danger alert-dismissible">
                            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                            <h5><i class="icon fas fa-ban"></i> Error!</h5>
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @if(session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Pilih Purchase Order (PO)</label>
                            <select id="po-select" name="purchase_order_id" class="form-control select2" required style="width: 100%;">
                                <option value="" disabled selected>--- Pilih PO ---</option>
                                @forelse($purchaseOrders as $po)
                                    @php
                                        $sumber = $po->supplier ? $po->supplier->nama_supplier : ($po->sumberLokasi->nama_lokasi . ' (Internal)' ?? 'Internal');
                                    @endphp
                                    <option value="{{ $po->id }}">{{ $po->nomor_po }} - {{ $sumber }}</option>
                                @empty
                                    <option value="" disabled>Tidak ada PO pending.</option>
                                @endforelse
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Tanggal Terima</label>
                            <input type="date" class="form-control" name="tanggal_terima" value="{{ now()->format('Y-m-d') }}" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Catatan Penerimaan</label>
                        <textarea name="catatan" class="form-control" rows="2" placeholder="Contoh: Barang diterima oleh Satpam, kondisi dus penyok..."></textarea>
                    </div>

                    <div class="alert alert-light border-left-success mt-4">
                        <i class="fas fa-info-circle text-success mr-1"></i>
                        Masukkan jumlah barang fisik yang diterima di kolom <strong>Qty Diterima Saat Ini</strong>.
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover v-middle">
                            <thead class="thead-dark">
                                <tr>
                                    <th style="width: 35%">Barang</th>
                                    <th class="text-center" style="width: 15%">Qty Pesan</th>
                                    <th class="text-center" style="width: 15%">Sdh Diterima</th>
                                    <th class="text-center" style="width: 15%">Sisa</th>
                                    <th class="text-center" style="width: 20%">Qty Diterima Saat Ini</th>
                                </tr>
                            </thead>
                            <tbody id="receiving-items-table">
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        <i class="fas fa-search fa-2x mb-2"></i><br>Silakan pilih PO terlebih dahulu.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-light d-flex justify-content-between">
                    <a href="{{ route('admin.receivings.index') }}" class="btn btn-default"><i class="fas fa-arrow-left mr-1"></i> Batal</a>
                    <button type="submit" class="btn btn-success"><i class="fas fa-save mr-1"></i> Simpan Data Penerimaan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@stop

{{-- ... (Section CSS dan JS tetap sama, tidak perlu diubah) ... --}}
@push('css')
<style>
    .border-left-success { border-left: 4px solid #28a745; }
    .table td { vertical-align: middle !important; }
    .qty-input { font-size: 1.1rem; }
    
    /* Fix Select2 Height */
    .select2-container .select2-selection--single { height: 38px !important; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 28px !important; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px !important; }
</style>
@endpush

@section('js')
<script>
$(document).ready(function() {
    $('.select2').select2({ theme: 'bootstrap4', placeholder: "--- Pilih PO ---", allowClear: true });

    // --- UX: Auto-clear on focus (Delegated event karena elemen dinamis) ---
    $('#receiving-items-table').on('focus', '.qty-input', function() {
        if ($(this).val() == 0) $(this).val('');
        else $(this).select();
    });

    $('#receiving-items-table').on('blur', '.qty-input', function() {
        if ($(this).val() === '') $(this).val(0);
    });
    // -----------------------------------------------------------------------

    $('#po-select').on('change', function() {
        let poId = $(this).val();
        let tableBody = $('#receiving-items-table');
        let url = "{{ route('admin.api.po.details', ['purchaseOrder' => ':poId']) }}".replace(':poId', poId);

        tableBody.html('<tr><td colspan="5" class="text-center py-4"><i class="fa fa-spinner fa-spin fa-2x text-success"></i><br>Memuat detail item...</td></tr>');

        if (poId) {
            $.ajax({
                url: url, type: 'GET', dataType: 'json',
                success: function(response) {
                    tableBody.empty();
                    if(response && response.length > 0) {
                        response.forEach(function(item) {
                            let qty_sisa = item.qty_pesan - item.qty_diterima;
                            let namaBarang = item.barang ? item.barang.part_name : 'Unknown';
                            let kodeBarang = item.barang ? item.barang.part_code : '-';
                            let barangId   = item.barang_id;

                            let row = `
                                <tr>
                                    <td>
                                        <span class="font-weight-bold d-block">${namaBarang}</span>
                                        <span class="text-muted small">${kodeBarang}</span>
                                        <input type="hidden" name="items[${barangId}][barang_id]" value="${barangId}">
                                    </td>
                                    <td class="text-center"><span class="badge badge-light border px-2">${item.qty_pesan}</span></td>
                                    <td class="text-center"><span class="badge badge-info px-2">${item.qty_diterima}</span></td>
                                    <td class="text-center"><span class="badge badge-warning px-2">${qty_sisa}</span></td>
                                    <td>
                                        <div class="input-group">
                                            <input type="number" name="items[${barangId}][qty_terima]"
                                               class="form-control text-center font-weight-bold qty-input text-success"
                                               min="0" max="${qty_sisa}" value="${qty_sisa}" required>
                                            <div class="input-group-append">
                                                <span class="input-group-text">Pcs</span>
                                            </div>
                                        </div>
                                    </td>
                                </tr>`;
                            tableBody.append(row);
                        });
                    } else {
                        tableBody.html('<tr><td colspan="5" class="text-center text-success font-weight-bold py-3"><i class="fas fa-check-circle mr-1"></i> Semua item PO ini sudah diterima sepenuhnya.</td></tr>');
                    }
                },
                error: function() {
                    tableBody.html('<tr><td colspan="5" class="text-center text-danger">Gagal memuat data. Silakan coba lagi.</td></tr>');
                }
            });
        } else {
            tableBody.html('<tr><td colspan="5" class="text-center text-muted py-4">Pilih PO untuk menampilkan item.</td></tr>');
        }
    });
});
</script>
@stop