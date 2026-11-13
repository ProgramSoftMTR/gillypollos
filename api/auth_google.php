<?php
ob_start();
session_start();
header('Content-Type: application/json; charset=utf-8');

$CLIENT_ID = '119531505215-g8vjsnlgcn029q97ckmccqpkop1o1hqi.apps.googleusercontent.com';

function fail($msg) {
    ob_clean();
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$action = isset($_GET['accion']) ? $_GET['accion'] : '';

if ($action === 'estado') {
    ob_clean();
    echo json_encode(['ok' => true, 'usuario' => $_SESSION['user'] ?? ($_SESSION['usuario'] ?? null)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'logout') {
    $_SESSION = [];
    session_destroy();
    ob_clean();
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) {
    fail('Datos inválidos');
}

$accessToken = $data['access_token'] ?? '';
$email = '';
$nombre = '';
$foto = '';
$googleSub = '';

if ($accessToken !== '') {
    $ch = curl_init('https://www.googleapis.com/oauth2/v3/userinfo');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 6);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
    $userinfoResp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($userinfoResp === false || $code !== 200) {
        fail('No se pudo obtener el perfil: ' . $curlErr . ' (HTTP ' . $code . ')');
    }
    $uinfo = json_decode($userinfoResp, true);
    if (!$uinfo || empty($uinfo['email'])) {
        fail('Perfil inválido');
    }
    $email = $uinfo['email'];
    $nombre = $uinfo['name'] ?? '';
    $foto = $uinfo['picture'] ?? '';
    $googleSub = $uinfo['sub'] ?? '';
} elseif (!empty($data['credential'])) {
    $cred = $data['credential'];
    $tokeninfo = @file_get_contents('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($cred));
    if (!$tokeninfo) fail('No se pudo verificar el token');
    $info = json_decode($tokeninfo, true);
    if (!$info || ($info['aud'] ?? '') !== $CLIENT_ID || ($info['email_verified'] ?? '') !== 'true') {
        fail('Token inválido');
    }
    $email = $info['email'] ?? '';
    $nombre = $info['name'] ?? '';
    $foto = $info['picture'] ?? '';
    $googleSub = $info['sub'] ?? '';
} else {
    fail('access_token faltante');
}

$telefono = $data['telefono'] ?? '';
$direccion = $data['direccion'] ?? '';
$acepta = !empty($data['acepta_promos']);

// MySQL connect to gilpollos_db
try {
    $pdo = new PDO('mysql:host=localhost;dbname=gilpollos_db;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    fail('Error de conexión MySQL: ' . $e->getMessage());
}

// Ensure columns exist (MariaDB 10.4 compatible)
$alters = [
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS google_sub VARCHAR(64) NULL UNIQUE",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS foto VARCHAR(500) NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS telefono VARCHAR(50) NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS direccion VARCHAR(255) NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS recibir_promos TINYINT(1) DEFAULT 1",
    "ALTER TABLE users MODIFY COLUMN password VARCHAR(255) NULL",
];
foreach ($alters as $sql) {
    try { $pdo->exec($sql); } catch (PDOException $e) { /* ignore */ }
}

// upsert
try {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $row = $stmt->fetch();
    if ($row) {
        $upd = $pdo->prepare('UPDATE users SET google_sub=?, name=?, foto=?, telefono=?, direccion=?, recibir_promos=? WHERE email=?');
        $upd->execute([$googleSub, $nombre, $foto, $telefono, $direccion, $acepta ? 1 : 0, $email]);
        $id = $row['id'];
    } else {
        $ins = $pdo->prepare('INSERT INTO users (google_sub, name, email, foto, telefono, direccion, recibir_promos, role) VALUES (?,?,?,?,?,?,?,?)');
        $ins->execute([$googleSub, $nombre, $email, $foto, $telefono, $direccion, $acepta ? 1 : 0, 'customer']);
        $id = $pdo->lastInsertId();
    }
} catch (PDOException $e) {
    fail('Error al guardar usuario: ' . $e->getMessage());
}

$user = [
    'id' => $id,
    'google_sub' => $googleSub,
    'name' => $nombre,
    'nombre' => $nombre,
    'email' => $email,
    'picture' => $foto,
    'foto' => $foto,
    'telefono' => $telefono,
    'direccion' => $direccion,
    'recibir_promos' => $acepta
];

$_SESSION['user'] = $user;
$_SESSION['usuario'] = $user;

ob_clean();
echo json_encode(['ok' => true, 'redirect' => 'panel.php', 'user' => $user], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
