@extends('adminlte::page')

@section('title', 'Proses Penyimpanan Barang')
@section('plugins.Select2', true)

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1><i class="fas fa-dolly-flatbed text-primary mr-2"></i> Proses Penyimpanan (Putaway)</h1>
        <div class="text-right">
             <span class="badge badge-secondary" style="font-size: 1rem;">No. Terima: {{ $receiving->nomor_penerimaan }}</span>
        </div>
    </div>
@stop

@section('content')
<div class="row justify-content-center">
    <div class="col-12">
        
        {{-- Progress Wizard --}}
        <div class="card mb-3">
            <div class="card-body p-3">
                <div class="progress" style="height: 25px;">
                    <div class="progress-bar bg-success" role="progressbar" style="width: 33%">1. Receiving</div>
                    <div class="progress-bar bg-success" role="progressbar" style="width: 33%">2. Quality Control</div>
                    <div class="progress-bar bg-primary progress-bar-striped progress-bar-animated" role="progressbar" style="width: 34%">3. Putaway (Sedang Proses)</div>
                </div>
            </div>
        </div>

        <div class="card card-outline card-primary shadow-sm">
            <form action="{{ route('admin.putaway.store', $receiving->id) }}" method="POST">
                @csrf
                <div class="card-body">
                    @if ($errors->any())
                        <div class="alert alert-danger alert-dismissible fade show">
                            <ul class="mb-0 pl-3">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        </div>
                    @endif
                    @if(session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif

                    <div class="alert alert-light border-left-primary" role="alert">
                        <i class="fas fa-info-circle text-primary mr-1"></i> 
                        Tentukan <strong>Rak Penyimpanan</strong> untuk setiap barang yang telah lolos Quality Control (QC).
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover v-middle">
                            <thead class="thead-dark">
                                <tr>
                                    <th style="width: 40%">Nama Barang (Part)</th>
                                    <th class="text-center" style="width: 20%">Qty Lolos QC</th>
                                    <th style="width: 40%">Lokasi Simpan (Rak)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($itemsToPutaway as $detail)
                                <tr>
                                    <td class="align-middle">
                                        <div class="d-flex align-items-center">
                                            <div class="symbol symbol-40 mr-3">
                                                <div class="symbol-label bg-light-primary rounded p-2">
                                                    <i class="fas fa-box-open text-primary"></i>
                                                </div>
                                            </div>
                                            <div>
                                                <span class="text-dark font-weight-bold d-block">{{ $detail->barang->part_name }}</span>
                                                <span class="text-muted small font-weight-bold">{{ $detail->barang->part_code }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <td class="align-middle text-center">
                                        <span class="badge badge-success px-3 py-2" style="font-size: 1.1rem;">
                                            <i class="fas fa-check mr-1"></i> {{ $detail->qty_lolos_qc }} Pcs
                                        </span>
                                    </td>
                                    
                                    <td class="align-middle">
                                         <div class="form-group mb-0">
                                             <select name="items[{{ $detail->id }}][rak_id]" class="form-control select2" style="width: 100%;" required>
                                                 <option value="" disabled selected>-- Cari Kode Rak / Lokasi --</option>
                                                 @foreach($raks as $rak)
                                                     <option value="{{ $rak->id }}">
                                                         {{ $rak->kode_rak }} 
                                                         @if($rak->nama_rak != $rak->kode_rak) 
                                                            - {{ $rak->nama_rak }} 
                                                         @endif
                                                         (L{{$rak->level}} / B{{$rak->bin}})
                                                     </option>
                                                 @endforeach
                                             </select>
                                         </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-5">
                                        <i class="fas fa-check-double fa-3x mb-3 text-gray-300"></i><br>
                                        Tidak ada item yang perlu disimpan (Qty Lolos QC = 0).
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-light d-flex justify-content-between">
                    <a href="{{ route('admin.putaway.index') }}" class="btn btn-default shadow-sm"><i class="fas fa-arrow-left mr-1"></i> Batal</a>
                    <button type="submit" class="btn btn-primary shadow-sm px-4" {{ $itemsToPutaway->isEmpty() ? 'disabled' : '' }}>
                        <i class="fas fa-save mr-1"></i> Simpan ke Rak & Update Stok
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@stop

@push('css')
<style>
    /* Styling tambahan */
    .border-left-primary { border-left: 4px solid #007bff; }
    .v-middle td, .v-middle th { vertical-align: middle !important; }
    
    /* Select2 Height Fix */
    .select2-container .select2-selection--single { height: 38px !important; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 28px !important; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px !important; }
</style>
@endpush

@section('js')
<script>
    $(document).ready(function() {
        // Init Select2
        $('.select2').select2({ theme: 'bootstrap4', allowClear: true });
    });
</script>
@stop