<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Inventario') — {{ config('app.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('brand/rolo-logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.default.min.css">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
    @include('partials.searchable-selects')
    <style>
        :root {
            --bg: #ececeb;
            --bg-accent: #e2e1de;
            --surface: #f7f7f5;
            --ink: #111111;
            --muted: #6a6a66;
            --line: #d4d3cf;
            --brand: #111111;
            --brand-dark: #000000;
            --signal: #e85d04;
            --signal-dark: #c44d03;
            --danger: #c1121f;
            --warn: #b45309;
            --ok: #2d6a4f;
            --sidebar: #0a0a0a;
            --sidebar-ink: #f5f5f5;
            --sidebar-muted: #a3a3a3;
            --font-display: "Oswald", Impact, sans-serif;
            --font-body: "Space Grotesk", "Segoe UI", sans-serif;
            --radius: .55rem;
            --shadow: 0 10px 30px rgba(0, 0, 0, .06);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: var(--font-body);
            background:
                radial-gradient(ellipse 80% 50% at 100% -10%, rgba(232, 93, 4, .08), transparent 50%),
                linear-gradient(180deg, var(--bg) 0%, var(--bg-accent) 100%);
            color: var(--ink);
            min-height: 100vh;
        }
        a { color: var(--ink); text-decoration: none; }
        a:hover { color: var(--signal); }
        .shell { display: grid; grid-template-columns: 260px 1fr; min-height: 100vh; }
        .mobile-bar {
            display: none;
            position: sticky;
            top: 0;
            z-index: 45;
            align-items: center;
            gap: .75rem;
            padding: .7rem .9rem;
            background: #0a0a0a;
            border-bottom: 1px solid #222;
            color: #fff;
        }
        .mobile-bar-brand {
            display: flex;
            align-items: center;
            gap: .65rem;
            min-width: 0;
            color: #fff;
            text-decoration: none;
            flex: 1;
        }
        .mobile-bar-brand:hover { color: #fff; text-decoration: none; }
        .mobile-bar-brand img {
            width: 36px;
            height: 36px;
            border-radius: .3rem;
            object-fit: cover;
            flex-shrink: 0;
        }
        .mobile-bar-brand span {
            font-family: var(--font-display);
            letter-spacing: .08em;
            text-transform: uppercase;
            font-size: .92rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .menu-toggle {
            width: 42px;
            height: 42px;
            border-radius: var(--radius);
            border: 1px solid #333;
            background: transparent;
            color: #fff;
            cursor: pointer;
            display: inline-flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            gap: 4px;
            padding: 0;
            flex-shrink: 0;
        }
        .menu-toggle span {
            display: block;
            width: 18px;
            height: 2px;
            background: currentColor;
            border-radius: 2px;
        }
        .nav-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .45);
            z-index: 50;
            border: 0;
            padding: 0;
            cursor: pointer;
        }
        .sidebar {
            background:
                linear-gradient(180deg, rgba(232, 93, 4, .12) 0%, transparent 28%),
                repeating-linear-gradient(
                    -45deg,
                    transparent,
                    transparent 10px,
                    rgba(255, 255, 255, .015) 10px,
                    rgba(255, 255, 255, .015) 11px
                ),
                var(--sidebar);
            color: var(--sidebar-ink);
            padding: 1.35rem 1rem 1.25rem;
            display: flex;
            flex-direction: column;
            border-right: 1px solid #1f1f1f;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow: auto;
            z-index: 60;
        }
        .sidebar-close {
            display: none;
            width: 42px;
            height: 42px;
            border-radius: var(--radius);
            border: 1px solid #333;
            background: transparent;
            color: #fff;
            cursor: pointer;
            font-size: 1.4rem;
            line-height: 1;
            margin: 0 0 .75rem auto;
        }
        .brand-block {
            display: block;
            text-align: center;
            margin: 0 0 1.5rem;
            text-decoration: none;
            animation: brandIn .55s ease both;
        }
        .brand-block:hover { text-decoration: none; }
        .brand-logo {
            width: 100%;
            max-width: 168px;
            height: auto;
            display: block;
            margin: 0 auto .65rem;
            border-radius: .35rem;
        }
        .brand-tag {
            margin: 0;
            font-family: var(--font-display);
            font-size: .72rem;
            letter-spacing: .28em;
            text-transform: uppercase;
            color: var(--sidebar-muted);
        }
        .nav { display: flex; flex-direction: column; gap: .2rem; flex: 1; }
        .nav-label {
            font-size: .68rem;
            letter-spacing: .18em;
            text-transform: uppercase;
            color: #666;
            margin: .85rem .8rem .35rem;
            font-weight: 600;
        }
        .nav a {
            display: block;
            color: #d4d4d4;
            padding: .7rem .85rem;
            border-radius: var(--radius);
            text-decoration: none;
            font-weight: 500;
            font-size: .95rem;
            border: 1px solid transparent;
            transition: background .18s ease, color .18s ease, border-color .18s ease, transform .18s ease;
        }
        .nav a:hover {
            background: rgba(255, 255, 255, .06);
            color: #fff;
            text-decoration: none;
            transform: translateX(2px);
        }
        .nav a.active {
            background: #fff;
            color: #000;
            border-color: #fff;
            font-weight: 600;
        }
        .sidebar-footer { margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #222; }
        .sidebar-footer form { margin: 0; }
        .sidebar-footer button {
            width: 100%;
            background: transparent;
            border: 1px solid #333;
            color: #cfcfcf;
            border-radius: var(--radius);
            padding: .7rem .85rem;
            cursor: pointer;
            font: inherit;
            transition: background .18s ease, border-color .18s ease, color .18s ease;
        }
        .sidebar-footer button:hover {
            background: rgba(255, 255, 255, .06);
            border-color: var(--signal);
            color: #fff;
        }
        .main {
            padding: 1.75rem 2rem 2.5rem;
            animation: mainIn .45s ease both;
        }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.35rem;
        }
        h1 {
            margin: 0;
            font-family: var(--font-display);
            font-size: clamp(1.7rem, 2.4vw, 2.15rem);
            letter-spacing: .04em;
            text-transform: uppercase;
            line-height: 1.1;
        }
        .card {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: .7rem;
            padding: 1.25rem;
            box-shadow: var(--shadow);
        }
        .flash {
            padding: .9rem 1rem;
            border-radius: var(--radius);
            margin-bottom: 1rem;
            background: #e8f5e9;
            color: var(--ok);
            border: 1px solid #b7dfc3;
            animation: flashIn .35s ease both;
        }
        .errors {
            padding: .9rem 1rem;
            border-radius: var(--radius);
            margin-bottom: 1rem;
            background: #fdecee;
            color: var(--danger);
            border: 1px solid #f5c2c7;
        }
        .errors ul { margin: .25rem 0 0; padding-left: 1.1rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: .8rem .5rem; border-bottom: 1px solid var(--line); vertical-align: top; }
        th {
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--muted);
            font-weight: 600;
        }
        .btn {
            display: inline-block;
            background: var(--brand);
            color: #fff;
            border: 1px solid var(--brand);
            border-radius: var(--radius);
            padding: .58rem 1rem;
            cursor: pointer;
            text-decoration: none;
            font-size: .92rem;
            font-weight: 600;
            font-family: inherit;
            transition: background .18s ease, transform .15s ease, border-color .18s ease;
        }
        .btn:hover {
            background: var(--signal);
            border-color: var(--signal);
            color: #fff;
            text-decoration: none;
            transform: translateY(-1px);
        }
        .btn-secondary {
            background: #fff;
            color: var(--ink);
            border-color: var(--line);
        }
        .btn-secondary:hover {
            background: var(--ink);
            border-color: var(--ink);
            color: #fff;
        }
        .btn-danger { background: var(--danger); border-color: var(--danger); }
        .btn-danger:hover { background: #9b0e18; border-color: #9b0e18; }
        .action-menu { position: relative; }
        .action-menu > summary { list-style: none; }
        .action-menu > summary::-webkit-details-marker { display: none; }
        .action-menu-panel {
            position: absolute; right: 0; top: calc(100% + .35rem); z-index: 40;
            min-width: 240px; padding: .35rem; background: #fff;
            border: 1px solid var(--line); border-radius: var(--radius);
            box-shadow: 0 12px 32px rgba(0, 0, 0, .14);
        }
        .action-menu-panel form { margin: 0; }
        .action-menu-panel button, .action-menu-panel a {
            display: block; width: 100%; padding: .55rem .7rem; border: 0; border-radius: .4rem;
            background: none; color: var(--ink); font: inherit; text-align: left; text-decoration: none; cursor: pointer;
        }
        .action-menu-panel button:hover, .action-menu-panel a:hover { background: #f1f0ec; }
        .action-menu-panel .danger { color: var(--danger); font-weight: 600; }
        .action-menu-panel .danger:hover { background: #fdecee; }
        .action-menu-label { padding: .45rem .7rem .15rem; font-size: .72rem; letter-spacing: .05em; text-transform: uppercase; color: var(--muted); }
        .action-menu-sep { margin: .3rem 0; border-top: 1px solid var(--line); }
        .btn-link { background: transparent; color: var(--ink); padding: 0; border: 0; }
        .btn-link:hover { color: var(--signal); background: transparent; transform: none; }
        .actions { display: flex; gap: .75rem; flex-wrap: wrap; align-items: center; }
        .pagination {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: .75rem 1rem;
            margin-top: .25rem;
        }
        .pagination-meta { margin: 0; font-size: .9rem; }
        .pagination-links {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            flex-wrap: wrap;
            gap: .35rem;
            align-items: center;
        }
        .pagination-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 2.35rem;
            padding: .45rem .7rem;
            border: 1px solid var(--line);
            border-radius: var(--radius);
            background: #fff;
            color: var(--ink);
            text-decoration: none;
            font: inherit;
            font-size: .9rem;
            font-weight: 600;
            line-height: 1.2;
        }
        a.pagination-btn:hover {
            background: var(--ink);
            border-color: var(--ink);
            color: #fff;
            text-decoration: none;
        }
        .pagination-btn.is-current {
            background: var(--ink);
            border-color: var(--ink);
            color: #fff;
        }
        .pagination-btn.is-disabled {
            opacity: .45;
            cursor: not-allowed;
            background: #f3f2ef;
        }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; }
        label { display: block; font-size: .88rem; margin-bottom: .35rem; color: #333; font-weight: 500; }
        input[type=text],
        input[type=email],
        input[type=number],
        input[type=date],
        input[type=datetime-local],
        input[type=password],
        select,
        textarea {
            width: 100%;
            padding: .65rem .75rem;
            border: 1px solid var(--line);
            border-radius: var(--radius);
            background: #fff;
            font: inherit;
            color: var(--ink);
            transition: border-color .15s ease, box-shadow .15s ease;
        }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #111;
            box-shadow: 0 0 0 3px rgba(232, 93, 4, .18);
        }
        .ts-wrapper { width: 100%; }
        .ts-wrapper.single .ts-control {
            padding: .55rem .75rem;
            border: 1px solid var(--line);
            border-radius: var(--radius);
            background: #fff;
            font: inherit;
            color: var(--ink);
            box-shadow: none;
            min-height: 2.65rem;
            cursor: text;
        }
        .ts-wrapper.single .ts-control input {
            font: inherit;
            color: var(--ink);
        }
        .ts-wrapper.focus .ts-control {
            border-color: #111;
            box-shadow: 0 0 0 3px rgba(232, 93, 4, .18);
        }
        .ts-dropdown {
            border: 1px solid var(--line);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            font: inherit;
            z-index: 40;
        }
        .ts-dropdown .option { padding: .55rem .75rem; }
        .ts-dropdown .active { background: #111; color: #fff; }
        .ts-dropdown .option:hover { background: rgba(232, 93, 4, .12); color: var(--ink); }
        .ts-dropdown .create,
        .ts-dropdown .no-results { padding: .55rem .75rem; color: var(--muted); }
        .ts-wrapper.disabled .ts-control { background: #f0efec; opacity: .85; cursor: not-allowed; }
        .ts-wrapper .clear-button { color: var(--muted); cursor: pointer; }
        .search .ts-wrapper { min-width: 180px; flex: 1; }
        textarea { min-height: 90px; resize: vertical; }
        .field { margin-bottom: 1rem; }
        .muted { color: var(--muted); }
        .badge {
            display: inline-block;
            padding: .18rem .55rem;
            border-radius: .35rem;
            font-size: .75rem;
            background: #e8e7e4;
            font-weight: 600;
        }
        .badge-ok { background: #d8f3dc; color: var(--ok); }
        .badge-warn { background: #ffe8cc; color: var(--warn); }
        .badge-off { background: #fde2e4; color: var(--danger); }
        .search { display: flex; gap: .5rem; }
        .search input { min-width: 240px; }
        .meta { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1rem; }
        .meta .card strong {
            display: block;
            font-size: 1.45rem;
            margin-top: .35rem;
            font-family: var(--font-display);
            letter-spacing: .03em;
        }
        .tree { display: flex; flex-direction: column; gap: 1rem; }
        .tree-node {
            border: 1px solid var(--line);
            border-radius: .75rem;
            background: #fff;
            overflow: hidden;
            box-shadow: var(--shadow);
        }
        .tree-product {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            align-items: flex-start;
            padding: 1rem 1.1rem;
            background: linear-gradient(90deg, #111 0%, #1a1a1a 100%);
            color: #fff;
            border-bottom: 1px solid #222;
        }
        .tree-product-main { min-width: 0; }
        .tree-product-code {
            font-size: .72rem;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: var(--signal);
            font-weight: 700;
        }
        .tree-product-name {
            display: block;
            margin-top: .15rem;
            font-size: 1.08rem;
            font-weight: 700;
            color: #fff;
            text-decoration: none;
            font-family: var(--font-display);
            letter-spacing: .04em;
            text-transform: uppercase;
        }
        .tree-product-name:hover { color: var(--signal); text-decoration: none; }
        .tree-product-meta {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
            margin-top: .55rem;
        }
        .tree-product .badge {
            background: rgba(255, 255, 255, .12);
            color: #f0f0f0;
        }
        .tree-product-actions {
            display: flex;
            gap: .5rem;
            flex-shrink: 0;
            align-items: center;
        }
        .tree-product-actions .btn-secondary {
            background: transparent;
            color: #fff;
            border-color: #444;
        }
        .tree-product-actions .btn-secondary:hover {
            background: #fff;
            color: #000;
            border-color: #fff;
        }
        .tree-children {
            list-style: none;
            margin: 0;
            padding: .35rem 0 .35rem 1.25rem;
            position: relative;
        }
        .tree-children::before {
            content: "";
            position: absolute;
            left: .7rem;
            top: .4rem;
            bottom: .7rem;
            width: 2px;
            background: #cfcfc8;
        }
        .tree-child {
            position: relative;
            padding: .7rem 1rem .7rem 1.2rem;
            margin: .15rem 0;
        }
        .tree-child::before {
            content: "";
            position: absolute;
            left: -.55rem;
            top: 1.15rem;
            width: 1.1rem;
            height: 2px;
            background: #cfcfc8;
        }
        .tree-child-card {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            align-items: center;
            padding: .7rem .9rem;
            border: 1px solid var(--line);
            border-radius: .55rem;
            background: #fff;
        }
        .tree-child-title { font-weight: 600; }
        .tree-empty {
            padding: .9rem 1rem 1.1rem 2.2rem;
            color: var(--muted);
            position: relative;
        }
        .tree-empty::before {
            content: "";
            position: absolute;
            left: .7rem;
            top: 1.2rem;
            width: 1.1rem;
            height: 2px;
            background: #d1d5db;
        }
        .tree-accordion { border-top: 1px solid var(--line); }
        .tree-accordion-toggle {
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            padding: .85rem 1.1rem;
            background: #fafaf8;
            border: 0;
            cursor: pointer;
            font: inherit;
            color: var(--ink);
            text-align: left;
        }
        .tree-accordion-toggle:hover { background: #f0efec; }
        .tree-accordion-summary {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
            align-items: center;
        }
        .tree-accordion-chevron {
            width: 1.6rem;
            height: 1.6rem;
            border-radius: .35rem;
            display: grid;
            place-items: center;
            background: #111;
            color: #fff;
            flex-shrink: 0;
            transition: transform .2s ease;
        }
        .tree-accordion-chevron::before {
            content: "▾";
            font-size: .85rem;
            line-height: 1;
        }
        .tree-accordion[open] .tree-accordion-chevron { transform: rotate(180deg); }
        .tree-accordion-panel { border-top: 1px dashed var(--line); }
        .sidebar-user {
            display: flex;
            flex-direction: column;
            gap: .15rem;
            padding: .65rem .8rem;
            margin-bottom: .6rem;
            border-radius: var(--radius);
            color: var(--sidebar-ink);
            text-decoration: none;
            background: rgba(255, 255, 255, .05);
        }
        .sidebar-user span { color: var(--sidebar-muted); font-size: .8rem; }
        .sidebar-user:hover, .sidebar-user.active { background: rgba(255, 255, 255, .1); }
        .section-tabs {
            display: flex;
            gap: .25rem;
            margin: 0 0 1.1rem;
            border-bottom: 1px solid var(--line);
            overflow-x: auto;
            scrollbar-width: none;
        }
        .section-tabs a {
            padding: .6rem .95rem;
            color: var(--muted);
            text-decoration: none;
            font-weight: 600;
            font-size: .92rem;
            white-space: nowrap;
            border-bottom: 2px solid transparent;
            margin-bottom: -1px;
        }
        .section-tabs a:hover { color: var(--ink); }
        .section-tabs a.active { color: var(--ink); border-bottom-color: var(--signal); }
        @keyframes brandIn {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: none; }
        }
        @keyframes mainIn {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: none; }
        }
        @keyframes flashIn {
            from { opacity: 0; transform: translateY(-4px); }
            to { opacity: 1; transform: none; }
        }
        @media (max-width: 900px) {
            .shell { grid-template-columns: 1fr; }
            .mobile-bar { display: flex; }
            .nav-backdrop[data-open="1"] { display: block; }
            .sidebar {
                position: fixed;
                inset: 0 auto 0 0;
                width: min(86vw, 300px);
                height: 100vh;
                height: 100dvh;
                padding: 1rem;
                transform: translateX(-105%);
                transition: transform .22s ease;
                box-shadow: 12px 0 40px rgba(0, 0, 0, .28);
            }
            body.nav-open .sidebar { transform: translateX(0); }
            body.nav-open { overflow: hidden; }
            .sidebar-close { display: grid; place-items: center; }
            .brand-logo { max-width: 120px; }
            .nav { flex-direction: column; flex-wrap: nowrap; gap: .2rem; }
            .nav-label { width: auto; margin: .85rem .8rem .35rem; }
            .nav a { padding: .7rem .85rem; font-size: .95rem; }
            .nav a:hover { transform: none; }
            .grid-2, .grid-3, .meta { grid-template-columns: 1fr; }
            .main { padding: 1rem .9rem 2rem; min-width: 0; }
            .topbar {
                flex-direction: column;
                align-items: stretch;
                gap: .85rem;
            }
            .topbar > div:first-child { min-width: 0; }
            .topbar .actions { width: 100%; }
            .topbar .actions .btn { flex: 1 1 auto; text-align: center; }
            .search {
                flex-direction: column;
                align-items: stretch;
            }
            .search input,
            .search .ts-wrapper,
            .search select,
            .search .btn {
                min-width: 0;
                width: 100%;
            }
            .card { overflow-x: auto; -webkit-overflow-scrolling: touch; }
            table { min-width: 560px; }
            .tree-product,
            .tree-child-card,
            .tree-product-actions {
                flex-direction: column;
                align-items: stretch;
            }
            .tree-product-actions .btn,
            .tree-child-card .btn {
                width: 100%;
                text-align: center;
            }
            .item-row.grid-3 {
                grid-template-columns: 1fr;
            }
            h1 { font-size: 1.55rem; }
            .pagination { flex-direction: column; align-items: stretch; }
            .pagination-links { justify-content: center; }
        }
        @media (max-width: 480px) {
            .main { padding: .85rem .75rem 1.75rem; }
            .card { padding: 1rem; }
            .btn { padding: .62rem .85rem; }
            table { min-width: 480px; }
        }
    </style>
</head>
<body>
<button class="nav-backdrop" type="button" data-nav-backdrop aria-label="Cerrar menú"></button>
<div class="mobile-bar">
    <button class="menu-toggle" type="button" data-nav-open aria-label="Abrir menú" aria-expanded="false">
        <span></span>
        <span></span>
        <span></span>
    </button>
    <a class="mobile-bar-brand" href="{{ \App\Support\Navigation::homeUrl(auth()->user()) }}">
        <img src="{{ asset('brand/rolo-logo.png') }}" alt="ROLO Accesorios" width="36" height="36">
        <span>ROLO Accesorios</span>
    </a>
</div>
<div class="shell">
    <aside class="sidebar" data-sidebar>
        <button class="sidebar-close" type="button" data-nav-close aria-label="Cerrar menú">&times;</button>
        <a class="brand-block" href="{{ \App\Support\Navigation::homeUrl(auth()->user()) }}">
            <img class="brand-logo" src="{{ asset('brand/rolo-logo.png') }}" alt="ROLO Accesorios" width="168" height="168">
            <p class="brand-tag">CRM · Inventario</p>
        </a>
        <nav class="nav">
            @foreach (\App\Support\Navigation::sectionsFor(auth()->user()) as $navSection)
                @if ($navSection['label'] !== '')
                    <div class="nav-label">{{ $navSection['label'] }}</div>
                @endif
                @foreach ($navSection['items'] as $navItem)
                    <a href="{{ route($navItem['tabs'][0]['route']) }}" class="{{ \App\Support\Navigation::itemIsActive(request(), $navItem) ? 'active' : '' }}">{{ $navItem['label'] }}</a>
                @endforeach
            @endforeach
        </nav>
        <div class="sidebar-footer">
            @auth
                <a href="{{ route('account.edit') }}" class="sidebar-user {{ request()->routeIs('account.*') ? 'active' : '' }}">
                    <strong>{{ auth()->user()->name }}</strong>
                    <span>{{ auth()->user()->role?->name ?? 'Sin rol' }} · Mi cuenta</span>
                </a>
            @endauth
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit">Cerrar sesión</button>
            </form>
        </div>
    </aside>
    <main class="main">
        @php $sectionTabs = \App\Support\Navigation::activeTabs(request()); @endphp
        @if ($sectionTabs)
            <nav class="section-tabs" aria-label="Secciones">
                @foreach ($sectionTabs as $tab)
                    <a href="{{ route($tab['route']) }}" class="{{ $tab['is_active'] ? 'active' : '' }}">{{ $tab['label'] }}</a>
                @endforeach
            </nav>
        @endif

        @if (session('success'))
            <div class="flash">{{ session('success') }}</div>
        @endif

        @if (session('error'))
            <div class="errors">{{ session('error') }}</div>
        @endif

        @if ($errors->any())
            <div class="errors">
                <strong>Revisa el formulario:</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>
</div>
<script>
(() => {
    const openBtn = document.querySelector('[data-nav-open]');
    const closeBtn = document.querySelector('[data-nav-close]');
    const backdrop = document.querySelector('[data-nav-backdrop]');
    const setOpen = (open) => {
        document.body.classList.toggle('nav-open', open);
        if (backdrop) backdrop.dataset.open = open ? '1' : '0';
        if (openBtn) openBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    };
    openBtn?.addEventListener('click', () => setOpen(true));
    closeBtn?.addEventListener('click', () => setOpen(false));
    backdrop?.addEventListener('click', () => setOpen(false));
    document.querySelectorAll('[data-sidebar] .nav a').forEach((link) => {
        link.addEventListener('click', () => setOpen(false));
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') setOpen(false);
    });
})();
document.addEventListener('click', (e) => {
    document.querySelectorAll('details.action-menu[open]').forEach((menu) => {
        if (!menu.contains(e.target)) menu.removeAttribute('open');
    });
});
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') document.querySelectorAll('details.action-menu[open]').forEach((menu) => menu.removeAttribute('open'));
});
</script>
</body>
</html>
