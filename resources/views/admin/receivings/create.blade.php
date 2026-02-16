@extends('adminlte::page')

@section('title', 'Catat Penerimaan Barang')
@section('plugins.Select2', true)

@section('content_header')
    <div class="row mb-2">
        <div class="col-sm-6">
            <h1><i class="fas fa-truck-loading text-success mr-2"></i> Penerimaan Barang</h1>
        </div>  
    </div>
@stop

@section('content')
<div class="row justify-content-center">
    <div class="col-md-12">
        
        {{-- MODIFIKASI POIN 2: Progress Bar --}}
        <div class="card mb-3 shadow-sm">
            <div class="card-body p-3">
                <div class="progress" style="height: 25px;">
                    <div class="progress-bar bg-success progress-bar-striped progress-bar-animated font-weight-bold" style="width: 33%">1. Receiving (Aktif)</div>
                    <div class="progress-bar bg-secondary" style="width: 33%">2. Quality Control</div>
                    <div class="progress-bar bg-secondary" style="width: 34%">3. Putaway</div>
                </div>
            </div>
        </div>

        @if(session('error'))
            <x-adminlte-alert theme="danger" title="Gagal" dismissable>{{ session('error') }}</x-adminlte-alert>
        @endif

        <div class="card card-outline card-success shadow-sm">
            <div class="card-header">
                <h3 class="card-title">Form Input Penerimaan (Inbound)</h3>
            </div>
            <form action="{{ route('admin.receivings.store') }}" method="POST">
                @csrf
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Nomor PO <span class="text-danger">*</span></label>
                                <select name="purchase_order_id" id="po_select" class="form-control select2" required style="width: 100%;">
                                    <option value="" selected disabled>-- Pilih PO (Status Approved) --</option>
                                    @foreach($purchaseOrders as $po)
                                        <option value="{{ $po->id }}">
                                            {{ $po->nomor_po }} | {{ $po->supplier->nama_supplier ?? $po->sumberLokasi->nama_lokasi }} 
                                        </option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">Hanya menampilkan PO yang sudah disetujui.</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Tanggal Terima <span class="text-danger">*</span></label>
                                <input type="date" name="tanggal_terima" class="form-control" value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Catatan / Keterangan</label>
                        <textarea name="catatan" class="form-control" rows="2" placeholder="Contoh: Barang diterima lengkap, dus sedikit penyok..."></textarea>
                    </div>

                    <div class="card card-light border">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-boxes"></i> Daftar Item PO</h3>
                        </div>
                        <div class="card-body p-0 table-responsive">
                            <table class="table table-striped table-hover mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th style="width: 15%">Kode Part</th>
                                        <th style="width: 35%">Nama Barang</th>
                                        <th class="text-center" style="width: 10%">Total Pesan</th>
                                        <th class="text-center" style="width: 10%">Sudah Masuk</th>
                                        <th class="text-center" style="width: 10%">Sisa</th>
                                        <th class="text-center bg-warning" style="width: 20%">Qty Diterima (Sekarang)</th>
                                    </tr>
                                </thead>
                                <tbody id="items_table">
                                    <tr><td colspan="6" class="text-center text-muted py-4">Silakan pilih Nomor PO terlebih dahulu untuk memuat item.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="card-footer text-right">
                    <a href="{{ route('admin.receivings.index') }}" class="btn btn-default mr-2">
                        <i class="fas fa-times"></i> Batal
                    </a>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Simpan Penerimaan
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
    // Init Select2
    $('.select2').select2({ theme: 'bootstrap4' });

    $('#po_select').on('change', function() {
        let poId = $(this).val();
        let table = $('#items_table');
        
        table.html('<tr><td colspan="6" class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-success"></i><br>Sedang memuat data PO...</td></tr>');

        $.get("{{ url('admin/api/purchase-orders') }}/" + poId + "/details", function(data) {
            table.empty();
            
            if (data.length === 0) {
                table.html('<tr><td colspan="6" class="text-center text-success font-weight-bold py-3"><i class="fas fa-check-circle"></i> PO ini sudah diterima sepenuhnya (Fully Received).</td></tr>');
            } else {
                data.forEach(function(item) {
                    let sisa = item.qty_pesan - item.qty_diterima;
                    // Skip jika sisa 0
                    if(sisa <= 0) return;

                    let row = `
                        <tr>
                            <td class="align-middle font-weight-bold text-secondary">${item.barang.part_code}</td>
                            <td class="align-middle">${item.barang.part_name}</td>
                            <td class="text-center align-middle">${item.qty_pesan}</td>
                            <td class="text-center align-middle">${item.qty_diterima}</td>
                            <td class="text-center align-middle font-weight-bold text-danger">${sisa}</td>
                            <td>
                                <div class="input-group">
                                    <input type="number" name="items[${item.barang_id}][qty_terima]" 
                                        class="form-control text-center font-weight-bold input-qty" 
                                        min="0" max="${sisa}" value="${sisa}" required>
                                    <input type="hidden" name="items[${item.barang_id}][barang_id]" value="${item.barang_id}">
                                </div>
                            </td>
                        </tr>
                    `;
                    table.append(row);
                });

                // Focus ke input pertama
                $('.input-qty').first().select();
            }
        }).fail(function() {
            table.html('<tr><td colspan="6" class="text-center text-danger">Gagal mengambil data. Silakan coba lagi.</td></tr>');
        });
    });
});
</script>
@stop