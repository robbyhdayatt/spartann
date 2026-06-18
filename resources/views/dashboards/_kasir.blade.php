<div class="row mb-3">
    <div class="col-12">
        <h4 class="text-dark"><i class="fas fa-cash-register text-success mr-2"></i> Dashboard Kasir Dealer</h4>
        <p class="text-muted">Halo, <strong>{{ Auth::user()->nama ?? Auth::user()->username }}</strong>! Berikut ringkasan transaksi harian di dealer Anda.</p>
    </div>
</div>

{{-- BARIS 1: LIVE OPERASIONAL & KEUANGAN HARI INI --}}
<div class="row">
    {{-- Service Hari Ini (AKSI CEPAT SHORTCUT MENU SERVICE) --}}
    <div class="col-lg-3 col-6">
        <div class="small-box bg-info shadow-sm border-0">
            <div class="inner">
                <h3>{{ $data['serviceToday'] }}</h3>
                <p>Service Hari Ini</p>
            </div>
            <div class="icon"><i class="fas fa-tools"></i></div>
            <a href="{{ route('admin.services.index') }}" class="small-box-footer font-weight-bold">
                Buka Menu Service <i class="fas fa-arrow-circle-right ml-1"></i>
            </a>
        </div>
    </div>

    {{-- Penjualan Hari Ini (Dari Part Retail) --}}
    <div class="col-lg-3 col-6">
        <div class="small-box bg-success shadow-sm border-0">
            <div class="inner">
                <h3>{{ $data['salesToday'] }}</h3>
                <p>Penjualan Part Hari Ini</p>
            </div>
            <div class="icon"><i class="fas fa-shopping-cart"></i></div>
            <div class="small-box-footer" style="padding:4px 10px; background: rgba(0,0,0,0.1); color:rgba(255,255,255,0.8); font-size:0.85rem;">
                Tipe: Part Retail <i class="fas fa-check-circle ml-1" style="font-size: 0.7rem;"></i>
            </div>
        </div>
    </div>

    {{-- TOTAL UANG MASUK HARI INI --}}
    <div class="col-lg-6 col-12">
        <div class="small-box bg-gradient-primary shadow-sm border-0">
            <div class="inner">
                <h3>Rp {{ number_format($data['grandTotalRevenueToday'], 0, ',', '.') }}</h3>
                <p class="font-weight-bold mb-1">Total Uang Masuk Hari Ini (Service + Retail)</p>
            </div>
            <div class="icon"><i class="fas fa-wallet" style="opacity: 0.3;"></i></div>
            <div class="small-box-footer" style="padding:4px 12px; text-align:left; background: rgba(0,0,0,0.15); font-weight:500; font-size: 0.85rem;">
                <i class="fas fa-calculator mr-1"></i> Acuan validasi mesin EDC & cash laci saat tutup shift.
            </div>
        </div>
    </div>
</div>

{{-- BARIS 2: TOTAL ITEM TERJUAL & AUDIT FAKTUR --}}
<div class="row mt-2">
    <div class="col-md-5">
        <div class="info-box mb-3 bg-white shadow-sm border h-100">
            <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-boxes text-white"></i></span>
            <div class="info-box-content justify-content-center">
                <span class="info-box-text text-muted font-weight-bold">Total Part Terjual Bulan Ini</span>
                <span class="text-xs text-muted mb-2">(Dari Service + Penjualan Langsung)</span>
                <span class="info-box-number text-dark" style="font-size: 2.5rem; line-height: 1;">
                    {{ number_format($data['totalItemsSoldMonth'], 0, ',', '.') }} <small style="font-size: 1rem;">Unit/Pcs</small>
                </span>
            </div>
        </div>
    </div>
    
    {{-- TABEL AUDIT FAKTUR TERAKHIR --}}
    <div class="col-md-7">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-0 pb-2">
                <h3 class="card-title font-weight-bold"><i class="fas fa-history text-dark mr-2"></i> Audit Faktur Terakhir</h3>
            </div>
            <div class="card-body p-0 table-responsive" style="max-height: 250px; overflow-y: auto;">
                <table class="table table-hover table-striped table-sm mb-0">
                    <thead class="bg-light text-muted" style="position: sticky; top: 0; z-index: 1;">
                        <tr>
                            <th class="pl-4">Waktu</th>
                            <th>No. Faktur</th>
                            <th>Tipe</th>
                            <th class="text-right pr-4">Total Bayar (Rp)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($data['recentTransactions'] as $trx)
                            <tr>
                                <td class="pl-4 align-middle text-muted"><i class="far fa-clock mr-1"></i> {{ \Carbon\Carbon::parse($trx->created_at)->format('d M, H:i') }}</td>
                                <td class="align-middle font-weight-bold text-dark">{{ $trx->nomor ?? '-' }}</td>
                                <td class="align-middle">
                                    {{-- Mengubah warna lencana sesuai tipe transaksi --}}
                                    @if($trx->jenis == 'Retail (POS)')
                                        <span class="badge badge-success px-2 py-1">{{ $trx->jenis }}</span>
                                    @elseif($trx->jenis == 'Part Retail')
                                        <span class="badge badge-warning text-dark px-2 py-1">{{ $trx->jenis }}</span>
                                    @else
                                        <span class="badge badge-info px-2 py-1">{{ $trx->jenis }}</span>
                                    @endif
                                </td>
                                <td class="text-right pr-4 align-middle font-weight-bold text-dark">
                                    {{ number_format($trx->total, 0, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center py-4 text-muted">Belum ada riwayat transaksi.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>