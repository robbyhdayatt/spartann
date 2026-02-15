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
        return view('admin.barangs.index', compact('barangs'));
    }

    public function store(Request $request)
    {
        // $this->authorize('manage-barangs');

        // 1. Bersihkan format angka (hapus titik) sebelum validasi
        $this->cleanCurrencyRequest($request, ['selling_in', 'selling_out', 'retail']);

        $validated = $request->validate([
            'part_name'   => 'required|string|max:255',
            'merk'        => 'nullable|string|max:100',
            'part_code'   => 'required|string|max:50|unique:barangs,part_code',
            'selling_in'  => 'nullable|numeric|min:0',
            'selling_out' => 'required|numeric|min:0',
            'retail'      => 'required|numeric|min:0',
        ]);

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

        // 1. Bersihkan format angka (hapus titik) sebelum validasi
        $this->cleanCurrencyRequest($request, ['selling_in', 'selling_out', 'retail']);

        $validated = $request->validate([
            'part_name'   => 'required|string|max:255',
            'merk'        => 'nullable|string|max:100',
            'part_code'   => [
                'required',
                'string',
                'max:50',
                Rule::unique('barangs')->ignore($barang->id),
            ],
            'selling_in'  => 'nullable|numeric|min:0',
            'selling_out' => 'required|numeric|min:0',
            'retail'      => 'required|numeric|min:0',
        ]);

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

    /**
     * Helper untuk menghapus titik dari input currency format Indonesia
     */
    private function cleanCurrencyRequest(Request $request, array $fields)
    {
        foreach ($fields as $field) {
            if ($request->has($field)) {
                $val = $request->input($field);
                // Hapus titik, biarkan angka saja
                $cleanVal = str_replace('.', '', $val);
                $request->merge([$field => $cleanVal]);
            }
        }
    }
}