# 📚 Biblioteca Invaluable · Panel de Administración (Grupo 5)

Panel del bibliotecario (plataforma **web**) con arquitectura **MVC** en **Node.js**:

- **Frontend:** HTML + CSS (carpeta `css/`) + JavaScript con ES Modules (`fetch()` a la API).
- **Backend:** **Node.js + Express 5** (`backend/`), con la estructura `routes ➔ controllers ➔ models`.
- **Base de datos:** MySQL en **Aiven**, conectada con **mysql2** (pool de conexiones, sentencias preparadas con `?` y SSL).
- **Sesión:** JWT (`jsonwebtoken`) y contraseñas con **bcrypt** (`bcryptjs`).

```text
[ Frontend ]  fetch()  ─►  [ Node.js: Router ➔ Controller ➔ Model ]  ─►  mysql2  ─►  [ MySQL Aiven ]
localhost:3001                     backend/ (puerto 3001)                  src/database/db.js
```

> La app móvil (PWA) usa **la misma base**. Los dos backends son compatibles:
> las contraseñas (bcrypt) y los tokens (JWT) sirven en ambos si comparten `JWT_SECRET`.

---

## 📂 Estructura

```text
.
├── package.json                  scripts npm y dependencias (Node.js)
├── frontend/                     (consume la API del backend)
│   ├── login.html, index.html, usuarios.html, libros.html, configuracion.html  (admin)
│   ├── prestamos.html, pedidos.html, historial.html, crear-pedido.html         (préstamos)
│   ├── catalogo.html, inicio-usuario.html                                       (alumnos y docentes)
│   ├── css/                      estilo-general.css, componentes.css, prestamos.css, ...
│   └── js/                       config.js (URL de la API), api.js (fetch), ...
│                                 sesion.js (sesión + campanita), notificaciones.js,
│                                 prestamos.js, pedidos.js, historial.js, crear-pedido.js, pedidos-ui.js
│
└── backend/                      MVC: index.js ➔ Router ➔ Controller ➔ Model ➔ MySQL
    ├── index.js                  punto de entrada: CORS, JSON, frontend estático, rutas, 404 y errores
    ├── .env.example              plantilla (el .env real NO se sube)
    ├── src/
    │   ├── config/               env.js (.env con fail-fast), constants.js (reglas del negocio), config.js
    │   ├── database/db.js        pool mysql2 → MySQL Aiven (SSL) + conTransaccion()
    │   ├── models/               MODEL: solo SQL con comodines "?", devuelve datos
    │   │   ├── usuario.models.js, busqueda.models.js, sancion.models.js, perfil.models.js
    │   │   ├── usuario.sql.js     pedazos de SQL compartidos (atraso, condición, columnas)
    │   │   ├── libro.models.js (catálogo), inventario.models.js (alta, cambios, stock, baja), ejemplar.models.js
    │   │   ├── errores.models.js  ErrorNegocio: el Modelo dice QUÉ pasó, el Controlador elige el código HTTP
    │   │   ├── dashboard.models.js, curso.models.js, prestamo.models.js, pedido.models.js
    │   │   └── notificacion.models.js  actividad reciente para la campanita (UNION de tablas reales)
    │   ├── controllers/          CONTROLLER: reglas de negocio y respuesta HTTP
    │   │   ├── auth, perfil, usuarios, sanciones, dashboard, cursos, prestamos, libros, pedidos
    │   │   ├── notificaciones.controllers.js
    │   │   └── respuestas.js      error500, responder409 y responderError (ErrorNegocio → 404 / 409)
    │   ├── routes/               ROUTER: URL + verbo HTTP → middlewares → controlador
    │   │   └── auth, perfil, dashboard, usuarios, cursos, prestamos, libros, pedidos, notificaciones
    │   └── middlewares/          auth (token), validaciones (URL), formularios (body), predicados,
    │                             libros, pedidos, límite de intentos
    └── scripts/                  schema.sql, datos-prueba-grupo5.sql, inicializar-bd.js,
                                  crear-admin-inicial.js, generar-hash.js, probar-conexion.js
```

### ¿Cómo circula una petición?

```text
GET /api/usuarios/5
  ➔ routes/usuarios.routes.js   (verificarAdmin + validarID)
  ➔ controllers/usuarios.controllers.js
  ➔ models/usuario.models.js    (SELECT ... WHERE u.id = ?)
  ➔ database/db.js              (pool mysql2)
  ➔ MySQL Aiven
  ➔ res.json({ payload: ... })
```

Las URLs, los JSON de respuesta y los códigos HTTP del panel original se mantuvieron **exactamente iguales**, por eso las pantallas existentes siguen funcionando sin cambios.

### Endpoints

> `/api/auth/perfil` y `/api/perfil/*` aceptan cualquier rol (`verificarSesion`). El resto es solo para el administrador (`verificarAdmin` → 403 si entra un alumno o docente). Un alumno/docente suspendido puede iniciar sesión para ver su estado; de baja no entra nadie.

| Método | Ruta | Qué hace |
|---|---|---|
| `POST` | `/api/auth/login` | Body `{ "dni", "clave" }` → `{ payload: { token, usuario } }`. 5 intentos fallidos bloquean la IP 5 minutos |
| `GET` | `/api/auth/perfil` | Quién está logueado → `{ payload: { id, nombre, rol } }` |
| `GET` | `/api/perfil` | Mis datos completos (pantalla Configuración) |
| `PUT` | `/api/perfil` | Body `{ nombre_completo, correo, telefono, direccion }` |
| `PUT` | `/api/perfil/clave` | Body `{ clave_actual, clave_nueva, confirmacion }` |
| `GET` | `/api/perfil/historial` | Mi cuenta (alumno/docente): datos, préstamos con `dias_restantes`, reservas y sanciones |
| `GET` | `/api/dashboard` | `{ payload: { libros_total, prestamos_activos, alumnos_morosos } }` |
| `GET` | `/api/prestamos?limite=5` | Últimos préstamos (en curso, atrasados y devueltos) |
| `GET` | `/api/notificaciones?limite=40` | Actividad reciente de la campanita: altas, pedidos, entregas, devoluciones, cancelaciones y sanciones (admin) |
| **Grupo 4 (módulo de pedidos integrado)** | | |
| `GET` | `/api/pedidos?estado=&texto=&limite=` | Listado de pedidos con filtros. `estado`: `pendiente`, `prestado`, `vencido`, `devuelto`, `cancelado` o `todos` |
| `GET` | `/api/pedidos/resumen` | `{ total, pendientes, prestados, atrasados, devueltos, cancelados }` para el panel |
| `GET` | `/api/pedidos/formulario` | Usuarios habilitados y libros con stock reservable (alta de pedido) |
| `POST` | `/api/pedidos` | Alta de una reserva. Body `{ id_usuario, id_libro, cantidad, fecha_pedido, fecha_devolucion }` |
| `PATCH` | `/api/pedidos/:id/estado` | Body `{ "estado": "prestado" \| "devuelto" \| "cancelado" }`: entregar, devolver o rechazar |
| **Grupo 6 (integrado)** | | |
| `GET` | `/api/libros?texto=&categoria=&disponibilidad=` | Catálogo con stock (cualquier rol). `disponibilidad`: `disponible` o `agotado` |
| `GET` | `/api/libros/:id` | Un libro con autores, editorial, categoría y stock (cualquier rol) |
| `GET` | `/api/libros/categorias` | Categorías para los selectores (cualquier rol) |
| `GET` | `/api/libros/resumen` | Títulos, ejemplares, disponibles y fuera de servicio (admin) |
| `POST` | `/api/libros` | Alta (admin). Body `{ titulo, autores, editorial, isbn, categoria_id, anio, cantidad, portada_url? }`; crea los ejemplares `INV-<id>-001…` |
| `PUT` | `/api/libros/:id` | Modificación (admin), mismo body sin `cantidad` |
| `PATCH` | `/api/libros/:id/stock` | Body `{ cambio }`: `3` suma ejemplares, `-2` da de baja ejemplares del estante (no los reservados) |
| `DELETE` | `/api/libros/:id` | Baja lógica (admin): no se puede si tiene préstamos o reservas sin cerrar |
| **Grupo 5** | | |
| `GET` | `/api/usuarios?nombre=&dni=&curso=&filtro=&pagina=` | Búsqueda avanzada + listado (10 por página) y cantidad por filtro |
| `GET` | `/api/usuarios/dni/:dni` | Para el Grupo 4 → `{ payload: { id, nombre_completo, curso, rol, estado, puede_pedir } }` |
| `GET` | `/api/usuarios/:id` | Los 6 datos del usuario, tipo y estado |
| `GET` | `/api/usuarios/:id/historial` | Historial activo: entregas, devoluciones, atrasos y sanciones |
| `POST` | `/api/usuarios` | Alta. Body `{ nombre_completo, dni, rol, curso, correo, telefono, direccion, clave }` |
| `PUT` | `/api/usuarios/:id` | Modificación (mismo body; `clave` vacía = no se cambia) |
| `PATCH` | `/api/usuarios/:id/estado` | Body `{ "estado": "suspendido" \| "habilitado" \| "baja", "motivo" }` |
| `GET` | `/api/cursos` | Cursos para autocompletar |

`filtro` puede ser `todos`, `alumnos`, `docentes`, `administradores`, `morosos`, `suspendidos` o `bajas`.
Si un dato está mal, la API responde `400` con `{ error, campos: { dni: "..." } }` y la pantalla marca ese campo.
Todas menos el login piden el header `Authorization: Bearer <token>`.

### Módulo de préstamos (Grupo 4 integrado)

Las páginas del módulo viven en el frontend y consumen `/api/pedidos` sobre las tablas reales
(`pedidos`, `pedido_ejemplares`, `pedido_historial`, `ejemplares`, `usuarios`, `libros`):

| Página | Qué hace |
|---|---|
| `prestamos.html` | Resumen de pedidos, préstamos en curso, atrasos y últimos movimientos |
| `crear-pedido.html` | Alta de una reserva: buscador de usuarios y del catálogo, cantidad y fechas |
| `pedidos.html` | Listado con filtros; botones para entregar, rechazar o registrar la devolución |
| `historial.html` | Historial completo con fechas de pedido, entrega, devolución estimada y real |

Flujo de estados: la reserva nace **pendiente**; “Entregar” la pasa a **prestado**
(asigna ejemplares físicos); “Registrar devolución” la pasa a **devuelto** (los ejemplares vuelven
al estante); “Rechazar” la deja **cancelado**. El estado **vencido** no se guarda: se calcula al
listar cuando la fecha estimada ya pasó y el préstamo sigue en curso.

### Campanita de actividad (reemplaza a Reportes)

La campanita del encabezado (`frontend/js/notificaciones.js`) muestra la actividad reciente del
panel: usuarios registrados, pedidos/reservas, préstamos entregados, devoluciones, cancelaciones
y sanciones. Se arma en el backend con las tablas reales (`usuarios`, `pedidos`,
`pedido_historial`, `sanciones`) mediante `GET /api/notificaciones`; no duplica datos ni requiere
una tabla nueva. Solo la ve el administrador y el estado leído/no leído se guarda en el
`localStorage` del navegador.

---

## 🚀 Cómo levantarlo

### 1. Requisitos

- **Node.js 18 o más nuevo** (recomendado 20/22 LTS). Verificá con:
  ```
  node -v
  npm -v
  ```
- No hace falta instalar ningún servidor web adicional (Apache, IIS, etc.): todo corre con Node.

> En Windows, si PowerShell no reconoce `npm`, usá **`npm.cmd`** (por ejemplo `npm.cmd install`).
> Si `node` tampoco se reconoce, instalá Node desde <https://nodejs.org> y volvé a abrir la terminal.

### 2. Instalar dependencias (una sola vez)

Desde la **raíz del proyecto** (`gestion-biblioteca-eestn5-web`):

```
npm.cmd install
```

Esto descarga Express, mysql2, bcryptjs, jsonwebtoken, cors, dotenv y nodemon en `node_modules/`.

### 3. Crear `backend/.env`

```
copy backend\.env.example backend\.env
```

Completá los datos de Aiven en `backend/.env`:

| Variable | Qué va |
|---|---|
| `PORT` | `3001` (el panel se abre en ese puerto) |
| `JWT_SECRET` | Un texto largo y secreto. Si la app móvil usa el mismo, los tokens sirven en las dos |
| `DB_HOST` | Host de Aiven (`...aivencloud.com`) |
| `DB_PORT` | Puerto de Aiven (por ejemplo `27892`) |
| `DB_USER` | Usuario (`avnadmin`) |
| `DB_PASSWORD` | Contraseña de Aiven |
| `DB_NAME` | Nombre de la base (`defaultdb`) |
| `DB_SSL` | `true` (Aiven exige SSL) |
| `DB_CA_PATH` | `ca.pem` (el certificado tiene que estar en `backend/`) |
| `DB_ZONA_HORARIA` | Opcional, por defecto `-03:00` |

Si falta alguna variable obligatoria, el servidor **no arranca** y te dice cuál falta.

### 4. Probar la conexión y preparar la base

La base ya existe, pero estos comandos sirven para verificar y completar lo que falte:

```
npm.cmd run db:test     →  [OK] MySQL 8.0.x conectado a la base "defaultdb" (SSL: ...)
npm.cmd run db:init     →  crea tablas, vistas, roles y categorías (no borra datos)
npm.cmd run db:admin    →  crea el PRIMER administrador (solo si todavía no hay uno)
```

`db:init` es idempotente: se puede correr más de una vez sin perder información.

### 5. Encender el servidor

Desde la **raíz del proyecto**:

```
npm.cmd run dev
```

Vas a ver:

```
[OK] Servidor activo en http://localhost:3001
[OK] Base de datos conectada
```

Abrí **<http://localhost:3001>** y entrá con el DNI y la contraseña del administrador.

La dirección de la API (`frontend/js/config.js`) usa el **mismo origen** cuando el frontend lo
sirve este backend. Por eso también funciona desde un celular u otra PC de la LAN entrando a
`http://IP-DE-LA-PC:3001`. Si abrís el frontend con Live Server (por ejemplo `:5500`), sigue
apuntando a `http://localhost:3001/api`.

> `npm run dev` usa **nodemon**: si cambiás un archivo del backend, el servidor se reinicia solo.
> Para correrlo sin reinicio automático: `npm.cmd start`.

**Datos de prueba (opcional):** `backend/scripts/datos-prueba-grupo5.sql` carga usuarios de ejemplo
(DNI 99010001 a 99010034, clave `prueba123`). Se ejecuta desde Database Client de VS Code o HeidiSQL.

---

## 🧰 Scripts de npm

| Comando | Qué hace |
|---|---|
| `npm run dev` | Levanta el servidor con nodemon en el puerto 3001 |
| `npm start` | Levanta el servidor sin reinicio automático |
| `npm run db:test` | Prueba la conexión a MySQL y muestra la versión y el SSL |
| `npm run db:init` | Crea tablas, vistas, roles y categorías (idempotente) |
| `npm run db:admin` | Crea el primer administrador de forma interactiva |
| `npm run hash -- clave123` | Genera un hash bcrypt para insertar usuarios a mano |

---

## 🕒 Zona horaria

Cada conexión nueva del pool ejecuta `SET time_zone = '-03:00'`, así `NOW()` y `CURRENT_DATE` dan la hora de Argentina.
Se cambia con `DB_ZONA_HORARIA` en `backend/.env`.

## 👤 Crear otro administrador a mano

```
npm.cmd run hash -- claveNueva123
```

```sql
INSERT INTO usuarios (dni, nombre_completo, curso_id, correo, telefono, direccion, password_hash, rol_id)
VALUES ('12345678', 'Nombre Apellido', NULL, 'mail@eest5.edu.ar', '11 0000-0000', 'Dirección', '<hash>', 3);
```

---

## 🧯 Problemas comunes

| Mensaje | Solución |
|---|---|
| `'node' no se reconoce como un comando` | Instalá Node.js desde <https://nodejs.org> y abrí una terminal nueva. |
| `'npm' no se reconoce como un comando` | En Windows usá `npm.cmd`, o agregá la carpeta de Node al PATH. |
| `npm.ps1 cannot be loaded` / error de ExecutionPolicy | Usá `npm.cmd` o ejecutá `Set-ExecutionPolicy -Scope CurrentUser RemoteSigned`. |
| `Cannot find package 'express'` | Falta `npm install` en la raíz del proyecto. |
| `Faltan variables en backend/.env` | Copiá `backend/.env.example` a `backend/.env` y completá la variable que nombra el mensaje. |
| `Access denied for user` | Revisá `DB_USER` / `DB_PASSWORD` en `backend/.env` (y que `DB_SSL` no esté en `false`: Aiven exige SSL). |
| `Connection timed out` / `getaddrinfo failed` | Revisá `DB_HOST` / `DB_PORT` y que el servicio de Aiven esté en **Running**. |
| Error de certificado / SSL | Revisá que `ca.pem` sea el de Aiven y esté en `backend/` (`DB_CA_PATH=ca.pem`). |
| `EADDRINUSE: address already in use :::3001` | Ya hay otro proceso en el puerto 3001: cerralo o cambiá `PORT` en `backend/.env`. |
| En la página: "No hay conexión con el servidor" | Falta la terminal con `npm run dev` (la API tiene que estar en `http://localhost:3001`). |
