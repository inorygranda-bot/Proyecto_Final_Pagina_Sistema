const URL_SALIR = "../../PaginaPromocion/index.php";
const URL_LOGIN_FORM = "Login/login.php";
const URL_GESTION_API = "datos/gestion_api.php";

/* Fallback para evitar errores si otro módulo llama auditoría antes de tiempo */
if (typeof window.registrarAuditoria !== "function") {
    window.registrarAuditoria = function() {};
}

/**
 * La sesión se define en servidor (PHP $_SESSION).
 * Este script sólo enlaza ese JSON embebido en index.php como estado del cliente.
 */
function parseSesionPhpSeguro() {
    try {
        const tag = document.getElementById("__sesionPhp");
        if (!tag || !tag.textContent) return null;
        const parsed = JSON.parse(tag.textContent);
        return parsed && typeof parsed === "object" ? parsed : null;
    } catch (e) {
        console.warn("__sesionPhp no es JSON válido.", e);
        return null;
    }
}

let usuarioActivo = parseSesionPhpSeguro();

/* Redireccion automatica si se intenta entrar sin estar logueado */
if (!usuarioActivo) {
    if (!window.location.href.includes("Login/login.php")) {
        window.location.href = URL_LOGIN_FORM;
    }
}

/* Borrado de sesion y salida a la pagina promocional */
function cerrarSesion(e) {
    if (e) e.preventDefault();
    // Primero destruir sesion PHP, luego redirigir a pagina promocional
    window.location.href = "Login/controlador_login.php?salir=1";
}

/* Funcion para resetear el sistema durante pruebas de desarrollo */
function limpiarDatosPrueba() {
    if (!confirm("Esto eliminara TODOS los datos del sistema para pruebas. ¿Continuar?")) return;
    alert("Datos eliminados correctamente.");
    window.location.reload();
}

/* Helper para validar si el rol o permisos permiten ver un modulo */
function verificarAcceso(modulo) {
    if (!usuarioActivo) return false;
    const rolTxt = String(usuarioActivo.rol || "");
    const esAdmin = rolTxt === "admin" || /admin/i.test(rolTxt);
    const tienePermiso = usuarioActivo.permisos?.includes(modulo);
    return esAdmin || tienePermiso;
}

/* Actualiza los elementos de identidad en el encabezado */
function mostrarInfoUsuario() {
    if (!usuarioActivo) return;
    
    const spanUser = document.getElementById("NombreUsuarioUI");
    const spanRol = document.getElementById("MensajeRol");
    
    if (spanUser) {
        const nombre = usuarioActivo.nombre || usuarioActivo.usuario || "Usuario";
        spanUser.textContent = nombre;
    }
    if (spanRol) {
        const rolLimpio = (usuarioActivo.rol || "").replace("rol_", "").toUpperCase();
        spanRol.innerHTML = `Usted ingresó como: <strong>${rolLimpio}</strong>`;
    }
}

/* Control de visibilidad del menu lateral segun el usuario */
function aplicarMenuSegunRol() {
    mostrarInfoUsuario();
    
    // mapeo de id/selectores con sus permisos correspondientes
    const menuMap = [
        { id: "EnlaceResgistroUI", modulo: "registro" },
        { selector: 'a[href*="p=consulta"]', modulo: "consulta" },
        { selector: 'a[href*="p=horarios"]', modulo: "horarios" },
        { selector: 'a[href*="p=reportes"]', modulo: "reportes" },
        { id: "EnlaceGestionUI", modulo: "gestion" }
    ];

    menuMap.forEach(item => {
        let el = null;
        if (item.id) el = document.getElementById(item.id);
        if (!el && item.selector) el = document.querySelector(item.selector);
        
        if (el) {
            const visible = verificarAcceso(item.modulo);
            el.style.display = visible ? "" : "none";
            el.classList.toggle("sin-permiso", !visible);
        }
    });
    
    protegerAccesoDirecto();
}

/* Seguridad para que no entren escribiendo la URL manualmente */
function protegerAccesoDirecto() {
    const params = new URLSearchParams(window.location.search);
    const moduloActual = params.get("p") || "inicio";
    
    const modulosRestringidos = ["registro", "consulta", "horarios", "reportes", "gestion"];
    
    if (modulosRestringidos.includes(moduloActual) && !verificarAcceso(moduloActual)) {
        const mensaje = `⚠️ No tienes permisos para acceder al modulo "${moduloActual}".`;
        
        if (usuarioActivo.rol !== "admin") {
            alert(mensaje);
        }
        
        window.location.href = "index.php?p=inicio";
    }
}

/* Arranque de la logica al cargar el documento */
document.addEventListener("DOMContentLoaded", function() {
    aplicarMenuSegunRol();
    
    if (usuarioActivo) {
        console.log(" Sesion activa para:", usuarioActivo.usuario);
    }
});

/* Exportacion a window para que las funciones esten disponibles globalmente */
window.cerrarSesion = cerrarSesion;
window.limpiarDatosPrueba = limpiarDatosPrueba;
window.verificarAcceso = verificarAcceso;
window.usuarioActivo = usuarioActivo;

function registrarAuditoria(accion, detalle) {
    if (!usuarioActivo) {
        console.warn("Intento de registrar auditoría sin usuario activo.");
        return;
    }

    const usuario = usuarioActivo.usuario || "Desconocido";

    try {
        fetch(URL_GESTION_API, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
            body: new URLSearchParams({
                accion: "registrar_auditoria",
                usuario: usuario,
                accion_auditoria: accion,
                detalle: detalle,
            }),
        }).catch((err) => {
            console.error("No se pudo registrar la auditoría en BD:", err);
        });
    } catch (e) {
        console.error("Error al enviar auditoría a BD:", e);
    }
}

window.registrarAuditoria = registrarAuditoria;
