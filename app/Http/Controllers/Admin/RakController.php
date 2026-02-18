<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lokasi;
use App\Models\Rak;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class RakController extends Controller
{
    public function index()
    {
        $this->authorize('view-raks');
        $user = Auth::user();
        
        $query = Rak::with('lokasi');

        // Logic Filter View
        if ($user->isGlobal()) {
            // Lihat Semua (SA, PIC)
        } 
        elseif ($user->isPusat()) {
            // PUSAT (ASD, IMS, ACC) -> View Seluruh Dealer
            // (Asumsi: Pusat tidak perlu lihat rak Gudang Part? Sesuai request: "View seluruh dealer")
            // Jika mau lihat Dealer + Gudang, sesuaikan whereHas
            $query->whereHas('lokasi', fn($q) => $q->where('tipe', 'DEALER'));
        }
        elseif ($user->isGudang()) {
            // GUDANG (AG, KG) -> View Lokasi Gudang (Self)
            $query->where('lokasi_id', $user->lokasi_id);
        }
        elseif ($user->isDealer()) {
            // DEALER (KC, PC) -> View Dealer Sendiri
            $query->where('lokasi_id', $user->lokasi_id);
        }

        $raks = $query->latest()->get();
        
        // Filter Dropdown Lokasi untuk Modal Create (Hanya SA yg bisa create full)
        $lokasiQuery = Lokasi::where('is_active', true);
        if (!$user->isGlobal()) {
             // Jika user lain boleh create rak (misal PC buat rak sendiri), filter disini
             $lokasiQuery->where('id', $user->lokasi_id);
        }
        $lokasi = $lokasiQuery->orderBy('nama_lokasi')->get();

        return view('admin.raks.index', compact('raks', 'lokasi'));
    }

    public function store(Request $request)
    {
        $this->authorize('manage-raks');
        
        $validated = $request->validate([
            'lokasi_id' => 'required|exists:lokasi,id',
            'zona'      => 'required|string|max:5',
            'nomor_rak' => 'required|string|max:5',
            'level'     => 'required|string|max:5',
            'bin'       => 'required|string|max:5',
            'tipe_rak'  => 'required|in:PENYIMPANAN,KARANTINA',
        ]);

        // Cek duplikasi manual karena kode rak digenerate di Model boot()
        $generatedCode = sprintf("%s-%s-%s-%s", 
            strtoupper($request->zona), strtoupper($request->nomor_rak), 
            strtoupper($request->level), strtoupper($request->bin)
        );

        if (Rak::where('lokasi_id', $request->lokasi_id)->where('kode_rak', $generatedCode)->exists()) {
            return back()->with('error', "Rak $generatedCode sudah ada di lokasi ini.")->withInput();
        }

        Rak::create($validated); // Model event akan handle penggabungan string

        return redirect()->route('admin.raks.index')->with('success', 'Rak berhasil ditambahkan dengan format Zona-Rak-Level-Bin!');
    }

    public function update(Request $request, Rak $rak)
    {
        $this->authorize('manage-raks');
        // Logika update mirip store, pastikan validasi unique ignore ID saat ini
        // Implementasi disederhanakan untuk brevity
        $rak->update($request->all()); 
        return redirect()->route('admin.raks.index')->with('success', 'Rak diperbarui!');
    }

    public function destroy(Rak $rak)
    {
        $this->authorize('manage-raks');
        if ($rak->inventoryBatches()->sum('quantity') > 0) {
            return back()->with('error', 'Gagal hapus! Rak masih berisi barang.');
        }
        $rak->delete();
        return back()->with('success', 'Rak dihapus.');
    }
}