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

    public function setComisiones($data) {
        $json = [];
        $myuser_id = $this->token;
        $tipo = isset($data["tipo"]) ? (int)$data["tipo"] : 1;
        $nombre = isset($data["nombre"]) ? trim((string)$data["nombre"]) : "";
        $porcentajeDato = $data["porcentaje"] ?? 0;
        $montoExtraDato = $data["monto_extra"] ?? 0;

        if (!$this->conn) {
            $json["status"] = "error";
            $json["answer"] = "No existe conexión con la base de datos";
            $json["code"] = 500;
        } elseif (trim((string)$myuser_id) === "") {
            $json["status"] = "error";
            $json["answer"] = "La sesión no es válida";
            $json["code"] = 401;
        } elseif ($nombre === "" || strlen($nombre) > 150) {
            $json["status"] = "warning";
            $json["answer"] = "Ingresa un nombre válido de máximo 150 bytes";
            $json["code"] = 400;
        } elseif ($tipo !== 1 && $tipo !== 2) {
            $json["status"] = "warning";
            $json["answer"] = "El tipo de comisión no es válido";
            $json["code"] = 400;
        } elseif (!is_numeric($porcentajeDato) || !is_numeric($montoExtraDato)) {
            $json["status"] = "warning";
            $json["answer"] = "El porcentaje y el monto extra deben ser numéricos";
            $json["code"] = 400;
        } else {
            $porcentaje = round((float)$porcentajeDato, 4);
            $montoExtra = round((float)$montoExtraDato, 4);

            if ($porcentaje < 0 || $porcentaje > 100 || $montoExtra < 0 || $montoExtra > 9999999999) {
                $json["status"] = "warning";
                $json["answer"] = "Verifica el porcentaje y el monto extra de la comisión";
                $json["code"] = 400;
            } else {
                $sql = "INSERT INTO comisiones (myuser_id, tipo, nombre, porcentaje, monto_extra)
                        VALUES (?, ?, ?, ?, ?)";
                $stmt = $this->conn->prepare($sql);

                if ($stmt) {
                    $stmt->bind_param("sisdd", $myuser_id, $tipo, $nombre, $porcentaje, $montoExtra);

                    if ($stmt->execute()) {
                        $json["status"] = "ok";
                        $json["answer"] = "Comisión registrada correctamente";
                        $json["id"] = (int)$this->conn->insert_id;
                        $json["code"] = 200;
                    } else {
                        error_log("setComisiones execute: " . $stmt->error);
                        $json["status"] = "error";
                        $json["answer"] = "No fue posible registrar la comisión";
                        $json["code"] = 500;
                    }
                    $stmt->close();
                } else {
                    error_log("setComisiones prepare: " . $this->conn->error);
                    $json["status"] = "error";
                    $json["answer"] = "Error al preparar el registro de la comisión";
                    $json["code"] = 500;
                }
            }
        }
        return $json;
    }

    public function getComisiones($tipo = 0) {
        $json = [];
        $myuser_id = $this->token;
        $tipo = (int)$tipo;

        if (!$this->conn) {
            $json["status"] = "error";
            $json["answer"] = "No existe conexión con la base de datos";
            $json["code"] = 500;
        } elseif (trim((string)$myuser_id) === "") {
            $json["status"] = "error";
            $json["answer"] = "La sesión no es válida";
            $json["code"] = 401;
        } elseif (!in_array($tipo, [0, 1, 2], true)) {
            $json["status"] = "warning";
            $json["answer"] = "El tipo de comisión no es válido";
            $json["code"] = 400;
        } else {
            $sql = "SELECT id, tipo, nombre, porcentaje, monto_extra, fecha_alta
                    FROM comisiones
                    WHERE myuser_id = ? AND eliminado = 0 AND (? = 0 OR tipo = ?)
                    ORDER BY id DESC";
            $stmt = $this->conn->prepare($sql);

            if ($stmt) {
                $stmt->bind_param("sii", $myuser_id, $tipo, $tipo);

                if ($stmt->execute()) {
                    $result = $stmt->get_result();
                    $comisiones = [];

                    while ($row = $result->fetch_assoc()) {
                        $row["id"] = (int)$row["id"];
                        $row["tipo"] = (int)$row["tipo"];
                        $comisiones[] = $row;
                    }

                    $json["status"] = "ok";
                    $json["answer"] = "Comisiones consultadas correctamente";
                    $json["results"] = $comisiones;
                    $json["total"] = count($comisiones);
                    $json["code"] = 200;
                } else {
                    error_log("getComisiones execute: " . $stmt->error);
                    $json["status"] = "error";
                    $json["answer"] = "No fue posible consultar las comisiones";
                    $json["code"] = 500;
                }
                $stmt->close();
            } else {
                error_log("getComisiones prepare: " . $this->conn->error);
                $json["status"] = "error";
                $json["answer"] = "Error al preparar la consulta de comisiones";
                $json["code"] = 500;
            }
        }
        return $json;
    }

    public function updateComisiones($data) {
        $json = [];
        $myuser_id = $this->token;
        $id = isset($data["id"]) ? (int)$data["id"] : 0;
        $tipo = isset($data["tipo"]) ? (int)$data["tipo"] : 0;
        $nombre = isset($data["nombre"]) ? trim((string)$data["nombre"]) : "";
        $porcentajeDato = $data["porcentaje"] ?? null;
        $montoExtraDato = $data["monto_extra"] ?? null;

        if (!$this->conn) {
            $json["status"] = "error";
            $json["answer"] = "No existe conexión con la base de datos";
            $json["code"] = 500;
        } elseif (trim((string)$myuser_id) === "") {
            $json["status"] = "error";
            $json["answer"] = "La sesión no es válida";
            $json["code"] = 401;
        } elseif ($id <= 0) {
            $json["status"] = "warning";
            $json["answer"] = "Selecciona una comisión válida";
            $json["code"] = 400;
        } elseif ($nombre === "" || strlen($nombre) > 150 || !in_array($tipo, [1, 2], true)) {
            $json["status"] = "warning";
            $json["answer"] = "Verifica el nombre y el tipo de comisión";
            $json["code"] = 400;
        } elseif (!is_numeric($porcentajeDato) || !is_numeric($montoExtraDato)) {
            $json["status"] = "warning";
            $json["answer"] = "El porcentaje y el monto extra deben ser numéricos";
            $json["code"] = 400;
        } else {
            $porcentaje = round((float)$porcentajeDato, 4);
            $montoExtra = round((float)$montoExtraDato, 4);

            if ($porcentaje < 0 || $porcentaje > 100 || $montoExtra < 0 || $montoExtra > 9999999999) {
                $json["status"] = "warning";
                $json["answer"] = "Verifica el porcentaje y el monto extra de la comisión";
                $json["code"] = 400;
            } else {
                $sql = "UPDATE comisiones SET tipo = ?, nombre = ?, porcentaje = ?, monto_extra = ?
                        WHERE myuser_id = ? AND id = ? AND eliminado = 0";
                $stmt = $this->conn->prepare($sql);

                if ($stmt) {
                    $stmt->bind_param("isddsi", $tipo, $nombre, $porcentaje, $montoExtra, $myuser_id, $id);

                    if ($stmt->execute()) {
                        if ($stmt->affected_rows > 0) {
                            $json["status"] = "ok";
                            $json["answer"] = "Comisión actualizada correctamente";
                            $json["id"] = $id;
                            $json["code"] = 200;
                        } else {
                            $sqlExiste = "SELECT id FROM comisiones
                                          WHERE myuser_id = ? AND id = ? AND eliminado = 0 LIMIT 1";
                            $stmtExiste = $this->conn->prepare($sqlExiste);

                            if ($stmtExiste) {
                                $stmtExiste->bind_param("si", $myuser_id, $id);

                                if ($stmtExiste->execute()) {
                                    $stmtExiste->store_result();

                                    if ($stmtExiste->num_rows > 0) {
                                        $json["status"] = "ok";
                                        $json["answer"] = "La comisión ya tiene los datos indicados";
                                        $json["id"] = $id;
                                        $json["code"] = 200;
                                    } else {
                                        $json["status"] = "warning";
                                        $json["answer"] = "La comisión no existe o fue eliminada";
                                        $json["code"] = 404;
                                    }
                                } else {
                                    error_log("updateComisiones verificar execute: " . $stmtExiste->error);
                                    $json["status"] = "error";
                                    $json["answer"] = "No fue posible verificar la comisión";
                                    $json["code"] = 500;
                                }
                                $stmtExiste->close();
                            } else {
                                error_log("updateComisiones verificar prepare: " . $this->conn->error);
                                $json["status"] = "error";
                                $json["answer"] = "Error al verificar la comisión";
                                $json["code"] = 500;
                            }
                        }
                    } else {
                        error_log("updateComisiones execute: " . $stmt->error);
                        $json["status"] = "error";
                        $json["answer"] = "No fue posible actualizar la comisión";
                        $json["code"] = 500;
                    }
                    $stmt->close();
                } else {
                    error_log("updateComisiones prepare: " . $this->conn->error);
                    $json["status"] = "error";
                    $json["answer"] = "Error al preparar la actualización de la comisión";
                    $json["code"] = 500;
                }
            }
        }
        return $json;
    }

    public function eliminarComisiones($id) {
        $json = [];
        $myuser_id = $this->token;
        $id = (int)$id;

        if (!$this->conn) {
            $json["status"] = "error";
            $json["answer"] = "No existe conexión con la base de datos";
            $json["code"] = 500;
        } elseif (trim((string)$myuser_id) === "") {
            $json["status"] = "error";
            $json["answer"] = "La sesión no es válida";
            $json["code"] = 401;
        } elseif ($id <= 0) {
            $json["status"] = "warning";
            $json["answer"] = "La comisión seleccionada no es válida";
            $json["code"] = 400;
        } else {
            $sql = "UPDATE comisiones SET eliminado = 1
                    WHERE myuser_id = ? AND id = ? AND eliminado = 0";
            $stmt = $this->conn->prepare($sql);

            if ($stmt) {
                $stmt->bind_param("si", $myuser_id, $id);

                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        $json["status"] = "ok";
                        $json["answer"] = "Comisión eliminada correctamente";
                        $json["id"] = $id;
                        $json["code"] = 200;
                    } else {
                        $json["status"] = "warning";
                        $json["answer"] = "La comisión no existe o ya fue eliminada";
                        $json["code"] = 404;
                    }
                } else {
                    error_log("eliminarComisiones execute: " . $stmt->error);
                    $json["status"] = "error";
                    $json["answer"] = "No fue posible eliminar la comisión";
                    $json["code"] = 500;
                }
                $stmt->close();
            } else {
                error_log("eliminarComisiones prepare: " . $this->conn->error);
                $json["status"] = "error";
                $json["answer"] = "Error al preparar la eliminación de la comisión";
                $json["code"] = 500;
            }
        }
        return $json;
    }

}
?>