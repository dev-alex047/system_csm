const clientesUI = {
    lista: [],
    mapa: null,
    pin: null,
    timer: null,
    peticion: null,
    folio: 0
};

clientesUI.tipoFiltro = 1;
clientesUI.grupos = [];
clientesUI.mapaFicha = null;
clientesUI.paises = [];
clientesUI.geoVersion = 0;
clientesUI.estadosVersion = 0;
clientesUI.catalogosVersion = 0;

function apiClientes(datos) {
    return $.ajax({
        url: "php/index.php",
        method: "POST",
        dataType: "json",
        data: datos
    });
}

function errorClientes(xhr) {
    return xhr.responseJSON?.answer ||
        "No se pudo completar la operación.";
}

function nombreCompletoCliente(c) {
    return [c.nombre, c.apellido].filter(Boolean).join(" ").trim();
}

function consultarClientesAdmin() {
    apiClientes({action: "getClientes"}).done(function(r) {
        if (!r || r.status !== "ok") {
            return Swal.fire(
                "Aviso",
                r?.answer || "No se pudieron consultar clientes.",
                "warning"
            );
        }

        clientesUI.lista = Array.isArray(r.results) ? r.results : [];
        pintarClientes();
    }).fail(function(xhr) {
        Swal.fire("Error", errorClientes(xhr), "error");
    });
}


function pintarClientes() {
    const buscar = String($("#clBuscar").val() || "")
        .trim().toLocaleLowerCase("es");

    const tipo = Number(clientesUI.tipoFiltro);

    const clientes = clientesUI.lista.filter(function(c) {
        return Number(c.tipo) === tipo;
    });

    const filtrados = clientes.filter(function(c) {
        return [
            nombreCompletoCliente(c),
            c.alias,
            c.razon_social,
            c.rfc,
            c.numero,
            c.movil,
            c.correo,
            c.domicilio
        ].join(" ").toLocaleLowerCase("es").includes(buscar);
    });

    $("#clTotalClientes").text(
        clientesUI.lista.filter(c => Number(c.tipo) === 1).length
    );

    $("#clTotalProspectos").text(
        clientesUI.lista.filter(c => Number(c.tipo) === 0).length
    );

    $("#clConteo").text(
        "Mostrando " + filtrados.length + " de " +
        clientes.length + (tipo === 1 ? " clientes" : " prospectos")
    );

    $("#clNuevo").html(
        '<i class="bi bi-plus-lg me-2"></i>' +
        (tipo === 1 ? "Agregar cliente" : "Agregar prospecto")
    );

    const $tabla = $("#clTabla").empty();

    filtrados.forEach(function(c) {
        const nombre = nombreCompletoCliente(c);
        const telefono = c.movil || c.numero || "";
        const grupo = clientesUI.grupos.find(function(g) {
            return Number(g.id) === Number(c.grupo_id);
        });

        const $fila = $("<tr>").addClass("cl-fila");

        const $contacto = $("<div>").addClass("cl-contacto");

        $("<span>").addClass("cl-mini-avatar")
            .text(inicialesCliente(c))
            .appendTo($contacto);

        const $identidad = $("<div>").addClass("cl-identidad");

        $("<button>", {
            type: "button",
            class: "cl-nombre-enlace js-cl-ver",
            text: nombre
        }).attr("data-id", c.id).appendTo($identidad);

        $("<small>").text(
            c.alias ? "También conocido como " + c.alias :
            c.razon_social || "ID #" + c.id
        ).appendTo($identidad);

        $contacto.append($identidad);
        $("<td>").append($contacto).appendTo($fila);

        const $comunicacion = $("<td>");

        $("<div>").addClass("fw-semibold")
            .text(telefono || "Sin teléfono")
            .appendTo($comunicacion);

        $("<small>").addClass("cl-muted")
            .text(c.correo || c.correo_empresarial || "Sin correo")
            .appendTo($comunicacion);

        $fila.append($comunicacion);

        $("<td>").addClass("cl-domicilio-celda")
            .text(c.domicilio || "Sin domicilio")
            .appendTo($fila);

        const $comercial = $("<td>");

        $("<span>").addClass("cl-grupo-etiqueta")
            .text(grupo ? grupo.nombre : "Sin grupo")
            .appendTo($comercial);

        $("<small>").addClass("d-block cl-muted mt-1")
            .text("Precio " + (c.tipo_precio_id || 1))
            .appendTo($comercial);

        $fila.append($comercial);

        const $acciones = $("<td>").addClass("text-end");

        $("<button>", {
            type: "button",
            class: "cl-btn-opciones js-cl-opciones",
            html: '<i class="bi bi-three-dots"></i>'
        }).attr({
            "data-id": c.id,
            "aria-label": "Opciones de " + nombre,
            "aria-expanded": "false"
        }).appendTo($acciones);

        $fila.append($acciones);
        $tabla.append($fila);

        const $menuFila = $("<tr>").addClass("cl-menu-fila d-none");
        const $menuCelda = $("<td>").attr("colspan", 5);
        const $menu = $("<div>").addClass("cl-menu-acciones");

        function accion(texto, icono, clase, estilo) {
            $("<button>", {
                type: "button",
                class: "btn btn-sm " + estilo + " " + clase,
                html: '<i class="bi bi-' + icono +
                    ' me-2"></i>' + texto
            }).attr("data-id", c.id).appendTo($menu);
        }

        accion("Ver ficha", "eye", "js-cl-ver",
            "btn-outline-success");

        accion("Editar", "pencil-square", "js-cl-editar",
            "btn-outline-secondary");

        if (Number(c.tipo) === 0) {
            accion("Convertir en cliente", "person-check",
                "js-cl-convertir", "btn-outline-primary");
        }

        accion("Eliminar", "trash3", "js-cl-eliminar",
            "btn-outline-danger");

        $menuCelda.append($menu);
        $menuFila.append($menuCelda);
        $tabla.append($menuFila);
    });

    if (!filtrados.length) {
        $("<tr>").append(
            $("<td>", {
                colspan: 5,
                class: "text-center py-5 cl-muted",
                text: "No se encontraron contactos con esos filtros."
            })
        ).appendTo($tabla);
    }
}

function inicialesCliente(c) {
    const palabras = nombreCompletoCliente(c)
        .split(/\s+/)
        .filter(Boolean);

    return palabras.slice(0, 2).map(function(p) {
        return p.charAt(0).toUpperCase();
    }).join("") || "CL";
}


function liberarMapaClientes() {
    clientesUI.folio++;
    clientesUI.geoVersion++;
    clientesUI.estadosVersion++;
    clearTimeout(clientesUI.timer);

    if (clientesUI.peticion) clientesUI.peticion.abort();
    clientesUI.peticion = null;

    if (clientesUI.mapa) clientesUI.mapa.remove();
    if (clientesUI.mapaFicha) {
    clientesUI.mapaFicha.remove();
    clientesUI.mapaFicha = null;
}

    clientesUI.mapa = null;
    clientesUI.pin = null;
}

function mostrarListaClientes() {
    liberarMapaClientes();
    $("#clFormulario").addClass("d-none");
    $("#clListadoPanel").removeClass("d-none");
    $("#clDetallePanel").addClass("d-none");
    $("#clModal .modal-body").scrollTop(0);
}

function coordenadasClienteForm() {
    const latTexto = String($("#cl_latitud").val() || "").trim();
    const lngTexto = String($("#cl_longitud").val() || "").trim();

    if (!latTexto || !lngTexto) return null;

    const lat = Number(latTexto);
    const lng = Number(lngTexto);

    return Number.isFinite(lat) && Number.isFinite(lng) &&
        Math.abs(lat) <= 90 && Math.abs(lng) <= 180
        ? [lat, lng] : null;
}

function colocarPinCliente(lat, lng, centrar = true) {
    if (!clientesUI.mapa ||
        !Number.isFinite(lat) || !Number.isFinite(lng) ||
        Math.abs(lat) > 90 || Math.abs(lng) > 180) return;

    $("#cl_latitud").val(lat.toFixed(8));
    $("#cl_longitud").val(lng.toFixed(8));

    if (!clientesUI.pin) {
        clientesUI.pin = L.marker([lat, lng], {
            draggable: true,
            autoPan: true,
            icon: iconoDestinoCosteo()
        }).addTo(clientesUI.mapa);

        clientesUI.pin.on("dragend", function() {
            const punto = this.getLatLng();
            colocarPinCliente(punto.lat, punto.lng, false);
        });
    } else {
        clientesUI.pin.setLatLng([lat, lng]);
    }

    if (centrar) clientesUI.mapa.setView([lat, lng], 16);
}

function abrirFormularioCliente(c = null) {
    liberarMapaClientes();
    $("#clDetallePanel").addClass("d-none");

    const $form = $("#clFormulario");
    $form[0].reset();

    $form.find("[name]").each(function() {
        $(this).val(c?.[this.name] ?? "");
    });

    $("#cl_id").val(c?.id || 0);
    $("#cl_tipo").val(c?.tipo ?? 1);
    $("#cl_limite_credito").val(c?.limite_credito ?? "0.00");

    cambiarTipoCliente(c ? c.tipo : clientesUI.tipoFiltro);
    cargarCatalogosClienteUI(c);
    cargarPaisesCliente(c?.pais || "Mexico", c?.estado || "");

    $("#clDetallePanel").addClass("d-none");

    $("#clTituloFormulario").text(
        c ? "Editar cliente #" + c.id : "Nuevo cliente"
    );

    $("#clListadoPanel").addClass("d-none");
    $form.removeClass("d-none");

    $("#clLugares").empty().addClass("d-none");
    $("#clBuscarLugar").val("");

    try {
        const coords = coordenadasClienteForm();

        clientesUI.mapa = crearMapaCosteo(
            "clMapa",
            coords || CENTRO_MAPA,
            coords ? 16 : 10
        );

        clientesUI.mapa.on("click", function(e) {
            colocarPinCliente(e.latlng.lat, e.latlng.lng, false);
        });

        if (coords) colocarPinCliente(coords[0], coords[1], false);

        requestAnimationFrame(function() {
            clientesUI.mapa?.invalidateSize();
        });
    } catch (e) {
        Swal.fire("Mapa no disponible", e.message, "warning");
    }
    $("#clModal .modal-body").scrollTop(0);

    $("#cl_nombre").trigger("focus");
}

$(document).on("click", "#btnClientes", function() {
    if (!window.CSM_ES_ADMIN) return;

    mostrarListaClientes();

    bootstrap.Modal.getOrCreateInstance(
        document.getElementById("clModal")
    ).show();

    consultarClientesAdmin();
    cargarCatalogosClienteUI();
});

$(document).on("hidden.bs.modal", "#clModal", liberarMapaClientes);
$(document).on("input", "#clBuscar", pintarClientes);

$(document).on("click", "#clNuevo", function() {
    abrirFormularioCliente();
});

$(document).on("click", "#clVolver, #clVolverAbajo", function() {
    mostrarListaClientes();
});

$(document).on("click", ".js-cl-editar", function() {
    const c = clientesUI.lista.find(
        x => Number(x.id) === Number(this.dataset.id)
    );

    if (c) abrirFormularioCliente(c);
});

$(document).on("submit", "#clFormulario", function(e) {
    e.preventDefault();

    if (!this.checkValidity()) return this.reportValidity();

    const $boton = $("#clGuardar").prop("disabled", true);
    const datos = {action: "guardarCliente"};

    $(this).serializeArray().forEach(function(c) {
        datos[c.name] = c.value;
    });

    apiClientes(datos).done(function(r) {
        if (r.status !== "ok") {
            return Swal.fire("No se guardó", r.answer, "warning");
        }

        clientesUI.tipoFiltro = Number($("#cl_tipo").val());

$(".js-cl-filtro")
    .removeClass("active")
    .attr("aria-pressed", "false");

$(".js-cl-filtro[data-tipo='" + clientesUI.tipoFiltro + "']")
    .addClass("active")
    .attr("aria-pressed", "true");

        mostrarListaClientes();
        consultarClientesAdmin();

        Swal.fire({
            icon: "success",
            title: "Cliente guardado",
            timer: 1300,
            showConfirmButton: false
        });
    }).fail(function(xhr) {
        Swal.fire("Error", errorClientes(xhr), "error");
    }).always(function() {
        $boton.prop("disabled", false);
    });
});

$(document).on("click", ".js-cl-eliminar", function() {
    const id = Number(this.dataset.id);

    const c = clientesUI.lista.find(
        x => Number(x.id) === id
    );

    if (!c) return;

    Swal.fire({
        titleText: "¿Eliminar a " + nombreCompletoCliente(c) + "?",
        text: "Se ocultará del catálogo, pero conservará su historial.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Eliminar",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#b3261e"
    }).then(function(r) {
        if (!r.isConfirmed) return;

        apiClientes({
            action: "eliminarCliente",
            id: id
        }).done(function(res) {
            if (res.status !== "ok") {
                return Swal.fire("Aviso", res.answer, "warning");
            }

            consultarClientesAdmin();
        }).fail(function(xhr) {
            Swal.fire("Error", errorClientes(xhr), "error");
        });
    });
});

$(document).on("change", "#cl_latitud, #cl_longitud", function() {
    const coords = coordenadasClienteForm();
    if (coords) colocarPinCliente(coords[0], coords[1]);
});

$(document).on("click", "#clQuitarPin", function() {
    $("#cl_latitud, #cl_longitud").val("");

    if (clientesUI.pin && clientesUI.mapa) {
        clientesUI.mapa.removeLayer(clientesUI.pin);
    }

    clientesUI.pin = null;
});

$(document).on("click", "#clUsarGPS", function() {
    if (!window.isSecureContext || !navigator.geolocation) {
        return Swal.fire(
            "Ubicación no disponible",
            "Requiere HTTPS y permiso de ubicación.",
            "warning"
        );
    }

    const $boton = $(this).prop("disabled", true);

    navigator.geolocation.getCurrentPosition(
        function(pos) {
            colocarPinCliente(
                pos.coords.latitude,
                pos.coords.longitude
            );

            $boton.prop("disabled", false);
        },
        function() {
            $boton.prop("disabled", false);

            Swal.fire(
                "Ubicación no disponible",
                "Verifica los permisos del navegador.",
                "warning"
            );
        },
        {
            enableHighAccuracy: true,
            timeout: 15000,
            maximumAge: 0
        }
    );
});

$(document).on("input", "#clBuscarLugar", function() {
    const q = this.value.trim();
    const folio = ++clientesUI.folio;

    clearTimeout(clientesUI.timer);

    if (clientesUI.peticion) clientesUI.peticion.abort();

    const $lista = $("#clLugares").empty().addClass("d-none");

    if (q.length < 3) return;

    clientesUI.timer = setTimeout(function() {
        if (!CLAVE_LOCATIONIQ || !CLAVE_LOCATIONIQ.trim()) return;

        const paisSeleccionado = clientesUI.paises.find(function(p) {
    return p.name === $("#cl_pais").val();
});

const parametros = {
    key: CLAVE_LOCATIONIQ.trim(),
    q: q,
    "accept-language": "es",
    limit: 6
};

if (paisSeleccionado && paisSeleccionado.iso2) {
    parametros.countrycodes = paisSeleccionado.iso2.toLowerCase();
}

        clientesUI.peticion = $.ajax({
            url: "https://api.locationiq.com/v1/autocomplete",
            method: "GET",
            dataType: "json",
            timeout: 10000,
            data: parametros
        }).done(function(resultados) {
            if (folio !== clientesUI.folio) return;

            $lista.empty().removeClass("d-none");

            

            (Array.isArray(resultados) ? resultados : []).forEach(
                function(lugar) {
                    $("<button>", {
                        type: "button",
                        class: "list-group-item list-group-item-action js-cl-lugar",
                        text: lugar.display_name ||
                            lugar.display_place || "Lugar"
                    }).data("lugar", lugar).appendTo($lista);
                }
            );

            if (!$lista.children().length) {
                $lista.text("No se encontraron ubicaciones.");
            }
        }).fail(function(xhr, estado) {
            if (estado !== "abort" && folio === clientesUI.folio) {
                $lista.removeClass("d-none").text(
                    "No se pudieron consultar lugares."
                );
            }
        });
    }, 350);
});

$(document).on("click", ".js-cl-lugar", function() {
    const lugar = $(this).data("lugar");

    if (!lugar) return;

    const direccion = lugar.address || {};

    const valores = {
    domicilio: lugar.display_name || lugar.display_address || "",
    calle: direccion.road || direccion.pedestrian || "",
    numext: direccion.house_number || "",
    colonia: direccion.suburb ||
        direccion.neighbourhood ||
        direccion.quarter || "",
    municipio: direccion.city ||
        direccion.town ||
        direccion.village ||
        direccion.municipality || "",
    cp: direccion.postcode || ""
};

    Object.keys(valores).forEach(function(campo) {
        if (direccion.country || direccion.country_code) {
    cargarPaisesCliente(
        direccion.country_code || direccion.country,
        direccion.state || ""
    );
}
        if (!valores[campo]) return;

        const $control = $("#cl_" + campo);

        $control.val(
            String(valores[campo]).slice(
                0,
                Number($control.attr("maxlength")) || 255
            )
        );
    });


    colocarPinCliente(Number(lugar.lat), Number(lugar.lon));

    $("#clBuscarLugar").val(lugar.display_name || "").trigger("blur");
    $("#clLugares").empty().addClass("d-none");
});



function iniciarSelectorNotaClientes() {
    const $campo = $("#notaCliente");
    if (!$campo.length) return;

    apiClientes({action: "getClientes"}).done(function(r) {
        if (!document.body.contains($campo[0]) || r.status !== "ok") return;

        const clientes = (Array.isArray(r.results) ? r.results : [])
        .filter(function(c) {
            return Number(c.tipo) === 1;
        });

        $campo.attr(
            "placeholder",
            "Escribe o selecciona un cliente registrado"
        );

        $campo.autocomplete({
            minLength: 0,

            source: function(p, devolver) {
                const buscar = p.term.toLocaleLowerCase("es");

                const encontrados = clientes.filter(function(c) {
                    return [
                        nombreCompletoCliente(c),
                        c.alias,
                        c.numero
                    ].join(" ").toLocaleLowerCase("es").includes(buscar);
                });

                devolver(encontrados.slice(0, 12).map(function(c) {
                    return {
                        label: nombreCompletoCliente(c) +
                            (c.alias ? " · " + c.alias : ""),
                        value: nombreCompletoCliente(c),
                        cliente: c
                    };
                }));
            },

            select: function(e, ui) {
                const c = ui.item.cliente;

                state.ventaNota.cliente_id = Number(c.id);
                state.ventaNota.cliente = ui.item.value;
                state.ventaNota.direccion = c.domicilio || "";
                state.ventaNota.telefono = c.numero || "";

                $("#notaCliente").val(state.ventaNota.cliente);
                $("#notaDireccion").val(state.ventaNota.direccion);
                $("#notaTelefono").val(state.ventaNota.telefono);

                return false;
            }
        });

        $campo.off("input.clientes").on("input.clientes", function() {
            state.ventaNota.cliente_id = null;
        });

        $campo.on("focus.clientes", function() {
            $(this).autocomplete("search", this.value);
        });
    });
}


$(document).on("click", ".js-cl-filtro", function() {
    clientesUI.tipoFiltro = Number($(this).attr("data-tipo"));

    $(".js-cl-filtro")
        .removeClass("active")
        .attr("aria-pressed", "false");

    $(this).addClass("active").attr("aria-pressed", "true");

    $("#clBuscar").val("");
    pintarClientes();
});

$(document).on("click", ".js-cl-opciones", function() {
    const $fila = $(this).closest("tr");
    const $menu = $fila.next(".cl-menu-fila");
    const abrir = $menu.hasClass("d-none");

    $("#clTabla .cl-menu-fila").addClass("d-none");
    $("#clTabla .js-cl-opciones").attr("aria-expanded", "false");

    $menu.toggleClass("d-none", !abrir);
    $(this).attr("aria-expanded", abrir ? "true" : "false");
});

$(document).on("click", ".js-cl-convertir", function() {
    const id = Number($(this).attr("data-id"));

    const c = clientesUI.lista.find(function(item) {
        return Number(item.id) === id;
    });

    if (!c) return;

    abrirFormularioCliente(c);
    cambiarTipoCliente(1);
});

function cambiarTipoCliente(tipo) {
    tipo = Number(tipo) === 0 ? 0 : 1;

    $("#cl_tipo").val(tipo);

    $(".js-cl-tipo")
        .removeClass("active")
        .attr("aria-pressed", "false");

    $(".js-cl-tipo[data-tipo='" + tipo + "']")
        .addClass("active")
        .attr("aria-pressed", "true");
}

$(document).on("click", ".js-cl-tipo", function() {
    cambiarTipoCliente($(this).attr("data-tipo"));
});


function cargarCatalogosClienteUI(c = null) {
    const version = ++clientesUI.catalogosVersion;
    const grupoSeleccionado = c?.grupo_id ?? "";
    const precioSeleccionado = c?.tipo_precio_id ?? 1;

    return apiClientes({
        action: "getCatalogosClientes"
    }).done(function(r) {
        if (version !== clientesUI.catalogosVersion) {
            return;
        }
        if (r.status !== "ok") {
            Swal.fire("Aviso", r.answer, "warning");
            return;
        }

        clientesUI.grupos = r.results.grupos || [];

        const $grupo = $("#cl_grupo_id").empty();
        const $precio = $("#cl_tipo_precio_id").empty();

        $("<option>").val("").text("Sin grupo").appendTo($grupo);

        clientesUI.grupos.forEach(function(g) {
            $("<option>").val(g.id).text(g.nombre).appendTo($grupo);
        });

        (r.results.precios || []).forEach(function(p) {
            $("<option>").val(p.id).text(p.nombre).appendTo($precio);
        });

        $grupo.val(String(grupoSeleccionado || ""));
        $precio.val(String(precioSeleccionado || 1));

        pintarClientes();
    }).fail(function(xhr) {
        Swal.fire("Error", errorClientes(xhr), "error");
    });
}

$(document).on("click", "#clAgregarGrupo", function() {
    Swal.fire({
        title: "Nuevo grupo de clientes",
        input: "text",
        inputLabel: "Nombre del grupo",
        inputPlaceholder: "Ej. Clientes frecuentes",
        inputAttributes: {maxlength: 80},
        showCancelButton: true,
        confirmButtonText: "Crear grupo",
        cancelButtonText: "Cancelar",
        preConfirm: function(valor) {
            if (!valor || !valor.trim()) {
                Swal.showValidationMessage("Escribe el nombre del grupo.");
                return false;
            }

            return valor.trim();
        }
    }).then(function(r) {
        if (!r.isConfirmed) return;

        apiClientes({
            action: "guardarCatalogoCliente",
            tipo_catalogo: "grupo",
            nombre: r.value
        }).done(function(res) {
            if (res.status !== "ok") {
                Swal.fire("Aviso", res.answer, "warning");
                return;
            }

            const seleccionado = {
                grupo_id: res.results.id,
                tipo_precio_id: $("#cl_tipo_precio_id").val()
            };

            cargarCatalogosClienteUI(seleccionado);
        }).fail(function(xhr) {
            Swal.fire("Error", errorClientes(xhr), "error");
        });
    });
});


function normalizarGeoCliente(valor) {
    return String(valor || "")
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .trim()
        .toLowerCase();
}


function errorGeoCliente(mensaje) {
    $("#clGeoMensaje").text(mensaje);
    $("#clGeoError").removeClass("d-none");
}

function limpiarErrorGeoCliente() {
    $("#clGeoError").addClass("d-none");
    $("#clGeoMensaje").text("");
}

function cargarPaisesCliente(paisGuardado = "Mexico", estadoGuardado = "") {
    const version = ++clientesUI.geoVersion;
    ++clientesUI.estadosVersion;

    const $pais = $("#cl_pais");
    const $estado = $("#cl_estado");

    limpiarErrorGeoCliente();

    $pais.prop("disabled", true).empty().append(
        $("<option>").val("").text("Cargando países...")
    );

    $estado.prop("disabled", true).empty().append(
        $("<option>").val("").text("Primero selecciona un país")
    );

    return apiClientes({
        action: "getPaisesEstados",
        nivel: "paises"
    }).done(function(r) {
        if (version !== clientesUI.geoVersion) return;

        if (r.status !== "ok" ||
            !Array.isArray(r.results) ||
            !r.results.length) {

            $pais.empty().append(
                $("<option>").val("").text("No se pudieron cargar países")
            );

            errorGeoCliente(
                r.answer || "No se pudo consultar la lista de países."
            );

            return;
        }

        clientesUI.paises = r.results.slice().sort(function(a, b) {
            return a.name.localeCompare(b.name, "es");
        });

        $pais.empty().append(
            $("<option>").val("").text("Selecciona un país")
        );

        clientesUI.paises.forEach(function(p) {
            $("<option>")
                .val(p.name)
                .text(p.name === "Mexico" ? "México" : p.name)
                .appendTo($pais);
        });

        const buscado = normalizarGeoCliente(paisGuardado);

        const encontrado = clientesUI.paises.find(function(p) {
            return normalizarGeoCliente(p.name) === buscado ||
                normalizarGeoCliente(p.iso2) === buscado;
        });

        if (encontrado) {
            $pais.val(encontrado.name);
        } else if (paisGuardado) {
            $("<option>")
                .val(paisGuardado)
                .text(paisGuardado + " (guardado)")
                .appendTo($pais);

            $pais.val(paisGuardado);
        }

        $pais.prop("disabled", false);

        cargarEstadosCliente(
            $pais.val() || "",
            estadoGuardado
        );
    }).fail(function(xhr) {
        if (version !== clientesUI.geoVersion) return;

        $pais.empty().append(
            $("<option>").val("").text("Error al cargar países")
        );

        errorGeoCliente(
            errorClientes(xhr) + " Intenta nuevamente."
        );
    });
}

function cargarEstadosCliente(pais, seleccionado = "") {
    const version = ++clientesUI.estadosVersion;
    const $estado = $("#cl_estado");

    limpiarErrorGeoCliente();

    $estado.prop("disabled", true).empty().append(
        $("<option>").val("").text(
            pais ? "Cargando estados..." : "Primero selecciona un país"
        )
    );

    if (!pais) return;

    return apiClientes({
        action: "getPaisesEstados",
        nivel: "estados",
        pais: pais
    }).done(function(r) {
        if (version !== clientesUI.estadosVersion) return;

        if (r.status !== "ok" || !Array.isArray(r.results)) {
            $estado.empty().append(
                $("<option>").val("").text("Error al cargar estados")
            );

            errorGeoCliente(
                r.answer || "No se pudieron consultar los estados."
            );

            return;
        }

        const estados = r.results.slice().sort(function(a, b) {
            return a.name.localeCompare(b.name, "es");
        });

        $estado.empty().append(
            $("<option>").val("").text(
                estados.length
                    ? "Selecciona un estado o provincia"
                    : "Sin estados disponibles"
            )
        );

        estados.forEach(function(e) {
            $("<option>")
                .val(e.name)
                .text(e.name)
                .appendTo($estado);
        });

        if (seleccionado) {
            const encontrado = estados.find(function(e) {
                return normalizarGeoCliente(e.name) ===
                    normalizarGeoCliente(seleccionado);
            });

            if (encontrado) {
                $estado.val(encontrado.name);
            } else {
                $("<option>")
                    .val(seleccionado)
                    .text(seleccionado + " (guardado)")
                    .appendTo($estado);

                $estado.val(seleccionado);
            }
        }

        $estado.prop("disabled", !estados.length && !seleccionado);
    }).fail(function(xhr) {
        if (version !== clientesUI.estadosVersion) return;

        $estado.empty().append(
            $("<option>").val("").text("Error al cargar estados")
        );

        errorGeoCliente(
            errorClientes(xhr) + " Intenta nuevamente."
        );
    });
}

$(document).on("change", "#cl_pais", function() {
    cargarEstadosCliente($(this).val() || "", "");
});

$(document).on("click", "#clGeoReintentar", function() {
    cargarPaisesCliente(
        $("#cl_pais").val() || "Mexico",
        $("#cl_estado").val() || ""
    );
});


$(document).on("change", "#cl_pais", function() {
    cargarEstadosCliente(this.value);
});


function agregarDatoFicha(selector, etiqueta, valor, ancho = "col-md-6") {
    const $col = $("<div>").addClass(ancho);
    const $dato = $("<div>").addClass("cl-dato");

    $("<span>").addClass("cl-dato-etiqueta")
        .text(etiqueta).appendTo($dato);

    $("<strong>").text(
        valor === null || valor === undefined ||
        String(valor).trim() === "" ? "—" : String(valor)
    ).appendTo($dato);

    $col.append($dato);
    $(selector).append($col);
}

function abrirDetalleCliente(c) {
    liberarMapaClientes();

    $("#clListadoPanel, #clFormulario").addClass("d-none");
    $("#clDetallePanel").removeClass("d-none");

    const nombre = nombreCompletoCliente(c);

    $("#clDetalleNombre").text(nombre);
    $("#clDetalleAvatar").text(inicialesCliente(c));
    $("#clDetalleTipo").text(
        Number(c.tipo) === 1 ? "Cliente" : "Prospecto"
    );

    $("#clDetalleSubtitulo").text(
        c.alias || c.razon_social || "Registro #" + c.id
    );

    $("#clDetalleEditar").attr("data-id", c.id);

    $("#clDetalleGeneral, #clDetalleDireccion, #clDetalleAcciones")
        .empty();

    agregarDatoFicha("#clDetalleGeneral", "Nombre completo", nombre);
    agregarDatoFicha("#clDetalleGeneral", "Alias", c.alias);
    agregarDatoFicha("#clDetalleGeneral", "Razón social", c.razon_social);
    agregarDatoFicha("#clDetalleGeneral", "RFC", c.rfc);
    agregarDatoFicha("#clDetalleGeneral", "Teléfono", c.numero);
    agregarDatoFicha("#clDetalleGeneral", "Móvil", c.movil);
    agregarDatoFicha("#clDetalleGeneral", "Correo", c.correo);
    agregarDatoFicha(
        "#clDetalleGeneral", "Correo empresarial", c.correo_empresarial
    );

    const grupo = clientesUI.grupos.find(function(g) {
        return Number(g.id) === Number(c.grupo_id);
    });

    agregarDatoFicha(
        "#clDetalleGeneral",
        "Grupo",
        grupo ? grupo.nombre : "Sin grupo"
    );

    agregarDatoFicha(
        "#clDetalleGeneral",
        "Lista de precios",
        "Tipo de precio " + (c.tipo_precio_id || 1)
    );

    agregarDatoFicha(
        "#clDetalleGeneral",
        "Límite de crédito",
        monedaMXN(Number(c.limite_credito || 0))
    );

    agregarDatoFicha(
        "#clDetalleDireccion",
        "Domicilio o referencias",
        c.domicilio,
        "col-12"
    );

    agregarDatoFicha("#clDetalleDireccion", "Calle", c.calle);
    agregarDatoFicha("#clDetalleDireccion", "Núm. exterior", c.numext);
    agregarDatoFicha("#clDetalleDireccion", "Núm. interior", c.numint);
    agregarDatoFicha("#clDetalleDireccion", "Colonia", c.colonia);
    agregarDatoFicha("#clDetalleDireccion", "Municipio", c.municipio);
    agregarDatoFicha("#clDetalleDireccion", "Código postal", c.cp);
    agregarDatoFicha("#clDetalleDireccion", "Estado", c.estado);
    agregarDatoFicha("#clDetalleDireccion", "País", c.pais);

    $("#clDetalleNotas").text(c.notas || "Sin observaciones.");

    const $acciones = $("#clDetalleAcciones");

    const telefono = String(c.movil || c.numero || "")
        .replace(/[^\d+]/g, "");

    if (telefono) {
        $("<a>", {
            class: "btn btn-outline-success",
            href: "tel:" + telefono,
            text: "Llamar al cliente"
        }).prepend(
            $("<i>").addClass("bi bi-telephone me-2")
        ).appendTo($acciones);
    }

    const correo = c.correo || c.correo_empresarial;

    if (correo) {
        $("<a>", {
            class: "btn btn-outline-success",
            href: "mailto:" + correo,
            text: "Enviar correo"
        }).prepend(
            $("<i>").addClass("bi bi-envelope me-2")
        ).appendTo($acciones);
    }

    if (Number(c.tipo) === 0) {
        $("<button>", {
            type: "button",
            class: "btn btn-outline-primary js-cl-convertir",
            text: "Convertir en cliente"
        }).attr("data-id", c.id).appendTo($acciones);
    }

    if (!$acciones.children().length) {
        $("<p>").addClass("cl-muted mb-0")
            .text("No hay datos de contacto registrados.")
            .appendTo($acciones);
    }

    const tieneCoordenadas =
        c.latitud !== null && c.longitud !== null &&
        c.latitud !== "" && c.longitud !== "";

    const lat = Number(c.latitud);
    const lng = Number(c.longitud);

    const ubicacionValida = tieneCoordenadas &&
        Number.isFinite(lat) && Number.isFinite(lng) &&
        Math.abs(lat) <= 90 && Math.abs(lng) <= 180;

    $("#clDetalleMapaPanel")
        .toggleClass("d-none", !ubicacionValida);

    if (ubicacionValida) {
        $("#clDetalleRuta").attr(
            "href",
            "https://www.google.com/maps?q=" + lat + "," + lng
        );

        try {
            clientesUI.mapaFicha = crearMapaCosteo(
                "clMapaFicha", [lat, lng], 16
            );

            L.marker([lat, lng], {
                icon: iconoDestinoCosteo()
            }).addTo(clientesUI.mapaFicha);

            setTimeout(function() {
                if (clientesUI.mapaFicha) {
                    clientesUI.mapaFicha.invalidateSize();
                }
            }, 100);
        } catch (error) {
            $("#clDetalleMapaPanel").addClass("d-none");
        }
    }

    $("#clModal .modal-body").scrollTop(0);
}

$(document).on("click", ".js-cl-ver", function() {
    const id = Number($(this).attr("data-id"));

    const c = clientesUI.lista.find(function(item) {
        return Number(item.id) === id;
    });

    if (c) abrirDetalleCliente(c);
});

$(document).on("click", "#clDetalleVolver", function() {
    mostrarListaClientes();
});

$(document).on("click", "#clDetalleEditar", function() {
    const id = Number($(this).attr("data-id"));

    const c = clientesUI.lista.find(function(item) {
        return Number(item.id) === id;
    });

    if (c) abrirFormularioCliente(c);
});
