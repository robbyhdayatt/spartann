@extends('adminlte::page')

@section('title', 'Penerimaan Barang')
@section('plugins.Select2', true)

@section('content_header')
    <h1><i class="fas fa-truck-loading mr-2"></i> Penerimaan Barang (Receiving)</h1>
@stop

@section('content')
<div class="row justify-content-center">
    <div class="col-md-12">
        
        @if(session('error'))
            <x-adminlte-alert theme="danger" title="Gagal" dismissable>{{ session('error') }}</x-adminlte-alert>
        @endif

        <div class="card card-outline card-success">
            <form action="{{ route('admin.receivings.store') }}" method="POST">
                @csrf
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Nomor PO (Status: Approved/Partial)</label>
                                <select name="purchase_order_id" id="po_select" class="form-control select2" required>
                                    <option value="" selected disabled>-- Pilih PO --</option>
                                    @foreach($purchaseOrders as $po)
                                        <option value="{{ $po->id }}">
                                            {{ $po->nomor_po }} - {{ $po->supplier->nama_supplier ?? 'Internal Transfer' }} 
                                            ({{ $po->status }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Tanggal Terima</label>
                                <input type="date" name="tanggal_terima" class="form-control" value="{{ date('Y-m-d') }}" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Catatan</label>
                        <textarea name="catatan" class="form-control" rows="2" placeholder="Kondisi barang saat diterima..."></textarea>
                    </div>

                    <div class="table-responsive mt-3">
                        <table class="table table-bordered table-striped">
                            <thead class="bg-success text-white">
                                <tr>
                                    <th>Kode Part</th>
                                    <th>Nama Barang</th>
                                    <th class="text-center">Total Pesan</th>
                                    <th class="text-center">Sudah Diterima</th>
                                    <th class="text-center">Sisa</th>
                                    <th class="text-center" width="20%">Terima Sekarang</th>
                                </tr>
                            </thead>
                            <tbody id="items_table">
                                <tr><td colspan="6" class="text-center text-muted">Pilih PO terlebih dahulu...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer text-right">
                    <a href="{{ route('admin.receivings.index') }}" class="btn btn-secondary">Batal</a>
                    <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Simpan Penerimaan</button>
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

    $('#po_select').on('change', function() {
        let poId = $(this).val();
        let table = $('#items_table');
        
        table.html('<tr><td colspan="6" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>');

        $.get("{{ url('admin/api/purchase-orders') }}/" + poId + "/details", function(data) {
            table.empty();
            if (data.length === 0) {
                table.html('<tr><td colspan="6" class="text-center text-success font-weight-bold">PO ini sudah diterima sepenuhnya (Fully Received).</td></tr>');
            } else {
                data.forEach(function(item) {
                    let sisa = item.qty_pesan - item.qty_diterima;
                    let row = `
                        <tr>
                            <td>${item.barang.part_code}</td>
                            <td>${item.barang.part_name}</td>
                            <td class="text-center">${item.qty_pesan}</td>
                            <td class="text-center">${item.qty_diterima}</td>
                            <td class="text-center font-weight-bold text-danger">${sisa}</td>
                            <td>
                                <input type="number" name="items[${item.barang_id}][qty_terima]" 
                                    class="form-control text-center font-weight-bold" 
                                    min="0" max="${sisa}" value="${sisa}" required>
                                <input type="hidden" name="items[${item.barang_id}][barang_id]" value="${item.barang_id}">
                            </td>
                        </tr>
                    `;
                    table.append(row);
                });
            }
        });
    });
});
</script>
@stop