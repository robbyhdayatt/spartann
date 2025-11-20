<div class="row mb-3">
    <div class="col-12">
        <h4>Halo, {{ Auth::user()->nama }}!</h4>
        <p>Anda login sebagai <strong>{{ Auth::user()->jabatan->nama_jabatan }}</strong> di <strong>{{ $data['lokasi']->nama_lokasi }}</strong></p>
    </div>
</div>

<div class="row">
    {{-- KHUSUS ADMIN PUSAT: APPROVAL PO --}}
    @if($data['isPusat'])
    <div class="col-lg-3 col-6">
        <div class="small-box bg-warning">
            <div class="inner">
                <h3>{{ $data['taskCounts']['pending_po_approval'] ?? 0 }}</h3>
                <p>PO Butuh Approval</p>
            </div>
            <div class="icon"><i class="fas fa-check-circle"></i></div>
            <a href="{{ route('admin.purchase-orders.index') }}" class="small-box-footer">Proses Approval <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
    @endif

    {{-- KHUSUS DEALER: RECEIVING, QC, PUTAWAY --}}
    @if(!$data['isPusat'])
    <div class="col-lg-3 col-6">
        <div class="small-box bg-primary">
            <div class="inner">
                <h3>{{ $data['taskCounts']['receiving_po'] }}</h3>
                <p>PO Siap Diterima</p>
            </div>
            <div class="icon"><i class="fas fa-truck-loading"></i></div>
            <a href="{{ route('admin.receivings.create') }}" class="small-box-footer">Proses Penerimaan <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>

    <div class="col-lg-3 col-6">
        <div class="small-box bg-warning">
            <div class="inner">
                <h3>{{ $data['taskCounts']['qc'] }}</h3>
                <p>Pending Quality Control</p>
            </div>
            <div class="icon"><i class="fas fa-check-double"></i></div>
            <a href="{{ route('admin.qc.index') }}" class="small-box-footer">Lakukan QC <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>

    <div class="col-lg-3 col-6">
        <div class="small-box bg-success">
            <div class="inner">
                <h3>{{ $data['taskCounts']['putaway'] }}</h3>
                <p>Pending Putaway (Rak)</p>
            </div>
            <div class="icon"><i class="fas fa-box-open"></i></div>
            <a href="{{ route('admin.putaway.index') }}" class="small-box-footer">Simpan ke Rak <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
    @endif

    {{-- SEMUA: TERIMA MUTASI --}}
    <div class="col-lg-3 col-6">
        <div class="small-box bg-info">
            <div class="inner">
                <h3>{{ $data['taskCounts']['receiving_mutation'] }}</h3>
                <p>Mutasi Masuk (In Transit)</p>
            </div>
            <div class="icon"><i class="fas fa-people-carry"></i></div>
            <a href="{{ route('admin.mutation-receiving.index') }}" class="small-box-footer">Terima Mutasi <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
</div>
