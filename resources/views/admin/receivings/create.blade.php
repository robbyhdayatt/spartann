@extends('adminlte::page')

@section('title', 'Catat Penerimaan Barang')

@section('plugins.Select2', true)

@section('content_header')
    <h1>Catat Penerimaan Barang</h1>
@stop

@section('content')
<div class="card">
    <form action="{{ route('admin.receivings.store') }}" method="POST">
        @csrf
        <div class="card-body">
            @if ($errors->any())
                <div class="alert alert-danger">
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
                    <select id="po-select" name="purchase_order_id" class="form-control select2" required>
                        <option value="" disabled selected>--- Pilih PO yang sudah disetujui ---</option>
                        @forelse($purchaseOrders as $po)
                        <option value="{{ $po->id }}">{{ $po->nomor_po }} - {{ $po->supplier->nama_supplier }}</option>
                        @empty
                        <option value="" disabled>Tidak ada PO yang siap diterima di lokasi Anda.</option>
                        @endforelse
                    </select>
                </div>
                <div class="col-md-6 form-group">
                    <label>Tanggal Terima</label>
                    <input type="date" class="form-control" name="tanggal_terima" value="{{ now()->format('Y-m-d') }}" required>
                </div>
            </div>
             <div class="form-group">
                <label>Catatan</label>
                <textarea name="catatan" class="form-control" rows="2"></textarea>
            </div>

            <h5 class="mt-4">Item Diterima</h5>
            <p class="text-muted">Masukkan jumlah barang yang diterima secara fisik. Sistem akan otomatis menampilkan item yang kuantitasnya belum terpenuhi.</p>

            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Part</th>
                        <th style="width: 15%">Qty Pesan</th>
                        <th style="width: 15%">Sudah Diterima</th>
                        <th style="width: 15%">Qty Sisa</th>
                        <th style="width: 15%">Qty Diterima Saat Ini</th>
                    </tr>
                </thead>
                <tbody id="receiving-items-table">
                    <tr>
                        <td colspan="5" class="text-center text-muted">Pilih PO untuk menampilkan item.</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-primary">Simpan Data Penerimaan</button>
            <a href="{{ route('admin.receivings.index') }}" class="btn btn-secondary">Batal</a>
        </div>
    </form>
</div>
@stop

@push('css')
<style>
    .select2-container .select2-selection--single {
        height: calc(2.25rem + 2px) !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 1.5 !important;
        padding-left: .75rem !important;
        padding-top: .375rem !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: calc(2.25rem + 2px) !important;
    }
</style>
@endpush

@section('js')
<script>
$(document).ready(function() {
    $('.select2').select2({
        placeholder: "--- Pilih PO yang sudah disetujui ---",
        allowClear: true
    });

    $('#po-select').on('change', function() {
        let poId = $(this).val();
        let tableBody = $('#receiving-items-table');
        let url = "{{ route('admin.api.po.details', ['purchaseOrder' => ':poId']) }}";
        url = url.replace(':poId', poId);

        tableBody.html('<tr><td colspan="5" class="text-center"><i class="fa fa-spinner fa-spin"></i> Memuat detail item...</td></tr>');

        if (poId) {
            $.ajax({
                url: url,
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    tableBody.empty();
                    
                    // --- PERBAIKAN DI SINI ---
                    // Langsung gunakan 'response' sebagai array, bukan 'response.details'
                    if(response && response.length > 0) {
                        response.forEach(function(item) {
                            let qty_sisa = item.qty_pesan - item.qty_diterima;
                            
                            // Baris hanya akan ditambahkan jika masih ada sisa yang bisa diterima
                            let row = `
                                <tr>
                                    <td>
                                        ${item.part.nama_part} (${item.part.kode_part})
                                        <input type="hidden" name="items[${item.part_id}][part_id]" value="${item.part_id}">
                                    </td>
                                    <td><input type="text" class="form-control" value="${item.qty_pesan}" readonly></td>
                                    <td><input type="text" class="form-control" value="${item.qty_diterima}" readonly></td>
                                    <td><input type="text" class="form-control" value="${qty_sisa}" readonly></td>
                                    <td>
                                        <input type="number" name="items[${item.part_id}][qty_terima]"
                                               class="form-control"
                                               min="0"
                                               max="${qty_sisa}"
                                               value="${qty_sisa}"
                                               required>
                                    </td>
                                </tr>
                            `;
                            tableBody.append(row);
                        });
                    } else {
                        // Jika array kosong, berarti tidak ada item yang bisa diretur
                        tableBody.html('<tr><td colspan="5" class="text-center text-success">Semua item dari PO ini sudah diterima sepenuhnya atau tidak ada detail item.</td></tr>');
                    }
                },
                error: function() {
                    tableBody.html('<tr><td colspan="5" class="text-center text-danger">Gagal memuat data. Periksa otorisasi atau hubungi administrator.</td></tr>');
                }
            });
        } else {
            tableBody.html('<tr><td colspan="5" class="text-center text-muted">Pilih PO untuk menampilkan item.</td></tr>');
        }
    });
});
</script>
@stop
