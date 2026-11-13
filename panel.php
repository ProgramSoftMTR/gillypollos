<?php
session_start();
if (empty($_SESSION['user'])) {
    header('Location: index.html');
    exit;
}
$user = $_SESSION['user'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sesión iniciada - Gil & Pollos</title>
  <style>
    * { box-sizing: border-box; }
    body { margin: 0; min-height: 100vh; background: #0B0B0C; color: #fff; font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; display: flex; align-items: center; justify-content: center; padding: 20px; }
    .card { background: #18181B; border: 1px solid #2E2E32; border-radius: 18px; padding: 32px 28px; max-width: 420px; width: 100%; text-align: center; box-shadow: 0 25px 60px rgba(0,0,0,0.6); }
    .avatar { width: 96px; height: 96px; border-radius: 50%; object-fit: cover; border: 3px solid #F5B82E; margin-bottom: 16px; }
    h1 { font-size: 20px; margin: 0 0 6px; }
    .sub { color: #a1a1aa; font-size: 13px; margin: 0 0 20px; }
    .info { text-align: left; background: #111; border: 1px solid #2E2E32; border-radius: 12px; padding: 14px 16px; font-size: 13px; line-height: 1.6; margin-bottom: 20px; }
    .info div { margin: 4px 0; }
    .label { color: #a1a1aa; }
    .btn { display: inline-block; width: 100%; padding: 11px 14px; border-radius: 10px; font-size: 14px; font-weight: 700; text-decoration: none; margin-top: 8px; cursor: pointer; border: none; }
    .btn-primary { background: #F5B82E; color: #111; }
    .btn-secondary { background: transparent; border: 1px solid #2E2E32; color: #a1a1aa; }
  </style>
</head>
<body>
  <div class="card">
    <img class="avatar" src="<?php echo htmlspecialchars($user['foto'] ?? ''); ?>" alt="Foto de perfil" onerror="this.style.display='none';">
    <h1>Sesión iniciada correctamente con Google</h1>
    <p class="sub">Bienvenido/a a Gil &amp; Pollos</p>
    <div class="info">
      <div><span class="label">Nombre:</span> <?php echo htmlspecialchars($user['nombre'] ?? ''); ?></div>
      <div><span class="label">Correo:</span> <?php echo htmlspecialchars($user['email'] ?? ''); ?></div>
      <div><span class="label">Teléfono:</span> <?php echo htmlspecialchars($user['telefono'] ?? ''); ?></div>
      <div><span class="label">Dirección:</span> <?php echo htmlspecialchars($user['direccion'] ?? ''); ?></div>
    </div>
    <a class="btn btn-primary" href="index.html">Ir a la tienda</a>
    <a class="btn btn-secondary" href="api/logout.php">Cerrar sesión</a>
  </div>
</body>
</html>
