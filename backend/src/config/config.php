<?php
/* ==========================================================================
   config.php - Punto único de configuración
   Carga la infraestructura (env.php) y las reglas del negocio (constants.php),
   así el resto del código solo hace require de "config/config.php".
   ========================================================================== */

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/constants.php';
