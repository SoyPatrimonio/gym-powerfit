<?php
/**
 * registro.php
 * Crea un nuevo empleado en la tabla `empleados`.
 * Requiere nombre, apellido (opcional), email, password.
 * Por defecto se crea con rol = 'recepcion' y activo = 1.
 */
session_start();
require __DIR__ . '/conexion.php';

$entrada = leerJsonEntrada();

// El form de la landing manda "nombre completo" en un solo campo.
$nombreFull = trim($entrada['nombre']   ?? '');
$email      = trim($entrada['email']    ?? '');
$password   = trim($entrada['password'] ?? '');

if ($nombreFull === '' || $email === '' || $password === '') {
    responderJson(false, 'Todos los campos son obligatorios', null, 400);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    responderJson(false, 'Correo electrónico no válido', null, 400);
}
if (strlen($password) < 6) {
    responderJson(false, 'La contraseña debe tener al menos 6 caracteres', null, 400);
}

// Partir el nombre completo en nombre y apellido (1ra palabra = nombre, resto = apellido)
$partes   = preg_split('/\s+/', $nombreFull, 2);
$nombre   = $partes[0];
$apellido = $partes[1] ?? '-';

try {
    $check = $conexion->prepare('SELECT id_empleado FROM empleados WHERE email = :email');
    $check->execute([':email' => $email]);
    if ($check->fetch()) {
        responderJson(false, 'Ese correo ya está registrado', null, 409);
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);

    $sql = "INSERT INTO empleados
              (nombre, apellido, cargo, salario, fecha_contratacion, id_sucursal, email, password_hash, rol, activo)
            VALUES
              (:n, :a, :cargo, NULL, CAST(GETDATE() AS DATE), NULL, :email, :hash, :rol, 1)";
    $stmt = $conexion->prepare($sql);
    $stmt->execute([
        ':n'     => $nombre,
        ':a'     => $apellido,
        ':cargo' => 'Recepcionista',
        ':email' => $email,
        ':hash'  => $hash,
        ':rol'   => 'recepcion'
    ]);

    responderJson(true, 'Cuenta creada con éxito. Ya puedes iniciar sesión.');
} catch (PDOException $e) {
    responderJson(false, 'Error al registrar usuario', $e->getMessage(), 500);
}
