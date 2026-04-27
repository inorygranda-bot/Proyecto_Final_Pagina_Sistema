/**
 * Gestión de Usuarios y Roles
 * Sincronizado con el sistema de registro y persistencia local
 */

/* Lectura y persistencia de datos */

let datosGestionCache = { usuarios: [], empresas: [], departamentos: [] };
let rolesCache = [];
let usuariosBdCache = [];

async function cargarDatosGestionBD() {
    const respuesta = await fetch("datos/gestion_api.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
        body: new URLSearchParams({ accion: "obtener_datos_sistema" }),
    });
    const resultado = await respuesta.json();
    if (resultado?.ok && resultado?.data?.datos) {
        datosGestionCache = resultado.data.datos;
    }
    return datosGestionCache;
}

async function guardarDatosGestion(datos) {
    datosGestionCache = datos;
    const respuesta = await fetch("datos/gestion_api.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
        body: new URLSearchParams({
            accion: "guardar_datos_sistema",
            datos: JSON.stringify(datosGestionCache),
        }),
    });
    const resultado = await respuesta.json();
    if (!resultado?.ok) throw new Error(resultado?.mensaje || "No se pudo guardar en BD.");
}

async function cargarRolesBD() {
    const respuesta = await fetch("datos/gestion_api.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
        body: new URLSearchParams({ accion: "obtener_roles_sistema" }),
    });
    const resultado = await respuesta.json();
    if (resultado?.ok && Array.isArray(resultado?.data?.roles)) {
        rolesCache = resultado.data.roles;
    }
    return rolesCache;
}

async function guardarRoles(roles) {
    rolesCache = roles;
    const respuesta = await fetch("datos/gestion_api.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
        body: new URLSearchParams({
            accion: "guardar_roles_sistema",
            roles: JSON.stringify(rolesCache),
        }),
    });
    const resultado = await respuesta.json();
    if (!resultado?.ok) throw new Error(resultado?.mensaje || "No se pudo guardar roles en BD.");
}

async function cargarUsuariosBD() {
    const respuesta = await fetch("datos/gestion_api.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
        body: new URLSearchParams({ accion: "listar_usuarios" }),
    });
    const resultado = await respuesta.json();
    if (resultado?.ok && Array.isArray(resultado?.data?.usuarios)) {
        usuariosBdCache = resultado.data.usuarios;
    }
    return usuariosBdCache;
}

/* Control de ventanas y modales */

window.cerrarModales = function() {
    // Limpia formularios y oculta todos los overlays activos
    document.querySelectorAll(".Overlay").forEach(el => el.style.display = "none");
    document.querySelectorAll("form").forEach(f => f.reset());
};

window.abrirListaRoles = async function() {
    const overlay = document.getElementById("OverlayListaRoles");
    if (overlay) {
        await renderizarRoles();
        overlay.style.display = "flex"; // Se usa flex para el centrado del CSS
    }
};

window.abrirModalRol = async function(id = null) {
    const roles = await cargarRolesBD();
    const rol = id ? roles.find(r => r.id === id) : null;
    const overlay = document.getElementById("OverlayRol");
    
    if (!overlay) return;

    document.getElementById("R_Id").value = id || "";
    document.getElementById("R_Nombre").value = rol?.nombre || "";
    document.getElementById("TituloModalRol").textContent = rol ? "Editar Rol" : "Nuevo Rol";

    // Módulos disponibles en el sistema para asignar permisos
    const modulos = ["registro", "consulta", "horarios", "reportes", "gestion"];
    const grid = document.getElementById("GridModulosRol");
    if (grid) {
        grid.innerHTML = modulos.map(m => `
            <label class="CheckItem">
                <input type="checkbox" value="${m}" ${rol?.permisos?.includes(m) ? "checked" : ""}>
                <span>${m.charAt(0).toUpperCase() + m.slice(1)}</span>
            </label>
        `).join("");
    }

    overlay.style.display = "flex"; 
};

window.abrirModalUsuario = async function(login = null) {
    try {
        const datos = await cargarDatosGestionBD();
        const usuarios = await cargarUsuariosBD();
        const empresas = datos.empresas || [];
        const roles = await cargarRolesBD();
        const user = login ? usuarios.find(u => u.usuario === login) : null;

        const overlay = document.getElementById("OverlayUsuario");
        if (!overlay) return;

        // Carga de datos en los campos del formulario
        document.getElementById("U_EditarLogin").value = user?.usuario || "";
        document.getElementById("U_Nombre").value = user?.usuario || "";
        document.getElementById("U_Login").value = user?.usuario || "";
        document.getElementById("U_Clave").value = "";
        document.getElementById("U_Estado").value = String(user?.es_activo ?? "1");
        document.getElementById("TituloModalUsuario").textContent = user ? "Editar Usuario" : "Nuevo Usuario";

        const uRol = document.getElementById("U_Rol");
        if (uRol) {
            uRol.innerHTML = '<option value="">Selecciona un rol...</option>' + 
                roles.map(r => `<option value="${r.nombre}" ${user?.nombre_rol === r.nombre ? "selected" : ""}>${r.nombre}</option>`).join("");
        }

        const gridEmp = document.getElementById("GridEmpresasUsuario");
        if (gridEmp) {
            const asignadas = user?.empresas_asignadas || [];
            gridEmp.innerHTML = empresas.length ? empresas.map(e => `
                <label class="CheckItem">
                    <input type="checkbox" value="${e.rif}" ${asignadas.includes(e.rif) ? "checked" : ""}>
                    <span>${e.nombre} (${e.rif})</span>
                </label>
            `).join("") : '<p class="TextoGris">No hay empresas registradas aún.</p>';
        }

        overlay.style.display = "flex"; 
    } catch (error) {
        console.error("Error al abrir modal de usuario:", error);
    }
};

/* Renderizado de las tablas de datos */

async function renderizarRoles() {
    const tbody = document.querySelector("#TablaRoles tbody");
    if (!tbody) return;
    const roles = await cargarRolesBD();

    tbody.innerHTML = roles.length ? roles.map(r => `
        <tr>
            <td><strong>${r.nombre}</strong></td>
            <td><small>${r.permisos?.join(", ") || "Ninguno"}</small></td>
            <td class="Acciones">
                <button onclick="window.abrirModalRol('${r.id}')" title="Editar"><i class="fas fa-edit"></i></button>
                <button class="Eliminar" onclick="window.eliminarRol('${r.id}')" title="Eliminar"><i class="fas fa-trash"></i></button>
            </td>
        </tr>
    `).join("") : '<tr><td colspan="3" class="VacioTabla">No hay roles definidos.</td></tr>';
}

async function renderizarUsuarios() {
    const tbody = document.querySelector("#TablaUsuarios tbody");
    if (!tbody) return;
    const usuarios = await cargarUsuariosBD();

    tbody.innerHTML = usuarios.length ? usuarios.map(u => {
        return `
            <tr>
                <td><strong>${u.usuario}</strong></td>
                <td>${u.usuario}</td>
                <td><span class="Badge">${u.nombre_rol || "Sin rol"}</span></td>
                <td><span class="Estado ${String(u.es_activo) === '1' ? 'activo' : 'inactivo'}">${String(u.es_activo) === '1' ? 'Activo' : 'Inactivo'}</span></td>
                <td class="Acciones">
                    <button onclick="window.abrirModalUsuario('${u.usuario}')" title="Editar"><i class="fas fa-edit"></i></button>
                    <button class="Eliminar" onclick="window.eliminarUsuario('${u.usuario}')" title="Eliminar"><i class="fas fa-trash"></i></button>
                </td>
            </tr>
        `;
    }).join("") : '<tr><td colspan="5" class="VacioTabla">No hay usuarios registrados.</td></tr>';
}

/* Procesamiento de guardado */

window.guardarRol = async function(e) {
    e.preventDefault();
    const id = document.getElementById("R_Id").value;
    const nombre = document.getElementById("R_Nombre").value.trim();
    const permisos = Array.from(document.querySelectorAll("#GridModulosRol input:checked")).map(cb => cb.value);

    if (!nombre || !permisos.length) return alert("Ingrese el nombre y al menos un permiso.");

    let roles = await cargarRolesBD();
    if (roles.find(r => r.nombre.toLowerCase() === nombre.toLowerCase() && r.id !== id)) {
        return alert("Ya existe un rol con ese nombre.");
    }

    if (id) {
        const idx = roles.findIndex(r => r.id === id);
        roles[idx] = { ...roles[idx], nombre, permisos };
        window.registrarAuditoria("Editó Rol", `Rol: ${nombre} (ID: ${id})`); // Auditoría
    } else {
        roles.push({ id: "rol_" + Date.now(), nombre, permisos });
        window.registrarAuditoria("Creó Rol", `Rol: ${nombre}`); // Auditoría
    }

    await guardarRoles(roles);
    
    const overlayRol = document.getElementById("OverlayRol");
    if (overlayRol) overlayRol.style.display = "none";
    
    await renderizarRoles();
    await renderizarUsuarios();
};

window.guardarUsuario = async function(e) {
    e.preventDefault();
    const usuarios = await cargarUsuariosBD();

    const editar = document.getElementById("U_EditarLogin").value;
    const login = document.getElementById("U_Login").value.trim();
    const clave = document.getElementById("U_Clave").value;
    const rol = document.getElementById("U_Rol").value;
    const estado = document.getElementById("U_Estado").value;

    if (usuarios.find(u => u.usuario.toLowerCase() === login.toLowerCase() && u.usuario !== editar)) {
        return alert("El nombre de usuario ya está en uso.");
    }

    if (editar) {
        const respuesta = await fetch("datos/gestion_api.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
            body: new URLSearchParams({
                accion: "actualizar_usuario",
                usuario_original: editar,
                usuario: login,
                password: clave,
                rol,
                estado,
            }),
        });
        const resultado = await respuesta.json();
        if (!resultado?.ok) {
            return alert(resultado?.mensaje || "No se pudo actualizar usuario en base de datos.");
        }
        window.registrarAuditoria("Editó Usuario", `Usuario: ${login}`);
    } else {
        if (!clave) return alert("La contraseña es obligatoria para nuevos usuarios.");
        const respuesta = await fetch("datos/gestion_api.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
            body: new URLSearchParams({
                accion: "crear_usuario",
                usuario: login,
                password: clave,
                rol,
            }),
        });
        const resultado = await respuesta.json();
        if (!resultado?.ok) {
            return alert(resultado?.mensaje || "No se pudo crear usuario en base de datos.");
        }
        window.registrarAuditoria("Creó Usuario", `Usuario: ${login}`);
    }
    window.cerrarModales();
    await renderizarUsuarios();
};

/* Funciones de borrado */

window.eliminarRol = async function(id) {
    if (!confirm("¿Seguro que desea eliminar este rol? Los usuarios asociados podrían perder sus permisos.")) return;
    let roles = await cargarRolesBD();
    const rolEliminado = roles.find(r => r.id === id); // Obtener el rol antes de eliminarlo
    roles = roles.filter(r => r.id !== id);
    await guardarRoles(roles);
    if (rolEliminado) {
        window.registrarAuditoria("Eliminó Rol", `Rol: ${rolEliminado.nombre} (ID: ${id})`); // Auditoría
    }
    await renderizarRoles();
    await renderizarUsuarios();
};

window.eliminarUsuario = async function(login) {
    if (!confirm(`¿Desea eliminar definitivamente al usuario ${login}?`)) return;
    const respuesta = await fetch("datos/gestion_api.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
        body: new URLSearchParams({
            accion: "eliminar_usuario",
            usuario: login,
        }),
    });
    const resultado = await respuesta.json();
    if (!resultado?.ok) {
        return alert(resultado?.mensaje || "No se pudo eliminar usuario en base de datos.");
    }
    window.registrarAuditoria("Eliminó Usuario", `Usuario: ${login}`);
    await renderizarUsuarios();
};

/* Buscador y carga inicial */

window.filtrarUsuarios = function() {
    const texto = document.getElementById("BuscadorUsuarios")?.value.toLowerCase() || "";
    const filas = document.querySelectorAll("#TablaUsuarios tbody tr");
    filas.forEach(f => {
        if (f.querySelector(".VacioTabla")) return;
        f.style.display = f.textContent.toLowerCase().includes(texto) ? "" : "none";
    });
};

document.addEventListener("DOMContentLoaded", () => {
    renderizarRoles();
    renderizarUsuarios();
});