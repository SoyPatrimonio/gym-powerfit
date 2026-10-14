<?php
/**
 * login.php
 * Autenticación usando la tabla `empleados` (email + password_hash).
 * Solo permite ingreso a empleados con activo = 1.
 */
session_start();
require __DIR__ . '/conexion.php';

$entrada  = leerJsonEntrada();
$email    = trim($entrada['email']    ?? '');
$password = trim($entrada['password'] ?? '');

if ($email === '' || $password === '') {
    responderJson(false, 'Email y contraseña son obligatorios', null, 400);
}

try {
    $sql = 'SELECT id_empleado, nombre, apellido, email, password_hash, rol, activo
            FROM empleados WHERE email = :email';
    $stmt = $conexion->prepare($sql);
    $stmt->execute([':email' => $email]);
    $emp = $stmt->fetch();

    if (!$emp || !password_verify($password, $emp['password_hash'])) {
        responderJson(false, 'Credenciales inválidas', null, 401);
    }
    if ((int)$emp['activo'] !== 1) {
        responderJson(false, 'Tu cuenta está inactiva. Contacta al administrador.', null, 403);
    }

    // Guardar sesión
    $_SESSION['usuario_id']     = $emp['id_empleado'];
    $_SESSION['usuario_nombre'] = $emp['nombre'] . ' ' . $emp['apellido'];
    $_SESSION['usuario_rol']    = $emp['rol'];

    responderJson(true, 'Sesión iniciada', [
        'id'     => $emp['id_empleado'],
        'nombre' => $emp['nombre'] . ' ' . $emp['apellido'],
        'rol'    => $emp['rol']
    ]);
} catch (PDOException $e) {
    responderJson(false, 'Error al consultar la base de datos', $e->getMessage(), 500);
}
