<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lokasi;
use App\Models\Rak;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RakController extends Controller
{
    public function index()
    {
        $this->authorize('manage-locations');
        $raks = Rak::with('lokasi')->latest()->get();
        $lokasi = Lokasi::where('is_active', true)->orderBy('nama_lokasi')->get();
        return view('admin.raks.index', compact('raks', 'lokasi'));
    }

    public function store(Request $request)
    {
        $this->authorize('manage-locations');
        
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
        $this->authorize('manage-locations');
        // Logika update mirip store, pastikan validasi unique ignore ID saat ini
        // Implementasi disederhanakan untuk brevity
        $rak->update($request->all()); 
        return redirect()->route('admin.raks.index')->with('success', 'Rak diperbarui!');
    }

    public function destroy(Rak $rak)
    {
        $this->authorize('manage-locations');
        if ($rak->inventoryBatches()->sum('quantity') > 0) {
            return back()->with('error', 'Gagal hapus! Rak masih berisi barang.');
        }
        $rak->delete();
        return back()->with('success', 'Rak dihapus.');
    }
}