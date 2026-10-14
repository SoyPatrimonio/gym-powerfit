<?php
/**
 * pagos.php
 * CRUD sobre la tabla `pagos` + lógica de inscripción.
 *
 * Estructura real:
 *   pagos        -> id_pago, id_inscripcion, monto, fecha_pago, metodo_pago, referencia
 *   inscripciones-> id_inscripcion, id_cliente, id_membresia, id_sucursal,
 *                   fecha_inicio, fecha_fin, estado
 *
 * Para registrar un pago, el frontend manda id_cliente + id_membresia y
 * aquí buscamos o creamos automáticamente la inscripción correspondiente.
 */
session_start();
require __DIR__ . '/conexion.php';
requerirSesion();

$accion = $_GET['accion'] ?? 'listar';

try {
    switch ($accion) {

        case 'listar': {
            $sql = "SELECT p.id_pago, p.id_inscripcion, p.monto, p.metodo_pago, p.referencia,
                           CONVERT(VARCHAR(10), p.fecha_pago, 120) AS fecha_pago,
                           i.id_cliente, i.id_membresia,
                           (c.nombre + ' ' + c.apellido) AS cliente,
                           m.nombre AS membresia
                    FROM pagos p
                    INNER JOIN inscripciones i ON i.id_inscripcion = p.id_inscripcion
                    INNER JOIN clientes      c ON c.id_cliente     = i.id_cliente
                    INNER JOIN membresias    m ON m.id_membresia   = i.id_membresia
                    ORDER BY p.fecha_pago DESC, p.id_pago DESC";
            responderJson(true, 'OK', $conexion->query($sql)->fetchAll());
            break;
        }

        case 'obtener': {
            $id = (int)($_GET['id'] ?? 0);
            $sql = "SELECT p.*, i.id_cliente, i.id_membresia
                    FROM pagos p
                    INNER JOIN inscripciones i ON i.id_inscripcion = p.id_inscripcion
                    WHERE p.id_pago = :id";
            $stmt = $conexion->prepare($sql);
            $stmt->execute([':id' => $id]);
            $p = $stmt->fetch();
            if (!$p) responderJson(false, 'Pago no encontrado', null, 404);
            // Normaliza la fecha para el input datetime-local del HTML
            $p['fecha_pago'] = $p['fecha_pago'] ? (new DateTime($p['fecha_pago']))->format('Y-m-d') : '';
            responderJson(true, 'OK', $p);
            break;
        }

        case 'crear': {
            $d = leerJsonEntrada();
            $idCli  = (int)($d['id_cliente']   ?? 0);
            $idMem  = (int)($d['id_membresia'] ?? 0);
            $monto  = (float)($d['monto']      ?? 0);

            if ($idCli <= 0 || $idMem <= 0)  responderJson(false, 'Cliente y membresía son obligatorios', null, 400);
            if ($monto <= 0)                 responderJson(false, 'Monto inválido', null, 400);

            // Buscar o crear inscripción
            $idIns = obtenerOCrearInscripcion($conexion, $idCli, $idMem);

            $sql = 'INSERT INTO pagos (id_inscripcion, monto, fecha_pago, metodo_pago, referencia)
                    VALUES (:ins, :monto, :fecha, :metodo, :ref)';
            $stmt = $conexion->prepare($sql);
            $stmt->execute([
                ':ins'    => $idIns,
                ':monto'  => $monto,
                ':fecha'  => $d['fecha_pago']  ?: date('Y-m-d'),
                ':metodo' => $d['metodo_pago'] ?: 'Efectivo',
                ':ref'    => $d['referencia']  ?? null
            ]);
            responderJson(true, 'Pago registrado');
            break;
        }

        case 'actualizar': {
            $d  = leerJsonEntrada();
            $id = (int)($d['id_pago'] ?? $d['id'] ?? 0);
            if ($id <= 0) responderJson(false, 'ID inválido', null, 400);

            $idCli  = (int)($d['id_cliente']   ?? 0);
            $idMem  = (int)($d['id_membresia'] ?? 0);
            $idIns  = obtenerOCrearInscripcion($conexion, $idCli, $idMem);

            $sql = 'UPDATE pagos SET
                        id_inscripcion = :ins,
                        monto          = :monto,
                        fecha_pago     = :fecha,
                        metodo_pago    = :metodo,
                        referencia     = :ref
                    WHERE id_pago = :id';
            $stmt = $conexion->prepare($sql);
            $stmt->execute([
                ':ins'    => $idIns,
                ':monto'  => (float)$d['monto'],
                ':fecha'  => $d['fecha_pago']  ?: date('Y-m-d'),
                ':metodo' => $d['metodo_pago'] ?: 'Efectivo',
                ':ref'    => $d['referencia']  ?? null,
                ':id'     => $id
            ]);
            responderJson(true, 'Pago actualizado');
            break;
        }

        case 'eliminar': {
            $d  = leerJsonEntrada();
            $id = (int)($d['id_pago'] ?? $d['id'] ?? ($_GET['id'] ?? 0));
            if ($id <= 0) responderJson(false, 'ID inválido', null, 400);

            $stmt = $conexion->prepare('DELETE FROM pagos WHERE id_pago = :id');
            $stmt->execute([':id' => $id]);
            responderJson(true, 'Pago eliminado');
            break;
        }

        case 'reporte_mes': {
            $sql = "SELECT ISNULL(SUM(monto), 0) AS total, COUNT(*) AS transacciones
                    FROM pagos
                    WHERE MONTH(fecha_pago) = MONTH(GETDATE())
                      AND YEAR(fecha_pago)  = YEAR(GETDATE())";
            responderJson(true, 'OK', $conexion->query($sql)->fetch());
            break;
        }

        default:
            responderJson(false, 'Acción no reconocida', null, 400);
    }
} catch (PDOException $e) {
    responderJson(false, 'Error en operación de pagos', $e->getMessage(), 500);
}

/**
 * Devuelve el id_inscripcion existente más reciente para ese cliente+membresía,
 * o crea uno nuevo vigente desde hoy. Usa la primera sucursal disponible.
 */
function obtenerOCrearInscripcion(PDO $db, int $idCli, int $idMem): int {
    $stmt = $db->prepare("SELECT TOP 1 id_inscripcion
                          FROM inscripciones
                          WHERE id_cliente = :c AND id_membresia = :m
                          ORDER BY fecha_inicio DESC");
    $stmt->execute([':c' => $idCli, ':m' => $idMem]);
    $row = $stmt->fetch();
    if ($row) return (int)$row['id_inscripcion'];

    // Crear nueva inscripción: usa duración de la membresía y la 1ra sucursal
    $mem = $db->prepare('SELECT duracion_meses FROM membresias WHERE id_membresia = :m');
    $mem->execute([':m' => $idMem]);
    $meses = (int)($mem->fetchColumn() ?: 1);

    $suc = (int)$db->query('SELECT TOP 1 id_sucursal FROM sucursales ORDER BY id_sucursal')->fetchColumn();
    if ($suc <= 0) throw new RuntimeException('No hay sucursales registradas.');

    $hoy = date('Y-m-d');
    $fin = date('Y-m-d', strtotime("+$meses months"));

    $ins = $db->prepare("INSERT INTO inscripciones
                          (id_cliente, id_membresia, id_sucursal, fecha_inicio, fecha_fin, estado)
                          VALUES (:c, :m, :s, :ini, :fin, 'vigente')");
    $ins->execute([':c' => $idCli, ':m' => $idMem, ':s' => $suc, ':ini' => $hoy, ':fin' => $fin]);
    return (int)$db->lastInsertId();
}
