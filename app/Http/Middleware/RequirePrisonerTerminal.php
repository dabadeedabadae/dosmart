<?php

namespace App\Http\Middleware;

use App\Models\Institution;
use Closure;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class RequirePrisonerTerminal
{
    public const TABLET_HEADER = 'X-Dosmart-Tablet';

    public function handle(Request $request, Closure $next)
    {
        $loopback = app()->environment('local') && config('pilot.prisoner_loopback_demo')
            && in_array($request->server('REMOTE_ADDR'), ['127.0.0.1', '::1'], true);

        if (! $loopback) {
            // The gateway must authenticate the managed tablet and replace this header.
            $expected = (string) config('pilot.prisoner_gateway_secret');
            abort_unless(strlen($expected) >= 32
                && hash_equals($expected, (string) $request->header('X-Dosmart-Tablet-Gateway')),
                403, 'Личный кабинет доступен только на планшете учреждения.');
        }

        // Учреждение берём из привязки планшета (код из админки); старая настройка шлюза — запасной вариант.
        $tablet = self::tabletInstitution((string) $request->header(self::TABLET_HEADER));
        $configured = (int) config('pilot.prisoner_gateway_institution') ?: null;
        abort_if($tablet && $configured && $tablet !== $configured, 403, 'Планшет привязан к другому учреждению.');
        $request->attributes->set('prisoner_terminal_institution', $tablet ?? $configured);

        return $next($request);
    }

    public static function tabletToken(Institution $institution): string
    {
        return Crypt::encryptString(json_encode([
            'scope' => 'dosmart-tablet', 'institution_id' => $institution->id, 'sig' => $institution->tabletSignature(),
        ], JSON_THROW_ON_ERROR));
    }

    /** Токен перестаёт действовать, если в админке выдали новый код учреждения. */
    public static function tabletInstitution(string $token): ?int
    {
        if ($token === '' || strlen($token) > 2048) {
            return null;
        }
        try {
            $data = json_decode(Crypt::decryptString($token), true);
        } catch (DecryptException) {
            return null;
        }
        if (($data['scope'] ?? null) !== 'dosmart-tablet') {
            return null;
        }
        $institution = Institution::find($data['institution_id'] ?? 0);
        $signature = $institution?->tabletSignature();

        return $institution && $institution->is_active && $signature && hash_equals($signature, (string) ($data['sig'] ?? ''))
            ? $institution->id : null;
    }
}
