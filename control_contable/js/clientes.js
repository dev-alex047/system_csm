
const clientesUI = {
    lista: [],
    mapa: null,
    pin: null,
    timer: null,
    peticion: null,
    folio: 0
};

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

    const filtrados = clientesUI.lista.filter(function(c) {
        return [
            nombreCompletoCliente(c),
            c.alias, c.numero, c.correo, c.domicilio
        ].join(" ").toLocaleLowerCase("es").includes(buscar);
    });

    const $tabla = $("#clTabla").empty();

    $("#clConteo").text(
        filtrados.length + " de " + clientesUI.lista.length + " clientes"
    );

    filtrados.forEach(function(c) {
        const $fila = $("<tr>");

        $("<td>").text(nombreCompletoCliente(c)).appendTo($fila);
        $("<td>").text(c.alias || "—").appendTo($fila);
        $("<td>").text(c.numero || "—").appendTo($fila);
        $("<td>").text(c.domicilio || "Sin domicilio").appendTo($fila);
        $("<td>").text(Number(c.tipo) === 1 ? "Cliente" : "Prospecto")
            .appendTo($fila);

        const $acciones = $("<td>").addClass("text-nowrap");

        $("<button>", {
            type: "button",
            class: "btn btn-sm btn-outline-success me-2 js-cl-editar",
            text: "Editar"
        }).attr("data-id", c.id).appendTo($acciones);

        $("<button>", {
            type: "button",
            class: "btn btn-sm btn-outline-danger js-cl-eliminar",
            text: "Eliminar"
        }).attr("data-id", c.id).appendTo($acciones);

        $fila.append($acciones).appendTo($tabla);
    });

    if (!filtrados.length) {
        $("<tr>").append(
            $("<td>", {
                colspan: 6,
                class: "text-center text-secondary py-4",
                text: "No se encontraron clientes."
            })
        ).appendTo($tabla);
    }
}

function liberarMapaClientes() {
    clientesUI.folio++;
    clearTimeout(clientesUI.timer);

    if (clientesUI.peticion) clientesUI.peticion.abort();
    clientesUI.peticion = null;

    if (clientesUI.mapa) clientesUI.mapa.remove();

    clientesUI.mapa = null;
    clientesUI.pin = null;
}

function mostrarListaClientes() {
    liberarMapaClientes();
    $("#clFormulario").addClass("d-none");
    $("#clListadoPanel").removeClass("d-none");
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

    const $form = $("#clFormulario");
    $form[0].reset();

    $form.find("[name]").each(function() {
        $(this).val(c?.[this.name] ?? "");
    });

    $("#cl_id").val(c?.id || 0);
    $("#cl_tipo").val(c?.tipo ?? 1);
    $("#cl_limite_credito").val(c?.limite_credito ?? "0.00");

    if (!c) $("#cl_pais").val("México");

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

    $("#cl_nombre").trigger("focus");
}

$(document).on("click", "#btnClientes", function() {
    if (!window.CSM_ES_ADMIN) return;

    mostrarListaClientes();

    bootstrap.Modal.getOrCreateInstance(
        document.getElementById("clModal")
    ).show();

    consultarClientesAdmin();
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

        clientesUI.peticion = $.ajax({
            url: "https://api.locationiq.com/v1/autocomplete",
            method: "GET",
            dataType: "json",
            timeout: 10000,
            data: {
                key: CLAVE_LOCATIONIQ.trim(),
                q: q,
                countrycodes: "mx",
                "accept-language": "es",
                limit: 6
            }
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
            direccion.neighbourhood || direccion.quarter || "",
        cp: direccion.postcode || "",
        estado: direccion.state || "",
        pais: direccion.country || "México"
    };

    Object.keys(valores).forEach(function(campo) {
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

        const clientes = Array.isArray(r.results) ? r.results : [];

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
