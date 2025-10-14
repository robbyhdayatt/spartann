<div class="row">
    <div class="col-12">
        <h4>Tugas Persetujuan di {{ $data['lokasi']->nama_gudang }}</h4>
        <hr>
    </div>

    @if(auth()->user()->hasRole('KG'))
    <div class="col-md-4">
        <div class="info-box">
            <span class="info-box-icon bg-info"><i class="fas fa-shopping-cart"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Purchase Order</span>
                <span class="info-box-number">{{ count($data['pendingPurchaseOrders']) }}</span>
            </div>
        </div>
    </div>
    @endif

    <div class="col-md-4">
        <div class="info-box">
            <span class="info-box-icon bg-warning"><i class="fas fa-edit"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Adjusment Stok</span>
                <span class="info-box-number">{{ count($data['pendingAdjustments']) }}</span>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="info-box">
            <span class="info-box-icon bg-purple"><i class="fas fa-truck-loading"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Mutasi Stok</span>
                <span class="info-box-number">{{ count($data['pendingMutations']) }}</span>
            </div>
        </div>
    </div>
</div>