
var pasoLogin = 1;
var csrfLogin = "";
var enviandoLogin = false;

// INICIALIZACIÓN
$(document).ready(function() {
    $("#btnContinuar, #btnEntrar").prop("disabled", true);
    verificarSesionLogin();

    $("#formAcceso").on("submit", function(e) {
        e.preventDefault();
        if (enviandoLogin) return;

        if (pasoLogin === 1) {
            const correo = $("#correo")[0];

            if (!correo.checkValidity()) {
                correo.reportValidity();
                return;
            }

            cambiarPasoLogin(2);
            return;
        }

        const correo = $("#correo").val().trim().toLowerCase();
        const clave = $("#clave").val();

        if (!clave || clave.length < 8) {
            mostrarErrorLogin("Introduce tu contraseña.");
            $("#clave").trigger("focus");
            return;
        }

        if (!csrfLogin) {
            mostrarErrorLogin("No se pudo verificar la sesión. Actualiza la página.");
            return;
        }

        enviandoLogin = true;
        $("#btnEntrar").prop("disabled", true).text("Verificando acceso...");

        $.ajax({
            type: "POST",
            url: "php/login.php",
            dataType: "json",
            data: {
                action: "entrar",
                correo: correo,
                clave: clave
            },
            headers: {
                "X-CSRF-Token": csrfLogin
            }
        }).done(function(data) {
            if (!data || data.status !== "ok") {
                mostrarErrorLogin(data && data.answer ? data.answer : "Correo o contraseña incorrectos.");
                $("#clave").val("").trigger("focus");
                return;
            }

            window.location.replace("index.html");

        }).fail(function(xhr) {
            const data = xhr.responseJSON || {};

            mostrarErrorLogin(data.answer || "No fue posible iniciar sesión. Inténtalo nuevamente.");
            $("#clave").val("").trigger("focus");

            if (xhr.status === 403) verificarSesionLogin();

        }).always(function() {
            enviandoLogin = false;
            $("#btnEntrar").prop("disabled", false).html('Iniciar sesión <span aria-hidden="true">→</span>');
        });
    });

    $("#btnVolver").on("click", function() {
        cambiarPasoLogin(1);
    });

    $("#btnVerClave").on("click", function() {
        const visible = $("#clave").attr("type") === "text";

        $("#clave").attr("type", visible ? "password" : "text");
        $(this).text(visible ? "Mostrar" : "Ocultar")
            .attr("aria-label", visible ? "Mostrar contraseña" : "Ocultar contraseña")
            .attr("aria-pressed", String(!visible));
    });

    $("#correo, #clave").on("input", function() {
        mostrarErrorLogin("");
    });
});

// VERIFICAR SESIÓN
function verificarSesionLogin() {
    $.ajax({
        type: "POST",
        url: "php/login.php",
        dataType: "json",
        cache: false,
        data: {action: "estado"}
    }).done(function(data) {
        if (!data || data.status !== "ok" || !data.results) {
            mostrarErrorLogin("No fue posible verificar el acceso.");
            return;
        }

        if (data.results.autenticado) {
            window.location.replace("index.html");
            return;
        }

        csrfLogin = data.results.csrf || "";

        if (!csrfLogin) {
            mostrarErrorLogin("No se pudo preparar el inicio de sesión.");
            return;
        }

        $("#btnContinuar, #btnEntrar").prop("disabled", false);

    }).fail(function(xhr) {
        const data = xhr.responseJSON || {};
        mostrarErrorLogin(data.answer || "No hay conexión con el servidor. Actualiza la página.");
    });
}

// CAMBIAR ENTRE CORREO Y CONTRASEÑA
function cambiarPasoLogin(paso) {
    pasoLogin = paso;

    $("#vistaCorreo").prop("hidden", paso !== 1);
    $("#vistaClave").prop("hidden", paso !== 2);

    $("#pasoCorreo").toggleClass("activo", paso === 1)
        .toggleClass("completado", paso === 2);

    $("#pasoClave").toggleClass("activo", paso === 2);

    $("#tituloLogin").text(paso === 1 ? "Bienvenido de nuevo" : "Introduce tu contraseña");

    $("#mensajePaso").text(paso === 1
        ? "Introduce tu correo para acceder al sistema."
        : "Continúa con tu cuenta para entrar a Control Contable.");

    mostrarErrorLogin("");

    if (paso === 2) {
        $("#correoElegido").text($("#correo").val().trim().toLowerCase());
        $("#clave").trigger("focus");
    } else {
        $("#clave").val("").attr("type", "password");
        $("#btnVerClave").text("Mostrar").attr("aria-pressed", "false");
        $("#correo").trigger("focus");
    }
}

// MOSTRAR ERRORES
function mostrarErrorLogin(mensaje) {
    $("#errorLogin").text(mensaje || "").prop("hidden", !mensaje);
}
