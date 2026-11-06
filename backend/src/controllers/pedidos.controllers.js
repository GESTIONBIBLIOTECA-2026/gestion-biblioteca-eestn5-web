/* ==========================================================================
   pedidos.controllers.js - Pedidos y préstamos del panel · Grupo 4 integrado
   El Modelo lanza ErrorNegocio (404 / 409) y acá se traduce con responderError.
   ========================================================================== */

import {
    listarPedidos,
    obtenerResumenPedidos,
    listarUsuariosHabilitados,
    listarLibrosDisponibles,
    crearPedido,
    cambiarEstadoPedido
} from "../models/pedido.models.js";
import { error500, responderError } from "./respuestas.js";

/* --- GET /api/pedidos?estado=&texto=&limite= --- */
export const getPedidos = async (req, res) => {
    try {
        return res.json({ payload: await listarPedidos(req.filtros) });
    } catch (error) {
        return error500(res, error, "No se pudieron cargar los pedidos");
    }
};

/* --- GET /api/pedidos/resumen --- */
export const getResumenPedidos = async (req, res) => {
    try {
        return res.json({ payload: await obtenerResumenPedidos() });
    } catch (error) {
        return error500(res, error, "No se pudo calcular el resumen de pedidos");
    }
};

/* --- GET /api/pedidos/formulario (usuarios habilitados + libros con stock) --- */
export const getFormularioPedido = async (req, res) => {
    try {
        const [usuarios, libros] = await Promise.all([
            listarUsuariosHabilitados(),
            listarLibrosDisponibles()
        ]);
        return res.json({ payload: { usuarios, libros } });
    } catch (error) {
        return error500(res, error, "No se pudieron cargar los datos del formulario");
    }
};

/* --- POST /api/pedidos --- */
export const postPedido = async (req, res) => {
    try {
        const idPedido = await crearPedido(req.body, req.usuario.id);
        return res.status(201).json({
            mensaje: "Pedido registrado correctamente.",
            payload: { id_pedido: idPedido }
        });
    } catch (error) {
        return responderError(res, error, "No se pudo registrar el pedido");
    }
};

/* --- PATCH /api/pedidos/:id/estado --- */
export const patchEstadoPedido = async (req, res) => {
    try {
        await cambiarEstadoPedido(req.id, req.body.estado, req.usuario.id);
        return res.json({ mensaje: "Estado actualizado correctamente." });
    } catch (error) {
        return responderError(res, error, "No se pudo actualizar el estado del pedido");
    }
};
