/* ==========================================================================
   constants.js - Reglas puras del negocio (no tienen secretos)
   Si cambia una regla (ej: 15 usuarios por página) se toca SOLO este archivo.
   ========================================================================== */

export const ROLES = ["alumno", "docente", "administrador"];
export const ESTADOS = ["habilitado", "suspendido", "baja"];
export const FILTROS = ["todos", "alumnos", "docentes", "administradores", "morosos", "suspendidos", "bajas"];
export const USUARIOS_POR_PAGINA = 10;

// --- Pedidos y préstamos (módulo integrado del Grupo 4) ---
// Estados reales de la tabla `pedidos` del proyecto.
export const ESTADOS_PEDIDO = ["pendiente", "prestado", "devuelto", "vencido", "cancelado"];

// Acciones que puede ejecutar el panel y desde qué estado se permiten.
// El estado "vencido" se calcula al mostrar un préstamo cuya fecha pasó.
export const TRANSICIONES_PEDIDO = Object.freeze({
    prestado: ["pendiente"],
    cancelado: ["pendiente"],
    devuelto: ["prestado", "vencido"]
});

export const PEDIDOS_POR_PAGINA = 200;

// Tope de ejemplares por pedido (evita reservas absurdas y cuida el TINYINT)
export const CANTIDAD_MAXIMA_PEDIDO = 50;
