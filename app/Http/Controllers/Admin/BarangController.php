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
        $barangs = Barang::latest()->get();
        $barang = new Barang();
        return view('admin.barangs.index', compact('barangs', 'barang'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'part_name'   => 'required|string|max:255',
            'merk'        => 'nullable|string|max:100',
            'part_code'   => 'required|string|max:50|unique:barangs,part_code',
            'selling_in'  => 'required|numeric|min:0', // Baru
            'selling_out' => 'required|numeric|min:0', // Ex harga_modal
            'retail'      => 'required|numeric|min:0', // Ex harga_jual
        ]);

        Barang::create($validated);

        return redirect()->route('admin.barangs.index')->with('success', 'Barang baru berhasil ditambahkan.');
    }

    public function show(Barang $barang)
    {
        return response()->json($barang);
    }

    public function update(Request $request, Barang $barang)
    {
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
            'selling_in'  => 'required|numeric|min:0',
            'selling_out' => 'required|numeric|min:0',
            'retail'      => 'required|numeric|min:0',
        ]);

        $barang->update($validated);

        return redirect()->route('admin.barangs.index')->with('success', 'Data barang berhasil diperbarui.');
    }

    public function destroy(Barang $barang)
    {
        try {
            $barang->delete();
            return redirect()->route('admin.barangs.index')->with('success', 'Data barang berhasil dihapus.');
        } catch (\Illuminate\Database\QueryException $e) {
            return redirect()->route('admin.barangs.index')->with('error', 'Gagal menghapus barang. Data ini mungkin masih digunakan.');
        }
    }
}
