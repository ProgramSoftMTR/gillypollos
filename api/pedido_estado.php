<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
if (empty($_SESSION['es_admin'])) { echo json_encode(['ok'=>false]); exit; }
$raw = file_get_contents('php://input');
$d = json_decode($raw, true) ?: [];
$id = (int)($d['id'] ?? 0);
$estado = $d['estado'] ?? '';
$permitidos = ['pendiente','en_camino','entregado','preparando'];
if (!$id || !in_array($estado, $permitidos, true)) { echo json_encode(['ok'=>false]); exit; }
$conn = @new mysqli('localhost', 'root', '', 'gilpollos_db');
if ($conn->connect_error) { echo json_encode(['ok'=>false]); exit; }
$conn->set_charset('utf8mb4');
$stmt = $conn->prepare('UPDATE orders SET estado=? WHERE id=?');
$stmt->bind_param('si', $estado, $id);
$stmt->execute();
$stmt->close();
$conn->close();
echo json_encode(['ok'=>true]);
exit;
?>