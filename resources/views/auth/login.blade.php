@extends('adminlte::master')

@section('title', 'Login - SPARTAN')

@section('adminlte_css')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">

    <style>
        html, body { height: 100%; margin: 0; padding: 0; overflow: hidden; }
        .login-wrapper { display: flex; height: 100vh; width: 100vw; font-family: 'Poppins', sans-serif; position: relative; }
        .login-branding { width: 50%; background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); color: white; display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 40px; text-align: center; }
        .login-branding img { max-width: 150px; margin-bottom: 20px; animation: float 3s ease-in-out infinite; border-radius: 50%; object-fit: cover; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3); border: 3px solid rgba(255, 255, 255, 0.5); }
        .login-branding h1 { font-weight: 700; font-size: 2.5rem; margin: 0; text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3); }
        .login-branding p { font-size: 1.1rem; opacity: 0.9; text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.2); }
        .login-form-wrapper { width: 50%; display: flex; justify-content: center; align-items: center; background-color: #f4f6f9; }
        .login-box { width: 100%; max-width: 400px; padding: 20px; animation: fadeIn 1s ease-in-out; }
        .login-box .login-box-msg { padding: 0; margin-bottom: 25px; font-size: 1.5rem; font-weight: 600; color: #333; text-align: left; }
        .form-control, .btn-primary { border-radius: 8px; height: 48px; }
        .input-group-text { border-radius: 8px; }
        .btn-primary { font-weight: 600; background: #2a5298; border-color: #2a5298; }
        .btn-primary:hover { background: #1e3c72; border-color: #1e3c72; }
        #togglePassword { cursor: pointer; }
        .footer-credit { position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%); padding: 8px 20px; background-color: rgba(0, 0, 0, 0.3); border-radius: 50px; color: rgba(255, 255, 255, 0.9); font-size: 0.9rem; white-space: nowrap; z-index: 10; backdrop-filter: blur(5px); -webkit-backdrop-filter: blur(5px); border: 1px solid rgba(255, 255, 255, 0.1); }

        /* Custom Alert Style */
        .custom-alert { border-radius: 8px; font-size: 0.9rem; border: none; box-shadow: 0 2px 5px rgba(220, 53, 69, 0.2); }
        .custom-alert .close { outline: none; }

        @keyframes float { 0% { transform: translateY(0px); } 50% { transform: translateY(-10px); } 100% { transform: translateY(0px); } }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        @media (max-width: 768px) { .login-branding { display: none; } .login-form-wrapper { width: 100%; } .login-box { max-width: 350px; } .footer-credit { color: #888; text-shadow: none; background: transparent; border: none; } }
    </style>
@stop

@section('body')
<div class="login-wrapper">
    <div class="login-branding">
        <img src="{{ asset('img/SPARTAN.png') }}" alt="SPARTAN Logo">
        <h1>Selamat Datang</h1>
        <p>Sparepart Advance Transactions</p>
    </div>
    <div class="login-form-wrapper">
        <div class="login-box">
            <p class="login-box-msg">Login ke Akun Anda</p>

            {{-- [MODIFIKASI] MENAMPILKAN PESAN ERROR DENGAN ALERT --}}
            @if($errors->any() || session('error'))
                <div class="alert alert-danger alert-dismissible fade show custom-alert mb-4" role="alert">
                    <div class="d-flex align-items-start">
                        <div class="mr-3 mt-1">
                            <i class="fas fa-exclamation-triangle fa-lg"></i>
                        </div>
                        <div>
                            <strong>Gagal Masuk!</strong>
                            <div class="mt-1">
                                @if(session('error'))
                                    {{ session('error') }}
                                @else
                                    {{-- Menampilkan error pertama dari Bag (biasanya dari Controller Login) --}}
                                    {{ $errors->first() }}
                                @endif
                            </div>
                        </div>
                    </div>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endif
            {{-- [AKHIR MODIFIKASI] --}}

            <form action="{{ route('login') }}" method="post">
                @csrf
                <div class="input-group mb-3">
                    <input type="text" name="username" class="form-control @error('username') is-invalid @enderror" value="{{ old('username') }}" placeholder="Username" autofocus>
                    <div class="input-group-append"><div class="input-group-text"><span class="fas fa-user"></span></div></div>
                    {{-- Kita tetap biarkan highlight merah di input, tapi pesan teksnya sudah diwakili Alert di atas --}}
                </div>
                <div class="input-group mb-3">
                    <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror" placeholder="Password">
                    <div class="input-group-append">
                        <div class="input-group-text" id="togglePassword">
                            <span id="toggleIcon" class="fas fa-eye-slash"></span>
                        </div>
                    </div>
                </div>
                <div class="row mt-4"><div class="col-12"><button type=submit class="btn btn-primary btn-block">Sign In</button></div></div>
            </form>
        </div>
    </div>
    <div class="footer-credit">
        IT Lautan Teduh - Robby Hidayat &copy; 2025
    </div>
</div>
@stop

@section('adminlte_js')
<script>
    $(document).ready(function() {
        $('#togglePassword').on('click', function() {
            const passwordField = $('#password');
            const passwordIcon = $('#toggleIcon');
            const passwordFieldType = passwordField.attr('type');

            if (passwordFieldType === 'password') {
                passwordField.attr('type', 'text');
                passwordIcon.removeClass('fa-eye-slash').addClass('fa-eye');
            } else {
                passwordField.attr('type', 'password');
                passwordIcon.removeClass('fa-eye').addClass('fa-eye-slash');
            }
        });
    });
</script>
@stop