<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User; // penting

class LoginController extends Controller
{
    use \Illuminate\Foundation\Auth\AuthenticatesUsers;

    // Abaikan redirectTo bawaan; kita override via authenticated()
    protected $redirectTo = '/home';

    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    /**
     * Dipanggil setelah login sukses oleh trait AuthenticatesUsers.
     */
    protected function authenticated(Request $request, User $user)
    {
        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['admin', 'super admin'])) {
            return redirect()->route('admin.dashboard');   // /admin/dashboard
        }

        // Aman: arahkan ke rute bernama user.dashboard (alias /dashboard → ke admin.dashboard)
        return redirect()->route('user.dashboard');
    }

    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
