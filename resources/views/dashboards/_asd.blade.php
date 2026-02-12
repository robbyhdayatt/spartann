<div class="row mb-3">
    <div class="col-12">
        <h4 class="text-dark"><i class="fas fa-cogs mr-2"></i> Dashboard Area Service Development (ASD)</h4>
        <p class="text-muted">Halo, <strong>{{ Auth::user()->nama }}</strong>! Kelola stok, persetujuan mutasi, dan master data di sini.</p>
    </div>
</div>

{{-- ROW 1: SHORTCUTS & MASTER DATA --}}
<div class="row">
    {{-- Total Barang --}}
    <div class="col-12 col-sm-6 col-md-3">
        <div class="info-box shadow-sm">
            <span class="info-box-icon bg-info elevation-1"><i class="fas fa-box-open"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Total Master Barang</span>
                <span class="info-box-number">{{ number_format($data['totalItems']) }} SKU</span>
                <a href="#" class="small text-muted stretched-link">Lihat / Edit Barang <i class="fas fa-arrow-right ml-1"></i></a>
            </div>
        </div>
    </div>

    {{-- Total Convert --}}
    <div class="col-12 col-sm-6 col-md-3">
        <div class="info-box shadow-sm">
            <span class="info-box-icon bg-purple elevation-1"><i class="fas fa-sync-alt"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Item Convert</span>
                <span class="info-box-number">{{ number_format($data['totalConvertItems']) }} Paket</span>
                <a href="#" class="small text-muted stretched-link">Kelola Convert <i class="fas fa-arrow-right ml-1"></i></a>
            </div>
        </div>
    </div>

</div>

{{-- ROW 2: MONITORING TRANSAKSI & ALERT --}}
<div class="row">
    {{-- Service Hari Ini --}}
    <div class="col-lg-4 col-6">
        <div class="small-box bg-teal">
            <div class="inner">
                <h3>{{ $data['servicesToday'] }}</h3>
                <p>Service Hari Ini</p>
            </div>
            <div class="icon"><i class="fas fa-tools"></i></div>
        </div>
    </div>

    {{-- Penjualan Hari Ini --}}
    <div class="col-lg-4 col-6">
        <div class="small-box bg-success">
            <div class="inner">
                <h3>{{ $data['salesToday'] }}</h3>
                <p>Penjualan Part Hari Ini</p>
            </div>
            <div class="icon"><i class="fas fa-shopping-cart"></i></div>
        </div>
    </div>

    {{-- Pending Approval Mutasi --}}
    <div class="col-lg-4 col-12">
        <div class="small-box {{ $data['countPendingMutations'] > 0 ? 'bg-danger' : 'bg-secondary' }}">
            <div class="inner">
                <h3>{{ $data['countPendingMutations'] }}</h3>
                <p>Mutasi Menunggu Approval</p>
            </div>
            <div class="icon"><i class="fas fa-exchange-alt"></i></div>
        </div>
    </div>
</div>

{{-- ROW 3: TABEL APPROVAL & WARNING --}}
<div class="row">
    {{-- KOLOM KIRI: DAFTAR MUTASI PENDING --}}
    <div class="col-lg-8">
        <div class="card card-outline card-danger">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-exclamation-circle mr-1"></i> Perlu Persetujuan (Mutasi Stok)
                </h3>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-striped table-sm">
                    <thead>
                        <tr>
                            <th>No. Ref</th>
                            <th>Tanggal</th>
                            <th>Dari</th>
                            <th>Ke</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- Gunakan pengecekan data agar tidak error --}}
                        @if(isset($data['pendingMutations']) && count($data['pendingMutations']) > 0)
                            @foreach($data['pendingMutations'] as $mutasi)
                                <tr>
                                    <td>{{ $mutasi->nomor_mutasi ?? $mutasi->id }}</td>
                                    <td>{{ $mutasi->created_at->format('d/m/Y') }}</td>
                                    <td>{{ $mutasi->lokasiAsal->nama_lokasi ?? '-' }}</td>
                                    <td>{{ $mutasi->lokasiTujuan->nama_lokasi ?? '-' }}</td>
                                    <td class="text-center">
                                        {{-- Link ke Show Mutasi --}}
                                        @if(Route::has('admin.stock-mutations.show'))
                                            <a href="{{ route('admin.stock-mutations.show', $mutasi->id) }}" class="btn btn-xs btn-primary">
                                                <i class="fas fa-search"></i> Review
                                            </a>
                                        @else
                                            <span class="badge badge-warning">Route Error</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td colspan="5" class="text-center text-muted py-3">Tidak ada mutasi yang menunggu persetujuan.</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Tabel Service Terakhir --}}
        <div class="card card-outline card-teal mt-3">
            <div class="card-header">
                <h3 class="card-title">5 Service Terakhir</h3>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Invoice</th>
                            <th>Mekanik</th>
                            <th>Customer</th>
                            <th class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($data['recentServices'] as $svc)
                            <tr>
                                <td>{{ $svc->invoice_no }}</td>
                                <td>{{ $svc->technician_name }}</td>
                                <td>{{ Str::limit($svc->customer_name, 15) }}</td>
                                <td class="text-right">{{ number_format($svc->total_amount, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center">Belum ada service.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- KOLOM KANAN: STOK MENIPIS --}}
    {{-- KOLOM KANAN: MONITORING STOK JARINGAN DEALER --}}
    <div class="col-lg-4">
        <div class="card card-outline card-primary">
            <div class="card-header border-0">
                <h3 class="card-title">
                    <i class="fas fa-network-wired mr-1"></i> Monitoring Stok Dealer
                </h3>
                <div class="card-tools">
                    <a href="{{ route('admin.reports.stock-by-warehouse') }}" class="btn btn-tool btn-sm">
                        <i class="fas fa-bars"></i>
                    </a>
                </div>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-striped table-valign-middle table-sm">
                    <thead>
                    <tr>
                        <th>Dealer</th>
                        <th>Part</th>
                        <th class="text-center">Stok</th>
                        <th class="text-center">Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($data['stockData'] as $item)
                        <tr>
                            <td>
                                <span class="font-weight-bold text-dark" style="font-size: 0.9rem;">{{ $item->nama_lokasi }}</span>
                            </td>
                            <td>
                                {{ Str::limit($item->part_name, 15) }}
                                <br>
                                <small class="text-muted">{{ $item->part_code }}</small>
                            </td>
                            <td class="text-center font-weight-bold">
                                {{ $item->total_qty }}
                            </td>
                            <td class="text-center">
                                @if($item->total_qty < $item->stok_minimum)
                                    <span class="badge badge-danger">KRITIS</span>
                                @else
                                    <span class="badge badge-success">AMAN</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">Belum ada data stok dealer.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>