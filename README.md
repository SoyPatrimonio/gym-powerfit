# PowerFit Gym

Sistema web de gestión para un gimnasio: landing page comercial + panel administrativo con CRUDs para clientes, membresías, pagos, rutinas, clases y más.

Funciona en **dos modos**:

- **Modo completo** — backend PHP con PDO sobre SQL Server (autenticación por sesiones y hashes bcrypt).
- **Modo demo** — sin servidor: si el backend no responde, la interfaz trabaja contra `localStorage`, así que basta abrir `index.html` en el navegador para explorarlo.

## Tecnologías

| Capa | Stack |
|---|---|
| Frontend | HTML5, CSS3 (variables, tema claro/oscuro), JavaScript vanilla |
| Backend | PHP 8 + PDO (`pdo_sqlsrv`) |
| Base de datos | Microsoft SQL Server Express |
| Iconos / fuentes | Font Awesome 6, Google Fonts (Poppins) |

Sin frameworks ni dependencias por instalar.

## Estructura

```
gym_powerfit/
├── index.html              # Landing: hero, planes, tienda, entrenadores, FAQ, login/registro
├── css/styles.css          # Hoja de estilos única (incluye tema oscuro)
├── js/script.js            # Navbar, formularios, helper api(), autenticación demo
├── php/
│   ├── conexion.php        # Conexión PDO + helpers (responderJson, requerirSesion)
│   ├── login.php           # Autenticación contra la tabla empleados
│   ├── registro.php        # Alta de usuarios (bcrypt)
│   ├── logout.php          # Cierre de sesión
│   ├── clientes.php        # CRUD de clientes
│   ├── membresias.php      # CRUD de membresías
│   ├── pagos.php           # CRUD de pagos
│   └── rutinas.php         # CRUD de rutinas
├── views/                  # Panel administrativo (15 vistas)
│   ├── dashboard.html      # Panel admin / recepción
│   ├── entrenador.html     # Portal del entrenador
│   ├── cliente.html        # Portal del cliente
│   ├── clientes.html  membresias.html  pagos.html  personal.html
│   ├── rutinas.html   asignaciones.html  mediciones.html
│   ├── asistencias.html  reservas.html   productos.html
│   └── planes-nutricionales.html  reportes.html
├── database/gimnasio.sql   # Esquema completo + datos de ejemplo
└── img/productos/          # Imágenes de la tienda
```

## Base de datos

`database/gimnasio.sql` crea la base `gym_powerfit` y nueve tablas relacionadas:

`sucursales` · `empleados` · `clientes` · `membresias` · `inscripciones` · `pagos` · `entrenadores` · `clases` · `reservas`

El script es idempotente: elimina las tablas en orden inverso de claves foráneas antes de recrearlas, e inserta datos de ejemplo (sucursales, empleados con hash bcrypt, planes y clientes).

## Instalación

### Opción A — Modo demo (solo navegador)

```bash
git clone https://github.com/SoyPatrimonio/gym-powerfit.git
cd gym-powerfit
```

Abre `index.html` en el navegador. No requiere servidor: los datos se guardan en `localStorage`.

### Opción B — Modo completo (PHP + SQL Server)

1. **Base de datos** — ejecuta `database/gimnasio.sql` en SQL Server Management Studio o con `sqlcmd`:

   ```bash
   sqlcmd -S localhost\SQLEXPRESS -E -i database/gimnasio.sql
   ```

2. **Driver PHP** — habilita `pdo_sqlsrv` en `php.ini`:

   ```ini
   extension=pdo_sqlsrv
   ```

3. **Conexión** — ajusta servidor y base en [php/conexion.php](php/conexion.php) si tu instancia difiere:

   ```php
   $servidor  = 'localhost\SQLEXPRESS';
   $baseDatos = 'gym_powerfit';
   ```

   Usa **Windows Authentication**, por lo que no hay usuario ni contraseña en el código: la conexión toma la cuenta del proceso de Apache/PHP.

4. **Servidor** — copia el proyecto al directorio web (por ejemplo `C:\xampp\htdocs\gym_powerfit`) y visita `http://localhost/gym_powerfit/`.

## Cuentas de prueba

Credenciales del **modo demo**, pensadas solo para evaluar la interfaz:

| Rol | Email | Contraseña | Portal |
|---|---|---|---|
| Administrador | `admin@powerfit.com` | `admin123` | `views/dashboard.html` |
| Recepción | `recepcion@powerfit.com` | `recepcion123` | `views/dashboard.html` |
| Entrenador | (los de `views/personal.html`) | `entrenador123` | `views/entrenador.html` |
| Cliente | (los de `views/clientes.html`) | `cliente123` | `views/cliente.html` |

> En modo completo el acceso se valida contra la tabla `empleados` con `password_verify()`. Cambia los hashes de ejemplo antes de cualquier uso real.

## API

Los endpoints de `php/` reciben y devuelven JSON con una forma uniforme:

```json
{ "ok": true, "mensaje": "", "datos": null }
```

El frontend los consume con el helper `api(url, datos, metodo)` de [js/script.js](js/script.js), que ante un fallo de red devuelve `{ ok: false }` y deja que la vista caiga al modo demo.

## Notas

Proyecto académico. Antes de llevarlo a producción conviene: mover la configuración a variables de entorno, forzar HTTPS, añadir protección CSRF en los formularios, validar y escapar la salida en todas las vistas, y reemplazar los usuarios y hashes de ejemplo.
