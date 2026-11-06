/* ==========================================================================
   pedidos.routes.js - Rutas de pedidos y préstamos (/api/pedidos) · Grupo 4
   Todas las operaciones son del administrador de la biblioteca.
   El panel de Inicio sigue usando /api/prestamos (solo lectura).
   ========================================================================== */

import express from "express";
import {
    getPedidos,
    getResumenPedidos,
    getFormularioPedido,
    postPedido,
    patchEstadoPedido
} from "../controllers/pedidos.controllers.js";
import { verificarAdmin } from "../middlewares/auth.middleware.js";
import { validarID } from "../middlewares/validaciones.middleware.js";
import { validarFiltrosPedidos, validarNuevoPedido, validarEstadoPedido } from "../middlewares/pedidos.middleware.js";

const router = express.Router();

router.get("/resumen", verificarAdmin, getResumenPedidos);                                // números del panel
router.get("/formulario", verificarAdmin, getFormularioPedido);                           // usuarios + libros
router.get("/", verificarAdmin, validarFiltrosPedidos, getPedidos);                       // listado con filtros
router.post("/", verificarAdmin, validarNuevoPedido, postPedido);                         // alta de reserva
router.patch("/:id/estado", verificarAdmin, validarID, validarEstadoPedido, patchEstadoPedido); // entregar / devolver / cancelar

export default router;
