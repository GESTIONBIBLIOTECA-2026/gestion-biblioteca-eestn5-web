// Vercel detecta Express desde este punto de entrada.
import express from "express";
import backendApp from "./backend/app.js";

const app = express();
app.use(backendApp);

export default app;
