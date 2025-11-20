<div class="row mb-2">
    <div class="col-12">
        <h4>Selamat Datang, {{ Auth::user()->nama }}!</h4>
        <p class="text-muted">Lokasi: <strong>{{ $data['lokasi']->nama_lokasi ?? 'Global' }}</strong></p>
    </div>
</div>

<div class="row">
    {{-- 1. APPROVAL PO (Khusus KG Pusat) --}}
    @if($data['pendingPOs']->isNotEmpty())
    <div class="col-lg-6">
        <div class="card card-warning card-outline">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-shopping-cart"></i> Approval Purchase Order</h3>
                <div class="card-tools">
                    <span class="badge badge-warning">{{ $data['pendingPOs']->count() }} Pending</span>
                </div>
            </div>
            <div class="card-body p-0">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>No PO</th>
                            <th>Pemesan</th>
                            <th>Total</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($data['pendingPOs'] as $po)
                        <tr>
                            <td><a href="{{ route('admin.purchase-orders.show', $po->id) }}">{{ $po->nomor_po }}</a></td>
                            <td>{{ $po->lokasi->nama_lokasi }}<br><small>{{ $po->createdBy->nama }}</small></td>
                            <td>@rupiah($po->total_amount)</td>
                            <td>
                                <a href="{{ route('admin.purchase-orders.show', $po->id) }}" class="btn btn-xs btn-primary">Proses</a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    {{-- 2. APPROVAL MUTASI (KG & KC) --}}
    <div class="col-lg-6">
        <div class="card card-info card-outline">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-exchange-alt"></i> Approval Mutasi Stok</h3>
            </div>
            <div class="card-body p-0">
                @if($data['pendingMutations']->isEmpty())
                    <div class="p-3 text-center text-muted">Tidak ada permintaan mutasi.</div>
                @else
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>No Mutasi</th>
                                <th>Barang</th>
                                <th>Tujuan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($data['pendingMutations'] as $m)
                            <tr>
                                <td><a href="{{ route('admin.stock-mutations.show', $m->id) }}">{{ $m->nomor_mutasi }}</a></td>
                                <td>{{ $m->barang->part_name ?? '-' }}</td>
                                <td>{{ $m->lokasiTujuan->nama_lokasi }}</td>
                                <td>
                                    <a href="{{ route('admin.stock-mutations.show', $m->id) }}" class="btn btn-xs btn-info">Proses</a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>

    {{-- 3. APPROVAL ADJUSTMENT (KG & KC) --}}
    <div class="col-lg-6">
        <div class="card card-danger card-outline">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-sliders-h"></i> Approval Penyesuaian Stok</h3>
            </div>
            <div class="card-body p-0">
                @if($data['pendingAdjustments']->isEmpty())
                    <div class="p-3 text-center text-muted">Tidak ada permintaan penyesuaian.</div>
                @else
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Barang</th>
                                <th>Tipe</th>
                                <th>Jumlah</th>
                                <th>Alasan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($data['pendingAdjustments'] as $adj)
                            <tr>
                                <td>{{ $adj->barang->part_name ?? '-' }}</td>
                                <td><span class="badge {{ $adj->tipe == 'TAMBAH' ? 'badge-success' : 'badge-danger' }}">{{ $adj->tipe }}</span></td>
                                <td>{{ $adj->jumlah }}</td>
                                <td><small>{{ Str::limit($adj->alasan, 20) }}</small></td>
                                <td>
                                    {{-- Adjustment biasanya diproses di index/modal, buat link ke index filter ID --}}
                                    <a href="{{ route('admin.stock-adjustments.index') }}" class="btn btn-xs btn-danger">Lihat</a>
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
