<?php
/**
 * rutinas.php
 * CRUD sobre la tabla `clases` (gym_powerfit no tiene tabla "rutinas";
 * usamos `clases` que es la equivalente: sesiones grupales del gimnasio).
 * También expone listado de `reservas` para control de asistencia.
 *
 * Columnas:
 *   clases  -> id_clase, nombre, descripcion, duracion_min, cupo_maximo, id_entrenador
 *   reservas-> id_reserva, id_cliente, id_clase, fecha_reserva, hora, asistio
 */
session_start();
require __DIR__ . '/conexion.php';
requerirSesion();

$accion = $_GET['accion'] ?? 'listar';

try {
    switch ($accion) {

        case 'listar': {
            $sql = "SELECT c.id_clase, c.nombre, c.descripcion, c.duracion_min, c.cupo_maximo,
                           c.id_entrenador,
                           (e.nombre + ' ' + e.apellido) AS entrenador
                    FROM clases c
                    LEFT JOIN entrenadores ent ON ent.id_entrenador = c.id_entrenador
                    LEFT JOIN empleados    e   ON e.id_empleado     = ent.id_empleado
                    ORDER BY c.id_clase DESC";
            responderJson(true, 'OK', $conexion->query($sql)->fetchAll());
            break;
        }

        case 'obtener': {
            $id = (int)($_GET['id'] ?? 0);
            $stmt = $conexion->prepare('SELECT * FROM clases WHERE id_clase = :id');
            $stmt->execute([':id' => $id]);
            $r = $stmt->fetch();
            if (!$r) responderJson(false, 'Clase no encontrada', null, 404);
            responderJson(true, 'OK', $r);
            break;
        }

        case 'crear': {
            $d = leerJsonEntrada();
            if (empty($d['nombre']) || empty($d['duracion_min']) || empty($d['cupo_maximo'])) {
                responderJson(false, 'Nombre, duración y cupo son obligatorios', null, 400);
            }
            $stmt = $conexion->prepare('INSERT INTO clases (nombre, descripcion, duracion_min, cupo_maximo, id_entrenador)
                                        VALUES (:n, :d, :dur, :cupo, :ent)');
            $stmt->execute([
                ':n'    => $d['nombre'],
                ':d'    => $d['descripcion']   ?? null,
                ':dur'  => (int)$d['duracion_min'],
                ':cupo' => (int)$d['cupo_maximo'],
                ':ent'  => !empty($d['id_entrenador']) ? (int)$d['id_entrenador'] : null
            ]);
            responderJson(true, 'Clase creada');
            break;
        }

        case 'actualizar': {
            $d  = leerJsonEntrada();
            $id = (int)($d['id_clase'] ?? $d['id'] ?? 0);
            if ($id <= 0) responderJson(false, 'ID inválido', null, 400);

            $stmt = $conexion->prepare('UPDATE clases SET
                                            nombre = :n, descripcion = :d,
                                            duracion_min = :dur, cupo_maximo = :cupo,
                                            id_entrenador = :ent
                                        WHERE id_clase = :id');
            $stmt->execute([
                ':n'    => $d['nombre'],
                ':d'    => $d['descripcion']   ?? null,
                ':dur'  => (int)$d['duracion_min'],
                ':cupo' => (int)$d['cupo_maximo'],
                ':ent'  => !empty($d['id_entrenador']) ? (int)$d['id_entrenador'] : null,
                ':id'   => $id
            ]);
            responderJson(true, 'Clase actualizada');
            break;
        }

        case 'eliminar': {
            $d  = leerJsonEntrada();
            $id = (int)($d['id_clase'] ?? $d['id'] ?? ($_GET['id'] ?? 0));
            if ($id <= 0) responderJson(false, 'ID inválido', null, 400);

            $stmt = $conexion->prepare('DELETE FROM clases WHERE id_clase = :id');
            $stmt->execute([':id' => $id]);
            responderJson(true, 'Clase eliminada');
            break;
        }

        // --- Reservas / asistencias ---
        case 'reservas_listar': {
            $sql = "SELECT r.id_reserva, r.id_cliente, r.id_clase,
                           CONVERT(VARCHAR(10), r.fecha_reserva, 120) AS fecha_reserva,
                           CONVERT(VARCHAR(5),  r.hora, 108)         AS hora,
                           r.asistio,
                           (c.nombre + ' ' + c.apellido) AS cliente,
                           cl.nombre AS clase
                    FROM reservas r
                    INNER JOIN clientes c  ON c.id_cliente = r.id_cliente
                    INNER JOIN clases   cl ON cl.id_clase  = r.id_clase
                    ORDER BY r.fecha_reserva DESC, r.hora DESC";
            responderJson(true, 'OK', $conexion->query($sql)->fetchAll());
            break;
        }

        case 'marcar_asistencia': {
            $d = leerJsonEntrada();
            $id = (int)($d['id_reserva'] ?? 0);
            if ($id <= 0) responderJson(false, 'ID inválido', null, 400);
            $stmt = $conexion->prepare('UPDATE reservas SET asistio = 1 WHERE id_reserva = :id');
            $stmt->execute([':id' => $id]);
            responderJson(true, 'Asistencia registrada');
            break;
        }

        default:
            responderJson(false, 'Acción no reconocida', null, 400);
    }
} catch (PDOException $e) {
    responderJson(false, 'Error en operación de clases', $e->getMessage(), 500);
}
