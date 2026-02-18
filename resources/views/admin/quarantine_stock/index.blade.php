@extends('adminlte::page')

@section('title', 'Manajemen Stok Karantina')
@section('plugins.Datatables', true)
@section('plugins.Select2', true)

@section('content_header')
    <h1><i class="fas fa-exclamation-triangle text-warning mr-2"></i> Stok Karantina</h1>
@stop

@section('content')
<div class="card card-outline card-warning">
    <div class="card-header">
        <h3 class="card-title">Daftar Barang Gagal QC / Retur</h3>
    </div>
    <div class="card-body">
        
        @if(session('success'))
            <x-adminlte-alert theme="success" title="Sukses" dismissable>
                {{ session('success') }}
            </x-adminlte-alert>
        @endif

        @if(session('error'))
            <x-adminlte-alert theme="danger" title="Gagal" dismissable>
                {{ session('error') }}
            </x-adminlte-alert>
        @endif

        <table id="quarantine-table" class="table table-bordered table-striped table-hover">
            <thead class="thead-light">
                <tr>
                    <th>Barang / Part</th>
                    <th>Lokasi Gudang</th>
                    <th>Posisi Rak</th>
                    {{-- MODIFIKASI POIN 1: Kolom Detail Stok --}}
                    <th class="text-center" width="10%">Fisik</th>
                    <th class="text-center" width="10%">Pending</th>
                    <th class="text-center" width="10%">Tersedia</th>
                    <th class="text-center" style="width: 120px">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($quarantineItems as $item)
                <tr>
                    <td>
                        <span class="font-weight-bold">{{ $item->barang->part_name }}</span>
                        <br>
                        <small class="text-muted">{{ $item->barang->part_code }}</small>
                    </td>
                    <td>{{ $item->lokasi->nama_lokasi }}</td>
                    <td><span class="badge badge-warning">{{ $item->rak->kode_rak }}</span></td>
                    
                    {{-- Detail Stok --}}
                    <td class="text-center font-weight-bold">{{ $item->total_quantity }}</td>
                    <td class="text-center text-danger">
                        @if($item->pending_quantity > 0)
                            <span data-toggle="tooltip" title="Menunggu Approval Write-Off">
                                -{{ $item->pending_quantity }}
                            </span>
                        @else
                            -
                        @endif
                    </td>
                    <td class="text-center font-weight-bold text-success" style="font-size: 1.1em">
                        {{ $item->available_quantity }}
                    </td>

                    <td class="text-center">
                        @can('manage-quarantine-stock')
                            @if($item->available_quantity > 0)
                                <button type="button" class="btn btn-primary btn-sm btn-process"
                                    data-barang-id="{{ $item->barang_id }}"
                                    data-rak-id="{{ $item->rak_id }}"
                                    data-lokasi-id="{{ $item->lokasi_id }}"
                                    data-available-qty="{{ $item->available_quantity }}" 
                                    data-barang-name="{{ $item->barang->part_name }}">
                                    <i class="fas fa-cog"></i> Proses
                                </button>
                            @else
                                <button class="btn btn-secondary btn-sm" disabled>Locked</button>
                            @endif
                        @endcan
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">Tidak ada stok di rak karantina saat ini.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- MODAL PROSES --}}
<div class="modal fade" id="processModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Tindak Lanjut Karantina</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="{{ route('admin.quarantine-stock.process') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <input type="hidden" name="barang_id" id="modal_barang_id">
                    <input type="hidden" name="rak_id" id="modal_rak_id">
                    <input type="hidden" name="lokasi_id" id="modal_lokasi_id">

                    <div class="alert alert-light border">
                        <strong>Barang:</strong> <span id="modal_barang_name">-</span>
                    </div>

                    <div class="form-group">
                        <label>Jumlah Diproses <span class="text-danger">*</span></label>
                        {{-- Batasi input hanya sampai available qty --}}
                        <input type="number" name="quantity" id="modal_quantity" class="form-control" required min="1">
                        <small class="text-muted">Tersedia untuk diproses: <strong><span id="modal_max_qty">0</span></strong></small>
                    </div>

                    <div class="form-group">
                        <label>Aksi Tindakan <span class="text-danger">*</span></label>
                        <select name="action" id="modal_action" class="form-control" required>
                            <option value="" selected disabled>-- Pilih Aksi --</option>
                            <option value="return_to_stock">Kembalikan ke Stok Penjualan (Good Stock)</option>
                            <option value="write_off">Ajukan Pemusnahan (Write-Off)</option>
                        </select>
                    </div>

                    <div class="form-group d-none" id="group_destination">
                        <label>Pilih Rak Tujuan (Sales) <span class="text-danger">*</span></label>
                        <select name="destination_rak_id" id="modal_destination_rak" class="form-control select2" style="width: 100%;">
                            {{-- Diisi JS --}}
                        </select>
                    </div>

                    <div class="form-group d-none" id="group_reason">
                        <label>Alasan Pemusnahan <span class="text-danger">*</span></label>
                        <textarea name="reason" id="modal_reason" class="form-control" rows="2" placeholder="Contoh: Barang rusak total terkena air..."></textarea>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Keputusan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@stop

@section('js')
<script>
$(document).ready(function() {
    $('#quarantine-table').DataTable({ 
        "responsive": true, 
        "autoWidth": false,
        "ordering": false 
    });

    $('.select2').select2({
        dropdownParent: $('#processModal'),
        theme: 'bootstrap4'
    });

    const storageRaksMap = @json($storageRaks);

    $('.btn-process').on('click', function() {
        let btn = $(this);
        let availableQty = btn.data('available-qty'); // Ambil Data Tersedia

        $('#modal_barang_id').val(btn.data('barang-id'));
        $('#modal_rak_id').val(btn.data('rak-id'));
        $('#modal_lokasi_id').val(btn.data('lokasi-id'));
        $('#modal_barang_name').text(btn.data('barang-name'));
        
        // Set Max Input ke Available Qty, bukan Total Fisik
        $('#modal_quantity').val(availableQty).attr('max', availableQty);
        $('#modal_max_qty').text(availableQty);

        $('#modal_action').val('').trigger('change');
        
        let lokasiId = btn.data('lokasi-id');
        let rakSelect = $('#modal_destination_rak');
        rakSelect.empty().append('<option value="">-- Pilih Rak --</option>');
        
        if (storageRaksMap[lokasiId]) {
            storageRaksMap[lokasiId].forEach(function(rak) {
                rakSelect.append(new Option(rak.kode_rak + (rak.nama_rak ? ' - ' + rak.nama_rak : ''), rak.id));
            });
        }

        $('#processModal').modal('show');
    });

    $('#modal_action').on('change', function() {
        let action = $(this).val();
        if (action === 'return_to_stock') {
            $('#group_destination').removeClass('d-none');
            $('#modal_destination_rak').prop('required', true);
            $('#group_reason').addClass('d-none');
            $('#modal_reason').prop('required', false);
        } else if (action === 'write_off') {
            $('#group_destination').addClass('d-none');
            $('#modal_destination_rak').prop('required', false);
            $('#group_reason').removeClass('d-none');
            $('#modal_reason').prop('required', true);
        } else {
            $('#group_destination, #group_reason').addClass('d-none');
        }
    });
});
</script>
@stop