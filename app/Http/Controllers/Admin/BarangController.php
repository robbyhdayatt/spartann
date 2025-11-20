<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Barang;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BarangController extends Controller
{
    public function index()
    {
        // $this->authorize('manage-barangs');
        $barangs = Barang::latest()->get();
        $barang = new Barang();
        return view('admin.barangs.index', compact('barangs', 'barang'));
    }

    public function store(Request $request)
    {
        // $this->authorize('manage-barangs');
        $validated = $request->validate([
            'part_name'   => 'required|string|max:255',
            'merk'        => 'nullable|string|max:100',
            'part_code'   => 'required|string|max:50|unique:barangs,part_code',
            'selling_in'  => 'nullable|numeric|min:0', // PERUBAHAN: Jadi nullable
            'selling_out' => 'required|numeric|min:0',
            'retail'      => 'required|numeric|min:0',
        ]);

        // PERUBAHAN: Default ke 0 jika kosong
        $validated['selling_in'] = $validated['selling_in'] ?? 0;

        Barang::create($validated);

        return redirect()->route('admin.barangs.index')->with('success', 'Barang baru berhasil ditambahkan.');
    }

    public function show(Barang $barang)
    {
        // $this->authorize('manage-barangs');
        return response()->json($barang);
    }

    public function update(Request $request, Barang $barang)
    {
        // $this->authorize('manage-barangs');
        $request->session()->flash('edit_form_id', $barang->id);

        $validated = $request->validate([
            'part_name'   => 'required|string|max:255',
            'merk'        => 'nullable|string|max:100',
            'part_code'   => [
                'required',
                'string',
                'max:50',
                Rule::unique('barangs')->ignore($barang->id),
            ],
            'selling_in'  => 'nullable|numeric|min:0', // PERUBAHAN: Jadi nullable
            'selling_out' => 'required|numeric|min:0',
            'retail'      => 'required|numeric|min:0',
        ]);

        // PERUBAHAN: Default ke 0 jika kosong
        $validated['selling_in'] = $validated['selling_in'] ?? 0;

        $barang->update($validated);

        return redirect()->route('admin.barangs.index')->with('success', 'Data barang berhasil diperbarui.');
    }

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
