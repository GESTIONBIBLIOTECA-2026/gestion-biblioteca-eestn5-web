<?php
/* ==========================================================================
   errores.models.php - Errores de negocio que lanzan los Modelos
   El Modelo NO sabe de HTTP: dice QUÉ pasó ("NO_EXISTE" o "CONFLICTO")
   y el Controlador decide el código (404 o 409) en respuestas.php.
   ========================================================================== */

class ErrorNegocio extends Exception
{
    public function __construct(public readonly string $tipo, string $mensaje)   // "NO_EXISTE" | "CONFLICTO"
    {
        parent::__construct($mensaje);
    }
}

function noExiste(string $mensaje): ErrorNegocio  { return new ErrorNegocio('NO_EXISTE', $mensaje); }
function conflicto(string $mensaje): ErrorNegocio { return new ErrorNegocio('CONFLICTO', $mensaje); }
