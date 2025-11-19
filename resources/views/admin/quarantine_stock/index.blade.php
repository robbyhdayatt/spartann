@extends('adminlte::page')

@section('title', 'Manajemen Stok Karantina')

@section('plugins.Datatables', true)
@section('plugins.Select2', true)

@section('content_header')
    <h1>Manajemen Stok Karantina</h1>
@stop

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Daftar Barang di Rak Karantina</h3>
    </div>
    <div class="card-body">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <table id="quarantine-table" class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Part</th>
                    <th>lokasi</th>
                    <th>Rak Karantina</th>
                    <th class="text-right">Total Qty</th>
                    <th style="width: 150px">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($quarantineItems as $item)
                <tr>
                    <td>{{ $item->barang->part_name }} <br><small class="text-muted">{{ $item->barang->part_code }}</small></td>
                    <td>{{ $item->lokasi->nama_lokasi }}</td>
                    <td>{{ $item->rak->kode_rak }}</td>
                    <td class="text-right font-weight-bold">{{ $item->total_quantity }}</td>
                    <td>
                        @can('manage-quarantine-stock')
                        <button class="btn btn-primary btn-xs process-btn"
                                data-barang-id="{{ $item->barang_id }}"
                                data-rak-id="{{ $item->rak_id }}"
                                data-lokasi-id="{{ $item->lokasi_id }}"
                                data-max-qty="{{ $item->total_quantity }}"
                                data-barang-name="{{ $item->barang->part_name}}"
                                data-toggle="modal" data-target="#processModal">
                            Proses
                        </button>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center">Tidak ada stok di rak karantina.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Modal Proses Karantina --}}
<div class="modal fade" id="processModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Proses Stok Karantina</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="{{ route('admin.quarantine-stock.process') }}" method="POST">
                @csrf
                <input type="hidden" name="barang_id" id="barang_id">
                <input type="hidden" name="rak_id" id="rak_id">
                <input type="hidden" name="lokasi_id" id="lokasi_id">

                <div class="modal-body">
                    <p>Anda akan memproses part: <strong id="part_name"></strong></p>

                    <div class="form-group">
                        <label for="quantity">Jumlah yang Diproses</label>
                        <input type="number" name="quantity" id="quantity" class="form-control" required min="1">
                    </div>

                    <div class="form-group">
                        <label for="action">Pilih Aksi</label>
                        <select name="action" id="action" class="form-control" required>
                            <option value="">-- Pilih Aksi --</option>
                            <option value="return_to_stock">Kembalikan ke Stok Penjualan</option>
                            <option value="write_off">Ajukan Hapus Stok (Write-Off)</option>
                        </select>
                    </div>

                    <div class="form-group" id="destination-rak-group" style="display: none;">
                        <label for="destination_rak_id">Pilih Rak Tujuan</label>
                        <select name="destination_rak_id" id="destination_rak_id" class="form-control select2" style="width: 100%;">
                            {{-- Options populated by JS --}}
                        </select>
                    </div>

                    <div class="form-group" id="reason-group" style="display: none;">
                        <label for="reason">Alasan Write-Off</label>
                        <textarea name="reason" id="reason" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>
@stop

@push('js')
<script>
$(document).ready(function() {
    $('#quarantine-table').DataTable({ "responsive": true, "autoWidth": false });
    $('.select2').select2({ dropdownParent: $('#processModal') });

    const storageRaksBylokasi = @json($storageRaks);

    $('.process-btn').on('click', function() {
        const barangId = $(this).data('barang-id');
        const rakId = $(this).data('rak-id');
        const lokasiId = $(this).data('lokasi-id');
        const maxQty = $(this).data('max-qty');
        const barangName = $(this).data('barang-name');

        $('#barang_id').val(barangId);
        $('#rak_id').val(rakId);
        $('#lokasi_id').val(lokasiId);

        $('#barang_name').text(barangName);
        $('#quantity').val(maxQty).attr('max', maxQty);

        const destinationRakSelect = $('#destination_rak_id');
        destinationRakSelect.html('<option value="">-- Pilih Rak Tujuan --</option>');

        if (storageRaksBylokasi[lokasiId]) {
            storageRaksBylokasi[lokasiId].forEach(function(rak) {
                destinationRakSelect.append(new Option(`${rak.kode_rak} - ${rak.nama_rak}`, rak.id));
            });
        }
    });

    $('#action').on('change', function() {
        const selectedAction = $(this).val();
        $('#destination-rak-group').hide();
        $('#destination_rak_id').prop('required', false);
        $('#reason-group').hide();
        $('#reason').prop('required', false);

        if (selectedAction === 'return_to_stock') {
            $('#destination-rak-group').show();
            $('#destination_rak_id').prop('required', true);
        } else if (selectedAction === 'write_off') {
            $('#reason-group').show();
            $('#reason').prop('required', true);
        }
    }).trigger('change');
});
</script>
@endpush
