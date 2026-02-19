<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request; // [PENTING] Tambahkan ini

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    /**
     * Get the login username to be used by the controller.
     *
     * @return string
     */
    public function username()
    {
        return 'username';
    }

    /**
     * [MODIFIKASI]
     * Method ini dipanggil otomatis setelah user berhasil login (password benar).
     * Kita gunakan untuk validasi status bertingkat.
     */
    protected function authenticated(Request $request, $user)
    {
        // 1. Cek Status Akun User
        if (!$user->is_active) {
            $this->guard()->logout();
            $request->session()->invalidate();
            return back()->withErrors(['username' => 'Akses Ditolak: Akun Anda telah dinonaktifkan.']);
        }

        // 2. Cek Status Jabatan
        if ($user->jabatan && !$user->jabatan->is_active) {
            $this->guard()->logout();
            $request->session()->invalidate();
            return back()->withErrors(['username' => "Akses Ditolak: Jabatan '{$user->jabatan->nama_jabatan}' sedang dinonaktifkan."]);
        }

        // 3. Cek Status Lokasi (Gudang/Dealer)
        // Jika user terikat lokasi, dan lokasi tersebut tidak aktif
        if ($user->lokasi_id && $user->lokasi && !$user->lokasi->is_active) {
            $this->guard()->logout();
            $request->session()->invalidate();
            return back()->withErrors(['username' => "Akses Ditolak: Lokasi kerja '{$user->lokasi->nama_lokasi}' sedang ditutup/dinonaktifkan."]);
        }
        
        // Jika semua lolos, biarkan lanjut ke redirect default
    }
}