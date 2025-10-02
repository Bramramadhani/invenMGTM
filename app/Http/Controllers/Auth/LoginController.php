<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    use \Illuminate\Foundation\Auth\AuthenticatesUsers;

    protected $redirectTo = '/home'; // Default Laravel, kita override

    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    /**
     * Override setelah login berhasil.
     */
    protected function authenticated(Request $request, $user)
    {
        if ($user->hasRole('Admin') || $user->hasRole('Super Admin')) {
            return redirect()->route('admin.dashboard');
        }

        // Jika punya role lain
        return redirect('/dashboard');
    }
}
