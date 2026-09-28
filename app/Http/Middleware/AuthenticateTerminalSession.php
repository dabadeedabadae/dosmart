<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AuthenticateTerminalSession
{
    public function handle(Request $request, Closure $next)
    {
        $url = (string) config('pilot.terminal_identity_url');
        // Only an operator-configured HTTPS endpoint receives the existing user token.
        abort_unless(filter_var($url, FILTER_VALIDATE_URL) && parse_url($url, PHP_URL_SCHEME) === 'https', 503, 'Проверка сессии терминала пока не настроена.');
        $token = $request->bearerToken();
        abort_unless(is_string($token) && strlen($token) > 0 && strlen($token) <= 8192, 401);
        try {
            $response = Http::acceptJson()->withToken($token)->withOptions(['allow_redirects' => false])->connectTimeout(5)->timeout(12)->get($url);
        } catch (ConnectionException $e) {
            abort(503, 'Сервер терминала временно недоступен.');
        }
        if (in_array($response->status(), [401, 403, 409], true)) {
            abort(401, 'Сессия терминала недействительна.');
        }
        abort_unless($response->ok(), 503, 'Не удалось проверить сессию терминала.');
        $user = $response->json();
        abort_unless(is_array($user) && filter_var($user['id'] ?? null, FILTER_VALIDATE_INT) > 0 && is_string($user['id_number'] ?? null), 401);
        $subject = hash('sha256', $url.'|'.$user['id']);
        $request->attributes->set('terminal_subject', $subject);
        $name = implode(' ', array_filter(array_map(fn ($key) => is_string($user[$key] ?? null) ? $user[$key] : '', ['last_name', 'first_name', 'middle_name'])));
        $map = config('pilot.terminal_institution_map', []);
        $institution = is_array($map) ? ($map[(string) ($user['establishment']['id'] ?? '')] ?? null) : null;
        if ($request->is('api/v1/terminal/drafts')) {
            $request->merge(['prisoner_name' => mb_substr($name, 0, 255) ?: null, 'institution_id' => $institution]);
        }

        return $next($request);
    }
}
