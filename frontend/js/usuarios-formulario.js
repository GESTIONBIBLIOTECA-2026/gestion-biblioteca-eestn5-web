/* ==========================================================================
   MÓDULO: usuarios-formulario.js - Ayudas del formulario de alta / modificación
   - generarClave(datos) ...... arma una contraseña con lo que ya se cargó
   - camposFaltantes(datos) ... obligatorios vacíos → { campo: "mensaje" }
   Son funciones puras: reciben los datos del formulario y devuelven un resultado.
   ========================================================================== */

const SIMBOLOS = "#$%&*!?";
const LETRAS = "abcdefghjkmnpqrstuvwxyz";

// Obligatorios para cualquier usuario → mensaje que se muestra debajo del campo
const OBLIGATORIOS = {
    nombre_completo: "Escribí el nombre y apellido.",
    dni: "Escribí el DNI.",
    correo: "Escribí el correo electrónico.",
    telefono: "Escribí el teléfono.",
    direccion: "Escribí el domicilio."
};

// 🧰 Utilidades
const azar = (texto) => texto[Math.floor(Math.random() * texto.length)];
const sinAcentos = (texto) => texto.normalize("NFD").replace(/[̀-ͯ]/g, "").replace(/[^a-zA-Z ]/g, "");
const capitalizar = (texto) => texto.charAt(0).toUpperCase() + texto.slice(1).toLowerCase();


/* --- "Martina González" + DNI 44123456 → "MartG3456#27" (cambia en cada clic) --- */
export function generarClave({ nombre_completo = "", dni = "" }) {
    const [nombre = "", ...resto] = sinAcentos(nombre_completo).trim().split(/\s+/);
    const apellido = resto.at(-1) ?? "";
    const numeros = dni.replace(/\D/g, "").slice(-4);

    const partes = [
        capitalizar(nombre.slice(0, 4)) || capitalizar(azar(LETRAS) + azar(LETRAS) + azar(LETRAS)),
        apellido.charAt(0).toUpperCase() || azar(LETRAS).toUpperCase(),
        numeros || String(Math.floor(1000 + Math.random() * 9000)),
        azar(SIMBOLOS),
        String(Math.floor(10 + Math.random() * 90))
    ];

    return partes.join("");   // siempre entre 8 y 13 caracteres (el backend pide 6 a 72)
}


/* --- Obligatorios vacíos. La clave solo en el alta; el curso solo para alumnos --- */
export function camposFaltantes(datos, esAlta) {
    const campos = {};
    const vacio = (campo) => !String(datos[campo] ?? "").trim();

    Object.keys(OBLIGATORIOS).filter(vacio).forEach((campo) => { campos[campo] = OBLIGATORIOS[campo]; });   // HOF .filter

    if (esAlta && vacio("clave")) campos.clave = "Escribí o generá una contraseña.";
    if (datos.rol === "alumno" && vacio("curso")) campos.curso = "El curso es obligatorio para los alumnos.";

    return campos;
}
