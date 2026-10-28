# 📚 Biblioteca Invaluable · Panel de Administración (Grupo 5)

Panel del bibliotecario (plataforma **web**) con arquitectura **MVC**:

- **Frontend:** HTML + CSS (carpeta `css/`) + JS con ES Modules (`fetch()` a la API).
- **Backend:** **PHP** con la misma estructura MVC de la clase 06 (`routes ➔ controllers ➔ models`).
- **Base de datos:** MySQL en Aiven, conectada desde PHP con **PDO** (sentencias preparadas con `?` + SSL).

```text
[ Frontend ]  fetch()  ─►  [ PHP: Router ➔ Controller ➔ Model ]  ─►  PDO  ─►  [ MySQL Aiven ]
 localhost:3001 / Live Server       backend/ (puerto 3001)          src/database/db.php
```

> La app móvil (PWA) sigue en Node.js y usa **la misma base**. Los dos backends son compatibles:
> las contraseñas (bcrypt) y los tokens de sesión (JWT) sirven en ambos si comparten `JWT_SECRET`.

---

## 📂 Estructura

```text
.
├── frontend/                     (sin cambios: consume la API igual que antes)
│   ├── login.html, index.html, usuarios.html, libros.html, configuracion.html  (admin)
│   ├── catalogo.html, inicio-usuario.html                                       (alumnos y docentes)
│   ├── css/
│   └── js/                       config.js (URL de la API), api.js (fetch), ...
│
└── backend/                      MVC: index.php ➔ Router ➔ Controller ➔ Model ➔ MySQL
    ├── index.php                 punto de entrada: CORS, cuerpo JSON, monta las rutas, 404 y errores
    ├── .env.example              plantilla (el .env real NO se sube)
    ├── .htaccess                 solo si se usa Apache/XAMPP (protege .env, src/ y scripts/)
    ├── src/
    │   ├── config/               env.php (.env con fail-fast), constants.php (reglas del negocio), config.php
    │   ├── database/db.php       PDO → MySQL Aiven (SSL) + consultar(), ejecutar() y conTransaccion()
    │   ├── models/               MODEL: solo SQL con comodines "?", devuelve datos
    │   │   ├── usuario.models.php, busqueda.models.php, sancion.models.php, perfil.models.php
    │   │   ├── usuario.sql.php    pedazos de SQL compartidos (atraso, condición, columnas)
    │   │   ├── libro.models.php (catálogo), inventario.models.php (alta, cambios, stock, baja), ejemplar.models.php
    │   │   ├── errores.models.php ErrorNegocio: el Modelo dice QUÉ pasó, el Controlador elige el código HTTP
    │   │   └── dashboard.models.php, curso.models.php, prestamo.models.php
    │   ├── controllers/          CONTROLLER: reglas de negocio y respuesta HTTP
    │   │   ├── auth, perfil, usuarios, sanciones, dashboard, cursos, prestamos, libros
    │   │   └── respuestas.php    error500, responder409 y responderError (ErrorNegocio → 404 / 409)
    │   ├── routes/               ROUTER: URL + verbo HTTP → middlewares → controlador
    │   │   └── Router.php        el "mini Express": get/post/put/patch/delete, usar() y montar()
    │   └── middlewares/          auth (token), jwt, validaciones (URL), formularios (body), predicados, libros, límite de intentos
    └── scripts/                  schema.sql, datos-prueba-grupo5.sql, inicializar-bd.php, crear-admin-inicial.php,
                                  generar-hash.php, probar-conexion.php
```

### ¿Qué cambió respecto de Express?

| Express (Node) | PHP |
|---|---|
| `index.js` + `app.listen(3001)` | `index.php` + `php -S localhost:3001 -t ../frontend index.php` |
| `express.Router()`, `router.get("/:id", mw, ctrl)` | `new Router()`, `$router->get('/:id', mw(...), ctrl(...))` |
| `req`, `res.status(404).json({...})` | `$req`, `responder(404, [...])` |
| `next()` | si el middleware no llama a `responder()`, sigue el próximo paso |
| `mysql2` + pool, `connection.query(sql, [valores])` | `PDO`, `consultar(sql, [valores])` / `ejecutar(sql, [valores])` |
| `dotenv` | `src/config/env.php` lee el mismo `.env` |
| `bcryptjs`, `jsonwebtoken` | `password_hash()` / `password_verify()` y `src/middlewares/jwt.php` |

Las URLs, los JSON de respuesta y los códigos HTTP son **exactamente los mismos**, por eso el frontend no se tocó.

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

---

## 🚀 Cómo levantarlo

### 1. Requisitos
**PHP 8.1 o más nuevo** con las extensiones `pdo_mysql`, `openssl` y `mbstring` (XAMPP ya las trae).
Para comprobarlo: `php -v` y `php -m`. En Windows con XAMPP, si `php` no se reconoce, usá `C:\xampp\php\php.exe`
en lugar de `php` (o agregá `C:\xampp\php` al PATH). No hace falta `npm install`.

### 2. Crear `backend/.env`
```
cd backend
copy .env.example .env
```
Completá los datos de Aiven, guardá el `ca.pem` de Aiven dentro de `backend/` e inventá un texto largo para `JWT_SECRET`
(si la app móvil en Node usa el mismo, los tokens sirven en las dos).
Si falta alguna variable obligatoria, la API responde con el nombre de la que falta (fail-fast).

### 3. Probar la conexión y preparar la base (solo la primera vez)
```
php scripts/probar-conexion.php        →  ✅ MySQL 8.0.x conectado a la base "defaultdb" (SSL: ...)
php scripts/inicializar-bd.php         →  crea tablas, vistas, roles y categorías (no borra nada)
php scripts/crear-admin-inicial.php    →  pide los datos del primer administrador (la clave no se ve)
```

### 4. Encender el servidor (desde `backend/`)
```
php -S localhost:3001 -t ../frontend index.php
```
Abrí **http://localhost:3001**. También funciona con **Live Server** abriendo `frontend/login.html`
(la API sigue en `http://localhost:3001/api`, como dice `frontend/js/config.js`).

> **Con Apache/XAMPP (opcional):** si ponés el proyecto en `htdocs`, la API queda en
> `http://localhost/<carpeta>/backend/api` y hay que cambiar `API_URL` en `frontend/js/config.js`.
> El `.htaccess` ya bloquea el acceso al `.env`, al certificado y a las carpetas internas.

**Datos de prueba (opcional):** `backend/scripts/datos-prueba-grupo5.sql` carga usuarios de ejemplo
(DNI 99010001 a 99010034, clave `prueba123`). Se ejecuta desde Database Client de VS Code.

---

## 🕒 Zona horaria

Cada conexión arranca con `SET time_zone = '-03:00'`, así `NOW()` y `CURRENT_DATE` dan la hora de Argentina.
Se cambia con `DB_ZONA_HORARIA` en `backend/.env`.

## 👤 Crear otro administrador
```
php scripts/generar-hash.php claveNueva123
```
```sql
INSERT INTO usuarios (dni, nombre_completo, curso_id, correo, telefono, direccion, password_hash, rol_id)
VALUES ('12345678', 'Nombre Apellido', NULL, 'mail@eest5.edu.ar', '11 0000-0000', 'Dirección', '<hash>', 3);
```

---

## 🧯 Problemas comunes

| Mensaje | Solución |
|---|---|
| `'php' no se reconoce como un comando` | Usá `C:\xampp\php\php.exe` o agregá `C:\xampp\php` al PATH. |
| `could not find driver` | Falta `pdo_mysql`: en `php.ini` sacale el `;` a `extension=pdo_mysql` (y a `openssl`, `mbstring`). |
| `Access denied for user` | Revisá `DB_USER` / `DB_PASSWORD` en `backend/.env` (y que `DB_SSL` no esté en `false`: Aiven exige SSL). |
| `Connection timed out` / `getaddrinfo failed` | Revisá `DB_HOST` / `DB_PORT` y que el servicio de Aiven esté en **Running**. |
| Error de certificado / SSL | Revisá que `ca.pem` sea el de Aiven y esté en `backend/` (`DB_CA_PATH=ca.pem`). |
| `Faltan variables en backend/.env` | Completá la variable que nombra el mensaje. |
| En la página: "No hay conexión con el servidor" | Falta la terminal con `php -S localhost:3001 -t ../frontend index.php`. |
