let nivelActual = "";
let idActual = null;
let empresaActual = "";
let deptoActual = "";
let idEmpleadoActual = null;
let fechaActual = new Date();
let fechaGlobal = null;

function obtenerHorarioActualParaInputs(fecha) {
    asegurarCalendarios();
    const fs = fecha.toISOString().split("T")[0];
    const dow = String(fecha.getDay());
    const capa = calCapaNivel();
    const idEnt = calIdEntidadActiva();

    let h = datosSistema.calendarios.horariosFecha[capa]?.[idEnt]?.[fs];
    
    if (!h) {
        h = datosSistema.calendarios.horariosSemana[capa]?.[idEnt]?.[dow];
    }
    
    if (!h) {
        const clave = keyHorarioActual();
        const hCapas = datosSistema.calendarios.horarios;
        if (clave.tipo === "general") {
            h = hCapas.general;
        } else {
            h = hCapas[clave.tipo][clave.id] || hCapas.general;
        }
    }

    return h || { desdeM: "", hastaM: "", desdeT: "", hastaT: "" };
}

function parseDatosGestionSeguro() {
    return datosSistema && typeof datosSistema === "object" ? datosSistema : {};
}

let datosSistema = parseDatosGestionSeguro();

const CAL_ID_GLOBAL = "_";

function asegurarCalendarios() {
    if (!datosSistema.calendarios) datosSistema.calendarios = {};
    if (!datosSistema.calendarios.general) datosSistema.calendarios.general = {};
    if (!datosSistema.calendarios.empresas) datosSistema.calendarios.empresas = {};
    if (!datosSistema.calendarios.departamentos) datosSistema.calendarios.departamentos = {};
    if (!datosSistema.calendarios.empleados) datosSistema.calendarios.empleados = {};
    if (!datosSistema.calendarios.horarios) {
        datosSistema.calendarios.horarios = {
            general: null,
            empresas: {},
            departamentos: {},
            empleados: {}
        };
    }
    if (!datosSistema.calendarios.horarios.empleados) datosSistema.calendarios.horarios.empleados = {};
    const capas = ["general", "empresas", "departamentos", "empleados"];
    if (!datosSistema.calendarios.horariosFecha) datosSistema.calendarios.horariosFecha = {};
    if (!datosSistema.calendarios.horariosSemana) datosSistema.calendarios.horariosSemana = {};
    if (!datosSistema.calendarios.feriadosSemana) datosSistema.calendarios.feriadosSemana = {};
    capas.forEach((k) => {
        if (!datosSistema.calendarios.horariosFecha[k]) datosSistema.calendarios.horariosFecha[k] = {};
        if (!datosSistema.calendarios.horariosSemana[k]) datosSistema.calendarios.horariosSemana[k] = {};
        if (!datosSistema.calendarios.feriadosSemana[k]) datosSistema.calendarios.feriadosSemana[k] = {};
    });
}

async function guardarDatosCalendario() {
    asegurarCalendarios();
    const respuesta = await fetch("datos/gestion_api.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
        body: new URLSearchParams({
            accion: "guardar_datos_sistema",
            datos: JSON.stringify(datosSistema),
        }),
    });
    const resultado = await respuesta.json();
    if (!resultado?.ok) {
        throw new Error(resultado?.mensaje || "No se pudo guardar calendario en base de datos.");
    }
}

async function cargarDatosCalendarioDesdeBD() {
    try {
        const respuesta = await fetch("datos/gestion_api.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
            body: new URLSearchParams({ accion: "obtener_datos_sistema" }),
        });
        const resultado = await respuesta.json();
        if (resultado?.ok && resultado?.data?.datos) {
            datosSistema = resultado.data.datos;
        } else {
            datosSistema = {};
        }
    } catch (error) {
        console.error("No se pudo cargar calendario desde BD:", error);
        datosSistema = {};
    }
    asegurarCalendarios();
}

function obtenerDatoHeredado(tipo, idEmpresa, idDepto = null, idEmpleado = null) {
    const h = datosSistema.calendarios.horarios;
    if (idEmpleado && h.empleados[idEmpleado] && h.empleados[idEmpleado].desdeM !== "") {
        return h.empleados[idEmpleado];
    }
    if (idDepto && h.departamentos[idDepto] && h.departamentos[idDepto].desdeM !== "") {
        return h.departamentos[idDepto];
    }
    if (idEmpresa && h.empresas[idEmpresa] && h.empresas[idEmpresa].desdeM !== "") {
        return h.empresas[idEmpresa];
    }
    return h.general;
}

function calIdEntidadActiva() {
    if (nivelActual === "general") return CAL_ID_GLOBAL;
    if (nivelActual === "empresas") return idActual || CAL_ID_GLOBAL;
    if (nivelActual === "departamentos") return idActual || CAL_ID_GLOBAL;
    if (nivelActual === "empleados") return idEmpleadoActual || CAL_ID_GLOBAL;
    return CAL_ID_GLOBAL;
}

function calCapaNivel() {
    if (nivelActual === "general") return "general";
    if (nivelActual === "empresas") return "empresas";
    if (nivelActual === "departamentos") return "departamentos";
    if (nivelActual === "empleados") return "empleados";
    return "general";
}

function keyHorarioActual() {
    if (nivelActual === "general") return { tipo: "general", id: "general" };
    if (nivelActual === "empresas") return { tipo: "empresas", id: empresaActual };
    if (nivelActual === "departamentos") return { tipo: "departamentos", id: deptoActual };
    if (nivelActual === "empleados") return { tipo: "empleados", id: idEmpleadoActual };
    return { tipo: "general", id: "general" };
}

function cargarHorarioActual() {
    const hCapas = datosSistema.calendarios.horarios;
    let r = null;
    if (nivelActual === "empleados") {
        r = hCapas.empleados[idEmpleadoActual];
    } else if (nivelActual === "departamentos") {
        r = hCapas.departamentos[deptoActual];
    } else if (nivelActual === "empresas") {
        r = hCapas.empresas[empresaActual];
    }
    if (!r) r = hCapas.general;
    document.getElementById("HoraDesdeM").value = (r && r.desdeM) ? r.desdeM : "";
    document.getElementById("HoraHastaM").value = (r && r.hastaM) ? r.hastaM : "";
    document.getElementById("HoraDesdeT").value = (r && r.desdeT) ? r.desdeT : "";
    document.getElementById("HoraHastaT").value = (r && r.hastaT) ? r.hastaT : "";
}

async function guardarHorarioActual(e) {
    e.preventDefault();
    const h = {
        desdeM: document.getElementById("HoraDesdeM").value,
        hastaM: document.getElementById("HoraHastaM").value,
        desdeT: document.getElementById("HoraDesdeT").value,
        hastaT: document.getElementById("HoraHastaT").value
    };
    if (!h.desdeM || !h.hastaM) return alert("El turno de la mañana es obligatorio.");
    const clave = keyHorarioActual();
    const capa = datosSistema.calendarios.horarios;
    if (clave.tipo === "general") capa.general = h;
    else if (clave.tipo === "empresas") capa.empresas[clave.id] = h;
    else if (clave.tipo === "departamentos") capa.departamentos[clave.id] = h;
    else if (clave.tipo === "empleados") capa.empleados[clave.id] = h;
    window.registrarAuditoria("Guardó Horario General/Capa", `Horario general/capa ${clave.tipo} para ID: ${clave.id} (desdeM: ${h.desdeM}, hastaM: ${h.hastaM}, desdeT: ${h.desdeT}, hastaT: ${h.hastaT})`);
    await guardarDatosCalendario();
    renderizarCalendario();
    alert("Horarios de doble turno guardados.");
}

function normalizarFeriadoValor(v) {
    if (v == null) return null;
    if (typeof v === "string") return { motivo: v, laboral: false };
    if (typeof v === "object" && v.hasOwnProperty('motivo')) return { motivo: String(v.motivo), laboral: !!v.laboral };
    return null;
}

function leerFeriadoEnCapa(capa, idEnt, fs, dowStr) {
    const c = datosSistema.calendarios;
    if (capa === "general") {
        const porFecha = c.general[fs];
        const n1 = normalizarFeriadoValor(porFecha);
        if (n1) return { ...n1, capa: "general", idEnt: CAL_ID_GLOBAL, origen: "fecha" };
        const sem = c.feriadosSemana?.general?.[CAL_ID_GLOBAL]?.[dowStr];
        const n2 = normalizarFeriadoValor(sem);
        if (n2) return { ...n2, capa: "general", idEnt: CAL_ID_GLOBAL, origen: "semana" };
        return null;
    }
    const mapa = c[capa];
    if (!mapa || !idEnt) return null;
    const porFecha = mapa[idEnt]?.[fs];
    const n1 = normalizarFeriadoValor(porFecha);
    if (n1) return { ...n1, capa, idEnt, origen: "fecha" };
    const sem = c.feriadosSemana?.[capa]?.[idEnt]?.[dowStr];
    const n2 = normalizarFeriadoValor(sem);
    if (n2) return { ...n2, capa, idEnt, origen: "semana" };
    return null;
}

function obtenerEstadoDia(fecha){
    const fs = fecha.toISOString().split("T")[0];
    const dowStr = String(fecha.getDay());
    let hallado = null;

    if(idEmpleadoActual) hallado = leerFeriadoEnCapa("empleados", idEmpleadoActual, fs, dowStr);
    if(!hallado && deptoActual) hallado = leerFeriadoEnCapa("departamentos", deptoActual, fs, dowStr);
    if(!hallado && empresaActual) hallado = leerFeriadoEnCapa("empresas", empresaActual, fs, dowStr);
    if(!hallado) hallado = leerFeriadoEnCapa("general", CAL_ID_GLOBAL, fs, dowStr);

    if(hallado){
        return {
            tipo: hallado.capa,
            motivo: hallado.motivo,
            laboral: hallado.laboral,
            origen: hallado.origen,
            capa: hallado.capa,
            idEnt: hallado.idEnt
        };
    }

    return {tipo: "laboral", motivo: "", laboral: true, origen: null, capa: null, idEnt: null};
}

function textoHorarioParaDia(fecha) {
    asegurarCalendarios();
    const fs = fecha.toISOString().split("T")[0];
    const dow = String(fecha.getDay());
    const capa = calCapaNivel();
    const idEnt = calIdEntidadActiva();
    let h = datosSistema.calendarios.horariosFecha[capa]?.[idEnt]?.[fs];
    if (!h) h = datosSistema.calendarios.horariosSemana[capa]?.[idEnt]?.[dow];
    if (!h) {
        const clave = keyHorarioActual();
        const hCapas = datosSistema.calendarios.horarios;
        if (clave.tipo === "general") h = hCapas.general;
        else h = hCapas[clave.tipo][clave.id] || hCapas.general;
    }
    if (h && h.desdeM && h.hastaM) {
        let texto = `${h.desdeM}-${h.hastaM}`;
        if (h.desdeT && h.hastaT) {
            texto += `<br>${h.desdeT}-${h.hastaT}`;
        }
        return texto;
    }
    return "";
}

function nombreDiaSemanaES(dow) {
    const n = ["domingo", "lunes", "martes", "miércoles", "jueves", "viernes", "sábado"];
    return n[dow] || "día";
}

function guardarHorarioDia(fecha, soloEstaFecha) {
    asegurarCalendarios();
    const capa = calCapaNivel();
    const idEnt = calIdEntidadActiva();
    const fs = fecha.toISOString().split("T")[0];
    const dow = String(fecha.getDay());
    const nuevoHorario = {
        desdeM: document.getElementById("HoraDesdeM").value,
        hastaM: document.getElementById("HoraHastaM").value,
        desdeT: document.getElementById("HoraDesdeT").value,
        hastaT: document.getElementById("HoraHastaT").value
    };
    if (!nuevoHorario.desdeM || !nuevoHorario.hastaM) {
        return alert("El turno de la mañana es obligatorio para asignar un horario.");
    }
    if (!datosSistema.calendarios.horariosFecha[capa][idEnt]) datosSistema.calendarios.horariosFecha[capa][idEnt] = {};
    if (!datosSistema.calendarios.horariosSemana[capa][idEnt]) datosSistema.calendarios.horariosSemana[capa][idEnt] = {};
    if (soloEstaFecha) {
        datosSistema.calendarios.horariosFecha[capa][idEnt][fs] = nuevoHorario;
        window.registrarAuditoria("Guardó Horario por Fecha", `Horario para fecha ${fs} (Capa: ${capa}, ID: ${idEnt}, desdeM: ${nuevoHorario.desdeM})`);
    } else {
        datosSistema.calendarios.horariosSemana[capa][idEnt][dow] = nuevoHorario;
        window.registrarAuditoria("Guardó Horario por Semana", `Horario para día de semana ${dow} (Capa: ${capa}, ID: ${idEnt}, desdeM: ${nuevoHorario.desdeM})`);
    }
    guardarDatosCalendario();
    renderizarCalendario();
}

function guardarFeriadoEnCapa(capa, idEnt, fs, dowStr, motivo, laboral, soloEstaFecha) {
    asegurarCalendarios();
    const val = { motivo, laboral };
    if (!datosSistema.calendarios.feriadosSemana[capa]) datosSistema.calendarios.feriadosSemana[capa] = {};
    if (!datosSistema.calendarios.feriadosSemana[capa][idEnt]) datosSistema.calendarios.feriadosSemana[capa][idEnt] = {};
    if (soloEstaFecha) {
        if (capa === "general") {
            datosSistema.calendarios.general[fs] = val;
            window.registrarAuditoria("Guardó Feriado por Fecha", `Feriado para fecha ${fs} (General, Motivo: ${motivo}, Laboral: ${laboral})`);
        } else {
            if (!datosSistema.calendarios[capa][idEnt]) datosSistema.calendarios[capa][idEnt] = {};
            datosSistema.calendarios[capa][idEnt][fs] = val;
            window.registrarAuditoria("Guardó Feriado por Fecha", `Feriado para fecha ${fs} (Capa: ${capa}, ID: ${idEnt}, Motivo: ${motivo}, Laboral: ${laboral})`);
        }
    } else {
        datosSistema.calendarios.feriadosSemana[capa][idEnt][dowStr] = val;
        window.registrarAuditoria("Guardó Feriado por Semana", `Feriado para día de semana ${dowStr} (Capa: ${capa}, ID: ${idEnt}, Motivo: ${motivo}, Laboral: ${laboral})`);
    }
}

function eliminarFeriadoActivo(fecha, estado) {
    asegurarCalendarios();
    const fs = fecha.toISOString().split("T")[0];
    const dowStr = String(fecha.getDay());
    const capaActual = calCapaNivel();
    const idEntActual = calIdEntidadActiva();

    // Si el feriado viene de arriba(es heredado)
    if (estado.capa !== capaActual || String(estado.idEnt) !== String(idEntActual)) {
        guardarFeriadoEnCapa(capaActual, idEntActual, fs, dowStr, "", true, true);
        window.registrarAuditoria("Eliminó Feriado Heredado (Override)", `Feriado heredado en fecha ${fs} (Capa: ${capaActual}, ID: ${idEntActual}) se ocultó/overrideó.`);
    }
    else {
        if (estado.motivo === "" && estado.laboral === true && estado.origen !== "semana") {
        } else {
            let detalleAccion = `Feriado: ${estado.motivo || 'N/A'} (Laboral: ${estado.laboral}) en fecha ${fs} (Capa: ${estado.capa}, ID: ${estado.idEnt})`;
            if (estado.origen === "semana") {
                if (datosSistema.calendarios.feriadosSemana[capaActual]?.[idEntActual]) {
                    delete datosSistema.calendarios.feriadosSemana[capaActual][idEntActual][dowStr];
                    window.registrarAuditoria("Eliminó Feriado Semanal", detalleAccion);
                }
            } else if (capaActual === "general") {
                delete datosSistema.calendarios.general[fs];
                window.registrarAuditoria("Eliminó Feriado General", detalleAccion);
            } else if (datosSistema.calendarios[capaActual]?.[idEntActual]) {
                delete datosSistema.calendarios[capaActual][idEntActual][fs];
                window.registrarAuditoria("Eliminó Feriado", detalleAccion);
            }
        }
    }

    guardarDatosCalendario();
    renderizarCalendario();
}

function actualizarFeriadoExistente(fecha, estado, motivo, laboral) {
    asegurarCalendarios();
    const fs = fecha.toISOString().split("T")[0];
    const dowStr = String(fecha.getDay());
    const capa = estado.capa;
    const idEnt = estado.idEnt;
    const val = { motivo: String(motivo).trim(), laboral: !!laboral };
    if (estado.origen === "semana") {
        if (!datosSistema.calendarios.feriadosSemana[capa]) datosSistema.calendarios.feriadosSemana[capa] = {};
        if (!datosSistema.calendarios.feriadosSemana[capa][idEnt]) datosSistema.calendarios.feriadosSemana[capa][idEnt] = {};
        datosSistema.calendarios.feriadosSemana[capa][idEnt][dowStr] = val;
        window.registrarAuditoria("Actualizó Feriado Semanal", `Feriado para día de semana ${dowStr} (Capa: ${capa}, ID: ${idEnt}, Motivo: ${motivo}, Laboral: ${laboral})`);
    } else if (capa === "general") {
        datosSistema.calendarios.general[fs] = val;
        window.registrarAuditoria("Actualizó Feriado General", `Feriado para fecha ${fs} (General, Motivo: ${motivo}, Laboral: ${laboral})`);
    } else {
        if (!datosSistema.calendarios[capa][idEnt]) datosSistema.calendarios[capa][idEnt] = {};
        datosSistema.calendarios[capa][idEnt][fs] = val;
        window.registrarAuditoria("Actualizó Feriado", `Feriado para fecha ${fs} (Capa: ${capa}, ID: ${idEnt}, Motivo: ${motivo}, Laboral: ${laboral})`);
    }
    guardarDatosCalendario();
    renderizarCalendario();
}

function eliminarHorarioDia(fecha) {
    asegurarCalendarios();
    const capa = calCapaNivel();
    const idEnt = calIdEntidadActiva();
    const fs = fecha.toISOString().split("T")[0];
    const dowStr = String(fecha.getDay());
    if (datosSistema.calendarios.horariosFecha[capa]?.[idEnt]) {
        delete datosSistema.calendarios.horariosFecha[capa][idEnt][fs];
        window.registrarAuditoria("Eliminó Horario por Fecha", `Horario eliminado para fecha ${fs} (Capa: ${capa}, ID: ${idEnt})`);
    }
    if (datosSistema.calendarios.horariosSemana[capa]?.[idEnt]) {
        delete datosSistema.calendarios.horariosSemana[capa][idEnt][dowStr];
        window.registrarAuditoria("Eliminó Horario por Semana", `Horario eliminado para día de semana ${dowStr} (Capa: ${capa}, ID: ${idEnt})`);
    }
    guardarDatosCalendario();
    renderizarCalendario();
}

function abrirEditorDia(fecha, estado) {
    fechaGlobal = fecha;
    const fs = fecha.toISOString().split("T")[0];
    const modal = document.getElementById("editor");
    const btnEliminar = document.getElementById("borrarEvento");

    document.getElementById("fecha").textContent = fs;
    document.getElementById("motivo").value = estado.motivo || "";
    document.getElementById("laboral").checked = estado.laboral;

    if (estado.origen || (estado.motivo && estado.motivo.trim() !== "")) {
        btnEliminar.style.display = "block";
    } else {
        btnEliminar.style.display = "none";
    }

    btnEliminar.onclick = () => { 
        eliminarFeriadoActivo(fecha, estado); 
        cerrar(); 
    };

    const h = obtenerHorarioActualParaInputs(fecha);
    document.getElementById("m1").value = h.desdeM || "";
    document.getElementById("m2").value = h.hastaM || "";
    document.getElementById("t1").value = h.desdeT || "";
    document.getElementById("t2").value = h.hastaT || "";

    document.getElementById("guardarDia").onclick = () => procesarGuardado(true);
    document.getElementById("guardarSiempre").onclick = () => procesarGuardado(false);
    
    modal.showModal();
}

function cerrar() {
    document.getElementById("editor").close();
}

async function procesarGuardado(esUnico) {
    const mot = document.getElementById("motivo").value;
    const lab = document.getElementById("laboral").checked;
    const fs = fechaGlobal.toISOString().split("T")[0];
    const dow = String(fechaGlobal.getDay());

    const capaDestino = calCapaNivel();
    const idEntDestino = calIdEntidadActiva();

    guardarFeriadoEnCapa(capaDestino, idEntDestino, fs, dow, mot, lab, esUnico);

    const h1 = document.getElementById("m1").value;
    const h2 = document.getElementById("m2").value;
    if (h1 && h2) {
        const nuevo = {
            desdeM: h1,
            hastaM: h2,
            desdeT: document.getElementById("t1").value,
            hastaT: document.getElementById("t2").value
        };

        if (esUnico) {
            if (!datosSistema.calendarios.horariosFecha[capaDestino][idEntDestino])
                datosSistema.calendarios.horariosFecha[capaDestino][idEntDestino] = {};
            datosSistema.calendarios.horariosFecha[capaDestino][idEntDestino][fs] = nuevo;
        } else {
            if (!datosSistema.calendarios.horariosSemana[capaDestino][idEntDestino])
                datosSistema.calendarios.horariosSemana[capaDestino][idEntDestino] = {};
            datosSistema.calendarios.horariosSemana[capaDestino][idEntDestino][dow] = nuevo;
        }
    }

    await guardarDatosCalendario();
    renderizarCalendario();
    cerrar();
}

function renderizarCalendario() {
    const cont = document.getElementById("CuadriculaCalendario");
    if (!cont) return;
    if (!nivelActual || nivelActual === "") {
        cont.innerHTML = "<p style='padding:20px; text-align:center; color: #666;'>Seleccione una empresa para ver el calendario.</p>";
        return;
    }
    const mes = fechaActual.getMonth();
    const ano = fechaActual.getFullYear();
    const nombresMeses = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
    const etiqueta = document.getElementById("TextoMesAno");
    if (etiqueta) etiqueta.textContent = `${nombresMeses[mes]} ${ano}`;
    cont.innerHTML = "";
    let primerDiaSemana = new Date(ano, mes, 1).getDay();
    const blancos = (primerDiaSemana === 0) ? 6 : primerDiaSemana - 1;
    for (let i = 0; i < blancos; i++) {
        const celdaVacia = document.createElement("article");
        celdaVacia.className = "DiaCalendario vacio";
        cont.appendChild(celdaVacia);
    }
    const ultimoDiaMes = new Date(ano, mes + 1, 0).getDate();
    for (let d = 1; d <= ultimoDiaMes; d++) {
        const fechaDia = new Date(ano, mes, d);
        const estado = obtenerEstadoDia(fechaDia);
        const celda = document.createElement("article");
        celda.className = "DiaCalendario";
        if (estado.motivo && estado.motivo.trim() !== "") {
    celda.classList.add(estado.laboral ? "feriado-laboral" : "feriado");
}
        let horarioTexto = "";
        if (estado.tipo === "laboral" || estado.laboral === true) {
            horarioTexto = textoHorarioParaDia(fechaDia);
        }
        
        const subInfo = horarioTexto ? `<small class="dia-horario-extra">${horarioTexto}</small>` : "";
        celda.innerHTML = `<time>${d}</time><p>${estado.motivo || ""}</p>${subInfo}`;
        celda.onclick = (e) => {
            e.preventDefault();
            abrirEditorDia(new Date(ano, mes, d), estado);
        };
        cont.appendChild(celda);
    }
    cargarHorarioActual();
}

function mostrarCalendario() {
    const vista = document.getElementById("Calendario");
    if (!vista) return;
    const consulta = document.getElementById("Consulta");
    if (consulta) {
        consulta.style.setProperty("display", "none", "important");
    }
    vista.style.display = "block";
    calRellenarSelectContexto();
    renderizarCalendario();
}

function cerrarCalendario() {
    const vistaConsulta = document.getElementById("Consulta");
    const vistaCalendario = document.getElementById("Calendario");
    if (vistaConsulta && vistaCalendario) {
        vistaCalendario.style.display = "none";
        vistaConsulta.style.display = "flex";
        if (typeof renderizarListaEmpresas === "function") renderizarListaEmpresas();
        return;
    }
    window.location.href = "index.php";
}

function cambiarMes(d) {
    fechaActual.setMonth(fechaActual.getMonth() + d);
    renderizarCalendario();
}

function verCalendarioGeneral() {
    nivelActual = "general";
    idActual = null;
    empresaActual = "";
    deptoActual = "";
    idEmpleadoActual = null;
    document.getElementById("EtiquetaPrincipal").textContent = "Calendario maestro";
    document.getElementById("EtiquetaSubtitulo").textContent = "Nivel: Global";
    mostrarCalendario();
}

function verCalendarioEmpresa(n) {
    nivelActual = "empresas";
    idActual = n;
    empresaActual = n;
    deptoActual = "";
    idEmpleadoActual = null;
    document.getElementById("EtiquetaPrincipal").textContent = `Calendario de: ${n}`;
    document.getElementById("EtiquetaSubtitulo").textContent = "Nivel: Empresa";
    mostrarCalendario();
}

function verCalendarioDepartamento(d, e) {
    nivelActual = "departamentos";
    idActual = d;
    deptoActual = d;
    empresaActual = e;
    idEmpleadoActual = null;
    document.getElementById("EtiquetaPrincipal").textContent = `Calendario de: ${d}`;
    document.getElementById("EtiquetaSubtitulo").textContent = `Nivel: Departamento (${e})`;
    mostrarCalendario();
}

function verCalendarioDeEmpleado(ced, nom, dep, emp) {
    nivelActual = "empleados";
    idActual = ced;
    idEmpleadoActual = ced;
    deptoActual = dep;
    empresaActual = emp;
    document.getElementById("EtiquetaPrincipal").textContent = `Calendario de: ${nom}`;
    document.getElementById("EtiquetaSubtitulo").textContent = `Nivel: Empleado (${dep})`;
    mostrarCalendario();
}

function calRellenarSelectContexto(origen) {
    const selE = document.getElementById("CalSelEmpresa");
    const selD = document.getElementById("CalSelDepto");
    const selEm = document.getElementById("CalSelEmpleado");
    if (!selE || !selD || !selEm) return;
    const empSeleccionada = selE.value;
    if (origen === "empresa") {
        selD.innerHTML = '<option value="">— Elija departamento —</option>';
        selEm.innerHTML = '<option value="">— Elija empleado —</option>';
        selEm.disabled = true;
        if (empSeleccionada) {
            selD.disabled = false;
            const todosLosDeptos = Array.isArray(datosSistema.departamentos) ? datosSistema.departamentos : [];
            const filtrados = todosLosDeptos.filter(d =>
                String(d.empresa || "").trim().toLowerCase() === String(empSeleccionada).trim().toLowerCase()
            );
            filtrados.forEach(d => {
                const opt = document.createElement("option");
                opt.value = d.nombre;
                opt.textContent = d.nombre;
                selD.appendChild(opt);
            });
        } else {
            selD.disabled = true;
        }
    }
    if (origen === "depto") {
        const deptoSeleccionado = selD.value;
        selEm.innerHTML = '<option value="">— Elija empleado —</option>';
        if (deptoSeleccionado) {
            selEm.disabled = false;
            const empleados = Array.isArray(datosSistema.empleados) ? datosSistema.empleados : [];
            const filtradosEm = empleados.filter(e =>
                String(e.empresa || "").trim().toLowerCase() === String(empSeleccionada).trim().toLowerCase() &&
                String(e.depto || "").trim().toLowerCase() === String(deptoSeleccionado).trim().toLowerCase()
            );
            filtradosEm.forEach(e => {
                selEm.appendChild(new Option(`${e.nombres} ${e.apellidos}`, e.cedula));
            });
        } else {
            selEm.disabled = true;
        }
    }
    const btnAplicar = document.querySelector("button[onclick='calAplicarContextoDesdeSelects()']");
    if (btnAplicar) btnAplicar.disabled = !empSeleccionada;
}

function calAplicarContextoDesdeSelects() {
    const elEmp = document.getElementById("CalSelEmpresa");
    const elDep = document.getElementById("CalSelDepto");
    const elCed = document.getElementById("CalSelEmpleado");
    const emp = elEmp?.value || "";
    const dep = elDep?.value || "";
    const ced = elCed?.value || "";
    if (!emp || emp === "") return;
    empresaActual = emp;
    deptoActual = dep;
    idEmpleadoActual = ced || null;
    if (ced) {
        const e = datosSistema.empleados.find((x) => x.cedula === ced);
        const nom = e ? `${e.nombres} ${e.apellidos}` : ced;
        verCalendarioDeEmpleado(ced, nom, dep, emp);
    } else if (dep) {
        verCalendarioDepartamento(dep, emp);
    } else {
        verCalendarioEmpresa(emp);
    }
}

document.addEventListener("DOMContentLoaded", async () => {
    await cargarDatosCalendarioDesdeBD();
    asegurarCalendarios();
    nivelActual = "";
    empresaActual = "";
    idActual = null;
    const selE = document.getElementById("CalSelEmpresa");
    const selD = document.getElementById("CalSelDepto");
    const selEm = document.getElementById("CalSelEmpleado");
    if (selE) {
        selE.innerHTML = '<option value="">— Seleccione una Empresa —</option>';
        const empresas = Array.isArray(datosSistema.empresas) ? datosSistema.empresas : [];
        empresas.forEach(e => selE.appendChild(new Option(e.nombre, e.nombre)));
        selE.onchange = () => {
            console.log("Cambio en Empresa detectado");
            calRellenarSelectContexto("empresa");
        };
    }
    if (selD) {
        selD.onchange = () => {
            console.log("Cambio en Departamento detectado");
            calRellenarSelectContexto("depto");
        };
    }
    document.getElementById("FormHorarioLaboral")?.addEventListener("submit", guardarHorarioActual);
    if (selD) selD.disabled = true;
    if (selEm) selEm.disabled = true;
    document.getElementById("EtiquetaPrincipal").textContent = "Gestión de Asistencia";
    document.getElementById("EtiquetaSubtitulo").textContent = "Seleccione una empresa para comenzar";
});

async function sincronizarConGoogle() {
    const idEntidad = calIdEntidadActiva();
    const nivel = nivelActual;
    if (!empresaActual) {
        return alert("Por favor, selecciona una empresa antes de sincronizar.");
    }
    try {
        const btn = document.getElementById("BtnSincronizarGoogle");
        const textoOriginal = btn.textContent;
        btn.textContent = "Sincronizando...";
        btn.disabled = true;
        const respuesta = await fetch(`./Calendario/sincronizar_google.php?nivel=${nivel}&id=${idEntidad}`);
        const resultado = await respuesta.json();
        if (resultado.success) {
            actualizarDatosLocalesDesdeGoogle(resultado.eventos);
            alert("¡Sincronización exitosa!");
            renderizarCalendario();
        } else {
            alert("Error al sincronizar: " + resultado.message);
        }
    } catch (error) {
        console.error("Error en la conexión con la API:", error);
        alert("No se pudo conectar con el servidor de sincronización.");
    } finally {
        const btn = document.getElementById("BtnSincronizarGoogle");
        btn.textContent = "Sincronizar con Google";
        btn.disabled = false;
    }
}

function actualizarDatosLocalesDesdeGoogle(eventos) {
    asegurarCalendarios();
    const capa = calCapaNivel();
    const idEnt = calIdEntidadActiva();
    if (capa !== "general") {
        if (!datosSistema.calendarios[capa]) {
            datosSistema.calendarios[capa] = {};
        }
        if (!datosSistema.calendarios[capa][idEnt]) {
            datosSistema.calendarios[capa][idEnt] = {};
        }
        datosSistema.calendarios[capa][idEnt] = {};
    } else {
        datosSistema.calendarios.general = {};
    }
    eventos.forEach(ev => {
        const val = { motivo: ev.summary, laboral: ev.laboral || false };
        if (capa === "general") {
            datosSistema.calendarios.general[ev.fecha] = val;
        } else {
            datosSistema.calendarios[capa][idEnt][ev.fecha] = val;
        }
    });
    guardarDatosCalendario();
    console.log("Datos actualizados en LocalStorage:", datosSistema.calendarios);
}