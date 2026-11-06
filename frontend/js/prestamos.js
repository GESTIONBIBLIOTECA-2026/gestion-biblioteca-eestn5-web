/* ==========================================================================
   MÓDULO: prestamos.js - Inicio del módulo de préstamos (prestamos.html)
   - Resumen ............ GET /api/pedidos/resumen
   - Pedidos recientes .. GET /api/pedidos?limite=6
   ========================================================================== */

import { obtenerResumenPedidos, listarPedidos } from "./api.js";
import { crearFilaMensajeHTML, mostrarAviso } from "./ui.js";
import { crearFilaPedidoRecienteHTML } from "./pedidos-ui.js";

// 🎯 1. Nodos del DOM
const cuerpoPedidos = document.querySelector("#cuerpoPedidosRecientes");

// Cada tarjeta del resumen ↔ el número que devuelve el backend
const CONTADORES = {
    resumenTotal: "total",
    resumenPendientes: "pendientes",
    resumenPrestados: "prestados",
    resumenAtrasados: "atrasados"
};

// 🎨 2. Funciones de UI
function mostrarResumen(resumen) {
    Object.entries(CONTADORES).forEach(([id, clave]) => {
        const numero = resumen ? Number(resumen[clave]) || 0 : 0;
        document.getElementById(id).textContent = numero.toLocaleString("es-AR");
    });
}

// 🌐 3. Carga de datos (Loading → Éxito → Error)
async function cargarResumen() {
    try {
        const { payload } = await obtenerResumenPedidos();
        mostrarResumen(payload);
    } catch (error) {
        mostrarResumen(null);
        mostrarAviso(error.status ? error.message : "No hay conexión con el servidor.", true);
    }
}

async function cargarPedidos() {
    cuerpoPedidos.innerHTML = crearFilaMensajeHTML("Cargando pedidos...", 7);

    try {
        const { payload } = await listarPedidos({ limite: 6 });
        cuerpoPedidos.innerHTML = payload.length
            ? payload.map(crearFilaPedidoRecienteHTML).join("")
            : crearFilaMensajeHTML("Todavía no hay pedidos registrados.", 7);
    } catch (error) {
        cuerpoPedidos.innerHTML = crearFilaMensajeHTML(
            error.status ? error.message : "No hay conexión con el servidor.", 7);
    }

    window.lucide?.createIcons();
}

// 🚀 4. Inicialización (las dos consultas viajan en paralelo)
Promise.all([cargarResumen(), cargarPedidos()]);
