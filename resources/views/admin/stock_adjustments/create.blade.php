@extends('adminlte::page')

@section('title', 'Buat Stock Adjustment')
@section('plugins.Select2', true)

@section('content_header')
    <div class="row mb-2">
        <div class="col-sm-6">
            <h1><i class="fas fa-sliders-h text-primary mr-2"></i> Buat Stock Adjustment</h1>
        </div>
        <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="{{ route('admin.stock-adjustments.index') }}">Adjustment</a></li>
                <li class="breadcrumb-item active">Baru</li>
            </ol>
        </div>
    </div>
@stop

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card card-outline card-primary shadow-sm">
            <div class="card-header">
                <h3 class="card-title">Form Penyesuaian Stok (Stock Opname)</h3>
            </div>
            <form action="{{ route('admin.stock-adjustments.store') }}" method="POST">
                @csrf
                <div class="card-body">
                    
                    @if($errors->any())
                        <x-adminlte-alert theme="danger" title="Error" dismissable>
                            <ul class="mb-0 pl-3">
                                @foreach($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                            </ul>
                        </x-adminlte-alert>
                    @endif

                    {{-- Lokasi & Rak --}}
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Lokasi Gudang</label>
                                <select name="lokasi_id" id="lokasi_id" class="form-control select2" required style="width: 100%;">
                                    @if(count($lokasis) > 1)
                                        <option value="" selected disabled>-- Pilih Lokasi --</option>
                                    @endif
                                    @foreach($lokasis as $lok)
                                        <option value="{{ $lok->id }}" {{ count($lokasis) == 1 ? 'selected' : '' }}>
                                            {{ $lok->nama_lokasi }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Rak Penyimpanan</label>
                                <select name="rak_id" id="rak_id" class="form-control select2" required disabled style="width: 100%;">
                                    <option value="">-- Pilih Lokasi Dulu --</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- Barang --}}
                    <div class="form-group">
                        <label>Barang / Sparepart</label>
                        <select name="barang_id" id="barang_id" class="form-control select2" required disabled style="width: 100%;">
                             <option value="">-- Pilih Rak Dulu --</option>
                        </select>
                    </div>

                    {{-- Pilihan Batch (Fitur Baru) --}}
                    <div class="form-group d-none" id="batch-container">
                        <label><i class="fas fa-layer-group text-info"></i> Pilih Batch (Opsional)</label>
                        <select name="inventory_batch_id" id="inventory_batch_id" class="form-control select2" style="width: 100%;">
                             <option value="">-- Buat Batch Baru / General --</option>
                        </select>
                        <small class="form-text text-muted" id="batch-help-text">
                            Jika dipilih, stok akan ditambahkan/dikurangkan pada batch ini. Jika kosong, sistem akan FIFO.
                        </small>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Tipe Adjustment</label>
                                <select name="tipe" id="tipe" class="form-control" required>
                                    <option value="TAMBAH">TAMBAH (Stok Lebih/Ketemu)</option>
                                    <option value="KURANG">KURANG (Stok Hilang/Rusak)</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Jumlah Penyesuaian</label>
                                <input type="number" name="jumlah" id="jumlah" class="form-control" min="1" required placeholder="0">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Alasan / Keterangan</label>
                        <textarea name="alasan" class="form-control" rows="2" required placeholder="Contoh: Selisih SO bulan Juni, barang fisik lebih banyak..."></textarea>
                    </div>

                </div>
                <div class="card-footer text-right">
                    <a href="{{ route('admin.stock-adjustments.index') }}" class="btn btn-default mr-2">Batal</a>
                    <button type="submit" class="btn btn-primary" id="btn-submit"><i class="fas fa-save mr-1"></i> Ajukan Adjustment</button>
                </div>
            </form>
        </div>
    </div>
</div>
@stop

@section('js')
<script>
$(document).ready(function() {
    // Init Select2 Global
    $('.select2').select2({ theme: 'bootstrap4' });

    const lokasiSelect = $('#lokasi_id');
    const rakSelect    = $('#rak_id');
    const barangSelect = $('#barang_id');
    const batchSelect  = $('#inventory_batch_id');
    const batchContainer = $('#batch-container');

    // 1. Load Rak saat Lokasi berubah
    function loadRaks(lokasiId) {
        if(!lokasiId) return;
        rakSelect.prop('disabled', true).html('<option>Loading...</option>');
        
        // Asumsi route API Lokasi sudah ada, atau buat helper di controller
        // Jika belum ada route API khusus, ganti URL ini
        $.get("{{ url('admin/api/lokasi') }}/" + lokasiId + "/raks", function(data){
            rakSelect.empty().append('<option value="" selected disabled>-- Pilih Rak --</option>');
            data.forEach(function(rak){
                rakSelect.append(new Option(rak.nama_rak + ' ('+rak.kode_rak+')', rak.id));
            });
            rakSelect.prop('disabled', false);
        });
    }

    // 2. Load Barang via Select2 AJAX
    function initBarangSelect() {
        barangSelect.prop('disabled', false).empty().append('<option value="">-- Cari Barang --</option>');
        
        barangSelect.select2({
            theme: 'bootstrap4',
            placeholder: 'Ketik Nama/Kode Part...',
            ajax: {
                url: "{{ route('admin.stock-adjustments.get-barangs') }}", 
                dataType: 'json',
                delay: 250,
                data: function (params) { return { q: params.term }; },
                processResults: function (data) { return { results: data }; },
                cache: true
            }
        });
    }

    // 3. Load Batch saat Barang Dipilih
    function loadBatches() {
        let b_id = barangSelect.val();
        let r_id = rakSelect.val();

        if (b_id && r_id) {
            batchSelect.prop('disabled', true).html('<option>Loading...</option>');
            batchContainer.removeClass('d-none');

            $.get("{{ route('admin.stock-adjustments.get-batches') }}", { barang_id: b_id, rak_id: r_id }, function(data) {
                batchSelect.empty();
                
                // Opsi Default
                batchSelect.append(new Option('-- Buat Batch Baru / Otomatis --', ''));

                if (data.length > 0) {
                    data.forEach(function(batch) {
                        batchSelect.append(new Option(batch.text, batch.id));
                    });
                }
                
                batchSelect.prop('disabled', false);
            });
        } else {
            batchContainer.addClass('d-none');
        }
    }

    // Event Listeners
    lokasiSelect.on('change', function() { loadRaks($(this).val()); });
    
    rakSelect.on('change', function() { 
        initBarangSelect(); 
    });
    
    barangSelect.on('change', loadBatches);

    // Initial Load jika edit mode atau validasi error
    if(lokasiSelect.val()) loadRaks(lokasiSelect.val());
});
</script>
@stop