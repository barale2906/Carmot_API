<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Importar demo</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 720px; margin: 40px auto; padding: 0 16px; color: #1f2937; }
        .aviso { background: #fef3c7; border: 1px solid #f59e0b; padding: 12px; border-radius: 6px; }
        .ok { background: #dcfce7; border: 1px solid #16a34a; padding: 12px; border-radius: 6px; }
        .error { background: #fee2e2; border: 1px solid #dc2626; padding: 12px; border-radius: 6px; }
        pre { background: #111827; color: #e5e7eb; padding: 12px; border-radius: 6px; overflow-x: auto; font-size: 13px; }
        button { margin-top: 12px; padding: 8px 16px; cursor: pointer; }
    </style>
</head>
<body>
    <h1>Importar demo.sql</h1>

    <p class="aviso">
        Solo funciona si la base de datos está vacía (sin registros; puede estar migrada).
        Tras importar se ejecutan las migraciones pendientes.
    </p>

    @isset($mensaje)
        <p class="{{ $exito ? 'ok' : 'error' }}">{{ $mensaje }}</p>
        <pre>{{ $salida }}</pre>
    @endisset

    @if ($errors->any())
        <p class="error">{{ $errors->first() }}</p>
    @endif

    <form method="POST" action="{{ route('demo.importar.store', $token) }}" enctype="multipart/form-data"
          onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').textContent = 'Importando...';">
        <input type="file" name="archivo" accept=".sql" required>
        <br>
        <button type="submit">Importar</button>
    </form>
</body>
</html>
