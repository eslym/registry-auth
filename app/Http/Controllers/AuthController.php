<?php

namespace App\Http\Controllers;

use App\Lib\Toast;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);
        if (auth()->attempt($credentials, $request->boolean('remember'))) {
            $user = auth()->user();
            return redirect()->intended()
                ->with(
                    'toast',
                    Toast::success("Login Successfully", "Welcome back, $user->username!")
                );
        }
        return redirect()->back()->withErrors([
            'username' => 'Invalid credentials provided.',
        ]);
    }

    public function logout(Request $request)
    {
        auth()->logout();
        return redirect()->route('auth.login')
            ->with(
                'toast',
                Toast::info("Logged out", "You have been logged out successfully.")
            );
    }
}
