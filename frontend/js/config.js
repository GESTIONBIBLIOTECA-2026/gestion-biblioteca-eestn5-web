/* ==========================================================================
   MÓDULO: config.js - Configuración del Frontend
   ========================================================================== */

// Dirección de la API Node/Express.
// - Si el backend sirve el frontend (http://localhost:3001 o la IP de la LAN),
//   se usa el mismo origen: funciona también desde el celular.
// - Si se abre con Live Server (otro puerto) o como archivo, el backend
//   sigue escuchando en el puerto 3001 de esta misma computadora.
const HOSTS_LOCALES = ["localhost", "127.0.0.1"];
const PUERTO_BACKEND = "3001";

const hostBackend = HOSTS_LOCALES.includes(window.location.hostname)
    ? "localhost"
    : window.location.hostname;

// Puertos donde se sirve el frontend desde el propio backend (o un proxy web).
const puertosDelBackend = ["", PUERTO_BACKEND, "80", "443"];
const usaPuertoLocal = window.location.protocol === "file:"
    || !puertosDelBackend.includes(window.location.port);

export const API_URL = usaPuertoLocal
    ? `http://${hostBackend}:${PUERTO_BACKEND}/api`
    : `${window.location.origin}/api`;
