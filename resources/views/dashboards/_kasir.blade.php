<div class="row mb-3">
    <div class="col-12">
        <h4><i class="fas fa-cash-register"></i> Dashboard Kasir Dealer</h4>
        <p class="text-muted">Halo, <strong>{{ Auth::user()->nama }}</strong>! Berikut ringkasan transaksi di dealer Anda.</p>
    </div>
</div>

{{-- BARIS 1: RINGKASAN TRANSAKSI (Service & Penjualan) --}}
<div class="row">
    {{-- Service Hari Ini --}}
    <div class="col-lg-3 col-6">
        <div class="small-box bg-info">
            <div class="inner">
                <h3>{{ $data['serviceToday'] }}</h3>
                <p>Service Hari Ini</p>
            </div>
            <div class="icon"><i class="fas fa-tools"></i></div>
        </div>
    </div>

    {{-- Penjualan Hari Ini --}}
    <div class="col-lg-3 col-6">
        <div class="small-box bg-success">
            <div class="inner">
                <h3>{{ $data['salesToday'] }}</h3>
                <p>Penjualan Part Hari Ini</p>
            </div>
            <div class="icon"><i class="fas fa-shopping-cart"></i></div>
        </div>
    </div>

    {{-- Service Minggu Ini --}}
    <div class="col-lg-3 col-6">
        <div class="small-box bg-primary">
            <div class="inner">
                <h3>{{ $data['serviceWeek'] }}</h3>
                <p>Service 7 Hari Terakhir</p>
            </div>
            <div class="icon"><i class="fas fa-calendar-week"></i></div>
        </div>
    </div>

    {{-- Penjualan Minggu Ini --}}
    <div class="col-lg-3 col-6">
        <div class="small-box bg-teal">
            <div class="inner">
                <h3>{{ $data['salesWeek'] }}</h3>
                <p>Penjualan 7 Hari Terakhir</p>
            </div>
            <div class="icon"><i class="fas fa-chart-line"></i></div>
        </div>
    </div>
</div>

{{-- BARIS 2: TOTAL ITEM TERJUAL BULAN INI --}}
<div class="row">
    <div class="col-md-12">
        <div class="info-box mb-3 bg-white shadow-sm">
            <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-boxes"></i></span>
            <div class="info-box-content">
                <span class="info-box-text text-muted">Total Barang Terjual Bulan Ini (Service + Penjualan Langsung)</span>
                <span class="info-box-number display-4" style="font-size: 2rem;">
                    {{ number_format($data['totalItemsSoldMonth'], 0, ',', '.') }} <small>Unit/Pcs</small>
                </span>
            </div>
        </div>
    </div>
</div>

<!-- <div class="row">
    <div class="col-12 text-center mt-4">
        <a href="{{ route('admin.penjualans.create') }}" class="btn btn-app bg-success">
            <i class="fas fa-plus"></i> Transaksi Penjualan Baru
        </a>
        {{-- Jika ada route service --}}
        {{-- <a href="{{ route('admin.services.create') }}" class="btn btn-app bg-info">
            <i class="fas fa-wrench"></i> Service Baru
        </a> --}}
    </div> -->
</div>