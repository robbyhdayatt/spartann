@extends('adminlte::page')

@section('title', 'Putaway Barang')
@section('plugins.Select2', true)

@section('content_header')
    <div class="row mb-2">
        <div class="col-sm-6">
            <h1>Proses Penyimpanan (Putaway)</h1>
        </div>
    </div>
@stop

@section('content')
<div class="row">
    <div class="col-12">
        {{-- Progress Bar --}}
        <div class="card mb-3 shadow-sm">
            <div class="card-body p-3">
                <div class="progress" style="height: 25px;">
                    <div class="progress-bar bg-success" style="width: 33%">1. Receiving (Selesai)</div>
                    <div class="progress-bar bg-success" style="width: 33%">2. Quality Control (Selesai)</div>
                    <div class="progress-bar bg-info progress-bar-striped progress-bar-animated font-weight-bold" style="width: 34%">3. Putaway (Aktif)</div>
                </div>
            </div>
        </div>

        <div class="card card-outline card-info shadow">
            <div class="card-header">
                <h3 class="card-title">
                     <i class="fas fa-dolly mr-1"></i> Penempatan Barang ke Rak
                </h3>
            </div>
            
            <form action="{{ route('admin.putaway.store', $receiving->id) }}" method="POST">
                @csrf
                <div class="card-body">
                    @if($errors->any())
                        <x-adminlte-alert theme="danger" title="Error" dismissable>
                            <ul>@foreach($errors->all() as $e) <li>{{$e}}</li> @endforeach</ul>
                        </x-adminlte-alert>
                    @endif
                    
                    {{-- MODIFIKASI POIN 1: Penjelasan Kode Rak --}}
                    <div class="callout callout-info">
                        <h5><i class="fas fa-info-circle text-info"></i> Panduan Penyimpanan:</h5>
                        <p>
                            Pilih Rak tujuan untuk setiap barang. Sistem akan memberikan <strong>Rekomendasi Rak</strong> jika barang tersebut sudah pernah disimpan sebelumnya.
                        </p>
                        <hr>
                        <strong><i class="fas fa-barcode"></i> Struktur Kode Rak:</strong><br>
                        <span class="text-muted">Contoh: <strong>A-01-L2-B05</strong></span>
                        <ul class="mb-0 mt-1">
                            <li><strong>Zona (A):</strong> Area Gudang Utama.</li>
                            <li><strong>Nomor (01):</strong> Nomor urut Rak.</li>
                            <li><strong>Level (L2):</strong> Tingkat ketinggian rak (L1 = Bawah).</li>
                            <li><strong>Bin (B05):</strong> Kotak/Posisi spesifik dalam rak.</li>
                        </ul>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead class="bg-gradient-dark">
                                <tr>
                                    <th width="40%">Informasi Barang</th>
                                    <th width="15%" class="text-center">Qty (Siap Simpan)</th>
                                    <th width="45%">Pilih Lokasi Rak</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($itemsToPutaway as $item)
                                <tr>
                                    <td class="align-middle">
                                        <div class="font-weight-bold" style="font-size: 1.1em;">{{ $item->barang->part_name }}</div>
                                        <div class="text-muted"><i class="fas fa-barcode mr-1"></i> {{ $item->barang->part_code }}</div>
                                    </td>
                                    
                                    <td class="text-center align-middle">
                                        <span class="badge badge-success p-2" style="font-size:1.2em">{{ $item->qty_lolos_qc }}</span>
                                    </td>
                                    
                                    <td class="align-middle">
                                        <div class="form-group mb-1">
                                            <select name="items[{{ $item->id }}][rak_id]" class="form-control select2" required style="width: 100%;">
                                                <option value="" selected disabled>-- Pilih Rak Tujuan --</option>
                                                @foreach($raks as $rak)
                                                    <option value="{{ $rak->id }}">
                                                        {{ $rak->kode_rak }} 
                                                        @if($rak->nama_rak && $rak->nama_rak != $rak->kode_rak) - {{ $rak->nama_rak }} @endif
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        
                                        @if(!empty($item->rekomendasi_rak))
                                            <div class="mt-1">
                                                <small class="text-info font-weight-bold">
                                                    <i class="fas fa-map-marker-alt mr-1"></i> Stok saat ini ada di: 
                                                    <span class="badge badge-info ml-1">{{ $item->rekomendasi_rak }}</span>
                                                </small>
                                            </div>
                                        @else
                                            <small class="text-muted">Belum ada data lokasi sebelumnya (Item Baru di Gudang ini).</small>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between bg-light">
                    <a href="{{ route('admin.putaway.index') }}" class="btn btn-default"><i class="fas fa-times mr-1"></i> Batal</a>
                    <button type="submit" class="btn btn-info font-weight-bold">
                        <i class="fas fa-save mr-1"></i> Selesai & Update Stok
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
        $('.select2').select2({ 
            theme: 'bootstrap4',
            placeholder: "Cari Kode Rak..."
        });
    });
</script>
@stop