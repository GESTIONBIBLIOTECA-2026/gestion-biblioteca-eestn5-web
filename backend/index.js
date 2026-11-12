/* =====================================================================
   index.js - Servidor local para desarrollo y ejecución tradicional.
   ===================================================================== */

import { PORT } from "./src/config/config.js";
import connection from "./src/database/db.js";
import app from "./app.js";

app.listen(PORT, async () => {
    console.log(`[OK] Servidor activo en http://localhost:${PORT}`);
    try {
        await connection.query("SELECT 1");
        console.log("[OK] Base de datos conectada");
    } catch (error) {
        console.error("[!] No se pudo conectar a la base de datos:", error.message);
        console.error("    Revisa DB_HOST, DB_PORT, DB_USER, DB_PASSWORD y DB_NAME en backend/.env");
    }
});
