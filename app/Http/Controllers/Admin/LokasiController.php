<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lokasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LokasiController extends Controller
{
    public function index()
    {
        $this->authorize('manage-locations');
        $lokasi = Lokasi::orderBy('tipe', 'asc')->latest()->get();
        return view('admin.lokasi.index', compact('lokasi'));
    }

    public function store(Request $request)
    {
        $this->authorize('manage-locations');

        $validated = $request->validate([
            'kode_lokasi' => 'required|string|max:20|unique:lokasi', // Update max length to accommodate codes like UDUA001
            'nama_lokasi' => 'required|string|max:100',
            'singkatan'   => 'nullable|string|max:10',
            'npwp'        => 'nullable|string|max:25',
            'alamat'      => 'nullable|string',
            'tipe'        => 'required|in:PUSAT,DEALER',
            // Validasi field struktur
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
        $this->authorize('manage-locations');

        $validated = $request->validate([
            'kode_lokasi' => 'required|string|max:20|unique:lokasi,kode_lokasi,' . $lokasi->id,
            'nama_lokasi' => 'required|string|max:100',
            'singkatan'   => 'nullable|string|max:10',
            'npwp'        => 'nullable|string|max:25',
            'alamat'      => 'nullable|string',
            'tipe'        => 'required|in:PUSAT,DEALER',
            'is_active'   => 'required|boolean',
            // Validasi field struktur
            'koadmin'     => 'nullable|string|max:50',
            'asd'         => 'nullable|string|max:50',
            'aom'         => 'nullable|string|max:50',
            'asm'         => 'nullable|string|max:50',
            'gm'          => 'nullable|string|max:50',
        ]);

        $lokasi->update($validated);

        return redirect()->route('admin.lokasi.index')->with('success', 'Lokasi berhasil diperbarui!');
    }

    public function destroy(Lokasi $lokasi)
    {
        $this->authorize('manage-locations');

        if ($lokasi->tipe === 'PUSAT') {
            return redirect()->route('admin.lokasi.index')->with('error', 'Gudang Pusat tidak dapat dihapus!');
        }

        if ($lokasi->raks()->exists() || $lokasi->users()->exists()) {
            return redirect()->route('admin.lokasi.index')->with('error', 'Lokasi tidak dapat dihapus karena masih memiliki relasi dengan rak atau pengguna.');
        }

        $lokasi->delete();
        return redirect()->route('admin.lokasi.index')->with('success', 'Lokasi berhasil dihapus!');
    }
}