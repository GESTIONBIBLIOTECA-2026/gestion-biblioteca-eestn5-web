/* ==========================================================================
   MÓDULO: historial.js - Historial completo de pedidos y préstamos
   Consume GET /api/pedidos?limite=500 y muestra todas las fechas.
   ========================================================================== */

import { listarPedidos } from "./api.js";
import { crearFilaMensajeHTML, mostrarAviso } from "./ui.js";
import { crearFilaHistorialHTML } from "./pedidos-ui.js";

// 🎯 1. Nodos del DOM
const cuerpoHistorial = document.querySelector("#cuerpoHistorial");
const totalHistorial = document.querySelector("#totalHistorial");
const formularioFiltros = document.querySelector("#filtrosHistorial");

// 🌐 2. Carga de datos (Loading → Éxito → Error)
async function cargarHistorial() {
    cuerpoHistorial.innerHTML = crearFilaMensajeHTML("Cargando historial...", 9);

    try {
        const filtros = { ...Object.fromEntries(new FormData(formularioFiltros)), limite: 500 };
        const { payload } = await listarPedidos(filtros);

        cuerpoHistorial.innerHTML = payload.length
            ? payload.map(crearFilaHistorialHTML).join("")
            : crearFilaMensajeHTML("No hay pedidos en el historial con ese filtro.", 9);

        totalHistorial.textContent = payload.length;
    } catch (error) {
        cuerpoHistorial.innerHTML = crearFilaMensajeHTML(
            error.status ? error.message : "No hay conexión con el servidor.", 9);
        totalHistorial.textContent = "0";
        mostrarAviso(error.status ? error.message : "No hay conexión con el servidor.", true);
    }

    window.lucide?.createIcons();
}

// 🖱️ 3. Eventos
formularioFiltros.addEventListener("submit", (evento) => {
    evento.preventDefault();
    cargarHistorial();
});

formularioFiltros.addEventListener("reset", () => setTimeout(cargarHistorial));

// 🚀 4. Inicialización
cargarHistorial();
