<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Lokasi;
use App\Models\Barang; // Pastikan pakai Barang
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDF;

class PurchaseOrderController extends Controller
{
    public function index()
    {
        // Tampilkan PO yang sumbernya INTERNAL atau EXTERNAL
        $purchaseOrders = PurchaseOrder::with(['lokasi', 'createdBy', 'supplier'])
                                       ->latest()
                                       ->get();
        return view('admin.purchase_orders.index', compact('purchaseOrders'));
    }

    public function create()
    {
        $this->authorize('create-po'); // Gate UA22001 bekerja disini
        $user = Auth::user();

        // Ambil Gudang Pusat (Sumber Barang)
        $sumberPusat = Lokasi::where('tipe', 'PUSAT')->first();

        // Logic Dealers:
        // Jika user adalah PUSAT/SA, bisa pilih dealer mana saja.
        // Jika user adalah DEALER (UA22001), maka dealer tujuan TERKUNCI ke dealernya sendiri.
        if ($user->hasRole(['SA', 'PIC']) || ($user->lokasi && $user->lokasi->tipe === 'PUSAT')) {
            $dealers = Lokasi::where('tipe', 'DEALER')->where('is_active', true)->orderBy('nama_lokasi')->get();
        } else {
            // Dealer hanya bisa pilih dirinya sendiri
            $dealers = Lokasi::where('id', $user->lokasi_id)->get();
        }

        $barangs = Barang::orderBy('part_name')->get();

        return view('admin.purchase_orders.create', compact('sumberPusat', 'dealers', 'barangs'));
    }

    public function store(Request $request)
    {
        $this->authorize('create-po');

        // Validasi Struktur Data Multi-Dealer
        $request->validate([
            'tanggal_po' => 'required|date',
            'sumber_lokasi_id' => 'required|exists:lokasi,id', // Gudang Pusat
            'requests' => 'required|string', // JSON String dari Frontend
        ]);

        // Decode JSON dari frontend
        // Format: [ { "lokasi_id": 1, "items": [ {"barang_id": 5, "qty": 10}, ... ] }, ... ]
        $dealerRequests = json_decode($request->requests, true);

        if (empty($dealerRequests)) {
            return back()->with('error', 'Tidak ada item yang direquest.')->withInput();
        }

        DB::beginTransaction();
        try {
            // Generate ID Group Unik untuk batch ini
            $requestGroupId = 'REQ-' . now()->format('YmdHis') . '-' . strtoupper(Str::random(4));
            $successCount = 0;

            foreach ($dealerRequests as $dealerReq) {
                $lokasiTujuanId = $dealerReq['lokasi_id'];
                $items = $dealerReq['items'];

                if (empty($items)) continue;

                // 1. Hitung Subtotal per Dealer
                $subtotalPO = 0;
                $detailsToInsert = [];

                foreach ($items as $item) {
                    $barang = Barang::find($item['barang_id']);
                    // Gunakan 'selling_out' (Harga Jual ke Dealer) atau 'selling_in' (Harga Modal)
                    // Sesuai instruksi Anda "Internal Transfer", biasanya pakai Harga Modal (selling_in)
                    // Tapi jika Dealer "Membeli", pakai selling_out.
                    // Asumsi: Pakai selling_out (Harga Jual dari Pusat ke Dealer)
                    $hargaSatuan = $barang->selling_out;

                    $subtotalItem = $item['qty'] * $hargaSatuan;
                    $subtotalPO += $subtotalItem;

                    $detailsToInsert[] = [
                        'barang_id' => $barang->id, // Pastikan tabel detail sudah rename part_id -> barang_id
                        'qty_pesan' => $item['qty'],
                        'harga_beli' => $hargaSatuan,
                        'subtotal' => $subtotalItem,
                    ];
                }

                // 2. Buat 1 PO untuk Dealer ini
                $po = PurchaseOrder::create([
                    'nomor_po' => $this->generatePoNumber($lokasiTujuanId),
                    'request_group_id' => $requestGroupId,
                    'tanggal_po' => $request->tanggal_po,
                    'sumber_lokasi_id' => $request->sumber_lokasi_id, // Gudang Pusat
                    'supplier_id' => null, // Null karena internal
                    'lokasi_id' => $lokasiTujuanId, // Dealer Tujuan
                    'status' => 'PENDING_APPROVAL',
                    'total_amount' => $subtotalPO,
                    'created_by' => Auth::id(),
                    'catatan' => 'Request Internal Group: ' . $requestGroupId
                ]);

                // 3. Simpan Detail
                $po->details()->createMany($detailsToInsert);
                $successCount++;
            }

            DB::commit();
            return redirect()->route('admin.purchase-orders.index')
                             ->with('success', "Berhasil membuat $successCount Request PO terpisah (Group ID: $requestGroupId).");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal menyimpan: ' . $e->getMessage())->withInput();
        }
    }

    // Helper Generate Nomor PO Unik per Dealer
    private function generatePoNumber($lokasiId)
    {
        $lokasi = Lokasi::find($lokasiId);
        $kodeLokasi = $lokasi ? $lokasi->kode_lokasi : 'GEN';
        $date = now()->format('ymd');

        // Cari urutan terakhir hari ini untuk lokasi ini
        $count = PurchaseOrder::where('lokasi_id', $lokasiId)
                              ->whereDate('created_at', today())
                              ->count();
        $sequence = str_pad($count + 1, 3, '0', STR_PAD_LEFT);

        return "PO/{$kodeLokasi}/{$date}/{$sequence}";
    }

    // --- Fungsi Approval (Tetap Standard, tapi logicnya jadi per PO/Dealer) ---
    public function approve(PurchaseOrder $purchaseOrder)
    {
        $this->authorize('approve-po', $purchaseOrder);

        // Cek Stok Pusat (Optional: Jika ingin validasi stok saat approve)
        // foreach($purchaseOrder->details as $detail) { ... check inventory ... }

        $purchaseOrder->update([
            'status' => 'APPROVED',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        // Note: "Otomatis masuk receiving"
        // Biasanya Receiving dibuat manual saat barang fisik sampai di Dealer.
        // Jika ingin otomatis Receiving di sisi sistem (auto-receive), tambahkan logic create Receiving disini.
        // Tapi flow wajarnya: Pusat Kirim (Stock Movement Out) -> Dealer Terima (Receiving).

        return back()->with('success', 'Request Dealer ini disetujui. Silakan proses pengiriman.');
    }

    // ... method reject, show, dll tetap sama ...
    public function show(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load(['lokasi', 'details.barang', 'createdBy']);
        return view('admin.purchase_orders.show', compact('purchaseOrder'));
    }

    public function reject(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->authorize('approve-po', $purchaseOrder);

        $purchaseOrder->update([
            'status' => 'REJECTED',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Request Dealer ini ditolak.');
    }
}
