<?php

class consultas{
    private $token;
    private $conn;

    public function __construct($session) {
        $this->token = $session;
        $this->connectToDatabase();
    }

    private function connectToDatabase() {
        $conexion = new conexion();
        $this->conn = $conexion->getConnection();

        if (!$this->conn || $this->conn->connect_error) {
            $this->conn = null;
            return false;
        }
        
        $this->conn->set_charset("utf8mb4");

        return true;
    }

    public function getAlumnos() {
        $json = [];

        $myuser_id = $this->token;

        $sql = "SELECT id, matricula, nombre, apellido, grado, grupo, orden, estado, fecha_nacimiento, curp, correo, telefono, observaciones, fecha_alta
                FROM alumnos
                WHERE myuser_id = ? AND eliminado = 0
                ORDER BY grado ASC, grupo ASC, apellido ASC, nombre ASC";

        $stmt = $this->conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("s", $myuser_id);

            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $alumnos = [];

                while ($row = $result->fetch_assoc()) {
                    $row["id"] = (int)$row["id"];
                    $row["orden"] = (int)$row["orden"];
                    $row["estado"] = (int)$row["estado"];
                    $row["name"] = trim($row["nombre"] . " " . $row["apellido"]);
                    $row["enrollment"] = $row["matricula"];
                    $row["group"] = trim($row["grado"] . "° " . $row["grupo"]);
                    $row["status"] = $row["estado"] == 1 ? "Activo" : "Inactivo";

                    $alumnos[] = $row;
                }

                $json["status"] = "ok";
                $json["answer"] = "Alumnos consultados correctamente";
                $json["results"] = $alumnos;
                $json["total"] = count($alumnos);
                $json["code"] = 200;
            } else {
                error_log("getAlumnos execute: " . $stmt->error);

                $json["status"] = "error";
                $json["answer"] = "No fue posible consultar los alumnos";
                $json["code"] = 500;
            }

            $stmt->close();
        } else {
            error_log("getAlumnos prepare: " . $this->conn->error);

            $json["status"] = "error";
            $json["answer"] = "Error en la preparación de la consulta de alumnos";
            $json["code"] = 500;
        }
        return $json;
    }

    public function guardarAlumno($data) {
        $json = [];
        $myuser_id = $this->token;

        $id = isset($data["id"]) ? (int)$data["id"] : 0;
        $matricula = isset($data["matricula"]) ? strtoupper(trim((string)$data["matricula"])) : "";
        $nombre = isset($data["nombre"]) ? trim((string)$data["nombre"]) : "";
        $apellido = isset($data["apellido"]) ? trim((string)$data["apellido"]) : "";
        $grado = isset($data["grado"]) ? trim((string)$data["grado"]) : "";
        $grupo = isset($data["grupo"]) ? strtoupper(trim((string)$data["grupo"])) : "";
        $estado = isset($data["estado"]) && (int)$data["estado"] === 0 ? 0 : 1;
        $fechaNacimiento = isset($data["fecha_nacimiento"]) ? trim((string)$data["fecha_nacimiento"]) : "";
        $curp = isset($data["curp"]) ? strtoupper(trim((string)$data["curp"])) : "";
        $correo = isset($data["correo"]) ? strtolower(trim((string)$data["correo"])) : "";
        $telefono = isset($data["telefono"]) ? trim((string)$data["telefono"]) : "";
        $observaciones = isset($data["observaciones"]) ? trim((string)$data["observaciones"]) : "";

        
if ($matricula === "" && $id > 0) {
    $sqlMatriculaActual = "SELECT matricula
                           FROM alumnos
                           WHERE myuser_id = ?
                           AND id = ?
                           AND eliminado = 0
                           LIMIT 1";

    $stmtMatriculaActual = $this->conn->prepare($sqlMatriculaActual);

    if ($stmtMatriculaActual) {
        $stmtMatriculaActual->bind_param(
            "si",
            $myuser_id,
            $id
        );

        if ($stmtMatriculaActual->execute()) {
            $resultMatriculaActual =
                $stmtMatriculaActual->get_result();

            if ($resultMatriculaActual->num_rows > 0) {
                $registroMatricula =
                    $resultMatriculaActual->fetch_assoc();

                $matriculaActual =
                    (string)$registroMatricula["matricula"];

                if (
                    strpos(
                        $matriculaActual,
                        "SINMAT-"
                    ) === 0
                ) {
                    $matricula =
                        $matriculaActual;
                }
            }
        }

        $stmtMatriculaActual->close();
    }
}

 
if ($matricula === "") {
    $matricula =
        "SINMAT-" .
        strtoupper(
            bin2hex(
                random_bytes(16)
            )
        );
}


        $datosValidos = true;

        if ($nombre === "" || $apellido === "" || $grado === "" || $grupo === "") {
    $json["status"] = "warning";
    $json["answer"] = "Completa nombre, apellidos, grado y grupo";
    $json["code"] = 400;
    $datosValidos = false;
} elseif ($correo !== "" && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $json["status"] = "warning";
            $json["answer"] = "El correo electrónico no tiene un formato válido";
            $json["code"] = 400;
            $datosValidos = false;
        } elseif ($curp !== "" && strlen($curp) !== 18) {
            $json["status"] = "warning";
            $json["answer"] = "La CURP debe contener 18 caracteres";
            $json["code"] = 400;
            $datosValidos = false;
        }

        if ($datosValidos && $fechaNacimiento !== "") {
            $fechaValidar = DateTime::createFromFormat("Y-m-d", $fechaNacimiento);

            if (!$fechaValidar || $fechaValidar->format("Y-m-d") !== $fechaNacimiento) {
                $json["status"] = "warning";
                $json["answer"] = "La fecha de nacimiento no es válida";
                $json["code"] = 400;
                $datosValidos = false;
            }
        }

        if ($datosValidos) {
            $sqlExiste = "SELECT id FROM alumnos WHERE myuser_id = ? AND matricula = ? AND id <> ? AND eliminado = 0 LIMIT 1";

            $stmtExiste = $this->conn->prepare($sqlExiste);

            if ($stmtExiste) {
                $stmtExiste->bind_param("ssi", $myuser_id, $matricula, $id);

                if ($stmtExiste->execute()) {
                    $resultExiste = $stmtExiste->get_result();

                    if ($resultExiste->num_rows > 0) {
                        $json["status"] = "warning";
                        $json["answer"] = "Ya existe un alumno con la matrícula " . $matricula;
                        $json["code"] = 409;
                        $datosValidos = false;
                    }
                } else {
                    error_log("guardarAlumno validar matrícula execute: " . $stmtExiste->error);

                    $json["status"] = "error";
                    $json["answer"] = "No fue posible validar la matrícula";
                    $json["code"] = 500;
                    $datosValidos = false;
                }

                $stmtExiste->close();
            } else {
                error_log("guardarAlumno validar matrícula prepare: " . $this->conn->error);

                $json["status"] = "error";
                $json["answer"] = "Error al validar la matrícula";
                $json["code"] = 500;
                $datosValidos = false;
            }
        }

        if ($datosValidos) {
            $fechaNacimiento = $fechaNacimiento === "" ? null : $fechaNacimiento;

            if ($id > 0) {
                $sql = "UPDATE alumnos SET
                            matricula = ?,
                            nombre = ?,
                            apellido = ?,
                            grado = ?,
                            grupo = ?,
                            estado = ?,
                            fecha_nacimiento = ?,
                            curp = ?,
                            correo = ?,
                            telefono = ?,
                            observaciones = ?
                        WHERE myuser_id = ? AND id = ? AND eliminado = 0";

                $stmt = $this->conn->prepare($sql);

                if ($stmt) {
                    $stmt->bind_param(
                        "sssssissssssi",
                        $matricula,
                        $nombre,
                        $apellido,
                        $grado,
                        $grupo,
                        $estado,
                        $fechaNacimiento,
                        $curp,
                        $correo,
                        $telefono,
                        $observaciones,
                        $myuser_id,
                        $id
                    );

                    if ($stmt->execute()) {
                        $json["status"] = "ok";
                        $json["answer"] = "Alumno actualizado correctamente";
                        $json["id"] = $id;
                        $json["code"] = 200;
                    } else {
                        error_log("guardarAlumno UPDATE execute: " . $stmt->error);

                        $json["status"] = "error";
                        $json["answer"] = "No fue posible actualizar el alumno";
                        $json["code"] = 500;
                    }

                    $stmt->close();
                } else {
                    error_log("guardarAlumno UPDATE prepare: " . $this->conn->error);

                    $json["status"] = "error";
                    $json["answer"] = "Error en la preparación de la actualización";
                    $json["code"] = 500;
                }
            } else {
                $sql = "INSERT INTO alumnos
                        (myuser_id, matricula, nombre, apellido, grado, grupo, estado, fecha_nacimiento, curp, correo, telefono, observaciones)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

                $stmt = $this->conn->prepare($sql);

                if ($stmt) {
                    $stmt->bind_param(
                        "ssssssisssss",
                        $myuser_id,
                        $matricula,
                        $nombre,
                        $apellido,
                        $grado,
                        $grupo,
                        $estado,
                        $fechaNacimiento,
                        $curp,
                        $correo,
                        $telefono,
                        $observaciones
                    );

                    if ($stmt->execute()) {
                        $json["status"] = "ok";
                        $json["answer"] = "Alumno registrado correctamente";
                        $json["id"] = (int)$this->conn->insert_id;
                        $json["code"] = 200;
                    } else {
                        error_log("guardarAlumno INSERT execute: " . $stmt->error);

                        $json["status"] = "error";
                        $json["answer"] = "No fue posible registrar el alumno";
                        $json["code"] = 500;
                    }

                    $stmt->close();
                } else {
                    error_log("guardarAlumno INSERT prepare: " . $this->conn->error);

                    $json["status"] = "error";
                    $json["answer"] = "Error en la preparación del registro";
                    $json["code"] = 500;
                }
            }
        }
        return $json;
    }

    public function eliminarAlumno($id) {
        $json = [];
        $myuser_id = $this->token;
        $id = (int)$id;

        if ($id <= 0) {
            $json["status"] = "warning";
            $json["answer"] = "El alumno seleccionado no es válido";
            $json["code"] = 400;
        } else {
            $sql = "UPDATE alumnos
                    SET eliminado = 1, estado = 0
                    WHERE myuser_id = ? AND id = ? AND eliminado = 0";

            $stmt = $this->conn->prepare($sql);

            if ($stmt) {
                $stmt->bind_param("si", $myuser_id, $id);

                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        $json["status"] = "ok";
                        $json["answer"] = "Alumno eliminado correctamente";
                        $json["code"] = 200;
                    } else {
                        $json["status"] = "warning";
                        $json["answer"] = "El alumno no existe o ya fue eliminado";
                        $json["code"] = 404;
                    }
                } else {
                    error_log("eliminarAlumno execute: " . $stmt->error);

                    $json["status"] = "error";
                    $json["answer"] = "No fue posible eliminar el alumno";
                    $json["code"] = 500;
                }

                $stmt->close();
            } else {
                error_log("eliminarAlumno prepare: " . $this->conn->error);

                $json["status"] = "error";
                $json["answer"] = "Error en la preparación de la eliminación";
                $json["code"] = 500;
            }
        }
    
        return $json;
    }

    public function guardarOrdenAlumnos($data) {
    $json = [];
    $myuser_id = $this->token;
    $ids = isset($data["ids"]) ? json_decode($data["ids"], true) : [];

    if (!is_array($ids) || count($ids) === 0) {
        $json["status"] = "warning";
        $json["answer"] = "No se recibió un orden válido";
        $json["code"] = 400;
    } else {
        $stmt = $this->conn->prepare("UPDATE alumnos SET orden = ? WHERE myuser_id = ? AND id = ? AND eliminado = 0");

        if ($stmt) {
            $this->conn->begin_transaction();
            $valido = true;

            foreach ($ids as $index => $alumnoId) {
                $orden = $index + 1;
                $alumnoId = (int)$alumnoId;
                $stmt->bind_param("isi", $orden, $myuser_id, $alumnoId);

                if (!$stmt->execute()) {
                    $valido = false;
                    break;
                }
            }

            $stmt->close();

            if ($valido) {
                $this->conn->commit();
                $json["status"] = "ok";
                $json["answer"] = "Orden actualizado correctamente";
                $json["code"] = 200;
            } else {
                $this->conn->rollback();
                $json["status"] = "error";
                $json["answer"] = "No fue posible guardar el nuevo orden";
                $json["code"] = 500;
            }
        } else {
            $json["status"] = "error";
            $json["answer"] = "No fue posible preparar el cambio de orden";
            $json["code"] = 500;
        }
    }

    return $json;
}

    //MAESTROS
    public function getMaestros() {
        $json = [];
        $myuser_id = $this->token;

        $sql = "SELECT id, clave, nombre, apellido, curp, correo, telefono, especialidad, usuario, observaciones, estado, fecha_alta
                FROM maestros
                WHERE myuser_id = ? AND eliminado = 0
                ORDER BY apellido ASC, nombre ASC";

        $stmt = $this->conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("s", $myuser_id);

            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $maestros = [];

                while ($row = $result->fetch_assoc()) {
                    $row["id"] = (int)$row["id"];
                    $row["estado"] = (int)$row["estado"];
                    $row["name"] = trim($row["nombre"] . " " . $row["apellido"]);
                    $row["code"] = $row["clave"];
                    $row["email"] = $row["correo"];
                    $row["status"] = $row["estado"] == 1 ? "Activo" : "Inactivo";

                    $maestros[] = $row;
                }

                $json["status"] = "ok";
                $json["answer"] = "Maestros consultados correctamente";
                $json["results"] = $maestros;
                $json["total"] = count($maestros);
                $json["code"] = 200;
            } else {
                error_log("getMaestros execute: " . $stmt->error);
                $json["status"] = "error";
                $json["answer"] = "No fue posible consultar los maestros";
                $json["code"] = 500;
            }

            $stmt->close();
        } else {
            error_log("getMaestros prepare: " . $this->conn->error);
            $json["status"] = "error";
            $json["answer"] = "Error en la preparación de la consulta de maestros";
            $json["code"] = 500;
        }
        
        return $json;
    }

    public function guardarMaestro($data) {
        $json = [];
        $myuser_id = $this->token;
        
        $id = isset($data["id"]) ? (int)$data["id"] : 0;
        $clave = isset($data["clave"]) ? strtoupper(trim((string)$data["clave"])) : "";
        $nombre = isset($data["nombre"]) ? trim((string)$data["nombre"]) : "";
        $apellido = isset($data["apellido"]) ? trim((string)$data["apellido"]) : "";
        $curp = isset($data["curp"]) ? strtoupper(trim((string)$data["curp"])) : "";
        $correo = isset($data["correo"]) ? strtolower(trim((string)$data["correo"])) : "";
        $telefono = isset($data["telefono"]) ? trim((string)$data["telefono"]) : "";
        $especialidad = isset($data["especialidad"]) ? trim((string)$data["especialidad"]) : "";
        $usuario = isset($data["usuario"]) ? strtolower(trim((string)$data["usuario"])) : "";
        $password = isset($data["password"]) ? (string)$data["password"] : "";
        $observaciones = isset($data["observaciones"]) ? trim((string)$data["observaciones"]) : "";
        $estado = isset($data["estado"]) && (int)$data["estado"] === 0 ? 0 : 1;
        
        $datosValidos = true;
        
        if (!$this->conn) {
            $json["status"] = "error";
            $json["answer"] = "No existe conexión con la base de datos";
            $json["code"] = 501;
            $datosValidos = false;
        } elseif ($clave === "" || $nombre === "" || $apellido === "" || $usuario === "") {
            $json["status"] = "warning";
            $json["answer"] = "Completa clave, nombre, apellidos y usuario";
            $json["code"] = 400;
            $datosValidos = false;
        } elseif ($id <= 0 && $password === "") {
            $json["status"] = "warning";
            $json["answer"] = "La contraseña es obligatoria para un maestro nuevo";
            $json["code"] = 400;
            $datosValidos = false;
        } elseif ($password !== "" && strlen($password) < 8) {
            $json["status"] = "warning";
            $json["answer"] = "La contraseña debe contener al menos 8 caracteres";
            $json["code"] = 400;
            $datosValidos = false;
        } elseif ($correo !== "" && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $json["status"] = "warning";
            $json["answer"] = "El correo electrónico no tiene un formato válido";
            $json["code"] = 400;
            $datosValidos = false;
        } elseif ($curp !== "" && strlen($curp) !== 18) {
            $json["status"] = "warning";
            $json["answer"] = "La CURP debe contener 18 caracteres";
            $json["code"] = 400;
            $datosValidos = false;
        }

        if ($datosValidos) {
            $sqlExiste = "SELECT id, clave, curp, usuario
                      FROM maestros
                      WHERE myuser_id = ? AND id <> ?
                      AND (
                          clave = ?
                          OR usuario = ?
                          OR (? <> '' AND curp = ?)
                      )
                      LIMIT 1";

            $stmtExiste = $this->conn->prepare($sqlExiste);
            
            if ($stmtExiste) {
            $stmtExiste->bind_param("sissss", $myuser_id, $id, $clave, $usuario, $curp, $curp);

            if ($stmtExiste->execute()) {
                $resultExiste = $stmtExiste->get_result();

                if ($resultExiste->num_rows > 0) {
                    $registro = $resultExiste->fetch_assoc();

                    if (strcasecmp($registro["clave"], $clave) === 0) {
                        $json["answer"] = "Ya existe un maestro con la clave " . $clave;
                    } elseif (strcasecmp($registro["usuario"], $usuario) === 0) {
                        $json["answer"] = "El nombre de usuario ya está registrado";
                    } else {
                        $json["answer"] = "Ya existe un maestro con esa CURP";
                    }

                    $json["status"] = "warning";
                    $json["code"] = 409;
                    $datosValidos = false;
                }
            } else {
                error_log("guardarMaestro validar execute: " . $stmtExiste->error);
                $json["status"] = "error";
                $json["answer"] = "No fue posible validar los datos del maestro";
                $json["code"] = 500;
                $datosValidos = false;
            }

            $stmtExiste->close();
        } else {
            error_log("guardarMaestro validar prepare: " . $this->conn->error);
            $json["status"] = "error";
            $json["answer"] = "Error al validar los datos del maestro";
            $json["code"] = 500;
            $datosValidos = false;
        }
    }

    if ($datosValidos) {
        if ($id > 0) {
            $passwordHash = "";

            if ($password !== "") {
                $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            }

            $sql = "UPDATE maestros SET
                        clave = ?,
                        nombre = ?,
                        apellido = ?,
                        curp = ?,
                        correo = ?,
                        telefono = ?,
                        especialidad = ?,
                        usuario = ?,
                        password = CASE WHEN ? <> '' THEN ? ELSE password END,
                        observaciones = ?,
                        estado = ?
                    WHERE myuser_id = ? AND id = ? AND eliminado = 0";

            $stmt = $this->conn->prepare($sql);

            if ($stmt) {
                $stmt->bind_param(
                    "sssssssssssisi",
                    $clave,
                    $nombre,
                    $apellido,
                    $curp,
                    $correo,
                    $telefono,
                    $especialidad,
                    $usuario,
                    $passwordHash,
                    $passwordHash,
                    $observaciones,
                    $estado,
                    $myuser_id,
                    $id
                );

                if ($stmt->execute()) {
                    $json["status"] = "ok";
                    $json["answer"] = "Maestro actualizado correctamente";
                    $json["id"] = $id;
                    $json["code"] = 200;
                } else {
                    error_log("guardarMaestro UPDATE execute: " . $stmt->error);
                    $json["status"] = "error";
                    $json["answer"] = "No fue posible actualizar el maestro";
                    $json["code"] = 500;
                }

                $stmt->close();
            } else {
                error_log("guardarMaestro UPDATE prepare: " . $this->conn->error);
                $json["status"] = "error";
                $json["answer"] = "Error en la preparación de la actualización";
                $json["code"] = 500;
            }
        } else {
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);

            $sql = "INSERT INTO maestros
                    (myuser_id, clave, nombre, apellido, curp, correo, telefono, especialidad, usuario, password, observaciones, estado)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->conn->prepare($sql);

            if ($stmt) {
                $stmt->bind_param(
                    "sssssssssssi",
                    $myuser_id,
                    $clave,
                    $nombre,
                    $apellido,
                    $curp,
                    $correo,
                    $telefono,
                    $especialidad,
                    $usuario,
                    $passwordHash,
                    $observaciones,
                    $estado
                );

                if ($stmt->execute()) {
                    $json["status"] = "ok";
                    $json["answer"] = "Maestro registrado correctamente";
                    $json["id"] = (int)$this->conn->insert_id;
                    $json["code"] = 200;
                } else {
                    error_log("guardarMaestro INSERT execute: " . $stmt->error);
                    $json["status"] = "error";
                    $json["answer"] = "No fue posible registrar el maestro";
                    $json["code"] = 500;
                }

                $stmt->close();
            } else {
                error_log("guardarMaestro INSERT prepare: " . $this->conn->error);
                $json["status"] = "error";
                $json["answer"] = "Error en la preparación del registro";
                $json["code"] = 500;
            }
        }
    }

    return $json;
}

public function eliminarMaestro($id) {
    $json = [];
    $myuser_id = $this->token;
    $id = (int)$id;

    if (!$this->conn) {
        $json["status"] = "error";
        $json["answer"] = "No existe conexión con la base de datos";
        $json["code"] = 501;
    } elseif ($id <= 0) {
        $json["status"] = "warning";
        $json["answer"] = "El maestro seleccionado no es válido";
        $json["code"] = 400;
    } else {
        $sql = "UPDATE maestros
                SET eliminado = 1, estado = 0
                WHERE myuser_id = ? AND id = ? AND eliminado = 0";

        $stmt = $this->conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("si", $myuser_id, $id);

            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $json["status"] = "ok";
                    $json["answer"] = "Maestro eliminado correctamente";
                    $json["code"] = 200;
                } else {
                    $json["status"] = "warning";
                    $json["answer"] = "El maestro no existe o ya fue eliminado";
                    $json["code"] = 404;
                }
            } else {
                error_log("eliminarMaestro execute: " . $stmt->error);
                $json["status"] = "error";
                $json["answer"] = "No fue posible eliminar el maestro";
                $json["code"] = 500;
            }

            $stmt->close();
        } else {
            error_log("eliminarMaestro prepare: " . $this->conn->error);
            $json["status"] = "error";
            $json["answer"] = "Error en la preparación de la eliminación";
            $json["code"] = 500;
        }
    }

    return $json;
}

//ASIGNATURAS
public function getAsignaturas() {
    $json = [];
    $myuser_id = $this->token;
    $asignaturas = [];

    if (!$this->conn) {
        $json["status"] = "error";
        $json["answer"] = "No existe conexión con la base de datos";
        $json["code"] = 501;
    } else {
        $sql = "SELECT id, clave, nombre, descripcion, estado, fecha_alta
                FROM asignaturas
                WHERE myuser_id = ? AND eliminado = 0
                ORDER BY nombre ASC";

        $stmt = $this->conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("s", $myuser_id);

            if ($stmt->execute()) {
                $result = $stmt->get_result();

                while ($row = $result->fetch_assoc()) {
                    $row["id"] = (int)$row["id"];
                    $row["estado"] = (int)$row["estado"];
                    $row["name"] = $row["nombre"];
                    $row["code"] = $row["clave"];
                    $row["criteria"] = [];

                    $asignaturas[$row["id"]] = $row;
                }

                $sqlCriterios = "SELECT
                    id,
                    asignatura_id,
                    nombre,
                    porcentaje,
                    afecta_promedio,
                    tipo_captura,
                    usa_actividades,
                    orden,
                    color,
                    estado
                 FROM criterios_evaluacion
                 WHERE myuser_id = ? AND eliminado = 0
                 ORDER BY asignatura_id ASC, orden ASC, id ASC";

                $stmtCriterios = $this->conn->prepare($sqlCriterios);

                if ($stmtCriterios) {
                    $stmtCriterios->bind_param("s", $myuser_id);

                    if ($stmtCriterios->execute()) {
                        $resultCriterios = $stmtCriterios->get_result();

                        while ($criterio = $resultCriterios->fetch_assoc()) {
                            $criterio["id"] = (int)$criterio["id"];
                            $criterio["asignatura_id"] = (int)$criterio["asignatura_id"];
                            $criterio["porcentaje"] = (float)$criterio["porcentaje"];
                            $criterio["orden"] = (int)$criterio["orden"];
                            $criterio["estado"] = (int)$criterio["estado"];
                            $criterio["name"] = $criterio["nombre"];
                            $criterio["weight"] = $criterio["porcentaje"];
                            $criterio["afecta_promedio"] = (int)$criterio["afecta_promedio"];
                            $criterio["usa_actividades"] = (int)$criterio["usa_actividades"];

                            if (isset($asignaturas[$criterio["asignatura_id"]])) {
                                $asignaturas[$criterio["asignatura_id"]]["criteria"][] = $criterio;
                            }
                        }

                        $json["status"] = "ok";
                        $json["answer"] = "Asignaturas consultadas correctamente";
                        $json["results"] = array_values($asignaturas);
                        $json["total"] = count($asignaturas);
                        $json["code"] = 200;
                    } else {
                        error_log("getAsignaturas criterios execute: " . $stmtCriterios->error);
                        $json["status"] = "error";
                        $json["answer"] = "No fue posible consultar los criterios de evaluación";
                        $json["code"] = 500;
                    }

                    $stmtCriterios->close();
                } else {
                    error_log("getAsignaturas criterios prepare: " . $this->conn->error);
                    $json["status"] = "error";
                    $json["answer"] = "Error en la consulta de criterios de evaluación";
                    $json["code"] = 500;
                }
            } else {
                error_log("getAsignaturas execute: " . $stmt->error);
                $json["status"] = "error";
                $json["answer"] = "No fue posible consultar las asignaturas";
                $json["code"] = 500;
            }

            $stmt->close();
        } else {
            error_log("getAsignaturas prepare: " . $this->conn->error);
            $json["status"] = "error";
            $json["answer"] = "Error en la preparación de la consulta de asignaturas";
            $json["code"] = 500;
        }
    }

    return $json;
}

public function guardarAsignatura($data) {
    $json = [];
    $myuser_id = $this->token;

    $id = isset($data["id"]) ? (int)$data["id"] : 0;
    $clave = isset($data["clave"]) ? strtoupper(trim((string)$data["clave"])) : "";
    $nombre = isset($data["nombre"]) ? trim((string)$data["nombre"]) : "";
    $descripcion = isset($data["descripcion"]) ? trim((string)$data["descripcion"]) : "";
    $estado = isset($data["estado"]) && (int)$data["estado"] === 0 ? 0 : 1;
    $criteriosJson = isset($data["criterios"]) ? $data["criterios"] : "[]";
    $criteriosRecibidos = json_decode($criteriosJson, true);

    $valido = true;
    $criterios = [];
    $totalPorcentaje = 0;

    if (!$this->conn) {
        $json["status"] = "error";
        $json["answer"] = "No existe conexión con la base de datos";
        $json["code"] = 501;
        $valido = false;
    } elseif ($clave === "" || $nombre === "") {
        $json["status"] = "warning";
        $json["answer"] = "Completa la clave y el nombre de la asignatura";
        $json["code"] = 400;
        $valido = false;
    } elseif (!is_array($criteriosRecibidos)) {
        $json["status"] = "warning";
        $json["answer"] = "Los criterios de evaluación no tienen un formato válido";
        $json["code"] = 400;
        $valido = false;
    } elseif (count($criteriosRecibidos) === 0) {
        $json["status"] = "warning";
        $json["answer"] = "Agrega al menos un criterio de evaluación";
        $json["code"] = 400;
        $valido = false;
    }

    if ($valido) {
        $nombresCriterios = [];

        foreach ($criteriosRecibidos as $index => $criterio) {
            $idCriterio = isset($criterio["id"]) ? (int)$criterio["id"] : 0;
            $nombreCriterio = isset($criterio["nombre"]) ? trim((string)$criterio["nombre"]) : "";
            $porcentaje = isset($criterio["porcentaje"]) ? (float)$criterio["porcentaje"] : 0;
            $color = isset($criterio["color"]) ? trim((string)$criterio["color"]) : "#B8943C";
            $afectaPromedio = isset($criterio["afecta_promedio"]) ? (int)$criterio["afecta_promedio"] : 1;
            $tipoCaptura = isset($criterio["tipo_captura"]) ? trim((string)$criterio["tipo_captura"]) : "numero";
            $usaActividades = isset($criterio["usa_actividades"]) ? (int)$criterio["usa_actividades"] : 0;
            $porcentaje = $afectaPromedio ? $porcentaje : 0;
            $orden = $index + 1;

            if ($nombreCriterio === "") {
                $json["status"] = "warning";
                $json["answer"] = "Todos los criterios deben tener un nombre";
                $json["code"] = 400;
                $valido = false;
                break;
            }

            if ($afectaPromedio && ($porcentaje <= 0 || $porcentaje > 100)) {
    $json["status"] = "warning";
    $json["answer"] = "Los criterios que afectan el promedio deben tener un porcentaje válido";
    $json["code"] = 400;
    $valido = false;
    break;
}

if ($afectaPromedio && $tipoCaptura !== "numero") {
    $json["status"] = "warning";
    $json["answer"] = "Un criterio que afecta el promedio debe utilizar calificaciones numéricas";
    $json["code"] = 400;
    $valido = false;
    break;
}

if (!in_array($tipoCaptura, ["numero", "conducta", "inasistencias"], true)) {
    $tipoCaptura = "numero";
}

            $nombreComparar = function_exists("mb_strtolower")
                ? mb_strtolower($nombreCriterio, "UTF-8")
                : strtolower($nombreCriterio);

            if (isset($nombresCriterios[$nombreComparar])) {
                $json["status"] = "warning";
                $json["answer"] = "No puedes repetir el criterio \"" . $nombreCriterio . "\"";
                $json["code"] = 400;
                $valido = false;
                break;
            }

            $nombresCriterios[$nombreComparar] = true;

            if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
                $color = "#B8943C";
            }

            $criterios[] = [
    "id" => $idCriterio,
    "nombre" => $nombreCriterio,
    "porcentaje" => $porcentaje,
    "afecta_promedio" => $afectaPromedio,
    "tipo_captura" => $tipoCaptura,
    "usa_actividades" => $usaActividades,
    "orden" => $orden,
    "color" => $color
];

            if ($afectaPromedio) {
    $totalPorcentaje += $porcentaje;
}
        }
    }

    if ($valido && abs($totalPorcentaje - 100) > 0.01) {
        $json["status"] = "warning";
        $json["answer"] = "La suma de los criterios debe ser exactamente 100%. Actualmente suma " . $totalPorcentaje . "%";
        $json["code"] = 400;
        $valido = false;
    }

    if ($valido) {
        $sqlDuplicado = "SELECT id
                         FROM asignaturas
                         WHERE myuser_id = ? AND clave = ? AND id <> ? AND eliminado = 0
                         LIMIT 1";

        $stmtDuplicado = $this->conn->prepare($sqlDuplicado);

        if ($stmtDuplicado) {
            $stmtDuplicado->bind_param("ssi", $myuser_id, $clave, $id);

            if ($stmtDuplicado->execute()) {
                $resultDuplicado = $stmtDuplicado->get_result();

                if ($resultDuplicado->num_rows > 0) {
                    $json["status"] = "warning";
                    $json["answer"] = "Ya existe una asignatura con la clave " . $clave;
                    $json["code"] = 409;
                    $valido = false;
                }
            } else {
                $json["status"] = "error";
                $json["answer"] = "No fue posible validar la clave de la asignatura";
                $json["code"] = 500;
                $valido = false;
            }

            $stmtDuplicado->close();
        } else {
            $json["status"] = "error";
            $json["answer"] = "Error al validar la asignatura";
            $json["code"] = 500;
            $valido = false;
        }
    }

    $criteriosExistentes = [];

    if ($valido && $id > 0) {
        $sqlExiste = "SELECT id
                      FROM asignaturas
                      WHERE myuser_id = ? AND id = ? AND eliminado = 0
                      LIMIT 1";

        $stmtExiste = $this->conn->prepare($sqlExiste);

        if ($stmtExiste) {
            $stmtExiste->bind_param("si", $myuser_id, $id);
            $stmtExiste->execute();
            $resultExiste = $stmtExiste->get_result();

            if ($resultExiste->num_rows === 0) {
                $json["status"] = "warning";
                $json["answer"] = "La asignatura que intentas editar no existe";
                $json["code"] = 404;
                $valido = false;
            }

            $stmtExiste->close();
        } else {
            $json["status"] = "error";
            $json["answer"] = "No fue posible validar la asignatura";
            $json["code"] = 500;
            $valido = false;
        }
    }

    if ($valido && $id > 0) {
        $sqlExistentes = "SELECT id
                          FROM criterios_evaluacion
                          WHERE myuser_id = ? AND asignatura_id = ? AND eliminado = 0";

        $stmtExistentes = $this->conn->prepare($sqlExistentes);

        if ($stmtExistentes) {
            $stmtExistentes->bind_param("si", $myuser_id, $id);
            $stmtExistentes->execute();
            $resultExistentes = $stmtExistentes->get_result();

            while ($row = $resultExistentes->fetch_assoc()) {
                $criteriosExistentes[(int)$row["id"]] = true;
            }

            $stmtExistentes->close();

            foreach ($criterios as $criterio) {
                if ($criterio["id"] > 0 && !isset($criteriosExistentes[$criterio["id"]])) {
                    $json["status"] = "warning";
                    $json["answer"] = "Uno de los criterios de evaluación no pertenece a esta asignatura";
                    $json["code"] = 400;
                    $valido = false;
                    break;
                }
            }
        } else {
            $json["status"] = "error";
            $json["answer"] = "No fue posible validar los criterios actuales";
            $json["code"] = 500;
            $valido = false;
        }
    }

    $idAsignatura = $id;

    if ($valido) {
        if ($id > 0) {
            $sql = "UPDATE asignaturas
                    SET clave = ?, nombre = ?, descripcion = ?, estado = ?
                    WHERE myuser_id = ? AND id = ? AND eliminado = 0";

            $stmt = $this->conn->prepare($sql);

            if ($stmt) {
                $stmt->bind_param("sssisi", $clave, $nombre, $descripcion, $estado, $myuser_id, $id);

                if (!$stmt->execute()) {
                    error_log("guardarAsignatura UPDATE: " . $stmt->error);
                    $json["status"] = "error";
                    $json["answer"] = "No fue posible actualizar la asignatura";
                    $json["code"] = 500;
                    $valido = false;
                }

                $stmt->close();
            } else {
                $json["status"] = "error";
                $json["answer"] = "Error en la preparación de la asignatura";
                $json["code"] = 500;
                $valido = false;
            }
        } else {
            $sql = "INSERT INTO asignaturas
                    (myuser_id, clave, nombre, descripcion, estado)
                    VALUES (?, ?, ?, ?, ?)";

            $stmt = $this->conn->prepare($sql);

            if ($stmt) {
                $stmt->bind_param("ssssi", $myuser_id, $clave, $nombre, $descripcion, $estado);

                if ($stmt->execute()) {
                    $idAsignatura = (int)$this->conn->insert_id;
                } else {
                    error_log("guardarAsignatura INSERT: " . $stmt->error);
                    $json["status"] = "error";
                    $json["answer"] = "No fue posible registrar la asignatura";
                    $json["code"] = 500;
                    $valido = false;
                }

                $stmt->close();
            } else {
                $json["status"] = "error";
                $json["answer"] = "Error en la preparación del registro";
                $json["code"] = 500;
                $valido = false;
            }
        }
    }

    $criteriosConservados = [];

    if ($valido) {
        foreach ($criterios as $criterio) {
            $idCriterio = (int)$criterio["id"];
            $nombreCriterio = $criterio["nombre"];
            $porcentaje = (float)$criterio["porcentaje"];
            $afectaPromedio = (int)$criterio["afecta_promedio"];
            $tipoCaptura = $criterio["tipo_captura"];
            $usaActividades = (int)$criterio["usa_actividades"];
            $orden = (int)$criterio["orden"];
            $color = $criterio["color"];
            $estadoCriterio = 1;

            if ($idCriterio > 0) {
                $sql = "UPDATE criterios_evaluacion
        SET nombre = ?, porcentaje = ?, afecta_promedio = ?, tipo_captura = ?,
            usa_actividades = ?, orden = ?, color = ?, estado = ?
        WHERE myuser_id = ? AND asignatura_id = ? AND id = ? AND eliminado = 0";

                $stmt = $this->conn->prepare($sql);

                if ($stmt) {
                    $stmt->bind_param(
    "sdisiisisii",
    $nombreCriterio,
    $porcentaje,
    $afectaPromedio,
    $tipoCaptura,
    $usaActividades,
    $orden,
    $color,
    $estadoCriterio,
    $myuser_id,
    $idAsignatura,
    $idCriterio
);
                    if ($stmt->execute()) {
                        $criteriosConservados[$idCriterio] = true;
                    } else {
                        error_log("guardarAsignatura criterio UPDATE: " . $stmt->error);
                        $valido = false;
                    }

                    $stmt->close();
                } else {
                    $valido = false;
                }
            } else {
                $sql = "INSERT INTO criterios_evaluacion
        (myuser_id, asignatura_id, nombre, porcentaje, afecta_promedio,
         tipo_captura, usa_actividades, orden, color, estado)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

                $stmt = $this->conn->prepare($sql);

                if ($stmt) {
                    $stmt->bind_param(
    "sisdisiisi",
    $myuser_id,
    $idAsignatura,
    $nombreCriterio,
    $porcentaje,
    $afectaPromedio,
    $tipoCaptura,
    $usaActividades,
    $orden,
    $color,
    $estadoCriterio
);

                    if ($stmt->execute()) {
                        $nuevoIdCriterio = (int)$this->conn->insert_id;
                        $criteriosConservados[$nuevoIdCriterio] = true;
                    } else {
                        error_log("guardarAsignatura criterio INSERT: " . $stmt->error);
                        $valido = false;
                    }

                    $stmt->close();
                } else {
                    $valido = false;
                }
            }

            if (!$valido) {
                $json["status"] = "error";
                $json["answer"] = "No fue posible guardar todos los criterios de evaluación";
                $json["code"] = 500;
                break;
            }
        }
    }

    if ($valido && $id > 0) {
        foreach ($criteriosExistentes as $idCriterioExistente => $valor) {
            if (!isset($criteriosConservados[$idCriterioExistente])) {
                $sql = "UPDATE criterios_evaluacion
                        SET eliminado = 1, estado = 0
                        WHERE myuser_id = ? AND asignatura_id = ? AND id = ? AND eliminado = 0";

                $stmt = $this->conn->prepare($sql);

                if ($stmt) {
                    $stmt->bind_param("sii", $myuser_id, $idAsignatura, $idCriterioExistente);

                    if (!$stmt->execute()) {
                        error_log("guardarAsignatura eliminar criterio: " . $stmt->error);
                        $valido = false;
                    }

                    $stmt->close();
                } else {
                    $valido = false;
                }

                if (!$valido) {
                    $json["status"] = "error";
                    $json["answer"] = "La asignatura se guardó, pero no fue posible actualizar todos sus criterios";
                    $json["code"] = 500;
                    break;
                }
            }
        }
    }

    if ($valido) {
        $json["status"] = "ok";
        $json["answer"] = $id > 0
            ? "Asignatura actualizada correctamente"
            : "Asignatura registrada correctamente";
        $json["id"] = $idAsignatura;
        $json["total_criterios"] = count($criterios);
        $json["code"] = 200;
    }

    return $json;
}

public function eliminarAsignatura($id) {
    $json = [];
    $myuser_id = $this->token;
    $id = (int)$id;

    if (!$this->conn) {
        $json["status"] = "error";
        $json["answer"] = "No existe conexión con la base de datos";
        $json["code"] = 501;
    } elseif ($id <= 0) {
        $json["status"] = "warning";
        $json["answer"] = "La asignatura seleccionada no es válida";
        $json["code"] = 400;
    } else {
        $sql = "UPDATE asignaturas
                SET eliminado = 1, estado = 0
                WHERE myuser_id = ? AND id = ? AND eliminado = 0";

        $stmt = $this->conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("si", $myuser_id, $id);

            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $sqlCriterios = "UPDATE criterios_evaluacion
                                    SET eliminado = 1, estado = 0
                                    WHERE myuser_id = ? AND asignatura_id = ? AND eliminado = 0";

                    $stmtCriterios = $this->conn->prepare($sqlCriterios);

                    if ($stmtCriterios) {
                        $stmtCriterios->bind_param("si", $myuser_id, $id);

                        if ($stmtCriterios->execute()) {
                            $json["status"] = "ok";
                            $json["answer"] = "Asignatura eliminada correctamente";
                            $json["code"] = 200;
                        } else {
                            error_log("eliminarAsignatura criterios: " . $stmtCriterios->error);
                            $json["status"] = "warning";
                            $json["answer"] = "La asignatura fue eliminada, pero no fue posible actualizar todos sus criterios";
                            $json["code"] = 200;
                        }

                        $stmtCriterios->close();
                    } else {
                        $json["status"] = "warning";
                        $json["answer"] = "La asignatura fue eliminada, pero no fue posible actualizar sus criterios";
                        $json["code"] = 200;
                    }
                } else {
                    $json["status"] = "warning";
                    $json["answer"] = "La asignatura no existe o ya fue eliminada";
                    $json["code"] = 404;
                }
            } else {
                error_log("eliminarAsignatura execute: " . $stmt->error);
                $json["status"] = "error";
                $json["answer"] = "No fue posible eliminar la asignatura";
                $json["code"] = 500;
            }

            $stmt->close();
        } else {
            $json["status"] = "error";
            $json["answer"] = "Error en la preparación de la eliminación";
            $json["code"] = 500;
        }
    }

    return $json;
}

public function getActividadesEvaluacion($data) {

    $json = [];

    $myuser_id =
        $this->token;

    $asignatura_id =
        isset($data["asignatura_id"])
            ? (int)$data["asignatura_id"]
            : 0;

    $criterio_id =
        isset($data["criterio_id"])
            ? (int)$data["criterio_id"]
            : 0;

    $periodo =
        isset($data["periodo"])
            ? trim((string)$data["periodo"])
            : "";

    $ciclo_escolar =
        isset($data["ciclo_escolar"])
            ? trim((string)$data["ciclo_escolar"])
            : "";

    $grupo =
        isset($data["grupo"])
            ? trim((string)$data["grupo"])
            : "";

    $alumno_id =
        isset($data["alumno_id"])
            ? (int)$data["alumno_id"]
            : 0;

    $incluirCalificacionesGrupo =
    isset(
        $data[
            "incluir_calificaciones_grupo"
        ]
    )
        ? (int)$data[
            "incluir_calificaciones_grupo"
        ]
        : 0;


    if (
        !$this->conn ||
        $asignatura_id <= 0 ||
        $criterio_id <= 0 ||
        $periodo === "" ||
        $ciclo_escolar === "" ||
        $grupo === ""
    ) {

        $json["status"] =
            "warning";

        $json["answer"] =
            "Faltan datos para consultar las actividades";

        $json["code"] =
            400;

        return $json;
    }

if ($incluirCalificacionesGrupo === 1) {

    $sql = "
        SELECT
            ae.id,
            ae.nombre,
            ae.tipo,
            ae.orden,
            ca.alumno_id,
            ca.calificacion

        FROM actividades_evaluacion ae

        LEFT JOIN calificaciones_actividades ca
            ON ca.myuser_id = ae.myuser_id
            AND ca.actividad_id = ae.id
            AND ca.estado = 1
            AND ca.eliminado = 0

        WHERE
            ae.myuser_id = ?
            AND ae.asignatura_id = ?
            AND ae.criterio_id = ?
            AND ae.ciclo_escolar = ?
            AND ae.periodo = ?
            AND ae.grupo = ?
            AND ae.estado = 1
            AND ae.eliminado = 0

        ORDER BY
            ae.orden ASC,
            ae.id ASC,
            ca.alumno_id ASC
    ";


    $stmt =
        $this->conn->prepare(
            $sql
        );


    $stmt->bind_param(
        "siisss",
        $myuser_id,
        $asignatura_id,
        $criterio_id,
        $ciclo_escolar,
        $periodo,
        $grupo
    );

}else if ($alumno_id > 0) {

        $sql = "
            SELECT
                ae.id,
                ae.nombre,
                ae.tipo,
                ae.orden,
                ca.calificacion

            FROM actividades_evaluacion ae

            LEFT JOIN calificaciones_actividades ca
                ON ca.myuser_id = ae.myuser_id
                AND ca.actividad_id = ae.id
                AND ca.alumno_id = ?
                AND ca.eliminado = 0

            WHERE
                ae.myuser_id = ?
                AND ae.asignatura_id = ?
                AND ae.criterio_id = ?
                AND ae.ciclo_escolar = ?
                AND ae.periodo = ?
                AND ae.grupo = ?
                AND ae.estado = 1
                AND ae.eliminado = 0

            ORDER BY
                ae.orden ASC,
                ae.id ASC
        ";

        $stmt =
            $this->conn->prepare(
                $sql
            );

        $stmt->bind_param(
            "isiisss",
            $alumno_id,
            $myuser_id,
            $asignatura_id,
            $criterio_id,
            $ciclo_escolar,
            $periodo,
            $grupo
        );

    } else {

        $sql = "
            SELECT
                id,
                nombre,
                tipo,
                orden

            FROM actividades_evaluacion

            WHERE
                myuser_id = ?
                AND asignatura_id = ?
                AND criterio_id = ?
                AND ciclo_escolar = ?
                AND periodo = ?
                AND grupo = ?
                AND estado = 1
                AND eliminado = 0

            ORDER BY
                orden ASC,
                id ASC
        ";

        $stmt =
            $this->conn->prepare(
                $sql
            );

        $stmt->bind_param(
            "siisss",
            $myuser_id,
            $asignatura_id,
            $criterio_id,
            $ciclo_escolar,
            $periodo,
            $grupo
        );
    }


    if (!$stmt->execute()) {

        $json["status"] =
            "error";

        $json["answer"] =
            "No fue posible consultar las actividades";

        $json["code"] =
            500;

        $stmt->close();

        return $json;
    }


    $result =
        $stmt->get_result();

    $actividades = [];


    while (
        $row =
        $result->fetch_assoc()
    ) {

        if (
    array_key_exists(
        "alumno_id",
        $row
    )
) {

    $row["alumno_id"] =
        $row["alumno_id"] !== null
            ? (int)$row["alumno_id"]
            : null;
}

        $row["id"] =
            (int)$row["id"];

        $row["orden"] =
            (int)$row["orden"];

        if (
            isset($row["calificacion"])
        ) {

            $row["calificacion"] =
                $row["calificacion"] !== null
                    ? (float)$row["calificacion"]
                    : null;
        }

        $actividades[] =
            $row;
    }

    $stmt->close();

    $json["status"] = "ok";
    $json["answer"] = "Actividades consultadas correctamente";
    $json["results"] = $actividades;
    $json["total"] = count($actividades);
    $json["code"] = 200;

    return $json;
}

public function guardarActividadesEvaluacion($data) {

    $json = [];
    $myuser_id = $this->token;

    $asignatura_id = isset($data["asignatura_id"])
        ? (int)$data["asignatura_id"]
        : 0;

    $criterio_id = isset($data["criterio_id"])
        ? (int)$data["criterio_id"]
        : 0;

    $periodo = isset($data["periodo"])
        ? trim((string)$data["periodo"])
        : "";

    $ciclo_escolar = isset($data["ciclo_escolar"])
        ? trim((string)$data["ciclo_escolar"])
        : "";

    $grupo = isset($data["grupo"])
        ? trim((string)$data["grupo"])
        : "";

    $actividadesJson = isset($data["actividades"])
        ? $data["actividades"]
        : "[]";

    $actividades = json_decode(
        $actividadesJson,
        true
    );


    if (!$this->conn) {
        return [
            "status" => "error",
            "answer" => "No existe conexión con la base de datos",
            "code" => 501
        ];
    }


    if (
        $asignatura_id <= 0 ||
        $criterio_id <= 0 ||
        $periodo === "" ||
        $ciclo_escolar === "" ||
        $grupo === ""
    ) {
        return [
            "status" => "warning",
            "answer" => "Faltan datos para guardar las actividades",
            "code" => 400
        ];
    }


    if (!is_array($actividades) || count($actividades) === 0) {
        return [
            "status" => "warning",
            "answer" => "Agrega al menos una actividad",
            "code" => 400
        ];
    }

    $sql = "SELECT id
            FROM criterios_evaluacion
            WHERE myuser_id = ?
                AND asignatura_id = ?
                AND id = ?
                AND usa_actividades = 1
                AND estado = 1
                AND eliminado = 0
            LIMIT 1";

    $stmt = $this->conn->prepare($sql);

    if (!$stmt) {
        return [
            "status" => "error",
            "answer" => "No fue posible validar el criterio",
            "code" => 500
        ];
    }

    $stmt->bind_param(
        "sii",
        $myuser_id,
        $asignatura_id,
        $criterio_id
    );

    if (!$stmt->execute()) {
        error_log(
            "guardarActividadesEvaluacion criterio: " .
            $stmt->error
        );

        $stmt->close();

        return [
            "status" => "error",
            "answer" => "No fue posible validar el criterio",
            "code" => 500
        ];
    }

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();

        return [
            "status" => "warning",
            "answer" => "El criterio seleccionado no permite actividades",
            "code" => 400
        ];
    }

    $stmt->close();

    $existentes = [];

    $sql = "SELECT id
            FROM actividades_evaluacion
            WHERE myuser_id = ?
                AND asignatura_id = ?
                AND criterio_id = ?
                AND ciclo_escolar = ?
                AND periodo = ?
                AND grupo = ?
                AND eliminado = 0";

    $stmt = $this->conn->prepare($sql);

    if (!$stmt) {
        return [
            "status" => "error",
            "answer" => "No fue posible consultar las actividades actuales",
            "code" => 500
        ];
    }

    $stmt->bind_param(
        "siisss",
        $myuser_id,
        $asignatura_id,
        $criterio_id,
        $ciclo_escolar,
        $periodo,
        $grupo
    );

    if (!$stmt->execute()) {
        error_log(
            "guardarActividadesEvaluacion existentes: " .
            $stmt->error
        );

        $stmt->close();

        return [
            "status" => "error",
            "answer" => "No fue posible consultar las actividades actuales",
            "code" => 500
        ];
    }

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $existentes[(int)$row["id"]] = true;
    }

    $stmt->close();


    $conservadas = [];
    $guardadas = 0;


    foreach ($actividades as $index => $actividad) {

        if (!is_array($actividad)) {
            continue;
        }

        $id = isset($actividad["id"])
            ? (int)$actividad["id"]
            : 0;

        $nombre = isset($actividad["nombre"])
            ? trim((string)$actividad["nombre"])
            : "";

        $tipo = isset($actividad["tipo"])
            ? trim((string)$actividad["tipo"])
            : "actividad";

        $orden = isset($actividad["orden"])
            ? (int)$actividad["orden"]
            : $index + 1;


        if ($nombre === "") {
            return [
                "status" => "warning",
                "answer" => "Todas las actividades deben tener un nombre",
                "code" => 400
            ];
        }


        if ($tipo === "") {
            $tipo = "actividad";
        }

        if (function_exists("mb_substr")) {
            $nombre = mb_substr(
                $nombre,
                0,
                120,
                "UTF-8"
            );

            $tipo = mb_substr(
                $tipo,
                0,
                30,
                "UTF-8"
            );
        } else {
            $nombre = substr($nombre, 0, 120);
            $tipo = substr($tipo, 0, 30);
        }

        if ($id > 0) {

            if (!isset($existentes[$id])) {
                return [
                    "status" => "warning",
                    "answer" => "Una de las actividades no pertenece a esta evaluación",
                    "code" => 400
                ];
            }


            $sql = "UPDATE actividades_evaluacion
                    SET
                        nombre = ?,
                        tipo = ?,
                        orden = ?,
                        estado = 1,
                        eliminado = 0
                    WHERE myuser_id = ?
                        AND id = ?
                        AND asignatura_id = ?
                        AND criterio_id = ?
                        AND ciclo_escolar = ?
                        AND periodo = ?
                        AND grupo = ?";

            $stmt = $this->conn->prepare($sql);

            if (!$stmt) {
                return [
                    "status" => "error",
                    "answer" => "No fue posible preparar la actualización de actividades",
                    "code" => 500
                ];
            }


            $stmt->bind_param(
                "ssisiiisss",
                $nombre,
                $tipo,
                $orden,
                $myuser_id,
                $id,
                $asignatura_id,
                $criterio_id,
                $ciclo_escolar,
                $periodo,
                $grupo
            );


            if (!$stmt->execute()) {

                error_log(
                    "guardarActividadesEvaluacion UPDATE: " .
                    $stmt->error
                );

                $stmt->close();

                return [
                    "status" => "error",
                    "answer" => "No fue posible actualizar las actividades",
                    "code" => 500
                ];
            }


            $conservadas[$id] = true;

            $stmt->close();

        } else {

            $sql = "INSERT INTO actividades_evaluacion (
                        myuser_id,
                        asignatura_id,
                        criterio_id,
                        ciclo_escolar,
                        periodo,
                        grupo,
                        nombre,
                        tipo,
                        orden,
                        estado,
                        eliminado
                    ) VALUES (
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        1,
                        0
                    )";

            $stmt = $this->conn->prepare($sql);

            if (!$stmt) {
                return [
                    "status" => "error",
                    "answer" => "No fue posible preparar el registro de actividades",
                    "code" => 500
                ];
            }


            $stmt->bind_param(
                "siisssssi",
                $myuser_id,
                $asignatura_id,
                $criterio_id,
                $ciclo_escolar,
                $periodo,
                $grupo,
                $nombre,
                $tipo,
                $orden
            );


            if (!$stmt->execute()) {

                error_log(
                    "guardarActividadesEvaluacion INSERT: " .
                    $stmt->error
                );

                $stmt->close();

                return [
                    "status" => "error",
                    "answer" => "No fue posible registrar las actividades",
                    "code" => 500
                ];
            }


            $nuevoId = (int)$this->conn->insert_id;

            $conservadas[$nuevoId] = true;

            $stmt->close();
        }


        $guardadas++;
    }


    foreach ($existentes as $idExistente => $valor) {

        if (isset($conservadas[$idExistente])) {
            continue;
        }


        $sql = "UPDATE actividades_evaluacion
                SET
                    eliminado = 1,
                    estado = 0
                WHERE myuser_id = ?
                    AND id = ?";

        $stmt = $this->conn->prepare($sql);

        if ($stmt) {

            $stmt->bind_param(
                "si",
                $myuser_id,
                $idExistente
            );

            $stmt->execute();
            $stmt->close();
        }

        $sql = "UPDATE calificaciones_actividades
                SET
                    eliminado = 1,
                    estado = 0
                WHERE myuser_id = ?
                    AND actividad_id = ?";

        $stmt = $this->conn->prepare($sql);

        if ($stmt) {

            $stmt->bind_param(
                "si",
                $myuser_id,
                $idExistente
            );

            $stmt->execute();
            $stmt->close();
        }
    }

    /*
 * Recalcular los promedios del criterio
 * después de agregar, editar o eliminar actividades.
 */
$sql = "UPDATE calificaciones c
        INNER JOIN alumnos a ON a.myuser_id = c.myuser_id AND a.id = c.alumno_id
        SET c.calificacion = NULL
        WHERE c.myuser_id = ? AND c.asignatura_id = ? AND c.criterio_id = ?
        AND c.periodo = ? AND c.ciclo_escolar = ? AND c.eliminado = 0
        AND CONCAT(TRIM(a.grado), '° ', TRIM(a.grupo)) = ?";

$stmt = $this->conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("siisss", $myuser_id, $asignatura_id, $criterio_id, $periodo, $ciclo_escolar, $grupo);
    $stmt->execute();
    $stmt->close();
}

$sql = "SELECT ca.alumno_id, ce.redondear_resultado, AVG(ca.calificacion) AS promedio
        FROM actividades_evaluacion ae
        INNER JOIN criterios_evaluacion ce
            ON ce.myuser_id COLLATE utf8mb4_spanish_ci = ae.myuser_id COLLATE utf8mb4_spanish_ci
            AND ce.id = ae.criterio_id
        INNER JOIN calificaciones_actividades ca
            ON ca.myuser_id COLLATE utf8mb4_spanish_ci = ae.myuser_id COLLATE utf8mb4_spanish_ci
            AND ca.actividad_id = ae.id
            AND ca.estado = 1
            AND ca.eliminado = 0
        WHERE ae.myuser_id COLLATE utf8mb4_spanish_ci = ?
        AND ae.asignatura_id = ?
        AND ae.criterio_id = ?
        AND ae.ciclo_escolar = ?
        AND ae.periodo = ?
        AND ae.grupo = ?
        AND ae.estado = 1
        AND ae.eliminado = 0
        AND ca.calificacion IS NOT NULL
        GROUP BY ca.alumno_id, ce.redondear_resultado";

$stmt = $this->conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("siisss", $myuser_id, $asignatura_id, $criterio_id, $ciclo_escolar, $periodo, $grupo);
    $stmt->execute();
    $result = $stmt->get_result();

    $sqlUpdate = "UPDATE calificaciones
                  SET calificacion = ?
                  WHERE myuser_id = ? AND alumno_id = ? AND asignatura_id = ?
                  AND criterio_id = ? AND periodo = ? AND ciclo_escolar = ?
                  AND eliminado = 0";

    $stmtUpdate = $this->conn->prepare($sqlUpdate);

    while ($row = $result->fetch_assoc()) {
        $promedio = round((float)$row["promedio"], 2);

        if ((int)$row["redondear_resultado"] === 1) {
            $promedio = round($promedio);
        }

        if ($stmtUpdate) {
            $alumnoIdPromedio = (int)$row["alumno_id"];
            $stmtUpdate->bind_param(
                "dsiiiss",
                $promedio,
                $myuser_id,
                $alumnoIdPromedio,
                $asignatura_id,
                $criterio_id,
                $periodo,
                $ciclo_escolar
            );
            $stmtUpdate->execute();
        }
    }

    if ($stmtUpdate) $stmtUpdate->close();
    $stmt->close();
}

    $json["status"] = "ok";
    $json["answer"] = "Actividades guardadas correctamente";
    $json["guardadas"] = $guardadas;
    $json["total"] = count($actividades);
    $json["code"] = 200;

    return $json;
}

public function guardarCalificacionesActividades($data) {

    $json = [];
    $myuser_id = $this->token;

    $asignatura_id = isset($data["asignatura_id"])
        ? (int)$data["asignatura_id"]
        : 0;

    $criterio_id = isset($data["criterio_id"])
        ? (int)$data["criterio_id"]
        : 0;

    $alumno_id = isset($data["alumno_id"])
        ? (int)$data["alumno_id"]
        : 0;

    $maestro_id = isset($data["maestro_id"])
        ? (int)$data["maestro_id"]
        : 0;

    $periodo = isset($data["periodo"])
        ? trim((string)$data["periodo"])
        : "";

    $ciclo_escolar = isset($data["ciclo_escolar"])
        ? trim((string)$data["ciclo_escolar"])
        : "";

    $grupo = isset($data["grupo"])
        ? trim((string)$data["grupo"])
        : "";

    $calificacionesJson = isset($data["calificaciones"])
        ? $data["calificaciones"]
        : "[]";

    $calificaciones = json_decode(
        $calificacionesJson,
        true
    );


    if (!$this->conn) {
        return [
            "status" => "error",
            "answer" => "No existe conexión con la base de datos",
            "code" => 501
        ];
    }


    if (
        $asignatura_id <= 0 ||
        $criterio_id <= 0 ||
        $alumno_id <= 0 ||
        $periodo === "" ||
        $ciclo_escolar === "" ||
        $grupo === ""
    ) {
        return [
            "status" => "warning",
            "answer" => "Faltan datos para guardar las calificaciones de las actividades",
            "code" => 400
        ];
    }


    if (
        !is_array($calificaciones) ||
        count($calificaciones) === 0
    ) {
        return [
            "status" => "warning",
            "answer" => "No se recibieron actividades para calificar",
            "code" => 400
        ];
    }


    /*
     * Validar alumno.
     */
    $sql = "SELECT id
            FROM alumnos
            WHERE myuser_id = ?
                AND id = ?
                AND eliminado = 0
            LIMIT 1";

    $stmt = $this->conn->prepare($sql);

    if (!$stmt) {
        return [
            "status" => "error",
            "answer" => "No fue posible validar el alumno",
            "code" => 500
        ];
    }


    $stmt->bind_param(
        "si",
        $myuser_id,
        $alumno_id
    );


    if (!$stmt->execute()) {

        $stmt->close();

        return [
            "status" => "error",
            "answer" => "No fue posible validar el alumno",
            "code" => 500
        ];
    }


    $result = $stmt->get_result();

    if ($result->num_rows === 0) {

        $stmt->close();

        return [
            "status" => "warning",
            "answer" => "El alumno seleccionado no existe",
            "code" => 404
        ];
    }

    $stmt->close();


    /*
     * Validar criterio.
     */
    $sql = "SELECT id
            FROM criterios_evaluacion
            WHERE myuser_id = ?
                AND asignatura_id = ?
                AND id = ?
                AND usa_actividades = 1
                AND estado = 1
                AND eliminado = 0
            LIMIT 1";

    $stmt = $this->conn->prepare($sql);

    if (!$stmt) {
        return [
            "status" => "error",
            "answer" => "No fue posible validar el criterio",
            "code" => 500
        ];
    }


    $stmt->bind_param(
        "sii",
        $myuser_id,
        $asignatura_id,
        $criterio_id
    );


    if (!$stmt->execute()) {

        $stmt->close();

        return [
            "status" => "error",
            "answer" => "No fue posible validar el criterio",
            "code" => 500
        ];
    }


    $result = $stmt->get_result();

    if ($result->num_rows === 0) {

        $stmt->close();

        return [
            "status" => "warning",
            "answer" => "El criterio seleccionado no utiliza actividades",
            "code" => 400
        ];
    }

    $stmt->close();


    /*
     * Validar maestro sólo si fue enviado.
     */
    if ($maestro_id > 0) {

        $sql = "SELECT id
                FROM maestros
                WHERE myuser_id = ?
                    AND id = ?
                    AND eliminado = 0
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            return [
                "status" => "error",
                "answer" => "No fue posible validar el maestro",
                "code" => 500
            ];
        }


        $stmt->bind_param(
            "si",
            $myuser_id,
            $maestro_id
        );


        if (!$stmt->execute()) {

            $stmt->close();

            return [
                "status" => "error",
                "answer" => "No fue posible validar el maestro",
                "code" => 500
            ];
        }


        $result = $stmt->get_result();

        if ($result->num_rows === 0) {

            $stmt->close();

            return [
                "status" => "warning",
                "answer" => "El maestro seleccionado no existe",
                "code" => 404
            ];
        }

        $stmt->close();
    }


    /*
     * Obtener las actividades válidas
     * del criterio/grupo/periodo.
     */
    $actividadesValidas = [];

    $sql = "SELECT id
            FROM actividades_evaluacion
            WHERE myuser_id = ?
                AND asignatura_id = ?
                AND criterio_id = ?
                AND ciclo_escolar = ?
                AND periodo = ?
                AND grupo = ?
                AND estado = 1
                AND eliminado = 0";

    $stmt = $this->conn->prepare($sql);

    if (!$stmt) {
        return [
            "status" => "error",
            "answer" => "No fue posible consultar las actividades",
            "code" => 500
        ];
    }


    $stmt->bind_param(
        "siisss",
        $myuser_id,
        $asignatura_id,
        $criterio_id,
        $ciclo_escolar,
        $periodo,
        $grupo
    );


    if (!$stmt->execute()) {

        $stmt->close();

        return [
            "status" => "error",
            "answer" => "No fue posible consultar las actividades",
            "code" => 500
        ];
    }


    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $actividadesValidas[(int)$row["id"]] = true;
    }

    $stmt->close();


    if (count($actividadesValidas) === 0) {
        return [
            "status" => "warning",
            "answer" => "Este criterio todavía no tiene actividades configuradas",
            "code" => 400
        ];
    }

    /*
 * Validar TODAS las calificaciones
 * antes de escribir en la BD.
 */
foreach ($calificaciones as $item) {

    if (!is_array($item)) {

        return [
            "status" => "warning",
            "answer" => "Una de las calificaciones tiene un formato inválido",
            "code" => 400
        ];
    }


    $actividad_id =
        isset($item["actividad_id"])
            ? (int)$item["actividad_id"]
            : 0;


    if (
        $actividad_id <= 0 ||
        !isset(
            $actividadesValidas[
                $actividad_id
            ]
        )
    ) {

        return [
            "status" => "warning",
            "answer" => "Una de las actividades no pertenece a esta evaluación",
            "code" => 400
        ];
    }


    $valor =
        array_key_exists(
            "calificacion",
            $item
        )
            ? $item["calificacion"]
            : null;


    /*
     * NULL sí es válido:
     * significa pendiente.
     */
    if (
        $valor === null ||
        $valor === ""
    ) {
        continue;
    }


    if (!is_numeric($valor)) {

        return [
            "status" => "warning",
            "answer" => "Las calificaciones deben ser numéricas",
            "code" => 400
        ];
    }


    $valor =
        (float)$valor;


    if (
        !is_finite($valor) ||
        $valor < 0 ||
        $valor > 10
    ) {

        return [
            "status" => "warning",
            "answer" => "Las calificaciones deben estar entre 0 y 10",
            "code" => 400
        ];
    }
}


 
    $sql = "INSERT INTO calificaciones_actividades (
                myuser_id,
                actividad_id,
                alumno_id,
                maestro_id,
                calificacion,
                observaciones,
                estado,
                eliminado
            ) VALUES (
                ?,
                ?,
                ?,
                NULLIF(?, 0),
                NULLIF(?, ''),
                ?,
                1,
                0
            )
            ON DUPLICATE KEY UPDATE
                maestro_id = VALUES(maestro_id),
                calificacion = VALUES(calificacion),
                observaciones = VALUES(observaciones),
                estado = 1,
                eliminado = 0";

    $stmt = $this->conn->prepare($sql);

    if (!$stmt) {
        return [
            "status" => "error",
            "answer" => "No fue posible preparar el guardado de las calificaciones",
            "code" => 500
        ];
    }


    $guardadas = 0;


    foreach ($calificaciones as $item) {

        if (!is_array($item)) {
            continue;
        }


        $actividad_id = isset($item["actividad_id"])
            ? (int)$item["actividad_id"]
            : 0;

        $valor = array_key_exists(
            "calificacion",
            $item
        )
            ? $item["calificacion"]
            : null;

        $observaciones = isset($item["observaciones"])
            ? trim((string)$item["observaciones"])
            : "";


        if (
            $actividad_id <= 0 ||
            !isset($actividadesValidas[$actividad_id])
        ) {
            $stmt->close();

            return [
                "status" => "warning",
                "answer" => "Una de las actividades no pertenece a esta evaluación",
                "code" => 400
            ];
        }


        $calificacionDb = "";


        if (
            $valor !== null &&
            $valor !== ""
        ) {

            if (!is_numeric($valor)) {

                $stmt->close();

                return [
                    "status" => "warning",
                    "answer" => "Las calificaciones deben ser numéricas",
                    "code" => 400
                ];
            }


            $calificacion = round(
                (float)$valor,
                2
            );


            if (
                $calificacion < 0 ||
                $calificacion > 10
            ) {

                $stmt->close();

                return [
                    "status" => "warning",
                    "answer" => "Las calificaciones deben estar entre 0 y 10",
                    "code" => 400
                ];
            }


            $calificacionDb = number_format(
                $calificacion,
                2,
                ".",
                ""
            );
        }


        $stmt->bind_param(
            "siiiss",
            $myuser_id,
            $actividad_id,
            $alumno_id,
            $maestro_id,
            $calificacionDb,
            $observaciones
        );


        if (!$stmt->execute()) {

            error_log(
                "guardarCalificacionesActividades: " .
                $stmt->error
            );

            $stmt->close();

            return [
                "status" => "error",
                "answer" => "No fue posible guardar todas las calificaciones",
                "code" => 500
            ];
        }


        $guardadas++;
    }


    $stmt->close();


    /*
     * Calcular el promedio actual del criterio.
     * Los espacios vacíos quedan pendientes y
     * no cuentan como cero.
     */
    $sql = "SELECT
                COUNT(ae.id) AS total_actividades,
                COUNT(ca.calificacion) AS capturadas,
                AVG(ca.calificacion) AS promedio
            FROM actividades_evaluacion ae
            LEFT JOIN calificaciones_actividades ca
                ON ca.myuser_id = ae.myuser_id
                AND ca.actividad_id = ae.id
                AND ca.alumno_id = ?
                AND ca.estado = 1
                AND ca.eliminado = 0
            WHERE ae.myuser_id = ?
                AND ae.asignatura_id = ?
                AND ae.criterio_id = ?
                AND ae.ciclo_escolar = ?
                AND ae.periodo = ?
                AND ae.grupo = ?
                AND ae.estado = 1
                AND ae.eliminado = 0";

    $stmt = $this->conn->prepare($sql);

    if (!$stmt) {
        return [
            "status" => "error",
            "answer" => "Las calificaciones se guardaron, pero no fue posible calcular el promedio",
            "code" => 500
        ];
    }


    $stmt->bind_param(
        "isiisss",
        $alumno_id,
        $myuser_id,
        $asignatura_id,
        $criterio_id,
        $ciclo_escolar,
        $periodo,
        $grupo
    );


    if (!$stmt->execute()) {

        $stmt->close();

        return [
            "status" => "error",
            "answer" => "Las calificaciones se guardaron, pero no fue posible calcular el promedio",
            "code" => 500
        ];
    }


    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    $stmt->close();


    $promedio = null;
    $capturadas = 0;
    $totalActividades = 0;


    if ($row) {

        $totalActividades =
            (int)$row["total_actividades"];

        $capturadas =
            (int)$row["capturadas"];

        if ($row["promedio"] !== null) {
            $promedio = round(
                (float)$row["promedio"],
                2
            );
        }
    }


    $json["status"] = "ok";
    $json["answer"] = "Calificaciones de actividades guardadas correctamente";
    $json["guardadas"] = $guardadas;
    $json["promedio"] = $promedio;
    $json["capturadas"] = $capturadas;
    $json["total_actividades"] = $totalActividades;
    $json["completo"] = (
        $totalActividades > 0 &&
        $capturadas === $totalActividades
    ) ? 1 : 0;
    $json["code"] = 200;

    return $json;
}

//Calificaciones

public function getCalificaciones($data) {
    $json = [];
    $myuser_id = $this->token;

    $asignatura_id = isset($data["asignatura_id"]) ? (int)$data["asignatura_id"] : 0;
    $periodo = isset($data["periodo"]) ? trim((string)$data["periodo"]) : "";
    $ciclo_escolar = isset($data["ciclo_escolar"]) ? trim((string)$data["ciclo_escolar"]) : "";

    $valido = true;

    if (!$this->conn) {
        $json["status"] = "error";
        $json["answer"] = "No existe conexión con la base de datos";
        $json["code"] = 501;
        $valido = false;
    } elseif ($asignatura_id <= 0 || $periodo === "" || $ciclo_escolar === "") {
        $json["status"] = "warning";
        $json["answer"] = "Selecciona una asignatura, un periodo y un ciclo escolar";
        $json["code"] = 400;
        $valido = false;
    }

    if ($valido) {
        $sql = "SELECT id
                FROM asignaturas
                WHERE myuser_id = ? AND id = ? AND eliminado = 0
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("si", $myuser_id, $asignatura_id);

            if ($stmt->execute()) {
                $result = $stmt->get_result();

                if ($result->num_rows === 0) {
                    $json["status"] = "warning";
                    $json["answer"] = "La asignatura seleccionada no existe";
                    $json["code"] = 404;
                    $valido = false;
                }
            } else {
                error_log("getCalificaciones asignatura execute: " . $stmt->error);

                $json["status"] = "error";
                $json["answer"] = "No fue posible validar la asignatura";
                $json["code"] = 500;
                $valido = false;
            }

            $stmt->close();
        } else {
            error_log("getCalificaciones asignatura prepare: " . $this->conn->error);

            $json["status"] = "error";
            $json["answer"] = "Error en la validación de la asignatura";
            $json["code"] = 500;
            $valido = false;
        }
    }

    if ($valido) {
        $sql = "SELECT
                    c.id,
                    c.alumno_id,
                    a.matricula,
                    c.maestro_id,
                    c.asignatura_id,
                    c.criterio_id,
                    c.periodo,
                    c.ciclo_escolar,
                    c.calificacion,
                    c.valor_texto,
                    c.observaciones,
                    c.estado
                FROM calificaciones c
                INNER JOIN alumnos a
                    ON a.myuser_id = c.myuser_id
                    AND a.id = c.alumno_id
                WHERE c.myuser_id = ?
                    AND c.asignatura_id = ?
                    AND c.periodo = ?
                    AND c.ciclo_escolar = ?
                    AND c.eliminado = 0
                ORDER BY a.apellido ASC, a.nombre ASC, c.criterio_id ASC";

        $stmt = $this->conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param(
                "siss",
                $myuser_id,
                $asignatura_id,
                $periodo,
                $ciclo_escolar
            );

            if ($stmt->execute()) {
                $result = $stmt->get_result();

                $calificaciones = [];
                $grades = [];

                while ($row = $result->fetch_assoc()) {
                    $row["id"] = (int)$row["id"];
                    $row["alumno_id"] = (int)$row["alumno_id"];
                    $row["maestro_id"] = $row["maestro_id"] !== null
                        ? (int)$row["maestro_id"]
                        : null;

                    $row["asignatura_id"] = (int)$row["asignatura_id"];
                    $row["criterio_id"] = (int)$row["criterio_id"];
                    $row["estado"] = (int)$row["estado"];

                    $row["calificacion"] = $row["calificacion"] !== null
                        ? (float)$row["calificacion"]
                        : null;

                    $row["valor_texto"] =
    $row["valor_texto"] !== null
        ? trim(
            (string)$row["valor_texto"]
        )
        : null;

                    $calificaciones[] = $row;

                    $matricula = $row["matricula"];
                    $criterio_id = $row["criterio_id"];

                    if (!isset($grades[$matricula])) {
                        $grades[$matricula] = [];
                    }

                    //$grades[$matricula][$criterio_id] = $row["calificacion"];
                    $grades[$matricula][$criterio_id] = $row["valor_texto"] !== null && $row["valor_texto"] !== "" ? $row["valor_texto"] : $row["calificacion"];
                }

                $json["status"] = "ok";
                $json["answer"] = "Calificaciones consultadas correctamente";
                $json["results"] = $calificaciones;
                $json["grades"] = $grades;
                $json["total"] = count($calificaciones);
                $json["code"] = 200;
            } else {
                error_log("getCalificaciones execute: " . $stmt->error);

                $json["status"] = "error";
                $json["answer"] = "No fue posible consultar las calificaciones";
                $json["code"] = 500;
            }

            $stmt->close();
        } else {
            error_log("getCalificaciones prepare: " . $this->conn->error);

            $json["status"] = "error";
            $json["answer"] = "Error en la preparación de la consulta de calificaciones";
            $json["code"] = 500;
        }
    }

    return $json;
}

public function guardarCalificaciones($data) {
    $json = [];
    $myuser_id = $this->token;

    $asignatura_id = isset($data["asignatura_id"]) ? (int)$data["asignatura_id"] : 0;
    $maestro_id = isset($data["maestro_id"]) ? (int)$data["maestro_id"] : 0;
    $periodo = isset($data["periodo"]) ? trim((string)$data["periodo"]) : "";
    $ciclo_escolar = isset($data["ciclo_escolar"]) ? trim((string)$data["ciclo_escolar"]) : "";

    $calificacionesJson = isset($data["calificaciones"])
        ? $data["calificaciones"]
        : "[]";

    $calificacionesRecibidas = json_decode($calificacionesJson, true);

    $valido = true;
    $criteriosValidos = [];
    $alumnosValidados = [];
    $calificaciones = [];

    if (!$this->conn) {
        $json["status"] = "error";
        $json["answer"] = "No existe conexión con la base de datos";
        $json["code"] = 501;
        $valido = false;
    } elseif ($asignatura_id <= 0 || $periodo === "" || $ciclo_escolar === "") {
        $json["status"] = "warning";
        $json["answer"] = "Selecciona una asignatura, un periodo y un ciclo escolar";
        $json["code"] = 400;
        $valido = false;
    } elseif (!is_array($calificacionesRecibidas) || count($calificacionesRecibidas) === 0) {
        $json["status"] = "warning";
        $json["answer"] = "No se recibieron calificaciones para guardar";
        $json["code"] = 400;
        $valido = false;
    }


    if ($valido) {
        $sql = "SELECT id
                FROM asignaturas
                WHERE myuser_id = ? AND id = ? AND eliminado = 0
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("si", $myuser_id, $asignatura_id);

            if ($stmt->execute()) {
                $result = $stmt->get_result();

                if ($result->num_rows === 0) {
                    $json["status"] = "warning";
                    $json["answer"] = "La asignatura seleccionada no existe";
                    $json["code"] = 404;
                    $valido = false;
                }
            } else {
                error_log("guardarCalificaciones asignatura execute: " . $stmt->error);

                $json["status"] = "error";
                $json["answer"] = "No fue posible validar la asignatura";
                $json["code"] = 500;
                $valido = false;
            }

            $stmt->close();
        } else {
            $json["status"] = "error";
            $json["answer"] = "Error al validar la asignatura";
            $json["code"] = 500;
            $valido = false;
        }
    }


    if ($valido && $maestro_id > 0) {
        $sql = "SELECT id
                FROM maestros
                WHERE myuser_id = ? AND id = ? AND eliminado = 0
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("si", $myuser_id, $maestro_id);

            if ($stmt->execute()) {
                $result = $stmt->get_result();

                if ($result->num_rows === 0) {
                    $json["status"] = "warning";
                    $json["answer"] = "El maestro seleccionado no existe";
                    $json["code"] = 404;
                    $valido = false;
                }
            } else {
                error_log("guardarCalificaciones maestro execute: " . $stmt->error);

                $json["status"] = "error";
                $json["answer"] = "No fue posible validar el maestro";
                $json["code"] = 500;
                $valido = false;
            }

            $stmt->close();
        } else {
            $json["status"] = "error";
            $json["answer"] = "Error al validar el maestro";
            $json["code"] = 500;
            $valido = false;
        }
    }

    if ($valido) {
        $sql = "SELECT
            id,
            tipo_captura,
            afecta_promedio,
            porcentaje
        FROM criterios_evaluacion
        WHERE myuser_id = ?
            AND asignatura_id = ?
            AND eliminado = 0";

        $stmt = $this->conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("si", $myuser_id, $asignatura_id);

            if ($stmt->execute()) {
                $result = $stmt->get_result();

                while ($row = $result->fetch_assoc()) {

    $criteriosValidos[
        (int)$row["id"]
    ] = [
        "tipo_captura" =>
            $row["tipo_captura"] ?: "numero",

        "afecta_promedio" =>
            (int)$row["afecta_promedio"],

            
        "porcentaje" =>
        (float)$row["porcentaje"]
    ];
}



                if (count($criteriosValidos) === 0) {
                    $json["status"] = "warning";
                    $json["answer"] = "La asignatura no tiene criterios de evaluación";
                    $json["code"] = 400;
                    $valido = false;
                }
            } else {
                error_log("guardarCalificaciones criterios execute: " . $stmt->error);

                $json["status"] = "error";
                $json["answer"] = "No fue posible validar los criterios de evaluación";
                $json["code"] = 500;
                $valido = false;
            }

            $stmt->close();
        } else {
            $json["status"] = "error";
            $json["answer"] = "Error al validar los criterios de evaluación";
            $json["code"] = 500;
            $valido = false;
        }
    }

    if ($valido) {
        foreach ($calificacionesRecibidas as $item) {
            if (!is_array($item)) {
                $json["status"] = "warning";
                $json["answer"] = "Una de las calificaciones tiene un formato inválido";
                $json["code"] = 400;
                $valido = false;
                break;
            }

            $alumno_id = isset($item["alumno_id"])
                ? (int)$item["alumno_id"]
                : 0;

            $criterio_id = isset($item["criterio_id"])
                ? (int)$item["criterio_id"]
                : 0;

            $observaciones = isset($item["observaciones"])
                ? trim((string)$item["observaciones"])
                : "";

            $valor = isset($item["calificacion"])
                ? $item["calificacion"]
                : null;

            $calificacion = null;
            $valorTexto = null;

            if ($alumno_id <= 0 || $criterio_id <= 0) {
                $json["status"] = "warning";
                $json["answer"] = "Una de las calificaciones no tiene alumno o criterio válido";
                $json["code"] = 400;
                $valido = false;
                break;
            }

            if (!isset($criteriosValidos[$criterio_id])) {
                $json["status"] = "warning";
                $json["answer"] = "Uno de los criterios no pertenece a la asignatura seleccionada";
                $json["code"] = 400;
                $valido = false;
                break;
            }


$tipoCaptura =
    $criteriosValidos[
        $criterio_id
    ]["tipo_captura"];


/*
 * CONDUCTA
 */
if ($tipoCaptura === "conducta") {

    if (
        $valor !== null &&
        $valor !== ""
    ) {

        $valorTexto =
            strtoupper(
                trim((string)$valor)
            );


        if (
            !in_array(
                $valorTexto,
                ["B", "R", "M"],
                true
            )
        ) {

            $json["status"] =
                "warning";

            $json["answer"] =
                "La conducta sólo puede ser B, R o M";

            $json["code"] =
                400;

            $valido =
                false;

            break;
        }
    }


/*
 * CALIFICACIÓN NUMÉRICA
 */
} elseif ($tipoCaptura === "inasistencias") {

    if ($valor !== null && $valor !== "") {
        if (!is_numeric($valor) || (float)$valor < 0 || floor((float)$valor) != (float)$valor) {
            $json["status"] = "warning";
            $json["answer"] = "Las inasistencias deben ser un número entero mayor o igual a 0";
            $json["code"] = 400;
            $valido = false;
            break;
        }

        $calificacion = (int)$valor;
    }

} else {

    if (
        $valor !== null &&
        $valor !== ""
    ) {

        if (!is_numeric($valor)) {

            $json["status"] =
                "warning";

            $json["answer"] =
                "La calificación debe ser un valor numérico";

            $json["code"] =
                400;

            $valido =
                false;

            break;
        }


        $calificacion =
            round(
                (float)$valor,
                2
            );


        if (
            $calificacion < 0 ||
            $calificacion > 10
        ) {

            $json["status"] =
                "warning";

            $json["answer"] =
                "Las calificaciones deben estar entre 0 y 10";

            $json["code"] =
                400;

            $valido =
                false;

            break;
        }
    }
}

$aportacion = null;

if (
    $calificacion !== null &&
    (int)$criteriosValidos[
        $criterio_id
    ]["afecta_promedio"] === 1
) {

    $porcentaje =
        (float)$criteriosValidos[
            $criterio_id
        ]["porcentaje"];

    $aportacion = round(
        $calificacion *
        ($porcentaje / 100),
        2
    );
}

            $key = $alumno_id . "-" . $criterio_id;

            $calificaciones[$key] = [
                "alumno_id" => $alumno_id,
                "criterio_id" => $criterio_id,
                "calificacion" => $calificacion,
                "aportacion" => $aportacion,
                "valor_texto" => $valorTexto,
                "observaciones" => $observaciones
            ];

            $alumnosValidados[$alumno_id] = false;
        }
    }

    if ($valido) {
        $sql = "SELECT id
                FROM alumnos
                WHERE myuser_id = ? AND id = ? AND eliminado = 0
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);

        if ($stmt) {
            foreach ($alumnosValidados as $alumno_id => $estadoAlumno) {
                $idAlumnoValidar = (int)$alumno_id;

                $stmt->bind_param(
                    "si",
                    $myuser_id,
                    $idAlumnoValidar
                );

                if (!$stmt->execute()) {
                    error_log("guardarCalificaciones alumno execute: " . $stmt->error);

                    $json["status"] = "error";
                    $json["answer"] = "No fue posible validar los alumnos";
                    $json["code"] = 500;
                    $valido = false;
                    break;
                }

                $result = $stmt->get_result();

                if ($result->num_rows === 0) {
                    $json["status"] = "warning";
                    $json["answer"] = "Uno de los alumnos seleccionados no existe";
                    $json["code"] = 404;
                    $valido = false;
                    break;
                }

                $alumnosValidados[$alumno_id] = true;
            }

            $stmt->close();
        } else {
            $json["status"] = "error";
            $json["answer"] = "Error al validar los alumnos";
            $json["code"] = 500;
            $valido = false;
        }
    }

    if ($valido) {
        $sql = "INSERT INTO calificaciones (
            myuser_id,
            alumno_id,
            maestro_id,
            asignatura_id,
            criterio_id,
            periodo,
            ciclo_escolar,
            calificacion,
            valor_texto,
            observaciones,
            estado,
            eliminado
        ) VALUES (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            NULLIF(?, ''),
            NULLIF(?, ''),
            ?,
            1,
            0
        )
        ON DUPLICATE KEY UPDATE
            maestro_id = VALUES(maestro_id),
            calificacion = VALUES(calificacion),
            valor_texto = VALUES(valor_texto),
            observaciones = VALUES(observaciones),
            estado = 1,
            eliminado = 0";

        $stmt = $this->conn->prepare($sql);

        if ($stmt) {
            $guardadas = 0;
            $total = count($calificaciones);

            foreach ($calificaciones as $item) {
                $alumno_id = (int)$item["alumno_id"];
                $criterio_id = (int)$item["criterio_id"];
                $valorTextoDb = isset($item["valor_texto"]) && $item["valor_texto"] !== null ? $item["valor_texto"] : "";
                $observaciones = $item["observaciones"];

                $calificacionDb = $item["calificacion"] === null
                    ? ""
                    : number_format(
                        (float)$item["calificacion"],
                        2,
                        ".",
                        ""
                    );

                $stmt->bind_param(
    "siiiisssss",
    $myuser_id,
    $alumno_id,
    $maestro_id,
    $asignatura_id,
    $criterio_id,
    $periodo,
    $ciclo_escolar,
    $calificacionDb,
    $valorTextoDb,
    $observaciones
);

                if ($stmt->execute()) {
                    $guardadas++;
                } else {
                    error_log(
                        "guardarCalificaciones execute: " .
                        $stmt->error
                    );

                    $valido = false;
                    break;
                }
            }

            $stmt->close();

            if ($valido) {
                $json["status"] = "ok";
                $json["answer"] = $guardadas === 1
                    ? "Calificación guardada correctamente"
                    : "Calificaciones guardadas correctamente";

                $json["guardadas"] = $guardadas;
                $json["total"] = $total;
                $json["code"] = 200;
            } else {
                $json["status"] = "error";
                $json["answer"] = "No fue posible guardar todas las calificaciones";
                $json["guardadas"] = $guardadas;
                $json["total"] = $total;
                $json["code"] = 500;
            }
        } else {
            error_log(
                "guardarCalificaciones prepare: " .
                $this->conn->error
            );

            $json["status"] = "error";
            $json["answer"] = "Error en la preparación del guardado de calificaciones";
            $json["code"] = 500;
        }
    }

    return $json;
}

public function getAprovechamientoEscolar($data) {
    $json = [];
    $myuser_id = $this->token;

    $ciclo_escolar = isset($data["ciclo_escolar"])
        ? trim((string)$data["ciclo_escolar"])
        : "";

    $maestro_id = isset($data["maestro_id"])
        ? (int)$data["maestro_id"]
        : 0;

    $periodos = [
        "Primer trimestre",
        "Segundo trimestre",
        "Tercer trimestre"
    ];

    if (isset($data["periodos"])) {
        $periodosRecibidos = is_array($data["periodos"])
            ? $data["periodos"]
            : json_decode($data["periodos"], true);

        if (
            is_array($periodosRecibidos) &&
            count($periodosRecibidos) > 0
        ) {
            $periodos = [];

            foreach ($periodosRecibidos as $periodo) {
                $periodo = trim((string)$periodo);

                if ($periodo !== "") {
                    $periodos[] = $periodo;
                }
            }
        }
    }

    $valido = true;
    $existencias = [];
    $detalle = [];
    $resultados = [];

    if (!$this->conn) {
        $json["status"] = "error";
        $json["answer"] = "No existe conexión con la base de datos";
        $json["code"] = 501;
        $valido = false;
    } elseif ($ciclo_escolar === "") {
        $json["status"] = "warning";
        $json["answer"] = "Selecciona un ciclo escolar";
        $json["code"] = 400;
        $valido = false;
    } elseif (count($periodos) === 0) {
        $json["status"] = "warning";
        $json["answer"] = "No se recibieron periodos de evaluación";
        $json["code"] = 400;
        $valido = false;
    }

    /*
     * Existencia real por grado y grupo.
     *
     * Aquí contamos alumnos activos, no solamente
     * alumnos que ya tengan calificaciones.
     */
    if ($valido) {
        $sql = "SELECT
                    grado,
                    grupo,
                    COUNT(*) AS existencia
                FROM alumnos
                WHERE myuser_id = ?
                    AND estado = 1
                    AND eliminado = 0
                GROUP BY grado, grupo
                ORDER BY grado ASC, grupo ASC";

        $stmt = $this->conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param(
                "s",
                $myuser_id
            );

            if ($stmt->execute()) {
                $result = $stmt->get_result();

                while ($row = $result->fetch_assoc()) {
                    $grado = trim((string)$row["grado"]);
                    $grupo = trim((string)$row["grupo"]);

                    $keyGrupo =
                        $grado .
                        "|" .
                        $grupo;

                    $existencias[$keyGrupo] =
                        (int)$row["existencia"];
                }
            } else {
                error_log(
                    "getAprovechamientoEscolar existencia execute: " .
                    $stmt->error
                );

                $json["status"] = "error";
                $json["answer"] = "No fue posible consultar la existencia de los grupos";
                $json["code"] = 500;
                $valido = false;
            }

            $stmt->close();
        } else {
            error_log(
                "getAprovechamientoEscolar existencia prepare: " .
                $this->conn->error
            );

            $json["status"] = "error";
            $json["answer"] = "Error al consultar la existencia de los grupos";
            $json["code"] = 500;
            $valido = false;
        }
    }

    /*
     * Consultar todas las calificaciones del ciclo.
     *
     * Traemos:
     * - alumno
     * - grado/grupo
     * - asignatura
     * - periodo
     * - calificación del criterio
     * - porcentaje del criterio
     *
     * Después PHP calcula:
     * criterio -> promedio del trimestre
     * trimestre -> promedio final del ciclo
     */
    if ($valido) {
        $sql = "SELECT
                    c.alumno_id,
                    c.maestro_id,
                    c.asignatura_id,
                    c.criterio_id,
                    c.periodo,
                    c.calificacion,
                    c.valor_texto,

                    a.grado,
                    a.grupo,

                    asi.nombre AS asignatura,

                    ce.porcentaje

                FROM calificaciones c

                INNER JOIN alumnos a
                    ON a.myuser_id = c.myuser_id
                    AND a.id = c.alumno_id

                INNER JOIN asignaturas asi
                    ON asi.myuser_id = c.myuser_id
                    AND asi.id = c.asignatura_id

                INNER JOIN criterios_evaluacion ce
                    ON ce.myuser_id = c.myuser_id
                    AND ce.id = c.criterio_id
                    AND ce.asignatura_id = c.asignatura_id

                WHERE c.myuser_id = ?
                    AND c.ciclo_escolar = ?
                    AND c.estado = 1
                    AND c.eliminado = 0

                    AND a.estado = 1
                    AND a.eliminado = 0

                    AND asi.eliminado = 0
                    AND ce.eliminado = 0";

        if ($maestro_id > 0) {
            $sql .= " AND c.maestro_id = ?";
        }

        $sql .= " ORDER BY
                    asi.nombre ASC,
                    a.grado ASC,
                    a.grupo ASC,
                    c.alumno_id ASC,
                    c.periodo ASC,
                    ce.orden ASC,
                    ce.id ASC";

        $stmt = $this->conn->prepare($sql);

        if ($stmt) {
            if ($maestro_id > 0) {
                $stmt->bind_param(
                    "ssi",
                    $myuser_id,
                    $ciclo_escolar,
                    $maestro_id
                );
            } else {
                $stmt->bind_param(
                    "ss",
                    $myuser_id,
                    $ciclo_escolar
                );
            }

            if ($stmt->execute()) {
                $result = $stmt->get_result();

                while ($row = $result->fetch_assoc()) {
                    if (
                        $row["calificacion"] === null ||
                        $row["calificacion"] === ""
                    ) {
                        continue;
                    }

                    $alumno_id =
                        (int)$row["alumno_id"];

                    $asignatura_id =
                        (int)$row["asignatura_id"];

                    $grado =
                        trim((string)$row["grado"]);

                    $grupo =
                        trim((string)$row["grupo"]);

                    $periodo =
                        trim((string)$row["periodo"]);

                    $calificacion =
                        (float)$row["calificacion"];

                    $porcentaje =
                        (float)$row["porcentaje"];

                    /*
                     * Identificador de la fila final:
                     *
                     * asignatura + grado + grupo.
                     */
                    $keyFila =
                        $asignatura_id .
                        "|" .
                        $grado .
                        "|" .
                        $grupo;

                    if (!isset($detalle[$keyFila])) {
                        $detalle[$keyFila] = [
                            "asignatura_id" => $asignatura_id,
                            "asignatura" => $row["asignatura"],
                            "grado" => $grado,
                            "grupo" => $grupo,
                            "alumnos" => []
                        ];
                    }

                    if (
                        !isset(
                            $detalle[$keyFila]["alumnos"][$alumno_id]
                        )
                    ) {
                        $detalle[$keyFila]["alumnos"][$alumno_id] = [
                            "periodos" => []
                        ];
                    }

                    if (
                        !isset(
                            $detalle[$keyFila]["alumnos"][$alumno_id]["periodos"][$periodo]
                        )
                    ) {
                        $detalle[$keyFila]["alumnos"][$alumno_id]["periodos"][$periodo] = [
                            "ponderado" => 0,
                            "peso" => 0
                        ];
                    }

                    $detalle[$keyFila]["alumnos"][$alumno_id]["periodos"][$periodo]["ponderado"] +=
                        $calificacion *
                        (
                            $porcentaje /
                            100
                        );

                    $detalle[$keyFila]["alumnos"][$alumno_id]["periodos"][$periodo]["peso"] +=
                        $porcentaje;
                }
            } else {
                error_log(
                    "getAprovechamientoEscolar calificaciones execute: " .
                    $stmt->error
                );

                $json["status"] = "error";
                $json["answer"] = "No fue posible consultar las calificaciones del ciclo";
                $json["code"] = 500;
                $valido = false;
            }

            $stmt->close();
        } else {
            error_log(
                "getAprovechamientoEscolar calificaciones prepare: " .
                $this->conn->error
            );

            $json["status"] = "error";
            $json["answer"] = "Error al preparar el aprovechamiento escolar";
            $json["code"] = 500;
            $valido = false;
        }
    }

    /*
     * Construir las filas exactamente como las necesita
     * el formato:
     *
     * ASIGNATURA
     * GDO.
     * GPO.
     * EXISTENCIA
     * APROBADOS
     * % APROBADOS
     * REPROBADOS
     * % REPROBADOS
     * PROMEDIO GENERAL
     */
    if ($valido) {
        foreach ($detalle as $fila) {
            $grado =
                $fila["grado"];

            $grupo =
                $fila["grupo"];

            $keyGrupo =
                $grado .
                "|" .
                $grupo;

            $existencia =
                isset(
                    $existencias[$keyGrupo]
                )
                    ? (int)$existencias[$keyGrupo]
                    : 0;

            $aprobados = 0;
            $reprobados = 0;

            $sumaPromedios = 0;
            $evaluados = 0;

            foreach (
                $fila["alumnos"] as
                $alumno
            ) {
                $promediosPeriodos = [];

                /*
                 * Para obtener un promedio final,
                 * debe tener promedio en los tres
                 * periodos esperados.
                 */
                foreach ($periodos as $periodoEsperado) {
                    if (
                        !isset(
                            $alumno["periodos"][$periodoEsperado]
                        )
                    ) {
                        continue;
                    }

                    $datosPeriodo =
                        $alumno["periodos"][$periodoEsperado];

                    $peso =
                        (float)$datosPeriodo["peso"];

                    if ($peso <= 0) {
                        continue;
                    }

                    $promedioPeriodo =
                        (float)$datosPeriodo["ponderado"] /
                        (
                            $peso /
                            100
                        );

                    $promediosPeriodos[] =
                        $promedioPeriodo;
                }

               

                $promedioFinal = array_sum($promediosPeriodos) / count($promediosPeriodos);
/*
 * Si todavía no tiene ningún periodo
 * evaluado, no entra al cálculo.
 *
 * Si ya tiene uno o más periodos,
 * utilizamos los que estén capturados.
 *
 * Cuando tenga los 3 trimestres,
 * naturalmente el promedio se calculará
 * con los 3.
 */
if (count($promediosPeriodos) === 0) {
    continue;
}

$promedioFinal =
    array_sum($promediosPeriodos) /
    count($promediosPeriodos);

$promedioFinal =
    round(
        $promedioFinal,
        1
    );

$evaluados++;

$sumaPromedios +=
    $promedioFinal;

if ($promedioFinal >= 6) {
    $aprobados++;
} else {
    $reprobados++;
}
            }

            /*
             * Como en el formato oficial:
             * el porcentaje se calcula contra
             * la existencia total del grupo.
             */
            $porcentajeAprobados =
                $existencia > 0
                    ? round(
                        (
                            $aprobados /
                            $existencia
                        ) * 100,
                        0
                    )
                    : 0;

            $porcentajeReprobados =
                $existencia > 0
                    ? round(
                        (
                            $reprobados /
                            $existencia
                        ) * 100,
                        0
                    )
                    : 0;

            $promedioGeneral =
                $evaluados > 0
                    ? round(
                        $sumaPromedios /
                        $evaluados,
                        1
                    )
                    : null;

            $resultados[] = [
                "asignatura_id" =>
                    (int)$fila["asignatura_id"],

                "asignatura" =>
                    $fila["asignatura"],

                "grado" =>
                    $grado,

                "grupo" =>
                    $grupo,

                "existencia" =>
                    $existencia,

                "evaluados" =>
                    $evaluados,

                "aprobados" =>
                    $aprobados,

                "porcentaje_aprobados" =>
                    $porcentajeAprobados,

                "reprobados" =>
                    $reprobados,

                "porcentaje_reprobados" =>
                    $porcentajeReprobados,

                "promedio_general" =>
                    $promedioGeneral
            ];
        }

        usort(
            $resultados,
            function($a, $b) {
                $comparacionAsignatura =
                    strcasecmp(
                        $a["asignatura"],
                        $b["asignatura"]
                    );

                if ($comparacionAsignatura !== 0) {
                    return $comparacionAsignatura;
                }

                $gradoA =
                    (int)preg_replace(
                        '/[^0-9]/',
                        '',
                        $a["grado"]
                    );

                $gradoB =
                    (int)preg_replace(
                        '/[^0-9]/',
                        '',
                        $b["grado"]
                    );

                if ($gradoA !== $gradoB) {
                    return $gradoA < $gradoB
                        ? -1
                        : 1;
                }

                return strcasecmp(
                    $a["grupo"],
                    $b["grupo"]
                );
            }
        );

        $json["status"] = "ok";
        $json["answer"] = "Aprovechamiento escolar consultado correctamente";
        $json["results"] = $resultados;
        $json["total"] = count($resultados);

        /*
         * Lo dejamos listo para mandarlo
         * directamente al generador PDF.
         */
        $json["pdf_data"] = [
            "tipo_reporte" => "aprovechamiento",
            "ciclo" => $ciclo_escolar,
            "maestro_id" => $maestro_id,
            "resultados" => $resultados
        ];

        $json["code"] = 200;
    }

    return $json;
}
    

}

?>