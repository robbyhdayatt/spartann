<div class="row mb-3">
    <div class="col-12">
        <h4 class="text-dark"><i class="fas fa-briefcase mr-2"></i> Dashboard Admin Gudang</h4>
        <p class="text-muted"></p>
    </div>
</div>

<div class="row">
    {{-- WIDGET 1: PO REQUEST SAYA (PENDING APPROVAL) --}}
    <div class="col-lg-3 col-6">
        <div class="small-box bg-warning">
            <div class="inner">
                <h3>{{ $data['pendingApprovalPO'] }}</h3>
                <p>Request PO (Pending)</p>
            </div>
            <div class="icon">
                <i class="fas fa-file-signature"></i>
            </div>
            <a href="{{ route('admin.purchase-orders.index') }}" class="small-box-footer">
                Lihat Status <i class="fas fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>

    {{-- WIDGET 2: SIAP RECEIVING (PO APPROVED) --}}
    <div class="col-lg-3 col-6">
        <div class="small-box bg-info">
            <div class="inner">
                <h3>{{ $data['readyToReceivePO'] }}</h3>
                <p>PO Siap Terima</p>
            </div>
            <div class="icon">
                <i class="fas fa-truck-loading"></i>
            </div>
            <a href="{{ route('admin.receivings.create') }}" class="small-box-footer">
                Proses Penerimaan <i class="fas fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>

    {{-- WIDGET 3: MENUNGGU QC --}}
    <div class="col-lg-3 col-6">
        <div class="small-box bg-danger">
            <div class="inner">
                <h3>{{ $data['pendingQC'] }}</h3>
                <p>Menunggu QC</p>
            </div>
            <div class="icon">
                <i class="fas fa-clipboard-check"></i>
            </div>
            <a href="{{ route('admin.qc.index') }}" class="small-box-footer">
                Lakukan QC <i class="fas fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>

    {{-- WIDGET 4: MENUNGGU PUTAWAY --}}
    <div class="col-lg-3 col-6">
        <div class="small-box bg-success">
            <div class="inner">
                <h3>{{ $data['pendingPutaway'] }}</h3>
                <p>Siap Putaway (Rak)</p>
            </div>
            <div class="icon">
                <i class="fas fa-dolly"></i>
            </div>
            <a href="{{ route('admin.putaway.index') }}" class="small-box-footer">
                Atur Rak <i class="fas fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>
</div>

<div class="row">
    {{-- CARD 1: AKTIVITAS PENERIMAAN TERAKHIR --}}
    <div class="col-md-7">
        <div class="card card-primary card-outline">
            <div class="card-header border-0">
                <h3 class="card-title">
                    <i class="fas fa-history mr-1"></i> Penerimaan Terakhir
                </h3>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-valign-middle">
                    <thead>
                    <tr>
                        <th>No Terima</th>
                        <th>Sumber</th>
                        <th>Tanggal</th>
                        <th>Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($data['recentReceivings'] as $recv)
                        <tr>
                            <td>
                                <a href="{{ route('admin.receivings.show', $recv->id) }}" class="text-bold">
                                    {{ $recv->nomor_penerimaan }}
                                </a>
                            </td>
                            <td>
                                @if($recv->purchaseOrder && $recv->purchaseOrder->supplier)
                                    {{ Str::limit($recv->purchaseOrder->supplier->nama_supplier, 20) }}
                                @elseif($recv->purchaseOrder && $recv->purchaseOrder->sumberLokasi)
                                    {{ $recv->purchaseOrder->sumberLokasi->nama_lokasi }}
                                @else
                                    -
                                @endif
                            </td>
                            <td>{{ \Carbon\Carbon::parse($recv->tanggal_terima)->format('d M Y') }}</td>
                            <td>
                                @php
                                    $badge = 'secondary';
                                    if($recv->status == 'COMPLETED') $badge = 'success';
                                    if($recv->status == 'PENDING_QC') $badge = 'warning';
                                    if($recv->status == 'PENDING_PUTAWAY') $badge = 'info';
                                @endphp
                                <span class="badge badge-{{ $badge }}">
                                    {{ str_replace('_', ' ', $recv->status) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">Belum ada data penerimaan.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- CARD 2: PERINGATAN STOK MINIMUM --}}
    <div class="col-md-5">
        <div class="card card-danger card-outline">
            <div class="card-header border-0">
                <h3 class="card-title">
                    <i class="fas fa-exclamation-triangle mr-1"></i> Stok Menipis (Alert)
                </h3>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm">
                    <thead>
                    <tr>
                        <th>Part</th>
                        <th class="text-right">Sisa</th>
                        <th class="text-right">Min</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($data['stockAlerts'] as $item)
                        <tr>
                            <td>
                                <span class="d-block text-bold">{{ $item->part_code }}</span>
                                <small>{{ Str::limit($item->part_name, 15) }}</small>
                            </td>
                            <td class="text-right text-danger text-bold">
                                {{ number_format($item->total_qty) }}
                            </td>
                            <td class="text-right text-muted">
                                {{ number_format($item->stok_minimum) }}
                            </td>
                            <td class="text-right">
                                <a href="{{ route('admin.purchase-orders.create') }}" class="btn btn-xs btn-primary" title="Restock">
                                    <i class="fas fa-plus"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">
                                <i class="fas fa-check-circle text-success mb-2" style="font-size: 20px;"></i><br>
                                Stok aman.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer text-center">
                <a href="{{ route('admin.reports.stock-by-warehouse') }}">Lihat Laporan Stok Lengkap</a>
            </div>
        </div>
    </div>
</div>