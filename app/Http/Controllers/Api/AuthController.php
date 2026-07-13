<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'level_pendidikan' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'level_pendidikan' => $data['level_pendidikan'] ?? null,
            'password' => Hash::make($data['password']),
            'role' => 'siswa',
            'is_active' => true,
        ]);

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Registrasi berhasil. Silakan cek email Anda untuk verifikasi akun.',
            'email' => $user->email,
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($data)) {
            throw ValidationException::withMessages([
                'email' => 'Email atau password salah.',
            ]);
        }

        $user = User::where('email', $data['email'])->firstOrFail();

        if (! $user->is_active) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => 'Akun Anda tidak aktif, hubungi admin.',
            ]);
        }

        if (! $user->hasVerifiedEmail()) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => 'Email Anda belum diverifikasi. Silakan cek email atau kirim ulang link verifikasi.',
            ]);
        }

        $token = $user->createToken('siswa-app')->plainTextToken;

        return response()->json(['user' => $user, 'token' => $token]);
    }

    /**
     * POST /api/auth/google
     * Menerima ID token dari Google Identity Services (frontend), verifikasi
     * ke endpoint tokeninfo Google, lalu login atau bikin akun baru otomatis.
     */
    public function google(Request $request)
    {
        $data = $request->validate([
            'credential' => ['required', 'string'],
        ]);

        $verify = Http::get('https://oauth2.googleapis.com/tokeninfo', [
            'id_token' => $data['credential'],
        ]);

        if (! $verify->ok()) {
            throw ValidationException::withMessages([
                'credential' => 'Token Google tidak valid.',
            ]);
        }

        $payload = $verify->json();

        $expectedClientId = config('services.google.client_id');
        if ($expectedClientId && ($payload['aud'] ?? null) !== $expectedClientId) {
            throw ValidationException::withMessages([
                'credential' => 'Token Google tidak dikenali aplikasi ini.',
            ]);
        }

        $googleId = $payload['sub'] ?? null;
        $email = $payload['email'] ?? null;
        $name = $payload['name'] ?? ($payload['email'] ?? 'Pengguna Google');

        if (! $googleId || ! $email) {
            throw ValidationException::withMessages([
                'credential' => 'Data akun Google tidak lengkap.',
            ]);
        }

        $user = User::where('google_id', $googleId)->first()
            ?? User::where('email', $email)->first();

        if ($user) {
            if (! $user->google_id) {
                $user->google_id = $googleId;
            }
            // Email dari Google sudah pasti terverifikasi oleh Google sendiri.
            if (! $user->hasVerifiedEmail()) {
                $user->email_verified_at = now();
            }
            $user->save();
        } else {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'google_id' => $googleId,
                'password' => Hash::make(Str::random(32)),
                'role' => 'siswa',
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => 'Akun Anda tidak aktif, hubungi admin.',
            ]);
        }

        $token = $user->createToken('siswa-app')->plainTextToken;

        return response()->json(['user' => $user, 'token' => $token]);
    }

    public function verifyEmail(Request $request, $id, $hash)
    {
        $user = User::findOrFail($id);

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return redirect(config('app.frontend_url') . '/login?verified=0');
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return redirect(config('app.frontend_url') . '/login?verified=1');
    }

    public function resendVerification(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if ($user && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json([
            'message' => 'Jika email terdaftar dan belum terverifikasi, link verifikasi baru sudah dikirim.',
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        Password::sendResetLink($data);

        return response()->json([
            'message' => 'Jika email terdaftar, link reset password sudah dikirim.',
        ]);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            $data,
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json([
            'message' => 'Password berhasil direset. Silakan login dengan password baru.',
        ]);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Berhasil logout.']);
    }
}
