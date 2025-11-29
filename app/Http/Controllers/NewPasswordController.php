<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\View\View;
use App\Services\Logger;

class NewPasswordController extends Controller
{
    /**
     * Display the password reset view.
     */
    public function create(Request $request): View
{
    $logger = Logger::getInstance();

    try {
        $logger->info('Password reset page accessed', [
            'email' => $request->email ?? null,
            'accessed_by' => auth()->id() ?? null,
        ]);
    } catch (\Exception $e) {
        $logger->error('Password reset page logging failed', [
            'email' => $request->email ?? null,
            'error' => $e->getMessage(),
        ]);
    }

    return view('myauth.reset-password', ['request' => $request]);
}

    /**
     * Handle an incoming new password request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
{
    $logger = Logger::getInstance();

    $request->validate([
        'token' => ['required'],
        'email' => ['required', 'email'],
        'password' => ['required', 'confirmed', Rules\Password::defaults()],
    ]);

    try {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request, $logger) {
                $secretKey = env('PASSWORD_HMAC_KEY');
                $hmacHash = hash_hmac('sha256', $request->password, $secretKey);
                $bcryptHash = Hash::make($hmacHash);

                $user->forceFill([
                    'password' => $bcryptHash,
                    'remember_token' => Str::random(60),
                ])->save();
                event(new PasswordReset($user));

                $logger->info('Password reset successfully', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);
            }
        );

        if ($status == Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', __($status));
        } else {
            $logger->warning('Password reset failed', [
                'email' => $request->email,
                'status' => $status,
            ]);

            return back()->withInput($request->only('email'))
                         ->withErrors(['email' => __($status)]);
        }
    } catch (\Exception $e) {
        $logger->error('Password reset exception', [
            'email' => $request->email,
            'error' => $e->getMessage(),
        ]);

        return back()->withInput($request->only('email'))
                     ->withErrors(['email' => 'Something went wrong: '.$e->getMessage()]);
    }
}

}
