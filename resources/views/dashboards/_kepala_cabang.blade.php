<div class="row mb-2">
    <div class="col-12">
        <h4><i class="fas fa-store-alt text-primary"></i> Dashboard Kepala Cabang</h4>
        <p class="text-muted">Lokasi: <strong>{{ $data['lokasi']->nama_lokasi ?? 'Unknown' }}</strong></p>
    </div>
</div>

{{-- 1. RINGKASAN HARI INI --}}
<div class="row">
    <div class="col-lg-3 col-6">
        <div class="small-box bg-info">
            <div class="inner">
                <h3>{{ $data['salesToday'] }}</h3>
                <p>Penjualan Hari Ini</p>
            </div>
            <div class="icon"><i class="fas fa-shopping-cart"></i></div>
            <a href="{{ route('admin.penjualans.index') }}" class="small-box-footer">Lihat Detail <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-success">
            <div class="inner">
                <h3>{{ $data['serviceToday'] }}</h3>
                <p>Unit Service Hari Ini</p>
            </div>
            <div class="icon"><i class="fas fa-wrench"></i></div>
            <a href="{{ route('admin.services.index') }}" class="small-box-footer">Lihat Detail <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-warning">
            <div class="inner">
                <h3>{{ $data['pendingMutations']->count() }}</h3>
                <p>Pending Mutasi Masuk</p>
            </div>
            <div class="icon"><i class="fas fa-truck-loading"></i></div>
            <a href="{{ route('admin.stock-mutations.index') }}" class="small-box-footer">Approval <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-danger">
            <div class="inner">
                <h3>{{ $data['pendingAdjustments']->count() }}</h3>
                <p>Pending Opname Stok</p>
            </div>
            <div class="icon"><i class="fas fa-clipboard-check"></i></div>
            <a href="{{ route('admin.stock-adjustments.index') }}" class="small-box-footer">Approval <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
</div>

<div class="row">
    {{-- 2. APPROVAL MUTASI KELUAR (YANG DIMINTA PUSAT/CABANG LAIN) --}}
    <div class="col-lg-6">
        <div class="card card-primary card-outline">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-exchange-alt"></i> Permintaan Mutasi Stok (Outgoing)</h3>
            </div>
            <div class="card-body p-0">
                @if($data['pendingMutations']->isEmpty())
                    <div class="p-4 text-center text-muted">
                        <i class="fas fa-check-circle fa-2x text-success mb-2"></i><br>
                        Tidak ada permintaan mutasi dari cabang lain/pusat.
                    </div>
                @else
                    <table class="table table-sm table-striped">
                        <thead>
                            <tr>
                                <th>No Mutasi</th>
                                <th>Barang</th>
                                <th>Tujuan</th>
                                <th class="text-right">Qty</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($data['pendingMutations'] as $m)
                            <tr>
                                <td>{{ $m->nomor_mutasi }}</td>
                                <td>{{ $m->barang->part_name }}</td>
                                <td>{{ $m->lokasiTujuan->nama_lokasi }}</td>
                                <td class="text-right font-weight-bold">{{ $m->jumlah }}</td>
                                <td class="text-right">
                                    <a href="{{ route('admin.stock-mutations.show', $m->id) }}" class="btn btn-xs btn-primary">Proses</a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>

    {{-- 3. APPROVAL STOCK OPNAME / ADJUSTMENT --}}
    <div class="col-lg-6">
        <div class="card card-danger card-outline">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-sliders-h"></i> Approval Selisih Stok (Opname)</h3>
            </div>
            <div class="card-body p-0">
                @if($data['pendingAdjustments']->isEmpty())
                    <div class="p-4 text-center text-muted">
                        <i class="fas fa-thumbs-up fa-2x text-success mb-2"></i><br>
                        Stok fisik sesuai sistem. Tidak ada adjustment pending.
                    </div>
                @else
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Barang</th>
                                <th>Tipe</th>
                                <th class="text-right">Qty</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($data['pendingAdjustments'] as $adj)
                            <tr>
                                <td>{{ $adj->barang->part_name }}</td>
                                <td>
                                    <span class="badge {{ $adj->tipe == 'TAMBAH' ? 'badge-success' : 'badge-danger' }}">
                                        {{ $adj->tipe }}
                                    </span>
                                </td>
                                <td class="text-right font-weight-bold">{{ $adj->jumlah }}</td>
                                <td class="text-right">
                                    <a href="{{ route('admin.stock-adjustments.index') }}" class="btn btn-xs btn-danger">Review</a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
</div>