<?php
/**
 * conexion.php
 * ----------------------------------------------------------
 * Conexión a SQL Server con Windows Authentication
 * (no se pasan usuario/contraseña - usa la cuenta del proceso de Apache).
 * Devuelve una instancia PDO lista para usarse en el CRUD.
 * ----------------------------------------------------------
 */

// -- Configuración del servidor SQL Server --
// Tu instancia es SQL Server Express → usa el nombre de instancia.
$servidor   = 'localhost\\SQLEXPRESS';
$baseDatos  = 'gym_powerfit';

// Cadena DSN para PDO_SQLSRV con autenticación integrada de Windows.
// Sin pasar usuario/contraseña en el constructor → Windows Authentication.
$dsn = "sqlsrv:Server={$servidor};"
     . "Database={$baseDatos};"
     . "Encrypt=no;"
     . "TrustServerCertificate=yes";

try {
    // PDO con $username y $password en NULL → Windows Authentication
    $conexion = new PDO($dsn, null, null);
    $conexion->setAttribute(PDO::ATTR_ERRMODE,            PDO::ERRMODE_EXCEPTION);
    $conexion->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $conexion->setAttribute(PDO::ATTR_EMULATE_PREPARES,   false);
} catch (PDOException $e) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'ok'      => false,
        'mensaje' => 'Error de conexión a SQL Server',
        'detalle' => $e->getMessage()
    ]);
    exit;
}

/**
 * Helper: envía una respuesta JSON estándar y termina la ejecución.
 */
function responderJson(bool $ok, string $mensaje = '', $datos = null, int $codigo = 200): void {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($codigo);
    echo json_encode([
        'ok'      => $ok,
        'mensaje' => $mensaje,
        'datos'   => $datos
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Helper: lee el cuerpo JSON enviado por fetch().
 */
function leerJsonEntrada(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/**
 * Helper: verifica que exista una sesión válida.
 * Si no la hay, corta la ejecución con un 401.
 */
function requerirSesion(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['usuario_id'])) {
        responderJson(false, 'Sesión no iniciada', null, 401);
    }
}
