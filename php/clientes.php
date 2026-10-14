<?php
/**
 * clientes.php
 * CRUD completo sobre la tabla `clientes` (esquema real de gym_powerfit).
 * Columnas: id_cliente, nombre, apellido, documento, fecha_nacimiento,
 *           genero, telefono, email, fecha_registro
 */
session_start();
require __DIR__ . '/conexion.php';
requerirSesion();

$accion = $_GET['accion'] ?? 'listar';

try {
    switch ($accion) {

        case 'listar': {
            $sql = "SELECT id_cliente, nombre, apellido, documento, telefono, email,
                           CONVERT(VARCHAR(10), fecha_nacimiento, 120) AS fecha_nacimiento,
                           genero,
                           CONVERT(VARCHAR(10), fecha_registro, 120) AS fecha_registro
                    FROM clientes ORDER BY id_cliente DESC";
            $stmt = $conexion->query($sql);
            responderJson(true, 'OK', $stmt->fetchAll());
            break;
        }

        case 'obtener': {
            $id = (int)($_GET['id'] ?? 0);
            $sql = "SELECT id_cliente, nombre, apellido, documento, telefono, email,
                           CONVERT(VARCHAR(10), fecha_nacimiento, 120) AS fecha_nacimiento,
                           genero,
                           CONVERT(VARCHAR(10), fecha_registro, 120) AS fecha_registro
                    FROM clientes WHERE id_cliente = :id";
            $stmt = $conexion->prepare($sql);
            $stmt->execute([':id' => $id]);
            $cli = $stmt->fetch();
            if (!$cli) responderJson(false, 'Cliente no encontrado', null, 404);
            responderJson(true, 'OK', $cli);
            break;
        }

        case 'crear': {
            $d = leerJsonEntrada();
            foreach (['nombre', 'apellido', 'documento'] as $campo) {
                if (empty(trim($d[$campo] ?? ''))) {
                    responderJson(false, "El campo $campo es obligatorio", null, 400);
                }
            }
            $sql = 'INSERT INTO clientes
                    (nombre, apellido, documento, fecha_nacimiento, genero, telefono, email, fecha_registro)
                    VALUES
                    (:nombre, :apellido, :documento, :fnac, :genero, :tel, :email, :freg)';
            $stmt = $conexion->prepare($sql);
            $stmt->execute([
                ':nombre'    => $d['nombre'],
                ':apellido'  => $d['apellido'],
                ':documento' => $d['documento'],
                ':fnac'      => $d['fecha_nacimiento'] ?: null,
                ':genero'    => $d['genero']           ?: null,
                ':tel'       => $d['telefono']         ?? null,
                ':email'     => $d['email']            ?? null,
                ':freg'      => $d['fecha_registro']   ?: date('Y-m-d')
            ]);
            responderJson(true, 'Cliente creado correctamente');
            break;
        }

        case 'actualizar': {
            $d  = leerJsonEntrada();
            $id = (int)($d['id_cliente'] ?? $d['id'] ?? 0);
            if ($id <= 0) responderJson(false, 'ID inválido', null, 400);

            $sql = 'UPDATE clientes SET
                        nombre = :nombre,
                        apellido = :apellido,
                        documento = :documento,
                        fecha_nacimiento = :fnac,
                        genero = :genero,
                        telefono = :tel,
                        email = :email
                    WHERE id_cliente = :id';
            $stmt = $conexion->prepare($sql);
            $stmt->execute([
                ':nombre'    => $d['nombre'],
                ':apellido'  => $d['apellido'],
                ':documento' => $d['documento'],
                ':fnac'      => $d['fecha_nacimiento'] ?: null,
                ':genero'    => $d['genero']           ?: null,
                ':tel'       => $d['telefono']         ?? null,
                ':email'     => $d['email']            ?? null,
                ':id'        => $id
            ]);
            responderJson(true, 'Cliente actualizado');
            break;
        }

        case 'eliminar': {
            $d  = leerJsonEntrada();
            $id = (int)($d['id_cliente'] ?? $d['id'] ?? ($_GET['id'] ?? 0));
            if ($id <= 0) responderJson(false, 'ID inválido', null, 400);

            $stmt = $conexion->prepare('DELETE FROM clientes WHERE id_cliente = :id');
            $stmt->execute([':id' => $id]);
            responderJson(true, 'Cliente eliminado');
            break;
        }

        // Reporte: clientes con inscripción vigente
        case 'reporte_activos': {
            $sql = "SELECT COUNT(DISTINCT id_cliente) AS total
                    FROM inscripciones
                    WHERE estado = 'vigente' AND fecha_fin >= CAST(GETDATE() AS DATE)";
            responderJson(true, 'OK', $conexion->query($sql)->fetch());
            break;
        }

        default:
            responderJson(false, 'Acción no reconocida', null, 400);
    }
} catch (PDOException $e) {
    responderJson(false, 'Error en operación de clientes', $e->getMessage(), 500);
}
