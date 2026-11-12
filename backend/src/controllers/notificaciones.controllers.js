/* ==========================================================================
   notificaciones.controllers.js - Actividad reciente del panel
   Solo la puede consultar el administrador (ver notificaciones.routes.js).
   ========================================================================== */

import { listarNotificaciones } from "../models/notificacion.models.js";
import { error500 } from "./respuestas.js";

/* --- GET /api/notificaciones?limite=40 --- */
export const getNotificaciones = async (req, res) => {
    try {
        return res.json({ payload: await listarNotificaciones(req.limite) });
    } catch (error) {
        return error500(res, error, "No se pudieron cargar las notificaciones");
    }
};
