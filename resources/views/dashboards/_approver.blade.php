<div class="row">
    <div class="col-12">
        <h4>Tugas Persetujuan di {{ $data['lokasi']->nama_gudang }}</h4>
        <hr>
    </div>

    {{-- KOTAK NOTIFIKASI UNTUK PURCHASE ORDER --}}
    @if(auth()->user()->hasRole('KG') && count($data['pendingPurchaseOrders']) > 0)
    <div class="col-md-4">
        {{-- PERUBAHAN: Seluruh kotak sekarang adalah tautan --}}
        <a href="{{ route('admin.purchase-orders.index') }}" class="info-box mb-3 bg-info">
            <span class="info-box-icon"><i class="fas fa-shopping-cart"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Purchase Order</span>
                <span class="info-box-number">{{ count($data['pendingPurchaseOrders']) }} Menunggu</span>
            </div>
        </a>
    </div>
    @endif

    {{-- KOTAK NOTIFIKASI UNTUK ADJUSTMENT STOK --}}
    @if(count($data['pendingAdjustments']) > 0)
    <div class="col-md-4">
        <a href="{{ route('admin.stock-adjustments.index') }}" class="info-box mb-3 bg-warning">
            <span class="info-box-icon"><i class="fas fa-edit"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Adjusment Stok</span>
                <span class="info-box-number">{{ count($data['pendingAdjustments']) }} Menunggu</span>
            </div>
        </a>
    </div>
    @endif

    {{-- KOTAK NOTIFIKASI UNTUK MUTASI STOK --}}
    @if(count($data['pendingMutations']) > 0)
    <div class="col-md-4">
        <a href="{{ route('admin.stock-mutations.index') }}" class="info-box mb-3 bg-purple">
            <span class="info-box-icon"><i class="fas fa-truck-loading"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Mutasi Stok</span>
                <span class="info-box-number">{{ count($data['pendingMutations']) }} Menunggu</span>
            </div>
        </a>
    </div>
    @endif
</div>

{{-- Menampilkan pesan jika tidak ada tugas sama sekali --}}
@if(count($data['pendingPurchaseOrders']) == 0 && count($data['pendingAdjustments']) == 0 && count($data['pendingMutations']) == 0)
<div class="row">
    <div class="col-12">
        <div class="alert alert-success">
            <h5><i class="icon fas fa-check"></i> Tidak Ada Tugas!</h5>
            Saat ini tidak ada dokumen yang memerlukan persetujuan Anda di lokasi ini.
        </div>
    </div>
</div>
@endif
