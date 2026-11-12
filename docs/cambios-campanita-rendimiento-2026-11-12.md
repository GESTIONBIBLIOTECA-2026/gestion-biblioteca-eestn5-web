# Cambios: campanita de actividad, rendimiento y correcciones

**Fecha:** 12 de noviembre de 2026
**Rama:** `g4`
**Proyecto:** Sistema de Gestión de Biblioteca (E.E.S.T. N° 5)

---

## 🎯 Objetivo

Reemplazar la sección **REPORTES** por una campanita de actividad en el encabezado,
optimizar la velocidad de carga del panel y corregir errores detectados en el flujo de
usuarios, pedidos, sanciones e inventario, manteniendo la estructura MVC y las
convenciones de acceso a la base de datos del proyecto.

---

## 🔔 1. Campanita de actividad (reemplaza a Reportes)

### Qué muestra

La campanita reúne en una sola línea de tiempo la actividad ya registrada en el sistema:

- Altas de usuarios (alumnos, docentes y administradores).
- Pedidos y reservas creados.
- Préstamos entregados.
- Devoluciones registradas.
- Reservas canceladas.
- Sanciones aplicadas.

Cada evento incluye un ícono según el tipo, el detalle del movimiento y la fecha
(relativa para los últimos días y con fecha completa para los más antiguos).

### Cómo funciona

- Se eliminó el enlace **Reportes** de la navegación de las 9 páginas del panel.
- El encabezado de todas las páginas protegidas incluye el mismo bloque de campanita.
- El bloque arranca oculto y **solo el administrador** lo activa; alumnos y docentes
  no ven la campanita.
- El backend expone `GET /api/notificaciones?limite=40`, protegido con `verificarAdmin`
  y con validación del límite (entre 1 y 100).
- La actividad se arma con las tablas reales (`usuarios`, `pedidos`,
  `pedido_historial` y `sanciones`) mediante `UNION ALL`. **No se creó una tabla nueva**
  ni se duplican datos, por lo que el historial existente aparece de inmediato.
- Cada consulta trae como máximo los últimos 50 movimientos de cada tipo y después
  mezcla y recorta el resultado al límite pedido, para no recorrer tablas completas.
- El estado leído/no leído se guarda en `localStorage` por ID de evento (hasta 300 IDs).
  En la primera visita se toma la actividad existente como punto de partida: el badge
  solo avisa de movimientos posteriores.
- El panel se actualiza al abrirlo (si pasó más de un minuto), con el botón de refrescar,
  al volver a la pestaña y al iniciar la sesión. Se cierra con `Escape` o al hacer clic
  afuera.

### Archivos principales

- `backend/src/models/notificacion.models.js`
- `backend/src/controllers/notificaciones.controllers.js`
- `backend/src/routes/notificaciones.routes.js`
- `frontend/js/notificaciones.js`
- `frontend/js/sesion.js` (inicialización solo para administradores)
- `frontend/css/estilo-general.css` (estilos y adaptación a móvil)
- Marcado repetido en las 9 páginas del panel y en `inicio-usuario.html`

---

## ⚡ 2. Optimización de carga

- Se agregó el middleware `compression`: las respuestas JSON, CSS y JS viajan
  comprimidas cuando el navegador lo admite.
- Los archivos estáticos ahora se sirven con caché de 1 hora (`maxAge: "1h"`), mientras
  que los HTML quedan con `Cache-Control: no-cache` para ver los cambios al navegar.
- La sesión se pinta al instante con los datos guardados al iniciar sesión y luego se
  revalida en segundo plano contra `GET /api/auth/perfil`. Si el nombre o el rol
  cambiaron en la base, se actualiza lo guardado.
- Si hay un corte de red, la página ya mostrada no expulsa al usuario; solo se cierra
  la sesión ante un `401` o `403`.
- Lucide se fijó en la versión `0.468.0` y se cargó con `defer`; la creación de íconos
  se movió a `DOMContentLoaded`, evitando bloquear el renderizado y errores de orden.
- `API_URL` usa el mismo origen cuando el frontend lo sirve el backend
  (`localhost:3001`, puerto 80/443 o la IP de la LAN). Si se abre con Live Server u otro
  puerto, sigue apuntando a `http://<host>:3001/api`. Así funciona también desde el
  celular sin editar el archivo.

---

## 🐛 3. Correcciones de errores

- **Falsos 404 al guardar sin cambios:** en usuarios e inventario se comprueba primero
  que el registro exista; un `UPDATE` que no modifica valores ya no se interpreta como
  “no existe”.
- **Usuarios morosos o sancionados:** la creación de pedidos y el buscador del formulario
  usan la misma condición que ve el bibliotecario (`CONDICION`), por lo que se rechazan
  usuarios con préstamos vencidos, suspendidos o dados de baja. El backend devuelve un
  mensaje específico para cada caso (409).
- **Operaciones de varias escrituras:** el alta y la edición de usuarios, la suspensión,
  la habilitación y la baja ahora usan `conTransaccion`. Si falla una parte, no quedan
  datos a medias.
- **Baja de usuario:** además de cancelar las reservas pendientes, cierra las sanciones
  activas para que no queden vigentes sobre un usuario dado de baja.
- **Límite de cantidad:** se agregó `CANTIDAD_MAXIMA_PEDIDO = 50`, validado en el
  middleware y reflejado en el formulario (el máximo real es el menor entre el stock y 50).
- **Respuestas de error:** `responderError` usa un código 500 de respaldo si el tipo de
  error de negocio no tiene un estado asociado.
- **Notificaciones:** el límite de la URL se valida para evitar valores inválidos o
  consultas demasiado grandes.

---

## 🧪 4. Validación realizada

- `node --check` sobre los 79 archivos JavaScript de `backend` y `frontend`: **todos OK**.
- Prueba de arranque del backend con variables de entorno de prueba:
  - `GET /` → `200`.
  - `GET /api/notificaciones` sin sesión → `401`.
  - CSS → `200` con `Cache-Control: public, max-age=3600`.
  - HTML → `200` con `Cache-Control: no-cache`.
- Prueba del frontend con una API simulada y navegador:
  - Login de administrador y renderizado de la campanita.
  - Los seis tipos de actividad, fechas relativas, badge y resaltado de no leídos.
  - Marcar como leído, refrescar, menú de perfil, cierre con `Escape`.
  - Las 8 páginas del panel sin errores de consola y con 5 ítems de navegación.
  - Páginas de alumno sin errores, campanita oculta y 3 ítems de navegación.
- `npm audit --omit=dev`: **0 vulnerabilidades**.

**Limitación:** el entorno de trabajo no tiene `backend/.env` ni un MySQL local, por lo
que no se pudo ejecutar una prueba de integración contra la base real de Aiven. La lógica
del backend se validó con revisión de código, arranque del servidor y una API simulada en
el navegador. Para probar con datos reales, completar `backend/.env` y ejecutar
`npm run dev`.

---

## 🚀 5. Cómo verificarlo

1. Completar `backend/.env` con los datos de Aiven.
2. Ejecutar `npm run dev`.
3. Abrir `http://localhost:3001` e iniciar sesión como administrador.
4. Usar la campanita del encabezado: debe listar la actividad y marcar lo nuevo.
5. Entrar como alumno o docente: la campanita no debe aparecer.
6. Crear o editar un usuario/libro sin cambiar datos: debe guardar sin error 404.
7. Intentar un pedido con un usuario moroso o sancionado: debe rechazarse con un
   mensaje claro.
