/* ==========================================================================
   MÓDULO: sesion.js - Protege las páginas y maneja el encabezado
   - Sin sesión válida → login.html
   - Página de otro rol → al inicio que le corresponde (js/navegacion.js)
   - Nombre y rol de quien inició sesión, fecha y hora
   - Menú del perfil (Ajustes, Apariencia y Cerrar sesión)
   - Campanita de actividad reciente (solo administrador)
   Se incluye en cada página protegida: <script type="module" src="js/sesion.js">
   ========================================================================== */

import { obtenerSesionActual } from "./api.js";
import { guardarSesion, obtenerToken, obtenerUsuarioSesion, borrarSesion } from "./storage.js";
import { fechaDeHoy } from "./ui.js";
import { adaptarAlRol } from "./navegacion.js";
import { iniciarNotificaciones } from "./notificaciones.js";


// 🎯 1. Nodos del DOM
const textoFecha  = document.querySelector("[data-fecha-hoy]");
const textoReloj  = document.querySelector("#reloj-actual");
const botonPerfil = document.querySelector("[data-abrir-menu-perfil]");
const menuPerfil  = document.querySelector("#menuPerfil");

let paginaMostrada = false;


// 🎨 2. Funciones de UI
const irAlLogin = () => window.location.replace("login.html");

// Nombre (y rol) en todos los lugares del encabezado. También la usa Configuración.
export function mostrarNombre(nombre, rol = "administrador") {
    document.querySelectorAll("[data-nombre-admin]").forEach((elemento) => { elemento.textContent = nombre; });
    document.querySelectorAll("[data-rol-admin]").forEach((elemento) => { elemento.textContent = rol.charAt(0).toUpperCase() + rol.slice(1); });
}

function actualizarReloj() {
    if (textoFecha) textoFecha.textContent = fechaDeHoy();
    if (textoReloj) textoReloj.textContent = new Date().toLocaleTimeString("es-AR", { hour: "2-digit", minute: "2-digit" });
}

function alternarMenu(abrir) {
    if (!menuPerfil || !botonPerfil) return;
    menuPerfil.hidden = !abrir;
    botonPerfil.setAttribute("aria-expanded", abrir);
}

// Muestra la página con los datos de la sesión y enciende la campanita del admin
function aplicarSesion(usuario) {
    if (!adaptarAlRol(usuario.rol)) return false;   // página de otro rol: ya se está yendo

    mostrarNombre(usuario.nombre, usuario.rol);
    if (usuario.rol === "administrador") iniciarNotificaciones();

    document.documentElement.classList.remove("verificando-sesion");

    if (!paginaMostrada) {
        paginaMostrada = true;
        document.dispatchEvent(new CustomEvent("sesion-verificada"));
    }

    return true;
}


// 🌐 3. Verificar la sesión con el backend (GET /api/auth/perfil)
async function verificarSesion() {
    try {
        const { payload } = await obtenerSesionActual();

        // Si el nombre o el rol cambiaron en la base, se actualiza lo guardado
        guardarSesion({ token: obtenerToken(), usuario: payload });
        aplicarSesion(payload);

    } catch (error) {
        // Token vencido, inválido o usuario dado de baja
        if (error.status === 401 || error.status === 403) {
            borrarSesion();
            return irAlLogin();
        }

        // Sin conexión: si ya se mostró la página con los datos guardados,
        // se deja navegar en vez de expulsar al usuario por un corte de red.
        if (!paginaMostrada) irAlLogin();
    }
}


// 🖱️ 4. Eventos (delegación en el documento)
document.addEventListener("click", (evento) => {

    // Cerrar sesión
    if (evento.target.closest("[data-cerrar-sesion]")) {
        borrarSesion();
        return irAlLogin();
    }

    // Abrir / cerrar el menú del perfil (clic afuera lo cierra)
    if (evento.target.closest("[data-abrir-menu-perfil]")) return alternarMenu(menuPerfil.hidden);
    if (!evento.target.closest("[data-menu-perfil]")) alternarMenu(false);
});

document.addEventListener("keydown", (evento) => {
    if (evento.key === "Escape" && menuPerfil && !menuPerfil.hidden) {
        alternarMenu(false);
        botonPerfil.focus();
    }
});


// 🚀 5. Inicialización
function iniciar() {
    if (!obtenerToken()) return irAlLogin();

    // 1. Pintar la página al instante con los datos guardados al hacer login
    const usuarioGuardado = obtenerUsuarioSesion();
    if (usuarioGuardado?.nombre && usuarioGuardado?.rol) {
        if (!aplicarSesion(usuarioGuardado)) return;
    }

    // 2. Confirmar el rol y el estado actuales en segundo plano
    verificarSesion();
}

actualizarReloj();
setInterval(actualizarReloj, 30000);
iniciar();
