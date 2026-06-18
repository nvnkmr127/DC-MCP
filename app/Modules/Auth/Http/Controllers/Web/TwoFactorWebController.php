<?php

namespace App\Modules\Auth\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use PragmaRX\Google2FALaravel\Support\Authenticator;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class TwoFactorWebController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        
        $qrCodeSvg = null;
        $recoveryCodes = null;

        if ($user->two_factor_secret && !$user->two_factor_confirmed_at) {
            // Need to confirm
            $google2fa = app('pragmarx.google2fa');
            
            $qrCodeUrl = $google2fa->getQRCodeUrl(
                config('app.name'),
                $user->email,
                decrypt($user->two_factor_secret)
            );

            $renderer = new ImageRenderer(
                new RendererStyle(200),
                new SvgImageBackEnd()
            );
            $writer = new Writer($renderer);
            $qrCodeSvg = $writer->writeString($qrCodeUrl);
            
            $recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true);
        }

        return Inertia::render('Settings/TwoFactor', [
            'enabled' => $user->two_factor_secret && $user->two_factor_confirmed_at,
            'qrCodeSvg' => $qrCodeSvg,
            'recoveryCodes' => $recoveryCodes,
        ]);
    }

    public function enable(Request $request)
    {
        $user = $request->user();
        $google2fa = app('pragmarx.google2fa');

        $secret = $google2fa->generateSecretKey();
        
        $recoveryCodes = collect(range(1, 8))->map(function () {
            return \Illuminate\Support\Str::random(10) . '-' . \Illuminate\Support\Str::random(10);
        })->toArray();

        $user->update([
            'two_factor_secret' => encrypt($secret),
            'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
            'two_factor_confirmed_at' => null,
        ]);

        return back()->with('success', 'Two-factor authentication secret generated. Please confirm it.');
    }

    public function confirm(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        $user = $request->user();
        $google2fa = app('pragmarx.google2fa');

        $valid = $google2fa->verifyKey(decrypt($user->two_factor_secret), $request->code);

        if ($valid) {
            $user->update(['two_factor_confirmed_at' => now()]);
            return back()->with('success', 'Two-factor authentication enabled successfully.');
        }

        return back()->withErrors(['code' => 'The provided code was invalid.']);
    }

    public function disable(Request $request)
    {
        $request->validate([
            'password' => 'required|current_password',
        ]);

        $user = $request->user();

        $user->update([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);

        return back()->with('success', 'Two-factor authentication disabled.');
    }
}
