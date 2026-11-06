/* ==========================================================================
   MÓDULO: crear-pedido.js - Alta de pedidos (crear-pedido.html)
   - Usuarios y libros ... GET /api/pedidos/formulario
   - Alta de la reserva ... POST /api/pedidos
   La búsqueda y el filtrado se hacen en el navegador (igual que el módulo
   original), con los datos que trae la API del proyecto.
   ========================================================================== */

import { obtenerFormularioPedido, registrarPedido } from "./api.js";
import { mostrarAviso, marcarCampos } from "./ui.js";

// 🎯 1. Nodos del DOM
const formulario = document.querySelector("#formularioPedido");
const buscarUsuario = document.querySelector("#buscarUsuario");
const idUsuario = document.querySelector("#idUsuario");
const resultadosUsuarios = document.querySelector("#resultadosUsuarios");

const buscarLibro = document.querySelector("#buscarLibro");
const buscarAutor = document.querySelector("#buscarAutor");
const buscarEditorial = document.querySelector("#buscarEditorial");
const libroSeleccionado = document.querySelector("#libroSeleccionado");
const idLibro = document.querySelector("#idLibro");
const resultadosLibros = document.querySelector("#resultadosLibros");
const contadorLibros = document.querySelector("#contadorLibros");
const limpiarFiltros = document.querySelector("#limpiarFiltros");

const stock = document.querySelector("#stock");
const cantidad = document.querySelector("#cantidad");
const fechaPedido = document.querySelector("#fechaPedido");
const fechaDevolucion = document.querySelector("#fechaDevolucion");
const botonGuardar = document.querySelector("#botonGuardar");

// 🧠 2. Estado
let usuarios = [];
let libros = [];

// 🎨 3. Fechas (siempre en horario local, sin corrimientos)
function hoyISO() {
    const ahora = new Date();
    const local = new Date(ahora.getTime() - ahora.getTimezoneOffset() * 60000);
    return local.toISOString().slice(0, 10);
}

function sumarDias(fechaISO, dias) {
    const [anio, mes, dia] = fechaISO.split("-").map(Number);
    const fecha = new Date(anio, mes - 1, dia + dias);
    const local = new Date(fecha.getTime() - fecha.getTimezoneOffset() * 60000);
    return local.toISOString().slice(0, 10);
}

function ajustarFechaDevolucion() {
    const minimo = fechaPedido.value || hoyISO();
    fechaDevolucion.min = minimo;

    if (!fechaDevolucion.value || fechaDevolucion.value < minimo) {
        fechaDevolucion.value = sumarDias(minimo, 7);
    }
}

// 🎨 4. Búsqueda de usuarios (en el navegador)
function actualizarContadorLibros(total) {
    contadorLibros.textContent = `${total} libro${total === 1 ? "" : "s"}`;
}

buscarUsuario.addEventListener("input", () => {
    idUsuario.value = "";
    resultadosUsuarios.replaceChildren();

    const texto = buscarUsuario.value.trim().toLowerCase();
    if (!texto) return;

    const encontrados = usuarios.filter((usuario) =>
        `${usuario.nombre} ${usuario.dni}`.toLowerCase().includes(texto));

    encontrados.slice(0, 12).forEach((usuario) => {
        const opcion = document.createElement("button");
        opcion.type = "button";

        const nombre = document.createElement("strong");
        nombre.textContent = usuario.nombre;
        opcion.appendChild(nombre);

        const detalle = document.createElement("div");
        detalle.className = "detalle-libro";
        detalle.textContent = [
            `DNI ${usuario.dni}`,
            usuario.curso || usuario.rol || ""
        ].filter(Boolean).join(" | ");
        opcion.appendChild(detalle);

        opcion.addEventListener("click", () => {
            buscarUsuario.value = usuario.nombre;
            idUsuario.value = usuario.id_usuario;
            resultadosUsuarios.replaceChildren();
        });

        resultadosUsuarios.appendChild(opcion);
    });

    if (!encontrados.length) {
        const mensaje = document.createElement("p");
        mensaje.className = "sin-resultados";
        mensaje.textContent = "No se encontraron usuarios habilitados.";
        resultadosUsuarios.appendChild(mensaje);
    }
});

// 🎨 5. Filtro de libros (en el navegador)
function limpiarSeleccionLibro() {
    idLibro.value = "";
    libroSeleccionado.value = "";
    stock.value = "";
    cantidad.value = "";
    cantidad.max = "";
    cantidad.setCustomValidity("");
    resultadosLibros.replaceChildren();
}

function filtrarLibros() {
    limpiarSeleccionLibro();

    const titulo = buscarLibro.value.trim().toLowerCase();
    const autor = buscarAutor.value.trim().toLowerCase();
    const editorial = buscarEditorial.value.trim().toLowerCase();

    const encontrados = libros.filter((libro) =>
        (libro.titulo || "").toLowerCase().includes(titulo) &&
        (libro.autor || "").toLowerCase().includes(autor) &&
        (libro.editorial || "").toLowerCase().includes(editorial));

    actualizarContadorLibros(encontrados.length);

    encontrados.forEach((libro) => {
        const opcion = document.createElement("button");
        opcion.type = "button";

        const nombre = document.createElement("strong");
        nombre.textContent = libro.titulo;
        opcion.appendChild(nombre);

        const detalle = document.createElement("div");
        detalle.className = "detalle-libro";
        detalle.textContent = `Autor: ${libro.autor || "Sin autor"} | Editorial: ${libro.editorial || "Sin editorial"} | Stock: ${libro.stock}`;
        opcion.appendChild(detalle);

        opcion.addEventListener("click", () => {
            libroSeleccionado.value = libro.titulo;
            idLibro.value = libro.id_libro;
            stock.value = `${libro.stock} ejemplar${Number(libro.stock) === 1 ? "" : "es"}`;
            cantidad.max = libro.stock;
            cantidad.value = 1;
            cantidad.setCustomValidity("");
            resultadosLibros.replaceChildren();
        });

        resultadosLibros.appendChild(opcion);
    });

    if (!encontrados.length) {
        const mensaje = document.createElement("p");
        mensaje.className = "sin-resultados";
        mensaje.textContent = "No se encontraron libros con esos filtros.";
        resultadosLibros.appendChild(mensaje);
    }
}

buscarLibro.addEventListener("input", filtrarLibros);
buscarAutor.addEventListener("input", filtrarLibros);
buscarEditorial.addEventListener("input", filtrarLibros);

limpiarFiltros.addEventListener("click", () => {
    buscarLibro.value = "";
    buscarAutor.value = "";
    buscarEditorial.value = "";
    limpiarSeleccionLibro();
    actualizarContadorLibros(libros.length);
    buscarLibro.focus();
});

libroSeleccionado.addEventListener("input", () => {
    // El campo es de solo lectura: el libro se elige de los resultados.
    // Si se escribe encima, se invalida la selección para no guardar un id viejo.
    idLibro.value = "";
    stock.value = "";
    cantidad.value = "";
    cantidad.max = "";
});

// Si el usuario escribe la fecha de pedido, la devolución se acomoda
fechaPedido.addEventListener("change", ajustarFechaDevolucion);

// Cerrar los resultados al hacer clic afuera
document.addEventListener("click", (evento) => {
    if (!evento.target.closest(".buscador")) {
        resultadosUsuarios.replaceChildren();
        resultadosLibros.replaceChildren();
    }
});

cantidad.addEventListener("input", () => {
    cantidad.setCustomValidity(
        idLibro.value && (Number(cantidad.value) < 1 || Number(cantidad.value) > Number(cantidad.max))
            ? "Ingresá una cantidad válida según el stock disponible."
            : "");
});

// 🖱️ 6. Envío del formulario
formulario.addEventListener("submit", async (evento) => {
    evento.preventDefault();
    marcarCampos(formulario);

    if (!idUsuario.value) {
        mostrarAviso("Buscá y seleccioná un usuario.", true);
        buscarUsuario.focus();
        return;
    }

    if (!idLibro.value) {
        mostrarAviso("Buscá y seleccioná un libro de los resultados.", true);
        libroSeleccionado.focus();
        return;
    }

    if (Number(cantidad.value) < 1 || Number(cantidad.value) > Number(cantidad.max)) {
        mostrarAviso("La cantidad tiene que estar dentro del stock disponible.", true);
        cantidad.focus();
        return;
    }

    botonGuardar.disabled = true;
    const textoOriginal = botonGuardar.textContent;
    botonGuardar.textContent = "Guardando...";

    try {
        const respuesta = await registrarPedido({
            id_usuario: idUsuario.value,
            id_libro: idLibro.value,
            cantidad: cantidad.value,
            fecha_pedido: fechaPedido.value,
            fecha_devolucion: fechaDevolucion.value
        });

        const mensaje = respuesta.mensaje || "Pedido registrado correctamente.";
        window.location.href = `pedidos.html?mensaje=${encodeURIComponent(mensaje)}`;
    } catch (error) {
        mostrarAviso(error.status ? error.message : "No hay conexión con el servidor.", true);
        if (error.campos) marcarCampos(formulario, error.campos);
        botonGuardar.disabled = false;
        botonGuardar.textContent = textoOriginal;
    }
});

// 🌐 7. Carga inicial de usuarios y libros
async function cargarDatos() {
    try {
        const { payload } = await obtenerFormularioPedido();
        usuarios = payload.usuarios;
        libros = payload.libros;
        actualizarContadorLibros(libros.length);
    } catch (error) {
        mostrarAviso(error.status ? error.message : "No hay conexión con el servidor.", true);
    }
}

// 🚀 8. Inicialización
fechaPedido.value = hoyISO();
ajustarFechaDevolucion();
cargarDatos();
