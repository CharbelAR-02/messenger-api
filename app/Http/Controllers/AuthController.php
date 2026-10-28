<?php

namespace App\Http\Controllers;

use App\Http\Resources\MeResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
      public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return new MeResource($user);
    }

      public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        $request->session()->regenerate();

        return new MeResource(Auth::user());
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function user(Request $request)
    {
        return new MeResource($request->user());
    }
}
// //session()->regenerate() on both register and login — preventing session fixation attacks.
// Auth::attempt() handles password verification internally (Hash::check under the hood)
// ValidationException on failed login, not a generic 401 — this makes Laravel return a proper 422 with a structured error your React form can map straight to a field, rather than a vague failure.
// logout invalidates the session and regenerates the CSRF token — skipping the token regeneration is a real gap that leaves the old token valid, a subtle security miss.