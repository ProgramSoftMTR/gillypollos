<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

$dir = __DIR__ . '/../data';
if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
$file = $dir . '/perfil_usuario.json';

function loadAll() {
    global $file;
    if (!file_exists($file)) return [];
    $c = file_get_contents($file);
    $j = json_decode($c, true);
    return is_array($j) ? $j : [];
}
function saveAll($all) {
    global $file;
    file_put_contents($file, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

require_once __DIR__ . '/ensure_columns.php';
// Conexión MySQL opcional
$conn = @new mysqli('localhost', 'root', '', 'gilpollos_db');
$hasDb = !$conn->connect_error;
if ($hasDb) {
    $conn->set_charset('utf8mb4');
    gp_ensure_columns($conn);
    gp_migrate_json_to_db($conn);
}

$email = '';
$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?: [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $data['email'] ?? '';
    if (!$email && isset($_SESSION['usuario']) && is_array($_SESSION['usuario'])) {
        $email = $_SESSION['usuario']['email'] ?? $_SESSION['usuario']['correo'] ?? '';
    }
} else {
    $email = $_GET['email'] ?? '';
    if (!$email && isset($_SESSION['usuario']) && is_array($_SESSION['usuario'])) {
        $email = $_SESSION['usuario']['email'] ?? $_SESSION['usuario']['correo'] ?? '';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $all = loadAll();
    $perfil = null;
    if ($email && isset($all[$email])) { $perfil = $all[$email]; }
    // intentar DB
    if ($hasDb && $email) {
        $stmt = $conn->prepare('SELECT cedula, foto_documento, telefono_2, direccion_1, lat_1, lng_1, direccion_2, lat_2, lng_2, estado_verificacion, motivo_rechazo FROM users WHERE email=? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $dbp = [
                    'cedula' => $row['cedula'],
                    'fotoDoc' => $row['foto_documento'],
                    'tel2' => $row['telefono_2'],
                    'ubicacion1' => $row['direccion_1'],
                    'ubicacion1_coords' => ($row['lat_1'] && $row['lng_1']) ? $row['lat_1'].','.$row['lng_1'] : '',
                    'ubicacion2' => $row['direccion_2'],
                    'ubicacion2_coords' => ($row['lat_2'] && $row['lng_2']) ? $row['lat_2'].','.$row['lng_2'] : '',
                    'estado' => $row['estado_verificacion'] ?: 'sin_datos',
                    'motivo' => $row['motivo_rechazo'] ?: ''
                ];
                if (!$perfil) $perfil = $dbp; else $perfil = array_merge($dbp, $perfil);
            }
            $stmt->close();
        }
    }
    if ($hasDb) $conn->close();
    echo json_encode(['ok' => (bool)$perfil, 'perfil' => $perfil]);
    exit;
}

// POST guardar
$all = loadAll();
$prev = ($email && isset($all[$email])) ? $all[$email] : [];
if (!empty($prev['cedula'])) { $data['cedula'] = $prev['cedula']; }
if (empty($prev['estado']) || $prev['estado'] !== 'aprobado') {
    $data['estado'] = 'en_revision';
}
$all[$email] = array_merge($prev, $data);
$all[$email]['actualizado'] = date('c');

// sync a DB
if ($hasDb && $email) {
    $ced = $all[$email]['cedula'] ?? '';
    $foto = $all[$email]['fotoDoc'] ?? '';
    $tel2 = $all[$email]['tel2'] ?? '';
    $loc1 = $all[$email]['ubicacion1'] ?? '';
    $loc1c = $all[$email]['ubicacion1_coords'] ?? '';
    $lat1 = trim($data['lat_1'] ?? '');
    $lng1 = trim($data['lng_1'] ?? '');
    if ($lat1 === '' || $lng1 === '') {
        if ($loc1c) { $parts = explode(',', $loc1c); $lat1 = trim($parts[0] ?? ''); $lng1 = trim($parts[1] ?? ''); }
    }
    if ($lat1 === '') $lat1 = null;
    if ($lng1 === '') $lng1 = null;
    $loc2 = $all[$email]['ubicacion2'] ?? '';
    $loc2c = $all[$email]['ubicacion2_coords'] ?? '';
    $lat2 = trim($data['lat_2'] ?? '');
    $lng2 = trim($data['lng_2'] ?? '');
    if ($lat2 === '' || $lng2 === '') {
        if ($loc2c) { $parts2 = explode(',', $loc2c); $lat2 = trim($parts2[0] ?? ''); $lng2 = trim($parts2[1] ?? ''); }
    }
    if ($lat2 === '') $lat2 = null;
    if ($lng2 === '') $lng2 = null;
    $ced = $all[$email]['cedula'] ?? '';
    $foto = $all[$email]['fotoDoc'] ?? '';
    $tel2 = $all[$email]['tel2'] ?? '';
    $loc1 = $all[$email]['ubicacion1'] ?? '';
    $loc1c = $all[$email]['ubicacion1_coords'] ?? '';
    $lat1 = trim($data['lat_1'] ?? '');
    $lng1 = trim($data['lng_1'] ?? '');
    if ($lat1 === '' || $lng1 === '') {
        if ($loc1c) { $parts = explode(',', $loc1c); $lat1 = trim($parts[0] ?? ''); $lng1 = trim($parts[1] ?? ''); }
    }
    if ($lat1 === '') $lat1 = null;
    if ($lng1 === '') $lng1 = null;
    $loc2 = $all[$email]['ubicacion2'] ?? '';
    $loc2c = $all[$email]['ubicacion2_coords'] ?? '';
    $lat2 = trim($data['lat_2'] ?? '');
    $lng2 = trim($data['lng_2'] ?? '');
    if ($lat2 === '' || $lng2 === '') {
        if ($loc2c) { $parts2 = explode(',', $loc2c); $lat2 = trim($parts2[0] ?? ''); $lng2 = trim($parts2[1] ?? ''); }
    }
    if ($lat2 === '') $lat2 = null;
    if ($lng2 === '') $lng2 = null;
    $estado = $all[$email]['estado'] ?? 'en_revision';
    $motivo = $all[$email]['motivo'] ?? '';
    $nom = $all[$email]['nombre'] ?? '';
    $tel = $all[$email]['telefono'] ?? '';
    // verificar existencia y estado actual
    $chk = $conn->prepare('SELECT id, cedula, estado_verificacion FROM users WHERE email=? LIMIT 1');
    $chk->bind_param('s', $email);
    $chk->execute();
    $chk->store_result();
    $chk->bind_result($uid, $existingCed, $existingEstado);
    $chk->fetch();
    $exists = $chk->num_rows > 0;
    $chk->close();
    $yaAprobado = ($existingEstado === 'aprobado');
    if ($yaAprobado) {
        // conservar cedula/foto/estado; solo tel + ubicaciones (y cedula/foto si vacíos)
        $cedFinal = (!empty($existingCed)) ? $existingCed : $ced;
        $fotoFinal = $foto;
        if (empty($fotoFinal)) {
            // mantener foto existente en DB
            $q = $conn->prepare('SELECT foto_documento FROM users WHERE email=? LIMIT 1');
            if ($q) { $q->bind_param('s', $email); $q->execute(); $q->bind_result($f); $q->fetch(); $q->close(); if ($f) $fotoFinal = $f; }
        }
        $estado = 'aprobado';
        $stmt = $conn->prepare("UPDATE users SET telefono=?, telefono_2=?, direccion_1=?, lat_1=?, lng_1=?, direccion_2=?, lat_2=?, lng_2=?, name=? WHERE email=?");
        if ($stmt) {
            $stmt->bind_param('ssssssssss', $tel, $tel2, $loc1, $lat1, $lng1, $loc2, $lat2, $lng2, $nom, $email);
            $stmt->execute();
            $stmt->close();
        }
        if (empty($existingCed) && $ced) {
            $stmt2 = $conn->prepare("UPDATE users SET cedula=?, foto_documento=? WHERE email=?");
            if ($stmt2) { $stmt2->bind_param('sss', $ced, $foto, $email); $stmt2->execute(); $stmt2->close(); }
        }
    } else {
        $estado = 'en_revision';
        if (!$exists) {
            $ins = $conn->prepare('INSERT INTO users (name, email, password, role, cedula, foto_documento, telefono, telefono_2, direccion_1, lat_1, lng_1, direccion_2, lat_2, lng_2, estado_verificacion, motivo_rechazo) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            if ($ins) {
                $role = 'customer';
                $pass = '';
                $ins->bind_param('ssssssssssssssss', $nom, $email, $pass, $role, $ced, $foto, $tel, $tel2, $loc1, $lat1, $lng1, $loc2, $lat2, $lng2, $estado, $motivo);
                $ins->execute();
                $ins->close();
            }
        } else {
            $stmt = $conn->prepare("UPDATE users SET cedula=?, foto_documento=?, telefono=?, telefono_2=?, direccion_1=?, lat_1=?, lng_1=?, direccion_2=?, lat_2=?, lng_2=?, estado_verificacion=?, motivo_rechazo=?, name=? WHERE email=?");
            if ($stmt) {
                $stmt->bind_param('ssssssssssssss', $ced, $foto, $tel, $tel2, $loc1, $lat1, $lng1, $loc2, $lat2, $lng2, $estado, $motivo, $nom, $email);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
}
if ($hasDb) $conn->close();
saveAll($all);
echo json_encode(['ok' => true, 'perfil' => $all[$email]]);
exit;
?>