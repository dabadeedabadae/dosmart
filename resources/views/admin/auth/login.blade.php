<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход — DOSMART Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: oklch(97.3% 0.006 255);
            --surface: oklch(100% 0 0);
            --border: oklch(90% 0.007 255);
            --text: oklch(23% 0.02 255);
            --text-secondary: oklch(48% 0.015 255);
            --text-muted: oklch(63% 0.012 255);
            --accent: oklch(53% 0.17 258);
            --accent-hover: oklch(46% 0.17 258);
            --accent-wash: oklch(94% 0.035 258);
            --danger-text: oklch(42% 0.16 25);
            --danger-wash: oklch(95% 0.045 25);
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            box-shadow: 0 2px 12px rgb(0 0 0 / 6%);
            width: 100%;
            max-width: 380px;
            padding: 36px 32px;
        }
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 28px;
        }
        .logo-icon {
            width: 40px; height: 40px;
            background: var(--accent);
            border-radius: 11px;
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 20px; font-weight: 800;
        }
        .logo-name { font-size: 15px; font-weight: 800; letter-spacing: .04em; }
        .logo-sub  { font-size: 11.5px; color: var(--text-muted); margin-top: 1px; }
        h1 { font-size: 20px; font-weight: 800; margin-bottom: 6px; }
        .subtitle { font-size: 13px; color: var(--text-muted); margin-bottom: 24px; }
        .form-group { margin-bottom: 14px; }
        .form-label { display: block; font-size: 13px; font-weight: 600; color: var(--text-secondary); margin-bottom: 5px; }
        .form-control {
            display: block; width: 100%;
            padding: 9px 13px;
            border: 1px solid var(--border);
            border-radius: 9px;
            background: var(--surface);
            color: var(--text);
            font-family: inherit; font-size: 13.5px;
            outline: none;
            transition: border-color .15s, box-shadow .15s;
        }
        .form-control:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-wash); }
        .form-control.is-error { border-color: var(--danger-text); }
        .error-msg { font-size: 12px; color: var(--danger-text); margin-top: 5px; }
        .alert-error {
            background: var(--danger-wash);
            color: var(--danger-text);
            border-radius: 9px;
            padding: 10px 13px;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 16px;
        }
        .btn {
            display: block; width: 100%;
            padding: 10px 16px;
            background: var(--accent);
            color: #fff;
            border: none; border-radius: 10px;
            font-family: inherit; font-size: 14px; font-weight: 700;
            cursor: pointer;
            margin-top: 20px;
            transition: background .1s;
        }
        .btn:hover { background: var(--accent-hover); }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">
            <div class="logo-icon">D</div>
            <div>
                <div class="logo-name">DOSMART</div>
                <div class="logo-sub">market · админка</div>
            </div>
        </div>

        <h1>Вход</h1>
        <p class="subtitle">Введите данные для доступа к панели</p>

        @if($errors->any())
            <div class="alert-error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('admin.login.post') }}">
            @csrf
            <div class="form-group">
                <label class="form-label" for="username">Логин</label>
                <input type="text" id="username" name="username"
                       class="form-control {{ $errors->has('username') ? 'is-error' : '' }}"
                       value="{{ old('username') }}"
                       required autofocus autocomplete="username">
            </div>
            <div class="form-group">
                <label class="form-label" for="password">Пароль</label>
                <input type="password" id="password" name="password"
                       class="form-control"
                       required autocomplete="current-password">
            </div>
            <button type="submit" class="btn">Войти</button>
        </form>
    </div>
</body>
</html>
