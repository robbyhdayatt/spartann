@extends('adminlte::page')

@section('title', 'Buat Retur Pembelian')
@section('plugins.Select2', true)

@section('content_header')
    <div class="row mb-2">
        <div class="col-sm-6">
            <h1><i class="fas fa-undo text-danger mr-2"></i>Buat Retur Pembelian</h1>
        </div>
    </div>
@stop

@section('content')
<div class="row justify-content-center">
    <div class="col-md-12">
        
        @if($errors->any())
            <x-adminlte-alert theme="danger" title="Terdapat Kesalahan!" dismissable>
                <ul class="mb-0 pl-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-adminlte-alert>
        @endif

        @if(session('error'))
            <x-adminlte-alert theme="danger" title="Gagal" dismissable>
                {{ session('error') }}
            </x-adminlte-alert>
        @endif

        <div class="card card-outline card-danger shadow-sm">
            <div class="card-header">
                <h3 class="card-title">Form Pengajuan Retur Barang (Karantina)</h3>
            </div>
            
            <form action="{{ route('admin.purchase-returns.store') }}" method="POST">
                @csrf
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="receiving_id">Pilih Dokumen Penerimaan <span class="text-danger">*</span></label>
                                <select name="receiving_id" id="receiving_id" class="form-control select2" style="width: 100%;" required>
                                    <option value="" selected disabled>-- Cari Nomor Penerimaan --</option>
                                    @foreach($receivings as $recv)
                                        <option value="{{ $recv->id }}" {{ old('receiving_id') == $recv->id ? 'selected' : '' }}>
                                            {{ $recv->nomor_penerimaan }} | 
                                            {{ $recv->purchaseOrder->supplier->nama_supplier ?? 'Internal' }} |
                                            {{ \Carbon\Carbon::parse($recv->tanggal_terima)->format('d/m/Y') }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">
                                    <i class="fas fa-info-circle"></i> Hanya menampilkan penerimaan yang memiliki item <b>Gagal QC</b> yang belum diretur.
                                </small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="tanggal_retur">Tanggal Retur <span class="text-danger">*</span></label>
                                <input type="date" name="tanggal_retur" class="form-control" 
                                       value="{{ old('tanggal_retur', date('Y-m-d')) }}" 
                                       max="{{ date('Y-m-d') }}" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Catatan Tambahan</label>
                        <textarea name="catatan" class="form-control" rows="2" placeholder="Contoh: Barang cacat produksi, dikembalikan ke supplier...">{{ old('catatan') }}</textarea>
                    </div>

                    <div class="card card-light border mt-4">
                        <div class="card-header">
                            <h3 class="card-title text-danger"><i class="fas fa-boxes mr-2"></i>Daftar Barang Gagal QC</h3>
                        </div>
                        <div class="card-body p-0 table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="bg-gradient-light">
                                    <tr>
                                        <th width="35%">Barang</th>
                                        <th width="10%" class="text-center text-danger">Gagal QC</th>
                                        <th width="10%" class="text-center">Sisa Retur</th>
                                        <th width="20%" class="text-center bg-warning">Jml. Retur</th>
                                        <th width="25%">Alasan Spesifik</th>
                                    </tr>
                                </thead>
                                <tbody id="items-container">
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-5">
                                            <i class="fas fa-search fa-3x mb-3 text-gray"></i><br>
                                            Silakan pilih dokumen penerimaan terlebih dahulu.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card-footer text-right">
                    <a href="{{ route('admin.purchase-returns.index') }}" class="btn btn-secondary mr-2">
                        <i class="fas fa-times mr-1"></i> Batal
                    </a>
                    <button type="submit" class="btn btn-danger" id="btn-submit" disabled>
                        <i class="fas fa-paper-plane mr-1"></i> Simpan Retur
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@stop

@section('js')
<script>
$(document).ready(function() {
    $('.select2').select2({ theme: 'bootstrap4' });

    $('#receiving_id').on('change', function() {
        let receivingId = $(this).val();
        let container = $('#items-container');
        let btnSubmit = $('#btn-submit');

        if(!receivingId) {
            btnSubmit.prop('disabled', true);
            return;
        }

        container.html('<tr><td colspan="5" class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-danger"></i><br>Mengambil data barang...</td></tr>');

        // Gunakan route API yang kita buat manual via controller
        // Pastikan route ini terdaftar: Route::get('receivings/{receiving}/failed-items', [PurchaseReturnController::class, 'getFailedItems']);
        // Jika belum, sesuaikan URL dibawah ini ke endpoint yang valid.
        $.ajax({
            url: "{{ url('admin/purchase-returns/get-failed-items') }}/" + receivingId, 
            type: "GET",
            success: function(response) {
                container.empty();
                if(response.length === 0) {
                    container.html('<tr><td colspan="5" class="text-center text-success font-weight-bold py-4"><i class="fas fa-check-circle mr-1"></i> Semua barang gagal QC pada dokumen ini sudah diretur.</td></tr>');
                    btnSubmit.prop('disabled', true);
                } else {
                    response.forEach(function(item, index) {
                        let html = `
                            <tr>
                                <td class="align-middle">
                                    <span class="font-weight-bold" style="font-size: 1.1em">${item.barang.part_name}</span><br>
                                    <small class="text-muted"><i class="fas fa-barcode mr-1"></i> ${item.barang.part_code}</small>
                                    <input type="hidden" name="items[${index}][receiving_detail_id]" value="${item.id}">
                                </td>
                                <td class="text-center align-middle text-danger font-weight-bold">${item.qty_gagal_qc}</td>
                                <td class="text-center align-middle font-weight-bold" style="font-size: 1.1em">${item.sisa_retur}</td>
                                <td class="align-middle">
                                    <div class="input-group input-group-sm">
                                        <input type="number" name="items[${index}][qty_retur]" 
                                            class="form-control text-center font-weight-bold qty-input" 
                                            min="0" max="${item.sisa_retur}" value="0" required>
                                    </div>
                                </td>
                                <td class="align-middle">
                                    <input type="text" name="items[${index}][alasan]" class="form-control form-control-sm" placeholder="Contoh: Rusak/Cacat...">
                                </td>
                            </tr>
                        `;
                        container.append(html);
                    });
                    
                    // Auto select input pertama
                    container.find('.qty-input').first().select();
                    btnSubmit.prop('disabled', false);
                }
            },
            error: function(xhr) {
                container.html('<tr><td colspan="5" class="text-center text-danger py-4"><i class="fas fa-exclamation-triangle mr-1"></i> Gagal mengambil data. Silakan coba lagi.<br><small>' + xhr.statusText + '</small></td></tr>');
                console.error(xhr);
            }
        });
    });
});
</script>
@stop