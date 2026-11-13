<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
if (empty($_SESSION['es_admin'])) { echo json_encode(['ok'=>false,'mensaje'=>'No autorizado']); exit; }
$raw = file_get_contents('php://input');
$d = json_decode($raw, true) ?: [];
$email = $d['email'] ?? '';
$accion = $d['accion'] ?? '';
$dir = __DIR__ . '/../data';
$file = $dir . '/perfil_usuario.json';
if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
function loadAll(){ global $file; if(!file_exists($file)) return []; $c=file_get_contents($file); $j=json_decode($c,true); return is_array($j)?$j:[]; }
function saveAll($a){ global $file; file_put_contents($file, json_encode($a, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)); }
$all = loadAll();
if (!$email || !isset($all[$email])) { echo json_encode(['ok'=>false,'mensaje'=>'Usuario no encontrado']); exit; }
if ($accion === 'aprobar') {
    $all[$email]['estado'] = 'aprobado';
    $all[$email]['motivo'] = '';
} elseif ($accion === 'rechazar') {
    $all[$email]['estado'] = 'rechazado';
    $all[$email]['motivo'] = $d['motivo'] ?? 'Foto borrosa o ilegible';
} else {
    echo json_encode(['ok'=>false,'mensaje'=>'Acción inválida']); exit;
}
saveAll($all);
require_once __DIR__ . '/ensure_columns.php';
// sync DB
$conn = @new mysqli('localhost', 'root', '', 'gilpollos_db');
if (!$conn->connect_error) {
    $conn->set_charset('utf8mb4');
    gp_ensure_columns($conn);
    gp_migrate_json_to_db($conn);
    $estado = $all[$email]['estado'];
    $motivo = $all[$email]['motivo'] ?? '';
    $stmt = $conn->prepare('UPDATE users SET estado_verificacion=?, motivo_rechazo=? WHERE email=?');
    if ($stmt) { $stmt->bind_param('sss', $estado, $motivo, $email); $stmt->execute(); $stmt->close(); }
    $conn->close();
}
echo json_encode(['ok'=>true]);
exit;
?>