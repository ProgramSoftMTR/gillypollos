<?php
function gp_ensure_columns($conn) {
    if (!$conn || $conn->connect_error) return;
    $cols = [
        'cedula VARCHAR(30) NULL',
        'foto_documento LONGTEXT NULL',
        'telefono VARCHAR(30) NULL',
        'telefono_2 VARCHAR(30) NULL',
        'direccion_1 VARCHAR(255) NULL',
        'lat_1 DECIMAL(10,7) NULL',
        'lng_1 DECIMAL(10,7) NULL',
        'direccion_2 VARCHAR(255) NULL',
        'lat_2 DECIMAL(10,7) NULL',
        'lng_2 DECIMAL(10,7) NULL',
        'estado_verificacion VARCHAR(30) NOT NULL DEFAULT \'sin_datos\'',
        'motivo_rechazo VARCHAR(255) NULL'
    ];
    foreach ($cols as $c) {
        $name = explode(' ', $c)[0];
        $r = $conn->query("SHOW COLUMNS FROM users LIKE '$name'");
        if ($r && $r->num_rows === 0) {
            $conn->query("ALTER TABLE users ADD COLUMN $c");
        }
    }
}
function gp_migrate_json_to_db($conn) {
    if (!$conn || $conn->connect_error) return;
    $file = __DIR__ . '/../data/perfil_usuario.json';
    if (!file_exists($file)) return;
    $all = json_decode(file_get_contents($file), true);
    if (!is_array($all)) return;
    foreach ($all as $email => $p) {
        $stmt = $conn->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $res = $stmt->get_result();
        if (!$res->fetch_assoc()) { $stmt->close(); continue; }
        $stmt->close();
        $estado = $p['estado'] ?? 'sin_datos';
        $ced = $p['cedula'] ?? '';
        $foto = $p['fotoDoc'] ?? '';
        $tel2 = $p['tel2'] ?? '';
        $loc1 = $p['ubicacion1'] ?? '';
        $loc1c = $p['ubicacion1_coords'] ?? '';
        $lat1 = ''; $lng1 = '';
        if ($loc1c) { $parts = explode(',', $loc1c); $lat1 = trim($parts[0] ?? ''); $lng1 = trim($parts[1] ?? ''); }
        $loc2 = $p['ubicacion2'] ?? '';
        $loc2c = $p['ubicacion2_coords'] ?? '';
        $lat2 = ''; $lng2 = '';
        if ($loc2c) { $parts2 = explode(',', $loc2c); $lat2 = trim($parts2[0] ?? ''); $lng2 = trim($parts2[1] ?? ''); }
        $motivo = $p['motivo'] ?? '';
        $tel = $p['telefono'] ?? '';
        $nom = $p['nombre'] ?? '';
        $stmt = $conn->prepare("UPDATE users SET cedula=IF(cedula IS NULL OR cedula='', ?, cedula), foto_documento=IF(foto_documento IS NULL OR foto_documento='', ?, foto_documento), telefono=IF(telefono IS NULL OR telefono='', ?, telefono), telefono_2=IF(telefono_2 IS NULL OR telefono_2='', ?, telefono_2), direccion_1=IF(direccion_1 IS NULL OR direccion_1='', ?, direccion_1), lat_1=IF(lat_1 IS NULL, ?, lat_1), lng_1=IF(lng_1 IS NULL, ?, lng_1), direccion_2=IF(direccion_2 IS NULL OR direccion_2='', ?, direccion_2), lat_2=IF(lat_2 IS NULL, ?, lat_2), lng_2=IF(lng_2 IS NULL, ?, lng_2), estado_verificacion=IF(estado_verificacion IS NULL OR estado_verificacion='sin_datos' OR estado_verificacion='', ?, estado_verificacion), motivo_rechazo=IF(motivo_rechazo IS NULL OR motivo_rechazo='', ?, motivo_rechazo), name=IF(name IS NULL OR name='', ?, name) WHERE email=?");
        if ($stmt) {
            $stmt->bind_param('ssssssssssssss', $ced, $foto, $tel, $tel2, $loc1, $lat1, $lng1, $loc2, $lat2, $lng2, $estado, $motivo, $nom, $email);
            $stmt->execute();
            $stmt->close();
        }
    }
}
?>