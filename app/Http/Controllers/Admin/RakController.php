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

        if ($user->isGlobal()) {
        } 
        elseif ($user->isPusat()) {
            $query->whereHas('lokasi', fn($q) => $q->where('tipe', 'DEALER'));
        }
        elseif ($user->isGudang()) {
            $query->where('lokasi_id', $user->lokasi_id);
        }
        elseif ($user->isDealer()) {
            $query->where('lokasi_id', $user->lokasi_id);
        }

        $raks = $query->latest()->get();
        $lokasiQuery = Lokasi::where('is_active', true);
        if (!$user->isGlobal()) {
             $lokasiQuery->where('id', $user->lokasi_id);
        }
        $lokasi = $lokasiQuery->orderBy('nama_lokasi')->get();

        return view('admin.raks.index', compact('raks', 'lokasi'));
    }

    public function store(Request $request)
    {
        $this->authorize('manage-raks');
        
        $validated = $request->validate([
            'lokasi_id' => [
                'required',
                Rule::exists('lokasi', 'id')->where(function ($query) {
                    $query->where('is_active', true);
                }),
            ],
            'zona'      => 'required|string|max:5',
            'nomor_rak' => 'required|string|max:5',
            'level'     => 'required|string|max:5',
            'bin'       => 'required|string|max:5',
            'tipe_rak'  => 'required|in:PENYIMPANAN,KARANTINA',
        ]);

        $generatedCode = sprintf("%s-%s-%s-%s", 
            strtoupper($request->zona), strtoupper($request->nomor_rak), 
            strtoupper($request->level), strtoupper($request->bin)
        );

        if (Rak::where('lokasi_id', $request->lokasi_id)->where('kode_rak', $generatedCode)->exists()) {
            return back()->with('error', "Rak $generatedCode sudah ada di lokasi ini.")->withInput();
        }

        Rak::create($validated);

        return redirect()->route('admin.raks.index')->with('success', 'Rak berhasil ditambahkan dengan format Zona-Rak-Level-Bin!');
    }

    public function update(Request $request, Rak $rak)
    {
        $this->authorize('manage-raks');
        
        $validated = $request->validate([
            'lokasi_id' => 'required|exists:lokasi,id',
            'zona'      => 'required|string|max:5',
            'nomor_rak' => 'required|string|max:5',
            'level'     => 'required|string|max:5',
            'bin'       => 'required|string|max:5',
            'tipe_rak'  => 'required|in:PENYIMPANAN,KARANTINA',
            'is_active' => 'required|boolean', // Pastikan kolom ini divalidasi
        ]);

        // [MODIFIKASI] VALIDASI LOGIKA: Cek isi rak sebelum dinonaktifkan
        if ($request->is_active == 0 && $rak->is_active == 1) {
            // Hitung total stok di rak ini
            $totalStok = $rak->inventoryBatches()->sum('quantity');
            
            if ($totalStok > 0) {
                return back()->with('error', "Gagal menonaktifkan! Rak ini masih menyimpan stok sebanyak {$totalStok} unit. Pindahkan stok terlebih dahulu (Mutasi/Putaway).")->withInput();
            }
        }

        // Generate ulang kode rak jika ada perubahan struktur
        $generatedCode = sprintf("%s-%s-%s-%s", 
            strtoupper($request->zona), strtoupper($request->nomor_rak), 
            strtoupper($request->level), strtoupper($request->bin)
        );

        // Cek duplikasi kode rak (kecuali rak ini sendiri)
        if (Rak::where('lokasi_id', $request->lokasi_id)
                ->where('kode_rak', $generatedCode)
                ->where('id', '!=', $rak->id)
                ->exists()) {
            return back()->with('error', "Rak $generatedCode sudah ada di lokasi ini.")->withInput();
        }

        $rak->update($validated); 
        
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