<div class="row">
    <div class="col-12">
        <h4>Tugas Operasional di {{ $data['lokasi']->nama_lokasi }}</h4>
        <hr>
    </div>

    @if($data['isPusat'])
    <div class="col-md-4">
        <a href="{{ route('admin.receivings.index') }}" class="info-box mb-3 bg-info">
            <span class="info-box-icon"><i class="fas fa-box-open"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Penerimaan dari PO</span>
                <span class="info-box-number">{{ $data['taskCounts']['pending_receiving_po'] ?? 0 }} Tugas</span>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="#" class="info-box mb-3 bg-warning">
            <span class="info-box-icon"><i class="fas fa-check-circle"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Quality Control</span>
                <span class="info-box-number">{{ $data['taskCounts']['pending_qc'] ?? 0 }} Tugas</span>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="#" class="info-box mb-3 bg-success">
            <span class="info-box-icon"><i class="fas fa-dolly-flatbed"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Penyimpanan (Putaway)</span>
                <span class="info-box-number">{{ $data['taskCounts']['pending_putaway'] ?? 0 }} Tugas</span>
            </div>
        </a>
    </div>
    @endif

    <div class="col-md-4">
        <a href="{{ route('admin.mutation-receiving.index') }}" class="info-box mb-3 bg-purple">
            <span class="info-box-icon"><i class="fas fa-people-carry"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Penerimaan Mutasi</span>
                <span class="info-box-number">{{ $data['taskCounts']['pending_receiving_mutation'] ?? 0 }} Tugas</span>
            </div>
        </a>
    </div>
</div>