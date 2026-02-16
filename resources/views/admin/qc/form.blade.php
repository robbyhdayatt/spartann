@extends('adminlte::page')

@section('title', 'Proses QC')

@section('content_header')
    <div class="row mb-2">
        <div class="col-sm-6">
            <h1>Proses Quality Control</h1>
        </div>
    </div>
@stop

@section('content')
<div class="row">
    <div class="col-12">
        {{-- Progress Indicator --}}
        <div class="card mb-3 shadow-sm">
            <div class="card-body p-3">
                <div class="progress" style="height: 25px;">
                    <div class="progress-bar bg-success" style="width: 33%">1. Receiving (Selesai)</div>
                    <div class="progress-bar bg-warning progress-bar-striped progress-bar-animated font-weight-bold" style="width: 34%">2. Quality Control (Aktif)</div>
                    <div class="progress-bar bg-secondary" style="width: 33%">3. Putaway</div>
                </div>
            </div>
        </div>

        <div class="card card-outline card-warning shadow">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-file-alt mr-1"></i> Dokumen: <strong>{{ $receiving->nomor_penerimaan }}</strong>
                </h3>
                <div class="card-tools">
                    <span class="badge badge-warning">PENDING QC</span>
                </div>
            </div>
            
            <form action="{{ route('admin.qc.store', $receiving->id) }}" method="POST" id="qc-form">
                @csrf
                <div class="card-body">
                    
                    <div class="callout callout-info">
                        <h5><i class="fas fa-info"></i> Instruksi:</h5>
                        <p>Silakan periksa fisik barang. Masukkan jumlah <strong>Lolos (Good)</strong> dan <strong>Gagal (Reject)</strong>.
                        <br>Barang reject akan otomatis dipindahkan ke <strong>Rak Karantina</strong>.</p>
                    </div>

                    @if($errors->any())
                        <x-adminlte-alert theme="danger" title="Terdapat Kesalahan" dismissable>
                            <ul>@foreach($errors->all() as $err) <li>{{$err}}</li> @endforeach</ul>
                        </x-adminlte-alert>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="bg-gradient-dark">
                                <tr>
                                    <th width="35%">Informasi Barang</th>
                                    <th width="10%" class="text-center">Qty Terima</th>
                                    <th width="15%" class="text-center bg-success text-dark">Lolos (Good)</th>
                                    <th width="15%" class="text-center bg-danger text-white">Gagal (Reject)</th>
                                    <th width="10%" class="text-center">Validasi</th>
                                    <th width="15%">Catatan QC</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($receiving->details as $detail)
                                <tr class="qc-row">
                                    <td class="align-middle">
                                        <div class="font-weight-bold" style="font-size: 1.1em;">{{ $detail->barang->part_name }}</div>
                                        <div class="text-muted"><i class="fas fa-barcode mr-1"></i> {{ $detail->barang->part_code }}</div>
                                    </td>
                                    <td class="text-center align-middle">
                                        <span class="badge badge-secondary" style="font-size: 1.2em;">{{ $detail->qty_terima }}</span>
                                        <input type="hidden" class="qty-terima" value="{{ $detail->qty_terima }}">
                                    </td>
                                    <td class="align-middle">
                                        <input type="number" name="items[{{ $detail->id }}][qty_lolos]" 
                                            class="form-control text-center font-weight-bold text-success border-success qty-lolos" 
                                            min="0" max="{{ $detail->qty_terima }}" 
                                            value="{{ old('items.'.$detail->id.'.qty_lolos', $detail->qty_terima) }}" required>
                                    </td>
                                    <td class="align-middle">
                                        <input type="number" name="items[{{ $detail->id }}][qty_gagal]" 
                                            class="form-control text-center font-weight-bold text-danger border-danger qty-gagal" 
                                            min="0" max="{{ $detail->qty_terima }}" 
                                            value="{{ old('items.'.$detail->id.'.qty_gagal', 0) }}" required>
                                    </td>
                                    <td class="text-center align-middle">
                                        <span class="badge badge-light border sisa-badge p-2" style="font-size:1em; min-width: 50px;">0</span>
                                        <div class="text-xs mt-1 text-muted">Sisa</div>
                                    </td>
                                    <td class="align-middle">
                                        <textarea name="items[{{ $detail->id }}][catatan_qc]" class="form-control form-control-sm" rows="1" placeholder="Optional">{{ old('items.'.$detail->id.'.catatan_qc') }}</textarea>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between bg-light">
                    <a href="{{ route('admin.qc.index') }}" class="btn btn-default"><i class="fas fa-times mr-1"></i> Batal</a>
                    <button type="submit" id="btn-save" class="btn btn-warning font-weight-bold"><i class="fas fa-check-double mr-1"></i> Simpan Hasil QC</button>
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
            badge.removeClass('badge-danger badge-light').addClass('badge-success');
            row.removeClass('table-danger');
        } else {
            badge.removeClass('badge-success badge-light').addClass('badge-danger');
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
        
        let btn = $('#btn-save');
        btn.prop('disabled', !isValid);
        
        if(!isValid) {
            btn.removeClass('btn-warning').addClass('btn-secondary').html('<i class="fas fa-ban mr-1"></i> Jumlah Belum Sesuai');
        } else {
            btn.removeClass('btn-secondary').addClass('btn-warning').html('<i class="fas fa-check-double mr-1"></i> Simpan Hasil QC');
        }
    }

    $('.qty-lolos, .qty-gagal').on('input change', function() {
        validateRow($(this).closest('tr'));
    });

    $('.qty-lolos, .qty-gagal').on('focus', function() { $(this).select(); });

    // Init Validation
    $('.qc-row').each(function() { validateRow($(this)); });
});
</script>
@stop