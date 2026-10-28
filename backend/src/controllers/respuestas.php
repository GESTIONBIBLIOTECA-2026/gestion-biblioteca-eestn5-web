<?php
/* ==========================================================================
   respuestas.php - Respuestas HTTP que repiten varios controladores
   ========================================================================== */

require_once __DIR__ . '/../routes/Router.php';
require_once __DIR__ . '/../models/errores.models.php';

const STATUS_NEGOCIO = ['NO_EXISTE' => 404, 'CONFLICTO' => 409];

// Error inesperado (la base no responde, etc.) → 500 con un mensaje claro
function error500(Throwable $error, string $mensaje): never
{
    error_log("$mensaje: " . $error->getMessage());
    responder(500, ['error' => $mensaje]);
}

// Dato repetido o regla de negocio rota → 409 con los campos marcados { error, campos }
function responder409(array $campos): never
{
    responder(409, ['error' => reset($campos), 'campos' => $campos]);
}

// El Modelo avisó una regla de negocio (404 / 409); cualquier otra cosa es un 500
function responderError(Throwable $error, string $mensaje): never
{
    if ($error instanceof ErrorNegocio) {
        responder(STATUS_NEGOCIO[$error->tipo], ['error' => $error->getMessage()]);
    }
    error500($error, $mensaje);
}
