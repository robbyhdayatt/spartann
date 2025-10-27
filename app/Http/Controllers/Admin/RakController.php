<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lokasi; // DIUBAH: Menggunakan model Lokasi
use App\Models\Rak;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;

class RakController extends Controller
{
    public function index()
    {
        $this->authorize('manage-locations'); // Menggunakan gate yang sama dengan lokasi

        // PERUBAHAN: Mengambil data rak dengan relasi 'lokasi'
        $raks = Rak::with('lokasi')->latest()->get();

        // PERUBAHAN: Mengambil data lokasi untuk dropdown form
        $lokasi = Lokasi::where('is_active', true)->orderBy('nama_lokasi')->get();

        return view('admin.raks.index', compact('raks', 'lokasi'));
    }

    public function store(Request $request)
    {
        $this->authorize('manage-locations');
        $validated = $request->validate([
            'lokasi_id' => 'required|exists:lokasi,id', // PERUBAHAN: validasi ke tabel lokasi
            'nama_rak' => 'required|string|max:100',
            'tipe_rak' => 'required|in:PENYIMPANAN,KARANTINA',
            'kode_rak' => [
                'required', 'string', 'max:20',
                Rule::unique('raks')->where(fn ($query) => $query->where('lokasi_id', $request->lokasi_id)),
            ],
        ]);

        Rak::create($validated);

        return redirect()->route('admin.raks.index')->with('success', 'Rak berhasil ditambahkan!');
    }

    public function update(Request $request, Rak $rak)
    {
        $this->authorize('manage-locations');
        $validated = $request->validate([
            'lokasi_id' => 'required|exists:lokasi,id', // PERUBAHAN: validasi ke tabel lokasi
            'nama_rak' => 'required|string|max:100',
            'tipe_rak' => 'required|in:PENYIMPANAN,KARANTINA',
            'kode_rak' => [
                'required', 'string', 'max:20',
                Rule::unique('raks')->where(fn ($query) => $query->where('lokasi_id', $request->lokasi_id))->ignore($rak->id),
            ],
            'is_active' => 'required|boolean',
        ]);

        $rak->update($validated);

        return redirect()->route('admin.raks.index')->with('success', 'Rak berhasil diperbarui!');
    }

    public function destroy(Rak $rak)
    {
        $this->authorize('manage-locations');

        // Validasi ini belum ada di kode Anda, tapi saya tambahkan untuk keamanan
        if ($rak->inventoryBatches()->where('quantity', '>', 0)->exists()) {
            return redirect()->route('admin.raks.index')->with('error', 'Rak tidak dapat dihapus karena masih ada stok di dalamnya.');
        }

        $rak->delete();
        return redirect()->route('admin.raks.index')->with('success', 'Rak berhasil dihapus!');
    }
}
