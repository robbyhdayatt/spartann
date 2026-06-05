@php
    $fmtStart = \Carbon\Carbon::parse($data['filter']['startDate'])->translatedFormat('d M Y');
    $fmtEnd = \Carbon\Carbon::parse($data['filter']['endDate'])->translatedFormat('d M Y');
    $fmtPrevStart = \Carbon\Carbon::parse($data['filter']['prevStartDate'])->translatedFormat('d M Y');
    $fmtPrevEnd = \Carbon\Carbon::parse($data['filter']['prevEndDate'])->translatedFormat('d M Y');
    
    $lblCurrent = "Periode Filter ($fmtStart - $fmtEnd)";
    $lblPrevious = "Bulan Lalu ($fmtPrevStart - $fmtPrevEnd)";
@endphp

{{-- ========================================================== --}}
{{-- SUPER ADMIN COMMAND CENTER (ENHANCED UI)                   --}}
{{-- ========================================================== --}}

<div class="row mb-4 align-items-center">
    <div class="col-md-8">
        <h3 class="m-0 text-dark font-weight-bold">
            <i class="fas fa-satellite-dish text-primary mr-2"></i> Command Center <span class="text-muted font-weight-light">| Super Admin</span>
        </h3>
        <p class="text-muted text-sm mb-0 mt-1">Pemantauan Menyeluruh: IT, Finance, Management & Operations</p>
    </div>
    <div class="col-md-4 text-right">
        <span class="badge badge-success px-3 py-2 shadow-sm" style="font-size: 0.9rem;">
            <i class="fas fa-check-circle mr-1"></i> System Online
        </span>
    </div>
</div>

<div class="card card-primary card-outline card-outline-tabs shadow-sm border-0">
    <div class="card-header p-0 border-bottom-0 bg-light">
        <ul class="nav nav-tabs" id="custom-tabs-four-tab" role="tablist">
            <li class="nav-item">
                <a class="nav-link active font-weight-bold" id="tab-it-link" data-toggle="pill" href="#tab-it" role="tab">
                    <i class="fas fa-server mr-1 text-secondary"></i> IT & System Health
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link font-weight-bold" id="tab-fin-link" data-toggle="pill" href="#tab-fin" role="tab">
                    <i class="fas fa-chart-line mr-1 text-success"></i> Financial (ACC)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link font-weight-bold" id="tab-mgt-link" data-toggle="pill" href="#tab-mgt" role="tab">
                    <i class="fas fa-briefcase mr-1 text-warning"></i> Management (PIC)
                    @if($data['totalPending'] > 0)
                        <span class="badge badge-danger ml-1 shadow-sm">{{ $data['totalPending'] }}</span>
                    @endif
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link font-weight-bold" id="tab-ops-link" data-toggle="pill" href="#tab-ops" role="tab">
                    <i class="fas fa-boxes mr-1 text-info"></i> Operations & Stock
                </a>
            </li>
        </ul>
    </div>
    
    <div class="card-body bg-white rounded-bottom">
        <div class="tab-content" id="custom-tabs-four-tabContent">
            
            {{-- ================= TAB 1: IT & SYSTEM HEALTH ================= --}}
            <div class="tab-pane fade show active" id="tab-it" role="tabpanel">
                <div class="row">
                    <div class="col-md-4">
                        <div class="info-box shadow-sm border {{ $data['negativeStockCount'] > 0 ? 'border-danger bg-danger text-white' : 'border-success bg-white' }}">
                            <span class="info-box-icon {{ $data['negativeStockCount'] > 0 ? '' : 'text-success' }}"><i class="fas fa-bug"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text font-weight-bold">Integritas Data (Stok Minus)</span>
                                <span class="info-box-number" style="font-size: 1.5rem;">{{ $data['negativeStockCount'] }} <small>Record</small></span>
                                <span class="progress-description text-xs">
                                    {{ $data['negativeStockCount'] > 0 ? 'CRITICAL: Terdeteksi Race Condition!' : 'Database Sehat & Konsisten.' }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="info-box shadow-sm border border-info bg-white">
                            <span class="info-box-icon text-info"><i class="fas fa-database"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text text-muted font-weight-bold">Traffic Insert (Hari Ini)</span>
                                <span class="info-box-number text-dark" style="font-size: 1.5rem;">{{ number_format($data['todayMovements']) }} <small>Rows</small></span>
                                <span class="progress-description text-xs text-muted">Tabel Stock Movements</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="info-box shadow-sm border border-secondary bg-white">
                            <span class="info-box-icon text-secondary"><i class="fas fa-network-wired"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text text-muted font-weight-bold">Jaringan Ekosistem</span>
                                <span class="info-box-number text-dark" style="font-size: 1.5rem;">
                                    {{ $data['totalUsers'] }} <small>User</small> <span class="text-muted font-weight-light mx-1">|</span> {{ $data['totalWarehouses'] }} <small>Lokasi</small>
                                </span>
                                <span class="progress-description text-xs text-muted">Total entitas aktif di sistem</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-12">
                        <div class="card shadow-none border">
                            <div class="card-header bg-light border-0">
                                <h3 class="card-title font-weight-bold"><i class="fas fa-stream mr-2 text-primary"></i> Live System Activity Feed</h3>
                                <div class="card-tools"><span class="badge badge-light border">10 Aktivitas Terakhir</span></div>
                            </div>
                            <div class="card-body p-0 table-responsive">
                                <table class="table table-hover table-striped table-sm mb-0">
                                    <thead class="text-muted">
                                        <tr>
                                            <th class="pl-3">Waktu</th>
                                            <th>User</th>
                                            <th>Lokasi</th>
                                            <th>Barang</th>
                                            <th>Detail Aktivitas</th>
                                            <th class="text-center pr-3">Qty</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($data['recentActivities'] as $log)
                                            <tr>
                                                <td class="pl-3 text-muted align-middle"><i class="far fa-clock mr-1"></i>{{ $log->created_at->format('H:i:s') }}</td>
                                                <td class="font-weight-bold align-middle">{{ $log->user->username ?? 'System' }}</td>
                                                <td class="align-middle"><span class="badge badge-dark">{{ $log->lokasi->kode_lokasi ?? 'GLOBAL' }}</span></td>
                                                <td class="align-middle text-truncate" style="max-width: 200px;">{{ $log->barang->part_name ?? '-' }}</td>
                                                <td class="align-middle text-sm">{{ Str::limit($log->keterangan, 45) }}</td>
                                                <td class="text-center pr-3 align-middle">
                                                    <span class="badge {{ $log->jumlah > 0 ? 'badge-success' : 'badge-danger' }} px-2 py-1">
                                                        {{ $log->jumlah > 0 ? '+' : '' }}{{ $log->jumlah }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="6" class="text-center py-4 text-muted"><i class="fas fa-ghost fa-2x mb-2 text-light"></i><br>Belum ada aktivitas terekam.</td></tr>
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
                    <div class="col-lg-4 col-md-6">
                        <div class="small-box bg-gradient-success shadow">
                            <div class="inner">
                                <h3 class="mb-1">Rp {{ number_format($data['grandTotalOmset'], 0, ',', '.') }}</h3>
                                <p class="font-weight-bold mb-0">Omset Global (Sesuai Filter)</p>
                            </div>
                            <div class="icon"><i class="fas fa-coins" style="opacity: 0.4;"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <div class="small-box bg-gradient-info shadow">
                            <div class="inner">
                                <h3 class="mb-1">Rp {{ number_format($data['inventoryAssetValue'], 0, ',', '.') }}</h3>
                                <p class="font-weight-bold mb-0">Valuasi Aset Fisik Gudang</p>
                            </div>
                            <div class="icon"><i class="fas fa-box-open" style="opacity: 0.4;"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-12">
                        <div class="small-box bg-gradient-warning shadow">
                            <div class="inner text-dark">
                                <h3 class="mb-1">Rp {{ number_format($data['grandTotalLaba'], 0, ',', '.') }}</h3>
                                <p class="font-weight-bold mb-0">Est. Laba Kotor (Sesuai Filter)</p>
                            </div>
                            <div class="icon"><i class="fas fa-chart-line" style="opacity: 0.4;"></i></div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-2">
                    <div class="col-12">
                        <div class="card shadow-none border">
                            <div class="card-header bg-light border-0">
                                <h3 class="card-title font-weight-bold"><i class="fas fa-wave-square text-success mr-2"></i> Tren Pendapatan Global</h3>
                            </div>
                            <div class="card-body pt-2">
                                <canvas id="saRevenueChart" style="height: 280px; width: 100%;"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ================= TAB 3: MANAGEMENT (HYBRID PIC DASHBOARD) ================= --}}
            <div class="tab-pane fade" id="tab-mgt" role="tabpanel">
                
                @if($data['totalPending'] > 0)
                <div class="row">
                    <div class="col-12">
                        <div class="alert alert-warning border-left-warning shadow-sm">
                            <h5><i class="icon fas fa-exclamation-triangle"></i> Perhatian! Ada {{ $data['totalPending'] }} Dokumen Menunggu Persetujuan Anda.</h5>
                            <div class="mt-2">
                                @if($data['pendingPO'] > 0)
                                    <a href="{{ route('admin.purchase-orders.index') }}" class="btn btn-warning btn-sm mr-2"><i class="fas fa-file-invoice mr-1"></i> {{ $data['pendingPO'] }} PO Dealer</a>
                                @endif
                                @if($data['pendingMutasi'] > 0)
                                    <a href="{{ route('admin.stock-mutations.index') }}" class="btn btn-warning btn-sm mr-2"><i class="fas fa-exchange-alt mr-1"></i> {{ $data['pendingMutasi'] }} Mutasi Stok</a>
                                @endif
                                @if($data['pendingAdjustment'] > 0)
                                    <a href="{{ route('admin.stock-adjustments.index') }}" class="btn btn-warning btn-sm"><i class="fas fa-sliders-h mr-1"></i> {{ $data['pendingAdjustment'] }} Penyesuaian Stok</a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                {{-- FORM FILTER GANDA (SELECT2 & AJAX YGP) --}}
                <div class="row">
                    <div class="col-12">
                        <div class="card shadow-sm border-0 bg-light">
                            <div class="card-body py-3">
                                <form action="{{ route('admin.home') }}" method="GET">
                                    <div class="row align-items-end">
                                        <div class="col-md-2 mb-2 mb-md-0">
                                            <label class="text-xs text-muted">Tanggal Awal</label>
                                            <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $data['filter']['startDate'] }}">
                                        </div>
                                        <div class="col-md-2 mb-2 mb-md-0">
                                            <label class="text-xs text-muted">Tanggal Akhir</label>
                                            <input type="date" name="end_date" class="form-control form-control-sm" value="{{ $data['filter']['endDate'] }}">
                                        </div>
                                        <div class="col-md-3 mb-2 mb-md-0">
                                            <label class="text-xs text-muted">Filter Dealer</label>
                                            <select name="lokasi_id" class="form-control form-control-sm select2">
                                                <option value="all">Semua Dealer Aktif</option>
                                                @foreach($data['daftarLokasi'] as $loc)
                                                    <option value="{{ $loc->id }}" {{ $data['filter']['filterLokasi'] == $loc->id ? 'selected' : '' }}>{{ $loc->nama_lokasi }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-2 mb-2 mb-md-0">
                                            <label class="text-xs text-primary font-weight-bold">Filter Non-YGP</label>
                                            <select name="barang_id" class="form-control form-control-sm select2">
                                                <option value="all">Semua Non-YGP</option>
                                                @foreach($data['daftarBarang'] as $brg)
                                                    <option value="{{ $brg->id }}" {{ $data['filter']['filterNonYgp'] == $brg->id ? 'selected' : '' }}>{{ $brg->part_name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-2 mb-2 mb-md-0">
                                            <label class="text-xs text-danger font-weight-bold">Filter YGP (AJAX)</label>
                                            <select name="part_code" id="select2-ygp" class="form-control form-control-sm">
                                                @if($data['filter']['filterYgp'] === 'all')
                                                    <option value="all" selected>Semua YGP</option>
                                                @else
                                                    <option value="all">Semua YGP</option>
                                                    <option value="{{ $data['filter']['filterYgp'] }}" selected>{{ $data['selectedYgpName'] }}</option>
                                                @endif
                                            </select>
                                        </div>
                                        <div class="col-md-1">
                                            <button type="submit" class="btn btn-dark btn-sm w-100"><i class="fas fa-filter"></i> Go</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <hr>
                <h5 class="text-primary font-weight-bold mb-3"><i class="fas fa-money-check-alt mr-2"></i> SECTION 1: FINANCIAL METRICS (OMSET)</h5>

                <div class="row">
                    <div class="col-md-4">
                        <div class="small-box bg-white border shadow-sm">
                            <div class="inner text-center py-4">
                                <h3 class="text-success mb-1">Rp {{ number_format($data['grandTotalOmset'], 0, ',', '.') }}</h3>
                                <p class="text-muted font-weight-bold mb-0">Total Pendapatan (Omset)</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="small-box bg-white border shadow-sm">
                            <div class="inner text-center py-4">
                                <h3 class="text-info mb-1">Rp {{ number_format($data['grandTotalLaba'], 0, ',', '.') }}</h3>
                                <p class="text-muted font-weight-bold mb-0">Total Laba Kotor (Gross Profit)</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="small-box {{ $data['grossProfitMargin'] > 15 ? 'bg-primary' : 'bg-warning' }} shadow-sm">
                            <div class="inner text-center py-4 text-white">
                                <h3 class="mb-1">{{ $data['grossProfitMargin'] }} <sup style="font-size: 20px">%</sup></h3>
                                <p class="font-weight-bold mb-0">Gross Profit Margin</p>
                            </div>
                            <div class="icon"><i class="fas fa-percent" style="opacity: 0.3;"></i></div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-header bg-white border-0 pb-1">
                                <h3 class="card-title font-weight-bold"><i class="fas fa-balance-scale-left text-success mr-2"></i> Perbandingan Retail (Omset)</h3>
                                <div class="text-muted text-xs mt-1">{{ $lblCurrent }} vs {{ $lblPrevious }}</div>
                            </div>
                            <div class="card-body"><div style="position: relative; height: 250px; width: 100%;"><canvas id="barCompareRetailOmset"></canvas></div></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-header bg-white border-0 pb-1">
                                <h3 class="card-title font-weight-bold"><i class="fas fa-balance-scale-right text-info mr-2"></i> Perbandingan Service (Omset)</h3>
                                <div class="text-muted text-xs mt-1">{{ $lblCurrent }} vs {{ $lblPrevious }}</div>
                            </div>
                            <div class="card-body"><div style="position: relative; height: 250px; width: 100%;"><canvas id="barCompareServiceOmset"></canvas></div></div>
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-4">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-header bg-white border-0 pb-1"><h3 class="card-title font-weight-bold"><i class="fas fa-chart-pie text-secondary mr-2"></i> Komposisi Omset</h3></div>
                            <div class="card-body"><div style="position: relative; height: 250px; width: 100%;"><canvas id="pieOmset"></canvas></div></div>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-header bg-white border-0 pb-1"><h3 class="card-title font-weight-bold"><i class="fas fa-chart-line text-success mr-2"></i> Tren Omset Harian</h3></div>
                            <div class="card-body"><div style="position: relative; height: 250px; width: 100%;"><canvas id="barOmset"></canvas></div></div>
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-7">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-header bg-white border-0 pb-2"><h3 class="card-title font-weight-bold"><i class="fas fa-medal text-warning mr-2"></i> Top 5 Part Penghasil Omset</h3></div>
                            <div class="card-body p-0">
                                <table class="table table-hover table-striped table-sm mb-0">
                                    <thead class="bg-light text-muted"><tr><th class="pl-4">Nama Barang</th><th class="text-right pr-4">Total Rupiah</th></tr></thead>
                                    <tbody>
                                        @forelse($data['topItemsOmset'] as $item)
                                        <tr><td class="pl-4 align-middle font-weight-bold">{{ $item->name }}</td><td class="text-right pr-4"><h6 class="text-success font-weight-bold mb-0">Rp {{ number_format($item->omset, 0, ',', '.') }}</h6></td></tr>
                                        @empty
                                        <tr><td colspan="2" class="text-center text-muted py-4">Data kosong.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-header bg-white border-0 pb-2"><h3 class="card-title font-weight-bold"><i class="fas fa-store text-danger mr-2"></i> Top 5 Dealer (Omset)</h3></div>
                            <div class="card-body p-0">
                                <table class="table table-hover table-striped table-sm mb-0">
                                    <thead class="bg-light text-muted"><tr><th class="pl-4">Dealer</th><th class="text-right pr-4">Rupiah</th></tr></thead>
                                    <tbody>
                                        @forelse($data['topCabangOmset'] as $cab)
                                        <tr><td class="pl-4 align-middle font-weight-bold">{{ $cab->nama_lokasi }}</td><td class="text-right pr-4"><h6 class="text-primary font-weight-bold mb-0">Rp {{ number_format($cab->omset, 0, ',', '.') }}</h6></td></tr>
                                        @empty
                                        <tr><td colspan="2" class="text-center text-muted py-4">Data kosong.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="mt-5">
                <h5 class="text-danger font-weight-bold mb-3"><i class="fas fa-boxes mr-2"></i> SECTION 2: VOLUME METRICS (PHYSICAL QTY)</h5>

                <div class="row">
                    <div class="col-md-4">
                        <div class="small-box bg-white border shadow-sm">
                            <div class="inner text-center py-4">
                                <h3 class="text-dark mb-1">{{ number_format($data['totalRetailQty'], 0, ',', '.') }} <small style="font-size: 14px">Pcs</small></h3>
                                <p class="text-muted font-weight-bold mb-0">Qty Terjual Kasir (Retail)</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="small-box bg-white border shadow-sm">
                            <div class="inner text-center py-4">
                                <h3 class="text-dark mb-1">{{ number_format($data['totalServiceQty'], 0, ',', '.') }} <small style="font-size: 14px">Pcs</small></h3>
                                <p class="text-muted font-weight-bold mb-0">Qty Terpasang Bengkel (Service)</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="small-box bg-danger shadow-sm">
                            <div class="inner text-center py-4 text-white">
                                <h3 class="mb-1">{{ number_format($data['grandTotalQty'], 0, ',', '.') }} <small style="font-size: 14px">Pcs</small></h3>
                                <p class="font-weight-bold mb-0">Grand Total Part Keluar</p>
                            </div>
                            <div class="icon"><i class="fas fa-cogs" style="opacity: 0.3;"></i></div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-header bg-white border-0 pb-1">
                                <h3 class="card-title font-weight-bold"><i class="fas fa-balance-scale-left text-warning mr-2"></i> Perbandingan Retail (Qty)</h3>
                                <div class="text-muted text-xs mt-1">{{ $lblCurrent }} vs {{ $lblPrevious }}</div>
                            </div>
                            <div class="card-body"><div style="position: relative; height: 250px; width: 100%;"><canvas id="barCompareRetail"></canvas></div></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-header bg-white border-0 pb-1">
                                <h3 class="card-title font-weight-bold"><i class="fas fa-balance-scale-right text-info mr-2"></i> Perbandingan Service (Qty)</h3>
                                <div class="text-muted text-xs mt-1">{{ $lblCurrent }} vs {{ $lblPrevious }}</div>
                            </div>
                            <div class="card-body"><div style="position: relative; height: 250px; width: 100%;"><canvas id="barCompareService"></canvas></div></div>
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-4">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-header bg-white border-0 pb-1"><h3 class="card-title font-weight-bold"><i class="fas fa-chart-pie text-secondary mr-2"></i> Komposisi Qty Part</h3></div>
                            <div class="card-body"><div style="position: relative; height: 250px; width: 100%;"><canvas id="pieQty"></canvas></div></div>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-header bg-white border-0 pb-1"><h3 class="card-title font-weight-bold"><i class="fas fa-chart-bar text-danger mr-2"></i> Tren Qty Keluar Harian</h3></div>
                            <div class="card-body"><div style="position: relative; height: 250px; width: 100%;"><canvas id="barQty"></canvas></div></div>
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-7">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-header bg-white border-0 pb-2"><h3 class="card-title font-weight-bold"><i class="fas fa-fire text-danger mr-2"></i> Top 5 Part Paling Laris (Pcs)</h3></div>
                            <div class="card-body p-0">
                                <table class="table table-hover table-striped table-sm mb-0">
                                    <thead class="bg-light text-muted"><tr><th class="pl-4">Nama Barang</th><th class="text-right pr-4">Total Qty Keluar</th></tr></thead>
                                    <tbody>
                                        @forelse($data['topItemsQty'] as $item)
                                        <tr><td class="pl-4 align-middle font-weight-bold">{{ $item->name }}</td><td class="text-right pr-4"><h6 class="text-danger font-weight-bold mb-0">{{ number_format($item->qty, 0, ',', '.') }} Pcs</h6></td></tr>
                                        @empty
                                        <tr><td colspan="2" class="text-center text-muted py-4">Data kosong.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-header bg-white border-0 pb-2"><h3 class="card-title font-weight-bold"><i class="fas fa-truck-loading text-dark mr-2"></i> Top 5 Dealer (Volume Qty)</h3></div>
                            <div class="card-body p-0">
                                <table class="table table-hover table-striped table-sm mb-0">
                                    <thead class="bg-light text-muted"><tr><th class="pl-4">Dealer</th><th class="text-right pr-4">Qty</th></tr></thead>
                                    <tbody>
                                        @forelse($data['topCabangQty'] as $cab)
                                        <tr><td class="pl-4 align-middle font-weight-bold">{{ $cab->nama_lokasi }}</td><td class="text-right pr-4"><h6 class="text-dark font-weight-bold mb-0">{{ number_format($cab->qty, 0, ',', '.') }} Pcs</h6></td></tr>
                                        @empty
                                        <tr><td colspan="2" class="text-center text-muted py-4">Data kosong.</td></tr>
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
                        <div class="small-box bg-white border border-primary shadow-sm">
                            <div class="inner text-center py-3">
                                <h3 class="text-primary mb-0">{{ $data['globalReceivingPO'] }}</h3>
                                <p class="text-muted text-sm font-weight-bold mb-0">PO Supplier (Open)</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-white border border-warning shadow-sm">
                            <div class="inner text-center py-3">
                                <h3 class="text-warning mb-0">{{ $data['globalPendingQC'] }}</h3>
                                <p class="text-muted text-sm font-weight-bold mb-0">Pending QC (Global)</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-white border border-secondary shadow-sm">
                            <div class="inner text-center py-3">
                                <h3 class="text-secondary mb-0">{{ $data['globalPendingPutaway'] }}</h3>
                                <p class="text-muted text-sm font-weight-bold mb-0">Pending Putaway</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-white border border-success shadow-sm">
                            <div class="inner text-center py-3">
                                <h3 class="text-success mb-0">{{ number_format($data['totalItemsSoldMonth']) }}</h3>
                                <p class="text-muted text-sm font-weight-bold mb-0">Fisik Terjual (Bulan Ini)</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-2">
                    <div class="col-12">
                        <div class="card shadow-none border border-danger">
                            <div class="card-header bg-danger text-white border-0">
                                <h3 class="card-title font-weight-bold"><i class="fas fa-exclamation-circle mr-2"></i> Peringatan Stok Kritis Global (Top 10)</h3>
                            </div>
                            <div class="card-body p-0 table-responsive">
                                <table class="table table-hover table-striped table-sm mb-0">
                                    <thead class="bg-light text-dark">
                                        <tr>
                                            <th class="pl-4">Kode Part</th>
                                            <th>Nama Barang</th>
                                            <th class="text-center">Sisa Stok Fisik</th>
                                            <th class="text-center">Batas Minimum</th>
                                            <th class="text-center pr-4">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($data['criticalItems'] as $item)
                                            <tr>
                                                <td class="pl-4 align-middle"><span class="text-monospace">{{ $item->part_code }}</span></td>
                                                <td class="align-middle font-weight-bold">{{ $item->part_name }}</td>
                                                <td class="text-center align-middle">
                                                    <h5 class="mb-0 font-weight-bold text-danger">{{ $item->total_qty }}</h5>
                                                </td>
                                                <td class="text-center align-middle text-muted">{{ $item->stok_minimum }}</td>
                                                <td class="text-center pr-4 align-middle">
                                                    <span class="badge badge-danger px-2 py-1 pulse-danger">KRITIS</span>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="5" class="text-center py-4 text-success"><i class="fas fa-shield-alt fa-2x mb-2 text-success"></i><br>Ketahanan stok jaringan dealer dalam keadaan prima.</td></tr>
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

<style>
    .pulse-danger { animation: pulse 1.5s infinite; }
    @keyframes pulse {
        0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); }
        70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
        100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
    }
    .nav-tabs .nav-link.active { border-top: 3px solid #007bff; }
</style>

@push('js')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    $(document).ready(function() {
        // [UX HACK]: Mengingat Tab Terakhir Saat Halaman Reload
        $('a[data-toggle="pill"]').on('shown.bs.tab', function (e) {
            localStorage.setItem('activeTabSA', $(e.target).attr('href'));
        });
        var activeTab = localStorage.getItem('activeTabSA');
        if(activeTab){
            $('#custom-tabs-four-tab a[href="' + activeTab + '"]').tab('show');
        }

        $('.select2').select2({ theme: 'bootstrap4' });

        $('#select2-ygp').select2({
            theme: 'bootstrap4',
            placeholder: "Ketik Kode atau Nama YGP...",
            allowClear: true,
            ajax: {
                url: "{{ route('admin.ajax.ygp') }}",
                dataType: 'json',
                delay: 300, 
                data: function (params) { return { q: params.term }; },
                processResults: function (data) { return { results: data.results }; },
                cache: true
            }
        });
    });

    const formatRp = (value) => 'Rp ' + new Intl.NumberFormat('id-ID').format(value);
    const formatQty = (value) => new Intl.NumberFormat('id-ID').format(value) + ' Pcs';

    const lblCurText = "Periode Filter";
    const lblPrevText = "H-1 Bulan Lalu";

    // -------------------------------------------------------------
    // CHART TAB 2: FINANCIAL ACC (SUPER ADMIN)
    // -------------------------------------------------------------
    var ctxSA = document.getElementById('saRevenueChart').getContext('2d');
    var gradientSA = ctxSA.createLinearGradient(0, 0, 0, 400);
    gradientSA.addColorStop(0, 'rgba(40, 167, 69, 0.5)');   
    gradientSA.addColorStop(1, 'rgba(40, 167, 69, 0.0)');

    // Gabungkan array Retail + Service menggunakan JS untuk chart SuperAdmin
    var retailData = {!! json_encode($data['chartRetailOmset'] ?? []) !!};
    var serviceData = {!! json_encode($data['chartServiceOmset'] ?? []) !!};
    var combinedData = retailData.map(function(num, idx) {
        return num + (serviceData[idx] || 0);
    });

    new Chart(ctxSA, {
        type: 'line',
        data: {
            labels: {!! json_encode($data['chartLabels'] ?? []) !!},
            datasets: [{
                label: 'Pendapatan Global Harian (Rp)',
                backgroundColor: gradientSA,
                borderColor: '#28a745',
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#28a745',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
                data: combinedData,
                fill: true,
                tension: 0.4 
            }]
        },
        options: {
            maintainAspectRatio: false,
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            plugins: { tooltip: { callbacks: { label: function(context) { return ' Rp ' + new Intl.NumberFormat('id-ID').format(context.raw || 0); } } } },
            scales: { 
                y: { beginAtZero: true, grid: { borderDash: [5, 5] }, ticks: { callback: function(value) { return 'Rp ' + new Intl.NumberFormat('id-ID', { notation: "compact" }).format(value); } } },
                x: { grid: { display: false } }
            }
        }
    });

    // -------------------------------------------------------------
    // CHARTS TAB 3: MANAGEMENT PIC (HYBRID DASHBOARD)
    // -------------------------------------------------------------
    
    // 1. Pie Omset
    new Chart(document.getElementById('pieOmset').getContext('2d'), {
        type: 'doughnut', data: {
            labels: ['Retail (Omset)', 'Service (Omset)'],
            datasets: [{ data: [{{ $data['omsetPie']['retail'] ?? 0 }}, {{ $data['omsetPie']['service'] ?? 0 }}], backgroundColor: ['#28a745', '#17a2b8'], borderWidth: 0 }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { tooltip: { callbacks: { label: (ctx) => ' ' + ctx.label + ': ' + formatRp(ctx.raw) } } } }
    });

    // 2. Bar Omset (Line)
    new Chart(document.getElementById('barOmset').getContext('2d'), {
        type: 'line', data: {
            labels: {!! json_encode($data['chartLabels'] ?? []) !!},
            datasets: [
                { label: 'Omset Retail', borderColor: '#28a745', data: {!! json_encode($data['chartRetailOmset'] ?? []) !!}, backgroundColor: 'transparent', borderWidth: 2, tension: 0.3 },
                { label: 'Omset Service', borderColor: '#17a2b8', data: {!! json_encode($data['chartServiceOmset'] ?? []) !!}, backgroundColor: 'transparent', borderWidth: 2, tension: 0.3 }
            ]
        },
        options: { 
            responsive: true, maintainAspectRatio: false,
            scales: { y: { beginAtZero: true, ticks: { callback: function(val) { return 'Rp ' + new Intl.NumberFormat('id-ID', {notation: "compact"}).format(val); } } } },
            plugins: { tooltip: { callbacks: { label: (ctx) => ' ' + ctx.dataset.label + ': ' + formatRp(ctx.raw) } } }
        }
    });

    // 3. Bar Chart OMSET Perbandingan RETAIL
    new Chart(document.getElementById('barCompareRetailOmset').getContext('2d'), {
        type: 'bar', data: {
            labels: [lblCurText, lblPrevText],
            datasets: [{ label: 'Total Retail (Omset)', data: [{{ $data['totalRetailOmset'] ?? 0 }}, {{ $data['totalPrevRetailOmset'] ?? 0 }}], backgroundColor: ['#28a745', '#adb5bd'] }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { tooltip: { callbacks: { label: (ctx) => ' ' + formatRp(ctx.raw) } }, legend: {display: false} } }
    });

    // 4. Bar Chart OMSET Perbandingan SERVICE
    new Chart(document.getElementById('barCompareServiceOmset').getContext('2d'), {
        type: 'bar', data: {
            labels: [lblCurText, lblPrevText],
            datasets: [{ label: 'Total Service (Omset)', data: [{{ $data['totalServiceOmset'] ?? 0 }}, {{ $data['totalPrevServiceOmset'] ?? 0 }}], backgroundColor: ['#17a2b8', '#adb5bd'] }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { tooltip: { callbacks: { label: (ctx) => ' ' + formatRp(ctx.raw) } }, legend: {display: false} } }
    });

    // 5. Bar Chart QTY Perbandingan RETAIL
    new Chart(document.getElementById('barCompareRetail').getContext('2d'), {
        type: 'bar', data: {
            labels: [lblCurText, lblPrevText],
            datasets: [{ label: 'Total Retail (Qty)', data: [{{ $data['totalRetailQty'] ?? 0 }}, {{ $data['totalPrevRetailQty'] ?? 0 }}], backgroundColor: ['#ffc107', '#adb5bd'] }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { tooltip: { callbacks: { label: (ctx) => ' ' + formatQty(ctx.raw) } }, legend: {display: false} } }
    });

    // 6. Bar Chart QTY Perbandingan SERVICE
    new Chart(document.getElementById('barCompareService').getContext('2d'), {
        type: 'bar', data: {
            labels: [lblCurText, lblPrevText],
            datasets: [{ label: 'Total Service (Qty)', data: [{{ $data['totalServiceQty'] ?? 0 }}, {{ $data['totalPrevServiceQty'] ?? 0 }}], backgroundColor: ['#17a2b8', '#adb5bd'] }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { tooltip: { callbacks: { label: (ctx) => ' ' + formatQty(ctx.raw) } }, legend: {display: false} } }
    });

    // 7. Pie Qty
    new Chart(document.getElementById('pieQty').getContext('2d'), {
        type: 'doughnut', data: {
            labels: ['Retail (Qty)', 'Service (Qty)'],
            datasets: [{ data: [{{ $data['qtyPie']['retail'] ?? 0 }}, {{ $data['qtyPie']['service'] ?? 0 }}], backgroundColor: ['#ffc107', '#17a2b8'], borderWidth: 0 }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { tooltip: { callbacks: { label: (ctx) => ' ' + ctx.label + ': ' + formatQty(ctx.raw) } } } }
    });

    // 8. Bar Qty (Line)
    new Chart(document.getElementById('barQty').getContext('2d'), {
        type: 'line', data: {
            labels: {!! json_encode($data['chartLabels'] ?? []) !!},
            datasets: [
                { label: 'Qty Retail', borderColor: '#ffc107', data: {!! json_encode($data['chartRetailQty'] ?? []) !!}, backgroundColor: 'transparent', borderWidth: 2, tension: 0.3 },
                { label: 'Qty Service', borderColor: '#17a2b8', data: {!! json_encode($data['chartServiceQty'] ?? []) !!}, backgroundColor: 'transparent', borderWidth: 2, tension: 0.3 }
            ]
        },
        options: { 
            responsive: true, maintainAspectRatio: false,
            scales: { y: { beginAtZero: true } },
            plugins: { tooltip: { callbacks: { label: (ctx) => ' ' + ctx.dataset.label + ': ' + formatQty(ctx.raw) } } }
        }
    });
</script>
@endpush