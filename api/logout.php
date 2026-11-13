<?php
session_start();
$_SESSION = [];
session_destroy();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Cerrando sesión...</title>
</head>
<body>
  <script>
    try { localStorage.removeItem('user'); localStorage.removeItem('usuario'); } catch(e){}
    window.location.href = '../index.html';
  </script>
</body>
</html>
