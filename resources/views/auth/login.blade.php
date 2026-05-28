<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgroLixiSync — Acceso</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        body {
            margin: 0;
            min-height: 100vh;
            background: #0d1117;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }

        .login-card {
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 14px;
            padding: 2.5rem 2.25rem;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 8px 40px rgba(0,0,0,0.5);
        }

        .logo-block {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo-icon {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, #16a34a, #0f4c2a);
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            margin-bottom: 0.75rem;
        }

        .logo-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: #e6edf3;
        }

        .logo-sub {
            font-size: 0.78rem;
            color: #8b949e;
            margin-top: 0.2rem;
        }

        .login-heading {
            margin-top: 1.2rem;
            font-size: 0.95rem;
            font-weight: 700;
            color: #e6edf3;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: #8b949e;
            margin-bottom: 0.4rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        input[type=email],
        input[type=password],
        input[type=text] {
            width: 100%;
            background: #0d1117;
            border: 1px solid #30363d;
            border-radius: 8px;
            padding: 0.65rem 0.85rem;
            color: #e6edf3;
            font-size: 0.9rem;
            outline: none;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }

        input:focus {
            border-color: #22c55e;
        }

        .field {
            margin-bottom: 1.25rem;
        }

        .btn-login {
            width: 100%;
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 0.75rem;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            margin-top: 0.5rem;
            transition: opacity 0.2s;
        }

        .btn-login:hover {
            opacity: 0.9;
        }

        .error-box {
            background: rgba(220,38,38,0.1);
            border: 1px solid #dc2626;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            color: #fca5a5;
            font-size: 0.82rem;
            margin-bottom: 1.25rem;
        }

        .remember-row {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.25rem;
        }

        .remember-row input {
            width: auto;
        }

        .remember-row label {
            margin: 0;
            font-size: 0.82rem;
            color: #8b949e;
            text-transform: none;
            letter-spacing: 0;
        }

        .footer-note {
            text-align: center;
            font-size: 0.75rem;
            color: #484f58;
            margin-top: 1.5rem;
        }

        /* PASSWORD */

        .password-wrapper {
            position: relative;
        }

        .password-wrapper input {
            padding-right: 45px;
        }

        .password-wrapper button {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #8b949e;
            cursor: pointer;
            font-size: 1rem;
        }

        .password-wrapper button:hover {
            color: #e6edf3;
        }
    </style>
</head>

<body>

    <div class="login-card">

        <div class="logo-block">
            <div class="logo-icon">🌱</div>

            <div class="logo-title">
                AgroLixiSync
            </div>

            <div class="logo-sub">
                Sistema de Monitoreo de Lixiviación IoT
            </div>

            <div class="login-heading">
                INICIAR SESIÓN
            </div>
        </div>

        @if ($errors->any())
            <div class="error-box">
                @foreach ($errors->all() as $error)
                    <div>⚠ {{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="field">
                <label>Correo electrónico</label>

                <input
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    required
                    autofocus
                    placeholder="usuario@agrolixi.com"
                >
            </div>

            <div class="field">
                <label>Contraseña</label>

                <div class="password-wrapper">

                    <input
                        type="password"
                        name="password"
                        id="password"
                        required
                        placeholder="••••••••"
                    >

                    <button type="button" id="togglePassword">
                        👁
                    </button>

                </div>
            </div>

            <div class="remember-row">
                <input type="checkbox" name="remember" id="remember">

                <label for="remember">
                    Mantener sesión iniciada
                </label>
            </div>

            <button type="submit" class="btn-login">
                Ingresar al sistema
            </button>

        </form>

        <div class="footer-note">
            Acceso restringido — Solo personal autorizado
        </div>

    </div>

    <script>
        const togglePassword = document.getElementById('togglePassword');
        const password = document.getElementById('password');

        togglePassword.addEventListener('click', () => {

            const type = password.getAttribute('type') === 'password'
                ? 'text'
                : 'password';

            password.setAttribute('type', type);

            togglePassword.textContent = type === 'password'
                ? '👁'
                : '🙈';
        });
    </script>

</body>
</html>