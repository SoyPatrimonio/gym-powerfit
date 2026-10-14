/* ============================================================
   PowerFit Gym - JavaScript principal
   - Manejo de navbar, menú móvil
   - Formularios de login/registro/contacto con fetch() → PHP
   - Utilidades genéricas para los CRUDs del panel admin
   ============================================================ */

(() => {
    'use strict';

    // ----------------------------
    // NAVBAR - scroll + menú móvil
    // ----------------------------
    const navbar     = document.getElementById('navbar');
    const menuToggle = document.getElementById('menuToggle');
    const navLinks   = document.getElementById('navLinks');

    if (navbar) {
        window.addEventListener('scroll', () => {
            navbar.classList.toggle('scrolled', window.scrollY > 50);
        });
    }
    if (menuToggle && navLinks) {
        menuToggle.addEventListener('click', () => navLinks.classList.toggle('open'));
        navLinks.querySelectorAll('a').forEach(a =>
            a.addEventListener('click', () => navLinks.classList.remove('open'))
        );
    }

    // ----------------------------
    // Helper: petición JSON al backend
    // ----------------------------
    window.api = async function (url, datos = null, metodo = null) {
        const opciones = {
            method: metodo || (datos ? 'POST' : 'GET'),
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin'
        };
        if (datos) opciones.body = JSON.stringify(datos);
        try {
            const r = await fetch(url, opciones);
            return await r.json();
        } catch (err) {
            console.error('Error de red:', err);
            return { ok: false, mensaje: 'Error de conexión con el servidor' };
        }
    };

    // ----------------------------
    // Helper: validación básica de email
    // ----------------------------
    const validarEmail = (email) =>
        /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(email).trim());

    // ----------------------------
    // Mostrar mensaje en un <small class="msg">
    // ----------------------------
    const mostrarMsg = (el, texto, tipo) => {
        if (!el) return;
        el.textContent = texto;
        el.className = 'msg ' + tipo;
    };

    // ----------------------------
    // FORM: Login
    // ----------------------------
    // Portal de inicio según el rol
    const PORTAL_POR_ROL = {
        entrenador: 'views/entrenador.html',
        cliente:    'views/cliente.html',
        admin:      'views/dashboard.html',
        recepcion:  'views/dashboard.html'
    };

    // Autenticación demo (sin servidor): admin/recepción, cuentas
    // registradas, entrenadores (powerfit_personal) y clientes (powerfit_clientes).
    // Contraseñas demo: entrenadores → 'entrenador123', clientes → 'cliente123'.
    function autenticarDemo(email, password) {
        const e = email.toLowerCase();

        // 1) Administradores / recepción + cuentas registradas
        const DEMO_USERS = [
            { email: 'admin@powerfit.com',     password: 'admin123',     nombre: 'Administrador', rol: 'admin' },
            { email: 'recepcion@powerfit.com', password: 'recepcion123', nombre: 'Recepción',     rol: 'recepcion' }
        ];
        const cuentas = JSON.parse(localStorage.getItem('powerfit_cuentas') || '[]')
            .map(c => ({ ...c, rol: 'admin' }));
        const admin = [...DEMO_USERS, ...cuentas].find(u =>
            u.email.toLowerCase() === e && u.password === password);
        if (admin) return { rol: admin.rol, nombre: admin.nombre, email: admin.email };

        // 2) Entrenadores activos
        const personal = JSON.parse(localStorage.getItem('powerfit_personal') || '[]');
        const ent = personal.find(p => p.tipo === 'entrenador'
            && (p.estado || 'activo') === 'activo'
            && String(p.email || '').toLowerCase() === e);
        if (ent && password === 'entrenador123')
            return { rol: 'entrenador', id: ent.id, nombre: ent.nombre, email: ent.email };

        // 3) Clientes
        const clientes = JSON.parse(localStorage.getItem('powerfit_clientes') || '[]');
        const cli = clientes.find(c => String(c.email || '').toLowerCase() === e);
        if (cli && password === 'cliente123')
            return { rol: 'cliente', id: cli.id_cliente, nombre: (cli.nombre + ' ' + cli.apellido).trim(), email: cli.email };

        return null;
    }

    function iniciarSesion(u) {
        const rol = u.rol || 'admin';
        localStorage.setItem('powerfit_usuario', JSON.stringify({
            rol, id: u.id ?? null, nombre: u.nombre, email: u.email
        }));
        const dest = PORTAL_POR_ROL[rol] || 'views/dashboard.html';
        setTimeout(() => window.location.href = dest, 700);
    }

    const formLogin = document.getElementById('formLogin');
    if (formLogin) {
        formLogin.addEventListener('submit', async (e) => {
            e.preventDefault();
            const msg = document.getElementById('msgLogin');
            const email = formLogin.email.value.trim();
            const password = formLogin.password.value;

            if (!validarEmail(email))     return mostrarMsg(msg, 'Correo inválido', 'error');
            if (password.length < 6)      return mostrarMsg(msg, 'La contraseña debe tener al menos 6 caracteres', 'error');

            mostrarMsg(msg, 'Procesando...', 'ok');
            const res = await api('php/login.php', { email, password });

            // Backend real respondió con rol → úsalo
            if (res.ok && res.datos && res.datos.rol) {
                mostrarMsg(msg, 'Bienvenido, redirigiendo...', 'ok');
                return iniciarSesion({ rol: res.datos.rol, id: res.datos.id, nombre: res.datos.nombre, email });
            }

            // Demo / fallback local (sin servidor o credenciales no-empleado)
            const demo = autenticarDemo(email, password);
            if (demo) {
                mostrarMsg(msg, 'Bienvenido ' + demo.nombre + ', redirigiendo...', 'ok');
                return iniciarSesion(demo);
            }

            // Backend OK sin rol (compatibilidad) → entra como admin
            if (res.ok) {
                mostrarMsg(msg, 'Bienvenido, redirigiendo...', 'ok');
                return iniciarSesion({ rol: 'admin', nombre: 'Administrador', email });
            }

            mostrarMsg(msg, res.mensaje && !/conexi/i.test(res.mensaje) ? res.mensaje : 'Email o contraseña incorrectos', 'error');
        });
    }

    // ----------------------------
    // FORM: Registro
    // ----------------------------
    const formRegistro = document.getElementById('formRegistro');
    if (formRegistro) {
        formRegistro.addEventListener('submit', async (e) => {
            e.preventDefault();
            const msg = document.getElementById('msgRegistro');
            const nombre   = formRegistro.nombre.value.trim();
            const email    = formRegistro.email.value.trim();
            const password = formRegistro.password.value;

            if (nombre.length < 3)       return mostrarMsg(msg, 'Nombre demasiado corto', 'error');
            if (!validarEmail(email))    return mostrarMsg(msg, 'Correo inválido', 'error');
            if (password.length < 6)     return mostrarMsg(msg, 'Contraseña mínima 6 caracteres', 'error');

            mostrarMsg(msg, 'Creando cuenta...', 'ok');
            const res = await api('php/registro.php', { nombre, email, password });
            // Fallback: guardar en localStorage si no hay backend
            if (!res.ok && /conexi/i.test(res.mensaje || '')) {
                const cuentas = JSON.parse(localStorage.getItem('powerfit_cuentas') || '[]');
                const DEMO_EMAILS = ['admin@powerfit.com', 'recepcion@powerfit.com'];
                const existe = cuentas.some(c => c.email.toLowerCase() === email.toLowerCase())
                            || DEMO_EMAILS.includes(email.toLowerCase());
                if (existe) {
                    mostrarMsg(msg, 'Ese correo ya está registrado', 'error');
                    return;
                }
                cuentas.push({ nombre, email, password });
                localStorage.setItem('powerfit_cuentas', JSON.stringify(cuentas));
                mostrarMsg(msg, 'Cuenta creada. Ya puedes iniciar sesión.', 'ok');
                formRegistro.reset();
                return;
            }
            mostrarMsg(msg, res.mensaje, res.ok ? 'ok' : 'error');
            if (res.ok) formRegistro.reset();
        });
    }

    // ----------------------------
    // FORM: Contacto (solo simulación local)
    // ----------------------------
    const formContacto = document.getElementById('formContacto');
    if (formContacto) {
        formContacto.addEventListener('submit', (e) => {
            e.preventDefault();
            const msg = document.getElementById('msgContacto');
            mostrarMsg(msg, '¡Gracias! Te responderemos pronto.', 'ok');
            formContacto.reset();
        });
    }

    // ----------------------------
    // Filtro genérico para tablas en el panel admin
    // Llamada: filtrarTabla('inputBuscar', 'tablaClientes')
    // ----------------------------
    window.filtrarTabla = (inputId, tablaId) => {
        const input = document.getElementById(inputId);
        const tabla = document.getElementById(tablaId);
        if (!input || !tabla) return;
        input.addEventListener('input', () => {
            const q = input.value.toLowerCase();
            tabla.querySelectorAll('tbody tr').forEach(tr => {
                tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    };

    // ----------------------------
    // Modal genérico
    // ----------------------------
    window.abrirModal = (id) => document.getElementById(id)?.classList.add('open');
    window.cerrarModal = (id) => document.getElementById(id)?.classList.remove('open');

    // ----------------------------
    // Sesión + control de acceso por rol
    // ----------------------------
    window.sesionActual = () => JSON.parse(localStorage.getItem('powerfit_usuario') || 'null');

    // Portal correspondiente a un rol (rutas relativas a /views)
    const portalDeRol = (rol) =>
        rol === 'entrenador' ? 'entrenador.html'
      : rol === 'cliente'    ? 'cliente.html'
      : 'dashboard.html';

    // Exige que el usuario tenga uno de los roles permitidos.
    // Si no hay sesión → al login. Si tiene otro rol → a su propio portal.
    // Devuelve el usuario (con rol normalizado) o null si redirige.
    window.requireRol = (rolesPermitidos) => {
        const u = sesionActual();
        const roles = [].concat(rolesPermitidos);
        if (!u) { window.location.href = '../index.html'; return null; }
        const rol = u.rol || 'admin'; // sesiones antiguas sin rol = admin
        if (!roles.includes(rol)) { window.location.href = portalDeRol(rol); return null; }
        return { ...u, rol };
    };

    // Cierre al hacer click fuera del contenido
    document.addEventListener('click', (e) => {
        if (e.target.classList.contains('modal-bg')) {
            e.target.classList.remove('open');
        }
    });

    // ----------------------------
    // Formateo de moneda (Pesos Colombianos - COP)
    // ----------------------------
    const fmtCOP = new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    });
    window.formatoMoneda = (n) => fmtCOP.format(Number(n || 0));

    // ----------------------------
    // Formateo de fechas legibles
    // ----------------------------
    window.formatoFecha = (f) => {
        if (!f) return '-';
        const d = new Date(f);
        if (isNaN(d)) return String(f);
        return d.toLocaleDateString('es-CO', { year: 'numeric', month: 'short', day: '2-digit' });
    };

    // ----------------------------
    // Exportar tabla a CSV
    // Llamada: exportarCSV('tablaClientes', 'clientes.csv')
    // ----------------------------
    window.exportarCSV = (tablaId, nombreArchivo = 'datos.csv') => {
        const tabla = document.getElementById(tablaId);
        if (!tabla) return;
        const filas = [];
        // Cabecera (omitiendo la última columna "Acciones" si existe)
        const ths = [...tabla.querySelectorAll('thead th')]
            .map(th => th.textContent.trim())
            .filter(t => t.toLowerCase() !== 'acciones');
        filas.push(ths.map(s => `"${s.replace(/"/g, '""')}"`).join(','));
        // Cuerpo
        tabla.querySelectorAll('tbody tr').forEach(tr => {
            if (tr.style.display === 'none') return;
            const cols = [...tr.children].slice(0, ths.length)
                .map(td => `"${td.textContent.trim().replace(/"/g, '""')}"`);
            filas.push(cols.join(','));
        });
        const blob = new Blob(["﻿" + filas.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url; a.download = nombreArchivo;
        document.body.appendChild(a); a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    };

    // ----------------------------
    // Toast / notificación flotante
    // ----------------------------
    window.toast = (texto, tipo = 'ok') => {
        let cont = document.getElementById('toastContainer');
        if (!cont) {
            cont = document.createElement('div');
            cont.id = 'toastContainer';
            cont.className = 'toast-container';
            document.body.appendChild(cont);
        }
        const t = document.createElement('div');
        t.className = 'toast ' + tipo;
        t.innerHTML = `<i class="fa-solid ${tipo === 'ok' ? 'fa-circle-check' : tipo === 'error' ? 'fa-circle-xmark' : 'fa-circle-info'}"></i> <span>${texto}</span>`;
        cont.appendChild(t);
        setTimeout(() => t.classList.add('show'), 10);
        setTimeout(() => { t.classList.remove('show'); setTimeout(() => t.remove(), 300); }, 3000);
    };

    // ----------------------------
    // Toggle modo claro/oscuro (persistente en localStorage)
    // ----------------------------
    const aplicarTema = (tema) => {
        document.documentElement.setAttribute('data-tema', tema);
        localStorage.setItem('powerfit_tema', tema);
        document.querySelectorAll('.theme-toggle i').forEach(i => {
            i.className = tema === 'claro' ? 'fa-solid fa-moon' : 'fa-solid fa-sun';
        });
    };
    const temaGuardado = localStorage.getItem('powerfit_tema') || 'oscuro';
    aplicarTema(temaGuardado);
    document.querySelectorAll('.theme-toggle').forEach(btn => {
        btn.addEventListener('click', () => {
            const actual = document.documentElement.getAttribute('data-tema') || 'oscuro';
            aplicarTema(actual === 'oscuro' ? 'claro' : 'oscuro');
        });
    });

    // ----------------------------
    // Logout (botón con clase .logout-btn)
    // ----------------------------
    document.querySelectorAll('.logout-btn, a.logout').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            await api('../php/logout.php');
            localStorage.removeItem('powerfit_usuario');
            window.location.href = '../index.html';
        });
    });

    // ----------------------------
    // Mostrar nombre del usuario logueado
    // ----------------------------
    const userNameEl = document.getElementById('userName');
    if (userNameEl) {
        const u = JSON.parse(localStorage.getItem('powerfit_usuario') || 'null');
        if (u && u.nombre) userNameEl.textContent = u.nombre;
    }

    // ----------------------------
    // demoStore: helper genérico para CRUD en localStorage
    // ----------------------------
    window.demoStore = (key, defaultData) => ({
        all: () => JSON.parse(localStorage.getItem(key) || 'null') || (typeof defaultData === 'function' ? defaultData() : defaultData),
        save: (arr) => localStorage.setItem(key, JSON.stringify(arr)),
        nextId: (campoId) => {
            const arr = JSON.parse(localStorage.getItem(key) || 'null') || (typeof defaultData === 'function' ? defaultData() : defaultData);
            return arr.length ? Math.max(...arr.map(x => Number(x[campoId]) || 0)) + 1 : 1;
        }
    });

    // ----------------------------
    // Seed de datos demo (localStorage)
    // Garantiza que TODAS las vistas del panel tengan datos consistentes
    // aunque el usuario entre directo a Asignaciones sin pasar antes por
    // Clientes / Personal / Rutinas. Sin esto, los desplegables de
    // "Asignar entrenador" y "Asignar rutina" salen vacíos.
    // Cada clave se siembra sólo si aún no existe (no pisa ediciones).
    // ----------------------------
    const SEED_DEMO = {
        powerfit_personal: [
            { id:1, nombre:'Carlos Ramírez',  tipo:'entrenador',    especialidad:'Powerlifting & Fuerza', telefono:'320 111 2233', email:'carlos@powerfit.com',   certificaciones:'NSCA-CSCS, Powerlifting L2',                horario:'Lun-Vie 6am-2pm', estado:'activo' },
            { id:2, nombre:'Daniela Torres',  tipo:'entrenador',    especialidad:'Funcional & HIIT',      telefono:'310 222 3344', email:'daniela@powerfit.com',  certificaciones:'CrossFit L2, ACE-PT',                       horario:'Lun-Sáb 2pm-9pm', estado:'activo' },
            { id:3, nombre:'Laura Quintero',  tipo:'entrenador',    especialidad:'Zumba & Pilates',       telefono:'315 333 4455', email:'laura@powerfit.com',    certificaciones:'Zumba Basic 1-2, Mat Pilates',              horario:'Mar-Sáb 4pm-9pm', estado:'activo' },
            { id:4, nombre:'Andrés Mejía',    tipo:'nutricionista', especialidad:'Nutrición Deportiva',   telefono:'301 444 5566', email:'andres@powerfit.com',   certificaciones:'Nutricionista titulado, ISSN',              horario:'Lun-Vie 9am-5pm', estado:'activo' },
            { id:5, nombre:'Valentina Cruz',  tipo:'nutricionista', especialidad:'Pérdida de grasa',      telefono:'312 555 6677', email:'valentina@powerfit.com',certificaciones:'Nutrición clínica, Especialista en obesidad',horario:'Lun-Vie 2pm-8pm', estado:'activo' }
        ],
        powerfit_clientes: [
            { id_cliente:1, nombre:'Juan Carlos', apellido:'Pérez Gómez',   documento:'1020304050', telefono:'3201234567', email:'juan@mail.com',  fecha_nacimiento:'1995-06-12', genero:'M', fecha_registro:'2026-01-10', entrenador_id:1, nutricionista_id:4 },
            { id_cliente:2, nombre:'María José',  apellido:'López Mendoza', documento:'1030405060', telefono:'3112345678', email:'maria@mail.com', fecha_nacimiento:'1998-11-23', genero:'F', fecha_registro:'2026-02-05', entrenador_id:2, nutricionista_id:5 },
            { id_cliente:3, nombre:'Luis Andrés', apellido:'Castro Silva',  documento:'1040506070', telefono:'3013456789', email:'luis@mail.com',  fecha_nacimiento:'1990-03-15', genero:'M', fecha_registro:'2026-02-20', entrenador_id:1, nutricionista_id:null },
            { id_cliente:4, nombre:'Ana Lucía',   apellido:'Vargas Ríos',   documento:'1050607080', telefono:'3204567890', email:'ana@mail.com',   fecha_nacimiento:'2000-07-30', genero:'F', fecha_registro:'2026-03-01', entrenador_id:3, nutricionista_id:4 },
            { id_cliente:5, nombre:'Pedro',       apellido:'Salazar Mora',  documento:'1060708090', telefono:'3155678901', email:'pedro@mail.com', fecha_nacimiento:'1985-09-09', genero:'M', fecha_registro:'2026-03-15', entrenador_id:null, nutricionista_id:null }
        ],
        powerfit_rutinas: [
            { id_rutina:1, nombre:'Full Body Principiante', nivel:'Principiante', objetivo:'Tonificar',           descripcion:'3 días/semana. Trabaja todos los grupos musculares con peso ligero.' },
            { id_rutina:2, nombre:'Hipertrofia Push/Pull',  nivel:'Intermedio',   objetivo:'Ganar masa muscular', descripcion:'5 días/semana. División push-pull-legs con foco en volumen.' },
            { id_rutina:3, nombre:'Cardio HIIT',            nivel:'Intermedio',   objetivo:'Perder grasa',        descripcion:'Sesiones de 30 min de intervalos de alta intensidad.' },
            { id_rutina:4, nombre:'Powerlifting Avanzado',  nivel:'Avanzado',     objetivo:'Ganar fuerza',        descripcion:'Press banca, sentadilla y peso muerto con cargas máximas.' },
            { id_rutina:5, nombre:'Funcional Mujer',        nivel:'Principiante', objetivo:'Tonificar',           descripcion:'Rutina funcional enfocada en glúteos y piernas.' }
        ],
        powerfit_planes_nutri: [
            { id:1, nombre:'Cutting Hombre 80kg', objetivo:'Perder grasa',        calorias:2000, proteina:180, carbohidratos:180, grasas:55, comidas:'Desayuno: claras + avena + frutos rojos\nAlmuerzo: pollo + arroz + ensalada\nCena: pescado + brócoli', recomendaciones:'2.5 L agua/día, evitar azúcares simples, déficit de 500 kcal.' },
            { id:2, nombre:'Bulking Limpio',      objetivo:'Ganar masa muscular', calorias:3200, proteina:200, carbohidratos:400, grasas:90, comidas:'Desayuno: 4 huevos + 2 panes + plátano\nAlmuerzo: 200g carne + papa + ensalada\nCena: salmón + quinoa + aguacate', recomendaciones:'Surplus de 400 kcal, creatina 5g/día.' },
            { id:3, nombre:'Mantenimiento Mujer', objetivo:'Mantener',            calorias:1800, proteina:130, carbohidratos:200, grasas:60, comidas:'Desayuno: tostadas + huevo + fruta\nAlmuerzo: pollo + quinoa + verduras\nCena: omelette + ensalada', recomendaciones:'Balance macros, 5 comidas pequeñas.' },
            { id:4, nombre:'Definición Avanzada', objetivo:'Definición',          calorias:1700, proteina:200, carbohidratos:130, grasas:50, comidas:'Desayuno: claras + avena\nAlmuerzo: pescado + verduras\nCena: tortilla francesa + espinacas', recomendaciones:'Sin lácteos, sodio bajo. Cardio en ayunas opcional.' },
            { id:5, nombre:'Vegetariano Volumen', objetivo:'Ganar masa muscular', calorias:2800, proteina:160, carbohidratos:380, grasas:85, comidas:'Desayuno: tofu scramble + pan integral\nAlmuerzo: lentejas + arroz + aguacate\nCena: tempeh + quinoa + verduras', recomendaciones:'Vitamina B12, proteína vegetal combinada.' }
        ],
        powerfit_rutinas_asig: [
            { id:1, cliente_id:1, rutina_id:4, entrenador_id:1, fecha_inicio:'2026-05-01', fecha_fin:'2026-07-31', estado:'activa', notas:'Enfoque en fuerza máxima. Revisar técnica de peso muerto.' },
            { id:2, cliente_id:2, rutina_id:2, entrenador_id:2, fecha_inicio:'2026-04-15', fecha_fin:'',           estado:'activa', notas:'Volumen progresivo, sumar 2.5 kg semanales.' },
            { id:3, cliente_id:4, rutina_id:5, entrenador_id:3, fecha_inicio:'2026-03-01', fecha_fin:'',           estado:'activa', notas:'Foco en glúteos y movilidad.' }
        ],
        powerfit_planes_asig: [
            { id:1, cliente_id:1, plan_id:1, nutricionista_id:4, fecha_inicio:'2026-05-01', estado:'activo', notas:'Déficit moderado, revisar peso cada 2 semanas.' },
            { id:2, cliente_id:2, plan_id:2, nutricionista_id:5, fecha_inicio:'2026-04-15', estado:'activo', notas:'Superávit limpio, priorizar proteína.' }
        ]
    };
    Object.keys(SEED_DEMO).forEach((key) => {
        if (localStorage.getItem(key) === null) {
            localStorage.setItem(key, JSON.stringify(SEED_DEMO[key]));
        }
    });

})();
