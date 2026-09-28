<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dosmart') — DOSMART Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: oklch(97.3% 0.006 255);
            --surface: oklch(100% 0 0);
            --surface-sunken: oklch(96% 0.006 255);
            --border: oklch(90% 0.007 255);
            --text: oklch(23% 0.02 255);
            --text-secondary: oklch(48% 0.015 255);
            --text-muted: oklch(63% 0.012 255);
            --accent: oklch(53% 0.17 258);
            --accent-hover: oklch(46% 0.17 258);
            --accent-wash: oklch(94% 0.035 258);
            --success-text: oklch(38% 0.12 150);
            --success-wash: oklch(94% 0.05 150);
            --warning-text: oklch(42% 0.11 70);
            --warning-wash: oklch(95% 0.06 85);
            --danger-text: oklch(42% 0.16 25);
            --danger-wash: oklch(95% 0.045 25);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            display: flex;
            min-height: 100vh;
            font-size: 14px;
            line-height: 1.5;
        }

        /* ── Sidebar ── */
        .sidebar {
            width: 240px;
            min-width: 240px;
            background: var(--surface);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0; left: 0; bottom: 0;
            z-index: 10;
        }

        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 18px 16px 16px;
            border-bottom: 1px solid var(--border);
        }
        .logo-icon {
            width: 36px; height: 36px;
            background: var(--accent);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 17px; font-weight: 800;
            flex-shrink: 0; letter-spacing: -.02em;
        }
        .logo-text-name { font-size: 13px; font-weight: 800; color: var(--text); letter-spacing: .04em; }
        .logo-text-sub  { font-size: 11px; color: var(--text-muted); margin-top: 1px; }

        .sidebar-nav { padding: 10px 8px; flex: 1; overflow-y: auto; }
        .nav-link {
            display: flex; align-items: center; gap: 9px;
            padding: 8px 11px;
            border-radius: 8px;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 13.5px; font-weight: 500;
            transition: background .1s, color .1s;
            margin-bottom: 1px;
        }
        .nav-link:hover { background: var(--surface-sunken); color: var(--text); }
        .nav-link.active { background: var(--accent-wash); color: var(--accent); font-weight: 600; }
        .nav-icon { width: 17px; height: 17px; flex-shrink: 0; }

        .sidebar-user {
            padding: 12px 14px;
            border-top: 1px solid var(--border);
            display: flex; align-items: center; gap: 9px;
        }
        .user-avatar {
            width: 32px; height: 32px; border-radius: 50%;
            background: var(--accent); color: #fff;
            font-size: 11px; font-weight: 700;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .user-name { font-size: 12.5px; font-weight: 600; color: var(--text); }
        .user-role { font-size: 11px; color: var(--text-muted); }
        .user-menu-btn {
            margin-left: auto;
            background: none; border: none; cursor: pointer;
            color: var(--text-muted); padding: 4px; border-radius: 5px;
            display: flex; align-items: center;
        }
        .user-menu-btn:hover { background: var(--surface-sunken); color: var(--text); }

        /* ── Main ── */
        .main {
            margin-left: 240px;
            flex: 1;
            padding: 32px 36px;
            min-height: 100vh;
        }

        /* ── Page header ── */
        .page-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 22px;
        }
        .page-title { font-size: 24px; font-weight: 800; letter-spacing: -.025em; }

        /* ── Buttons ── */
        .btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 15px;
            border-radius: 10px;
            font-size: 13px; font-weight: 600; font-family: inherit;
            border: none; cursor: pointer; text-decoration: none;
            transition: background .1s, opacity .1s;
            white-space: nowrap;
        }
        .btn-primary { background: var(--accent); color: #fff; }
        .btn-primary:hover { background: var(--accent-hover); color: #fff; }
        .btn-ghost { background: transparent; border: 1px solid var(--border); color: var(--text-secondary); }
        .btn-ghost:hover { background: var(--surface-sunken); }
        .btn-danger-soft { background: var(--danger-wash); color: var(--danger-text); }
        .btn-danger-soft:hover { filter: brightness(.96); }
        .btn-sm { padding: 6px 11px; font-size: 12px; border-radius: 8px; }

        /* ── Card ── */
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: 0 1px 2px rgb(0 0 0 / 4%);
            overflow: hidden;
        }

        /* ── Toolbar ── */
        .toolbar { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; flex-wrap: wrap; }
        .toolbar-left { display: flex; align-items: center; gap: 10px; flex: 1; }
        .search-wrap { position: relative; }
        .search-icon { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none; }
        .search-input { padding-left: 32px !important; width: 210px; }

        /* ── Inputs ── */
        .form-control {
            display: block; width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: 9px;
            background: var(--surface);
            color: var(--text);
            font-family: inherit; font-size: 13.5px;
            outline: none;
            transition: border-color .15s, box-shadow .15s;
        }
        .form-control:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-wash); }
        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23999' stroke-width='2'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 9px center;
            padding-right: 30px;
        }
        .form-label { display: block; margin-bottom: 5px; font-size: 13px; font-weight: 600; color: var(--text-secondary); }
        .form-group { margin-bottom: 16px; }
        .form-hint { font-size: 11.5px; color: var(--text-muted); margin-top: 4px; }
        .form-error { font-size: 11.5px; color: var(--danger-text); margin-top: 4px; }
        .form-control.is-error { border-color: var(--danger-text); }
        textarea.form-control { resize: vertical; min-height: 80px; }

        /* ── Table ── */
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        thead th {
            padding: 9px 16px;
            font-size: 10.5px; font-weight: 700;
            text-transform: uppercase; letter-spacing: .08em;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border);
            text-align: left; white-space: nowrap;
        }
        tbody tr { border-bottom: 1px solid var(--border); }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: var(--surface-sunken); }
        tbody td { padding: 12px 16px; font-size: 13.5px; vertical-align: middle; }

        /* ── Badges ── */
        .badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 3px 9px; border-radius: 20px;
            font-size: 12px; font-weight: 600; white-space: nowrap;
        }
        .badge-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; flex-shrink: 0; }
        .badge-success { background: var(--success-wash); color: var(--success-text); }
        .badge-warning { background: var(--warning-wash); color: var(--warning-text); }
        .badge-danger  { background: var(--danger-wash);  color: var(--danger-text);  }
        .badge-neutral { background: var(--surface-sunken); color: var(--text-secondary); }
        .badge-accent  { background: var(--accent-wash); color: var(--accent); }

        /* ── Category pill ── */
        .cat-pill {
            display: inline-block;
            padding: 3px 9px; border-radius: 6px;
            background: var(--surface-sunken);
            font-size: 12px; font-weight: 500; color: var(--text-secondary);
        }

        /* ── Icon buttons ── */
        .icon-btn {
            background: none; border: none; cursor: pointer;
            padding: 6px; border-radius: 6px;
            color: var(--text-muted);
            display: inline-flex; align-items: center; justify-content: center;
            text-decoration: none;
            transition: background .1s, color .1s;
        }
        .icon-btn:hover         { background: var(--surface-sunken); color: var(--text); }
        .icon-btn.delete:hover  { background: var(--danger-wash); color: var(--danger-text); }

        /* ── Product image placeholder ── */
        .img-placeholder {
            width: 40px; height: 40px; border-radius: 8px;
            background: var(--surface-sunken);
            border: 1px solid var(--border);
            display: flex; align-items: center; justify-content: center;
            color: var(--text-muted); flex-shrink: 0;
        }

        /* ── Category dot ── */
        .cat-dot {
            width: 10px; height: 10px; border-radius: 50%;
            background: var(--accent); flex-shrink: 0;
        }
        .cat-dot.empty { background: var(--border); }

        /* ── Category list card ── */
        .cat-list-item {
            display: flex; align-items: center; gap: 12px;
            padding: 14px 20px;
            border-bottom: 1px solid var(--border);
        }
        .cat-list-item:last-child { border-bottom: none; }
        .cat-list-item:hover { background: var(--surface-sunken); }
        .cat-list-name { font-size: 14px; font-weight: 600; color: var(--text); }
        .cat-list-count { font-size: 12px; color: var(--text-muted); margin-top: 1px; }
        .cat-list-actions { margin-left: auto; display: flex; gap: 2px; }

        /* ── Stat cards ── */
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 18px; }
        .stat-card { padding: 20px 22px; }
        .stat-label { font-size: 12px; font-weight: 500; color: var(--text-muted); margin-bottom: 8px; }
        .stat-value { font-size: 30px; font-weight: 800; color: var(--text); letter-spacing: -.03em; line-height: 1; margin-bottom: 8px; }
        .stat-trend { font-size: 12px; font-weight: 500; }
        .stat-trend.up   { color: var(--success-text); }
        .stat-trend.down { color: var(--danger-text); }
        .stat-trend.neutral { color: var(--text-muted); }

        /* ── Alerts ── */
        .alert { padding: 11px 15px; border-radius: 10px; font-size: 13.5px; margin-bottom: 14px; font-weight: 500; }
        .alert-success { background: var(--success-wash); color: var(--success-text); }
        .alert-danger  { background: var(--danger-wash);  color: var(--danger-text);  }

        /* ── Divider ── */
        .divider { height: 1px; background: var(--border); margin: 20px 0; }

        /* ── Form layout ── */
        .form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .form-actions { display: flex; gap: 10px; padding-top: 4px; }

        /* ── Modal-like inline block ── */
        .form-card { max-width: 580px; }
        .form-card .card-body { padding: 24px; }

        /* ── Report chart ── */
        .chart-wrap { padding: 20px 20px 10px; }
        .chart-label { font-size: 13.5px; font-weight: 700; color: var(--text); margin-bottom: 2px; }
        .chart-sub   { font-size: 12px; color: var(--text-muted); margin-bottom: 16px; }

        /* ── Recent orders in report ── */
        .recent-item {
            display: flex; align-items: center;
            padding: 13px 20px;
            border-bottom: 1px solid var(--border);
        }
        .recent-item:last-child { border-bottom: none; }
        .recent-name { font-size: 13.5px; font-weight: 600; flex: 1; }
        .recent-amount { font-size: 13.5px; font-weight: 700; color: var(--text); margin-left: 16px; }

        /* ── Dropdown filter ── */
        .filter-wrap { display: flex; align-items: center; gap: 8px; }
        .filter-select { min-width: 150px; }
    </style>
    @stack('head')
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-logo">
        <div class="logo-icon">D</div>
        <div>
            <div class="logo-text-name">DOSMART</div>
            <div class="logo-text-sub">market · админка</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <a href="{{ route('admin.products.index') }}"
           class="nav-link {{ request()->routeIs('admin.products*') ? 'active' : '' }}">
            <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/>
                <path d="m3.3 7 8.7 5 8.7-5M12 22V12"/>
            </svg>
            Товары
        </a>
        <a href="{{ route('admin.categories.index') }}"
           class="nav-link {{ request()->routeIs('admin.categories*') ? 'active' : '' }}">
            <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/>
                <line x1="7" y1="7" x2="7.01" y2="7"/>
            </svg>
            Категории
        </a>
        <a href="{{ route('admin.institutions.index') }}"
           class="nav-link {{ request()->routeIs('admin.institutions*') ? 'active' : '' }}">
            <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path d="M3 21h18M5 21V3h14v18M9 7h2m2 0h2M9 11h2m2 0h2M10 21v-6h4v6"/>
            </svg>
            Учреждения
        </a>
        <a href="{{ route('admin.orders.index') }}"
           class="nav-link {{ request()->routeIs('admin.orders*') ? 'active' : '' }}">
            <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path d="M9 11l3 3L22 4"/>
                <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
            </svg>
            Заказы
        </a>
        <a href="{{ route('admin.report') }}"
           class="nav-link {{ request()->routeIs('admin.report') ? 'active' : '' }}">
            <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <line x1="18" y1="20" x2="18" y2="10"/>
                <line x1="12" y1="20" x2="12" y2="4"/>
                <line x1="6"  y1="20" x2="6"  y2="14"/>
            </svg>
            Отчёты
        </a>
    </nav>

    <div class="sidebar-user">
        <div class="user-avatar">АД</div>
        <div>
            <div class="user-name">{{ auth()->user()->name ?? 'Админ' }}</div>
            <div class="user-role">Администратор</div>
        </div>
        <form method="POST" action="{{ route('admin.logout') }}" style="margin-left:auto">
            @csrf
            <button type="submit" class="user-menu-btn" title="Выйти">
                <svg width="15" height="15" fill="currentColor" viewBox="0 0 24 24">
                    <circle cx="12" cy="5"  r="1.5"/>
                    <circle cx="12" cy="12" r="1.5"/>
                    <circle cx="12" cy="19" r="1.5"/>
                </svg>
            </button>
        </form>
    </div>
</aside>

<main class="main">
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @if($errors->any())<div class="alert alert-danger" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    @yield('content')
</main>

@stack('scripts')
</body>
</html>
