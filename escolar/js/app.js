const MAESTRO_ID_ACTUAL = 1;
const state = {
    activeView: 'home',
    editing: null,
    importedRows: [],
    evaluationPeriods: [
    'Primer trimestre',
    'Segundo trimestre',
    'Tercer trimestre'
],

periodGrades: {},
activeEvaluationKey: null,
    pendingEvaluation: {
    id: 1,
    subject: 'Matemáticas',
    group: '5° A',
    period: 'Primer trimestre'
},
    subjects: [],
    teachers: [],
    students: [],
    studentGroupFilter: 'all',
    assignments: [],
    schedule: [],
    criteria: [],
    grades: {},
    pendingGradeChanges: {}
};

const days = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'];
const entityModal = new bootstrap.Modal(document.getElementById('entityModal'));
//const pendingEvaluationModal = new bootstrap.Modal(document.getElementById('pendingEvaluationModal'));
const activityModalInstance = bootstrap.Modal.getOrCreateInstance(document.getElementById('activityModal'));

var saveTimer = null;

window.addEventListener('DOMContentLoaded', () => {
    bindNavigation();
    bindSearches();
    bindForms();
    bindEvaluationEvents();
    activarOrdenAlumnos();
    bindEvaluationViews();
    bindBulkUpload();
    //bindPendingEvaluation();
    bindSettingsMenu();
    //bindEvaluationPdf();

    renderAll();
    initializeEvaluationGrades();
    initBulkGradeTools();
    getAsignaturas();
    getAlumnos();
    getMaestros();
    getHorarios();

    navigateTo('home', false);
    //setTimeout(showPendingEvaluationReminder, 650);
});

function bindNavigation() {
    document.addEventListener('click', (event) => {
        const moduleButton = event.target.closest('[data-module]');
        if (moduleButton) {
            navigateTo(moduleButton.dataset.module);
            return;
        }

        const studentGroupButton = event.target.closest('[data-student-group-filter]');
        
        if (studentGroupButton) {
            state.studentGroupFilter = studentGroupButton.dataset.studentGroupFilter;
            renderStudents();
            return;
        }

        const actionButton = event.target.closest('[data-action]');
        if (!actionButton) return;

        const { action, entity, id } = actionButton.dataset;

        /*if (action === 'add-subject-criterion') {
    var lista = document.getElementById('subjectCriteriaList');

    if (lista) {
        lista.insertAdjacentHTML(
            'beforeend',
            criterioAsignaturaRow({
                id: 0,
                nombre: '',
                porcentaje: '',
                color: '#B8943C'
            })
        );

        var filas = lista.querySelectorAll(".subject-criterion-row");
        var nuevaFila = filas[filas.length - 1];

        var nuevoInput = nuevaFila.querySelector(
            ".subject-criterion-percentage"
        );

        var porcentajeInicial = 100 / filas.length;

        nuevoInput.value = porcentajeInicial.toFixed(2);

        redistribuirPorcentajesCriteriosAsignatura(
            nuevoInput
        );
    }

    return;
}*/

/*if (action === 'remove-subject-criterion') {
    var fila = actionButton.closest('.subject-criterion-row');

    if (fila) {
        fila.remove();
        redistribuirPorcentajesCriteriosAsignatura();
    }

    return;
}*/

if (action === 'add-subject-criterion') {
    var lista = document.getElementById('subjectCriteriaList');

    lista.insertAdjacentHTML('beforeend', criterioAsignaturaRow({
        id: 0,
        nombre: '',
        porcentaje: '',
        afecta_promedio: 1,
        tipo_captura: 'numero',
        usa_actividades: 0,
        color: '#B8943C'
    }));

    actualizarTotalCriteriosAsignatura();
    return;
}

if (action === 'remove-subject-criterion') {
    var fila = actionButton.closest('.subject-criterion-row');

    if (fila) fila.remove();

    actualizarTotalCriteriosAsignatura();
    return;
}



        if (action === 'new') openEntityModal(entity);
        if (action === 'edit') openEntityModal(entity, Number(id));
        if (action === 'delete') confirmDelete(entity, Number(id));
    });
}

function bindSearches() {
    document.getElementById('subjectSearch').addEventListener('input', renderSubjects);
    document.getElementById('teacherSearch').addEventListener('input', renderTeachers);
    document.getElementById('studentSearch').addEventListener('input', renderStudents);
    document.getElementById('scheduleGroupFilter').addEventListener('change', renderSchedule);
}

function bindForms() {
    const entityForm = document.getElementById('entityForm');

    if (entityForm) {
        entityForm.addEventListener('submit', saveEntity);
    }

    const modalBody = document.getElementById('entityModalBody');

    if (modalBody) {
        /*modalBody.addEventListener('input', function(event) {
            if (event.target.classList.contains('subject-criterion-percentage')) {
                redistribuirPorcentajesCriteriosAsignatura(event.target);
            }
        });*/

        modalBody.addEventListener('input', function(event) {
    if (event.target.classList.contains('subject-criterion-percentage')) {
        actualizarTotalCriteriosAsignatura();
    }
});

modalBody.addEventListener('change', function(event) {
    var fila = event.target.closest('.subject-criterion-row');

    if (!fila) return;

    if (event.target.classList.contains('subject-criterion-type')) {
        var tipo = event.target.value;
        var afecta = fila.querySelector('.subject-criterion-affects');
        var actividades = fila.querySelector('.subject-criterion-activities');

        if (tipo !== 'numero') {
            afecta.checked = false;
            actividades.checked = false;
        }
    }

    if (
        event.target.classList.contains('subject-criterion-affects') ||
        event.target.classList.contains('subject-criterion-type') ||
        event.target.classList.contains('subject-criterion-activities')
    ) {
        actualizarTotalCriteriosAsignatura();
    }
});
    }
}

function actualizarTotalCriteriosAsignatura() {
    var total = 0;

    document.querySelectorAll('.subject-criterion-row').forEach(function(fila) {
        var afecta = fila.querySelector('.subject-criterion-affects').checked;
        var porcentaje = fila.querySelector('.subject-criterion-percentage');
        var tipo = fila.querySelector('.subject-criterion-type');
        var actividades = fila.querySelector('.subject-criterion-activities');

        porcentaje.disabled = !afecta;

        if (!afecta) {
            //porcentaje.value = 0;
            actividades.checked = false;
            actividades.disabled = true;
        } else {
            actividades.disabled = tipo.value !== 'numero';
            total += Number(porcentaje.value || 0);
        }
    });

    total = Math.round(total * 100) / 100;

    var elemento = document.getElementById('subjectCriteriaTotal');
    if (!elemento) return;

    var diferencia = Math.round((100 - total) * 100) / 100;

    elemento.textContent = total + '%';

    if (total === 100) {
        elemento.className = 'fw-bold text-success';
        elemento.title = 'Porcentaje completo';
    } else if (total < 100) {
        elemento.className = 'fw-bold text-warning';
        elemento.title = 'Faltan ' + diferencia + '%';
    } else {
        elemento.className = 'fw-bold text-danger';
        elemento.title = 'Excede por ' + Math.abs(diferencia) + '%';
    }
}

function redistribuirPorcentajesCriteriosAsignatura(inputCambiado = null) {
    var inputs = Array.from(
        document.querySelectorAll(".subject-criterion-percentage")
    );

    if (!inputs.length) {
        actualizarTotalCriteriosAsignatura();
        return;
    }

    function formatear(unidades) {
        var valor = (unidades / 100).toFixed(2);

        return valor
            .replace(/\.00$/, "")
            .replace(/(\.\d)0$/, "$1");
    }

    function distribuir(destinos, unidadesDisponibles) {
        if (!destinos.length) return;

        var valores = destinos.map(function(input) {
            return Math.max(0, Number(input.value) || 0);
        });

        var sumaActual = valores.reduce(function(total, valor) {
            return total + valor;
        }, 0);

        if (sumaActual <= 0) {
            valores = destinos.map(function() {
                return 1;
            });

            sumaActual = destinos.length;
        }

        var reparto = destinos.map(function(input, index) {
            var exacto =
                (valores[index] / sumaActual) *
                unidadesDisponibles;

            var unidades = Math.floor(exacto);

            return {
                input: input,
                index: index,
                unidades: unidades,
                decimal: exacto - unidades
            };
        });

        var usadas = reparto.reduce(function(total, item) {
            return total + item.unidades;
        }, 0);

        var pendientes = unidadesDisponibles - usadas;

        reparto.sort(function(a, b) {
            return b.decimal - a.decimal || a.index - b.index;
        });

        reparto.forEach(function(item) {
            if (pendientes > 0) {
                item.unidades++;
                pendientes--;
            }

            item.input.value = formatear(item.unidades);
        });
    }

    if (inputs.length === 1) {
        inputs[0].value = "100";
        actualizarTotalCriteriosAsignatura();
        return;
    }

    if (inputCambiado) {
        var valor = Number(inputCambiado.value) || 0;

        valor = Math.max(0, Math.min(100, valor));

        var unidadesCambiadas = Math.round(valor * 100);

        inputCambiado.value = formatear(unidadesCambiadas);

        var otros = inputs.filter(function(input) {
            return input !== inputCambiado;
        });

        distribuir(
            otros,
            10000 - unidadesCambiadas
        );
    } else {
        distribuir(inputs, 10000);
    }

    actualizarTotalCriteriosAsignatura();
}

function bindSettingsMenu() {
    document.querySelectorAll('[data-settings-section]').forEach((button) => {
        button.addEventListener('click', () => openSettingsModal(button.dataset.settingsSection));
    });
}

function getMoreSettingsHtml() {
    return `
        <div class="d-grid gap-2">

            <button type="button" class="settings-action-card btn text-start">
                <span class="settings-option-icon">
                    <i class="bi bi-people"></i>
                </span>

                <span class="flex-grow-1">
                    <span class="d-block fw-semibold">Usuarios</span>
                    <span class="d-block text-muted small">Administra usuarios del sistema.</span>
                </span>

                <i class="bi bi-chevron-right text-muted"></i>
            </button>

            <button type="button" class="settings-action-card btn text-start">
                <span class="settings-option-icon">
                    <i class="bi bi-shield-check"></i>
                </span>

                <span class="flex-grow-1">
                    <span class="d-block fw-semibold">Permisos</span>
                    <span class="d-block text-muted small">Controla accesos y funciones.</span>
                </span>

                <i class="bi bi-chevron-right text-muted"></i>
            </button>

            <button type="button" class="settings-action-card btn text-start">
                <span class="settings-option-icon">
                    <i class="bi bi-file-earmark-bar-graph"></i>
                </span>

                <span class="flex-grow-1">
                    <span class="d-block fw-semibold">Reportes</span>
                    <span class="d-block text-muted small">Opciones de reportes escolares.</span>
                </span>

                <i class="bi bi-chevron-right text-muted"></i>
            </button>

        </div>
    `;
}

function openSettingsModal(section) {
    const title = document.getElementById('settingsModalLabel');
    const subtitle = document.getElementById('settingsModalSubtitle');
    const icon = document.getElementById('settingsModalIcon');
    const body = document.getElementById('settingsModalBody');

    if (section === 'school') {
        title.textContent = 'Escuela y ajustes';
        subtitle.textContent = 'Datos generales, ciclo escolar y evaluaciones';
        icon.className = 'bi bi-building';

        body.innerHTML = getSchoolSettingsHtml();
    } else {
        title.textContent = 'Más opciones';
        subtitle.textContent = 'Configuraciones adicionales del sistema';
        icon.className = 'bi bi-sliders';

        body.innerHTML = getMoreSettingsHtml();
    }

    if (section === 'school') bindSettingsForm();

    bootstrap.Modal.getOrCreateInstance(document.getElementById('settingsModal')).show();
}

function bindSettingsForm() {
    const form = document.getElementById('settingsForm');
    if (!form) return;

    form.addEventListener('submit', (event) => {
        event.preventDefault();

        const data = new FormData(form);
        const cycle = String(data.get('cycle') || '').trim();
        const periods = String(data.get('periods') || '')
            .split(',')
            .map((period) => period.trim())
            .filter(Boolean);

        if (cycle) document.getElementById('evaluationCycle').value = cycle;
        if (periods.length) state.evaluationPeriods = periods;

        bootstrap.Modal.getOrCreateInstance(document.getElementById('settingsModal')).hide();
        showToast('Configuración guardada correctamente.');
    });
}

function getSchoolSettingsHtml() {
    return `
        <form id="settingsForm">

            <div class="settings-section mb-4">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <i class="bi bi-building text-primary"></i>
                    <h3 class="h6 fw-bold mb-0">Datos de la escuela</h3>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Nombre de la escuela</label>
                    <input class="form-control" name="schoolName" value="Instituto Educativo Siglo XXI">
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Clave escolar</label>
                        <input class="form-control" name="schoolKey" value="14PPR0123X">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Nivel</label>
                        <select class="form-select" name="schoolLevel">
                            <option>Preescolar</option>
                            <option selected>Primaria</option>
                            <option>Secundaria</option>
                            <option>Bachillerato</option>
                        </select>
                    </div>
                </div>
            </div>

            <hr>

            <div class="settings-section my-4">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <i class="bi bi-calendar3 text-primary"></i>
                    <h3 class="h6 fw-bold mb-0">Ciclo y periodos</h3>
                </div>

                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Ciclo escolar</label>
                        <input class="form-control" name="cycle" value="${escapeHtml(document.getElementById('evaluationCycle').value)}">
                    </div>

                    <div class="col-md-7">
                        <label class="form-label fw-semibold">Periodos de evaluación</label>
                        <input class="form-control" name="periods" value="${escapeHtml(state.evaluationPeriods.join(', '))}">
                    </div>
                </div>
            </div>

            <hr>

            <div class="settings-section my-4">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <i class="bi bi-clipboard-check text-primary"></i>
                    <h3 class="h6 fw-bold mb-0">Captura de calificaciones</h3>
                </div>

                <div class="d-flex flex-column gap-3">
                    <div class="form-check form-switch">
                        <input id="autoSave" class="form-check-input" type="checkbox" checked>
                        <label class="form-check-label" for="autoSave">Guardado automático</label>
                    </div>

                    <div class="form-check form-switch">
                        <input id="roundAverage" class="form-check-input" type="checkbox" checked>
                        <label class="form-check-label" for="roundAverage">Redondear promedios a un decimal</label>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 pt-2">
                <button type="button" class="btn btn-light border rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary rounded-pill px-4">Guardar cambios</button>
            </div>

        </form>
    `;
}

function bindEvaluationEvents() {
    $("#evaluationGroup").on("change", function() {
    renderEvaluationSubjectOptions();
    cargarCriteriosAsignaturaSeleccionada();
    getCalificaciones();
});

$("#evaluationSubject").on("change", function() {
    cargarCriteriosAsignaturaSeleccionada();
    getCalificaciones();
});

$("#evaluationPeriod").on("change", function() {
    getCalificaciones();
});

$("#evaluationCycle").on("change", function() {
    renderEvaluationGroupOptions();
    renderEvaluationSubjectOptions();
    cargarCriteriosAsignaturaSeleccionada();
    getCalificaciones();
});

    document.getElementById('btnDownloadTemplate').addEventListener('click', downloadGradeTemplate);

    const btnSaveGrades = document.getElementById('btnSaveGrades');

if (btnSaveGrades) {
    btnSaveGrades.addEventListener('click', function() {
        guardarCalificacionesPendientes();
    });
}
    const btnGeneratePeriodPdf = document.getElementById('btnGeneratePeriodPdf');
    const btnGenerateFinalPdf = document.getElementById('btnGenerateFinalPdf');
    const btnGeneratePerformancePdf = document.getElementById('btnGeneratePerformancePdf');

    if (btnGeneratePeriodPdf) {
    btnGeneratePeriodPdf.addEventListener('click', async function() {
        if (obtenerCalificacionesPendientes().length) {
            Swal.fire({
                icon: 'warning',
                title: 'Hay cambios sin guardar',
                text: 'Guarda las calificaciones antes de generar el PDF.',
                confirmButtonColor: '#1D1D1F'
            });

            return;
        }

        const datosPdf = obtenerDatosPdfEvaluacion('periodo', '');


try {

    await cargarDetalleExamenPdf(
        datosPdf
    );

    generarPdfEvaluacion(
        datosPdf
    );

} catch (error) {

    console.error(error);

    Swal.fire({
        icon: "error",
        title: "No fue posible generar el PDF",
        text:
            error.message ||
            "No fue posible obtener el detalle de los exámenes.",
        confirmButtonColor:
            "#1D1D1F"
    });
}
    });
}

    if (btnGenerateFinalPdf) {
    btnGenerateFinalPdf.addEventListener('click', function() {
        if (obtenerCalificacionesPendientes().length) {
            Swal.fire({
                icon: 'warning',
                title: 'Hay cambios sin guardar',
                text: 'Guarda las calificaciones antes de generar el PDF final.',
                confirmButtonColor: '#1D1D1F'
            });

            return;
        }

        cargarTodosLosPeriodos(function() {
            const datosPdf = obtenerDatosPdfEvaluacion('final', '');

            generarPdfEvaluacion(datosPdf);
        });
    });
}

if (btnGeneratePerformancePdf) {
    btnGeneratePerformancePdf.addEventListener('click', function() {
        generarPdfAprovechamientoEscolar();
    });
}

    const gradeTableBody = document.getElementById('gradeTableBody');

gradeTableBody.addEventListener(
    'input',
    function(event) {

        const input =
            event.target.closest(
                '.grade-input'
            );

        if (!input) return;


        let texto =
            input.value.trim();


        /*
         * Vacío = pendiente
         */
        if (texto === '') {

            actualizarCalificacionDirecta(
                input,
                null
            );

            return;
        }

        if (/^\d+\.$/.test(texto)) {
            return;
        }


        let value = Number(texto);

        if (!Number.isFinite(value)) {
            return;
        }

        const tipoCaptura = input.dataset.captureType || "numero";

if (tipoCaptura === "inasistencias") {
    value = Math.max(0, Math.trunc(value));
    input.value = value;
    actualizarCalificacionDirecta(input, value);
    return;
}

        if (value > 10) {
            value = 10;
            input.value = 10;
        }

        if (value < 0) {
            value = 0;
            input.value = 0;
        }

        if (texto.includes('.')) {
            const partes = texto.split('.');

            if (partes[1] && partes[1].length > 2) {
                value =
                    Number(
                        value.toFixed(2)
                    );

                input.value =
                    value;
            }
        }
        actualizarCalificacionDirecta(
            input,
            value
        );
    }
);

function actualizarCalificacionDirecta(
    input,
    value
) {

    const enrollment =
        input.dataset.enrollment;

    const alumnoId =
        Number(
            input.dataset.studentId
        );

    const criterioId =
        Number(
            input.dataset.criterionId
        );

    state.grades[enrollment] ??= {};

    state.grades[
        enrollment
    ][criterioId] =
        value;

    var criterio =
    state.criteria.find(
        function(item) {
            return Number(item.id) ===
                Number(criterioId);
        }
    );


if (criterio) {

    var elementoAportacion =
        document.querySelector(
            '[data-contribution-for="' +
            CSS.escape(
                enrollment +
                '-' +
                criterioId
            ) +
            '"]'
        );


    if (elementoAportacion) {

        var aportacion =
            calcularAportacionCriterio(
                value,
                criterio
            );

        var maximo =
            maximoAportacionCriterio(
                criterio
            );


        elementoAportacion.textContent =
            'Aporta ' +
            formatearAportacion(
                aportacion
            ) +
            ' de ' +
            formatearAportacion(
                maximo
            );
    }
}

    registrarCambioCalificacion(
        alumnoId,
        enrollment,
        criterioId,
        value
    );

    syncCurrentEvaluationGrades();

    updateAveragesOnly();

    marcarCambiosSinGuardar();
}


/* =========================
   CONDUCTA B / R / M
========================= */

gradeTableBody.addEventListener(
    "change",
    function(event) {

        var select =
            event.target.closest(
                ".conduct-grade-select"
            );

        if (!select) return;

        var value =
            String(select.value || "")
                .trim()
                .toUpperCase();

        if (
            value !== "" &&
            !["B", "R", "M"].includes(value)
        ) {
            return;
        }

        var enrollment =
            select.dataset.enrollment;

        var alumnoId =
            Number(
                select.dataset.studentId
            );

        var criterioId =
            Number(
                select.dataset.criterionId
            );

        state.grades[enrollment] ??= {};

        state.grades[
            enrollment
        ][criterioId] =
            value;

        registrarCambioCalificacion(
            alumnoId,
            enrollment,
            criterioId,
            value
        );

        syncCurrentEvaluationGrades();

        updateAveragesOnly();

        marcarCambiosSinGuardar();
    }
);


/* =========================
   BOTÓN DE ACTIVIDADES
========================= */

gradeTableBody.addEventListener(
    'click',
    function(event) {

        var boton =
            event.target.closest(
                '.btn-activity-grade'
            );

        if (!boton) return;

        abrirCalificacionActividades(
            Number(
                boton.dataset.criterionId
            ),
            Number(
                boton.dataset.studentId
            ),
            boton.dataset.enrollment
        );
    }
);

}

function abrirCalificacionActividades(
    criterioId,
    alumnoId,
    enrollment
) {

    var criterio =
        state.criteria.find(function(item) {
            return Number(item.id) ===
                Number(criterioId);
        });


    var alumno =
        state.students.find(function(item) {
            return Number(item.id) ===
                Number(alumnoId);
        });


    if (!criterio || !alumno) return;


    $("#activityModalLabel").text(
        "Calificar · " + criterio.name
    );

    $("#activityModalContext").text(
        alumno.name + " · " + $("#evaluationGroup").val() + " · " + $("#evaluationPeriod").val()
    );

    $("#activityModalBody").html(`
        <div class="text-center py-5">
            <div class="spinner-border spinner-border-sm"></div>
        </div>
    `);

    activityModalInstance.show();

    var formData = new FormData();

    formData.append("action", "getActividadesEvaluacion");
    formData.append("asignatura_id", $("#evaluationSubject").val());
    formData.append("criterio_id", criterioId);
    formData.append("periodo", $("#evaluationPeriod").val());
    formData.append("ciclo_escolar", $("#evaluationCycle").val());
    formData.append("grupo", $("#evaluationGroup").val());
    formData.append("alumno_id",alumnoId);

    $.ajax({
        type: "POST",
        url: "php/index.php",
        data: formData,
        dataType: "json",
        processData: false,
        contentType: false

    }).done(function(data) {

        if (!data || data.status !== "ok") {

            Swal.fire(
                "Error",
                data && data.answer
                    ? data.answer
                    : "No fue posible consultar las actividades.",
                "error"
            );

            return;
        }


        var actividades =
            Array.isArray(data.results)
                ? data.results
                : [];


        if (!actividades.length) {

    $("#activityModalBody").html(`
        <div class="text-center py-5 px-3">

            <div
                class="d-inline-flex align-items-center justify-content-center
                       rounded-circle bg-light mb-3"
                style="width:64px;height:64px;"
            >
                <i class="bi bi-list-check fs-2 text-primary"></i>
            </div>

            <h3 class="h5 fw-bold mb-2">
                Aún no hay actividades
            </h3>

            <p class="text-muted mb-4">
                Configura primero las tareas o exámenes
                que forman parte de
                <strong>${escapeHtml(criterio.name)}</strong>.
            </p>

            <button
    type="button"
    class="btn btn-outline-primary btn-sm rounded-pill w-100 mt-3
           d-flex align-items-center justify-content-between gap-2"
    onclick="abrirConfiguracionActividades(${criterio.id})">

    <span>
        <i class="bi bi-sliders me-2"></i>
        Administrar actividades
    </span>

    <i class="bi bi-chevron-right"></i>
</button>

        </div>
    `);

    return;
}


        $("#activityModalBody")
            .attr(
                "data-criterion-id",
                criterioId
            )
            .attr(
                "data-student-id",
                alumnoId
            )
            .attr(
                "data-enrollment",
                enrollment
            );


        $("#activityModalBody").html(`

            <div class="alert alert-light border rounded-4">

                <div class="fw-bold">
                    ${escapeHtml(alumno.name)}
                </div>

                <div class="small text-muted">
                    Captura de 0 a 10.
                    Los espacios vacíos se consideran pendientes.
                </div>

            </div>

            <div class="d-flex justify-content-end mb-3">
    <button type="button"
        class="btn btn-outline-primary btn-sm rounded-pill px-3"
        onclick="abrirConfiguracionActividades(${criterio.id})">
        <i class="bi bi-pencil-square me-2"></i>
        Editar actividades
    </button>
</div>


            <div class="d-grid gap-2">

                ${actividades.map(function(actividad) {

                    return `
                        <div class="border rounded-4 p-3">

                            <div class="row align-items-center g-3">

                                <div class="col">

                                    <div class="fw-semibold">
                                        ${escapeHtml(
                                            actividad.nombre
                                        )}
                                    </div>

                                    <div class="small text-muted text-capitalize">
                                        ${escapeHtml(
                                            actividad.tipo
                                        )}
                                    </div>

                                </div>


                                <div class="col-4 col-md-3">

                                    <input
                                        type="number"
                                        min="0"
                                        max="10"
                                        step="0.01"
                                        class="form-control text-center activity-grade-input"
                                        data-activity-id="${actividad.id}"
                                        value="${
                                            actividad.calificacion === null ||
                                            actividad.calificacion === undefined
                                                ? ''
                                                : actividad.calificacion
                                        }">

                                </div>

                            </div>

                        </div>
                    `;

                }).join('')}

            </div>


            <div class="border-top mt-4 pt-4">

                <div class="d-flex align-items-start justify-content-between gap-4 mb-4">

    <div>

        <div class="text-muted">
            Promedio del criterio
        </div>

        <div
            id="activityContributionPreview"
            class="small text-muted mt-1">

            ${
                Number(criterio.afecta_promedio) === 1
                    ? `
                        Este criterio equivale al
                        ${Number(criterio.weight)}%
                        del promedio final.
                    `
                    : 'Este criterio no afecta el promedio final.'
            }

        </div>

    </div>


    <strong
        id="activityAveragePreview"
        class="fs-4 text-nowrap">
        —
    </strong>

</div>


                <div class="d-flex justify-content-end gap-2">

                    <button
                        type="button"
                        class="btn btn-light border rounded-pill px-4"
                        data-bs-dismiss="modal">
                        Cancelar
                    </button>

                    <button
                        type="button"
                        class="btn btn-primary rounded-pill px-4"
                        onclick="guardarCalificacionActividades()">

                        <i class="bi bi-floppy me-2"></i>
                        Guardar
                    </button>

                </div>

            </div>
        `);


        function actualizarPromedio() {

    var valores = [];


    document
        .querySelectorAll(
            ".activity-grade-input"
        )
        .forEach(function(input) {

            var texto =
                input.value.trim();


            if (texto === "") {
                return;
            }


            var valor =
                Number(texto);


            /*
             * Por seguridad,
             * sólo calculamos valores válidos.
             */
            if (
                !Number.isFinite(valor) ||
                valor < 0 ||
                valor > 10
            ) {
                return;
            }


            valores.push(
                valor
            );
        });


    var promedio = valores.length
            ? valores.reduce(
                function(suma, valor) {
                    return suma + valor;
                },
                0
            ) / valores.length
            : null;



    var promedioMostrar = promedio;

if (promedio !== null && Number(criterio.redondear_resultado) === 1) {
    promedioMostrar = Math.round(promedio);
}

$("#activityAveragePreview").text(
    promedioMostrar === null ? "—" : Number(promedioMostrar).toFixed(
        Number(criterio.redondear_resultado) === 1 ? 0 : 1
    )
);

    var aportacion = calcularAportacionCriterio(promedio, criterio);
    var maximo = maximoAportacionCriterio(criterio);

    if (Number(criterio.afecta_promedio) === 1) {

    $("#activityContributionPreview")
        .text(
            promedio === null
                ? "Este criterio equivale al " +
                  Number(criterio.weight) +
                  "% del promedio final."
                : "Aporta " +
                  formatearAportacion(
                      aportacion
                  ) +
                  " de " +
                  formatearAportacion(
                      maximo
                  ) +
                  " · equivale al " +
                  Number(
                      criterio.weight
                  ) +
                  "% del promedio final."
        );

} else {

    $("#activityContributionPreview")
        .text(
            "Este criterio no afecta el promedio final."
        );
}
}


        $(".activity-grade-input").on("input", function() {

    var texto = this.value.trim();

    if (texto === "") {
        $(this).removeClass("is-invalid");
        actualizarPromedio();
        return;
    }


    /*
     * Permitimos temporalmente:
     * 8.
     * 9.
     *
     * para que el usuario pueda seguir escribiendo
     * 8.5, 9.25, etc.
     */
    if (/^\d+\.$/.test(texto)) {
        return;
    }


    var valor = Number(texto);


    if (!Number.isFinite(valor)) {
        return;
    }


    /*
     * Máximo 10
     */
    if (valor > 10) {
        this.value = 10;
        valor = 10;
    }


    /*
     * Mínimo 0
     */
    if (valor < 0) {
        this.value = 0;
        valor = 0;
    }


    /*
     * Máximo 2 decimales
     */
    if (texto.includes(".")) {

        var partes = texto.split(".");

        if (
            partes[1] &&
            partes[1].length > 2
        ) {

            this.value =
                Number(valor.toFixed(2));

            valor =
                Number(this.value);
        }
    }


    $(this).removeClass("is-invalid");

    actualizarPromedio();
});

actualizarPromedio();
    });
}

function guardarCalificacionActividades() {

    var cuerpo = document.getElementById("activityModalBody");
    var criterioId =
        Number(
            cuerpo.dataset.criterionId
        );

    var alumnoId =
        Number(
            cuerpo.dataset.studentId
        );

    var enrollment =
        cuerpo.dataset.enrollment;


    var inputs =
    Array.from(
        document.querySelectorAll(
            ".activity-grade-input"
        )
    );


var calificaciones = [];


for (
    var i = 0;
    i < inputs.length;
    i++
) {

    var input =
        inputs[i];

    var texto =
        input.value.trim();


    /*
     * Vacío = pendiente.
     */
    if (texto === "") {

        calificaciones.push({
            actividad_id:
                Number(
                    input.dataset.activityId
                ),

            calificacion:
                null,

            observaciones:
                ""
        });

        continue;
    }


    var valor =
        Number(texto);


    var decimales = 0;

    if (texto.includes(".")) {

        decimales =
            texto.split(".")[1].length;
    }


    /*
     * Aquí NO transformamos
     * un error en NULL.
     *
     * Detenemos todo el guardado.
     */
    if (
        !Number.isFinite(valor) ||
        valor < 0 ||
        valor > 10 ||
        decimales > 2
    ) {

        $(input).addClass(
            "is-invalid"
        );

        input.focus();


        Swal.fire({
            icon: "warning",
            title: "Calificación no válida",
            text:
                "Las calificaciones deben estar entre 0 y 10 y pueden tener hasta 2 decimales.",
            confirmButtonColor:
                "#1D1D1F"
        });


        return;
    }


    $(input).removeClass(
        "is-invalid"
    );


    calificaciones.push({
        actividad_id:
            Number(
                input.dataset.activityId
            ),

        calificacion:
            valor,

        observaciones:
            ""
    });
}

var asignaturaId = Number($("#evaluationSubject").val() || 0);
var grupo = String($("#evaluationGroup").val() || "").trim();
var ciclo = String($("#evaluationCycle").val() || "").trim();

var asignacion = state.assignments.find(function(item) {
    return Number(item.asignatura_id) === asignaturaId &&
        item.group === grupo &&
        item.ciclo_escolar === ciclo;
});

if (!asignacion) {
    Swal.fire({
        icon: "warning",
        title: "Asignación no válida",
        text: "La materia no está asignada a este grupo.",
        confirmButtonColor: "#1D1D1F"
    });

    return;
}


    var formData = new FormData();

    formData.append("action", "guardarCalificacionesActividades");
    //formData.append("asignatura_id", $("#evaluationSubject").val());
    formData.append("asignatura_id", asignacion.asignatura_id);
    formData.append("criterio_id", criterioId);
    formData.append("alumno_id", alumnoId);
    //formData.append("maestro_id", MAESTRO_ID_ACTUAL);
    formData.append("maestro_id", asignacion.maestro_id);
    formData.append("asignacion_id", asignacion.id);
    formData.append("periodo", $("#evaluationPeriod").val());
    formData.append("ciclo_escolar", $("#evaluationCycle").val());
    formData.append("grupo", $("#evaluationGroup").val());
    formData.append("calificaciones", JSON.stringify(calificaciones));
    
    $.ajax({
        type: "POST",
        url: "php/index.php",
        data: formData,
        dataType: "json",
        processData: false,
        contentType: false

    }).done(function(data) {

        if (!data || data.status !== "ok") {

            Swal.fire(
                "Error",
                data && data.answer
                    ? data.answer
                    : "No fue posible guardar las calificaciones.",
                "error"
            );

            return;
        }


        var promedio =
            data.promedio === null
                ? null
                : Number(data.promedio);

        var criterio = state.criteria.find(function(item) {
    return Number(item.id) === Number(criterioId);
});

if (promedio !== null && criterio && Number(criterio.redondear_resultado) === 1) {
    promedio = Math.round(promedio);
}

        guardarCalificaciones([
            {
                alumno_id:
                    alumnoId,

                criterio_id:
                    criterioId,

                calificacion:
                    promedio
            }
        ]).done(function(respuesta) {

            if (
                !respuesta ||
                respuesta.status !== "ok"
            ) {
                return;
            }


            state.grades[enrollment] ??= {};

            state.grades[
                enrollment
            ][criterioId] =
                promedio;


            syncCurrentEvaluationGrades();

            activityModalInstance.hide();

            renderGrades();

            showToast(
                "Actividades y promedio guardados correctamente."
            );
        });

    }).fail(function(xhr) {

        console.error(
            xhr.responseText
        );

        Swal.fire(
            "Error",
            "No fue posible guardar las actividades.",
            "error"
        );
    });
}

function initBulkGradeTools() {
    $('#bulkGradeStudents').select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: 'Selecciona alumnos',
        closeOnSelect: false,
        allowClear: true
    });

    $('#bulkGradeCriteria').select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: 'Selecciona criterios',
        closeOnSelect: false,
        allowClear: true
    });

    renderBulkGradeOptions();

    $('#bulkGradeAllStudents, #bulkGradeAllCriteria').on('change', updateBulkGradeSelectors);
    $('#btnApplyBulkGrade').on('click', applyBulkGrade);
    
    updateBulkGradeSelectors();
}

function updateBulkGradeSelectors() {
    const allStudents = $('#bulkGradeAllStudents').is(':checked');
    const allCriteria = $('#bulkGradeAllCriteria').is(':checked');

    $('#bulkGradeStudents').prop('disabled', allStudents);
    $('#bulkGradeCriteria').prop('disabled', allCriteria);

    if (allStudents) $('#bulkGradeStudents').val(null).trigger('change');
    if (allCriteria) $('#bulkGradeCriteria').val(null).trigger('change');

    $('#bulkGradeStudents').next('.select2-container').toggleClass('bulk-select-disabled', allStudents);
    $('#bulkGradeCriteria').next('.select2-container').toggleClass('bulk-select-disabled', allCriteria);
}

function renderBulkGradeOptions() {
    const group = document.getElementById('evaluationGroup').value;
    const students = state.students.filter((student) => student.group === group);

    $('#bulkGradeStudents').html(
        students.map((student) => `
            <option value="${escapeHtml(student.enrollment)}">
                ${escapeHtml(student.name)} · ${escapeHtml(student.enrollment)}
            </option>
        `).join('')
    ).trigger('change.select2');

    $('#bulkGradeCriteria').html(
        state.criteria.map((criterion) => `
            <option value="${criterion.id}">
                ${escapeHtml(criterion.name)}
            </option>
        `).join('')
    ).trigger('change.select2');
}

function applyBulkGrade() {
    const group = $('#evaluationGroup').val();
    const allStudents = $('#bulkGradeAllStudents').is(':checked');
    const allCriteria = $('#bulkGradeAllCriteria').is(':checked');
    const onlyEmpty = $('#bulkGradeOnlyEmpty').is(':checked');

    const selectedStudents = allStudents
        ? state.students.filter((student) => student.group === group).map((student) => student.enrollment)
        : ($('#bulkGradeStudents').val() || []);

    const selectedCriteria = allCriteria
        ? state.criteria.map((criterion) => criterion.id)
        : ($('#bulkGradeCriteria').val() || []).map(Number);

    let value = $('#bulkGradeValue').val().trim();

    if (!selectedStudents.length) {
        Swal.fire('', 'Selecciona uno o varios alumnos, o activa “Todos”.', 'info');
        return;
    }

    if (!selectedCriteria.length) {
        Swal.fire('', 'Selecciona uno o varios criterios, o activa “Todos”.', 'info');
        return;
    }

    if (value === '') {
        Swal.fire('', 'Captura una calificación de 0 a 10.', 'info');
        return;
    }

    value = Number(value);

    if (Number.isNaN(value) || value < 0 || value > 10) {
        Swal.fire('', 'La calificación debe estar entre 0 y 10.', 'warning');
        return;
    }

    const calificaciones = [];

    selectedStudents.forEach((enrollment) => {
        const student = state.students.find((item) => item.enrollment === enrollment);

        if (!student) return;

        state.grades[enrollment] ??= {};

        selectedCriteria.forEach((criterionId) => {
            const currentValue = state.grades[enrollment][criterionId];

            if (
                onlyEmpty &&
                currentValue !== null &&
                currentValue !== undefined &&
                currentValue !== ''
            ) {
                return;
            }

            state.grades[enrollment][criterionId] = value;

registrarCambioCalificacion(
    student.id,
    enrollment,
    criterionId,
    value
);

calificaciones.push({
    alumno_id: student.id,
    criterio_id: criterionId,
    calificacion: value
});
        });
    });

    if (!calificaciones.length) {
        Swal.fire('', 'No había calificaciones pendientes por actualizar.', 'info');
        return;
    }

    syncCurrentEvaluationGrades();
    renderGrades();

    const alumnosTexto = allStudents
        ? 'todos los alumnos'
        : `${selectedStudents.length} alumno${selectedStudents.length === 1 ? '' : 's'}`;

    const criteriosTexto = allCriteria
        ? 'todos los criterios'
        : `${selectedCriteria.length} criterio${selectedCriteria.length === 1 ? '' : 's'}`;

    marcarCambiosSinGuardar();

showToast(
    `Calificación ${value} aplicada a ${alumnosTexto} en ${criteriosTexto}. Presiona Guardar calificaciones para confirmar.`
);

    $('#bulkGradeValue').val('');
}

function bindEvaluationViews() {
    document.querySelectorAll('[data-evaluation-view]').forEach((button) => {
        button.addEventListener('click', function() {
            const view = button.dataset.evaluationView;

            document.querySelectorAll('[data-evaluation-view]').forEach((item) => {
                item.classList.remove('active');
            });

            button.classList.add('active');

            $('#evaluationDashboardView').toggleClass('d-none', view !== 'dashboard');
            $('#evaluationCaptureView').toggleClass('d-none', view !== 'capture');
            $('#evaluationSummaryView').toggleClass('d-none', view !== 'summary');

            syncCurrentEvaluationGrades();

            if (view === 'dashboard') renderEvaluationDashboard();
            if (view === 'summary') {
                cargarTodosLosPeriodos(function() {
                    renderEvaluationSummary();
                });
            }
        });
    });
}

function getEvaluationContextKey(period = null) {
    const subject = document.getElementById('evaluationSubject').value || '0';
    const currentPeriod = period || document.getElementById('evaluationPeriod').value || '';
    const cycle = document.getElementById('evaluationCycle').value || '';

    return `${subject}|${currentPeriod}|${cycle}`;
}

function cloneGrades(grades) {
    return JSON.parse(JSON.stringify(grades || {}));
}

function initializeEvaluationGrades() {
    const key = getEvaluationContextKey();

    state.activeEvaluationKey = key;
    state.periodGrades[key] = cloneGrades(state.grades);
}

function syncCurrentEvaluationGrades() {
    if (!state.activeEvaluationKey) state.activeEvaluationKey = getEvaluationContextKey();
    state.periodGrades[state.activeEvaluationKey] = cloneGrades(state.grades);
}

function createEmptyEvaluationGrades() {
    const group = document.getElementById('evaluationGroup').value;
    const grades = {};

    state.students
        .filter((student) => student.group === group)
        .forEach((student) => {
            grades[student.enrollment] = {};

            state.criteria.forEach((criterion) => {
                grades[student.enrollment][criterion.id] = null;
            });
        });

    return grades;
}

function changeEvaluationContext() {
    syncCurrentEvaluationGrades();

    const newKey = getEvaluationContextKey();

    state.grades = state.periodGrades[newKey]
        ? cloneGrades(state.periodGrades[newKey])
        : createEmptyEvaluationGrades();

    state.activeEvaluationKey = newKey;

    renderGrades();
}

function renderEvaluationSummary() {
    syncCurrentEvaluationGrades();

    const periods = state.evaluationPeriods;

    $("#summaryCycleBadge").text(
        $("#evaluationCycle").val() || "Ciclo escolar"
    );

    $("#periodSummaryCards").html(
        periods.map(function (period, index) {

            var average = calculatePeriodGroupAverage(period);

            return `
                <div class="col-12 col-md-4">
                    <div class="period-summary-card">

                        <div class="d-flex align-items-start justify-content-between gap-3">

                            <div class="d-flex align-items-center gap-3">

                                <span class="period-summary-number">
                                    ${index + 1}
                                </span>

                                <div>
                                    <div class="fw-bold">
                                        ${escapeHtml(period)}
                                    </div>

                                    <div class="text-muted small">
                                        Promedio del grupo
                                    </div>
                                </div>

                            </div>

                            <div class="period-summary-average">
                                ${average === null ? "—" : average.toFixed(1)}
                            </div>

                        </div>

                    </div>
                </div>
            `;
        }).join("")
    );

    renderFinalResults();
}

function calculatePeriodStudentAverage(enrollment, period) {

    const key = getEvaluationContextKey(period);
    const gradesPeriod = state.periodGrades[key];

    if (!gradesPeriod || !gradesPeriod[enrollment]) {
        return null;
    }

    var grades = gradesPeriod[enrollment];

    var weighted = 0;
    var appliedWeight = 0;

    state.criteria.forEach(function (criterion) {

        if (Number(criterion.afecta_promedio) !== 1) {
            return;
        }

        var grade = grades[criterion.id];

        if (
            grade === null ||
            grade === undefined ||
            grade === ""
        ) {
            return;
        }

        var gradeCalculo = Number(grade);

if (Number(criterion.redondear_resultado) === 1) {
    gradeCalculo = Math.round(gradeCalculo);
}

weighted += gradeCalculo * Number(criterion.weight);;

        appliedWeight +=
            Number(criterion.weight);
    });

    if (!appliedWeight) {
        return null;
    }

    return weighted / appliedWeight;
}

function calculatePeriodGroupAverage(period) {

    var averages = state.students
        .map(function (student) {
            return calculatePeriodStudentAverage(
                student.enrollment,
                period
            );
        })
        .filter(function (value) {
            return value !== null;
        });

    if (!averages.length) {
        return null;
    }

    return averages.reduce(function (sum, value) {
        return sum + value;
    }, 0) / averages.length;
}

function renderFinalResults() {

    var periods = state.evaluationPeriods;
    var group = $("#evaluationGroup").val();

    var students = state.students.filter(function (student) {
        return student.group === group;
    });

    $("#finalResultsHead").html(`
        <th>Alumno</th>

        ${periods.map(function (period) {
            return `
                <th class="text-center">
                    ${escapeHtml(period)}
                </th>
            `;
        }).join("")}

        <th class="text-center">
            Promedio final
        </th>
    `);

    var finalGroupValues = [];

    $("#finalResultsBody").html(
        students.map(function (student) {

            var periodValues = periods.map(function (period) {
                return calculatePeriodStudentAverage(
                    student.enrollment,
                    period
                );
            });

            var completedValues = periodValues.filter(function (value) {
                return value !== null;
            });

            /*
             * Solo hay promedio final cuando todos
             * los periodos están capturados.
             */
            var finalAverage = null;

            if (
                completedValues.length === periods.length
            ) {
                finalAverage =
                    completedValues.reduce(function (sum, value) {
                        return sum + value;
                    }, 0) / periods.length;

                finalGroupValues.push(finalAverage);
            }

            return `
                <tr>

                    <td>
                        <div class="d-flex align-items-center gap-3">

                            <span class="avatar">
                                ${initials(student.name)}
                            </span>

                            <div>
                                <div class="fw-bold">
                                    ${escapeHtml(student.name)}
                                </div>

                                <div class="text-muted small">
                                    ${escapeHtml(student.enrollment)}
                                </div>
                            </div>

                        </div>
                    </td>

                    ${periodValues.map(function (value) {
                        return `
                            <td class="text-center">
                                <span class="final-period-value">
                                    ${value === null ? "—" : value.toFixed(1)}
                                </span>
                            </td>
                        `;
                    }).join("")}

                    <td class="text-center">
                        <span class="grade-average ${
                            finalAverage === null
                                ? "bg-light text-muted"
                                : averageClass(finalAverage)
                        }">
                            ${
                                finalAverage === null
                                    ? "Pendiente"
                                    : finalAverage.toFixed(1)
                            }
                        </span>
                    </td>

                </tr>
            `;
        }).join("")
    );

    var finalGroupAverage = null;

    if (finalGroupValues.length) {
        finalGroupAverage =
            finalGroupValues.reduce(function (sum, value) {
                return sum + value;
            }, 0) / finalGroupValues.length;
    }

    $("#finalGroupAverage").text(
        finalGroupAverage === null
            ? "—"
            : finalGroupAverage.toFixed(1)
    );
}

let evaluationPeriodChart = null;
let evaluationDistributionChart = null;
let evaluationCriteriaChart = null;

function renderEvaluationDashboard() {
    syncCurrentEvaluationGrades();

    const group = $('#evaluationGroup').val();
    const period = $('#evaluationPeriod').val();
    const subjectId = Number($('#evaluationSubject').val());

    const subject = state.subjects.find((item) => item.id === subjectId);
    const students = state.students.filter((student) => student.group === group);

    $('#evaluationDashboardSubject').text(subject ? subject.name : 'Materia');
    $('#evaluationDashboardGroup').text(group || 'Grupo');
    $('#evaluationDashboardPeriod').text(period || 'Periodo');

    const results = students.map((student) => {
        return {
            student: student,
            average: calculatePeriodStudentAverage(student.enrollment, period)
        };
    });

    const evaluated = results.filter((item) => item.average !== null);

    const approved = evaluated.filter((item) => item.average >= 6);
    const failed = evaluated.filter((item) => item.average < 6);
    const risk = evaluated.filter((item) => item.average < 7);

    const average = evaluated.length
        ? evaluated.reduce((sum, item) => sum + item.average, 0) / evaluated.length
        : null;

    const best = evaluated.slice().sort((a, b) => b.average - a.average)[0];

    $('#evalDashboardAverage').text(average === null ? '—' : average.toFixed(1));
    $('#evalDashboardApproved').text(approved.length);
    $('#evalDashboardFailed').text(failed.length);
    $('#evalDashboardRisk').text(risk.length);

    $('#evalDashboardApprovedText').text(
        evaluated.length
            ? ((approved.length / evaluated.length) * 100).toFixed(1) + '% de alumnos evaluados'
            : 'Sin alumnos evaluados'
    );

    $('#evalDashboardFailedText').text(
        evaluated.length
            ? ((failed.length / evaluated.length) * 100).toFixed(1) + '% de alumnos evaluados'
            : 'Sin alumnos evaluados'
    );

    $('#evalDashboardBest').text(best ? best.average.toFixed(1) : '—');
    $('#evalDashboardBestStudent').text(best ? best.student.name : 'Sin resultado');

    const completion = calculateEvaluationCompletion(period, students);

    $('#evalDashboardCompletion').text(completion.toFixed(0) + '%');
    $('#evalDashboardCompletionBar').css('width', completion + '%');

    renderEvaluationPeriodChart();
    renderEvaluationDistributionChart(evaluated);
    renderEvaluationCriteriaChart(period, students);
    renderEvaluationAttention(evaluated);
    renderEvaluationTopStudents(evaluated);
}

function calculateEvaluationCompletion(period, students) {
    if (!students.length || !state.criteria.length) return 0;

    const key = getEvaluationContextKey(period);
    const gradesPeriod = state.periodGrades[key] || {};

    let captured = 0;
    const total = students.length * state.criteria.length;

    students.forEach((student) => {
        const grades = gradesPeriod[student.enrollment] || {};

        state.criteria.forEach((criterion) => {
            const value = grades[criterion.id];

            if (value !== null && value !== undefined && value !== '') captured++;
        });
    });

    return total ? (captured / total) * 100 : 0;
}

function renderEvaluationPeriodChart() {
    const canvas = document.getElementById('evaluationPeriodChart');
    if (!canvas || typeof Chart === 'undefined') return;

    const values = state.evaluationPeriods.map((period) => {
        const average = calculatePeriodGroupAverage(period);
        return average === null ? 0 : Number(average.toFixed(2));
    });

    if (evaluationPeriodChart) evaluationPeriodChart.destroy();

    evaluationPeriodChart = new Chart(canvas, {
        type: 'bar',
        data: {
            labels: state.evaluationPeriods,
            datasets: [{
                data: values,
                backgroundColor: '#B8943C',
                borderRadius: 10,
                maxBarThickness: 70
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 10,
                    ticks: {
                        stepSize: 2
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
}

function renderEvaluationDistributionChart(evaluated) {
    const canvas = document.getElementById('evaluationDistributionChart');
    if (!canvas || typeof Chart === 'undefined') return;

    let excellent = 0;
    let good = 0;
    let sufficient = 0;
    let failed = 0;

    evaluated.forEach((item) => {
        if (item.average >= 9) excellent++;
        else if (item.average >= 8) good++;
        else if (item.average >= 6) sufficient++;
        else failed++;
    });

    if (evaluationDistributionChart) evaluationDistributionChart.destroy();

    evaluationDistributionChart = new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: [
                'Excelente',
                'Buen desempeño',
                'Suficiente',
                'Reprobado'
            ],
            datasets: [{
                data: [
                    excellent,
                    good,
                    sufficient,
                    failed
                ],
                backgroundColor: [
                    '#1F7A3A',
                    '#B8943C',
                    '#D39A36',
                    '#A12620'
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        boxWidth: 10,
                        padding: 16
                    }
                }
            }
        }
    });
}

function renderEvaluationCriteriaChart(period, students) {
    const canvas = document.getElementById('evaluationCriteriaChart');
    if (!canvas || typeof Chart === 'undefined') return;

    const key = getEvaluationContextKey(period);
    const gradesPeriod = state.periodGrades[key] || {};

    const values = state.criteria.map((criterion) => {
        const grades = [];

        students.forEach((student) => {
            const studentGrades = gradesPeriod[student.enrollment] || {};
            const value = studentGrades[criterion.id];

            if (value !== null && value !== undefined && value !== '') {
                grades.push(Number(value));
            }
        });

        if (!grades.length) return 0;

        return grades.reduce((sum, value) => sum + value, 0) / grades.length;
    });

    if (evaluationCriteriaChart) evaluationCriteriaChart.destroy();

    evaluationCriteriaChart = new Chart(canvas, {
        type: 'bar',
        data: {
            labels: state.criteria.map((criterion) => criterion.name),
            datasets: [{
                data: values,
                backgroundColor: '#1D1D1F',
                borderRadius: 8,
                maxBarThickness: 45
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    max: 10,
                    ticks: {
                        stepSize: 2
                    }
                },
                y: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
}

function renderEvaluationAttention(evaluated) {
    const students = evaluated
        .filter((item) => item.average < 7)
        .sort((a, b) => a.average - b.average);

    if (!students.length) {
        $('#evaluationAttentionBody').html(`
            <tr>
                <td colspan="3" class="text-center py-5 text-muted">
                    <i class="bi bi-check2-circle text-success fs-3 d-block mb-2"></i>
                    No hay alumnos con promedio menor a 7.
                </td>
            </tr>
        `);

        return;
    }

    $('#evaluationAttentionBody').html(
        students.map((item) => {
            const situation = item.average < 6
                ? '<span class="badge rounded-pill text-bg-danger">Reprobado</span>'
                : '<span class="badge rounded-pill bg-warning-subtle text-warning-emphasis">En riesgo</span>';

            return `
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-3">
                            <span class="avatar">${initials(item.student.name)}</span>

                            <div>
                                <div class="fw-bold">${escapeHtml(item.student.name)}</div>
                                <div class="small text-muted">${escapeHtml(item.student.enrollment)}</div>
                            </div>
                        </div>
                    </td>

                    <td class="text-center fw-bold">
                        ${item.average.toFixed(1)}
                    </td>

                    <td class="text-center">
                        ${situation}
                    </td>
                </tr>
            `;
        }).join('')
    );
}

function renderEvaluationTopStudents(evaluated) {
    const students = evaluated
        .slice()
        .sort((a, b) => b.average - a.average)
        .slice(0, 5);

    if (!students.length) {
        $('#evaluationTopStudents').html(`
            <div class="text-center text-muted small py-4">
                No hay alumnos evaluados.
            </div>
        `);

        return;
    }

    $('#evaluationTopStudents').html(
        students.map((item, index) => {
            return `
                <div class="evaluation-ranking-item">

                    <span class="evaluation-ranking-number">
                        ${index + 1}
                    </span>

                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold text-truncate">
                            ${escapeHtml(item.student.name)}
                        </div>

                        <div class="small text-muted">
                            ${escapeHtml(item.student.enrollment)}
                        </div>
                    </div>

                    <span class="evaluation-ranking-average">
                        ${item.average.toFixed(1)}
                    </span>

                </div>
            `;
        }).join('')
    );
}

function bindBulkUpload() {
    const input = document.getElementById('bulkFileInput');
    const dropzone = document.getElementById('uploadDropzone');

    document.getElementById('btnChooseFile').addEventListener('click', (event) => {
        event.stopPropagation();
        input.click();
    });

    dropzone.addEventListener('click', () => input.click());
    dropzone.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') input.click();
    });

    ['dragenter', 'dragover'].forEach((name) => {
        dropzone.addEventListener(name, (event) => {
            event.preventDefault();
            dropzone.classList.add('dragover');
        });
    });

    ['dragleave', 'drop'].forEach((name) => {
        dropzone.addEventListener(name, (event) => {
            event.preventDefault();
            dropzone.classList.remove('dragover');
        });
    });

    dropzone.addEventListener('drop', (event) => {
        const [file] = event.dataTransfer.files;
        if (file) processGradeFile(file);
    });

    input.addEventListener('change', () => {
        const [file] = input.files;
        if (file) processGradeFile(file);
    });

    document.getElementById('btnImportGrades').addEventListener('click', importGrades);

    document.getElementById('bulkUploadModal').addEventListener('hidden.bs.modal', resetBulkUpload);
}

/*
function bindPendingEvaluation() {
    document
        .getElementById('btnContinuePendingEvaluation')
        .addEventListener('click', () => {
            pendingEvaluationModal.hide();
            navigateTo('evaluations');
        });
}

function showPendingEvaluationReminder() {
    const pending = state.pendingEvaluation;

    if (!pending || state.activeView !== 'home') {
        return;
    }

    const storageKey = `pending-evaluation-${pending.id}`;

    if (sessionStorage.getItem(storageKey) === 'shown') {
        return;
    }

    sessionStorage.setItem(storageKey, 'shown');
    pendingEvaluationModal.show();
}*/

function renderPendingEvaluation() {
    const button = document.getElementById(
        'pendingEvaluationButton'
    );

    const pending = state.pendingEvaluation;

    if (!pending) {
        button.classList.add('d-none');
        button.classList.remove('d-flex');
        return;
    }

    setText(
        'pendingEvaluationName',
        `${pending.subject} · ${pending.group}`
    );

    setText(
        'pendingEvaluationPeriod',
        pending.period
    );

    const showButton = state.activeView === 'home';

    button.classList.toggle('d-none', !showButton);
    button.classList.toggle('d-flex', showButton);
}

function navigateTo(view, scroll = true) {
    state.activeView = view;

    const isHome = view === 'home';

    document
        .getElementById('appHeader')
        .classList.toggle('is-home', isHome);

    document.querySelectorAll('.app-view').forEach((section) => {
        section.classList.toggle(
            'active',
            section.dataset.view === view
        );
    });

    document.querySelectorAll('.btn-nav-module').forEach((button) => {
        button.classList.toggle(
            'active',
            button.dataset.module === view
        );
    });

    renderPendingEvaluation();

    if (scroll) {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    }
}

function renderAll() {
    renderDashboard();
    renderEvaluationSubjectOptions();
    renderSubjects();
    renderTeachers();
    renderStudents();
    renderSchedule();
    renderCriteria();
    renderGrades();
}

function renderDashboard() {
    setText('dashboardSubjects', `${state.subjects.length} ${plural(state.subjects.length, 'materia registrada', 'materias registradas')}`);
    setText('dashboardTeachers', `${state.teachers.length} ${plural(state.teachers.length, 'maestro registrado', 'maestros registrados')}`);
    setText('dashboardStudents', `${state.students.length} ${plural(state.students.length, 'alumno registrado', 'alumnos registrados')}`);
    setText('dashboardSchedule', `${state.schedule.length} ${plural(state.schedule.length, 'clase esta semana', 'clases esta semana')}`);
}

function renderSubjects() {
    const search = normalize($("#subjectSearch").val());

    const rows = state.subjects.filter(function(subject) {
        return normalize(
            `${subject.name || ""} ${subject.nombre || ""} ${subject.clave || ""} ${subject.descripcion || ""}`
        ).includes(search);
    });

    $("#subjectCount").text(`${rows.length} de ${state.subjects.length} materias`);

    if (!rows.length) {
        $("#subjectTableBody").html(emptyTableRow(5, "No se encontraron materias."));
        return;
    }

    $("#subjectTableBody").html(rows.map(function(subject) {
        var activo = Number(subject.estado) === 1;
        var criterios = Array.isArray(subject.criteria) ? subject.criteria : [];

        return `
            <tr>
                <td>
                    <div class="d-flex align-items-center gap-3">
                        <span class="subject-badge-icon"><i class="bi bi-book"></i></span>
                        <div>
                            <div class="fw-bold">${escapeHtml(subject.nombre || subject.name)}</div>
                            ${subject.descripcion ? `<div class="small text-muted">${escapeHtml(subject.descripcion)}</div>` : ""}
                        </div>
                    </div>
                </td>

                <td>
                    <span class="fw-semibold">${escapeHtml(subject.clave || subject.code || "")}</span>
                </td>

                <td>
                    <span class="badge rounded-pill bg-light text-dark border">
                        ${criterios.length} ${plural(criterios.length, "criterio", "criterios")}
                    </span>
                </td>

                <td>
                    <span class="badge rounded-pill ${activo ? "bg-success-subtle text-success" : "bg-secondary-subtle text-secondary"}">
                        ${activo ? "Activa" : "Inactiva"}
                    </span>
                </td>

                <td class="text-end">
                    ${actionButtons("subject", subject.id)}
                </td>
            </tr>
        `;
    }).join(""));
}

function renderTeachers() {
    const search = normalize($("#teacherSearch").val());

    const rows = state.teachers.filter(function(teacher) {
        return normalize(
            `${teacher.name} ${teacher.code} ${teacher.email || ""} ${teacher.curp || ""} ${teacher.telefono || ""} ${teacher.especialidad || ""} ${teacher.usuario || ""}`
        ).includes(search);
    });

    $("#teacherCount").text(`${rows.length} de ${state.teachers.length} maestros`);

    if (!rows.length) {
        $("#teacherTableBody").html(emptyTableRow(5, "No se encontraron maestros."));
        return;
    }

    $("#teacherTableBody").html(rows.map(function(teacher) {
        /*const subjectCount = state.subjects.filter(function(subject) {
            return subject.teacherId === teacher.id;
        }).length;*/

        return `
            <tr>
                <td>
                    <div class="d-flex align-items-center gap-3">
                        <span class="avatar">${initials(teacher.name)}</span>
                        <div>
                            <div class="fw-bold">${escapeHtml(teacher.name)}</div>
                            ${teacher.especialidad ? `<div class="small text-muted">${escapeHtml(teacher.especialidad)}</div>` : ""}
                        </div>
                    </div>
                </td>
                <td>${escapeHtml(teacher.code)}</td>
                <td>${teacher.email ? `<a class="link-dark" href="mailto:${escapeHtml(teacher.email)}">${escapeHtml(teacher.email)}</a>` : '<span class="text-muted">—</span>'}</td>
                <!--<td>${subjectCount} ${plural(subjectCount, 'materia', 'materias')}</td>-->
                <td><span class="text-muted">—</span></td>
                <td class="text-end">${actionButtons('teacher', teacher.id)}</td>
            </tr>`;
    }).join(""));
}

function getAsignaturas(mostrarCarga = true) {
    var formData = new FormData();
    formData.append("action", "getAsignaturas");

    $.ajax({
        type: "POST",
        url: "php/index.php",
        data: formData,
        dataType: "json",
        processData: false,
        contentType: false,
        beforeSend: function() {
            if (!mostrarCarga) return;

            $("#subjectTableBody").html(`
                <tr>
                    <td colspan="5" class="text-center py-5">
                        <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                        <span class="text-muted">Cargando materias...</span>
                    </td>
                </tr>
            `);
        }
    }).done(function(data) {
        if (!data || data.status !== "ok") {
            Swal.fire({
                icon: "error",
                title: "No fue posible cargar las materias",
                text: data && data.answer ? data.answer : "Ocurrió un problema al consultar las materias.",
                confirmButtonColor: "#1D1D1F"
            });
            return;
        }

        state.subjects = Array.isArray(data.results) ? data.results : [];

        renderSubjects();
        renderDashboard();
        renderEvaluationSubjectOptions();

        cargarCriteriosAsignaturaSeleccionada();
        getCalificaciones();
    }).fail(function(xhr) {
        console.error(xhr.responseText);

        if (mostrarCarga) {
            $("#subjectTableBody").html(`
                <tr>
                    <td colspan="5" class="text-center text-danger py-5">
                        <i class="bi bi-exclamation-circle fs-3 d-block mb-2"></i>
                        No fue posible cargar las materias.
                    </td>
                </tr>
            `);
        }

        Swal.fire({
            icon: "error",
            title: "Error de conexión",
            text: "No fue posible comunicarse con el servidor.",
            confirmButtonColor: "#1D1D1F"
        });
    });
}

function getCalificaciones(periodo = null, mostrarCarga = true, actualizarActual = true) {
    var asignaturaId = Number($("#evaluationSubject").val() || 0);
    var periodoConsulta = periodo || String($("#evaluationPeriod").val() || "").trim();
    var ciclo = String($("#evaluationCycle").val() || "").trim();

    if (!asignaturaId || !periodoConsulta || !ciclo) {
        state.grades = {};
        renderGrades();
        return $.Deferred().resolve().promise();
    }

    var formData = new FormData();
    formData.append("action", "getCalificaciones");
    formData.append("asignatura_id", asignaturaId);
    formData.append("periodo", periodoConsulta);
    formData.append("ciclo_escolar", ciclo);

    return $.ajax({
        type: "POST",
        url: "php/index.php",
        data: formData,
        dataType: "json",
        processData: false,
        contentType: false,
        beforeSend: function() {
            if (!mostrarCarga || periodoConsulta !== $("#evaluationPeriod").val()) return;

            $("#gradeTableBody").html(`
                <tr>
                    <td colspan="${state.criteria.length + 2}" class="text-center py-5">
                        <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                        <span class="text-muted">Cargando calificaciones...</span>
                    </td>
                </tr>
            `);
        }
    }).done(function(data) {
        if (!data || data.status !== "ok") {
            if (mostrarCarga) {
                Swal.fire({
                    icon: data && data.status === "warning" ? "warning" : "error",
                    title: "No fue posible cargar las calificaciones",
                    text: data && data.answer ? data.answer : "Ocurrió un problema al consultar las calificaciones.",
                    confirmButtonColor: "#1D1D1F"
                });
            }

            return;
        }

        var grades = data.grades && !Array.isArray(data.grades)
            ? data.grades
            : {};

        var key = getEvaluationContextKey(periodoConsulta);

        state.periodGrades[key] = cloneGrades(grades);

        if (
            actualizarActual &&
            periodoConsulta === String($("#evaluationPeriod").val() || "").trim()
        ) {
            state.grades = cloneGrades(grades);
            state.activeEvaluationKey = key;

            renderGrades();
        }
    }).fail(function(xhr) {
        console.error(xhr.responseText);

        if (!mostrarCarga) return;

        Swal.fire({
            icon: "error",
            title: "Error de conexión",
            text: "No fue posible consultar las calificaciones.",
            confirmButtonColor: "#1D1D1F"
        });
    });
}

function getMaestros(mostrarCarga = true) {
    var formData = new FormData();
    formData.append("action", "getMaestros");

    $.ajax({
        type: "POST",
        url: "php/index.php",
        data: formData,
        dataType: "json",
        processData: false,
        contentType: false,
        beforeSend: function() {
            if (!mostrarCarga) return;

            $("#teacherTableBody").html(`
                <tr>
                    <td colspan="5" class="text-center py-5">
                        <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                        <span class="text-muted">Cargando maestros...</span>
                    </td>
                </tr>
            `);
        }
    }).done(function(data) {
        if (!data || data.status !== "ok") {
            Swal.fire({
                icon: "error",
                title: "No fue posible cargar los maestros",
                text: data && data.answer ? data.answer : "Ocurrió un problema al consultar los maestros.",
                confirmButtonColor: "#1D1D1F"
            });
            return;
        }

        state.teachers = Array.isArray(data.results) ? data.results : [];

        renderTeachers();
        renderDashboard();
        renderSubjects();
    }).fail(function(xhr) {
        console.error(xhr.responseText);

        if (mostrarCarga) {
            $("#teacherTableBody").html(`
                <tr>
                    <td colspan="5" class="text-center text-danger py-5">
                        <i class="bi bi-exclamation-circle fs-3 d-block mb-2"></i>
                        No fue posible cargar los maestros.
                    </td>
                </tr>
            `);
        }

        Swal.fire({
            icon: "error",
            title: "Error de conexión",
            text: "No fue posible comunicarse con el servidor.",
            confirmButtonColor: "#1D1D1F"
        });
    });
}

function getHorarios(mostrarCarga = true) {
    var formData = new FormData();
    formData.append("action", "getHorarios");

    $.ajax({
        type: "POST",
        url: "php/index.php",
        data: formData,
        dataType: "json",
        processData: false,
        contentType: false
    }).done(function(data) {
        if (!data || data.status !== "ok") {
            Swal.fire({
                icon: "error",
                title: "No fue posible cargar los horarios",
                text: data && data.answer ? data.answer : "Ocurrió un problema al consultar los horarios.",
                confirmButtonColor: "#1D1D1F"
            });
            return;
        }

        state.schedule = Array.isArray(data.results)
            ? data.results
            : [];

        state.assignments = Array.isArray(data.assignments)
            ? data.assignments
            : [];

        var actual = $("#scheduleGroupFilter").val() || "all";

        var grupos = [...new Set(
            state.assignments.map(function(item) {
                return item.group;
            }).filter(Boolean)
        )].sort(function(a, b) {
            return a.localeCompare(b, "es", { numeric: true });
        });

        $("#scheduleGroupFilter").html(
            '<option value="all">Todos los grupos</option>' +
            grupos.map(function(grupo) {
                return `<option value="${escapeHtml(grupo)}">${escapeHtml(grupo)}</option>`;
            }).join("")
        );

        if (actual !== "all" && grupos.includes(actual)) {
            $("#scheduleGroupFilter").val(actual);
        }

        renderSchedule();
        renderDashboard();
        renderEvaluationGroupOptions();
        renderEvaluationSubjectOptions();
        cargarCriteriosAsignaturaSeleccionada();
        getCalificaciones(null, false);
    }).fail(function(xhr) {
        console.error(xhr.responseText);

        if (mostrarCarga) {
            Swal.fire({
                icon: "error",
                title: "Error de conexión",
                text: "No fue posible consultar los horarios.",
                confirmButtonColor: "#1D1D1F"
            });
        }
    });
}

function guardarHorario(form, id) {
    var formData = new FormData(form);
    var grupoAcademico = String(formData.get("grupo_academico") || "");

    if (grupoAcademico) {
        var partes = grupoAcademico.split("|");

        formData.set("grado", partes[0] || "");
        formData.set("grupo", partes[1] || "");
        formData.delete("grupo_academico");
    }

    formData.append("action", "guardarHorario");
    formData.append("id", id || 0);

    var boton = $(form).find('button[type="submit"]');
    var contenidoAnterior = boton.html();

    boton.prop("disabled", true).html(`
        <span class="spinner-border spinner-border-sm me-2"></span>
        Guardando...
    `);

    $.ajax({
        type: "POST",
        url: "php/index.php",
        data: formData,
        dataType: "json",
        processData: false,
        contentType: false
    }).done(function(data) {
        boton.prop("disabled", false).html(contenidoAnterior);

        if (!data || data.status !== "ok") {
            Swal.fire({
                icon: data && data.status === "warning" ? "warning" : "error",
                title: data && data.status === "warning"
                    ? "Revisa la asignación"
                    : "No fue posible guardar",
                text: data && data.answer
                    ? data.answer
                    : "No fue posible guardar el horario.",
                confirmButtonColor: "#1D1D1F"
            });

            return;
        }

        entityModal.hide();
        getHorarios(false);
        showToast(data.answer || "Horario guardado correctamente.");
    }).fail(function(xhr) {
        boton.prop("disabled", false).html(contenidoAnterior);

        console.error(xhr.responseText);

        Swal.fire({
            icon: "error",
            title: "Error de conexión",
            text: "No fue posible guardar el horario.",
            confirmButtonColor: "#1D1D1F"
        });
    });
}

function eliminarHorario(id) {
    var formData = new FormData();

    formData.append("action", "eliminarHorario");
    formData.append("id", id);

    $.ajax({
        type: "POST",
        url: "php/index.php",
        data: formData,
        dataType: "json",
        processData: false,
        contentType: false
    }).done(function(data) {
        if (!data || data.status !== "ok") {
            Swal.fire({
                icon: "error",
                title: "No fue posible eliminar",
                text: data && data.answer
                    ? data.answer
                    : "No fue posible eliminar el horario.",
                confirmButtonColor: "#1D1D1F"
            });

            return;
        }

        getHorarios(false);
        showToast(data.answer || "Horario eliminado correctamente.");
    }).fail(function(xhr) {
        console.error(xhr.responseText);
    });
}

function getAlumnos(mostrarCarga = true) {
    var formData = new FormData();
    formData.append("action", "getAlumnos");

    $.ajax({
        type: "POST",
        url: "php/index.php",
        data: formData,
        dataType: "json",
        processData: false,
        contentType: false,
        beforeSend: function() {
            if (!mostrarCarga) return;

            $("#studentTableBody").html(`
                <tr>
                    <td colspan="5" class="text-center py-5">
                        <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                        <span class="text-muted">Cargando alumnos...</span>
                    </td>
                </tr>
            `);
        }
    }).done(function(data) {
        if (!data || data.status !== "ok") {
            Swal.fire({
                icon: "error",
                title: "No fue posible cargar los alumnos",
                text: data && data.answer ? data.answer : "Ocurrió un problema al consultar los alumnos.",
                confirmButtonColor: "#1D1D1F"
            });

            return;
        }

        state.students = Array.isArray(data.results) ? data.results : [];
        
        renderStudents();
        renderDashboard();
        renderEvaluationGroupOptions();
        renderGrades();

    }).fail(function(xhr) {
        console.error(xhr.responseText);

        if (mostrarCarga) {
            $("#studentTableBody").html(`
                <tr>
                    <td colspan="5" class="text-center text-danger py-5">
                        <i class="bi bi-exclamation-circle fs-3 d-block mb-2"></i>
                        No fue posible cargar los alumnos.
                    </td>
                </tr>
            `);
        }

        Swal.fire({
            icon: "error",
            title: "Error de conexión",
            text: "No fue posible comunicarse con el servidor.",
            confirmButtonColor: "#1D1D1F"
        });
    });
}

function activarOrdenAlumnos() {
    Sortable.create(document.getElementById("studentTableBody"), {
        handle: ".student-drag-handle",
        animation: 180,
        ghostClass: "student-sort-ghost",

        onMove: function(evt) {
            if (!evt.related) return true;
            return evt.dragged.dataset.group === evt.related.dataset.group;
        },

        onEnd: function(evt) {
            if (evt.oldIndex === evt.newIndex) return;

            var grupo = evt.item.dataset.group;

            var ids = Array.from(
                document.querySelectorAll("#studentTableBody tr[data-student-id]")
            ).filter(function(row) {
                return row.dataset.group === grupo;
            }).map(function(row) {
                return Number(row.dataset.studentId);
            });

            var formData = new FormData();
            formData.append("action", "guardarOrdenAlumnos");
            formData.append("ids", JSON.stringify(ids));

            $.ajax({
                type: "POST",
                url: "php/index.php",
                data: formData,
                dataType: "json",
                processData: false,
                contentType: false
            }).done(function(data) {
                if (!data || data.status !== "ok") {
                    getAlumnos(false);

                    Swal.fire({
                        icon: "error",
                        title: "No fue posible cambiar el orden",
                        text: data && data.answer ? data.answer : "Inténtelo nuevamente.",
                        confirmButtonColor: "#1D1D1F"
                    });

                    return;
                }

                getAlumnos(false);
                showToast("Orden de alumnos actualizado.");
            }).fail(function() {
                getAlumnos(false);

                Swal.fire({
                    icon: "error",
                    title: "No fue posible cambiar el orden",
                    text: "No fue posible comunicarse con el servidor.",
                    confirmButtonColor: "#1D1D1F"
                });
            });
        }
    });
}

function renderStudents() {
    const search = normalize($("#studentSearch").val());

    const grupos = [...new Set(
        state.students
            .map(function(student) {
                return String(student.group || "").trim();
            })
            .filter(Boolean)
    )].sort(function(a, b) {
        return a.localeCompare(b, "es", {
            numeric: true,
            sensitivity: "base"
        });
    });

    if (
        state.studentGroupFilter !== "all" &&
        !grupos.includes(state.studentGroupFilter)
    ) {
        state.studentGroupFilter = "all";
    }

    $("#studentGroupTabs").html(`
        <li class="nav-item">
            <button
                type="button"
                class="nav-link ${state.studentGroupFilter === "all" ? "active" : ""}"
                data-student-group-filter="all"
            >
                Todos
                <span class="badge rounded-pill ms-1">
                    ${state.students.length}
                </span>
            </button>
        </li>

        ${grupos.map(function(grupo) {
            var totalGrupo = state.students.filter(function(student) {
                return String(student.group || "").trim() === grupo;
            }).length;

            return `
                <li class="nav-item">
                    <button
                        type="button"
                        class="nav-link ${state.studentGroupFilter === grupo ? "active" : ""}"
                        data-student-group-filter="${escapeHtml(grupo)}"
                    >
                        ${escapeHtml(grupo)}
                        <span class="badge rounded-pill ms-1">
                            ${totalGrupo}
                        </span>
                    </button>
                </li>
            `;
        }).join("")}
    `);

    const alumnosGrupo = state.studentGroupFilter === "all"
        ? state.students
        : state.students.filter(function(student) {
            return String(student.group || "").trim() === state.studentGroupFilter;
        });

    const rows = alumnosGrupo.filter(function(student) {
        return normalize(
            `${student.name} ${student.enrollment} ${student.group} ${student.status} ${student.curp || ""} ${student.correo || ""} ${student.telefono || ""}`
        ).includes(search);
    });

    $("#studentCount").text(
        state.studentGroupFilter === "all"
            ? `${rows.length} de ${state.students.length} alumnos`
            : `${rows.length} de ${alumnosGrupo.length} alumnos`
    );

    if (!rows.length) {
        $("#studentTableBody").html(emptyTableRow(5, "No se encontraron alumnos."));
        return;
    }

    $("#studentTableBody").html(rows.map(function(student) {
        var activo = Number(student.estado) === 1;

        return `
    <tr data-student-id="${student.id}" data-group="${escapeHtml(student.group)}">
        <td>
            <div class="d-flex align-items-center gap-3">
                ${search ? "" : `
                    <span class="student-drag-handle" title="Arrastrar para cambiar el orden">
                        <i class="bi bi-grip-vertical"></i>
                    </span>
                `}
                <span class="avatar">${initials(student.name)}</span>
                        <div class="min-w-0">
                            <div class="fw-bold">${escapeHtml(student.name)}</div>
                            ${student.correo ? `<div class="small text-muted text-truncate">${escapeHtml(student.correo)}</div>` : ""}
                        </div>
                    </div>
                </td>

                <td>
    ${
        String(
            student.enrollment || ''
        ).startsWith('SINMAT-')
            ? `
                <span class="text-muted">
                    Sin matrícula
                </span>
            `
            : `
                <span class="fw-semibold">
                    ${escapeHtml(student.enrollment)}
                </span>
            `
    }
</td>

                <td>
                    <span class="badge rounded-pill badge-group">${escapeHtml(student.group)}</span>
                </td>

                <td>
                    <span class="badge rounded-pill ${activo ? "bg-success-subtle text-success" : "bg-secondary-subtle text-secondary"}">
                        ${activo ? "Activo" : "Inactivo"}
                    </span>
                </td>

                <td class="text-end">
                    ${actionButtons("student", student.id)}
                </td>
            </tr>
        `;
    }).join(""));
}

function renderSchedule() {
    const selectedGroup = document.getElementById('scheduleGroupFilter').value;
    const filtered = state.schedule.filter((item) => selectedGroup === 'all' || item.group === selectedGroup || item.group === 'General');

    document.getElementById('scheduleCount').textContent = `${filtered.length} ${plural(filtered.length, 'clase esta semana', 'clases esta semana')}`;

    document.getElementById('scheduleBoard').innerHTML = days.map((day) => {
        const items = filtered.filter((item) => item.day === day);
        return `
            <section class="schedule-day">
                <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-3">
                    <h3 class="h6 fw-bold mb-0">${day}</h3>
                    <span class="text-muted small fw-bold">${items.length}</span>
                </div>
                <div class="d-grid gap-3">
                    ${items.length ? items.map(scheduleCard).join('') : '<div class="text-muted small py-4 text-center">Sin asignaciones</div>'}
                </div>
            </section>`;
    }).join('');
}

function scheduleCard(item) {
    return `
        <article class="schedule-card ${item.type === 'activity' ? 'activity' : ''}">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <div class="schedule-time">${item.start}–${item.end}</div>
                <div class="d-flex">
                    <button class="action-btn" type="button" data-action="edit" data-entity="schedule" data-id="${item.id}" aria-label="Editar"><i class="bi bi-pencil"></i></button>
                    <button class="action-btn action-delete" type="button" data-action="delete" data-entity="schedule" data-id="${item.id}" aria-label="Eliminar"><i class="bi bi-trash"></i></button>
                </div>
            </div>
            <div class="fw-bold mt-2">${escapeHtml(item.title)}</div>
            <div class="text-muted small mt-1">${escapeHtml(item.group)} · ${escapeHtml(item.teacher)}</div>
            ${item.type === 'activity' ? '<div class="schedule-type small fw-bold mt-2">Actividad</div>' : ''}
        </article>`;
}

function cargarCriteriosAsignaturaSeleccionada() {
    var asignaturaId = Number($("#evaluationSubject").val() || 0);

    var asignatura = state.subjects.find(function(subject) {
        return Number(subject.id) === asignaturaId;
    });

    var criterios = asignatura && Array.isArray(asignatura.criteria)
        ? asignatura.criteria
        : [];

    state.criteria = criterios.map(function(criterio) {
        return {
            id: Number(criterio.id),
            name: criterio.name || criterio.nombre,
            weight: Number(
                criterio.weight !== undefined
                    ? criterio.weight
                    : criterio.porcentaje
            ),
            afecta_promedio: criterio.afecta_promedio !== undefined ? Number(criterio.afecta_promedio) : 1,
            tipo_captura: criterio.tipo_captura || "numero",
            usa_actividades: criterio.usa_actividades !== undefined ? Number(criterio.usa_actividades) : 0,
            redondear_resultado: Number(criterio.redondear_resultado || 0),
            color: criterio.color || "#B8943C"
        };
    });

    renderCriteria();
}

function renderEvaluationSubjectOptions() {
    var select = document.getElementById('evaluationSubject');
    var actual = select.value;
    var grupo = String($("#evaluationGroup").val() || "").trim();
    var ciclo = String($("#evaluationCycle").val() || "").trim();

    var idsPermitidos = state.assignments
        .filter(function(item) {
            return item.group === grupo &&
                item.ciclo_escolar === ciclo;
        })
        .map(function(item) {
            return Number(item.asignatura_id);
        });

    var materias = state.subjects.filter(function(subject) {
        return idsPermitidos.includes(Number(subject.id));
    });

    select.innerHTML = materias.length
        ? materias.map(function(subject) {
            return `<option value="${subject.id}">${escapeHtml(subject.name)}</option>`;
        }).join("")
        : '<option value="">Sin materias asignadas</option>';

    if (
        actual &&
        materias.some(function(subject) {
            return String(subject.id) === String(actual);
        })
    ) {
        select.value = actual;
    }
}

function renderEvaluationGroupOptions() {
    var select = document.getElementById('evaluationGroup');
    var actual = select.value;
    var ciclo = String($("#evaluationCycle").val() || "").trim();

    var grupos = [...new Set(
        state.assignments
            .filter(function(item) {
                return item.ciclo_escolar === ciclo;
            })
            .map(function(item) {
                return item.group;
            })
            .filter(Boolean)
    )].sort(function(a, b) {
        return a.localeCompare(b, 'es', { numeric: true });
    });

    select.innerHTML = grupos.length
        ? grupos.map(function(grupo) {
            return `<option value="${escapeHtml(grupo)}">${escapeHtml(grupo)}</option>`;
        }).join("")
        : '<option value="">Sin asignaciones para este ciclo</option>';

    if (actual && grupos.includes(actual)) {
        select.value = actual;
    }
}

function renderCriteria() {
    const total = criteriaTotal();
    const badge = document.getElementById('criteriaTotalBadge');

    badge.textContent = `${total}%`;

    badge.className =
        `badge rounded-pill fs-6 px-3 py-2 ${
            total === 100
                ? 'text-bg-success'
                : 'text-bg-warning'
        }`;

    document.getElementById('criteriaGrid').innerHTML =
        state.criteria.length
            ? state.criteria.map(function(criterion) {

                var afecta =
                    Number(criterion.afecta_promedio) === 1;

                var usaActividades =
                    Number(criterion.usa_actividades) === 1;

                return `
                    <div class="col-12 col-md-6 col-xl">
                        <article class="criteria-card p-3 p-lg-4 h-100">

                            <div class="d-flex align-items-center gap-2">
                                <span
                                    class="criteria-color"
                                    style="background:${criterion.color}">
                                </span>

                                <h3 class="h6 fw-bold mb-0">
                                    ${escapeHtml(criterion.name)}
                                </h3>
                            </div>

                            <div class="text-muted small mt-3">
                                ${
                                    afecta
                                        ? 'Valor en el promedio'
                                        : 'No afecta el promedio'
                                }
                            </div>

                            <div class="criteria-percent mt-2">
                                ${afecta ? criterion.weight + '%' : '—'}
                            </div>

                            ${
                                usaActividades
                                    ? `
                                        <button
    type="button"
    class="btn btn-outline-primary btn-sm rounded-pill w-100 mt-3
           d-flex align-items-center justify-content-between gap-2"
    onclick="abrirConfiguracionActividades(${criterion.id})">

    <span>
        <i class="bi bi-sliders me-2"></i>
        Administrar actividades
    </span>

    <i class="bi bi-chevron-right"></i>
</button>
                                    `
                                    : ''
                            }

                        </article>
                    </div>
                `;
            }).join('')
            : `
                <div class="col-12">
                    <div class="text-center text-muted py-4">
                        Esta materia no tiene criterios de evaluación.
                    </div>
                </div>
            `;
}

function abrirConfiguracionActividades(criterioId) {

    var criterio = state.criteria.find(function(item) {
        return Number(item.id) === Number(criterioId);
    });

    if (!criterio) return;

    var asignaturaId =
        Number($("#evaluationSubject").val() || 0);

    var periodo =
        String($("#evaluationPeriod").val() || "").trim();

    var ciclo =
        String($("#evaluationCycle").val() || "").trim();

    var grupo =
        String($("#evaluationGroup").val() || "").trim();

    if (!asignaturaId || !periodo || !ciclo || !grupo) {
        Swal.fire(
            "Evaluación incompleta",
            "Selecciona materia, grupo, periodo y ciclo escolar.",
            "warning"
        );

        return;
    }

    $("#activityModalLabel").text(
        "Administrar · " + criterio.name
    );

    $("#activityModalContext").text(
        grupo + " · " + periodo + " · " + ciclo
    );

    $("#activityModalBody").html(`
        <div class="text-center py-5">
            <div class="spinner-border spinner-border-sm"></div>
            <div class="text-muted mt-2">
                Cargando actividades...
            </div>
        </div>
    `);

    activityModalInstance.show();

    var formData = new FormData();

    formData.append("action", "getActividadesEvaluacion");
    formData.append("asignatura_id", asignaturaId);
    formData.append("criterio_id", criterioId);
    formData.append("periodo", periodo);
    formData.append("ciclo_escolar", ciclo);
    formData.append("grupo", grupo);

    $.ajax({
        type: "POST",
        url: "php/index.php",
        data: formData,
        dataType: "json",
        processData: false,
        contentType: false
    }).done(function(data) {

        if (!data || data.status !== "ok") {
            Swal.fire(
                "Error",
                data && data.answer
                    ? data.answer
                    : "No fue posible consultar las actividades.",
                "error"
            );

            return;
        }

        var actividades =
            Array.isArray(data.results)
                ? data.results
                : [];

        $("#activityModalBody").html(`
            <div
                id="activityConfiguration"
                data-criterion-id="${criterioId}">

                <div class="alert alert-light border rounded-4 mb-4">

                    <div class="fw-bold mb-1">
                        ${escapeHtml(criterio.name)}
                    </div>

                    <div class="small text-muted">
    Agrega las actividades que se utilizarán para calcular este criterio.
    Puedes combinar tareas, exámenes u otros tipos de actividad.
</div>

                </div>


                <div class="activity-builder mb-4">

    <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
        <div>
            <div class="fw-bold">
                Agregar actividades
            </div>

            <div class="small text-muted">
                Selecciona el tipo y la cantidad. Después podrás cambiar
                el nombre de cada elemento.
            </div>
        </div>

        <i class="bi bi-plus-circle text-primary fs-4"></i>
    </div>


    <div class="row g-3 align-items-end">

        <div class="col-md-5">

            <label class="form-label fw-semibold">
                Tipo
            </label>

            <select
                id="activityTypeSelector"
                class="form-select"
                onchange="actualizarConstructorActividades()">

                <option value="tarea">
                    Tarea
                </option>

                <option value="examen">
                    Examen
                </option>

                <option value="actividad">
                    Actividad
                </option>

                <option value="otro">
                    Otro tipo...
                </option>

            </select>

        </div>


        <div class="col-md-3">

            <label class="form-label fw-semibold">
                Cantidad
            </label>

            <input
                id="activityTaskCount"
                type="number"
                min="1"
                max="50"
                value="1"
                class="form-control"
                oninput="actualizarConstructorActividades()">

        </div>


        <div class="col-md-4">

            <button
                id="btnAddActivities"
                type="button"
                class="btn btn-primary rounded-pill w-100"
                onclick="agregarActividadesDesdeConstructor()">

                <i class="bi bi-plus-lg me-2"></i>

                <span id="activityAddButtonText">
                    Agregar 1 tarea
                </span>

            </button>

        </div>


        <div
            id="activityCustomTypeWrap"
            class="col-12 d-none">

            <label class="form-label fw-semibold">
                Nombre del tipo
            </label>

            <input
                id="activityCustomType"
                type="text"
                maxlength="30"
                class="form-control"
                placeholder="Ej. Proyecto, exposición, práctica..."
                oninput="actualizarConstructorActividades()">

            <div class="form-text">
                Este nombre sólo identifica el tipo de actividad.
            </div>

        </div>

    </div>

</div>


<div class="d-flex align-items-center justify-content-between mb-3">

    <div>
        <div class="fw-bold">
            Actividades configuradas
        </div>

       <div class="small text-muted">
    ${actividades.length} actividades configuradas. Puedes editar, eliminar o agregar nuevas.
    Los cambios se aplicarán al guardar.
</div>
    </div>

</div>


<div
    id="activityRows"
    class="d-grid gap-2 mb-4">
</div>


<div class="d-flex justify-content-end gap-2">

    <button
        type="button"
        class="btn btn-light border rounded-pill px-4"
        data-bs-dismiss="modal">
        Cancelar
    </button>

    <button
        type="button"
        class="btn btn-primary rounded-pill px-4"
        onclick="guardarConfiguracionActividades()">

        <i class="bi bi-floppy me-2"></i>
        Guardar actividades

    </button>

</div>

            </div>
        `);

        actividades.forEach(function(actividad) {
            agregarFilaActividad(
                actividad.nombre,
                actividad.tipo,
                actividad.id
            );
        });

    }).fail(function(xhr) {

        console.error(xhr.responseText);

        Swal.fire(
            "Error",
            "No fue posible comunicarse con el servidor.",
            "error"
        );
    });
}

function agregarFilaActividad(nombre = "", tipo = "actividad", id = 0) {

    var contenedor =
        document.getElementById("activityRows");

    if (!contenedor) return;


    tipo = String(
        tipo || "actividad"
    ).trim();


    var tipoNormalizado =
        tipo.toLowerCase();


    var tiposBase = [
        "tarea",
        "examen",
        "actividad"
    ];


    var personalizado =
        !tiposBase.includes(
            tipoNormalizado
        );


    if (!personalizado) {
        tipo = tipoNormalizado;
    }


    contenedor.insertAdjacentHTML(
        "beforeend",
        `
        <div
            class="activity-config-row border rounded-4 p-3"
            data-id="${Number(id || 0)}">

            <div class="row g-3 align-items-center">

                <div class="col-md-7">

                    <label class="form-label small text-muted mb-1">
                        Nombre
                    </label>

                    <input
                        type="text"
                        maxlength="120"
                        class="form-control activity-name"
                        value="${escapeHtml(nombre)}"
                        placeholder="Nombre de la actividad">

                </div>


                <div class="col-md-3">

                    <label class="form-label small text-muted mb-1">
                        Tipo
                    </label>

                    <select class="form-select activity-type">

                        <option
                            value="tarea"
                            ${tipoNormalizado === "tarea" ? "selected" : ""}>
                            Tarea
                        </option>

                        <option
                            value="examen"
                            ${tipoNormalizado === "examen" ? "selected" : ""}>
                            Examen
                        </option>

                        <option
                            value="actividad"
                            ${tipoNormalizado === "actividad" ? "selected" : ""}>
                            Actividad
                        </option>

                        ${
                            personalizado
                                ? `
                                    <option
                                        value="${escapeHtml(tipo)}"
                                        selected>
                                        ${escapeHtml(tipo)}
                                    </option>
                                `
                                : ""
                        }

                    </select>

                </div>


                <div class="col-md-2 text-md-end">

                    <label class="form-label small text-muted mb-1 d-none d-md-block">
                        &nbsp;
                    </label>

                    <button
                        type="button"
                        class="btn btn-light border text-danger"
                        onclick="this.closest('.activity-config-row').remove()"
                        title="Eliminar actividad">

                        <i class="bi bi-trash"></i>

                    </button>

                </div>

            </div>

        </div>
        `
    );
}
function actualizarConstructorActividades() {

    var tipo = $("#activityTypeSelector").val() || "tarea";

    var cantidad = Number(
        $("#activityTaskCount").val() || 1
    );

    cantidad = Math.max(
        1,
        Math.min(50, cantidad)
    );

    var esOtro = tipo === "otro";

    $("#activityCustomTypeWrap")
        .toggleClass("d-none", !esOtro);


    var texto = "";


    if (tipo === "tarea") {

        texto = cantidad === 1
            ? "Agregar 1 tarea"
            : "Agregar " + cantidad + " tareas";

    } else if (tipo === "examen") {

        texto = cantidad === 1
            ? "Agregar 1 examen"
            : "Agregar " + cantidad + " exámenes";

    } else if (tipo === "actividad") {

        texto = cantidad === 1
            ? "Agregar 1 actividad"
            : "Agregar " + cantidad + " actividades";

    } else {

        var personalizado =
            $("#activityCustomType").val().trim();

        texto = personalizado
            ? "Agregar " + cantidad + ' · ' + personalizado
            : "Agregar";
    }


    $("#activityAddButtonText").text(texto);
}

function agregarActividadesDesdeConstructor() {

    var tipo =
        $("#activityTypeSelector").val() || "tarea";

    var cantidad =
        Number($("#activityTaskCount").val() || 1);


    cantidad = Math.max(
        1,
        Math.min(50, cantidad)
    );


    var etiqueta = "";


    if (tipo === "tarea") {

        etiqueta = "Tarea";

    } else if (tipo === "examen") {

        etiqueta = "Examen";

    } else if (tipo === "actividad") {

        etiqueta = "Actividad";

    } else {

        etiqueta =
            $("#activityCustomType").val().trim();

        if (!etiqueta) {

            Swal.fire({
                icon: "warning",
                title: "Escribe el tipo",
                text: "Indica cómo quieres llamar a este tipo de actividad.",
                confirmButtonColor: "#1D1D1F"
            });

            $("#activityCustomType").focus();

            return;
        }

        tipo = etiqueta;
    }


    var filas =
        Array.from(
            document.querySelectorAll(
                "#activityRows .activity-config-row"
            )
        );


    var existentes =
        filas.filter(function(fila) {

            var select =
                fila.querySelector(".activity-type");

            if (!select) return false;

            return String(select.value)
                .trim()
                .toLowerCase() ===
                String(tipo)
                    .trim()
                    .toLowerCase();

        }).length;


    for (var i = 1; i <= cantidad; i++) {

        var numero =
            existentes + i;

        agregarFilaActividad(
            etiqueta + " " + numero,
            tipo
        );
    }


    $("#activityTaskCount").val(1);

    actualizarConstructorActividades();
}

function guardarConfiguracionActividades() {

    var configuracion =
        document.getElementById(
            "activityConfiguration"
        );

    if (!configuracion) return;


    var criterioId =
        Number(
            configuracion.dataset.criterionId
        );


    var actividades = [];

    var valido = true;


    document
        .querySelectorAll(
            "#activityRows .activity-config-row"
        )
        .forEach(function(fila, index) {

            var nombre =
                fila
                    .querySelector(
                        ".activity-name"
                    )
                    .value
                    .trim();

            var tipo =
                fila
                    .querySelector(
                        ".activity-type"
                    )
                    .value;

            if (!nombre) {
                valido = false;
                return;
            }

            actividades.push({
                id: Number(
                    fila.dataset.id || 0
                ),
                nombre: nombre,
                tipo: tipo,
                orden: index + 1
            });
        });


    if (!valido || !actividades.length) {

        Swal.fire(
            "Revisa las actividades",
            "Agrega al menos una actividad y asegúrate de que todas tengan nombre.",
            "warning"
        );

        return;
    }


    var formData = new FormData();

    formData.append("action", "guardarActividadesEvaluacion");
    formData.append("asignatura_id", Number($("#evaluationSubject").val()));
    formData.append("criterio_id", criterioId);
    formData.append("periodo", $("#evaluationPeriod").val());
    formData.append("ciclo_escolar", $("#evaluationCycle").val());
    formData.append("grupo", $("#evaluationGroup").val());
    formData.append("actividades", JSON.stringify(actividades));

    $.ajax({
        type: "POST",
        url: "php/index.php",
        data: formData,
        dataType: "json",
        processData: false,
        contentType: false
    }).done(function(data) {

        if (!data || data.status !== "ok") {

            Swal.fire(
                "No fue posible guardar",
                data && data.answer
                    ? data.answer
                    : "Ocurrió un problema.",
                "error"
            );

            return;
        }

        activityModalInstance.hide();
        showToast("Actividades actualizadas correctamente.");
        getCalificaciones();

    }).fail(function(xhr) {

        console.error(xhr.responseText);

        Swal.fire(
            "Error",
            "No fue posible guardar las actividades.",
            "error"
        );
    });
}

function renderGrades() {

    const group =
        document.getElementById(
            'evaluationGroup'
        ).value;

    const students =
        state.students.filter(function(student) {
            return student.group === group;
        });


    document.getElementById(
        'gradeTableHead'
    ).innerHTML = `
        <tr>

            <th>Alumno</th>

            ${state.criteria.map(function(criterion) {

                var afecta =
                    Number(
                        criterion.afecta_promedio
                    ) === 1;

                return `
                    <th class="text-center">

                        ${escapeHtml(
                            criterion.name
                        )}

                        <div class="small fw-normal">
                            ${
                                afecta
                                    ? criterion.weight + "%"
                                    : "No pondera"
                            }
                        </div>

                    </th>
                `;
            }).join('')}

            <th class="text-center">
                Promedio
            </th>

        </tr>
    `;


    document.getElementById(
        'gradeTableBody'
    ).innerHTML = students.length

        ? students.map(function(student) {

            const grades =
                state.grades[
                    student.enrollment
                ] || {};


            return `
                <tr
                    data-enrollment="${escapeHtml(
                        student.enrollment
                    )}">

                    <td>

                        <div class="d-flex align-items-center gap-3 grade-student-cell">

                            <span class="avatar">
                                ${initials(student.name)}
                            </span>

                            <div>

                                <div class="fw-bold text-nowrap">
                                    ${escapeHtml(student.name)}
                                </div>

                                <div class="text-muted small">
                                    ${escapeHtml(
                                        student.enrollment
                                    )}
                                </div>

                            </div>

                        </div>

                    </td>


                    ${state.criteria.map(function(criterion) {

                        var usaActividades = Number(criterion.usa_actividades) === 1;
                        var tipoCaptura = criterion.tipo_captura || "numero";

                        if (tipoCaptura === "conducta") {

    var valorConducta =
        grades[criterion.id] ?? "";

    return `
        <td class="text-center">

            <select
                class="form-select conduct-grade-select mx-auto"
                data-student-id="${student.id}"
                data-enrollment="${escapeHtml(student.enrollment)}"
                data-criterion-id="${criterion.id}"
                aria-label="Conducta de ${escapeHtml(student.name)}">

                <option value="" ${valorConducta === "" ? "selected" : ""}>
                    —
                </option>

                <option value="B" ${valorConducta === "B" ? "selected" : ""}>
                    Buena
                </option>

                <option value="R" ${valorConducta === "R" ? "selected" : ""}>
                    Regular
                </option>

                <option value="M" ${valorConducta === "M" ? "selected" : ""}>
                    Mala
                </option>

            </select>

        </td>
    `;
}


                        if (usaActividades) {

                            var resultado =
                                grades[criterion.id];

                                var aportacion =
    calcularAportacionCriterio(
        resultado,
        criterion
    );

var maximoAportacion =
    maximoAportacionCriterio(
        criterion
    );

                            return `
    <td class="text-center">

        <button
            type="button"
            class="activity-grade-button btn-activity-grade"
            data-student-id="${student.id}"
            data-enrollment="${escapeHtml(student.enrollment)}"
            data-criterion-id="${criterion.id}">

            <div class="activity-grade-score">
                ${
                    resultado === null ||
                    resultado === undefined ||
                    resultado === ''
                        ? '—'
                        : Number(resultado).toFixed(
    Number(criterion.redondear_resultado) === 1 ? 0 : 1
)
                }
            </div>

            <div class="small text-muted mt-1">
                ${
                    aportacion === null
                        ? 'Sin aportación'
                        : 'Aporta ' +
                          formatearAportacion(aportacion) +
                          ' de ' +
                          formatearAportacion(maximoAportacion)
                }
            </div>

            <div class="activity-grade-link mt-1">

                <span>
                    <i class="bi bi-pencil-square me-1"></i>
                    ${
                        resultado === null ||
                        resultado === undefined ||
                        resultado === ''
                            ? 'Capturar'
                            : 'Calificar'
                    }
                </span>

                <i class="bi bi-chevron-right"></i>

            </div>

        </button>

    </td>
`;
                        }


                        var valorDirecto =
    grades[criterion.id] ?? '';

var aportacionDirecta =
    calcularAportacionCriterio(
        valorDirecto,
        criterion
    );

var maximoDirecto =
    maximoAportacionCriterio(
        criterion
    );

return `
    <td class="text-center">

        <div class="d-flex flex-column align-items-center gap-1">

            <input
                class="form-control grade-input mx-auto"
                type="number"
                min="0"
                ${tipoCaptura === "inasistencias" ? 'step="1"' : 'max="10" step="0.01"'}
    data-capture-type="${tipoCaptura}"
                step="0.01"
                value="${valorDirecto}"
                data-student-id="${student.id}"
                data-enrollment="${escapeHtml(
                    student.enrollment
                )}"
                data-criterion-id="${criterion.id}"
                aria-label="${escapeHtml(
                    criterion.name
                )} de ${escapeHtml(
                    student.name
                )}">

            ${
                Number(
                    criterion.afecta_promedio
                ) === 1
                    ? `
                        <div
                            class="small text-muted"
                            data-contribution-for="${escapeHtml(
                                student.enrollment
                            )}-${criterion.id}">

                            Aporta
                            ${formatearAportacion(
                                aportacionDirecta
                            )}
                            de
                            ${formatearAportacion(
                                maximoDirecto
                            )}

                        </div>
                    `
                    : ''
            }

        </div>

    </td>
`;

                    }).join('')}


                    <td class="text-center">

                        <span
                            class="grade-average ${
                                averageClass(
                                    calculateStudentAverage(
                                        student.enrollment
                                    )
                                )
                            }"
                            data-average-for="${escapeHtml(
                                student.enrollment
                            )}">

                            ${
                                formatAverage(
                                    calculateStudentAverage(
                                        student.enrollment
                                    )
                                )
                            }

                        </span>

                    </td>

                </tr>
            `;

        }).join('')

        : emptyTableRow(
            state.criteria.length + 2,
            'No hay alumnos registrados en este grupo.'
        );


    updateGroupAverage(students);

    renderBulkGradeOptions();
}

function updateAveragesOnly() {
    const group = document.getElementById('evaluationGroup').value;
    const students = state.students.filter((student) => student.group === group);

    students.forEach((student) => {
        const average = calculateStudentAverage(student.enrollment);
        const element = document.querySelector(`[data-average-for="${CSS.escape(student.enrollment)}"]`);
        if (!element) return;
        element.textContent = formatAverage(average);
        element.className = `grade-average ${averageClass(average)}`;
    });

    updateGroupAverage(students);
}

function updateGroupAverage(students) {
    const averages = students.map((student) => calculateStudentAverage(student.enrollment)).filter((value) => value !== null);
    const average = averages.length ? averages.reduce((sum, value) => sum + value, 0) / averages.length : 0;
    document.getElementById('groupAverage').textContent = average.toFixed(1);
}

function calculateStudentAverage(enrollment) {
    const totalWeight = criteriaTotal();
    if (!totalWeight) return null;

    const grades = state.grades[enrollment] || {};
    let weighted = 0;
    let appliedWeight = 0;

    state.criteria.forEach((criterion) => {
    if (Number(criterion.afecta_promedio) !== 1) return;

    const grade = grades[criterion.id];

    if (
        grade === null ||
        grade === undefined ||
        grade === ''
    ) {
        return;
    }

    var gradeCalculo = Number(grade);

if (Number(criterion.redondear_resultado) === 1) {
    gradeCalculo = Math.round(gradeCalculo);
}

weighted += gradeCalculo * Number(criterion.weight);

    //weighted += Number(grade) * Number(criterion.weight);
    appliedWeight += Number(criterion.weight);
});

    if (!appliedWeight) return null;
    return weighted / appliedWeight;
}

function criteriaTotal() {
    return state.criteria.reduce(function(sum, criterion) {
        if (Number(criterion.afecta_promedio) !== 1) {
            return sum;
        }

        return sum + Number(criterion.weight || 0);
    }, 0);
}

function distributeCriteria() {
    if (!state.criteria.length) return;

    const base = Math.floor(100 / state.criteria.length);
    let remainder = 100 - (base * state.criteria.length);

    state.criteria.forEach((criterion) => {
        criterion.weight = base + (remainder > 0 ? 1 : 0);
        remainder -= remainder > 0 ? 1 : 0;
    });

    renderCriteria();
    renderGrades();
    showToast('Los porcentajes se distribuyeron automáticamente.');
}

function actualizarPosicionAlumnoModal() {
    var posicion = String($("#field-posicion").val() || "");
    var mostrarReferencia = posicion === "antes" || posicion === "despues";
    var grado = String($("#field-grado").val() || "").trim();
    var grupo = String($("#field-grupo").val() || "").trim().toUpperCase();
    var alumnoActualId = state.editing && state.editing.id ? Number(state.editing.id) : 0;

    $("#studentReferenceWrap").toggleClass("d-none", !mostrarReferencia);
    $("#field-referencia_id").prop("required", mostrarReferencia);

    if (!mostrarReferencia) {
        $("#field-referencia_id").val("");
        return;
    }

    var alumnos = state.students.filter(function(student) {
        return Number(student.id) !== alumnoActualId &&
            String(student.grado || "").trim() === grado &&
            String(student.grupo || "").trim().toUpperCase() === grupo;
    });

    $("#field-referencia_id").html(
        '<option value="">Selecciona un alumno</option>' +
        alumnos.map(function(student) {
            return `<option value="${student.id}">${escapeHtml(student.name)}</option>`;
        }).join("")
    );
}

function openEntityModal(entity, id = null) {
    state.editing = { entity, id };

    const record = id ? getEntityCollection(entity).find((item) => item.id === id) : null;
    const title = document.getElementById('entityModalLabel');
    const body = document.getElementById('entityModalBody');
    const dialog = document.querySelector('#entityModal .modal-dialog');

    dialog.classList.toggle(
    'modal-lg',
    entity === 'student' || entity === 'teacher' || entity === 'subject'
);

    title.textContent = `${record ? 'Editar' : 'Nuevo'} ${entityLabel(entity)}`;
    body.innerHTML = entityFields(entity, record);

if (entity === 'subject') {
    actualizarTotalCriteriosAsignatura();
}

if (entity === 'student') {
    $("#field-posicion").on("change", actualizarPosicionAlumnoModal);
    $("#field-grado, #field-grupo").on("input", actualizarPosicionAlumnoModal);
    actualizarPosicionAlumnoModal();
}

entityModal.show();
}

function entityFields(entity, record = {}) {
    record = record || {};

    const groups = ['5° A', '5° B', '6° A'];

    if (entity === 'subject') {
    const estado = record.estado !== undefined ? Number(record.estado) : 1;

    const criterios = Array.isArray(record.criteria) && record.criteria.length
        ? record.criteria
        : [{
            id: 0,
            nombre: "",
            porcentaje: 100,
            color: "#B8943C"
        }];

    return `
        <div class="row g-3">
            <div class="col-md-4">
                ${inputField(
                    'clave',
                    'Clave',
                    record.clave || record.code || '',
                    'Ej. MAT-01',
                    'text',
                    'maxlength="60" autocomplete="off" style="text-transform:uppercase"'
                )}
            </div>

            <div class="col-md-5">
                ${inputField(
                    'nombre',
                    'Nombre de la materia',
                    record.nombre || record.name || '',
                    'Ej. Matemáticas'
                )}
            </div>

            <div class="col-md-3">
                ${selectField(
                    'estado',
                    'Estado',
                    [
                        { value: 1, label: 'Activa' },
                        { value: 0, label: 'Inactiva' }
                    ],
                    estado
                )}
            </div>
        </div>

        ${textareaField(
            'descripcion',
            'Descripción',
            record.descripcion || '',
            'Descripción opcional de la asignatura'
        )}

        <hr class="my-4">

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
            <div>
                <div class="fw-bold">Criterios de evaluación</div>
                <div class="small text-muted">
                    Define los criterios y su porcentaje. El total debe ser 100%.
                </div>
            </div>

            <button type="button" class="btn btn-outline-primary rounded-pill px-3"
                data-action="add-subject-criterion">
                <i class="bi bi-plus-lg me-1"></i>Agregar criterio
            </button>
        </div>

        <div id="subjectCriteriaList" class="d-grid gap-3">
            ${criterios.map(function(criterio) {
                return criterioAsignaturaRow(criterio);
            }).join("")}
        </div>

        <div class="d-flex justify-content-end mt-3">
            <div class="border rounded-pill px-3 py-2">
                <span class="text-muted me-2">Total</span>
                <span id="subjectCriteriaTotal" class="fw-bold">0%</span>
            </div>
        </div>
    `;
}

    if (entity === 'teacher') {
    const estado = record.estado !== undefined ? Number(record.estado) : 1;
    const editando = Number(record.id || 0) > 0;

    return `
        <div class="row g-3">
            <div class="col-md-4">
                ${inputField('clave', 'Clave', record.clave || record.code, 'DOC-001')}
            </div>
            <div class="col-md-4">
                ${inputField('nombre', 'Nombre', record.nombre, 'Nombre')}
            </div>
            <div class="col-md-4">
                ${inputField('apellido', 'Apellidos', record.apellido, 'Apellidos')}
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                ${optionalInputField('curp', 'CURP', record.curp, '18 caracteres', 'text', 'maxlength="18"')}
            </div>
            <div class="col-md-6">
                ${selectField('estado', 'Estado', [
                    { value: 1, label: 'Activo' },
                    { value: 0, label: 'Inactivo' }
                ], estado)}
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                ${optionalInputField('correo', 'Correo electrónico', record.correo || record.email, 'correo@escuela.mx', 'email')}
            </div>
            <div class="col-md-6">
                ${optionalInputField('telefono', 'Teléfono', record.telefono, 'Ej. 951 123 4567')}
            </div>
        </div>

        ${optionalInputField('especialidad', 'Especialidad', record.especialidad, 'Ej. Matemáticas')}

        <hr class="my-4">

        <div class="small text-primary fw-bold text-uppercase mb-3">Acceso al sistema</div>

        <div class="row g-3">
            <div class="col-md-6">
                ${inputField('usuario', 'Usuario', record.usuario, 'Ej. jmartinez')}
            </div>

            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="field-password">Contraseña</label>
                    <input id="field-password" class="form-control form-control-lg" type="password" name="password"
                        placeholder="${editando ? '' : 'Mínimo 8 caracteres'}"
                        autocomplete="new-password" ${editando ? '' : 'required'}>
                    ${editando ? '<div class="form-text">Solo escribe una contraseña si deseas cambiarla.</div>' : ''}
                </div>
            </div>
        </div>

        ${textareaField('observaciones', 'Observaciones', record.observaciones, 'Información adicional del maestro')}
    `;
}

    if (entity === 'student') {
        const estado = record.estado !== undefined ? Number(record.estado) : 1;
        const editandoAlumno = Number(record.id || 0) > 0;

        return `
            <div class="mb-4">
                <div class="d-flex align-items-center gap-3">
                    <span class="avatar">
                        <i class="bi bi-person"></i>
                    </span>

                    <div>
                        <div class="fw-bold">Información del alumno</div>
                        <div class="small text-muted">Captura los datos generales del estudiante.</div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-5">
                   ${optionalInputField(
    'matricula',
    'Matrícula (opcional)',
    (
        String(
            record.matricula ||
            record.enrollment ||
            ''
        ).startsWith('SINMAT-')
            ? ''
            : (
                record.matricula ||
                record.enrollment ||
                ''
            )
    ),
    '',
    'text',
    'maxlength="60" autocomplete="off" style="text-transform:uppercase"'
)}
                </div>

                <div class="col-md-7">
                    ${selectField(
                        'estado',
                        'Estado',
                        [
                            { value: 1, label: 'Activo' },
                            { value: 0, label: 'Inactivo' }
                        ],
                        estado
                    )}
                </div>

                <div class="col-md-6">
                    ${inputField(
                        'nombre',
                        'Nombre',
                        record.nombre || '',
                        'Nombre del alumno'
                    )}
                </div>

                <div class="col-md-6">
                    ${inputField(
                        'apellido',
                        'Apellidos',
                        record.apellido || '',
                        'Apellidos del alumno'
                    )}
                </div>

                <div class="col-md-6">
                    ${inputField(
                        'grado',
                        'Grado',
                        record.grado || '',
                        'Ej. 5',
                        'text',
                        'maxlength="20"'
                    )}
                </div>

                <div class="col-md-6">
                    ${inputField(
                        'grupo',
                        'Grupo',
                        record.grupo || '',
                        'Ej. A',
                        'text',
                        'maxlength="20" style="text-transform:uppercase"'
                    )}
                </div>
            </div>

                        <div id="studentOrderSection" class="border-top pt-3 mt-2 mb-3">
                <div class="fw-semibold">Posición en la lista</div>
                <div class="small text-muted mb-3">
                    Elige dónde debe aparecer el alumno dentro de su grupo.
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="field-posicion">
                            Ubicación
                        </label>

                        <select id="field-posicion" name="posicion" class="form-select form-select-lg">
                            ${editandoAlumno ? '<option value="mantener" selected>Mantener posición actual</option>' : ''}
                            <option value="inicio">Al inicio</option>
                            <option value="final" ${editandoAlumno ? '' : 'selected'}>Al final</option>
                            <option value="antes">Antes de</option>
                            <option value="despues">Después de</option>
                        </select>
                    </div>

                    <div id="studentReferenceWrap" class="col-md-6 d-none">
                        <label class="form-label fw-semibold" for="field-referencia_id">
                            Alumno de referencia
                        </label>

                        <select id="field-referencia_id" name="referencia_id" class="form-select form-select-lg">
                            <option value="">Selecciona un alumno</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="border-top pt-3 mt-2 mb-3">
                <div class="fw-semibold">Información adicional</div>
                <div class="small text-muted">Estos datos son opcionales y pueden completarse después.</div>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    ${optionalInputField(
                        'fecha_nacimiento',
                        'Fecha de nacimiento',
                        record.fecha_nacimiento || '',
                        '',
                        'date'
                    )}
                </div>

                <div class="col-md-6">
                    ${optionalInputField(
                        'curp',
                        'CURP',
                        record.curp || '',
                        '18 caracteres',
                        'text',
                        'maxlength="18" style="text-transform:uppercase"'
                    )}
                </div>

                <div class="col-md-7">
                    ${optionalInputField(
                        'correo',
                        'Correo electrónico',
                        record.correo || '',
                        'correo@ejemplo.com',
                        'email'
                    )}
                </div>

                <div class="col-md-5">
                    ${optionalInputField(
                        'telefono',
                        'Teléfono',
                        record.telefono || '',
                        'Ej. 2221234567',
                        'tel',
                        'maxlength="30"'
                    )}
                </div>
            </div>

            ${textareaField(
                'observaciones',
                'Observaciones',
                record.observaciones || '',
                'Información adicional del alumno...'
            )}
        `;
    }

    if (entity === 'schedule') {
    var editando = Number(record.id || 0) > 0;

    var gruposHorario = [...new Map(
        state.students
            .filter(function(student) {
                return Number(student.estado) === 1;
            })
            .map(function(student) {
                var key = String(student.grado) + "|" + String(student.grupo);

                return [
                    key,
                    {
                        value: key,
                        label: student.group
                    }
                ];
            })
    ).values()];

    var materiasHorario = state.subjects
        .filter(function(subject) {
            return Number(subject.estado) === 1;
        })
        .map(function(subject) {
            return {
                value: subject.id,
                label: subject.name
            };
        });

    var maestrosHorario = state.teachers
        .filter(function(teacher) {
            return Number(teacher.estado) === 1;
        })
        .map(function(teacher) {
            return {
                value: teacher.id,
                label: teacher.name
            };
        });

    var grupoActual = record.grado && record.grupo
        ? record.grado + "|" + record.grupo
        : "";

    var cicloActual =
        record.ciclo_escolar ||
        $("#evaluationCycle").val() ||
        "2026-2027";

    return `
        ${
            editando
                ? `
                    <div class="alert alert-light border rounded-4 mb-4">
                        <div class="small text-muted mb-1">Asignación académica</div>
                        <div class="fw-bold">${escapeHtml(record.asignatura || record.title || "")}</div>
                        <div class="text-muted small">
                            ${escapeHtml(record.group || "")} · ${escapeHtml(record.maestro || record.teacher || "")}
                        </div>

                        <input type="hidden" name="asignatura_id" value="${record.asignatura_id}">
                        <input type="hidden" name="maestro_id" value="${record.maestro_id}">
                        <input type="hidden" name="grado" value="${escapeHtml(record.grado || "")}">
                        <input type="hidden" name="grupo" value="${escapeHtml(record.grupo || "")}">
                        <input type="hidden" name="ciclo_escolar" value="${escapeHtml(cicloActual)}">
                    </div>
                `
                : `
                    <div class="row g-3">
                        <div class="col-md-6">
                            ${selectField(
                                'asignatura_id',
                                'Materia',
                                materiasHorario,
                                ''
                            )}
                        </div>

                        <div class="col-md-6">
                            ${selectField(
                                'grupo_academico',
                                'Grado y grupo',
                                gruposHorario,
                                grupoActual
                            )}
                        </div>

                        <div class="col-md-8">
                            ${selectField(
                                'maestro_id',
                                'Maestro',
                                maestrosHorario,
                                ''
                            )}
                        </div>

                        <div class="col-md-4">
                            ${inputField(
                                'ciclo_escolar',
                                'Ciclo escolar',
                                cicloActual,
                                '2026-2027'
                            )}
                        </div>
                    </div>
                `
        }

        <div class="border-top pt-3 mt-2 mb-3">
            <div class="fw-semibold">Horario de clase</div>
            <div class="small text-muted">Indica cuándo se imparte esta materia.</div>
        </div>

        <div class="row g-3">
            <div class="col-md-4">
                ${selectField(
                    'dia',
                    'Día',
                    days,
                    record.dia || record.day || ''
                )}
            </div>

            <div class="col-md-4">
                ${inputField(
                    'hora_inicio',
                    'Hora de inicio',
                    record.hora_inicio || record.start || '',
                    '',
                    'time'
                )}
            </div>

            <div class="col-md-4">
                ${inputField(
                    'hora_fin',
                    'Hora final',
                    record.hora_fin || record.end || '',
                    '',
                    'time'
                )}
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-5">
                ${optionalInputField(
                    'aula',
                    'Aula (opcional)',
                    record.aula || '',
                    'Ej. Aula 3'
                )}
            </div>

            <div class="col-md-7">
                ${optionalInputField(
                    'observaciones',
                    'Observaciones (opcional)',
                    record.observaciones || '',
                    'Información adicional'
                )}
            </div>
        </div>
    `;
}

    if (entity === 'criterion') {
        return `
            ${inputField(
                'name',
                'Nombre del criterio',
                record.name,
                'Ej. Proyecto final'
            )}

            <div class="row g-3">
                <div class="col-md-7">
                    ${inputField(
                        'weight',
                        'Valor en el promedio (%)',
                        record.weight,
                        '20',
                        'number',
                        'min="0" max="100" step="1"'
                    )}
                </div>

                <div class="col-md-5">
                    ${inputField(
                        'color',
                        'Color',
                        record.color || '#B8943C',
                        '',
                        'color'
                    )}
                </div>
            </div>

            <div class="alert alert-light border rounded-3 small mb-0">
                El total se mantendrá automáticamente en 100%.
                Al cambiar este valor, los demás criterios se ajustarán proporcionalmente.
            </div>
        `;
    }

    return '';
}

function calcularAportacionCriterio(calificacion, criterio) {
    if (Number(criterio.afecta_promedio) !== 1 || calificacion === null || calificacion === undefined || calificacion === '') return null;

    var valor = Number(calificacion);
    var porcentaje = Number(criterio.weight || 0);

    if (!Number.isFinite(valor) || !Number.isFinite(porcentaje)) return null;

    if (Number(criterio.redondear_resultado) === 1) valor = Math.round(valor);

    return valor * porcentaje / 100;
}


function maximoAportacionCriterio(
    criterio
) {

    if (
        Number(criterio.afecta_promedio) !== 1
    ) {
        return null;
    }

    /*
     * 60% = máximo 6
     * 30% = máximo 3
     * 10% = máximo 1
     */
    return Number(criterio.weight || 0) / 10;
}


function formatearAportacion(valor) {
    if (valor === null || valor === undefined || !Number.isFinite(Number(valor))) return '—';

    var numero = Math.trunc(Number(valor) * 10) / 10;
    return numero.toFixed(1);
}

function criterioAsignaturaRow(criterio = {}) {
    var redondearResultado = Number(criterio.redondear_resultado || 0);
    var id = Number(criterio.id || 0);
    var nombre = criterio.nombre || criterio.name || '';
    var porcentaje = criterio.porcentaje !== undefined
        ? criterio.porcentaje
        : (criterio.weight !== undefined ? criterio.weight : '');

    var afecta = criterio.afecta_promedio === undefined
        ? 1
        : Number(criterio.afecta_promedio);

    var tipo = criterio.tipo_captura || 'numero';
    var usaActividades = Number(criterio.usa_actividades || 0);
    var color = criterio.color || '#B8943C';

    return `
        <div class="subject-criterion-row border rounded-4 p-3">
            <input type="hidden" class="subject-criterion-id" value="${id}">

            <div class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label fw-semibold">Criterio</label>
                    <input type="text"
                        class="form-control subject-criterion-name"
                        value="${escapeHtml(nombre)}"
                        placeholder="Ej. Aspecto cognoscitivo">
                </div>

                <div class="col-md-2">
                    <label class="form-label fw-semibold">Porcentaje</label>
                    <div class="input-group">
                        <input type="number"
                            class="form-control subject-criterion-percentage"
                            value="${escapeHtml(porcentaje)}"
                            min="0"
                            max="100"
                            step="0.01">
                        <span class="input-group-text">%</span>
                    </div>
                </div>

                <div class="col-md-2">
                    <label class="form-label fw-semibold">Captura</label>
                    <select class="form-select subject-criterion-type">
                        <option value="numero" ${tipo === 'numero' ? 'selected' : ''}>Calificación</option>
                        <option value="conducta" ${tipo === 'conducta' ? 'selected' : ''}>Conducta</option>
                        <option value="inasistencias" ${tipo === 'inasistencias' ? 'selected' : ''}>Inasistencias</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label fw-semibold">Color</label>
                    <input type="color"
                        class="form-control form-control-color w-100 subject-criterion-color"
                        value="${escapeHtml(color)}">
                </div>

                <div class="col-md-1 text-end">
                    <button type="button"
                        class="btn btn-light border text-danger"
                        data-action="remove-subject-criterion"
                        title="Eliminar criterio">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-4 mt-3 pt-3 border-top">
                <div class="form-check form-switch">
                    <input class="form-check-input subject-criterion-affects"
                        type="checkbox"
                        ${afecta ? 'checked' : ''}>
                    <label class="form-check-label fw-semibold">
                        Afecta el promedio
                    </label>
                </div>

                <div class="form-check form-switch">
                    <input class="form-check-input subject-criterion-activities"
                        type="checkbox"
                        ${usaActividades ? 'checked' : ''}>
                    <label class="form-check-label">
                        Se obtiene de actividades
                    </label>
                </div>

                <div class="form-check form-switch">
    <input class="form-check-input subject-criterion-round" type="checkbox" ${redondearResultado ? 'checked' : ''}>
    <label class="form-check-label">Redondear resultado</label>
</div>
            </div>
        </div>
    `;
}

function redistributeCriterionWeights(changedId, requestedWeight) {
    const changedCriterion = state.criteria.find(
        (criterion) => criterion.id === changedId
    );

    if (!changedCriterion) return;

    const newWeight = Math.max(
        0,
        Math.min(100, Math.round(Number(requestedWeight) || 0))
    );

    const otherCriteria = state.criteria.filter(
        (criterion) => criterion.id !== changedId
    );

    changedCriterion.weight = newWeight;

    if (otherCriteria.length === 0) {
        changedCriterion.weight = 100;
        return;
    }

    const remainingWeight = 100 - newWeight;

    const previousTotal = otherCriteria.reduce(
        (sum, criterion) => sum + Number(criterion.weight || 0),
        0
    );

    if (previousTotal <= 0) {
        const baseWeight = Math.floor(
            remainingWeight / otherCriteria.length
        );

        let remainder =
            remainingWeight - (baseWeight * otherCriteria.length);

        otherCriteria.forEach((criterion) => {
            criterion.weight =
                baseWeight + (remainder > 0 ? 1 : 0);

            if (remainder > 0) remainder--;
        });

        return;
    }

    const distributed = otherCriteria.map((criterion, index) => {
        const exactWeight =
            (Number(criterion.weight || 0) / previousTotal) *
            remainingWeight;

        const integerWeight = Math.floor(exactWeight);

        return {
            criterion,
            index,
            weight: integerWeight,
            decimal: exactWeight - integerWeight
        };
    });

    let pendingWeight =
        remainingWeight -
        distributed.reduce(
            (sum, item) => sum + item.weight,
            0
        );

    distributed.sort((a, b) => {
        return b.decimal - a.decimal || a.index - b.index;
    });

    distributed.forEach((item) => {
        if (pendingWeight > 0) {
            item.weight++;
            pendingWeight--;
        }

        item.criterion.weight = item.weight;
    });
}

function saveEntity(event) {
    event.preventDefault();

    const { entity, id } = state.editing;

    if (entity === 'student') {
        guardarAlumno(event.currentTarget, id);
        return;
    }

    if (entity === 'teacher') {
        guardarMaestro(event.currentTarget, id);
        return;
    }

    if (entity === 'subject') {
        guardarAsignatura(event.currentTarget, id);
        return;
    }

    if (entity === 'schedule') {
        guardarHorario(event.currentTarget, id);
        return;
    }

    const form = new FormData(event.currentTarget);
    const collection = getEntityCollection(entity);
    const record = id ? collection.find((item) => item.id === id) : { id: nextId(collection) };

    if (!record) return;

    for (const [key, value] of form.entries()) {
        record[key] = ['teacherId', 'weight'].includes(key)
            ? Number(value)
            : value.trim();
    }

    if (entity === 'criterion') {
        const requestedWeight = record.weight;

        if (!id) {
            collection.push(record);

            state.students.forEach((student) => {
                state.grades[student.enrollment] ??= {};
                state.grades[student.enrollment][record.id] ??= null;
            });
        }

        redistributeCriterionWeights(record.id, requestedWeight);
    } else if (!id) {
        collection.push(record);
    }

    entityModal.hide();
    renderAll();
    showToast(`${capitalize(entityLabel(entity))} guardado correctamente.`);
}

function guardarAlumno(form, id) {
    var formData = new FormData(form);

    var boton = $(form).find('button[type="submit"]');
    var contenidoBoton = boton.html();

    formData.append("action", "guardarAlumno");
    formData.append("id", id || 0);

    $.ajax({
        type: "POST",
        url: "php/index.php",
        data: formData,
        dataType: "json",
        processData: false,
        contentType: false,
        beforeSend: function() {
            boton.prop("disabled", true);

            boton.html(`
                <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                Guardando...
            `);
        }
    }).done(function(data) {
        boton.prop("disabled", false);
        boton.html(contenidoBoton);

        if (!data || data.status !== "ok") {
            Swal.fire({
                icon: data && data.status === "warning"
                    ? "warning"
                    : "error",
                title: data && data.status === "warning"
                    ? "Revisa la información"
                    : "No fue posible guardar",
                text: data && data.answer
                    ? data.answer
                    : "Ocurrió un problema al guardar el alumno.",
                confirmButtonColor: "#1D1D1F"
            });

            return;
        }
var posicion = String(formData.get("posicion") || (id ? "mantener" : "final"));
var alumnoId = Number(data.id || id || 0);
var grado = String(formData.get("grado") || "").trim();
var grupo = String(formData.get("grupo") || "").trim().toUpperCase();
var referenciaId = Number(formData.get("referencia_id") || 0);

var alumnoAnterior = id
    ? state.students.find(function(student) {
        return Number(student.id) === Number(id);
    })
    : null;

if (posicion === "mantener" && alumnoAnterior && (String(alumnoAnterior.grado || "").trim() !== grado || String(alumnoAnterior.grupo || "").trim().toUpperCase() !== grupo)) {
    posicion = "final";
}

if (posicion === "mantener") {
    entityModal.hide();
    getAlumnos(false);
    showToast(data.answer || "Alumno actualizado correctamente.");
    return;
}

var ids = state.students
    .filter(function(student) {
        return Number(student.id) !== alumnoId &&
            String(student.grado || "").trim() === grado &&
            String(student.grupo || "").trim().toUpperCase() === grupo;
    })
    .map(function(student) {
        return Number(student.id);
    });

if (posicion === "inicio") {
    ids.unshift(alumnoId);
} else if (posicion === "final") {
    ids.push(alumnoId);
} else {
    var indice = ids.indexOf(referenciaId);

    if (indice === -1) {
        ids.push(alumnoId);
    } else {
        ids.splice(
            posicion === "antes" ? indice : indice + 1,
            0,
            alumnoId
        );
    }
}

var ordenData = new FormData();
ordenData.append("action", "guardarOrdenAlumnos");
ordenData.append("ids", JSON.stringify(ids));

$.ajax({
    type: "POST",
    url: "php/index.php",
    data: ordenData,
    dataType: "json",
    processData: false,
    contentType: false
}).done(function(dataOrden) {
    entityModal.hide();
    getAlumnos(false);

    if (!dataOrden || dataOrden.status !== "ok") {
        Swal.fire({
            icon: "warning",
            title: "Alumno guardado",
            text: "El alumno se guardó, pero no fue posible actualizar su posición.",
            confirmButtonColor: "#1D1D1F"
        });

        return;
    }

    showToast("Alumno guardado correctamente.");
}).fail(function() {
    entityModal.hide();
    getAlumnos(false);

    Swal.fire({
        icon: "warning",
        title: "Alumno guardado",
        text: "El alumno se guardó, pero no fue posible actualizar su posición.",
        confirmButtonColor: "#1D1D1F"
    });
});

    }).fail(function(xhr) {
        boton.prop("disabled", false);
        boton.html(contenidoBoton);

        console.error(xhr.responseText);

        Swal.fire({
            icon: "error",
            title: "Error de conexión",
            text: "No fue posible comunicarse con el servidor.",
            confirmButtonColor: "#1D1D1F"
        });
    });
}

function guardarMaestro(form, id) {
    var formData = new FormData(form);
    var boton = $(form).find('button[type="submit"]');
    var contenidoBoton = boton.html();

    formData.append("action", "guardarMaestro");
    formData.append("id", id || 0);

    $.ajax({
        type: "POST",
        url: "php/index.php",
        data: formData,
        dataType: "json",
        processData: false,
        contentType: false,
        beforeSend: function() {
            boton.prop("disabled", true);
            boton.html(`<span class="spinner-border spinner-border-sm me-2" role="status"></span>Guardando...`);
        }
    }).done(function(data) {
        boton.prop("disabled", false);
        boton.html(contenidoBoton);

        if (!data || data.status !== "ok") {

            Swal.fire({
                icon: data && data.status === "warning" ? "warning" : "error",
                title: data && data.status === "warning" ? "Revisa la información" : "No fue posible guardar",
                text: data && data.answer ? data.answer : "Ocurrió un problema al guardar el maestro.",
                confirmButtonColor: "#1D1D1F"
            });

            return;
        }

        entityModal.hide();
        getMaestros(false);
        showToast(data.answer || "Maestro guardado correctamente.");
    }).fail(function(xhr) {
        boton.prop("disabled", false);
        boton.html(contenidoBoton);

        console.error(xhr.responseText);

        Swal.fire({
            icon: "error",
            title: "Error de conexión",
            text: "No fue posible comunicarse con el servidor.",
            confirmButtonColor: "#1D1D1F"
        });
    });
}

function guardarAsignatura(form, id) {
    var formData = new FormData(form);
    var boton = $(form).find('button[type="submit"]');
    var contenidoBoton = boton.html();

    var criterios = [];
    var valido = true;

    $(".subject-criterion-row").each(function() {
    var fila = $(this);

    var idCriterio = Number(fila.find(".subject-criterion-id").val() || 0);
    var nombre = fila.find(".subject-criterion-name").val().trim();
    var afectaPromedio = fila.find(".subject-criterion-affects").is(":checked") ? 1 : 0;
    var tipoCaptura = fila.find(".subject-criterion-type").val() || "numero";
    var usaActividades = fila.find(".subject-criterion-activities").is(":checked") ? 1 : 0;
    var redondearResultado = fila.find(".subject-criterion-round").is(":checked") ? 1 : 0;
    var porcentaje = afectaPromedio
        ? Number(fila.find(".subject-criterion-percentage").val() || 0)
        : 0;

    var color = fila.find(".subject-criterion-color").val() || "#B8943C";

    if (!nombre) {
        valido = false;
        return false;
    }

    if (afectaPromedio && (porcentaje <= 0 || porcentaje > 100)) {
        valido = false;
        return false;
    }

    if (afectaPromedio && tipoCaptura !== "numero") {
        valido = false;
        return false;
    }

    criterios.push({
        id: idCriterio,
        nombre: nombre,
        porcentaje: porcentaje,
        afecta_promedio: afectaPromedio,
        tipo_captura: tipoCaptura,
        usa_actividades: usaActividades,
        redondear_resultado: redondearResultado,
        color: color
    });
});

    if (!valido || !criterios.length) {
        Swal.fire({
            icon: "warning",
            title: "Revisa los criterios",
            text: "Todos los criterios deben tener nombre y un porcentaje válido.",
            confirmButtonColor: "#1D1D1F"
        });
        return;
    }

    var total = criterios.reduce(function(suma, criterio) {
    return criterio.afecta_promedio
        ? suma + Number(criterio.porcentaje)
        : suma;
}, 0);

if (Math.abs(total - 100) > 0.01) {
    Swal.fire({
        icon: "warning",
        title: "Revisa los porcentajes",
        text: total < 100
            ? "A los criterios que afectan el promedio les falta " + (100 - total).toFixed(2) + "%."
            : "Los criterios que afectan el promedio exceden el 100% por " + (total - 100).toFixed(2) + "%.",
        confirmButtonColor: "#1D1D1F"
    });

    return;
}

    formData.append("action", "guardarAsignatura");
    formData.append("id", id || 0);
    formData.append("criterios", JSON.stringify(criterios));

    $.ajax({
        type: "POST",
        url: "php/index.php",
        data: formData,
        dataType: "json",
        processData: false,
        contentType: false,
        beforeSend: function() {
            boton.prop("disabled", true);
            boton.html(`
                <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                Guardando...
            `);
        }
    }).done(function(data) {
        boton.prop("disabled", false);
        boton.html(contenidoBoton);

        if (!data || data.status !== "ok") {
            Swal.fire({
                icon: data && data.status === "warning" ? "warning" : "error",
                title: data && data.status === "warning"
                    ? "Revisa la información"
                    : "No fue posible guardar",
                text: data && data.answer
                    ? data.answer
                    : "Ocurrió un problema al guardar la materia.",
                confirmButtonColor: "#1D1D1F"
            });

            return;
        }

        entityModal.hide();

        getAsignaturas(false);

        showToast(
            data.answer ||
            "Materia guardada correctamente."
        );
    }).fail(function(xhr) {
        boton.prop("disabled", false);
        boton.html(contenidoBoton);

        console.error(xhr.responseText);

        Swal.fire({
            icon: "error",
            title: "Error de conexión",
            text: "No fue posible comunicarse con el servidor.",
            confirmButtonColor: "#1D1D1F"
        });
    });
}

function getPendingGradeContext() {
    return getEvaluationContextKey();
}

function registrarCambioCalificacion(alumnoId, enrollment, criterioId, value) {
    var context = getPendingGradeContext();

    if (!state.pendingGradeChanges[context]) {
        state.pendingGradeChanges[context] = {};
    }

    var key = alumnoId + "-" + criterioId;

    state.pendingGradeChanges[context][key] = {
        alumno_id: Number(alumnoId),
        criterio_id: Number(criterioId),
        calificacion: value
    };

    actualizarEstadoBotonGuardar();
}

function obtenerCalificacionesPendientes() {
    var context = getPendingGradeContext();

    if (!state.pendingGradeChanges[context]) {
        return [];
    }

    return Object.values(
        state.pendingGradeChanges[context]
    );
}

function limpiarCalificacionesPendientes() {
    var context = getPendingGradeContext();

    delete state.pendingGradeChanges[context];

    actualizarEstadoBotonGuardar();
}

function actualizarEstadoBotonGuardar() {
    var boton = document.getElementById("btnSaveGrades");

    if (!boton) return;

    var pendientes = obtenerCalificacionesPendientes();

    boton.disabled = pendientes.length === 0;

    if (pendientes.length > 0) {
        boton.innerHTML = `
            <i class="bi bi-floppy me-2"></i>
            Guardar calificaciones
            <span class="badge bg-light text-dark ms-2">
                ${pendientes.length}
            </span>
        `;
    } else {
        boton.innerHTML = `
            <i class="bi bi-check2 me-2"></i>
            Guardado
        `;
    }
}

function marcarCambiosSinGuardar() {
    const indicator = document.getElementById('saveStatus');

    if (!indicator) return;

    indicator.classList.remove('saving', 'error');

    const text = indicator.querySelector('span:last-child');

    if (text) {
        text.textContent = 'Cambios sin guardar';
    }
}

function guardarCalificacionesPendientes() {
    var calificaciones = obtenerCalificacionesPendientes();

    if (!calificaciones.length) {
        Swal.fire({
            icon: "info",
            title: "Sin cambios",
            text: "No hay calificaciones pendientes por guardar.",
            confirmButtonColor: "#1D1D1F"
        });

        return;
    }

    var boton = $("#btnSaveGrades");
    var contenidoAnterior = boton.html();

    boton
        .prop("disabled", true)
        .html(`
            <span class="spinner-border spinner-border-sm me-2"></span>
            Guardando...
        `);

    guardarCalificaciones(
        calificaciones
    ).done(function(data) {
        if (!data || data.status !== "ok") {
            boton
                .prop("disabled", false)
                .html(contenidoAnterior);

            return;
        }

        limpiarCalificacionesPendientes();

        boton
            .prop("disabled", true)
            .html(`
                <i class="bi bi-check2 me-2"></i>
                Guardado
            `);

        showToast(
            "Calificaciones guardadas correctamente."
        );
    }).fail(function() {
        boton
            .prop("disabled", false)
            .html(contenidoAnterior);
    });
}

function guardarCalificaciones(calificaciones, mensajeExito = "") {
    if (!Array.isArray(calificaciones) || !calificaciones.length) {
        return $.Deferred().resolve().promise();
    }

    var asignaturaId = Number($("#evaluationSubject").val() || 0);
    var periodo = String($("#evaluationPeriod").val() || "").trim();
    var ciclo = String($("#evaluationCycle").val() || "").trim();

    if (!asignaturaId || !periodo || !ciclo) {
        Swal.fire({
            icon: "warning",
            title: "Evaluación incompleta",
            text: "Selecciona materia, periodo y ciclo escolar.",
            confirmButtonColor: "#1D1D1F"
        });

        return $.Deferred().reject().promise();
    }

    var grupo = String($("#evaluationGroup").val() || "").trim();

var asignacion = state.assignments.find(function(item) {
    return Number(item.asignatura_id) === asignaturaId &&
        item.group === grupo &&
        item.ciclo_escolar === ciclo;
});

if (!asignacion) {
    Swal.fire({
        icon: "warning",
        title: "Asignación no válida",
        text: "La materia seleccionada no está asignada a este grupo.",
        confirmButtonColor: "#1D1D1F"
    });

    return $.Deferred().reject().promise();
}

    var formData = new FormData();

    formData.append("action", "guardarCalificaciones");
    formData.append("asignatura_id", asignaturaId);
    formData.append("asignacion_id", asignacion.id);
    //formData.append("maestro_id", MAESTRO_ID_ACTUAL);
    formData.append("maestro_id", asignacion.maestro_id);
    formData.append("periodo", periodo);
    formData.append("ciclo_escolar", ciclo);
    formData.append("calificaciones", JSON.stringify(calificaciones));

    return $.ajax({
        type: "POST",
        url: "php/index.php",
        data: formData,
        dataType: "json",
        processData: false,
        contentType: false
    }).done(function(data) {
        if (!data || data.status !== "ok") {

            Swal.fire({
                icon: data && data.status === "warning" ? "warning" : "error",
                title: "No fue posible guardar",
                text: data && data.answer ? data.answer : "No fue posible guardar las calificaciones.",
                confirmButtonColor: "#1D1D1F"
            });

            getCalificaciones(null, false);
            return;
        }

        syncCurrentEvaluationGrades();

        if (mensajeExito) {
            showToast(mensajeExito);
        }
    }).fail(function(xhr) {
        console.error(xhr.responseText);

        Swal.fire({
            icon: "error",
            title: "Error de conexión",
            text: "No fue posible guardar las calificaciones.",
            confirmButtonColor: "#1D1D1F"
        });

        getCalificaciones(null, false);
    });
}

async function confirmDelete(entity, id) {
    var registro = getEntityCollection(entity).find(
        (item) => item.id === id
    );

    var titulo = entity === "student"
    ? "¿Eliminar alumno?"
    : entity === "teacher"
        ? "¿Eliminar maestro?"
        : entity === "subject"
            ? "¿Eliminar materia?"
            : "¿Eliminar registro?";

var texto = (entity === "student" || entity === "teacher" || entity === "subject") && registro
    ? `Se eliminará ${registro.name || registro.nombre}. Esta acción dejará de mostrarlo en el sistema.`
    : "Esta acción no se puede deshacer.";

    const result = await Swal.fire({
        title: titulo,
        text: texto,
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Sí, eliminar",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#dc3545",
        cancelButtonColor: "#6E6E73",
        reverseButtons: true
    });

    if (!result.isConfirmed) return;

    if (entity === "student") {
        eliminarAlumno(id);
        return;
    }

    if (entity === "teacher") {
        eliminarMaestro(id);
        return;
    }

    if (entity === "subject") {
        eliminarAsignatura(id);
        return;
    }

    if (entity === "schedule") {
        eliminarHorario(id);
        return;
    }

    const collection = getEntityCollection(entity);
    const index = collection.findIndex(
        (item) => item.id === id
    );

    if (index === -1) return;

    const [deleted] = collection.splice(index, 1);

    if (entity === "criterion") {
        Object.values(state.grades).forEach(
            (grades) => delete grades[id]
        );
    }

    renderAll();
    showToast("Registro eliminado.");
}

function eliminarAlumno(id) {
    var formData = new FormData();

    formData.append("action", "eliminarAlumno");
    formData.append("id", id);

    $.ajax({
        type: "POST",
        url: "php/index.php",
        data: formData,
        dataType: "json",
        processData: false,
        contentType: false,
        beforeSend: function() {
            Swal.fire({
                title: "Eliminando alumno",
                text: "Espera un momento...",
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: function() {
                    Swal.showLoading();
                }
            });
        }
    }).done(function(data) {
        Swal.close();

        if (!data || data.status !== "ok") {
            Swal.fire({
                icon: data && data.status === "warning"
                    ? "warning"
                    : "error",
                title: "No fue posible eliminar",
                text: data && data.answer
                    ? data.answer
                    : "Ocurrió un problema al eliminar el alumno.",
                confirmButtonColor: "#1D1D1F"
            });

            return;
        }

        getAlumnos(false);

        showToast(
            data.answer ||
            "Alumno eliminado correctamente."
        );

    }).fail(function(xhr) {
        Swal.close();

        console.error(xhr.responseText);

        Swal.fire({
            icon: "error",
            title: "Error",
            text: "No fue posible comunicarse con el servidor.",
            confirmButtonColor: "#1D1D1F"
        });
    });
}

function eliminarMaestro(id) {
    var formData = new FormData();

    formData.append("action", "eliminarMaestro");
    formData.append("id", id);

    $.ajax({
        type: "POST",
        url: "php/index.php",
        data: formData,
        dataType: "json",
        processData: false,
        contentType: false,
        beforeSend: function() {
            Swal.fire({
                title: "Eliminando maestro",
                text: "Espera un momento...",
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: function() {
                    Swal.showLoading();
                }
            });
        }
    }).done(function(data) {
        Swal.close();

        if (!data || data.status !== "ok") {
            Swal.fire({
                icon: data && data.status === "warning" ? "warning" : "error",
                title: "No fue posible eliminar",
                text: data && data.answer ? data.answer : "Ocurrió un problema al eliminar el maestro.",
                confirmButtonColor: "#1D1D1F"
            });
            return;
        }

        getMaestros(false);
        showToast(data.answer || "Maestro eliminado correctamente.");
    }).fail(function(xhr) {
        Swal.close();
        console.error(xhr.responseText);

        Swal.fire({
            icon: "error",
            title: "Error",
            text: "No fue posible comunicarse con el servidor.",
            confirmButtonColor: "#1D1D1F"
        });
    });
}

function eliminarAsignatura(id) {
    var formData = new FormData();

    formData.append("action", "eliminarAsignatura");
    formData.append("id", id);

    $.ajax({
        type: "POST",
        url: "php/index.php",
        data: formData,
        dataType: "json",
        processData: false,
        contentType: false,
        beforeSend: function() {
            Swal.fire({
                title: "Eliminando materia",
                text: "Espera un momento...",
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: function() {
                    Swal.showLoading();
                }
            });
        }
    }).done(function(data) {
        Swal.close();

        if (!data || data.status !== "ok") {
            Swal.fire({
                icon: data && data.status === "warning" ? "warning" : "error",
                title: "No fue posible eliminar",
                text: data && data.answer
                    ? data.answer
                    : "Ocurrió un problema al eliminar la materia.",
                confirmButtonColor: "#1D1D1F"
            });

            return;
        }

        getAsignaturas(false);

        showToast(
            data.answer ||
            "Materia eliminada correctamente."
        );
    }).fail(function(xhr) {
        Swal.close();

        console.error(xhr.responseText);

        Swal.fire({
            icon: "error",
            title: "Error",
            text: "No fue posible comunicarse con el servidor.",
            confirmButtonColor: "#1D1D1F"
        });
    });
}

function getEntityCollection(entity) {
    const map = {
        subject: state.subjects,
        teacher: state.teachers,
        student: state.students,
        schedule: state.schedule,
        criterion: state.criteria
    };
    return map[entity] || [];
}

function getTeacher(id) {
    return state.teachers.find((teacher) => teacher.id === Number(id));
}

function downloadGradeTemplate() {
    const group = document.getElementById('evaluationGroup').value;
    const students = state.students.filter((student) => student.group === group);
    const headers = ['Matrícula', 'Alumno', ...state.criteria.map((criterion) => criterion.name)];
    const rows = students.map((student) => [student.enrollment, student.name, ...state.criteria.map(() => '')]);
    const csv = [headers, ...rows].map((row) => row.map(csvValue).join(',')).join('\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = `plantilla_calificaciones_${slug(group)}.csv`;
    anchor.click();
    URL.revokeObjectURL(url);
}
function processGradeFile(file) {

    if (!file) {
        Swal.fire(
            "Archivo requerido",
            "Selecciona un archivo para continuar.",
            "warning"
        );
        return;
    }

    if (file.size > 5 * 1024 * 1024) {
        Swal.fire(
            "Archivo demasiado grande",
            "El tamaño máximo permitido es de 5 MB.",
            "warning"
        );
        return;
    }

    var extension = file.name
        .split(".")
        .pop()
        .toLowerCase();

    var extensionesPermitidas = [
        "csv",
        "xlsx",
        "xls",
        "pdf",
        "jpg",
        "jpeg",
        "png",
        "webp"
    ];

    if (extensionesPermitidas.indexOf(extension) === -1) {
        Swal.fire(
            "Formato no compatible",
            "Selecciona un archivo CSV, XLSX, XLS, PDF, JPG, PNG o WEBP.",
            "warning"
        );
        return;
    }

    var formData = new FormData();

    formData.append("action", "analizarArchivo");
    formData.append("file", file);


    var subjectSelect = document.getElementById("evaluationSubject");
var subjectName = "";

if (
    subjectSelect &&
    subjectSelect.selectedIndex >= 0
) {
    subjectName =
        subjectSelect.options[
            subjectSelect.selectedIndex
        ].text;
}

var group = $("#evaluationGroup").val() || "";
var period = $("#evaluationPeriod").val() || "";
var cycle = $("#evaluationCycle").val() || "";

var studentsGroup = state.students
    .filter(function (student) {
        return student.group === group;
    })
    .map(function (student) {
        return {
            id: student.id,
            matricula: student.enrollment,
            nombre: student.name
        };
    });

var criteriaCurrent = state.criteria.map(function (criterion) {
    return {
        id: criterion.id,
        nombre: criterion.name,
        porcentaje: criterion.weight
    };
});

formData.append("subject", subjectName);
formData.append("group", group);
formData.append("period", period);
formData.append("cycle", cycle);
formData.append("students", JSON.stringify(studentsGroup));
formData.append("criteria", JSON.stringify(criteriaCurrent));

    Swal.fire({
        title: "Analizando archivo",
        html: "Estamos revisando la estructura y las calificaciones.",
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: function () {
            Swal.showLoading();
        }
    });

    $.ajax({
        type: "POST",
        url: "php/index.php",
        data: formData,
        dataType: "json",
        processData: false,
        contentType: false
    })
    .done(function (data) {

        Swal.close();

        console.log("analizarArchivo:", data);

        if (
            data.status === "ok" &&
            Array.isArray(data.rows)
        ) {
            validateImportedRows(
                data.rows,
                data.file_name || file.name
            );

            return;
        }

        Swal.fire(
            "No se pudo analizar el archivo",
            data.answer || "El servidor no regresó información válida.",
            data.status === "warning" ? "warning" : "error"
        );
    })
    .fail(function (xhr) {

        console.error("Error analizarArchivo:", xhr.responseText);

        Swal.fire(
            "Error de conexión",
            "No fue posible analizar el archivo. Revisa la respuesta del servidor.",
            "error"
        );
    });
}

function validateImportedRows(rows, fileName) {
    const cleanRows = rows.filter((row) => Array.isArray(row) && row.some((cell) => String(cell).trim() !== ''));
    if (cleanRows.length < 2) throw new Error('El archivo no contiene filas de calificaciones.');

    const headers = cleanRows[0].map((header) => String(header).trim());
    const normalizedHeaders = headers.map(normalizeHeader);
    const enrollmentIndex = normalizedHeaders.findIndex((header) => ['matricula', 'matrícula'].includes(header));

    if (enrollmentIndex === -1) throw new Error('No se encontró la columna “Matrícula”.');

    const criterionColumns = state.criteria.map((criterion) => ({
        criterion,
        index: normalizedHeaders.findIndex((header) => header === normalizeHeader(criterion.name))
    }));

    const missingCriteria = criterionColumns.filter((column) => column.index === -1).map((column) => column.criterion.name);
    const group = document.getElementById('evaluationGroup').value;
    const groupStudents = state.students.filter((student) => student.group === group);
    const studentMap = new Map(groupStudents.map((student) => [normalize(student.enrollment), student]));

    const validated = cleanRows.slice(1).map((row, rowIndex) => {
        const enrollment = String(row[enrollmentIndex] ?? '').trim();
        const student = studentMap.get(normalize(enrollment));
        const issues = [];
        const values = {};

        if (!enrollment) issues.push('Matrícula vacía');
        if (enrollment && !student) issues.push('Matrícula no encontrada en el grupo');

        criterionColumns.forEach(({ criterion, index }) => {
            if (index === -1) return;
            const raw = String(row[index] ?? '').trim();
            if (raw === '') return;
            const value = Number(raw.replace(',', '.'));
            if (Number.isNaN(value) || value < 0 || value > 10) issues.push(`${criterion.name}: debe estar entre 0 y 10`);
            else values[criterion.id] = value;
        });

        return {
            rowNumber: rowIndex + 2,
            enrollment,
            student,
            values,
            issues,
            valid: issues.length === 0
        };
    });

    state.importedRows = validated;
    renderUploadPreview({ fileName, headers, missingCriteria, criterionColumns, validated });
}

function renderUploadPreview({ fileName, missingCriteria, validated }) {
    const validRows = validated.filter((row) => row.valid);
    const errorRows = validated.filter((row) => !row.valid);

    document.getElementById('uploadResult').classList.remove('d-none');
    document.getElementById('uploadFileName').textContent = fileName;
    document.getElementById('uploadValidRows').textContent = validRows.length;
    document.getElementById('uploadErrorRows').textContent = errorRows.length;

    const status = document.getElementById('uploadStatusText');
    const importButton = document.getElementById('btnImportGrades');

    if (missingCriteria.length) {
        status.textContent = 'Faltan columnas';
        status.className = 'fw-bold text-danger';
        importButton.disabled = true;
    } else if (!validRows.length) {
        status.textContent = 'Sin filas válidas';
        status.className = 'fw-bold text-danger';
        importButton.disabled = true;
    } else if (errorRows.length) {
        status.textContent = 'Revisión necesaria';
        status.className = 'fw-bold text-warning';
        importButton.disabled = false;
    } else {
        status.textContent = 'Listo para importar';
        status.className = 'fw-bold text-success';
        importButton.disabled = false;
    }

    document.getElementById('uploadIssues').innerHTML = missingCriteria.length
        ? `<div class="alert alert-danger rounded-4"><strong>Columnas faltantes:</strong> ${missingCriteria.map(escapeHtml).join(', ')}.</div>`
        : errorRows.length
            ? `<div class="alert alert-warning rounded-4">Las filas con errores no se importarán. Puedes corregir el archivo o continuar únicamente con las filas válidas.</div>`
            : '<div class="alert alert-success rounded-4">El archivo pasó todas las validaciones.</div>';

    document.getElementById('uploadPreviewHead').innerHTML = `
        <tr>
            <th>Fila</th>
            <th>Matrícula</th>
            <th>Alumno</th>
            ${state.criteria.map((criterion) => `<th class="text-center">${escapeHtml(criterion.name)}</th>`).join('')}
            <th>Validación</th>
        </tr>`;

    document.getElementById('uploadPreviewBody').innerHTML = validated.slice(0, 50).map((row) => `
        <tr class="${row.valid ? '' : 'invalid-row'}">
            <td>${row.rowNumber}</td>
            <td>${escapeHtml(row.enrollment || '—')}</td>
            <td>${escapeHtml(row.student?.name || 'No encontrado')}</td>
            ${state.criteria.map((criterion) => `<td class="text-center">${row.values[criterion.id] ?? '—'}</td>`).join('')}
            <td>${row.valid ? '<span class="badge text-bg-success">Válida</span>' : `<span class="text-danger small">${row.issues.map(escapeHtml).join(' · ')}</span>`}</td>
        </tr>`).join('');
}

function importGrades() {
    const validRows = state.importedRows.filter((row) => row.valid);

    if (!validRows.length) return;

    let cambios = 0;

    validRows.forEach((row) => {
        if (!row.student) return;

        state.grades[row.enrollment] ??= {};

        Object.entries(row.values).forEach(([criterioId, value]) => {
            criterioId = Number(criterioId);
            value = Number(value);

            state.grades[row.enrollment][criterioId] = value;

            registrarCambioCalificacion(
                row.student.id,
                row.enrollment,
                criterioId,
                value
            );

            cambios++;
        });
    });

    if (!cambios) return;

    syncCurrentEvaluationGrades();
    renderGrades();
    marcarCambiosSinGuardar();

    bootstrap.Modal
        .getOrCreateInstance(document.getElementById('bulkUploadModal'))
        .hide();

    Swal.fire({
        icon: 'success',
        title: 'Calificaciones cargadas',
        text: 'Los datos fueron agregados a la captura. Presiona “Guardar calificaciones” para registrarlos.',
        confirmButtonColor: '#1D1D1F'
    });
}

function resetBulkUpload() {
    state.importedRows = [];
    document.getElementById('bulkFileInput').value = '';
    document.getElementById('uploadResult').classList.add('d-none');
    document.getElementById('btnImportGrades').disabled = true;
    document.getElementById('uploadPreviewHead').innerHTML = '';
    document.getElementById('uploadPreviewBody').innerHTML = '';
    document.getElementById('uploadIssues').innerHTML = '';
}

function parseCsv(text) {
    const rows = [];
    let row = [];
    let field = '';
    let inQuotes = false;

    for (let i = 0; i < text.length; i += 1) {
        const char = text[i];
        const next = text[i + 1];

        if (char === '"' && inQuotes && next === '"') {
            field += '"';
            i += 1;
        } else if (char === '"') {
            inQuotes = !inQuotes;
        } else if (char === ',' && !inQuotes) {
            row.push(field);
            field = '';
        } else if ((char === '\n' || char === '\r') && !inQuotes) {
            if (char === '\r' && next === '\n') i += 1;
            row.push(field);
            rows.push(row);
            row = [];
            field = '';
        } else {
            field += char;
        }
    }

    if (field.length || row.length) {
        row.push(field);
        rows.push(row);
    }

    return rows;
}

function showToast(message) {
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: message,
        showConfirmButton: false,
        timer: 1900,
        timerProgressBar: true
    });
}

function actionButtons(entity, id) {
    return `
        <button class="action-btn" type="button" data-action="edit" data-entity="${entity}" data-id="${id}" aria-label="Editar"><i class="bi bi-pencil"></i></button>
        <button class="action-btn action-delete" type="button" data-action="delete" data-entity="${entity}" data-id="${id}" aria-label="Eliminar"><i class="bi bi-trash"></i></button>`;
}

function inputField(name, label, value = '', placeholder = '', type = 'text', extra = '') {
    return `
        <div class="mb-3">
            <label class="form-label fw-semibold" for="field-${name}">${label}</label>
            <input id="field-${name}" class="form-control form-control-lg" type="${type}" name="${name}" value="${escapeHtml(value ?? '')}" placeholder="${escapeHtml(placeholder)}" ${extra} required>
        </div>`;
}

function selectField(name, label, options, selected = '') {
    const normalized = options.map((option) => typeof option === 'object' ? option : { value: option, label: option });
    return `
        <div class="mb-3">
            <label class="form-label fw-semibold" for="field-${name}">${label}</label>
            <select id="field-${name}" class="form-select form-select-lg" name="${name}" required>
                <option value="" disabled ${selected === '' || selected === undefined ? 'selected' : ''}>Selecciona una opción</option>
                ${normalized.map((option) => `<option value="${escapeHtml(option.value)}" ${String(option.value) === String(selected) ? 'selected' : ''}>${escapeHtml(option.label)}</option>`).join('')}
            </select>
        </div>`;
}

function optionalInputField(
    name,
    label,
    value = '',
    placeholder = '',
    type = 'text',
    extra = ''
) {
    return `
        <div class="mb-3">
            <label class="form-label fw-semibold" for="field-${name}">
                ${label}
            </label>

            <input
                id="field-${name}"
                class="form-control form-control-lg"
                type="${type}"
                name="${name}"
                value="${escapeHtml(value ?? '')}"
                placeholder="${escapeHtml(placeholder)}"
                ${extra}>
        </div>
    `;
}

function textareaField(
    name,
    label,
    value = '',
    placeholder = ''
) {
    return `
        <div class="mb-3">
            <label class="form-label fw-semibold" for="field-${name}">
                ${label}
            </label>

            <textarea
                id="field-${name}"
                class="form-control"
                name="${name}"
                rows="3"
                placeholder="${escapeHtml(placeholder)}">${escapeHtml(value ?? '')}</textarea>
        </div>
    `;
}

function emptyTableRow(columns, text) {
    return `<tr><td colspan="${columns}" class="text-center text-muted py-5"><i class="bi bi-inbox fs-3 d-block mb-2"></i>${escapeHtml(text)}</td></tr>`;
}

function calculateTeacherSubjects(teacherId) {
    return state.subjects.filter((subject) => subject.teacherId === teacherId).length;
}

function averageClass(value) {
    if (value === null) return 'bg-light text-muted';
    if (value >= 9) return 'grade-high';
    if (value >= 7) return 'grade-mid';
    return 'grade-low';
}

function formatAverage(value) {
    if (value === null || value === undefined || value === '') return '—';
    return String(Math.round(Number(value)));
}

function entityLabel(entity) {
    const labels = {
        subject: 'materia',
        teacher: 'maestro',
        student: 'alumno',
        schedule: 'asignación',
        criterion: 'criterio'
    };
    return labels[entity] || 'registro';
}

function nextId(collection) {
    return collection.length ? Math.max(...collection.map((item) => item.id)) + 1 : 1;
}

function initials(name) {
    return String(name)
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() || '')
        .join('');
}

function normalize(value) {
    return String(value ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();
}

function normalizeHeader(value) {
    return normalize(value).replace(/\s+/g, ' ');
}

function slug(value) {
    return normalize(value).replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
}

function plural(count, singular, pluralText) {
    return count === 1 ? singular : pluralText;
}

function capitalize(value) {
    return value.charAt(0).toUpperCase() + value.slice(1);
}

function setText(id, text) {
    const element = document.getElementById(id);
    if (element) element.textContent = text;
}

function csvValue(value) {
    const text = String(value ?? '');
    return `"${text.replace(/"/g, '""')}"`;
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

let evaluationSignatureCanvas = null;
let evaluationSignatureCtx = null;
let evaluationSignatureDrawing = false;
let evaluationSignatureEmpty = true;
let tipoPdfPendiente = 'periodo';

function abrirModalPdfEvaluacion(tipo) {
    tipoPdfPendiente = tipo;

    if (tipo === 'final') {
        $('#pdfCurrentPeriod').text('Resultado final del ciclo escolar');
    } else {
        $('#pdfCurrentPeriod').text($('#evaluationPeriod').val() || 'Periodo actual');
    }

    const modalElement = document.getElementById('evaluationPdfModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);

    modalElement.addEventListener('shown.bs.modal', function() {
        inicializarFirmaEvaluacion();
    }, { once: true });

    modal.show();
}

function inicializarFirmaEvaluacion() {
    evaluationSignatureCanvas = document.getElementById('evaluationSignatureCanvas');
    if (!evaluationSignatureCanvas) return;

    const rect = evaluationSignatureCanvas.getBoundingClientRect();
    evaluationSignatureCanvas.width = Math.max(600, Math.round(rect.width * 2));
    evaluationSignatureCanvas.height = 260;

    evaluationSignatureCtx = evaluationSignatureCanvas.getContext('2d');
    evaluationSignatureCtx.fillStyle = '#FFFFFF';
    evaluationSignatureCtx.fillRect(0, 0, evaluationSignatureCanvas.width, evaluationSignatureCanvas.height);

    evaluationSignatureCtx.strokeStyle = '#1D1D1F';
    evaluationSignatureCtx.lineWidth = 4;
    evaluationSignatureCtx.lineCap = 'round';
    evaluationSignatureCtx.lineJoin = 'round';

    evaluationSignatureEmpty = true;
}

function obtenerPosicionFirma(event) {
    const rect = evaluationSignatureCanvas.getBoundingClientRect();
    const punto = event.touches ? event.touches[0] : event;

    return {
        x: (punto.clientX - rect.left) * (evaluationSignatureCanvas.width / rect.width),
        y: (punto.clientY - rect.top) * (evaluationSignatureCanvas.height / rect.height)
    };
}

function iniciarFirmaEvaluacion(event) {
    event.preventDefault();

    const pos = obtenerPosicionFirma(event);

    evaluationSignatureDrawing = true;
    evaluationSignatureEmpty = false;

    evaluationSignatureCtx.beginPath();
    evaluationSignatureCtx.moveTo(pos.x, pos.y);
}

function dibujarFirmaEvaluacion(event) {
    if (!evaluationSignatureDrawing) return;

    event.preventDefault();

    const pos = obtenerPosicionFirma(event);

    evaluationSignatureCtx.lineTo(pos.x, pos.y);
    evaluationSignatureCtx.stroke();
}

function finalizarFirmaEvaluacion() {
    evaluationSignatureDrawing = false;

    if (evaluationSignatureCtx) {
        evaluationSignatureCtx.closePath();
    }
}

function limpiarFirmaEvaluacion() {
    if (!evaluationSignatureCanvas || !evaluationSignatureCtx) return;

    evaluationSignatureCtx.fillStyle = '#FFFFFF';
    evaluationSignatureCtx.fillRect(0, 0, evaluationSignatureCanvas.width, evaluationSignatureCanvas.height);

    evaluationSignatureEmpty = true;
}

function bindEvaluationPdf() {
    const canvas = document.getElementById('evaluationSignatureCanvas');
    if (!canvas) return;

    canvas.addEventListener('mousedown', iniciarFirmaEvaluacion);
    canvas.addEventListener('mousemove', dibujarFirmaEvaluacion);
    window.addEventListener('mouseup', finalizarFirmaEvaluacion);

    canvas.addEventListener('touchstart', iniciarFirmaEvaluacion, { passive: false });
    canvas.addEventListener('touchmove', dibujarFirmaEvaluacion, { passive: false });
    canvas.addEventListener('touchend', finalizarFirmaEvaluacion);

    $('#btnClearEvaluationSignature').on('click', limpiarFirmaEvaluacion);

    $('#btnConfirmEvaluationPdf').on('click', function() {
        const firma = evaluationSignatureEmpty
            ? ''
            : evaluationSignatureCanvas.toDataURL('image/png');

        const datosPdf = obtenerDatosPdfEvaluacion(tipoPdfPendiente, firma);

        bootstrap.Modal.getOrCreateInstance(
            document.getElementById('evaluationPdfModal')
        ).hide();

        generarPdfEvaluacion(datosPdf);
    });
}

function obtenerDatosPdfEvaluacion(tipoReporte, firma) {
    syncCurrentEvaluationGrades();

    const subjectId = Number($('#evaluationSubject').val());
    const groupValue = String($('#evaluationGroup').val() || '').trim();
    const periodo = String($('#evaluationPeriod').val() || '').trim();
    const ciclo = String($('#evaluationCycle').val() || '').trim();

    const subject = state.subjects.find((item) => item.id === subjectId);
    const maestroId = 2;
    
    const teacher = state.teachers.find(function(item) {
        return Number(item.id) === maestroId;
    });
    
    const students = state.students.filter((student) => {
        return student.group === groupValue;
    });

    const groupParts = groupValue.split(/\s+/);
    const grado = groupParts.length ? groupParts[0] : '';
    const grupo = groupParts.length > 1 ? groupParts.slice(1).join(' ') : '';

    const hoy = new Date();
    const fecha = String(hoy.getDate()).padStart(2, '0') + '/' +
        String(hoy.getMonth() + 1).padStart(2, '0') + '/' +
        hoy.getFullYear();

    const datos = {
    tipo_reporte: tipoReporte,
    maestro_id: MAESTRO_ID_ACTUAL,

    escuela: {
        nombre: 'INSTITUTO ESTATAL DE EDUCACIÓN PÚBLICA DE OAXACA',
        tipo: 'ESC. SECUNDARIA GENERAL "SIGLO XXI"',
        clave: 'ES-372-180',
        cct: '20DES0193I'
    },

    profesor: teacher ? teacher.name : '',
    asignatura: subject ? subject.name : '',
    periodo: periodo,
    grado: grado,
    grupo: grupo,
    ciclo: ciclo,
    fecha: fecha
};

    if (tipoReporte === 'periodo') {

    const evaluationKey =
        getEvaluationContextKey(periodo);

    const gradesPeriod =
        state.periodGrades[evaluationKey] ||
        state.grades;


    datos.criterios =
        state.criteria.map(function(criterion) {

            return {
                id: Number(criterion.id),

                nombre:
                    criterion.name,

                porcentaje:
                    Number(criterion.weight || 0),

                afecta_promedio:
                    Number(
                        criterion.afecta_promedio
                    ),

                tipo_captura:
                    criterion.tipo_captura ||
                    "numero",

                usa_actividades: Number(criterion.usa_actividades),
                redondear_resultado: Number(criterion.redondear_resultado || 0)
            };
        });


    datos.alumnos =
        students.map(function(student) {

            const gradesStudent =
                gradesPeriod[
                    student.enrollment
                ] || {};


            var calificaciones = {};


            state.criteria.forEach(
                function(criterion) {

                    var value =
                        gradesStudent[
                            criterion.id
                        ];


                    if (
                        value === null ||
                        value === undefined ||
                        value === ''
                    ) {

                        calificaciones[
                            criterion.id
                        ] = '';

                        return;
                    }


                    /*
                     * Conducta debe conservarse
                     * como B / R / M.
                     */
                    if (
                        criterion.tipo_captura ===
                        "conducta"
                    ) {

                        calificaciones[
                            criterion.id
                        ] =
                            String(value)
                                .trim()
                                .toUpperCase();

                        return;
                    }


                    calificaciones[
                        criterion.id
                    ] =
                        Number(value);
                }
            );


            return {
                id: Number(student.id),

                nombre:
                    student.name,

                matricula:
                    student.enrollment,

                calificaciones:
                    calificaciones
            };
        });


    /*
     * Aquí cargaremos después
     * Examen 1, Examen 2, etc.
     */
    datos.detalle_actividades = {};


    return datos;
}

    datos.periodos = state.evaluationPeriods.slice();

    datos.alumnos_final = students.map((student) => {
        const promedios = state.evaluationPeriods.map((nombrePeriodo) => {
            const promedio = calculatePeriodStudentAverage(student.enrollment, nombrePeriodo);

            return promedio === null
                ? null
                : Number(promedio.toFixed(1));
        });

        const completos = promedios.filter((value) => value !== null);

        let promedioFinal = null;

        if (completos.length === state.evaluationPeriods.length) {
            promedioFinal = completos.reduce((sum, value) => sum + value, 0) / completos.length;
        }

        return {
            nombre: student.name,
            matricula: student.enrollment,
            periodos: promedios,
            promedio_final: promedioFinal === null
                ? null
                : Number(promedioFinal.toFixed(1))
        };
    });

    return datos;
}

async function cargarDetalleExamenPdf(
    datosPdf
) {

    var asignaturaId =
        Number(
            $("#evaluationSubject").val()
        );

    var periodo =
        String(
            $("#evaluationPeriod").val() ||
            ""
        ).trim();

    var ciclo =
        String(
            $("#evaluationCycle").val() ||
            ""
        ).trim();

    var grupoCompleto =
        String(
            $("#evaluationGroup").val() ||
            ""
        ).trim();


    var criteriosExamen =
        state.criteria.filter(
            function(criterion) {

                return (
                    Number(
                        criterion.usa_actividades
                    ) === 1 &&
                    String(
                        criterion.name || ""
                    )
                        .toLowerCase()
                        .includes("examen")
                );
            }
        );


    for (
        var i = 0;
        i < criteriosExamen.length;
        i++
    ) {

        var criterio =
            criteriosExamen[i];


        var data =
            await $.ajax({
                type: "POST",

                url:
                    "php/index.php",

                dataType:
                    "json",

                data: {
                    action:
                        "getActividadesEvaluacion",

                    asignatura_id:
                        asignaturaId,

                    criterio_id:
                        criterio.id,

                    periodo:
                        periodo,

                    ciclo_escolar:
                        ciclo,

                    grupo:
                        grupoCompleto,

                    incluir_calificaciones_grupo:
                        1
                }
            });


        if (
            !data ||
            data.status !== "ok"
        ) {

            throw new Error(
                data &&
                data.answer
                    ? data.answer
                    : "No fue posible obtener el detalle de los exámenes."
            );
        }


        var actividades = {};

        var calificaciones = {};


        (data.results || [])
            .forEach(function(row) {

                var actividadId =
                    Number(row.id);


                if (
                    !actividades[
                        actividadId
                    ]
                ) {

                    actividades[
                        actividadId
                    ] = {
                        id:
                            actividadId,

                        nombre:
                            row.nombre,

                        tipo:
                            row.tipo,

                        orden:
                            Number(
                                row.orden
                            )
                    };
                }


                if (
                    row.alumno_id !== null &&
                    row.alumno_id !== undefined
                ) {

                    var alumnoId =
                        String(
                            row.alumno_id
                        );


                    calificaciones[
                        alumnoId
                    ] ??= {};


                    calificaciones[
                        alumnoId
                    ][
                        actividadId
                    ] =
                        row.calificacion === null
                            ? null
                            : Number(
                                row.calificacion
                            );
                }
            });


        datosPdf.detalle_actividades[
            criterio.id
        ] = {

            actividades:
                Object
                    .values(
                        actividades
                    )
                    .sort(
                        function(a, b) {
                            return (
                                Number(a.orden) -
                                Number(b.orden)
                            );
                        }
                    ),

            calificaciones:
                calificaciones
        };
    }


    return datosPdf;
}

function generarPdfAprovechamientoEscolar() {
    if (obtenerCalificacionesPendientes().length) {
        Swal.fire({
            icon: 'warning',
            title: 'Hay cambios sin guardar',
            text: 'Guarda las calificaciones antes de generar el aprovechamiento escolar.',
            confirmButtonColor: '#1D1D1F'
        });

        return;
    }

    var ciclo = String(
        $("#evaluationCycle").val() || ""
    ).trim();

    if (!ciclo) {
        Swal.fire({
            icon: "warning",
            title: "Ciclo escolar requerido",
            text: "Selecciona o captura el ciclo escolar antes de generar el reporte.",
            confirmButtonColor: "#1D1D1F"
        });

        return;
    }

    var formData = new FormData();

    formData.append(
        "action",
        "getAprovechamientoEscolar"
    );

    formData.append(
        "ciclo_escolar",
        ciclo
    );


    formData.append("maestro_id", MAESTRO_ID_ACTUAL);

    formData.append(
        "periodos",
        JSON.stringify(
            state.evaluationPeriods
        )
    );

    $.ajax({
        type: "POST",
        url: "php/index.php",
        data: formData,
        dataType: "json",
        processData: false,
        contentType: false,

        beforeSend: function() {
            Swal.fire({
                title: "Preparando reporte",
                text: "Calculando el aprovechamiento escolar...",
                allowOutsideClick: false,
                allowEscapeKey: false,

                didOpen: function() {
                    Swal.showLoading();
                }
            });
        }

    }).done(function(data) {
        if (
            !data ||
            data.status !== "ok"
        ) {
            Swal.fire({
                icon:
                    data &&
                    data.status === "warning"
                        ? "warning"
                        : "error",

                title:
                    "No fue posible generar el reporte",

                text:
                    data &&
                    data.answer
                        ? data.answer
                        : "No fue posible consultar el aprovechamiento escolar.",

                confirmButtonColor:
                    "#1D1D1F"
            });

            return;
        }

        if (
            !data.pdf_data ||
            !Array.isArray(
                data.pdf_data.resultados
            ) ||
            !data.pdf_data.resultados.length
        ) {
            Swal.fire({
                icon: "info",
                title: "Sin información",
                text: "Todavía no existen datos suficientes para generar el aprovechamiento escolar.",
                confirmButtonColor: "#1D1D1F"
            });

            return;
        }

        var datosPdf = data.pdf_data;

        datosPdf.coordinador = "";
        datosPdf.subdirector = "PROFR. JOSÉ DE JESÚS FLORES RODRÍGUEZ";
        
        datosPdf.director = "PROFR. FORTINO BAUTISTA CRUZ";

        generarPdfEvaluacion(
            datosPdf
        );

    }).fail(function(xhr) {
        console.error(
            xhr.responseText
        );

        Swal.fire({
            icon: "error",
            title: "Error de conexión",
            text: "No fue posible consultar el aprovechamiento escolar.",
            confirmButtonColor: "#1D1D1F"
        });
    });
}

function generarPdfEvaluacion(datosPdf) {
    var formData = new FormData();

    formData.append("action", "generarPdfEvaluacion");
    formData.append("data", JSON.stringify(datosPdf));

    $.ajax({
        url: "php/index.php",
        type: "POST",
        data: formData,
        processData: false,
        contentType: false,
        dataType: "json",
        beforeSend: function() {
            Swal.fire({
                title: "Generando reporte",
                text: "Preparando el registro de evaluaciones...",
                allowOutsideClick: false,
                didOpen: function() {
                    Swal.showLoading();
                }
            });
        },
        success: function(data) {
            Swal.close();

            if (data.status != "ok") {
                return Swal.fire("Error", data.answer || "No fue posible generar el PDF.", "error");
            }

            window.open(data.url, "_blank");
        },
        error: function(xhr) {
            Swal.close();
            console.error(xhr.responseText);
            Swal.fire("Error", "No fue posible generar el PDF.", "error");
        }
    });
}

function cargarTodosLosPeriodos(callback) {
    const periodos = state.evaluationPeriods.slice();

    if (!periodos.length) {
        if (callback) callback();
        return;
    }

    let pendientes = periodos.length;

    periodos.forEach(function(periodo) {
        getCalificaciones(periodo, false, false).always(function() {
            pendientes--;

            if (pendientes === 0 && callback) {
                callback();
            }
        });
    });
}