@extends('adminlte::page')

@section('title', 'Putaway Barang')
@section('plugins.Select2', true)

@section('content_header')
    <h1><i class="fas fa-dolly-flatbed text-primary"></i> Proses Putaway</h1>
@stop

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card mb-3">
            <div class="card-body p-3">
                <div class="progress" style="height: 25px;">
                    <div class="progress-bar bg-success" style="width: 33%">1. Receiving</div>
                    <div class="progress-bar bg-success" style="width: 33%">2. Quality Control</div>
                    <div class="progress-bar bg-primary progress-bar-striped progress-bar-animated" style="width: 34%">3. Putaway (Aktif)</div>
                </div>
            </div>
        </div>

        <div class="card card-outline card-primary">
            <div class="card-header">
                <h3 class="card-title">Pilih Rak Penyimpanan</h3>
            </div>
            <form action="{{ route('admin.putaway.store', $receiving->id) }}" method="POST">
                @csrf
                <div class="card-body">
                    @if($errors->any())
                        <x-adminlte-alert theme="danger" title="Error" dismissable>
                            <ul>@foreach($errors->all() as $e) <li>{{$e}}</li> @endforeach</ul>
                        </x-adminlte-alert>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead class="thead-dark">
                                <tr>
                                    <th>Barang</th>
                                    <th width="15%" class="text-center">Qty Lolos QC</th>
                                    <th width="40%">Lokasi Rak</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($itemsToPutaway as $item)
                                <tr>
                                    <td>
                                        <strong>{{ $item->barang->part_name }}</strong><br>
                                        <small>{{ $item->barang->part_code }}</small>
                                    </td>
                                    <td class="text-center align-middle font-weight-bold" style="font-size:1.2em">
                                        {{ $item->qty_lolos_qc }}
                                    </td>
                                    <td>
                                        <select name="items[{{ $item->id }}][rak_id]" class="form-control select2" required style="width: 100%;">
                                            <option value="" selected disabled>-- Pilih Rak --</option>
                                            @foreach($raks as $rak)
                                                <option value="{{ $rak->id }}">
                                                    {{ $rak->kode_rak }} 
                                                    @if($rak->nama_rak) - {{ $rak->nama_rak }} @endif
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between">
                    <a href="{{ route('admin.putaway.index') }}" class="btn btn-secondary">Batal</a>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check-circle mr-1"></i> Selesai & Update Stok</button>
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
    });
</script>
@stop