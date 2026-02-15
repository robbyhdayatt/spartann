@extends('adminlte::page')

@section('title', 'Buat Stock Adjustment')
@section('plugins.Select2', true)

@section('content_header')
    <h1>Buat Stock Adjustment</h1>
@stop

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card card-outline card-primary">
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

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Lokasi</label>
                                <select name="lokasi_id" id="lokasi_id" class="form-control select2" required>
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
                                <label>Rak</label>
                                <select name="rak_id" id="rak_id" class="form-control select2" required disabled>
                                    <option value="">-- Pilih Lokasi Dulu --</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Barang</label>
                        {{-- Select2 Ajax untuk Barang bisa lebih efisien jika data banyak, 
                             disini saya gunakan Select2 standar dengan helper API --}}
                        <select name="barang_id" id="barang_id" class="form-control select2" required disabled>
                             <option value="">-- Pilih Rak Dulu --</option>
                        </select>
                    </div>

                    <div class="alert alert-info d-none" id="stock-info">
                        <i class="fas fa-info-circle"></i> Stok saat ini di Rak tersebut: <strong id="stock-val">0</strong> unit.
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
                        <label>Alasan</label>
                        <textarea name="alasan" class="form-control" rows="2" required placeholder="Contoh: Selisih SO bulan Juni..."></textarea>
                    </div>

                </div>
                <div class="card-footer text-right">
                    <a href="{{ route('admin.stock-adjustments.index') }}" class="btn btn-secondary">Batal</a>
                    <button type="submit" class="btn btn-primary" id="btn-submit">Simpan</button>
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

    const lokasiSelect = $('#lokasi_id');
    const rakSelect    = $('#rak_id');
    const barangSelect = $('#barang_id');
    const stockInfo    = $('#stock-info');
    const stockVal     = $('#stock-val');
    const jumlahInput  = $('#jumlah');
    const tipeSelect   = $('#tipe');
    const btnSubmit    = $('#btn-submit');
    let currentStock = 0;

    // 1. Load Rak saat Lokasi berubah
    function loadRaks(lokasiId) {
        if(!lokasiId) return;
        rakSelect.prop('disabled', true).html('<option>Loading...</option>');
        
        $.get("{{ url('admin/api/lokasi') }}/" + lokasiId + "/raks", function(data){
            rakSelect.empty().append('<option value="" selected disabled>-- Pilih Rak --</option>');
            data.forEach(function(rak){
                rakSelect.append(new Option(rak.nama_rak + ' ('+rak.kode_rak+')', rak.id));
            });
            rakSelect.prop('disabled', false);
        });
    }

    // 2. Load Barang saat Rak berubah (Hanya barang yang ada stok di lokasi itu? 
    //    Untuk adjustment TAMBAH, harusnya semua barang bisa. 
    //    Disini kita load semua barang aktif via API getBarangItems umum atau API khusus)
    //    Untuk efisiensi, mari gunakan endpoint yang sudah kita buat di Penjualan/Mutation.
    function loadBarangs(lokasiId) {
        // Kita gunakan endpoint dari StockMutationController yang sudah ada: getPartsWithStock
        // Atau ambil semua barang master. Agar aman (bisa TAMBAH barang yang stok 0), ambil master barang.
        barangSelect.prop('disabled', true).html('<option>Loading...</option>');
        
        // Kita panggil API master barang
        // Asumsi route: admin.barangs.index (json) atau buat helper baru.
        // Agar cepat, kita gunakan data dari Mutation logic: Parts yang ada di lokasi ini. 
        // TAPI: Adjustment TAMBAH bisa untuk barang yang stoknya 0.
        // Solusi: Kita gunakan search select2 ajax ke endpoint general barang.
        barangSelect.prop('disabled', false).empty(); // Reset dan enable agar select2 ajax jalan
    }
    
    // Inisialisasi Select2 Ajax untuk Barang
    barangSelect.select2({
        theme: 'bootstrap4',
        placeholder: 'Cari Barang...',
        ajax: {
            url: "{{ route('admin.api.penjualan.items') }}", // Gunakan endpoint penjualan yang return semua barang aktif
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return { 
                    q: params.term, 
                    lokasi_id: lokasiSelect.val() // Kirim lokasi agar backend bisa kasih info stok (optional)
                };
            },
            processResults: function (data) {
                return { results: data };
            },
            cache: true
        }
    });

    // 3. Cek Stok saat Barang/Rak Dipilih
    function checkStock() {
        let l_id = lokasiSelect.val();
        let r_id = rakSelect.val();
        let b_id = barangSelect.val();

        if(l_id && r_id && b_id) {
            // Kita butuh endpoint khusus di StockAdjustmentController untuk cek stok spesifik Rak
            // Tambahkan route ini di web.php: Route::get('api/check-stock', ...)
            $.get("{{ url('admin/api/check-stock') }}", { lokasi_id: l_id, rak_id: r_id, barang_id: b_id }, function(res) {
                currentStock = parseInt(res.stock);
                stockVal.text(currentStock);
                stockInfo.removeClass('d-none');
                validateInput();
            });
        }
    }

    function validateInput() {
        let val = parseInt(jumlahInput.val()) || 0;
        let tipe = tipeSelect.val();

        if (tipe === 'KURANG' && val > currentStock) {
            jumlahInput.addClass('is-invalid');
            btnSubmit.prop('disabled', true);
            Swal.fire('Stok Tidak Cukup', 'Jumlah pengurangan melebihi stok yang ada di rak ini.', 'warning');
        } else {
            jumlahInput.removeClass('is-invalid');
            btnSubmit.prop('disabled', false);
        }
    }

    lokasiSelect.on('change', function() { loadRaks($(this).val()); });
    rakSelect.on('change', function() { 
        barangSelect.prop('disabled', false); 
        checkStock(); 
    });
    barangSelect.on('change', checkStock);
    jumlahInput.on('input', validateInput);
    tipeSelect.on('change', validateInput);

    if(lokasiSelect.val()) loadRaks(lokasiSelect.val());
});
</script>
@stop