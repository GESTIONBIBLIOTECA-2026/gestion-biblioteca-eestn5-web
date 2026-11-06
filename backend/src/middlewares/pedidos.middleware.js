/* ==========================================================================
   pedidos.middleware.js - Validación del módulo de pedidos/préstamos
   Frena los datos mal cargados antes de llegar al controlador y a la base.
   Responde 400 con { error, campos: { campo: "mensaje" } } (igual que el
   resto del proyecto) para que el frontend marque el campo.
   ========================================================================== */

import { ESTADOS_PEDIDO, PEDIDOS_POR_PAGINA } from "../config/config.js";
import { limpiar, rechazar } from "./predicados.js";

// Fecha real en formato YYYY-MM-DD (rechaza 2026-02-31, por ejemplo)
const esFechaISO = (valor) => {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(valor)) return false;
    const fecha = new Date(`${valor}T00:00:00`);
    return !Number.isNaN(fecha.getTime()) && fecha.toISOString().slice(0, 10) === valor;
};

const enteroPositivo = (valor) => {
    const numero = Number(valor);
    return Number.isInteger(numero) && numero > 0 ? numero : 0;
};

/* --- ?estado=&texto=&limite= --- */
export const validarFiltrosPedidos = (req, res, next) => {
    const estado = String(req.query.estado ?? "todos");
    const texto = limpiar(req.query.texto).slice(0, 120);
    const limite = Number(req.query.limite ?? PEDIDOS_POR_PAGINA);

    if (estado !== "todos" && !ESTADOS_PEDIDO.includes(estado)) {
        return rechazar(res, { estado: "El estado elegido no es válido." });
    }

    if (!Number.isInteger(limite) || limite < 1 || limite > 500) {
        return rechazar(res, { limite: "El límite tiene que ser un número entre 1 y 500." });
    }

    req.filtros = { estado, texto, limite };
    next();
};

/* --- Alta: { id_usuario, id_libro, cantidad, fecha_pedido, fecha_devolucion } ---
   Acepta también los nombres de columna de la base (usuario_id / libro_id /
   fecha_devolucion_estimada) por si otro cliente consume la API. */
export const validarNuevoPedido = (req, res, next) => {
    const body = req.body ?? {};
    const datos = {
        id_usuario: enteroPositivo(body.id_usuario ?? body.usuario_id),
        id_libro: enteroPositivo(body.id_libro ?? body.libro_id),
        cantidad: enteroPositivo(body.cantidad ?? 1),
        fecha_pedido: String(body.fecha_pedido ?? "").trim(),
        fecha_devolucion: String(body.fecha_devolucion ?? body.fecha_devolucion_estimada ?? "").trim()
    };
    const campos = {};

    if (!datos.id_usuario) campos.id_usuario = "Buscá y seleccioná un usuario.";
    if (!datos.id_libro) campos.id_libro = "Buscá y seleccioná un libro.";
    if (!datos.cantidad) campos.cantidad = "Ingresá una cantidad válida.";

    if (!esFechaISO(datos.fecha_pedido)) {
        campos.fecha_pedido = "Elegí una fecha de pedido válida.";
    }

    if (!esFechaISO(datos.fecha_devolucion)) {
        campos.fecha_devolucion = "Elegí una fecha de devolución válida.";
    } else if (campos.fecha_pedido === undefined && datos.fecha_devolucion < datos.fecha_pedido) {
        campos.fecha_devolucion = "La devolución no puede ser anterior a la fecha del pedido.";
    }

    if (Object.keys(campos).length > 0) return rechazar(res, campos);

    req.body = datos;
    next();
};

/* --- Acción de estado: { estado: "prestado" | "devuelto" | "cancelado" } ---
   También entiende los nombres viejos del módulo original (Entregado,
   Rechazado, Devuelto) para no romper integraciones existentes. */
const MAPA_ESTADOS = {
    prestado: "prestado",
    entregado: "prestado",
    devuelto: "devuelto",
    cancelado: "cancelado",
    rechazado: "cancelado"
};

export const validarEstadoPedido = (req, res, next) => {
    const valor = String(req.body?.estado ?? req.body?.accion ?? "").toLowerCase();
    const estado = MAPA_ESTADOS[valor];

    if (!estado) {
        return rechazar(res, { estado: "La acción no es válida." });
    }

    req.body = { estado };
    next();
};
