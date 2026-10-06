<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ingresar — {{ config('app.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('brand/rolo-logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #111;
            --muted: #8a8a8a;
            --line: #2a2a2a;
            --signal: #e85d04;
            --danger: #ff6b6b;
            --font-display: "Oswald", Impact, sans-serif;
            --font-body: "Space Grotesk", "Segoe UI", sans-serif;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: var(--font-body);
            color: #f5f5f5;
            background:
                radial-gradient(ellipse 70% 55% at 50% -5%, rgba(232, 93, 4, .18), transparent 55%),
                repeating-linear-gradient(
                    -45deg,
                    transparent,
                    transparent 14px,
                    rgba(255, 255, 255, .02) 14px,
                    rgba(255, 255, 255, .02) 15px
                ),
                #050505;
            display: grid;
            place-items: center;
            padding: 1.5rem;
        }
        .login {
            width: 100%;
            max-width: 420px;
            text-align: center;
            animation: rise .55s ease both;
        }
        .logo-wrap {
            margin: 0 auto 1.75rem;
            max-width: 240px;
        }
        .logo-wrap img {
            width: 100%;
            height: auto;
            display: block;
            border-radius: .5rem;
            box-shadow: 0 24px 60px rgba(0, 0, 0, .45);
        }
        .panel {
            background: rgba(18, 18, 18, .92);
            border: 1px solid var(--line);
            border-radius: .85rem;
            padding: 1.65rem 1.4rem 1.5rem;
            text-align: left;
            backdrop-filter: blur(8px);
        }
        h1 {
            margin: 0 0 .35rem;
            font-family: var(--font-display);
            font-size: 1.55rem;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .lede {
            margin: 0 0 1.35rem;
            color: var(--muted);
            font-size: .95rem;
        }
        label {
            display: block;
            margin-bottom: .45rem;
            font-size: .82rem;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: #bdbdbd;
            font-weight: 600;
        }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: .85rem 1rem;
            border: 1px solid #333;
            border-radius: .65rem;
            font-size: 1.05rem;
            font-family: inherit;
            background: #0c0c0c;
            color: #fff;
            margin-bottom: 1rem;
        }
        .remember { display: flex; align-items: center; gap: .5rem; font-size: .9rem; color: #bdbdbd; letter-spacing: 0; text-transform: none; font-weight: 500; }
        input:focus {
            outline: none;
            border-color: var(--signal);
            box-shadow: 0 0 0 3px rgba(232, 93, 4, .25);
        }
        .error {
            color: var(--danger);
            font-size: .9rem;
            margin: .75rem 0 0;
        }
        button {
            width: 100%;
            margin-top: 1.25rem;
            border: 0;
            border-radius: .65rem;
            padding: .95rem 1rem;
            background: #fff;
            color: #000;
            font-size: 1rem;
            font-weight: 700;
            font-family: var(--font-display);
            letter-spacing: .14em;
            text-transform: uppercase;
            cursor: pointer;
            transition: background .18s ease, color .18s ease, transform .15s ease;
        }
        button:hover {
            background: var(--signal);
            color: #fff;
            transform: translateY(-1px);
        }
        .foot {
            margin-top: 1.15rem;
            font-size: .75rem;
            letter-spacing: .2em;
            text-transform: uppercase;
            color: #666;
        }
        @keyframes rise {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: none; }
        }
    </style>
</head>
<body>
    <div class="login">
        <div class="logo-wrap">
            <img src="{{ asset('brand/rolo-logo.png') }}" alt="ROLO Accesorios" width="240" height="240">
        </div>
        <div class="panel">
            <h1>Acceso</h1>
            <p class="lede">Ingresa con tu usuario y contraseña.</p>

            <form method="POST" action="{{ route('login.store') }}">
                @csrf
                <label for="username">Usuario</label>
                <input id="username" type="text" name="username" value="{{ old('username') }}" autocomplete="username" autocapitalize="none" autofocus required>

                <label for="password">Contraseña</label>
                <input id="password" type="password" name="password" autocomplete="current-password" required>

                <label class="remember">
                    <input type="checkbox" name="remember" value="1" @checked(old('remember'))> Mantener sesión iniciada
                </label>

                @error('username')
                    <p class="error">{{ $message }}</p>
                @enderror

                <button type="submit">Entrar</button>
            </form>
        </div>
        <p class="foot">ROLO Accesorios</p>
    </div>
</body>
</html>
