/* ==========================================================================
   notificaciones.routes.js - Rutas de la actividad reciente (/api/notificaciones)
   Reemplaza la antigua sección "Reportes": la campanita del encabezado.
   ========================================================================== */

import express from "express";
import { getNotificaciones } from "../controllers/notificaciones.controllers.js";
import { verificarAdmin } from "../middlewares/auth.middleware.js";
import { validarLimiteNotificaciones } from "../middlewares/validaciones.middleware.js";

const router = express.Router();

router.use(verificarAdmin);                                              // solo el panel del bibliotecario
router.get("/", validarLimiteNotificaciones, getNotificaciones);         // GET /api/notificaciones?limite=40

export default router;
