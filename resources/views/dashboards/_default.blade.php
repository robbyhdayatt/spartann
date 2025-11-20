<div class="jumbotron">
    <h1 class="display-4">Selamat Datang, {{ Auth::user()->nama }}!</h1>
    <p class="lead">Anda telah berhasil masuk ke Sistem Spartan.</p>
    <hr class="my-4">
    <p>Silakan gunakan menu di samping kiri untuk mulai bekerja.</p>
    <a class="btn btn-primary btn-lg" href="{{ route('admin.profile.show') }}" role="button">Lihat Profil Saya</a>
</div>
