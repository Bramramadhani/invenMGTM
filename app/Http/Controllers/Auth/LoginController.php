<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User; // <— penting!

class LoginController extends Controller
{
    use \Illuminate\Foundation\Auth\AuthenticatesUsers;

    protected $redirectTo = '/home';

    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    /**
     * Dipanggil otomatis setelah login sukses oleh trait AuthenticatesUsers.
     * $user DI-isi oleh Laravel (instance App\Models\User).
     */
    protected function authenticated(Request $request, User $user)
    {
        if ($user->hasAnyRole(['admin', 'super admin'])) {
            return redirect()->route('admin.dashboard');
        }

        return redirect('/dashboard');
    }
}
