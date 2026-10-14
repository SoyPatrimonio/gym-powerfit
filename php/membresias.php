<?php
/**
 * membresias.php
 * CRUD sobre la tabla `membresias`.
 * Columnas: id_membresia, nombre, descripcion, duracion_meses, precio, incluye_clases
 */
session_start();
require __DIR__ . '/conexion.php';

// El listado público es accesible sin sesión (para la landing).
$accion = $_GET['accion'] ?? 'listar';
if ($accion !== 'listar') {
    requerirSesion();
}

try {
    switch ($accion) {

        case 'listar': {
            $stmt = $conexion->query('SELECT id_membresia, nombre, descripcion, duracion_meses, precio, incluye_clases
                                      FROM membresias ORDER BY precio ASC');
            responderJson(true, 'OK', $stmt->fetchAll());
            break;
        }

        case 'obtener': {
            $id = (int)($_GET['id'] ?? 0);
            $stmt = $conexion->prepare('SELECT * FROM membresias WHERE id_membresia = :id');
            $stmt->execute([':id' => $id]);
            $m = $stmt->fetch();
            if (!$m) responderJson(false, 'Membresía no encontrada', null, 404);
            responderJson(true, 'OK', $m);
            break;
        }

        case 'crear': {
            $d = leerJsonEntrada();
            if (empty($d['nombre']) || !isset($d['duracion_meses']) || !isset($d['precio'])) {
                responderJson(false, 'Nombre, duración (meses) y precio son obligatorios', null, 400);
            }
            $stmt = $conexion->prepare('INSERT INTO membresias (nombre, descripcion, duracion_meses, precio, incluye_clases)
                                        VALUES (:n, :d, :dur, :p, :c)');
            $stmt->execute([
                ':n'   => $d['nombre'],
                ':d'   => $d['descripcion']    ?? null,
                ':dur' => (int)$d['duracion_meses'],
                ':p'   => (float)$d['precio'],
                ':c'   => !empty($d['incluye_clases']) ? 1 : 0
            ]);
            responderJson(true, 'Membresía creada');
            break;
        }

        case 'actualizar': {
            $d  = leerJsonEntrada();
            $id = (int)($d['id_membresia'] ?? $d['id'] ?? 0);
            if ($id <= 0) responderJson(false, 'ID inválido', null, 400);

            $stmt = $conexion->prepare('UPDATE membresias SET
                                            nombre = :n,
                                            descripcion = :d,
                                            duracion_meses = :dur,
                                            precio = :p,
                                            incluye_clases = :c
                                        WHERE id_membresia = :id');
            $stmt->execute([
                ':n'   => $d['nombre'],
                ':d'   => $d['descripcion']    ?? null,
                ':dur' => (int)$d['duracion_meses'],
                ':p'   => (float)$d['precio'],
                ':c'   => !empty($d['incluye_clases']) ? 1 : 0,
                ':id'  => $id
            ]);
            responderJson(true, 'Membresía actualizada');
            break;
        }

        case 'eliminar': {
            $d  = leerJsonEntrada();
            $id = (int)($d['id_membresia'] ?? $d['id'] ?? ($_GET['id'] ?? 0));
            if ($id <= 0) responderJson(false, 'ID inválido', null, 400);

            $stmt = $conexion->prepare('DELETE FROM membresias WHERE id_membresia = :id');
            $stmt->execute([':id' => $id]);
            responderJson(true, 'Membresía eliminada');
            break;
        }

        // Cuenta cuántas membresías están en uso ahora (vía inscripciones vigentes)
        case 'reporte_activas': {
            $sql = "SELECT COUNT(DISTINCT id_membresia) AS total
                    FROM inscripciones
                    WHERE estado = 'vigente' AND fecha_fin >= CAST(GETDATE() AS DATE)";
            responderJson(true, 'OK', $conexion->query($sql)->fetch());
            break;
        }

        default:
            responderJson(false, 'Acción no reconocida', null, 400);
    }
} catch (PDOException $e) {
    responderJson(false, 'Error en operación de membresías', $e->getMessage(), 500);
}
