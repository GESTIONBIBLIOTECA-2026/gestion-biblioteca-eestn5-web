/* ==========================================================================
   MÓDULO: pedidos-ui.js - Piezas HTML del módulo de pedidos/préstamos
   Las páginas de préstamos, pedidos e historial comparten estas plantillas.
   ========================================================================== */

import { escapar, fechaCorta } from "./ui.js";

// Estado de la base → texto visible + clase de color de componentes.css
const ETIQUETAS = {
    pendiente: ["Pendiente", "ambar"],
    prestado: ["En curso", "azul"],
    vencido: ["Vencido", "rojo"],
    devuelto: ["Devuelto", "verde"],
    cancelado: ["Cancelado", "gris"]
};

export function etiquetaEstadoHTML(estado) {
    const [texto, clase] = ETIQUETAS[estado] ?? [estado ?? "—", "gris"];
    return `<span class="etiqueta ${clase}">${escapar(texto)}</span>`;
}

// Botones según el estado: pendiente se entrega o rechaza; en curso se devuelve.
function accionesHTML(pedido) {
    const id = pedido.id_pedido ?? pedido.id;

    if (pedido.estado === "pendiente") {
        return `
            <button type="button" class="boton-principal" data-accion="prestado" data-id="${id}">
                Entregar
            </button>
            <button type="button" class="boton-peligro" data-accion="cancelado" data-id="${id}">
                Rechazar
            </button>`;
    }

    if (pedido.estado === "prestado" || pedido.estado === "vencido") {
        return `
            <button type="button" class="boton-principal" data-accion="devuelto" data-id="${id}">
                Registrar devolución
            </button>`;
    }

    return `<span class="texto-tenue">Sin acciones</span>`;
}

const usuarioConDni = (pedido) =>
    `<strong>${escapar(pedido.usuario)}</strong><small>DNI ${escapar(pedido.dni ?? "—")}</small>`;

/* --- Fila compacta para el inicio de Préstamos (sin acciones) --- */
export function crearFilaPedidoRecienteHTML(pedido) {
    const id = pedido.id_pedido ?? pedido.id;

    return `
        <tr>
            <td>#${id}</td>
            <td>${usuarioConDni(pedido)}</td>
            <td>${escapar(pedido.libro)}</td>
            <td>${pedido.cantidad}</td>
            <td>${fechaCorta(pedido.fecha_pedido)}</td>
            <td>${fechaCorta(pedido.fecha_devolucion)}</td>
            <td>${etiquetaEstadoHTML(pedido.estado)}</td>
        </tr>`;
}

/* --- Fila completa para Pedidos (con acciones) --- */
export function crearFilaPedidoHTML(pedido) {
    const id = pedido.id_pedido ?? pedido.id;
    const atraso = pedido.dias_atraso > 0
        ? `<small class="texto-error">${pedido.dias_atraso} día(s) de atraso</small>`
        : "";

    return `
        <tr>
            <td>#${id}</td>
            <td>${usuarioConDni(pedido)}</td>
            <td>${escapar(pedido.libro)}${atraso}</td>
            <td>${pedido.cantidad}</td>
            <td>${fechaCorta(pedido.fecha_pedido)}</td>
            <td>${fechaCorta(pedido.fecha_devolucion)}</td>
            <td>${etiquetaEstadoHTML(pedido.estado)}</td>
            <td><div class="acciones-fila">${accionesHTML(pedido)}</div></td>
        </tr>`;
}

/* --- Fila del historial: muestra también entrega y devolución real --- */
export function crearFilaHistorialHTML(pedido) {
    const id = pedido.id_pedido ?? pedido.id;

    return `
        <tr>
            <td>#${id}</td>
            <td>${usuarioConDni(pedido)}</td>
            <td>${escapar(pedido.libro)}</td>
            <td>${pedido.cantidad}</td>
            <td>${fechaCorta(pedido.fecha_pedido)}</td>
            <td>${fechaCorta(pedido.fecha_entrega)}</td>
            <td>${fechaCorta(pedido.fecha_devolucion)}</td>
            <td>${fechaCorta(pedido.fecha_devolucion_real)}</td>
            <td>${etiquetaEstadoHTML(pedido.estado)}</td>
        </tr>`;
}
