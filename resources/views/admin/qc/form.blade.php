@extends('adminlte::page')

@section('title', 'Quality Control')
@section('content_header')
    <h1><i class="fas fa-clipboard-check text-primary"></i> Proses Quality Control</h1>
@stop

@section('content')
<div class="row">
    <div class="col-12">
        {{-- Progress Bar --}}
        <div class="card mb-3">
            <div class="card-body p-3">
                <div class="progress" style="height: 25px;">
                    <div class="progress-bar bg-success" style="width: 33%">1. Receiving</div>
                    <div class="progress-bar bg-primary progress-bar-striped progress-bar-animated" style="width: 34%">2. Quality Control (Aktif)</div>
                    <div class="progress-bar bg-secondary" style="width: 33%">3. Putaway</div>
                </div>
            </div>
        </div>

        <div class="card card-outline card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    Penerimaan: <strong>{{ $receiving->nomor_penerimaan }}</strong>
                    <small class="ml-2 text-muted">({{ $receiving->tanggal_terima->format('d/m/Y') }})</small>
                </h3>
            </div>
            
            <form action="{{ route('admin.qc.store', $receiving->id) }}" method="POST" id="qc-form">
                @csrf
                <div class="card-body">
                    @if($errors->any())
                        <x-adminlte-alert theme="danger" title="Error Validasi" dismissable>
                            <ul>@foreach($errors->all() as $err) <li>{{$err}}</li> @endforeach</ul>
                        </x-adminlte-alert>
                    @endif

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle mr-1"></i>
                        Isi jumlah <strong>Lolos</strong> dan <strong>Gagal</strong>. Pastikan <strong>Sisa = 0</strong>.
                        Barang gagal akan otomatis masuk ke <strong>Rak Karantina</strong>.
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="thead-light">
                                <tr>
                                    <th width="30%">Barang</th>
                                    <th width="10%" class="text-center">Diterima</th>
                                    <th width="15%" class="text-center bg-success text-white">Lolos (Bagus)</th>
                                    <th width="15%" class="text-center bg-danger text-white">Gagal (Rusak)</th>
                                    <th width="10%" class="text-center">Sisa</th>
                                    <th width="20%">Catatan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($receiving->details as $detail)
                                <tr class="qc-row">
                                    <td>
                                        <strong>{{ $detail->barang->part_name }}</strong><br>
                                        <small class="text-muted">{{ $detail->barang->part_code }}</small>
                                    </td>
                                    <td class="text-center align-middle font-weight-bold" style="font-size:1.1em">
                                        {{ $detail->qty_terima }}
                                        <input type="hidden" class="qty-terima" value="{{ $detail->qty_terima }}">
                                    </td>
                                    <td>
                                        <input type="number" name="items[{{ $detail->id }}][qty_lolos]" 
                                            class="form-control text-center font-weight-bold text-success qty-lolos" 
                                            min="0" max="{{ $detail->qty_terima }}" 
                                            value="{{ old('items.'.$detail->id.'.qty_lolos', $detail->qty_terima) }}" required>
                                    </td>
                                    <td>
                                        <input type="number" name="items[{{ $detail->id }}][qty_gagal]" 
                                            class="form-control text-center font-weight-bold text-danger qty-gagal" 
                                            min="0" max="{{ $detail->qty_terima }}" 
                                            value="{{ old('items.'.$detail->id.'.qty_gagal', 0) }}" required>
                                    </td>
                                    <td class="text-center align-middle">
                                        <span class="badge badge-light border sisa-badge" style="font-size:1em">0</span>
                                    </td>
                                    <td>
                                        <textarea name="items[{{ $detail->id }}][catatan_qc]" class="form-control" rows="1" placeholder="Ket...">{{ old('items.'.$detail->id.'.catatan_qc') }}</textarea>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between">
                    <a href="{{ route('admin.qc.index') }}" class="btn btn-default">Kembali</a>
                    <button type="submit" id="btn-save" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan Hasil QC</button>
                </div>
            </form>
        </div>
    </div>
</div>
@stop

@section('js')
<script>
$(document).ready(function() {
    function validateRow(row) {
        let terima = parseInt(row.find('.qty-terima').val());
        let lolos = parseInt(row.find('.qty-lolos').val()) || 0;
        let gagal = parseInt(row.find('.qty-gagal').val()) || 0;
        
        let sisa = terima - (lolos + gagal);
        let badge = row.find('.sisa-badge');
        
        badge.text(sisa);
        
        if(sisa === 0) {
            badge.removeClass('badge-danger').addClass('badge-success');
            row.removeClass('table-danger');
        } else {
            badge.removeClass('badge-success').addClass('badge-danger');
            row.addClass('table-danger');
        }
        
        checkGlobal();
    }

    function checkGlobal() {
        let isValid = true;
        $('.qc-row').each(function() {
            let sisa = parseInt($(this).find('.sisa-badge').text());
            if(sisa !== 0) isValid = false;
        });
        $('#btn-save').prop('disabled', !isValid);
    }

    $('.qty-lolos, .qty-gagal').on('input change', function() {
        validateRow($(this).closest('tr'));
    });

    // UX Auto Select
    $('.qty-lolos, .qty-gagal').on('focus', function() { $(this).select(); });

    // Init
    $('.qc-row').each(function() { validateRow($(this)); });
});
</script>
@stop