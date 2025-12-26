<div class="row mb-2">
    <div class="col-12">
        <h4><i class="fas fa-user-tie"></i> Dashboard Kepala Gudang</h4>
        <p class="text-muted">Lokasi: <strong>{{ $data['lokasi']->nama_lokasi ?? 'Pusat' }}</strong></p>
    </div>
</div>

<div class="row">
    {{-- 1. APPROVAL PO SUPPLIER (UTAMA) --}}
    <div class="col-lg-12 mb-3">
        <div class="card card-warning card-outline">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-truck text-warning"></i> 
                    Purchase Order ke Supplier (Menunggu Persetujuan Anda)
                </h3>
                <div class="card-tools">
                    <span class="badge badge-warning">{{ $data['pendingSupplierPOs']->count() }} Pending</span>
                </div>
            </div>
            <div class="card-body p-0">
                @if($data['pendingSupplierPOs']->isEmpty())
                    <div class="text-center p-4 text-muted">
                        <i class="fas fa-check-circle fa-2x mb-2 text-success"></i><br>
                        Tidak ada PO Supplier yang perlu disetujui.
                    </div>
                @else
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>No PO</th>
                                <th>Tanggal</th>
                                <th>Supplier</th>
                                <th>Pembuat</th>
                                <th class="text-right">Total Nilai</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($data['pendingSupplierPOs'] as $po)
                            <tr>
                                <td><a href="{{ route('admin.purchase-orders.show', $po->id) }}"><b>{{ $po->nomor_po }}</b></a></td>
                                <td>{{ $po->tanggal_po->format('d/m/Y') }}</td>
                                <td>{{ $po->supplier->nama_supplier }}</td>
                                <td>{{ $po->createdBy->nama }}</td>
                                <td class="text-right">@rupiah($po->total_amount)</td>
                                <td class="text-center">
                                    <a href="{{ route('admin.purchase-orders.show', $po->id) }}" class="btn btn-sm btn-primary">
                                        <i class="fas fa-search"></i> Review
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
            <div class="card-footer text-center">
                <a href="{{ route('admin.purchase-orders.index', ['type' => 'supplier_po']) }}" class="uppercase">Lihat Semua PO Supplier</a>
            </div>
        </div>
    </div>

    {{-- 2. APPROVAL MUTASI --}}
    <div class="col-lg-6">
        <div class="card card-info card-outline">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-exchange-alt"></i> Approval Mutasi Keluar</h3>
            </div>
            <div class="card-body p-0">
                @if($data['pendingMutations']->isEmpty())
                    <div class="p-3 text-center text-muted">Aman. Tidak ada pendingan.</div>
                @else
                    <table class="table table-sm">
                        @foreach($data['pendingMutations'] as $m)
                        <tr>
                            <td>{{ $m->nomor_mutasi }}</td>
                            <td>{{ $m->barang->part_name }}</td>
                            <td class="text-right">
                                <a href="{{ route('admin.stock-mutations.show', $m->id) }}" class="btn btn-xs btn-info">Cek</a>
                            </td>
                        </tr>
                        @endforeach
                    </table>
                @endif
            </div>
        </div>
    </div>

    {{-- 3. APPROVAL ADJUSTMENT --}}
    <div class="col-lg-6">
        <div class="card card-danger card-outline">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-sliders-h"></i> Approval Stock Opname</h3>
            </div>
            <div class="card-body p-0">
                @if($data['pendingAdjustments']->isEmpty())
                    <div class="p-3 text-center text-muted">Stok aman terkendali.</div>
                @else
                    <table class="table table-sm">
                        @foreach($data['pendingAdjustments'] as $adj)
                        <tr>
                            <td>{{ $adj->barang->part_name }}</td>
                            <td>
                                <span class="badge {{ $adj->tipe == 'TAMBAH' ? 'badge-success' : 'badge-danger' }}">
                                    {{ $adj->tipe }} {{ $adj->jumlah }}
                                </span>
                            </td>
                            <td class="text-right">
                                <a href="{{ route('admin.stock-adjustments.index') }}" class="btn btn-xs btn-danger">Cek</a>
                            </td>
                        </tr>
                        @endforeach
                    </table>
                @endif
            </div>
        </div>
    </div>
</div>