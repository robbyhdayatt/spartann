<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dealer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DealerController extends Controller
{
    // Hak akses akan kita definisikan nanti, untuk sekarang kita fokus membangun halaman
    // public function __construct()
    // {
    //     $this->authorize('manage-dealers');
    // }

    public function index()
    {
        $dealers = Dealer::latest()->get();
        return view('admin.dealers.index', compact('dealers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'kode_dealer' => 'required|string|max:20|unique:dealers',
            'nama_dealer' => 'required|string|max:255',
            'grup' => 'nullable|string|max:50',
            'kota' => 'nullable|string|max:100',
            'singkatan' => 'nullable|string|max:10',
        ]);

        Dealer::create($validated);

        return redirect()->route('admin.dealers.index')->with('success', 'Dealer berhasil ditambahkan!');
    }

    public function update(Request $request, Dealer $dealer)
    {
        $validated = $request->validate([
            'kode_dealer' => 'required|string|max:20|unique:dealers,kode_dealer,' . $dealer->id,
            'nama_dealer' => 'required|string|max:255',
            'grup' => 'nullable|string|max:50',
            'kota' => 'nullable|string|max:100',
            'singkatan' => 'nullable|string|max:10',
            'is_active' => 'required|boolean',
        ]);

        $dealer->update($validated);

        return redirect()->route('admin.dealers.index')->with('success', 'Dealer berhasil diperbarui!');
    }

    public function destroy(Dealer $dealer)
    {
        // Tambahkan validasi jika diperlukan sebelum menghapus
        // Contoh: if ($dealer->hasRelatedTransactions()) { ... }

        $dealer->delete();
        return redirect()->route('admin.dealers.index')->with('success', 'Dealer berhasil dihapus!');
    }
}
