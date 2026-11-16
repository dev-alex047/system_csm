
let pasoLogin = 1;
let enviandoLogin = false;

// INICIALIZACIÓN
$(document).ready(function() {

    $("#correo").on("input", function() {
        const valor = this.value.trim();
        const valido = this.checkValidity() &&
            /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(valor);

        $("#btnSiguienteCorreo").prop("disabled", !valido);
        mostrarErrorLogin("");
    });

    $("#correo").on("keydown", function(e) {
        if (e.key === "Enter") {
            e.preventDefault();
            $("#btnSiguienteCorreo").trigger("click");
        }
    });

    $("#btnSiguienteCorreo").on("click", function() {
        if ($(this).prop("disabled")) return;

        $("#correo").val($("#correo").val().trim().toLowerCase());
        cambiarPasoLogin(2);
    });

    $("#btnVolver").on("click", function() {
        cambiarPasoLogin(1);
    });

    $("#btnVerClave").on("click", function() {
        const visible = $("#clave").attr("type") === "text";
        $("#clave").attr("type", visible ? "password" : "text");

        $(this).text(visible ? "Mostrar" : "Ocultar")
            .attr("aria-pressed", String(!visible))
            .attr("aria-label", visible ? "Mostrar contraseña" : "Ocultar contraseña");
    });

    $("#clave").on("input", function() {
        mostrarErrorLogin("");
    });

    $("#formAcceso").on("submit", function(e) {
        e.preventDefault();
        if (enviandoLogin) return;

        if (pasoLogin === 1) {
            $("#btnSiguienteCorreo").trigger("click");
            return;
        }

        const correo = $("#correo").val().trim();
        const clave = $("#clave").val();

        /*if (clave.length < 8) {
            mostrarErrorLogin("Introduce una contraseña de al menos 8 caracteres.");
            $("#clave").trigger("focus");
            return;
        }*/

        if (!clave) { 
            mostrarErrorLogin("Introduce tu contraseña."); 
        }

        enviandoLogin = true;
        $("#btnEntrar").prop("disabled", true).text("Verificando acceso...");
        mostrarErrorLogin("");

        // Solicitar token CSRF únicamente al intentar iniciar sesión.
        $.ajax({
            type: "POST",
            url: "php/login.php",
            data: {action:"estado"},
            dataType: "json"
        }).done(function(r) {

            if (r && r.status === "ok" && r.results && r.results.autenticado) {
                window.location.replace("index.html");
                return;
            }

            const csrf = r && r.results ? r.results.csrf : "";

            if (!csrf) {
                mostrarErrorLogin("No fue posible preparar el inicio de sesión.");
                enviandoLogin = false;
                $("#btnEntrar").prop("disabled", false).html('Iniciar sesión <span aria-hidden="true">→</span>');
                return;
            }

            $.ajax({
                type: "POST",
                url: "php/login.php",
                dataType: "json",
                data: {
                    action: "entrar",
                    correo: correo,
                    clave: clave
                },
                headers: {"X-CSRF-Token":csrf}
            }).done(function(data) {
                if (data && data.status === "ok") {
                    window.location.replace("index.html");
                    return;
                }

                mostrarErrorLogin(data && data.answer ? data.answer : "No fue posible iniciar sesión.");
                $("#clave").val("").trigger("focus");

            }).fail(function(xhr) {
                const data = xhr.responseJSON || {};
                mostrarErrorLogin(data.answer || "No fue posible validar las credenciales.");
                $("#clave").val("").trigger("focus");

            }).always(function() {
                enviandoLogin = false;
                $("#btnEntrar").prop("disabled", false).html('Iniciar sesión <span aria-hidden="true">→</span>');
            });

        }).fail(function(xhr) {
            mostrarErrorLogin(xhr.status === 404
                ? "El inicio de sesión todavía no está configurado en PHP."
                : "No fue posible conectar con el servidor.");

            enviandoLogin = false;
            $("#btnEntrar").prop("disabled", false).html('Iniciar sesión <span aria-hidden="true">→</span>');
        });
    });
    cambiarPasoLogin(1);
    $("#correo").trigger("input");
});


// CAMBIAR DE CORREO A CONTRASEÑA
function cambiarPasoLogin(paso) {
    pasoLogin = paso;

    $("#vistaCorreo").prop("hidden", paso !== 1);
    $("#vistaClave").prop("hidden", paso !== 2);

    $("#tituloLogin").text(paso === 1
        ? "Bienvenido de nuevo" : "Introduce tu contraseña");

    $("#mensajePaso").text(paso === 1
        ? "Introduce tu correo para acceder al sistema."
        : "Ingresa tu contraseña para continuar.");

    mostrarErrorLogin("");

    if (paso === 2) {
        $("#correoElegido").text($("#correo").val());
        $("#clave").trigger("focus");
    } else {
        $("#clave").val("").attr("type", "password");
        $("#btnVerClave").text("Mostrar").attr("aria-pressed", "false");
        $("#correo").trigger("focus");
    }
}

// MOSTRAR MENSAJES
function mostrarErrorLogin(mensaje) {
    $("#errorLogin").text(mensaje || "").prop("hidden", !mensaje);
}
