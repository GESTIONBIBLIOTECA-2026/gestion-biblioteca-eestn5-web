/* ==========================================================================
   MÓDULO: storage.js - Persistencia Local de la Sesión (localStorage & JSON)
   ========================================================================== */

const SESION_KEY = "biblioteca_sesion_v1";

// Guarda { token, usuario } como texto JSON
export function guardarSesion(sesion) {
    localStorage.setItem(SESION_KEY, JSON.stringify(sesion));
}

// Devuelve la sesión completa { token, usuario } (o null si no hay sesión)
export function obtenerSesion() {
    try {
        return JSON.parse(localStorage.getItem(SESION_KEY));
    } catch (error) {
        return null;
    }
}

// Devuelve solo el token (o null si no hay sesión)
export function obtenerToken() {
    return obtenerSesion()?.token ?? null;
}

// Datos básicos guardados al iniciar sesión: { id, nombre, rol }
export function obtenerUsuarioSesion() {
    return obtenerSesion()?.usuario ?? null;
}

// Cierra la sesión en este navegador
export function borrarSesion() {
    localStorage.removeItem(SESION_KEY);
}
