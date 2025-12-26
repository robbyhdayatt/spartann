<div class="row mb-3">
    <div class="col-12">
        <h4 class="text-dark"><i class="fas fa-user-secret mr-2"></i> Super Admin Control Tower</h4>
        <p class="text-muted">Pemantauan Menyeluruh (IT, Finance, Management, Operations)</p>
    </div>
</div>

<div class="card card-primary card-outline card-outline-tabs shadow-none">
    <div class="card-header p-0 border-bottom-0">
        <ul class="nav nav-tabs" id="custom-tabs-four-tab" role="tablist">
            {{-- TAB 1: IT SYSTEM (Default) --}}
            <li class="nav-item">
                <a class="nav-link active" id="tab-it-link" data-toggle="pill" href="#tab-it" role="tab">
                    <i class="fas fa-server mr-1"></i> IT & System Health
                </a>
            </li>
            {{-- TAB 2: FINANCE --}}
            <li class="nav-item">
                <a class="nav-link" id="tab-fin-link" data-toggle="pill" href="#tab-fin" role="tab">
                    <i class="fas fa-chart-line mr-1"></i> Financial (ACC)
                </a>
            </li>
            {{-- TAB 3: MANAGEMENT --}}
            <li class="nav-item">
                <a class="nav-link" id="tab-mgt-link" data-toggle="pill" href="#tab-mgt" role="tab">
                    <i class="fas fa-briefcase mr-1"></i> Management (PIC)
                    @if($data['totalPending'] > 0)
                        <span class="badge badge-warning right">{{ $data['totalPending'] }}</span>
                    @endif
                </a>
            </li>
            {{-- TAB 4: OPERATIONS --}}
            <li class="nav-item">
                <a class="nav-link" id="tab-ops-link" data-toggle="pill" href="#tab-ops" role="tab">
                    <i class="fas fa-boxes mr-1"></i> Operations & Stock
                </a>
            </li>
        </ul>
    </div>
    
    <div class="card-body">
        <div class="tab-content" id="custom-tabs-four-tabContent">
            
            {{-- ================= TAB 1: IT & SYSTEM HEALTH ================= --}}
            <div class="tab-pane fade show active" id="tab-it" role="tabpanel">
                <div class="row">
                    {{-- Alert Bug Detector --}}
                    <div class="col-md-4">
                        <div class="info-box shadow-sm {{ $data['negativeStockCount'] > 0 ? 'bg-danger' : 'bg-success' }}">
                            <span class="info-box-icon"><i class="fas fa-bug"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Stok Minus (Data Integrity)</span>
                                <span class="info-box-number">{{ $data['negativeStockCount'] }} Record</span>
                                <span class="progress-description text-sm">
                                    {{ $data['negativeStockCount'] > 0 ? 'PERHATIAN: Ada bug race condition!' : 'Database Konsisten.' }}
                                </span>
                            </div>
                        </div>
                    </div>
                    {{-- DB Load --}}
                    <div class="col-md-4">
                        <div class="info-box shadow-sm bg-info">
                            <span class="info-box-icon"><i class="fas fa-database"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Insert Load (Hari Ini)</span>
                                <span class="info-box-number">{{ number_format($data['todayMovements']) }} Rows</span>
                                <span class="progress-description">Tabel Stock Movements</span>
                            </div>
                        </div>
                    </div>
                    {{-- User Stats --}}
                    <div class="col-md-4">
                        <div class="info-box shadow-sm bg-secondary">
                            <span class="info-box-icon"><i class="fas fa-users-cog"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">User & Lokasi</span>
                                <span class="info-box-number">{{ $data['totalUsers'] }} User / {{ $data['totalWarehouses'] }} Lokasi</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card card-outline card-dark">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-satellite-dish mr-1 text-primary"></i> Live System Activity Feed</h3>
                            </div>
                            <div class="card-body p-0 table-responsive" style="height: 350px;">
                                <table class="table table-head-fixed text-nowrap table-striped table-sm">
                                    <thead>
                                        <tr>
                                            <th>Waktu</th>
                                            <th>User</th>
                                            <th>Lokasi</th>
                                            <th>Barang</th>
                                            <th>Aktivitas</th>
                                            <th class="text-right">Qty</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($data['recentActivities'] as $log)
                                            <tr>
                                                <td class="text-muted small">{{ $log->created_at->format('H:i:s') }}</td>
                                                <td class="font-weight-bold">{{ $log->user->username ?? 'System' }}</td>
                                                <td><span class="badge badge-light">{{ $log->lokasi->kode_lokasi ?? '-' }}</span></td>
                                                <td>{{ Str::limit($log->barang->part_name ?? '-', 20) }}</td>
                                                <td class="small">{{ Str::limit($log->keterangan, 40) }}</td>
                                                <td class="text-right {{ $log->jumlah > 0 ? 'text-success' : 'text-danger' }} font-weight-bold">
                                                    {{ $log->jumlah }}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="6" class="text-center">Tidak ada aktivitas.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ================= TAB 2: FINANCIAL ================= --}}
            <div class="tab-pane fade" id="tab-fin" role="tabpanel">
                <div class="row">
                    <div class="col-lg-4 col-6">
                        <div class="small-box bg-success">
                            <div class="inner">
                                <h3>Rp {{ number_format($data['revenueThisMonth'], 0, ',', '.') }}</h3>
                                <p>Omset Bulan Ini</p>
                            </div>
                            <div class="icon"><i class="fas fa-money-bill-wave"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-6">
                        <div class="small-box bg-primary">
                            <div class="inner">
                                <h3>Rp {{ number_format($data['inventoryAssetValue'], 0, ',', '.') }}</h3>
                                <p>Total Aset Inventory</p>
                            </div>
                            <div class="icon"><i class="fas fa-warehouse"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-12">
                        <div class="small-box bg-warning">
                            <div class="inner">
                                <h3>Rp {{ number_format($data['profitThisMonth'], 0, ',', '.') }}</h3>
                                <p>Est. Gross Profit (Bulan Ini)</p>
                            </div>
                            <div class="icon"><i class="fas fa-percent"></i></div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header border-0">
                                <h3 class="card-title">Tren Pendapatan (Global)</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="saRevenueChart" style="height: 250px;"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ================= TAB 3: MANAGEMENT ================= --}}
            <div class="tab-pane fade" id="tab-mgt" role="tabpanel">
                <div class="row">
                    <div class="col-md-12">
                        @if($data['totalPending'] > 0)
                            <div class="alert alert-warning">
                                <h5><i class="icon fas fa-exclamation-triangle"></i> Pending Approvals (Global)</h5>
                                Ada <b>{{ $data['pendingPO'] }}</b> PO, <b>{{ $data['pendingMutasi'] }}</b> Mutasi, dan <b>{{ $data['pendingAdjustment'] }}</b> Adjustment menunggu persetujuan di sistem.
                            </div>
                        @else
                            <div class="alert alert-success">
                                <i class="icon fas fa-check"></i> Tidak ada dokumen pending (All Clear).
                            </div>
                        @endif
                    </div>
                </div>
                <div class="row">
                    <div class="col-lg-6">
                        <div class="card card-outline card-primary">
                            <div class="card-header">
                                <h3 class="card-title">Top 5 Cabang (Omset Bulan Ini)</h3>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-striped">
                                    <thead><tr><th>Cabang</th><th class="text-right">Omset</th></tr></thead>
                                    <tbody>
                                        @forelse($data['topCabang'] as $cabang)
                                            <tr>
                                                <td>{{ $cabang->nama_lokasi }}</td>
                                                <td class="text-right font-weight-bold">Rp {{ number_format($cabang->omset, 0, ',', '.') }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="2" class="text-center">Belum ada data.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ================= TAB 4: OPERATIONS ================= --}}
            <div class="tab-pane fade" id="tab-ops" role="tabpanel">
                <div class="row">
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-info">
                            <div class="inner">
                                <h3>{{ $data['globalReceivingPO'] }}</h3>
                                <p>PO Supplier (Open)</p>
                            </div>
                            <div class="icon"><i class="fas fa-truck-loading"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-warning">
                            <div class="inner">
                                <h3>{{ $data['globalPendingQC'] }}</h3>
                                <p>Pending QC (Global)</p>
                            </div>
                            <div class="icon"><i class="fas fa-clipboard-check"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-secondary">
                            <div class="inner">
                                <h3>{{ $data['globalPendingPutaway'] }}</h3>
                                <p>Pending Putaway (Global)</p>
                            </div>
                            <div class="icon"><i class="fas fa-dolly"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-purple">
                            <div class="inner">
                                <h3>{{ number_format($data['totalItemsSoldMonth']) }}</h3>
                                <p>Total Item Terjual (Bulan Ini)</p>
                            </div>
                            <div class="icon"><i class="fas fa-shopping-basket"></i></div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card card-outline card-danger">
                            <div class="card-header">
                                <h3 class="card-title">Stok Kritis Global (Top 10)</h3>
                            </div>
                            <div class="card-body p-0 table-responsive">
                                <table class="table table-striped table-sm">
                                    <thead>
                                        <tr>
                                            <th>Part Number</th>
                                            <th>Nama Barang</th>
                                            <th class="text-center">Total Fisik</th>
                                            <th class="text-center">Min. Stok</th>
                                            <th class="text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($data['criticalItems'] as $item)
                                            <tr>
                                                <td>{{ $item->part_code }}</td>
                                                <td>{{ $item->part_name }}</td>
                                                <td class="text-center font-weight-bold">{{ $item->total_qty }}</td>
                                                <td class="text-center">{{ $item->stok_minimum }}</td>
                                                <td class="text-center"><span class="badge badge-danger">CRITICAL</span></td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="5" class="text-center text-success">Stok Global Aman.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

@push('js')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Chart hanya di-render jika Tab Finance aktif atau di-load awal
    var ctx = document.getElementById('saRevenueChart').getContext('2d');
    var chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: {!! json_encode($data['chartLabels']) !!},
            datasets: [{
                label: 'Pendapatan Harian (Rp)',
                backgroundColor: 'rgba(60, 141, 188, 0.1)',
                borderColor: '#3c8dbc',
                data: {!! json_encode($data['chartData']) !!},
                fill: true
            }]
        },
        options: {
            maintainAspectRatio: false,
            responsive: true,
            scales: { y: { beginAtZero: true } }
        }
    });
</script>
@endpush