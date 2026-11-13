<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?: [];

$conn = @new mysqli('localhost', 'root', '', 'gilpollos_db');
if ($conn->connect_error) { echo json_encode(['ok'=>false,'mensaje'=>'DB error']); exit; }
$conn->set_charset('utf8mb4');

// asegurar tablas
$conn->query("CREATE TABLE IF NOT EXISTS orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  cliente_nombre VARCHAR(255),
  cedula VARCHAR(64),
  telefono VARCHAR(64),
  direccion VARCHAR(255),
  lat VARCHAR(64),
  lng VARCHAR(64),
  metodo_pago VARCHAR(64),
  total DECIMAL(12,2) DEFAULT 0,
  estado VARCHAR(32) DEFAULT 'pendiente',
  fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
$conn->query("CREATE TABLE IF NOT EXISTS order_details (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT,
  producto VARCHAR(255),
  cantidad INT DEFAULT 1,
  precio DECIMAL(12,2) DEFAULT 0,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
)");

$nombre = $data['nombre'] ?? '';
$cedula = $data['cedula'] ?? '';
$telefono = $data['telefono'] ?? '';
$direccion = $data['direccion'] ?? '';
$lat = $data['lat'] ?? '';
$lng = $data['lng'] ?? '';
$metodo = $data['metodo_pago'] ?? 'efectivo';
$total = (float)($data['total'] ?? 0);
$items = $data['items'] ?? [];
$userId = $data['user_id'] ?? null;
if (!$userId && isset($_SESSION['usuario_id'])) $userId = $_SESSION['usuario_id'];

$stmt = $conn->prepare('INSERT INTO orders (user_id, cliente_nombre, cedula, telefono, direccion, lat, lng, metodo_pago, total, estado) VALUES (?,?,?,?,?,?,?,?,?,?)');
$estado = 'pendiente';
$uid = $userId ? (int)$userId : null;
$stmt->bind_param('isssssssds', $uid, $nombre, $cedula, $telefono, $direccion, $lat, $lng, $metodo, $total, $estado);
// fix types: total decimal
$stmt = $conn->prepare('INSERT INTO orders (user_id, cliente_nombre, cedula, telefono, direccion, lat, lng, metodo_pago, total, estado) VALUES (?,?,?,?,?,?,?,?,?,?)');
$stmt->bind_param('isssssssds', $uid, $nombre, $cedula, $telefono, $direccion, $lat, $lng, $metodo, $total, $estado);
$stmt->execute();
$orderId = $conn->insert_id;
$stmt->close();

foreach ($items as $it) {
    $prod = $it['nombre'] ?? $it['producto'] ?? '';
    $cant = (int)($it['cantidad'] ?? 1);
    $precio = (float)($it['precio'] ?? 0);
    $st = $conn->prepare('INSERT INTO order_details (order_id, producto, cantidad, precio) VALUES (?,?,?,?)');
    $st->bind_param('isid', $orderId, $prod, $cant, $precio);
    $st->execute();
    $st->close();
}
$conn->close();
echo json_encode(['ok' => true, 'order_id' => $orderId]);
exit;
?>