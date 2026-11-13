<?php
session_start();
if (empty($_SESSION['es_admin'])) { header('Location: index.html'); exit; }

// Acciones admin
require_once __DIR__ . '/api/ensure_columns.php';
$dir = __DIR__ . '/data';
$file = $dir . '/perfil_usuario.json';
if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
$conn = @new mysqli('localhost', 'root', '', 'gilpollos_db');
if (!$conn->connect_error) {
    $conn->set_charset('utf8mb4');
    gp_ensure_columns($conn);
    gp_migrate_json_to_db($conn);
    $conn->close();
}
function loadAll() { global $file; if (!file_exists($file)) return []; $c=file_get_contents($file); $j=json_decode($c,true); return is_array($j)?$j:[]; }
function saveAll($all){ global $file; file_put_contents($file, json_encode($all, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $d = json_decode($raw, true) ?: [];
    $email = $d['email'] ?? '';
    $accion = $d['accion'] ?? '';
    $all = loadAll();
    if ($email && isset($all[$email])) {
        if ($accion === 'aprobar') {
            $all[$email]['estado'] = 'aprobado';
            $all[$email]['motivo'] = '';
        } elseif ($accion === 'rechazar') {
            $all[$email]['estado'] = 'rechazado';
            $all[$email]['motivo'] = $d['motivo'] ?? 'Foto borrosa o no coincide con la cédula';
        }
        saveAll($all);
        echo json_encode(['ok'=>true]);
    } else {
        echo json_encode(['ok'=>false]);
    }
    exit;
}

if (isset($_GET['logout'])) { unset($_SESSION['es_admin']); header('Location: index.html'); exit; }

$all = loadAll();
require_once __DIR__ . '/api/ensure_columns.php';
$conn = @new mysqli('localhost', 'root', '', 'gilpollos_db');
if (!$conn->connect_error) {
    $conn->set_charset('utf8mb4');
    gp_ensure_columns($conn);
    gp_migrate_json_to_db($conn);
    $res = $conn->query("SELECT * FROM users WHERE COALESCE(role,'customer') <> 'admin' ORDER BY id DESC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $em = $row['email'];
            if (!$em) continue;
            $p = $all[$em] ?? [];
            $p['nombre'] = $row['name'] ?? ($p['nombre'] ?? '');
            $p['email'] = $em;
            $p['cedula'] = $row['cedula'] ?: ($p['cedula'] ?? '');
            $p['fotoDoc'] = $row['foto_documento'] ?: ($p['fotoDoc'] ?? '');
            $p['telefono'] = $row['telefono'] ?: ($p['telefono'] ?? '');
            $p['tel2'] = $row['telefono_2'] ?: ($p['tel2'] ?? '');
            $p['ubicacion1'] = $row['direccion_1'] ?: ($p['ubicacion1'] ?? '');
            $p['ubicacion1_coords'] = ($row['lat_1'] && $row['lng_1']) ? $row['lat_1'].','.$row['lng_1'] : ($p['ubicacion1_coords'] ?? '');
            $p['ubicacion2'] = $row['direccion_2'] ?: ($p['ubicacion2'] ?? '');
            $p['ubicacion2_coords'] = ($row['lat_2'] && $row['lng_2']) ? $row['lat_2'].','.$row['lng_2'] : ($p['ubicacion2_coords'] ?? '');
            $p['estado'] = $row['estado_verificacion'] ?: ($p['estado'] ?? 'sin_datos');
            $p['motivo'] = $row['motivo_rechazo'] ?: ($p['motivo'] ?? '');
            $all[$em] = $p;
        }
    }
    $conn->close();
}
$filtro = $_GET['filtro'] ?? 'pendientes';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin - Gil & Pollos</title>
<style>
  body { margin:0; font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif; background:#0a0a0a; color:#e5e7eb; }
  .topbar { display:flex; justify-content:space-between; align-items:center; padding:16px 24px; background:#18181b; border-bottom:1px solid rgba(255,255,255,0.1); }
  .topbar h1 { margin:0; font-size:20px; font-weight:800; color:#F5B82E; }
  .topbar a { color:#fff; text-decoration:none; padding:8px 14px; border-radius:8px; border:1px solid rgba(255,255,255,0.15); font-size:13px; font-weight:600; }
  .topbar a:hover { background:rgba(255,255,255,0.08); }
  .wrap { max-width:1200px; margin:0 auto; padding:24px; }
  .tabs { display:flex; gap:8px; margin-bottom:20px; flex-wrap:wrap; }
  .tab { padding:8px 16px; border-radius:999px; border:1px solid rgba(255,255,255,0.15); background:transparent; color:#e5e7eb; cursor:pointer; font-size:13px; font-weight:700; text-decoration:none; }
  .tab.active { background:#F5B82E; color:#111; border-color:#F5B82E; }
  .card { background:#18181b; border:1px solid rgba(255,255,255,0.1); border-radius:14px; padding:20px; margin-bottom:16px; }
  .grid { display:grid; grid-template-columns: 1fr 1fr; gap:20px; }
  @media (max-width:768px){ .grid { grid-template-columns:1fr; } }
  .row { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:8px; font-size:13px; }
  .label { color:#9ca3af; font-weight:600; }
  .badge { display:inline-block; padding:4px 10px; border-radius:999px; font-size:11px; font-weight:700; }
  .badge.en_revision { background:rgba(250,204,21,0.15); color:#facc15; }
  .badge.aprobado { background:rgba(74,222,128,0.15); color:#4ade80; }
  .badge.rechazado { background:rgba(248,113,113,0.15); color:#f87171; }
  .badge.sin_datos { background:rgba(156,163,175,0.15); color:#9ca3af; }
  .foto { max-width:100%; border-radius:10px; border:1px solid rgba(255,255,255,0.1); cursor:zoom-in; }
  .foto:hover { transform:scale(1.02); }
  .modal-img { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.9); z-index:999999; align-items:center; justify-content:center; padding:20px; }
  .modal-img img { max-width:90%; max-height:80%; border-radius:10px; }
  .modal-img .close-btn { position:absolute; top:20px; right:24px; background:#F5B82E; color:#111; border:none; border-radius:8px; padding:8px 14px; font-size:13px; font-weight:700; cursor:pointer; }
  .btn { padding:6px 10px; border-radius:8px; border:none; cursor:pointer; font-size:12px; font-weight:700; }
  .btn-aprobar { background:#22c55e; color:#fff; }
  .btn-rechazar { background:#ef4444; color:#fff; }
  .btn-foto { background:#374151; color:#fff; }
  .btn:disabled { opacity:0.5; cursor:not-allowed; }
  .empty { color:#9ca3af; padding:40px; text-align:center; }

  /* Compact user rows */
  .users-table { display:flex; flex-direction:column; gap:8px; }
  .user-row { display:grid; grid-template-columns: 1.5fr 0.8fr 1fr 1.8fr 95px auto; align-items:center; gap:12px; background:#18181b; border:1px solid rgba(255,255,255,0.1); border-radius:10px; padding:10px 12px; font-size:12px; width:100%; box-sizing:border-box; }
  .user-cell { min-width:0; }
  .user-name, .user-email { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .user-row > .user-cell:nth-child(4) { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .maps-link { flex-shrink:0; white-space:nowrap; }
  .user-actions { display:flex; gap:6px; white-space:nowrap; }
  @media (min-width: 1025px) { .user-row { flex-wrap:nowrap; } }
  .user-cell { min-width:0; }
  .user-name { font-weight:700; font-size:13px; color:#fff; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .user-email { color:#9ca3af; font-size:11px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .user-actions { display:flex; gap:6px; flex-wrap:wrap; }
  .maps-link { color:#F5B82E; text-decoration:none; font-size:11px; font-weight:600; }
  .maps-link:hover { text-decoration:underline; }
  .nofoto { color:#9ca3af; font-size:11px; }
  @media (max-width:900px){ .user-row { grid-template-columns: 1fr 1fr; } }
</style>
</head>
<body>
  <div class="topbar">
    <h1>Gil & Pollos · Admin</h1>
    <div style="display:flex; gap:10px;">
      <a href="index.html">Volver a la tienda</a>
      <a href="admin.php?logout=1">Cerrar sesión admin</a>
    </div>
  </div>
  <div class="wrap">
    <div class="tabs">
      <a class="tab <?php echo $filtro==='pedidos'?'active':''; ?>" href="admin.php?filtro=pedidos">Pedidos</a>
      <a class="tab <?php echo $filtro==='pendientes'?'active':''; ?>" href="admin.php?filtro=pendientes">Pendientes de revisión</a>
      <a class="tab <?php echo $filtro==='aprobados'?'active':''; ?>" href="admin.php?filtro=aprobados">Aprobados</a>
      <a class="tab <?php echo $filtro==='rechazados'?'active':''; ?>" href="admin.php?filtro=rechazados">Rechazados</a>
      <a class="tab <?php echo $filtro==='todos'?'active':''; ?>" href="admin.php?filtro=todos">Todos</a>
    </div>
    <?php
    $lista = [];
    foreach ($all as $email => $p) {
        $estado = $p['estado'] ?? 'sin_datos';
        if ($filtro === 'pendientes' && $estado !== 'en_revision') continue;
        if ($filtro === 'aprobados' && $estado !== 'aprobado') continue;
        if ($filtro === 'rechazados' && $estado !== 'rechazado') continue;
        $lista[$email] = $p;
    }
    if ($filtro === 'pedidos') {
        // cargar pedidos
        $conn = @new mysqli('localhost', 'root', '', 'gilpollos_db');
        $pedidos = [];
        if (!$conn->connect_error) {
            $conn->set_charset('utf8mb4');
            $res = $conn->query('SELECT * FROM orders ORDER BY id DESC');
            if ($res) { while ($r = $res->fetch_assoc()) { $pedidos[] = $r; } }
            $conn->close();
        }
        if (empty($pedidos)) {
            echo '<div class="empty">No hay pedidos registrados.</div>';
        } else {
            foreach ($pedidos as $p) {
                $oid = (int)$p['id'];
                $detalles = [];
                $conn2 = @new mysqli('localhost', 'root', '', 'gilpollos_db');
                if (!$conn2->connect_error) {
                    $conn2->set_charset('utf8mb4');
                    $r2 = $conn2->query('SELECT * FROM order_details WHERE order_id=' . $oid);
                    if ($r2) { while ($d = $r2->fetch_assoc()) $detalles[] = $d; }
                    $conn2->close();
                }
                echo '<div class="card">';
                echo '<div class="row"><span class="label">Pedido #:</span> ' . $oid . ' &nbsp; <span class="label">Estado:</span> <b>' . htmlspecialchars($p['estado']) . '</b></div>';
                echo '<div class="row"><span class="label">Cliente:</span> ' . htmlspecialchars($p['cliente_nombre']) . '</div>';
                echo '<div class="row"><span class="label">Cédula:</span> ' . htmlspecialchars($p['cedula']) . '</div>';
                echo '<div class="row"><span class="label">Teléfono:</span> ' . htmlspecialchars($p['telefono']) . '</div>';
                echo '<div class="row"><span class="label">Dirección:</span> ' . htmlspecialchars($p['direccion']) . ' ' . ($p['lat'] ? '<a href="https://maps.google.com/?q='.urlencode($p['lat'].','.$p['lng']).'" target="_blank" style="color:#F5B82E;">Ver ubicación de entrega en Maps</a>' : '') . '</div>';
                echo '<div class="row"><span class="label">Total:</span> <b style="color:#F5B82E;">$ ' . number_format($p['total'],0,',','.') . '</b> &nbsp; <span class="label">Método:</span> ' . htmlspecialchars($p['metodo_pago']) . '</div>';
                echo '<div class="row"><span class="label">Productos:</span></div>';
                echo '<ul style="margin:4px 0 12px 20px; font-size:13px;">';
                foreach ($detalles as $d) {
                    echo '<li>' . htmlspecialchars($d['producto']) . ' x' . (int)$d['cantidad'] . ' — $ ' . number_format($d['precio'],0,',','.') . '</li>';
                }
                echo '</ul>';
                echo '<div style="display:flex; gap:8px; flex-wrap:wrap;">';
                foreach (['pendiente'=>'Preparando','en_camino'=>'En camino','entregado'=>'Entregado'] as $est=>$lbl) {
                    $act = ($p['estado']===$est) ? ' style="opacity:0.5;"' : '';
                    echo '<button class="btn" style="background:#374151;color:#fff;" onclick="cambiarEstadoPedido('.$oid.',\''.$est.'\')"'.$act.'>'.$lbl.'</button>';
                }
                echo '</div>';
                echo '</div>';
            }
        }
    } else {
        if (empty($lista)) {
            echo '<div class="empty">No hay usuarios en esta categoría.</div>';
        } else {
            foreach ($lista as $email => $p) {
                $estado = $p['estado'] ?? 'sin_datos';
                $nombre = htmlspecialchars($p['nombre'] ?? 'Sin nombre');
                $correo = htmlspecialchars($email);
                $cedula = htmlspecialchars($p['cedula'] ?? '—');
                $tel1 = htmlspecialchars($p['telefono'] ?? '—');
                $tel2 = htmlspecialchars($p['tel2'] ?? '—');
                $loc1 = htmlspecialchars($p['ubicacion1'] ?? '—');
                $loc1c = $p['ubicacion1_coords'] ?? '';
                $loc2 = htmlspecialchars($p['ubicacion2'] ?? '—');
                $loc2c = $p['ubicacion2_coords'] ?? '';
                $foto = $p['fotoDoc'] ?? '';
                $motivo = htmlspecialchars($p['motivo'] ?? '');
                // build maps link 1
                $mapUrl1 = '';
                if ($loc1c && trim($loc1c) !== '' && $loc1c !== '0,0' && $loc1c !== '0.0000000,0.0000000') {
                    $mapUrl1 = 'https://www.google.com/maps?q=' . urlencode(trim($loc1c));
                } else {
                    if (preg_match('/(-?\d+\.\d+)\s*,\s*(-?\d+\.\d+)/', $loc1, $m)) {
                        $mapUrl1 = 'https://www.google.com/maps?q=' . urlencode($m[1].','.$m[2]);
                    } elseif (trim($loc1) !== '' && $loc1 !== '—') {
                        $mapUrl1 = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($loc1);
                    }
                }
                // build maps link 2
                $mapUrl2 = '';
                if ($loc2 && $loc2 !== '—') {
                    if ($loc2c && trim($loc2c) !== '' && $loc2c !== '0,0' && $loc2c !== '0.0000000,0.0000000') {
                        $mapUrl2 = 'https://www.google.com/maps?q=' . urlencode(trim($loc2c));
                    } else {
                        if (preg_match('/(-?\d+\.\d+)\s*,\s*(-?\d+\.\d+)/', $loc2, $m2)) {
                            $mapUrl2 = 'https://www.google.com/maps?q=' . urlencode($m2[1].','.$m2[2]);
                        } elseif (trim($loc2) !== '' && $loc2 !== '—') {
                            $mapUrl2 = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($loc2);
                        }
                    }
                }
                echo '<div class="user-row">';
                echo '<div class="user-cell"><div class="user-name">' . $nombre . '</div><div class="user-email">' . $correo . '</div></div>';
                echo '<div class="user-cell">' . $cedula . '</div>';
                echo '<div class="user-cell">Tel1: ' . $tel1 . '<br>Tel2: ' . $tel2 . '</div>';
                echo '<div class="user-cell">' . $loc1 . ($mapUrl1 ? ' <a class="maps-link" href="'.$mapUrl1.'" target="_blank">Ver en Google Maps</a>' : '') . ($loc2 && $loc2 !== '—' ? '<br>' . $loc2 . ($mapUrl2 ? ' <a class="maps-link" href="'.$mapUrl2.'" target="_blank">Ver en Google Maps</a>' : '') : '') . '</div>';
                echo '<div class="user-cell"><span class="badge ' . $estado . '">' . $estado . '</span>' . ($motivo ? '<div style="font-size:10px;color:#f87171;margin-top:2px;">'.$motivo.'</div>' : '') . '</div>';
                echo '<div class="user-cell user-actions">';
                echo '<button class="btn btn-aprobar" onclick="aprobar(\'' . htmlspecialchars($email, ENT_QUOTES) . '\')">✓ Aprobar</button>';
                echo '<button class="btn btn-rechazar" onclick="rechazar(\'' . htmlspecialchars($email, ENT_QUOTES) . '\')">✕ Rechazar</button>';
                if ($foto) {
                    echo '<button class="btn btn-foto" onclick="ampliarFoto(\'' . htmlspecialchars($foto, ENT_QUOTES) . '\')">👁 Ver foto</button>';
                } else {
                    echo '<button class="btn btn-foto" disabled>Sin foto</button>';
                }
                echo '</div>';
                echo '</div>';
            }
        }
    }
?>
  </div>
  <div class="modal-img" id="modal-img" onclick="if(event.target===this) this.style.display='none'">
    <button class="close-btn" type="button" onclick="document.getElementById('modal-img').style.display='none'">✕ Cerrar</button>
    <img id="modal-img-src" src="" alt="ampliada">
  </div>
  <script>
    function ampliarFoto(src){ var m=document.getElementById('modal-img'); var i=document.getElementById('modal-img-src'); if(i) i.src=src; if(m) m.style.display='flex'; }
    document.addEventListener('keydown', function(e){ if(e.key==='Escape'){ var m=document.getElementById('modal-img'); if(m) m.style.display='none'; } });
    function aprobar(email){
      if(!confirm('¿Aprobar esta cuenta?')) return;
      fetch('api/perfil_usuario_admin.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({email:email, accion:'aprobar'})})
        .then(function(r){ return r.json(); }).then(function(d){ if(d&&d.ok) location.reload(); else alert('Error'); });
    }
    function rechazar(email){
      var motivo = prompt('Motivo del rechazo (ej. Foto borrosa o no coincide con la cédula):');
      if (motivo === null) return;
      fetch('api/perfil_usuario_admin.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({email:email, accion:'rechazar', motivo:motivo})})
        .then(function(r){ return r.json(); }).then(function(d){ if(d&&d.ok) location.reload(); else alert('Error'); });
    }
    function cambiarEstadoPedido(id, estado){
      fetch('api/pedido_estado.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({id:id, estado:estado})})
        .then(function(r){ return r.json(); }).then(function(d){ if(d&&d.ok) location.reload(); else alert('Error'); });
    }
  </script>
</body>
</html>