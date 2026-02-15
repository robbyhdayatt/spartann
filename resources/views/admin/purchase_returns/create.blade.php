@extends('adminlte::page')

@section('title', 'Buat Retur Pembelian')

@section('content_header')
    <h1><i class="fas fa-undo text-danger mr-2"></i>Buat Retur Pembelian</h1>
@stop

@section('content')
<div class="row">
    <div class="col-12">
        
        {{-- Alert Errors --}}
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

        <div class="card card-outline card-danger">
            <div class="card-header">
                <h3 class="card-title">Form Pengajuan Retur</h3>
            </div>
            
            <form action="{{ route('admin.purchase-returns.store') }}" method="POST">
                @csrf
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="receiving_id">Pilih Dokumen Penerimaan (Receiving)</label>
                                <select name="receiving_id" id="receiving_id" class="form-control select2" style="width: 100%;" required>
                                    <option value="" selected disabled>-- Cari Nomor Penerimaan --</option>
                                    @foreach($receivings as $recv)
                                        <option value="{{ $recv->id }}" {{ old('receiving_id') == $recv->id ? 'selected' : '' }}>
                                            {{ $recv->nomor_penerimaan }} - {{ $recv->supplier->nama_supplier ?? 'No Supplier' }} ({{ \Carbon\Carbon::parse($recv->tanggal_terima)->format('d/m/Y') }})
                                        </option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">
                                    Hanya menampilkan penerimaan yang memiliki item <b>Gagal QC</b> yang belum diretur.
                                </small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="tanggal_retur">Tanggal Retur</label>
                                <input type="date" name="tanggal_retur" class="form-control" value="{{ old('tanggal_retur', date('Y-m-d')) }}" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Catatan Tambahan</label>
                        <textarea name="catatan" class="form-control" rows="2" placeholder="Masukkan alasan umum retur...">{{ old('catatan') }}</textarea>
                    </div>

                    <hr>
                    <h5 class="text-danger mb-3"><i class="fas fa-boxes mr-2"></i>Daftar Barang Gagal QC</h5>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="bg-light">
                                <tr>
                                    <th width="30%">Nama Barang</th>
                                    <th width="15%" class="text-center">Gagal QC</th>
                                    <th width="15%" class="text-center">Sisa (Bisa Retur)</th>
                                    <th width="20%">Jml. Retur Sekarang</th>
                                    <th width="20%">Alasan Spesifik</th>
                                </tr>
                            </thead>
                            <tbody id="items-container">
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        Silakan pilih dokumen penerimaan terlebih dahulu.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card-footer bg-white text-right">
                    <a href="{{ route('admin.purchase-returns.index') }}" class="btn btn-default mr-2">Batal</a>
                    <button type="submit" class="btn btn-danger" id="btn-submit" disabled>
                        <i class="fas fa-save mr-1"></i> Simpan Retur
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@stop

@section('plugins.Select2', true)

@section('js')
<script>
$(document).ready(function() {
    $('.select2').select2({ theme: 'bootstrap4' });

    $('#receiving_id').on('change', function() {
        let receivingId = $(this).val();
        let container = $('#items-container');
        let btnSubmit = $('#btn-submit');

        if(!receivingId) {
            container.html('<tr><td colspan="5" class="text-center text-muted">Pilih dokumen penerimaan.</td></tr>');
            btnSubmit.prop('disabled', true);
            return;
        }

        container.html('<tr><td colspan="5" class="text-center py-4"><i class="fas fa-spinner fa-spin mr-2"></i>Mengambil data barang...</td></tr>');

        $.ajax({
            url: "{{ url('admin/admin/purchase-returns') }}/" + receivingId + "/failed-items", // Sesuaikan URL jika perlu (karena route Anda pakai resource)
            // Atau lebih aman gunakan endpoint khusus di controller sebelumnya: 
            // url: "/admin/purchase-returns/api/failed-items/" + receivingId, 
            // Tapi karena di controller saya buat 'getFailedItems', kita pakai route helper manual di blade:
            url: "{{ url('admin/receivings') }}/" + receivingId + "/failed-items-api", // Kita perlu define route ini jika belum ada, atau pakai method yang ada.
            // Sesuai kode controller Anda sebelumnya, methodnya 'getFailedItems' tapi routenya blm didefinisikan di web.php. 
            // Mari kita asumsikan routenya: Route::get('receivings/{receiving}/failed-items', ...);
            
            // REVISI URL AJAX AGAR SESUAI ROUTE YANG UMUM
            url: "{{ url('admin/api/receivings') }}/" + receivingId + "/failed-items", 
            
            type: "GET",
            success: function(response) {
                container.empty();
                if(response.length === 0) {
                    container.html('<tr><td colspan="5" class="text-center text-success font-weight-bold">Semua barang gagal QC pada dokumen ini sudah diretur.</td></tr>');
                    btnSubmit.prop('disabled', true);
                } else {
                    response.forEach(function(item, index) {
                        let html = `
                            <tr>
                                <td>
                                    <strong>${item.barang.part_name}</strong><br>
                                    <small class="text-muted">${item.barang.part_code}</small>
                                    <input type="hidden" name="items[${index}][receiving_detail_id]" value="${item.id}">
                                </td>
                                <td class="text-center text-danger font-weight-bold">${item.qty_gagal_qc}</td>
                                <td class="text-center">${item.sisa_retur}</td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <input type="number" name="items[${index}][qty_retur]" 
                                            class="form-control text-center" 
                                            min="0" max="${item.sisa_retur}" value="0" required>
                                        <div class="input-group-append">
                                            <span class="input-group-text">Unit</span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <input type="text" name="items[${index}][alasan]" class="form-control form-control-sm" placeholder="Contoh: Pecah/Rusak">
                                </td>
                            </tr>
                        `;
                        container.append(html);
                    });
                    btnSubmit.prop('disabled', false);
                }
            },
            error: function(xhr) {
                container.html('<tr><td colspan="5" class="text-center text-danger">Gagal mengambil data. Silakan coba lagi.</td></tr>');
                console.error(xhr);
            }
        });
    });
});
</script>
@stop