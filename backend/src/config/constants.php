<?php
/* ==========================================================================
   constants.php - Reglas puras del negocio (no tienen secretos)
   Si cambia una regla (ej: 15 usuarios por página) se toca SOLO este archivo.
   ========================================================================== */

const ROLES = ['alumno', 'docente', 'administrador'];
const ESTADOS = ['habilitado', 'suspendido', 'baja'];
const FILTROS = ['todos', 'alumnos', 'docentes', 'administradores', 'morosos', 'suspendidos', 'bajas'];
const USUARIOS_POR_PAGINA = 10;
