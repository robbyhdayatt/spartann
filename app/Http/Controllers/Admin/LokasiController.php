<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lokasi;
use Illuminate\Http\Request;

class LokasiController extends Controller
{
    public function index()
    {
        $this->authorize('view-lokasi');
        $lokasi = Lokasi::orderByRaw("FIELD(tipe, 'PUSAT', 'GUDANG', 'DEALER')")->latest()->get();
        return view('admin.lokasi.index', compact('lokasi'));
    }

    public function store(Request $request)
    {
        $this->authorize('manage-lokasi');

        $validated = $request->validate([
            'kode_lokasi' => 'required|string|max:20|unique:lokasi',
            'nama_lokasi' => 'required|string|max:100',
            'singkatan'   => 'nullable|string|max:10',
            'npwp'        => 'nullable|string|max:25',
            'alamat'      => 'nullable|string',
            'tipe'        => 'required|in:PUSAT,GUDANG,DEALER', 
            'koadmin'     => 'nullable|string|max:50',
            'asd'         => 'nullable|string|max:50',
            'aom'         => 'nullable|string|max:50',
            'asm'         => 'nullable|string|max:50',
            'gm'          => 'nullable|string|max:50',
        ]);

        Lokasi::create($validated);

        return redirect()->route('admin.lokasi.index')->with('success', 'Lokasi berhasil ditambahkan!');
    }

    public function update(Request $request, Lokasi $lokasi)
    {
        $this->authorize('manage-lokasi');

        $validated = $request->validate([
            'kode_lokasi' => 'required|string|max:20|unique:lokasi,kode_lokasi,' . $lokasi->id,
            'nama_lokasi' => 'required|string|max:100',
            'singkatan'   => 'nullable|string|max:10',
            'npwp'        => 'nullable|string|max:25',
            'alamat'      => 'nullable|string',
            'tipe'        => 'required|in:PUSAT,GUDANG,DEALER',
            'is_active'   => 'required|boolean',
            'koadmin'     => 'nullable|string|max:50',
            'asd'         => 'nullable|string|max:50',
            'aom'         => 'nullable|string|max:50',
            'asm'         => 'nullable|string|max:50',
            'gm'          => 'nullable|string|max:50',
        ]);
        
        if ($validated['is_active'] == 0 && $lokasi->is_active == 1) {
            $totalStokDiLokasi = \App\Models\InventoryBatch::where('lokasi_id', $lokasi->id)->sum('quantity');

            if ($totalStokDiLokasi > 0) {
                return back()->with('error', "Gagal menonaktifkan! Lokasi ini masih memiliki total sisa stok {$totalStokDiLokasi} unit. Lakukan mutasi keluar atau adjustment terlebih dahulu.")->withInput();
            }
        }

        $lokasi->update($validated);

        return redirect()->route('admin.lokasi.index')->with('success', 'Lokasi berhasil diperbarui!');
    }

    public function destroy(Lokasi $lokasi)
    {
        $this->authorize('manage-lokasi');

        if (in_array($lokasi->tipe, ['PUSAT', 'GUDANG'])) {
            return redirect()->route('admin.lokasi.index')->with('error', 'Lokasi Pusat/Gudang Utama tidak dapat dihapus!');
        }

        if ($lokasi->raks()->exists() || $lokasi->users()->exists()) {
            return redirect()->route('admin.lokasi.index')->with('error', 'Lokasi tidak dapat dihapus karena masih memiliki relasi.');
        }

        $lokasi->delete();
        return redirect()->route('admin.lokasi.index')->with('success', 'Lokasi berhasil dihapus!');
    }
}