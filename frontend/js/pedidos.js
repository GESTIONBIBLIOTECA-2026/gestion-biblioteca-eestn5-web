/* ==========================================================================
   MÓDULO: pedidos.js - Listado y cambios de estado (pedidos.html)
   - Listado + filtros .. GET /api/pedidos?estado=&texto=
   - Entregar / devolver / rechazar .. PATCH /api/pedidos/:id/estado
   ========================================================================== */

import { listarPedidos, cambiarEstadoPedido } from "./api.js";
import { crearFilaMensajeHTML, mostrarAviso } from "./ui.js";
import { crearFilaPedidoHTML } from "./pedidos-ui.js";

// 🎯 1. Nodos del DOM
const formularioFiltros = document.querySelector("#filtrosPedidos");
const cuerpoPedidos = document.querySelector("#cuerpoPedidos");
const totalPedidos = document.querySelector("#totalPedidos");

const MENSAJES_ACCION = {
    prestado: "Pedido entregado: ahora figura como préstamo en curso.",
    devuelto: "Devolución registrada correctamente.",
    cancelado: "Pedido rechazado correctamente."
};

// 🎨 2. Funciones de UI
function mostrarAvisoDeLaUrl() {
    const mensaje = new URLSearchParams(window.location.search).get("mensaje");
    if (mensaje) mostrarAviso(mensaje);
}

// 🌐 3. Carga de datos (Loading → Éxito → Error)
async function cargarPedidos() {
    cuerpoPedidos.innerHTML = crearFilaMensajeHTML("Cargando pedidos...", 8);

    try {
        const filtros = Object.fromEntries(new FormData(formularioFiltros));
        const { payload } = await listarPedidos(filtros);

        cuerpoPedidos.innerHTML = payload.length
            ? payload.map(crearFilaPedidoHTML).join("")
            : crearFilaMensajeHTML("No hay pedidos que coincidan con el filtro.", 8);

        totalPedidos.textContent = payload.length;
    } catch (error) {
        cuerpoPedidos.innerHTML = crearFilaMensajeHTML(
            error.status ? error.message : "No hay conexión con el servidor.", 8);
        totalPedidos.textContent = "0";
    }

    window.lucide?.createIcons();
}

// 🖱️ 4. Eventos
formularioFiltros.addEventListener("submit", (evento) => {
    evento.preventDefault();
    cargarPedidos();
});

formularioFiltros.addEventListener("reset", () => setTimeout(cargarPedidos));

// Delegación: un solo listener para los botones de todas las filas
cuerpoPedidos.addEventListener("click", async (evento) => {
    const boton = evento.target.closest("[data-accion]");
    if (!boton) return;

    const accion = boton.dataset.accion;

    if (accion === "cancelado" && !window.confirm("¿Rechazar este pedido? La reserva se cancela.")) {
        return;
    }

    boton.disabled = true;

    try {
        const respuesta = await cambiarEstadoPedido(boton.dataset.id, accion);
        mostrarAviso(respuesta.mensaje || MENSAJES_ACCION[accion]);
        await cargarPedidos();
    } catch (error) {
        mostrarAviso(error.status ? error.message : "No hay conexión con el servidor.", true);
        boton.disabled = false;
    }
});

// 🚀 5. Inicialización
mostrarAvisoDeLaUrl();
cargarPedidos();
