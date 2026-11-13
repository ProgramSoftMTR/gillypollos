<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?: [];
$identificador = trim($data['usuario'] ?? '');
$password = $data['password'] ?? '';

if ($identificador === '') {
    echo json_encode(['ok' => false, 'error' => 'Correo o contraseña incorrectos.']);
    exit;
}

$conn = @new mysqli('localhost', 'root', '', 'gilpollos_db');
if ($conn->connect_error) {
    echo json_encode(['ok' => false, 'error' => 'Error de conexión a la base de datos.']);
    exit;
}
$conn->set_charset('utf8mb4');

$stmt = $conn->prepare('SELECT id, name, email, password, role FROM users WHERE (LOWER(TRIM(email)) = LOWER(TRIM(?)) OR LOWER(TRIM(name)) = LOWER(TRIM(?))) LIMIT 1');
$stmt->bind_param('ss', $identificador, $identificador);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
    $conn->close();
    echo json_encode(['ok' => false, 'error' => 'Correo o contraseña incorrectos.']);
    exit;
}

$stored = $row['password'];
$valid = password_verify($password, $stored) || ($password === $stored);

if (!$valid) {
    $conn->close();
    echo json_encode(['ok' => false, 'error' => 'Correo o contraseña incorrectos.']);
    exit;
}

if (($row['role'] ?? '') !== 'admin') {
    $conn->close();
    echo json_encode(['ok' => false, 'error' => 'Correo o contraseña incorrectos.']);
    exit;
}

$_SESSION['es_admin'] = true;
$_SESSION['admin_id'] = $row['id'];
$_SESSION['admin_nombre'] = $row['name'];
$conn->close();
echo json_encode(['ok' => true, 'redirect' => 'admin.php']);
exit;
?>