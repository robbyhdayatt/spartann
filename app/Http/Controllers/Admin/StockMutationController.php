<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockMutation;
use App\Models\InventoryBatch;
use App\Models\Barang;
use App\Models\Lokasi;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\Facades\DataTables;

class StockMutationController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('view-stock-transaction');

        // Menyimpan riwayat filter di session agar tidak hilang saat refresh
        if ($request->filled('start_date') || $request->filled('end_date')) {
            session([
                'mutation.start_date' => $request->input('start_date'),
                'mutation.end_date'   => $request->input('end_date'),
            ]);
        }

        // Default: 1 Bulan terakhir (jika belum ada filter)
        $startDate = $request->input('start_date', session('mutation.start_date', now()->startOfMonth()->toDateString()));
        $endDate   = $request->input('end_date', session('mutation.end_date', now()->toDateString()));
        
        if ($request->ajax()) {
            $user = Auth::user();
            $query = StockMutation::with(['barang', 'lokasiAsal', 'lokasiTujuan', 'createdBy'])->select('stock_mutations.*');

            if (!$user->hasRole(['SA', 'PIC', 'ASD', 'ACC', 'IMS'])) {
                $query->where(function($q) use ($user) {
                    $q->where('lokasi_asal_id', $user->lokasi_id)
                      ->orWhere('lokasi_tujuan_id', $user->lokasi_id);
                });
            }

            // Menerapkan Filter Tanggal
            if ($startDate && $endDate) {
                try {
                    $start = \Carbon\Carbon::createFromFormat('Y-m-d', $startDate)->startOfDay();
                    $end = \Carbon\Carbon::createFromFormat('Y-m-d', $endDate)->endOfDay();
                    $query->whereBetween('stock_mutations.created_at', [$start, $end]);
                } catch (\Exception $e) {
                    // Abaikan jika format tanggal salah
                }
            }

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('nomor_mutasi_html', function($row) {
                    return '<strong>'.$row->nomor_mutasi.'</strong><br><small class="text-muted">'.$row->created_at->format('d M Y').'</small>';
                })
                ->addColumn('part_html', function($row) {
                    $name = $row->barang ? $row->barang->part_name : '-';
                    $code = $row->barang ? $row->barang->part_code : '-';
                    return $name.'<br><small class="text-muted">'.$code.'</small>';
                })
                ->addColumn('rute_html', function($row) {
                    $asal = $row->lokasiAsal ? $row->lokasiAsal->nama_lokasi : '-';
                    $tujuan = $row->lokasiTujuan ? $row->lokasiTujuan->nama_lokasi : '-';
                    return '<i class="fas fa-arrow-up text-danger"></i> '.$asal.'<br><i class="fas fa-arrow-down text-success"></i> '.$tujuan;
                })
                ->editColumn('status', function($row) {
                    if($row->status == 'PENDING_APPROVAL') return '<span class="badge badge-warning">Menunggu Persetujuan</span>';
                    if($row->status == 'IN_TRANSIT') return '<span class="badge badge-info"><i class="fas fa-shipping-fast"></i> Dalam Perjalanan</span>';
                    if($row->status == 'COMPLETED') return '<span class="badge badge-success"><i class="fas fa-check"></i> Selesai</span>';
                    if($row->status == 'REJECTED') return '<span class="badge badge-danger"><i class="fas fa-times"></i> Ditolak</span>';
                    return '<span class="badge badge-secondary">'.$row->status.'</span>';
                })
                ->addColumn('aksi', function($row) {
                    return '<a href="'.route('admin.stock-mutations.show', $row).'" class="btn btn-info btn-sm shadow-sm"><i class="fas fa-eye"></i> Detail</a>';
                })
                ->rawColumns(['nomor_mutasi_html', 'part_html', 'rute_html', 'status', 'aksi'])
                ->make(true);
        }

        return view('admin.stock_mutations.index', compact('startDate', 'endDate'));
    }

    public function create()
    {
        $this->authorize('create-stock-transaction');
        $user = Auth::user();

        if ($user->hasRole(['SA', 'PIC', 'ACC', 'IMS', 'ASD'])) {
            $lokasiAsal = Lokasi::where('is_active', true)->orderBy('nama_lokasi')->get();
        } else {
            if (!$user->lokasi_id) {
                return redirect()->route('admin.home')->with('error', 'Akun Anda tidak terasosiasi dengan lokasi manapun.');
            }
            $lokasiAsal = Lokasi::where('id', $user->lokasi_id)->get();
        }

        $lokasiTujuan = Lokasi::where('is_active', true)
                              ->orderBy('nama_lokasi')
                              ->get();

        return view('admin.stock_mutations.create', compact('lokasiAsal', 'lokasiTujuan'));
    }

    public function store(Request $request)
    {
        $this->authorize('create-stock-transaction');
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'barang_id'         => 'required|exists:barangs,id',
            'lokasi_asal_id'    => 'required|exists:lokasi,id',
            'lokasi_tujuan_id'  => 'required|exists:lokasi,id|different:lokasi_asal_id',
            'jumlah'            => 'required|integer|min:1',
            'keterangan'        => 'nullable|string|max:255',
        ], [
            'lokasi_tujuan_id.different' => 'Lokasi tujuan tidak boleh sama dengan lokasi asal.',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        if (!$user->hasRole(['SA', 'PIC', 'ASD']) && $request->lokasi_asal_id != $user->lokasi_id) {
             return back()->with('error', 'Anda tidak memiliki akses untuk memindahkan barang dari lokasi ini.')->withInput();
        }

        $barang = Barang::findOrFail($request->barang_id);
        
        $totalStock = InventoryBatch::where('lokasi_id', $request->lokasi_asal_id)
            ->where('barang_id', $request->barang_id)
            ->sum('quantity');

        if ($totalStock < $request->jumlah) {
            return back()->with('error', "Gagal! Stok di lokasi asal tidak mencukupi. (Tersedia: {$totalStock})")->withInput();
        }

        $sisaPrediksi = $totalStock - $request->jumlah;
        if ($sisaPrediksi < $barang->stok_minimum) {
             return back()->with('error', "Gagal! Mutasi ini melanggar batas stok minimum ({$barang->stok_minimum}).")->withInput();
        }

        DB::beginTransaction();
        try {
            StockMutation::create([
                'nomor_mutasi'     => StockMutation::generateNomorMutasi(),
                'barang_id'        => $request->barang_id,
                'lokasi_asal_id'   => $request->lokasi_asal_id,
                'lokasi_tujuan_id' => $request->lokasi_tujuan_id,
                'jumlah'           => $request->jumlah,
                'keterangan'       => $request->keterangan,
                'status'           => 'PENDING_APPROVAL',
                'created_by'       => $user->id,
            ]);

            DB::commit();
            return redirect()->route('admin.stock-mutations.index')
                ->with('success', 'Permintaan mutasi berhasil dibuat dan menunggu persetujuan.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage())->withInput();
        }
    }

    public function approve(StockMutation $stockMutation)
    {
        $this->authorize('approve-stock-transaction', $stockMutation);

        if ($stockMutation->status !== 'PENDING_APPROVAL') {
            return back()->with('error', 'Hanya mutasi dengan status PENDING yang dapat diproses.');
        }

        DB::beginTransaction();
        try {
            $jumlahToMutate = $stockMutation->jumlah;
            $barang = $stockMutation->barang;
            $batches = InventoryBatch::where('lokasi_id', $stockMutation->lokasi_asal_id)
                   ->where('barang_id', $stockMutation->barang_id)
                   ->where('quantity', '>', 0)
                   ->orderBy('created_at', 'asc')
                   ->lockForUpdate()
                   ->get();

            $totalStockRealtime = $batches->sum('quantity');

            if ($totalStockRealtime < $jumlahToMutate) {
                throw new \Exception("Stok fisik tidak mencukupi saat proses approval. (Tersedia: {$totalStockRealtime})");
            }

            if (($totalStockRealtime - $jumlahToMutate) < $barang->stok_minimum) {
                throw new \Exception("Approval dibatalkan! Sisa stok akan menembus batas minimum ({$barang->stok_minimum}).");
            }

            $sisaPermintaan = $jumlahToMutate;

            foreach ($batches as $batch) {
                if ($sisaPermintaan <= 0) break;

                $ambil = min($batch->quantity, $sisaPermintaan);
                
                $stokAwalBatch = $batch->quantity;
                $batch->decrement('quantity', $ambil);

                StockMovement::create([
                    'barang_id'      => $stockMutation->barang_id,
                    'lokasi_id'      => $stockMutation->lokasi_asal_id,
                    'rak_id'         => $batch->rak_id,
                    'jumlah'         => -$ambil,
                    'stok_sebelum'   => $stokAwalBatch,
                    'stok_sesudah'   => $stokAwalBatch - $ambil,
                    'referensi_type' => get_class($stockMutation),
                    'referensi_id'   => $stockMutation->id,
                    'keterangan'     => 'Mutasi Keluar ke: ' . $stockMutation->lokasiTujuan->nama_lokasi,
                    'user_id'        => Auth::id(),
                ]);

                $sisaPermintaan -= $ambil;
            }

            if ($sisaPermintaan > 0) {
                throw new \Exception("Terjadi anomali saat alokasi batch. Silakan coba lagi.");
            }

            $stockMutation->update([
                'status'      => 'IN_TRANSIT',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            DB::commit();
            return redirect()->route('admin.stock-mutations.index')
                ->with('success', 'Mutasi disetujui. Stok asal telah dipotong dan status berubah menjadi IN TRANSIT.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal memproses: ' . $e->getMessage());
        }
    }

    public function reject(Request $request, StockMutation $stockMutation)
    {
        $this->authorize('approve-stock-transaction', $stockMutation);
        
        $request->validate([
            'rejection_reason' => 'required|string|min:5|max:255'
        ], [
            'rejection_reason.required' => 'Alasan penolakan wajib diisi.',
            'rejection_reason.min' => 'Alasan penolakan terlalu singkat.',
        ]);

        if ($stockMutation->status !== 'PENDING_APPROVAL') {
             return back()->with('error', 'Permintaan sudah diproses sebelumnya.');
        }

        $stockMutation->update([
            'status'           => 'REJECTED',
            'rejection_reason' => $request->rejection_reason,
            'approved_by'      => Auth::id(),
            'approved_at'      => now(),
        ]);

        return redirect()->route('admin.stock-mutations.index')->with('success', 'Permintaan mutasi ditolak.');
    }

    // ====================================================================================
    // [FUNGSI BARU] MENERIMA BARANG (MENGUBAH STATUS IN_TRANSIT -> COMPLETED)
    // ====================================================================================
    public function receive(Request $request, StockMutation $stockMutation)
    {
        $user = Auth::user();
        if (!$user->hasRole(['SA', 'PIC', 'ASD']) && $user->lokasi_id != $stockMutation->lokasi_tujuan_id) {
            return back()->with('error', 'Anda tidak memiliki hak untuk menerima barang di lokasi tujuan ini.');
        }

        if (!in_array($stockMutation->status, ['IN_TRANSIT', 'PARTIALLY_RECEIVED'])) {
            return back()->with('error', 'Hanya mutasi berstatus IN TRANSIT atau PARTIAL yang dapat diterima.');
        }

        // Hitung sisa barang yang belum diterima
        $sisaBelumDiterima = $stockMutation->jumlah - $stockMutation->jumlah_diterima;

        // [PERBAIKAN POINT 3]: Validasi Partial Receiving
        $request->validate([
            'rak_id' => 'required',
            'qty_terima' => 'required|integer|min:1|max:' . $sisaBelumDiterima,
        ], [
            'rak_id.required' => 'Wajib memilih lokasi Rak untuk putaway.',
            'qty_terima.max' => 'Jumlah terima tidak boleh melebihi sisa barang yang dikirim (' . $sisaBelumDiterima . ' Pcs).'
        ]);

        $qtyTerimaSekarang = (int) $request->qty_terima;

        DB::beginTransaction();
        try {
            // [PERBAIKAN POINT 2]: Simpan stock_mutation_id ke InventoryBatch
            $batchTujuan = InventoryBatch::create([
                'barang_id'         => $stockMutation->barang_id,
                'lokasi_id'         => $stockMutation->lokasi_tujuan_id,
                'rak_id'            => $request->rak_id, 
                'stock_mutation_id' => $stockMutation->id, // Tautkan ID Mutasi
                'batch_no'          => 'MUT-' . date('ymd') . '-' . $stockMutation->id,
                'quantity'          => $qtyTerimaSekarang,
            ]);

            StockMovement::create([
                'barang_id'      => $stockMutation->barang_id,
                'lokasi_id'      => $stockMutation->lokasi_tujuan_id,
                'rak_id'         => $request->rak_id,
                'jumlah'         => $qtyTerimaSekarang,
                'stok_sebelum'   => 0,
                'stok_sesudah'   => $qtyTerimaSekarang,
                'referensi_type' => get_class($stockMutation),
                'referensi_id'   => $stockMutation->id,
                'keterangan'     => "Terima Parsial ({$qtyTerimaSekarang} Pcs) dari Mutasi Asal: " . $stockMutation->lokasiAsal->nama_lokasi,
                'user_id'        => Auth::id(),
            ]);

            // Hitung total penerimaan baru
            $totalDiterima = $stockMutation->jumlah_diterima + $qtyTerimaSekarang;
            
            // Tentukan status: Apakah sudah genap diterima semua, atau masih sisa (Partial)?
            $statusBaru = ($totalDiterima >= $stockMutation->jumlah) ? 'COMPLETED' : 'PARTIALLY_RECEIVED';

            // [PERBAIKAN POINT 4]: Update status, jumlah terima, dan rak_tujuan_id
            $stockMutation->update([
                'status'          => $statusBaru,
                'jumlah_diterima' => $totalDiterima,
                'rak_tujuan_id'   => $request->rak_id, // Simpan rak terakhir ke header mutasi
                'received_by'     => Auth::id(),
                'received_at'     => now(),
            ]);

            DB::commit();
            
            $pesan = $statusBaru === 'COMPLETED' 
                ? 'Seluruh barang mutasi telah berhasil diterima dan diletakkan di rak.' 
                : "Berhasil menerima sebagian barang ({$qtyTerimaSekarang} Pcs). Sisa barang masih berstatus PARTIAL.";

            return redirect()->route('admin.stock-mutations.show', $stockMutation->id)->with('success', $pesan);

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal memproses penerimaan: ' . $e->getMessage());
        }
    }

    public function show(StockMutation $stockMutation)
    {
        $stockMutation->load(['barang', 'lokasiAsal', 'lokasiTujuan', 'createdBy', 'approvedBy', 'receivedBy']);
        
        // [PERBAIKAN POINT 1]: Sembunyikan Rak Karantina
        $daftarRak = DB::table('raks') 
            ->where('lokasi_id', $stockMutation->lokasi_tujuan_id)
            ->where('tipe_rak', '!=', 'karantina') // Filter rak karantina
            ->orderBy('kode_rak')
            ->get();

        return view('admin.stock_mutations.show', compact('stockMutation', 'daftarRak'));
    }

    public function getPartsWithStock(Lokasi $lokasi)
    {
        $barangs = Barang::where('is_active', true)->whereHas('inventoryBatches', function($q) use ($lokasi) {
                $q->where('lokasi_id', $lokasi->id)->where('quantity', '>', 0);
            })
            ->select('id', 'part_name', 'part_code')
            ->orderBy('part_name')
            ->get();

        return response()->json($barangs);
    }

    public function getPartStockDetails(Request $request)
    {
        $request->validate([
            'lokasi_id' => 'required|exists:lokasi,id',
            'barang_id' => 'required|exists:barangs,id',
        ]);

        $totalStock = InventoryBatch::where('lokasi_id', $request->lokasi_id)
            ->where('barang_id', $request->barang_id)
            ->sum('quantity');

        $barang = Barang::find($request->barang_id);

        return response()->json([
            'total_stock'  => $totalStock,
            'stok_minimum' => $barang->stok_minimum ?? 0
        ]);
    }
}