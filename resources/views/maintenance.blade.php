<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Mantenimiento · {{ config('app.name') }}</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 860px; margin: 2rem auto; padding: 0 1rem; color: #1f2937; }
        h1 { font-size: 1.5rem; }
        .actions { display: flex; gap: .75rem; flex-wrap: wrap; margin: 1.5rem 0; }
        button { padding: .6rem 1rem; border: 0; border-radius: .4rem; background: #b91c1c; color: #fff; font-size: 1rem; cursor: pointer; }
        button.secondary { background: #374151; }
        .success { padding: .75rem 1rem; background: #dcfce7; color: #166534; border-radius: .4rem; }
        .error { padding: .75rem 1rem; background: #fee2e2; color: #991b1b; border-radius: .4rem; }
        label { display: block; font-weight: 600; margin-bottom: .35rem; }
        input[type=password] { padding: .55rem .75rem; border: 1px solid #d1d5db; border-radius: .4rem; font-size: 1rem; width: min(100%, 20rem); }
        pre { background: #111827; color: #e5e7eb; padding: 1rem; border-radius: .4rem; overflow-x: auto; font-size: .85rem; }
        a { color: #1d4ed8; }
    </style>
</head>
<body>
    <h1>Mantenimiento del sistema</h1>
    <p>Página sin React para ejecutar migraciones y limpiar caché aunque el panel esté roto.</p>

    @if (session('success'))
        <p class="success">{{ session('success') }}</p>
    @endif

    @if ($errors->any())
        <p class="error">{{ $errors->first() }}</p>
    @endif

    <form method="POST" id="maintenance-form" action="{{ route('admin.maintenance.migrate') }}">
        @csrf
        <label for="password">Tu contraseña (se pide en cada acción)</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>

        <div class="actions">
            <button type="submit" onclick="return confirm('¿Ejecutar las migraciones pendientes?')">Ejecutar migraciones</button>
            <button type="submit" class="secondary" formaction="{{ route('admin.maintenance.cache-clear') }}">Limpiar caché</button>
        </div>
    </form>

    @if (session('maintenance_output'))
        <h2>Resultado</h2>
        <pre>{{ session('maintenance_output') }}</pre>
    @endif

    <h2>Estado de migraciones</h2>
    <pre>{{ $migrationStatus }}</pre>

    <p><a href="{{ route('dashboard') }}">Volver al panel</a></p>
</body>
</html>
