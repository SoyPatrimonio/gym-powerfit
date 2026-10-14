

-- Crear la base de datos (si no existe)
IF NOT EXISTS (SELECT name FROM sys.databases WHERE name = 'gym_powerfit')
BEGIN
    CREATE DATABASE gym_powerfit;
END
GO

USE gym_powerfit;
GO

-- ============================================================
-- Eliminar tablas existentes (orden inverso por claves foráneas)
-- ============================================================
IF OBJECT_ID('reservas',      'U') IS NOT NULL DROP TABLE reservas;
IF OBJECT_ID('clases',        'U') IS NOT NULL DROP TABLE clases;
IF OBJECT_ID('entrenadores',  'U') IS NOT NULL DROP TABLE entrenadores;
IF OBJECT_ID('pagos',         'U') IS NOT NULL DROP TABLE pagos;
IF OBJECT_ID('inscripciones', 'U') IS NOT NULL DROP TABLE inscripciones;
IF OBJECT_ID('membresias',    'U') IS NOT NULL DROP TABLE membresias;
IF OBJECT_ID('clientes',      'U') IS NOT NULL DROP TABLE clientes;
IF OBJECT_ID('empleados',     'U') IS NOT NULL DROP TABLE empleados;
IF OBJECT_ID('sucursales',    'U') IS NOT NULL DROP TABLE sucursales;
GO

-- ============================================================
-- Tabla: sucursales
-- ============================================================
CREATE TABLE sucursales (
    id_sucursal  INT IDENTITY(1,1) PRIMARY KEY,
    nombre       NVARCHAR(100) NOT NULL,
    direccion    NVARCHAR(200),
    ciudad       NVARCHAR(80),
    telefono     NVARCHAR(20),
    email        NVARCHAR(150)
);
GO

-- ============================================================
-- Tabla: empleados (administradores, recepción, entrenadores,
--                   nutricionistas). Se usa para el login.
-- ============================================================
CREATE TABLE empleados (
    id_empleado         INT IDENTITY(1,1) PRIMARY KEY,
    nombre              NVARCHAR(100) NOT NULL,
    apellido            NVARCHAR(100) NOT NULL,
    cargo               NVARCHAR(80),
    salario             DECIMAL(10,2),
    fecha_contratacion  DATE          NOT NULL DEFAULT CAST(GETDATE() AS DATE),
    id_sucursal         INT           NULL,
    telefono            NVARCHAR(20),
    email               NVARCHAR(150) NOT NULL UNIQUE,
    password_hash       NVARCHAR(255) NOT NULL,
    rol                 NVARCHAR(20)  NOT NULL DEFAULT 'recepcion',
    activo              BIT           NOT NULL DEFAULT 1,
    CONSTRAINT FK_Empleados_Sucursal FOREIGN KEY (id_sucursal) REFERENCES sucursales(id_sucursal)
);
GO

CREATE INDEX IX_Empleados_Email ON empleados(email);
GO

-- ============================================================
-- Tabla: clientes
-- ============================================================
CREATE TABLE clientes (
    id_cliente        INT IDENTITY(1,1) PRIMARY KEY,
    nombre            NVARCHAR(100) NOT NULL,
    apellido          NVARCHAR(100) NOT NULL,
    documento         NVARCHAR(20)  NOT NULL UNIQUE,
    fecha_nacimiento  DATE,
    genero            NVARCHAR(10),
    telefono          NVARCHAR(20),
    email             NVARCHAR(150),
    fecha_registro    DATE          NOT NULL DEFAULT CAST(GETDATE() AS DATE),
    estado            NVARCHAR(20)  NOT NULL DEFAULT 'activo'
);
GO

CREATE INDEX IX_Clientes_Documento ON clientes(documento);
CREATE INDEX IX_Clientes_Estado    ON clientes(estado);
GO

-- ============================================================
-- Tabla: membresias (planes disponibles)
-- ============================================================
CREATE TABLE membresias (
    id_membresia    INT IDENTITY(1,1) PRIMARY KEY,
    nombre          NVARCHAR(100) NOT NULL,
    descripcion     NVARCHAR(500),
    duracion_meses  INT           NOT NULL,
    precio          DECIMAL(10,2) NOT NULL,
    incluye_clases  BIT           NOT NULL DEFAULT 0,
    activo          BIT           NOT NULL DEFAULT 1
);
GO

-- ============================================================
-- Tabla: inscripciones (cliente + plan + sucursal + vigencia)
-- ============================================================
CREATE TABLE inscripciones (
    id_inscripcion  INT IDENTITY(1,1) PRIMARY KEY,
    id_cliente      INT NOT NULL,
    id_membresia    INT NOT NULL,
    id_sucursal     INT NOT NULL,
    fecha_inicio    DATE NOT NULL,
    fecha_fin       DATE NOT NULL,
    estado          NVARCHAR(20) NOT NULL DEFAULT 'vigente',
    CONSTRAINT FK_Insc_Cliente   FOREIGN KEY (id_cliente)   REFERENCES clientes(id_cliente),
    CONSTRAINT FK_Insc_Membresia FOREIGN KEY (id_membresia) REFERENCES membresias(id_membresia),
    CONSTRAINT FK_Insc_Sucursal  FOREIGN KEY (id_sucursal)  REFERENCES sucursales(id_sucursal)
);
GO

CREATE INDEX IX_Insc_Cliente   ON inscripciones(id_cliente);
CREATE INDEX IX_Insc_Membresia ON inscripciones(id_membresia);
CREATE INDEX IX_Insc_Estado    ON inscripciones(estado);
GO

-- ============================================================
-- Tabla: pagos (un pago pertenece a una inscripción)
-- ============================================================
CREATE TABLE pagos (
    id_pago         INT IDENTITY(1,1) PRIMARY KEY,
    id_inscripcion  INT NOT NULL,
    monto           DECIMAL(10,2) NOT NULL,
    fecha_pago      DATETIME      NOT NULL DEFAULT GETDATE(),
    metodo_pago     NVARCHAR(30)  NOT NULL,
    referencia      NVARCHAR(100),
    CONSTRAINT FK_Pagos_Inscripcion FOREIGN KEY (id_inscripcion) REFERENCES inscripciones(id_inscripcion)
);
GO

CREATE INDEX IX_Pagos_Inscripcion ON pagos(id_inscripcion);
CREATE INDEX IX_Pagos_Fecha       ON pagos(fecha_pago);
GO

-- ============================================================
-- Tabla: entrenadores (perfil ligado a un empleado)
-- ============================================================
CREATE TABLE entrenadores (
    id_entrenador    INT IDENTITY(1,1) PRIMARY KEY,
    id_empleado      INT NOT NULL,
    especialidad     NVARCHAR(120),
    certificaciones  NVARCHAR(255),
    CONSTRAINT FK_Entrenadores_Empleado FOREIGN KEY (id_empleado) REFERENCES empleados(id_empleado)
);
GO

-- ============================================================
-- Tabla: clases (sesiones grupales)
-- ============================================================
CREATE TABLE clases (
    id_clase       INT IDENTITY(1,1) PRIMARY KEY,
    nombre         NVARCHAR(100) NOT NULL,
    descripcion    NVARCHAR(500),
    duracion_min   INT           NOT NULL,
    cupo_maximo    INT           NOT NULL,
    id_entrenador  INT           NULL,
    CONSTRAINT FK_Clases_Entrenador FOREIGN KEY (id_entrenador) REFERENCES entrenadores(id_entrenador)
);
GO

-- ============================================================
-- Tabla: reservas (cliente reserva una clase + control asistencia)
-- ============================================================
CREATE TABLE reservas (
    id_reserva     INT IDENTITY(1,1) PRIMARY KEY,
    id_cliente     INT NOT NULL,
    id_clase       INT NOT NULL,
    fecha_reserva  DATE NOT NULL DEFAULT CAST(GETDATE() AS DATE),
    hora           TIME NOT NULL,
    asistio        BIT  NOT NULL DEFAULT 0,
    CONSTRAINT FK_Reservas_Cliente FOREIGN KEY (id_cliente) REFERENCES clientes(id_cliente),
    CONSTRAINT FK_Reservas_Clase   FOREIGN KEY (id_clase)   REFERENCES clases(id_clase)
);
GO

CREATE INDEX IX_Reservas_Cliente ON reservas(id_cliente);
CREATE INDEX IX_Reservas_Clase   ON reservas(id_clase);
GO

-- ============================================================
-- =============  DATOS DE PRUEBA (SEED)  =====================
-- ============================================================

-- ---------- Sucursales ----------
INSERT INTO sucursales (nombre, direccion, ciudad, telefono, email) VALUES
('PowerFit Sede Norte', 'Cra 15 # 100-25', 'Bogotá', '601 7001234', 'norte@powerfit.com'),
('PowerFit Sede Sur',   'Calle 40 Sur # 30-10', 'Bogotá', '601 7005678', 'sur@powerfit.com');
GO

-- ---------- Empleados (login) ----------
-- Hashes bcrypt generados con password_hash() / bcrypt cost 10. Son funcionales.
INSERT INTO empleados (nombre, apellido, cargo, salario, fecha_contratacion, id_sucursal, telefono, email, password_hash, rol, activo) VALUES
('Administrador', 'General',     'Administrador',  4500000, '2025-01-02', 1, '320 000 0000', 'admin@powerfit.com',     '$2b$10$r4SEvV0av8Tjh/as6zDZMOFkKSdB63xbg7SXcM41FTRyoGMw0D/Na', 'admin',         1),
('Recepción',     'Principal',   'Recepcionista',  1700000, '2025-02-01', 1, '320 000 0001', 'recepcion@powerfit.com', '$2b$10$sevasjO3zeZFI76IAKmczuEwCLVVPs7L41dVYp52.XBpAcv.yqQx.', 'recepcion',     1),
('Carlos',        'Ramírez',     'Entrenador',     2400000, '2025-03-10', 1, '320 111 2233', 'carlos@powerfit.com',    '$2b$10$KcHWdpEAQ8o1kPz2XH7vSuqXwaJhGRtuALqr9JWpGFlPGm4SLcpUW', 'entrenador',    1),
('Daniela',       'Torres',      'Entrenadora',    2400000, '2025-03-10', 2, '310 222 3344', 'daniela@powerfit.com',   '$2b$10$KcHWdpEAQ8o1kPz2XH7vSuqXwaJhGRtuALqr9JWpGFlPGm4SLcpUW', 'entrenador',    1),
('Laura',         'Quintero',    'Entrenadora',    2300000, '2025-04-05', 2, '315 333 4455', 'laura@powerfit.com',     '$2b$10$KcHWdpEAQ8o1kPz2XH7vSuqXwaJhGRtuALqr9JWpGFlPGm4SLcpUW', 'entrenador',    1),
('Andrés',        'Mejía',       'Nutricionista',  2600000, '2025-05-12', 1, '301 444 5566', 'andres@powerfit.com',    '$2b$10$KcHWdpEAQ8o1kPz2XH7vSuqXwaJhGRtuALqr9JWpGFlPGm4SLcpUW', 'nutricionista', 1),
('Valentina',     'Cruz',        'Nutricionista',  2600000, '2025-06-01', 2, '312 555 6677', 'valentina@powerfit.com', '$2b$10$KcHWdpEAQ8o1kPz2XH7vSuqXwaJhGRtuALqr9JWpGFlPGm4SLcpUW', 'nutricionista', 1);
GO

-- ---------- Entrenadores (perfil ligado a empleado) ----------
INSERT INTO entrenadores (id_empleado, especialidad, certificaciones) VALUES
(3, 'Powerlifting & Fuerza', 'NSCA-CSCS, Powerlifting L2'),
(4, 'Funcional & HIIT',      'CrossFit L2, ACE-PT'),
(5, 'Zumba & Pilates',       'Zumba Basic 1-2, Mat Pilates');
GO

-- ---------- Clientes ----------
INSERT INTO clientes (nombre, apellido, documento, fecha_nacimiento, genero, telefono, email, fecha_registro, estado) VALUES
('Juan Carlos', 'Pérez Gómez',   '1020304050', '1995-06-12', 'M', '3201234567', 'juan@mail.com',  '2026-01-10', 'activo'),
('María José',  'López Mendoza', '1030405060', '1998-11-23', 'F', '3112345678', 'maria@mail.com', '2026-02-05', 'activo'),
('Luis Andrés', 'Castro Silva',  '1040506070', '1990-03-15', 'M', '3013456789', 'luis@mail.com',  '2026-02-20', 'activo'),
('Ana Lucía',   'Vargas Ríos',   '1050607080', '2000-07-30', 'F', '3204567890', 'ana@mail.com',   '2026-03-01', 'activo'),
('Pedro',       'Salazar Mora',  '1060708090', '1985-09-09', 'M', '3155678901', 'pedro@mail.com', '2026-03-15', 'inactivo');
GO

-- ---------- Membresías (precios en pesos colombianos - COP) ----------
INSERT INTO membresias (nombre, descripcion, duracion_meses, precio, incluye_clases, activo) VALUES
('Plan Mensual',    'Membresía mensual con acceso completo a la sala.',      1,  80000.00,  0, 1),
('Plan Trimestral', '3 meses con clases grupales incluidas.',               3,  210000.00, 1, 1),
('Plan Semestral',  '6 meses + asesoría nutricional y clases incluidas.',   6,  390000.00, 1, 1),
('Plan Anual',      'Membresía anual con todos los beneficios y descuento.',12, 720000.00, 1, 1),
('Plan Estudiante', 'Plan mensual con descuento por carnet estudiantil.',   1,  60000.00,  0, 1),
('Plan Pareja',     'Plan mensual para 2 personas.',                        1,  140000.00, 1, 1);
GO

-- ---------- Inscripciones ----------
INSERT INTO inscripciones (id_cliente, id_membresia, id_sucursal, fecha_inicio, fecha_fin, estado) VALUES
(1, 1, 1, '2026-05-01', '2026-05-31', 'vigente'),
(2, 2, 2, '2026-04-15', '2026-07-14', 'vigente'),
(3, 1, 1, '2026-05-10', '2026-06-09', 'vigente'),
(4, 3, 2, '2026-01-01', '2026-06-30', 'vigente'),
(5, 5, 1, '2026-03-15', '2026-04-14', 'vencida');
GO

-- ---------- Pagos (montos en COP) ----------
INSERT INTO pagos (id_inscripcion, monto, fecha_pago, metodo_pago, referencia) VALUES
(1, 80000.00,  '2026-05-01', 'Efectivo',      'COMP-001'),
(2, 210000.00, '2026-04-15', 'Transferencia', 'COMP-002'),
(3, 80000.00,  '2026-05-10', 'Tarjeta',       'COMP-003'),
(4, 390000.00, '2026-01-01', 'Transferencia', 'COMP-004'),
(5, 60000.00,  '2026-03-15', 'Efectivo',      'COMP-005'),
(1, 80000.00,  '2026-05-15', 'Nequi',         'COMP-006'),
(3, 80000.00,  '2026-05-20', 'Daviplata',     'COMP-007');
GO

-- ---------- Clases ----------
INSERT INTO clases (nombre, descripcion, duracion_min, cupo_maximo, id_entrenador) VALUES
('Spinning Intenso',   'Cardio de alta intensidad sobre bicicleta estática.', 45, 20, 1),
('CrossFit WOD',       'Entrenamiento funcional del día, intensidad variable.',60, 15, 2),
('Zumba Fitness',      'Baile aeróbico para quemar calorías divirtiéndote.',  50, 25, 3),
('Yoga & Estiramiento','Movilidad, flexibilidad y relajación guiada.',        60, 18, 3),
('HIIT Express',       'Intervalos de alta intensidad de 30 minutos.',        30, 16, 2);
GO

-- ---------- Reservas ----------
INSERT INTO reservas (id_cliente, id_clase, fecha_reserva, hora, asistio) VALUES
(1, 1, '2026-05-28', '07:00', 1),
(2, 3, '2026-05-28', '18:00', 1),
(3, 2, '2026-05-29', '06:30', 1),
(4, 4, '2026-05-29', '17:00', 0),
(1, 5, '2026-05-30', '07:30', 0);
GO

PRINT 'Base de datos gym_powerfit creada correctamente con datos de prueba.';
GO
