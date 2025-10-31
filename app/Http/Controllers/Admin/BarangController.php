<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Barang;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule; // Import Rule

class BarangController extends Controller
{
    /**
     * Tampilkan daftar semua barang.
     */
    public function index()
    {
        // $this->authorize('manage-barangs');
        $barangs = Barang::latest()->get();
        // Kirim 'barang' kosong untuk modal 'Tambah' jika terjadi error validasi
        $barang = new Barang();
        return view('admin.barangs.index', compact('barangs', 'barang'));
    }

    /**
     * Simpan barang baru ke database.
     */
    public function store(Request $request)
    {
        // $this->authorize('manage-barangs');
        $validated = $request->validate([
            'part_name' => 'required|string|max:255',
            'merk' => 'nullable|string|max:100',
            'part_code' => 'required|string|max:50|unique:barangs,part_code',
            'harga_modal' => 'required|numeric|min:0',
            'harga_jual' => 'required|numeric|min:0',
        ]);

        Barang::create($validated);

        return redirect()->route('admin.barangs.index')->with('success', 'Barang baru berhasil ditambahkan.');
    }

    /**
     * Tampilkan data JSON untuk satu barang (untuk modal Edit).
     */
    public function show(Barang $barang)
    {
        // $this->authorize('manage-barangs');
        return response()->json($barang); // Kirim data sebagai JSON
    }

    /**
     * Update barang yang ada di database.
     */
    public function update(Request $request, Barang $barang)
    {
        // $this->authorize('manage-barangs');

        // Simpan ID barang yang diedit di session flash untuk JS
        $request->session()->flash('edit_form_id', $barang->id);

        $validated = $request->validate([
            'part_name' => 'required|string|max:255',
            'merk' => 'nullable|string|max:100',
            'part_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('barangs')->ignore($barang->id), // Validasi unique ignore ID ini
            ],
            'harga_modal' => 'required|numeric|min:0',
            'harga_jual' => 'required|numeric|min:0',
        ]);

        $barang->update($validated);

        return redirect()->route('admin.barangs.index')->with('success', 'Data barang berhasil diperbarui.');
    }

    /**
     * Hapus barang dari database.
     */
    public function destroy(Barang $barang)
    {
        // $this->authorize('manage-barangs');
        try {
            $barang->delete();
            return redirect()->route('admin.barangs.index')->with('success', 'Data barang berhasil dihapus.');
        } catch (\Illuminate\Database\QueryException $e) {
            return redirect()->route('admin.barangs.index')->with('error', 'Gagal menghapus barang. Data ini mungkin masih digunakan.');
        }
    }
}
