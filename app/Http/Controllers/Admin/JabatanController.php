<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Jabatan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JabatanController extends Controller
{
    public function index()
    {
        $this->authorize('manage-jabatans');
        
        $jabatans = Jabatan::orderBy('nama_jabatan')->get();
        return view('admin.jabatans.index', compact('jabatans'));
    }

    public function store(Request $request)
    {
        $this->authorize('manage-jabatans');

        $validated = $request->validate([
            'nama_jabatan' => 'required|string|max:100|unique:jabatans,nama_jabatan',
            'singkatan'    => 'required|string|max:10|unique:jabatans,singkatan',
        ]);

        Jabatan::create([
            'nama_jabatan' => $validated['nama_jabatan'],
            'singkatan'    => strtoupper($validated['singkatan']),
            'is_active'    => true
        ]);

        return redirect()->route('admin.jabatans.index')->with('success', 'Jabatan berhasil ditambahkan.');
    }

    public function show(Jabatan $jabatan)
    {
        $this->authorize('manage-jabatans');
        return response()->json($jabatan);
    }

    public function update(Request $request, Jabatan $jabatan)
    {
        $this->authorize('manage-jabatans');

        $validated = $request->validate([
            'nama_jabatan' => [
                'required', 'string', 'max:100',
                Rule::unique('jabatans')->ignore($jabatan->id)
            ],
            'singkatan' => [
                'required', 'string', 'max:10',
                Rule::unique('jabatans')->ignore($jabatan->id)
            ],
            'is_active' => 'required|boolean'
        ]);

        // [MODIFIKASI] VALIDASI: Cek user aktif sebelum nonaktifkan jabatan
        if ($validated['is_active'] == 0) {
            $userCount = $jabatan->users()->where('is_active', true)->count();
            if ($userCount > 0) {
                return back()->with('error', "Gagal menonaktifkan! Masih ada {$userCount} user aktif yang menggunakan jabatan ini. Nonaktifkan user terkait terlebih dahulu.")->withInput();
            }
        }

        $jabatan->update([
            'nama_jabatan' => $validated['nama_jabatan'],
            'singkatan'    => strtoupper($validated['singkatan']),
            'is_active'    => $validated['is_active']
        ]);

        return redirect()->route('admin.jabatans.index')->with('success', 'Jabatan berhasil diperbarui.');
    }

    public function destroy(Jabatan $jabatan)
    {
        $this->authorize('manage-jabatans');

        // Cek apakah jabatan masih dipakai oleh user
        if ($jabatan->users()->count() > 0) {
            return back()->with('error', 'Gagal hapus: Masih ada user yang menjabat posisi ini.');
        }

        $jabatan->delete();
        return redirect()->route('admin.jabatans.index')->with('success', 'Jabatan berhasil dihapus.');
    }
}