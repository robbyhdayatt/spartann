@extends('adminlte::page')

@section('title', 'Proses Penerimaan Mutasi')

@section('plugins.Select2', true) {{-- Pastikan Select2 aktif --}}

@section('content_header')
    <h1>Proses Penerimaan Mutasi: {{ $mutation->nomor_mutasi }}</h1>
@stop

@section('content')
<form action="{{ route('admin.mutation-receiving.receive', $mutation) }}" method="POST">
    @csrf
    <div class="row">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Detail Barang</h3></div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item"><b>Nomor Mutasi:</b> {{ $mutation->nomor_mutasi }}</li>
                        <li class="list-group-item"><b>Part:</b> {{ $mutation->barang->part_name }} ({{$mutation->barang->part_code}})</li>
                        <li class="list-group-item"><b>Jumlah Dikirim:</b> <span class="badge badge-primary">{{ $mutation->jumlah }} </span></li>
                        {{-- ++ PERBAIKAN DI SINI ++ --}}
                        <li class="list-group-item"><b>Dari Lokasi:</b> {{ $mutation->lokasiAsal->nama_lokasi }} ({{ $mutation->lokasiAsal->kode_lokasi }})</li>
                        <li class="list-group-item"><b>Rak Asal:</b> <span class="text-muted">Diambil dari batch tertua (FIFO)</span></li>
                        <li class="list-group-item"><b>Tanggal Kirim:</b> {{ $mutation->approved_at ? $mutation->approved_at->format('d M Y, H:i') : '-' }}</li> {{-- Tambah check null --}}
                        <li class="list-group-item"><b>Keterangan Kirim:</b> {{ $mutation->keterangan ?? '-' }}</li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card card-success">
                 <div class="card-header"><h3 class="card-title">Konfirmasi Penerimaan</h3></div>
                 <div class="card-body">
                    {{-- BLOK UNTUK MENAMPILKAN SEMUA JENIS ERROR --}}
                    @if(session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <p>Silakan pilih rak tujuan untuk menyimpan barang ini.</p>
                      <div class="form-group">
                          <label for="rak_tujuan_id">Pilih Rak Penyimpanan <span class="text-danger">*</span></label>
                          <select name="rak_tujuan_id" id="rak_tujuan_id" class="form-control select2 @error('rak_tujuan_id') is-invalid @enderror" required>
                              <option value="">-- Pilih Rak --</option>
                              {{-- Pastikan $raks hanya berisi rak dari lokasi tujuan --}}
                              @forelse ($raks as $rak)
                                  <option value="{{ $rak->id }}" {{ old('rak_tujuan_id') == $rak->id ? 'selected' : '' }}>
                                      {{ $rak->nama_rak }} ({{ $rak->kode_rak }})
                                  </option>
                              @empty
                                 <option value="" disabled>Tidak ada rak penyimpanan aktif di lokasi ini</option>
                              @endforelse
                          </select>
                           @error('rak_tujuan_id')
                               <span class="invalid-feedback" role="alert">
                                   <strong>{{ $message }}</strong>
                               </span>
                           @enderror
                      </div>
                 </div>
                 <div class="card-footer">
                      {{-- Tambahkan kondisi disabled jika tidak ada rak --}}
                      <button type="submit" class="btn btn-success" {{ $raks->isEmpty() ? 'disabled' : '' }}>
                          <i class="fas fa-check-circle"></i> Konfirmasi Terima Barang
                      </button>
                      <a href="{{ route('admin.mutation-receiving.index') }}" class="btn btn-secondary">Batal</a>
                 </div>
            </div>
        </div>
    </div>
</form>
@stop

@section('js')
<script>
    $(document).ready(function() {
        // Inisialisasi Select2
        $('.select2').select2({ theme: 'bootstrap4' }); // Tambahkan theme
    });
</script>
@stop
