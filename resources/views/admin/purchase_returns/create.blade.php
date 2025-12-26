@extends('adminlte::page')
@section('title', 'Buat Retur Pembelian')
@section('plugins.Select2', true)

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1><i class="fas fa-undo-alt text-danger mr-2"></i> Buat Retur Pembelian</h1>
    </div>
@stop

@section('content')
<div class="row justify-content-center">
    <div class="col-12">
        <div class="card card-outline card-danger shadow-sm">
            <form action="{{ route('admin.purchase-returns.store') }}" method="POST" id="return-form">
                @csrf
                <div class="card-body">
                    {{-- Error Blocks --}}
                    @if (session('error'))
                        <div class="alert alert-danger alert-dismissible">
                            <button type="button" class="close" data-dismiss="alert">×</button>
                            {{ session('error') }}
                        </div>
                    @endif
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <p><strong>Terjadi kesalahan:</strong></p>
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    @if (!preg_match('/^items\.\d+\..*/', $error)) <li>{{ $error }}</li> @endif
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Pilih Dokumen Penerimaan <span class="text-danger">*</span></label>
                            <select id="receiving-select" name="receiving_id" class="form-control select2" required style="width: 100%;">
                                <option value="" disabled selected>--- Pilih Penerimaan ---</option>
                                @foreach ($receivings as $receiving)
                                    <option value="{{ $receiving->id }}" {{ old('receiving_id') == $receiving->id ? 'selected' : '' }}>
                                        {{ $receiving->nomor_penerimaan }} - 
                                        {{ $receiving->purchaseOrder->supplier->nama_supplier ?? 'N/A' }} 
                                        ({{ \Carbon\Carbon::parse($receiving->tanggal_terima)->format('d/m/Y') }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Tanggal Retur <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="tanggal_retur" value="{{ old('tanggal_retur', now()->format('Y-m-d')) }}" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Catatan / Alasan Umum</label>
                        <textarea name="catatan" class="form-control" rows="2" placeholder="Contoh: Barang cacat produksi massal...">{{ old('catatan') }}</textarea>
                    </div>

                    <div class="alert alert-light border-left-danger mt-4">
                        <i class="fas fa-info-circle text-danger mr-1"></i>
                        Hanya item yang <strong>Gagal QC</strong> dan belum diretur yang akan tampil di bawah ini.
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover v-middle">
                            <thead class="thead-dark">
                                <tr>
                                    <th style="width: 35%">Barang</th>
                                    <th style="width: 15%" class="text-center">Gagal QC</th>
                                    <th style="width: 20%" class="text-center">Qty Diretur <span class="text-danger">*</span></th>
                                    <th style="width: 30%">Alasan Retur (Opsional)</th>
                                </tr>
                            </thead>
                            <tbody id="return-items-table">
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">Pilih dokumen penerimaan terlebih dahulu.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-light d-flex justify-content-between">
                    <a href="{{ route('admin.purchase-returns.index') }}" class="btn btn-default"><i class="fas fa-arrow-left mr-1"></i> Batal</a>
                    <button type="submit" class="btn btn-danger px-4" id="submit-button" disabled>
                        <i class="fas fa-file-export mr-1"></i> Simpan Dokumen Retur
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@stop

@push('css')
<style>
    .border-left-danger { border-left: 4px solid #dc3545; }
    .table td { vertical-align: middle; }
    .qty-retur { font-size: 1.1rem; }
    .select2-container .select2-selection--single { height: 38px !important; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 28px !important; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px !important; }
</style>
@endpush

@section('js')
<script>
$(document).ready(function() {
    $('.select2').select2({ theme: 'bootstrap4' });

    // --- UX: Auto-clear on focus ---
    $('#return-items-table').on('focus', '.qty-retur', function() {
        if ($(this).val() == 0) $(this).val('');
        else $(this).select();
    });
    $('#return-items-table').on('blur', '.qty-retur', function() {
        if ($(this).val() === '') $(this).val(0);
    });
    // -------------------------------

    const validationErrors = @json($errors->toArray());
    const oldItems = @json(old('items', []));
    const receivingSelect = $('#receiving-select');
    const tableBody = $('#return-items-table');
    const submitButton = $('#submit-button');

    function loadItems(receivingId) {
        tableBody.html('<tr><td colspan="4" class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-danger"></i><br>Memuat data...</td></tr>');
        submitButton.prop('disabled', true);

        if (!receivingId) {
            tableBody.html('<tr><td colspan="4" class="text-center text-muted py-4">Pilih dokumen penerimaan terlebih dahulu.</td></tr>');
            return;
        }

        let url = "{{ route('admin.api.receivings.failed-items', ['receiving' => ':id']) }}".replace(':id', receivingId);

        $.getJSON(url).done(function(data) {
            tableBody.empty();
            if (data && data.length > 0) {
                let hasItems = false;
                data.forEach(function(item) {
                    let available = parseInt(item.qty_gagal_qc) - parseInt(item.qty_diretur || 0);
                    if (available > 0) {
                        hasItems = true;
                        // Logika nilai lama/error (disederhanakan untuk ringkas)
                        let defQty = oldItems[item.id] ? oldItems[item.id]['qty_retur'] : available;
                        let defNote = oldItems[item.id] ? oldItems[item.id]['alasan'] : (item.catatan_qc || '');

                        let row = `
                            <tr>
                                <td>
                                    <span class="font-weight-bold d-block">${item.barang.part_name}</span>
                                    <span class="text-muted small">${item.barang.part_code}</span>
                                    <input type="hidden" name="items[${item.id}][receiving_detail_id]" value="${item.id}">
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-danger px-3">${available}</span>
                                </td>
                                <td>
                                    <div class="input-group">
                                        <input type="number" name="items[${item.id}][qty_retur]"
                                               class="form-control text-center font-weight-bold qty-retur text-danger"
                                               min="0" max="${available}" value="${defQty}" required>
                                    </div>
                                </td>
                                <td>
                                    <input type="text" name="items[${item.id}][alasan]" class="form-control form-control-sm" value="${defNote}" placeholder="Alasan...">
                                </td>
                            </tr>`;
                        tableBody.append(row);
                    }
                });

                if (!hasItems) {
                    tableBody.html('<tr><td colspan="4" class="text-center text-info py-4"><i class="fas fa-check-double fa-lg mb-2"></i><br>Semua item gagal QC sudah diretur.</td></tr>');
                    submitButton.prop('disabled', true);
                } else {
                    submitButton.prop('disabled', false);
                }
            } else {
                tableBody.html('<tr><td colspan="4" class="text-center text-muted py-4">Tidak ada item Gagal QC yang tersedia.</td></tr>');
            }
        });
    }

    receivingSelect.on('select2:select', function(e) { loadItems(e.params.data.id); });
    let initId = "{{ old('receiving_id') }}";
    if (initId) loadItems(initId);
});
</script>
@stop