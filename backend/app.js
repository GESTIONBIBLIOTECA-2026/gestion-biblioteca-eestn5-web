/* =====================================================================
   app.js - Aplicación Express compartida por Vercel y el servidor local.
   ===================================================================== */

import express from "express";
import cors from "cors";
import compression from "compression";
import { fileURLToPath } from "url";
import authRoutes from "./src/routes/auth.routes.js";
import perfilRoutes from "./src/routes/perfil.routes.js";
import dashboardRoutes from "./src/routes/dashboard.routes.js";
import usuariosRoutes from "./src/routes/usuarios.routes.js";
import cursosRoutes from "./src/routes/cursos.routes.js";
import prestamosRoutes from "./src/routes/prestamos.routes.js";
import pedidosRoutes from "./src/routes/pedidos.routes.js";
import librosRoutes from "./src/routes/libros.routes.js";
import notificacionesRoutes from "./src/routes/notificaciones.routes.js";

const app = express();
const carpetaFrontend = fileURLToPath(new URL("../frontend", import.meta.url));

app.disable("x-powered-by");
app.use(compression());
app.use(cors());
app.use(express.json());

app.use(express.static(carpetaFrontend, {
    maxAge: "1h",
    setHeaders: (res, ruta) => {
        if (ruta.endsWith(".html")) res.setHeader("Cache-Control", "no-cache");
    }
}));

app.use("/api/auth", authRoutes);
app.use("/api/perfil", perfilRoutes);
app.use("/api/dashboard", dashboardRoutes);
app.use("/api/usuarios", usuariosRoutes);
app.use("/api/cursos", cursosRoutes);
app.use("/api/prestamos", prestamosRoutes);
app.use("/api/pedidos", pedidosRoutes);
app.use("/api/libros", librosRoutes);
app.use("/api/notificaciones", notificacionesRoutes);

app.use("/api", (req, res) => res.status(404).json({ error: "Ruta no encontrada" }));

app.use((err, req, res, next) => {
    if (err.type === "entity.parse.failed") {
        return res.status(400).json({ error: "El cuerpo de la petición no es un JSON válido." });
    }
    console.error("Error no controlado:", err);
    res.status(500).json({ error: "Error interno del servidor" });
});

export default app;
