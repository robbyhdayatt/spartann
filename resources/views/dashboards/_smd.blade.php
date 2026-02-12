{{-- HEADER DASHBOARD --}}
<div class="row mb-3">
    <div class="col-12">
        <h4><i class="fas fa-tachometer-alt"></i> Dashboard {{ $data['isSMD'] ? 'Inventory MD Shop (Monitoring Area)' : 'Sales Counter' }}</h4>
    </div>
</div>

@if($data['isSMD'])
    {{-- ================= TAMPILAN KHUSUS SERVICE MD ================= --}}
    
    <div class="row">
        {{-- Tombol Action Cepat --}}
        <div class="col-md-12 mb-3">
            <a href="{{ route('admin.purchase-orders.create') }}" class="btn btn-primary btn-lg shadow-sm">
                <i class="fas fa-plus-circle mr-2"></i> Buat Request PO (Restock Dealer)
            </a>
        </div>
    </div>

    <div class="row">
        {{-- TABEL MONITORING STOK --}}
        <div class="col-lg-8">
            <div class="card card-outline card-info">
                <div class="card-header border-0">
                    <h3 class="card-title">
                        <i class="fas fa-boxes mr-1"></i> Monitoring Stok Jaringan Dealer
                    </h3>
                    <div class="card-tools">
                        {{-- Link ke Laporan Stok Per Lokasi (sudah bisa difilter user pusat) --}}
                        <a href="{{ route('admin.reports.stock-by-warehouse') }}" class="btn btn-tool btn-sm">
                            <i class="fas fa-bars"></i> Lihat Detail Semua
                        </a>
                    </div>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-striped table-valign-middle">
                        <thead>
                        <tr>
                            <th>Nama Dealer</th> {{-- Kolom Baru --}}
                            <th>Barang / Part</th>
                            <th class="text-center">Stok</th>
                            <th class="text-center">Min</th>
                            <th class="text-center">Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($data['stockData'] as $item)
                            <tr>
                                <td>
                                    <span class="font-weight-bold text-dark">{{ $item->nama_lokasi }}</span>
                                </td>
                                <td>
                                    {{ $item->part_name }}
                                    <br><small class="text-muted">{{ $item->part_code }}</small>
                                </td>
                                <td class="text-center font-weight-bold" style="font-size: 1.1em">
                                    {{ $item->total_qty }}
                                </td>
                                <td class="text-center text-muted">
                                    {{ $item->stok_minimum }}
                                </td>
                                <td class="text-center">
                                    @if($item->total_qty < $item->stok_minimum)
                                        <span class="badge badge-danger">CRITICAL</span>
                                    @else
                                        <span class="badge badge-success">AMAN</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">Belum ada data stok di jaringan dealer.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- STATUS REQUEST TERAKHIR --}}
        <div class="col-lg-4">
            <div class="card card-outline card-warning">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-history mr-1"></i> Request Terakhir Saya</h3>
                </div>
                <div class="card-body p-0">
                    <ul class="products-list product-list-in-card pl-2 pr-2">
                        @forelse($data['myRequests'] as $po)
                            <li class="item">
                                <div class="product-info ml-0">
                                    <a href="{{ route('admin.purchase-orders.show', $po->id) }}" class="product-title">
                                        {{ $po->nomor_po }}
                                        <span class="badge float-right {{ $po->status == 'APPROVED' ? 'badge-success' : ($po->status == 'REJECTED' ? 'badge-danger' : 'badge-warning') }}">
                                            {{ $po->status }}
                                        </span>
                                    </a>
                                    <span class="product-description">
                                        {{ $po->tanggal_po->format('d M Y') }} - Ke: {{ $po->lokasi->nama_lokasi ?? 'Unknown' }}
                                    </span>
                                </div>
                            </li>
                        @empty
                            <li class="item text-center p-3 text-muted">Belum ada request.</li>
                        @endforelse
                    </ul>
                </div>
                <div class="card-footer text-center">
                    <a href="{{ route('admin.purchase-orders.index') }}" class="uppercase">Lihat Semua Request</a>
                </div>
            </div>
        </div>
    </div>

@else
    {{-- ================= TAMPILAN SALES COUNTER (TETAP) ================= --}}
    <div class="row">
        <div class="col-lg-3 col-6">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3>Rp {{ number_format($data['achievedAmount'], 0, ',', '.') }}</h3>
                    <p>Penjualan Bulan Ini</p>
                </div>
                <div class="icon"><i class="fas fa-shopping-cart"></i></div>
            </div>
        </div>
        {{-- ... widget sales lainnya ... --}}
    </div>
@endif