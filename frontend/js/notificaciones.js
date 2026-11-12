/* ==========================================================================
   MÓDULO: notificaciones.js - Campanita de actividad reciente
   Reemplaza la antigua sección "Reportes". La actividad se arma en el
   backend (GET /api/notificaciones) con altas de usuarios, pedidos,
   entregas, devoluciones, cancelaciones y sanciones ya guardadas.
   Lo leído/no leído se recuerda en este navegador (localStorage).
   ========================================================================== */

import { obtenerNotificaciones } from "./api.js";
import { escapar } from "./ui.js";

const CLAVE_LEIDAS = "biblioteca_notificaciones_leidas_v1";
const LIMITE_EVENTOS = 40;
const MAXIMO_LEIDAS = 300;        // tope de IDs recordados (no crece sin límite)
const REFRESCO_MS = 60000;        // al reabrir, refresca si pasó más de un minuto

// Ícono Lucide según el tipo de movimiento que manda el backend
const ICONOS = {
    usuario: "user-plus",
    pedido: "book-plus",
    prestamo: "book-open-check",
    devolucion: "book-down",
    cancelacion: "circle-x",
    sancion: "triangle-alert"
};

const MESES = ["ene", "feb", "mar", "abr", "may", "jun", "jul", "ago", "sep", "oct", "nov", "dic"];


// 🎯 1. Nodos del DOM (los trae el encabezado de cada página protegida)
const contenedor = document.querySelector("[data-menu-notificaciones]");
const boton = document.querySelector("[data-abrir-notificaciones]");
const panel = document.querySelector("#panelNotificaciones");
const lista = document.querySelector("[data-lista-notificaciones]");
const contador = document.querySelector("[data-contador-notificaciones]");
const resumen = document.querySelector("[data-notificaciones-resumen]");
const botonActualizar = document.querySelector("[data-actualizar-notificaciones]");


// 🧠 2. Estado local
let eventos = [];
let leidas = leerLeidas();        // null = primera visita en este navegador
let ultimaCarga = 0;
let cargando = false;
let iniciado = false;


// 💾 3. Persistencia de lo leído
function leerLeidas() {
    try {
        const guardado = JSON.parse(localStorage.getItem(CLAVE_LEIDAS));
        return Array.isArray(guardado) ? new Set(guardado) : null;
    } catch (error) {
        return null;
    }
}

function recordarLeidas(ids) {
    if (leidas === null) leidas = new Set();

    ids.forEach((id) => leidas.add(id));

    // Conserva solo los últimos IDs para que el localStorage no crezca de más
    const recortadas = [...leidas].slice(-MAXIMO_LEIDAS);
    leidas = new Set(recortadas);

    try {
        localStorage.setItem(CLAVE_LEIDAS, JSON.stringify(recortadas));
    } catch (error) {
        // Si el navegador bloquea localStorage, la campanita sigue funcionando
    }
}


// 🎨 4. Formato de fechas y plantillas HTML
function fechaHora(texto) {
    const [fecha, hora = ""] = String(texto ?? "").split(" ");
    const [anio, mes, dia] = fecha.slice(0, 10).split("-").map(Number);
    if (!anio || !mes || !dia) return "";
    return `${dia} ${MESES[mes - 1]} ${anio}${hora ? ` · ${hora.slice(0, 5)}` : ""}`;
}

function tiempoRelativo(texto) {
    if (!texto) return "";

    const fecha = new Date(String(texto).replace(" ", "T"));
    if (Number.isNaN(fecha.getTime())) return fechaHora(texto);

    const minutos = Math.floor((Date.now() - fecha.getTime()) / 60000);
    if (minutos < 1) return "ahora";
    if (minutos < 60) return `hace ${minutos} min`;

    const horas = Math.floor(minutos / 60);
    if (horas < 24) return `hace ${horas} h`;

    const dias = Math.floor(horas / 24);
    if (dias < 7) return `hace ${dias} día${dias === 1 ? "" : "s"}`;

    return fechaHora(texto);
}

function crearItemHTML(evento, esNueva) {
    const tipo = escapar(evento.tipo);
    const icono = ICONOS[evento.tipo] ?? "bell";

    return `
        <li class="notificacion-item${esNueva ? " nueva" : ""}" data-tipo="${tipo}">
            <span class="notificacion-icono" aria-hidden="true">
                <i data-lucide="${icono}"></i>
            </span>
            <div class="notificacion-texto">
                <strong>${escapar(evento.titulo)}</strong>
                <p>${escapar(evento.mensaje)}</p>
                <time datetime="${escapar(String(evento.fecha ?? "").replace(" ", "T"))}">${escapar(tiempoRelativo(evento.fecha))}</time>
            </div>
        </li>`;
}

function render(nuevas = new Set()) {
    if (!eventos.length) {
        lista.innerHTML = `<li class="notificacion-vacia">No hay actividad reciente.</li>`;
    } else {
        lista.innerHTML = eventos.map((evento) => crearItemHTML(evento, nuevas.has(evento.id))).join("");
    }

    resumen.textContent = eventos.length === 1 ? "1 movimiento" : `${eventos.length} movimientos`;
    window.lucide?.createIcons();
}

function actualizarContador() {
    const nuevas = leidas === null ? 0 : eventos.filter((evento) => !leidas.has(evento.id)).length;

    contador.hidden = nuevas === 0;
    contador.textContent = nuevas > 9 ? "9+" : String(nuevas);
    boton.setAttribute("aria-label", nuevas
        ? `Actividad reciente: ${nuevas} sin leer`
        : "Ver actividad reciente");
}


// 🌐 5. Carga desde la API
async function cargar() {
    if (cargando) return;
    cargando = true;

    try {
        const { payload } = await obtenerNotificaciones(LIMITE_EVENTOS);
        eventos = Array.isArray(payload) ? payload : [];
        ultimaCarga = Date.now();

        // En la primera visita no se marca todo como "sin leer": se toma la
        // actividad existente como punto de partida y solo avisa lo nuevo.
        if (leidas === null) {
            leidas = new Set();
            recordarLeidas(eventos.map((evento) => evento.id));
        }

        actualizarContador();
        if (!panel.hidden) render();
    } catch (error) {
        // La campanita no debe romper la página si la API no responde
        if (!panel.hidden) {
            lista.innerHTML = `<li class="notificacion-vacia">No se pudo cargar la actividad.</li>`;
        }
    } finally {
        cargando = false;
    }
}


// 🖱️ 6. Abrir / cerrar el panel
function abrirPanel() {
    const nuevas = new Set(eventos.filter((evento) => leidas !== null && !leidas.has(evento.id)).map((evento) => evento.id));

    render(nuevas);
    panel.hidden = false;
    boton.setAttribute("aria-expanded", "true");

    // Al abrir la campanita, lo mostrado pasa a leído
    recordarLeidas(eventos.map((evento) => evento.id));
    actualizarContador();

    if (Date.now() - ultimaCarga > REFRESCO_MS) cargar();
}

function cerrarPanel() {
    panel.hidden = true;
    boton.setAttribute("aria-expanded", "false");
}

function alternarPanel() {
    if (panel.hidden) abrirPanel();
    else cerrarPanel();
}


// 🚀 7. Inicialización (la llama sesion.js solo para administradores)
export function iniciarNotificaciones() {
    if (iniciado || !contenedor) return;
    iniciado = true;

    contenedor.hidden = false;      // el HTML lo trae oculto para alumnos/docentes

    boton.addEventListener("click", alternarPanel);

    botonActualizar.addEventListener("click", (evento) => {
        evento.stopPropagation();
        botonActualizar.classList.add("girando");
        cargar().finally(() => botonActualizar.classList.remove("girando"));
    });

    // Clic afuera cierra el panel (el menú del perfil hace lo mismo con el suyo).
    // Se usa composedPath porque Lucide reemplaza el SVG del botón al abrir:
    // en ese caso event.target queda fuera del documento y closest() fallaría.
    document.addEventListener("click", (evento) => {
        const dentro = evento.composedPath().some(
            (nodo) => nodo instanceof Element && nodo.matches("[data-menu-notificaciones]"));
        if (!panel.hidden && !dentro) cerrarPanel();
    });

    document.addEventListener("keydown", (evento) => {
        if (evento.key === "Escape" && !panel.hidden) {
            cerrarPanel();
            boton.focus();
        }
    });

    // Al volver a la pestaña, si pasó un rato, se actualiza sola
    document.addEventListener("visibilitychange", () => {
        if (!document.hidden && Date.now() - ultimaCarga > REFRESCO_MS) cargar();
    });

    cargar();
}
