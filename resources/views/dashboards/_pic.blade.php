<div class="row mb-3">
    <div class="col-12">
        <h4 class="text-dark"><i class="fas fa-briefcase mr-2"></i> Management Dashboard (PIC/Manager)</h4>
        <p class="text-muted">Overview Operasional & Persetujuan Tertunda</p>
    </div>
</div>

{{-- ROW 1: APPROVAL ALERTS (Prioritas Tertinggi PIC) --}}
@if($data['totalPending'] > 0)
<div class="row">
    <div class="col-12">
        <div class="alert alert-warning border-left-warning shadow-sm">
            <h5><i class="icon fas fa-exclamation-triangle"></i> Perhatian! Ada {{ $data['totalPending'] }} Dokumen Menunggu Persetujuan Anda.</h5>
            <div class="mt-2">
                @if($data['pendingPO'] > 0)
                    <a href="{{ route('admin.purchase-orders.index') }}" class="btn btn-warning btn-sm mr-2">
                        <i class="fas fa-file-invoice mr-1"></i> {{ $data['pendingPO'] }} PO Dealer
                    </a>
                @endif
                @if($data['pendingMutasi'] > 0)
                    <a href="{{ route('admin.stock-mutations.index') }}" class="btn btn-warning btn-sm mr-2">
                        <i class="fas fa-exchange-alt mr-1"></i> {{ $data['pendingMutasi'] }} Mutasi Stok
                    </a>
                @endif
                @if($data['pendingAdjustment'] > 0)
                    <a href="{{ route('admin.stock-adjustments.index') }}" class="btn btn-warning btn-sm">
                        <i class="fas fa-sliders-h mr-1"></i> {{ $data['pendingAdjustment'] }} Penyesuaian Stok
                    </a>
                @endif
            </div>
        </div>
    </div>
</div>
@endif

{{-- ROW 2: RINGKASAN CABANG --}}
<div class="row">
    <div class="col-md-3 col-6">
        <div class="info-box shadow-sm">
            <span class="info-box-icon bg-info"><i class="fas fa-store"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Total Cabang</span>
                <span class="info-box-number">{{ $data['totalCabang'] }} Lokasi</span>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="info-box shadow-sm">
            <span class="info-box-icon bg-purple"><i class="fas fa-users"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Total Karyawan</span>
                <span class="info-box-number">{{ $data['totalUser'] }} User</span>
            </div>
        </div>
    </div>
    {{-- Bisa ditambah widget lain --}}
</div>

{{-- ROW 3: TOP CABANG & STOK KRITIS GLOBAL --}}
<div class="row">
    {{-- Top Performa Cabang --}}
    <div class="col-lg-6">
        <div class="card card-outline card-primary">
            <div class="card-header border-0">
                <h3 class="card-title">Top 5 Cabang (Omset Bulan Ini)</h3>
            </div>
            <div class="card-body p-0">
                <table class="table table-striped table-valign-middle">
                    <thead>
                    <tr>
                        <th>Cabang</th>
                        <th class="text-right">Omset</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($data['topCabang'] as $cabang)
                        <tr>
                            <td>{{ $cabang->nama_lokasi }}</td>
                            <td class="text-right font-weight-bold text-success">
                                Rp {{ number_format($cabang->omset, 0, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="text-center">Belum ada data penjualan.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Global Critical Stock --}}
    <div class="col-lg-6">
        <div class="card card-outline card-danger">
            <div class="card-header border-0">
                <h3 class="card-title">Global Critical Stock (Top 5)</h3>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Part</th>
                            <th class="text-center">Total Fisik</th>
                            <th class="text-center">Min</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($data['criticalItems'] as $item)
                            <tr>
                                <td>{{ $item->part_name }}</td>
                                <td class="text-center text-danger font-weight-bold">{{ $item->total_qty }}</td>
                                <td class="text-center text-muted">{{ $item->stok_minimum }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-success">Stok Global Aman.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer text-center">
                <a href="{{ route('admin.reports.stock-report') }}">Lihat Laporan Stok Lengkap</a>
            </div>
        </div>
    </div>
</div>