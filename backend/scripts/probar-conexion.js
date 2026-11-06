/* ==========================================================================
   probar-conexion.js - ¿El backend llega a la base de datos?
   Uso (desde la raíz del proyecto):  npm run db:test
   ========================================================================== */

import connection from "../src/database/db.js";

try {
    const [[datos]] = await connection.query("SELECT VERSION() AS version, DATABASE() AS base");
    const [[ssl]] = await connection.query("SHOW SESSION STATUS LIKE 'Ssl_cipher'");
    const cifrado = ssl?.Value ?? "";

    console.log(`[OK] MySQL ${datos.version} conectado a la base "${datos.base}"${cifrado ? ` (SSL: ${cifrado})` : " (sin SSL)"}`);
} catch (error) {
    console.error(`[!] No se pudo conectar a la base de datos: ${error.message}`);
    process.exitCode = 1;
} finally {
    await connection.end();
}
