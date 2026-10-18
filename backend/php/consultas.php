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

    //Categorias
    public function getCategorias() {
    $json = [];
    $myuser_id = (string)$this->token;
    $tipo = (int)($_POST['tipo'] ?? 1);
    $pagina = max(1, (int)($_POST['pagina'] ?? 1));
    $limite = max(1, min(48, (int)($_POST['limite'] ?? 12)));
    $buscar = trim(substr((string)($_POST['buscar'] ?? ''), 0, 120));
    $combo = (int)($_POST['combo'] ?? 0) === 1;

    if ($myuser_id === '' || $tipo < 1 || $tipo > 255) {
        $json = ['status' => 'error', 'answer' => 'Usuario o tipo de categoría inválido', 'code' => 400];
    } else {
        try {
            if ($combo) {
                $sql = "SELECT id, nombre, imagen FROM categorias
                        WHERE myuser_id = ? AND tipo = ? AND activo = 1 AND eliminado = 0
                        ORDER BY nombre ASC LIMIT 500";

                $stmt = $this->conn->prepare($sql);
                if (!$stmt) throw new RuntimeException($this->conn->error);
                $stmt->bind_param("si", $myuser_id, $tipo);
                if (!$stmt->execute()) throw new RuntimeException($stmt->error);

                $resultados = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();

                $json = ['status' => 'ok', 'answer' => 'Categorías consultadas correctamente',
                         'results' => $resultados, 'total' => count($resultados), 'code' => 200];
            } else {
                $texto = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $buscar);
                $like = '%' . $texto . '%';
                $where = "WHERE myuser_id = ? AND tipo = ? AND eliminado = 0
                          AND (nombre LIKE ? ESCAPE '!' OR descripcion LIKE ? ESCAPE '!')";

                $stmt = $this->conn->prepare("SELECT COUNT(*) AS total FROM categorias $where");
                if (!$stmt) throw new RuntimeException($this->conn->error);
                $stmt->bind_param("siss", $myuser_id, $tipo, $like, $like);
                if (!$stmt->execute()) throw new RuntimeException($stmt->error);

                $total = (int)$stmt->get_result()->fetch_assoc()['total'];
                $stmt->close();

                $paginas = max(1, (int)ceil($total / $limite));
                $pagina = min($pagina, $paginas);
                $offset = ($pagina - 1) * $limite;

                $sql = "SELECT id, tipo, nombre, descripcion, imagen, activo, fecha_alta
                        FROM categorias $where
                        ORDER BY activo DESC, nombre ASC, id DESC LIMIT ? OFFSET ?";

                $stmt = $this->conn->prepare($sql);
                if (!$stmt) throw new RuntimeException($this->conn->error);
                $stmt->bind_param("sissii", $myuser_id, $tipo, $like, $like, $limite, $offset);
                if (!$stmt->execute()) throw new RuntimeException($stmt->error);

                $resultados = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();

                $json = ['status' => 'ok', 'answer' => 'Categorías consultadas correctamente',
                         'results' => $resultados, 'total' => $total, 'pagina' => $pagina,
                         'limite' => $limite, 'paginas' => $paginas, 'code' => 200];
            }
        } catch (Throwable $e) {
            error_log('getCategorias: ' . $e->getMessage());
            $json = ['status' => 'error', 'answer' => 'No fue posible consultar las categorías', 'code' => 500];
        }
    }

    return $json;
}

public function getCategoria() {
    $json = [];
    $myuser_id = (string)$this->token;
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

    if ($myuser_id === '' || !$id || $id < 1) {
        $json = ['status' => 'error', 'answer' => 'Identificador de categoría inválido', 'code' => 400];
    } else {
        try {
            $sql = "SELECT id, tipo, nombre, descripcion, imagen, activo, fecha_alta
                    FROM categorias WHERE myuser_id = ? AND id = ? AND eliminado = 0 LIMIT 1";

            $stmt = $this->conn->prepare($sql);
            if (!$stmt) throw new RuntimeException($this->conn->error);
            $stmt->bind_param("si", $myuser_id, $id);
            if (!$stmt->execute()) throw new RuntimeException($stmt->error);

            $categoria = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $json = [
                'status' => $categoria ? 'ok' : 'error',
                'answer' => $categoria ? 'Categoría encontrada' : 'Categoría no encontrada',
                'results' => $categoria ?: null,
                'code' => $categoria ? 200 : 404
            ];
        } catch (Throwable $e) {
            error_log('getCategoria: ' . $e->getMessage());
            $json = ['status' => 'error', 'answer' => 'No fue posible consultar la categoría', 'code' => 500];
        }
    }

    return $json;
}

public function setCategoria() {
    $json = [];
    $myuser_id = (string)$this->token;
    $idTexto = trim((string)($_POST['id'] ?? ''));
    $id = $idTexto === '' ? 0 : filter_var($idTexto, FILTER_VALIDATE_INT);
    $tipo = filter_var($_POST['tipo'] ?? 1, FILTER_VALIDATE_INT);
    $nombre = trim((string)($_POST['nombre'] ?? ''));
    $descripcion = trim((string)($_POST['descripcion'] ?? ''));
    $activo = filter_var($_POST['activo'] ?? 1, FILTER_VALIDATE_INT);
    $quitarImagen = (int)($_POST['quitar_imagen'] ?? 0) === 1;
    $archivoNuevo = null;
    $longitud = function_exists('mb_strlen') ? mb_strlen($nombre, 'UTF-8') : strlen($nombre);

    if ($myuser_id === '' || strlen($myuser_id) > 170 || $id === false || $id < 0 ||
        $tipo === false || $tipo < 1 || $tipo > 255 || $nombre === '' ||
        $longitud > 120 || strlen($descripcion) > 10000 || !in_array($activo, [0, 1], true)) {
        $json = ['status' => 'error', 'answer' => 'Verifica los datos de la categoría', 'code' => 400];
    } else {
        try {
            $actual = null;

            if ($id > 0) {
                $stmt = $this->conn->prepare("SELECT tipo, imagen FROM categorias WHERE myuser_id = ? AND id = ? AND eliminado = 0 LIMIT 1");
                if (!$stmt) throw new RuntimeException($this->conn->error);
                $stmt->bind_param("si", $myuser_id, $id);
                if (!$stmt->execute()) throw new RuntimeException($stmt->error);

                $actual = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$actual) throw new DomainException('La categoría no existe', 404);
                if ((int)$actual['tipo'] !== (int)$tipo) throw new DomainException('No se puede modificar el tipo de una categoría existente', 400);
            }

            $stmt = $this->conn->prepare("SELECT id FROM categorias WHERE myuser_id = ? AND tipo = ? AND nombre = ? AND eliminado = 0 AND id <> ? LIMIT 1");
            if (!$stmt) throw new RuntimeException($this->conn->error);
            $stmt->bind_param("sisi", $myuser_id, $tipo, $nombre, $id);
            if (!$stmt->execute()) throw new RuntimeException($stmt->error);

            $duplicada = $stmt->get_result()->num_rows > 0;
            $stmt->close();

            if ($duplicada) throw new DomainException('Ya existe una categoría con ese nombre', 409);

            $imagen = $quitarImagen ? null : ($actual['imagen'] ?? null);

            if (isset($_FILES['imagen']) && ($_FILES['imagen']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $archivo = $_FILES['imagen'];

                if (!is_scalar($archivo['error']) || $archivo['error'] !== UPLOAD_ERR_OK ||
                    !is_scalar($archivo['size']) || $archivo['size'] <= 0 || $archivo['size'] > 3145728 ||
                    !is_uploaded_file($archivo['tmp_name'])) {
                    throw new DomainException('La imagen no es válida o supera los 3 MB', 400);
                }

                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name']);
                $extensiones = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                $medidas = @getimagesize($archivo['tmp_name']);

                if (!isset($extensiones[$mime]) || !$medidas || $medidas[0] > 5000 || $medidas[1] > 5000) {
                    throw new DomainException('Solo se permiten imágenes JPG, PNG o WebP válidas', 400);
                }

                $carpeta = __DIR__ . '/uploads/categorias/';

                if (!is_dir($carpeta) && !mkdir($carpeta, 0755, true) && !is_dir($carpeta)) {
                    throw new RuntimeException('No fue posible crear el directorio de imágenes');
                }

                $nombreArchivo = bin2hex(random_bytes(16)) . '.' . $extensiones[$mime];
                $archivoNuevo = $carpeta . $nombreArchivo;

                if (!move_uploaded_file($archivo['tmp_name'], $archivoNuevo)) {
                    throw new RuntimeException('No fue posible almacenar la fotografía');
                }

                $imagen = 'uploads/categorias/' . $nombreArchivo;
            }

            $descripcionBD = $descripcion === '' ? null : $descripcion;

            if ($id > 0) {
                $sql = "UPDATE categorias SET nombre = ?, descripcion = ?, imagen = ?, activo = ?
                        WHERE myuser_id = ? AND id = ? AND eliminado = 0";
                $stmt = $this->conn->prepare($sql);
                if (!$stmt) throw new RuntimeException($this->conn->error);
                $stmt->bind_param("sssisi", $nombre, $descripcionBD, $imagen, $activo, $myuser_id, $id);
            } else {
                $sql = "INSERT INTO categorias (myuser_id, tipo, nombre, descripcion, imagen, activo)
                        VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $this->conn->prepare($sql);
                if (!$stmt) throw new RuntimeException($this->conn->error);
                $stmt->bind_param("sisssi", $myuser_id, $tipo, $nombre, $descripcionBD, $imagen, $activo);
            }

            if (!$stmt->execute()) throw new RuntimeException($stmt->error);
            if ($id === 0) $id = (int)$this->conn->insert_id;
            $stmt->close();

            $json = [
                'status' => 'ok',
                'answer' => 'Categoría guardada correctamente',
                'id' => $id,
                'code' => 200
            ];
        } catch (DomainException $e) {
            if ($archivoNuevo && is_file($archivoNuevo)) unlink($archivoNuevo);
            $json = ['status' => 'error', 'answer' => $e->getMessage(), 'code' => $e->getCode()];
        } catch (Throwable $e) {
            if ($archivoNuevo && is_file($archivoNuevo)) unlink($archivoNuevo);
            error_log('setCategoria: ' . $e->getMessage());
            $json = ['status' => 'error', 'answer' => 'No fue posible guardar la categoría', 'code' => 500];
        }
    }

    return $json;
}

public function eliminarCategoria() {
    $json = [];
    $myuser_id = (string)$this->token;
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

    if ($myuser_id === '' || !$id || $id < 1) {
        $json = ['status' => 'error', 'answer' => 'Identificador de categoría inválido', 'code' => 400];
    } else {
        try {
            $sql = "SELECT tipo FROM categorias
                    WHERE myuser_id = ? AND id = ? AND eliminado = 0 LIMIT 1";

            $stmt = $this->conn->prepare($sql);
            if (!$stmt) throw new RuntimeException($this->conn->error);
            $stmt->bind_param("si", $myuser_id, $id);
            if (!$stmt->execute()) throw new RuntimeException($stmt->error);

            $categoria = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$categoria) {
                $json = ['status' => 'error', 'answer' => 'La categoría no existe o ya fue eliminada', 'code' => 404];
            } else {
                $asignados = 0;

                if ((int)$categoria['tipo'] === 1) {
                    $stmt = $this->conn->prepare("SELECT COUNT(*) AS total FROM products WHERE categoria_id = ? AND eliminado = 0");
                    if (!$stmt) throw new RuntimeException($this->conn->error);
                    $stmt->bind_param("i", $id);
                    if (!$stmt->execute()) throw new RuntimeException($stmt->error);

                    $asignados = (int)$stmt->get_result()->fetch_assoc()['total'];
                    $stmt->close();
                }

                if ($asignados > 0) {
                    $json = [
                        'status' => 'error',
                        'answer' => 'No puedes eliminar esta categoría porque tiene productos relacionados. Desactívala o reasigna los productos.',
                        'code' => 409
                    ];
                } else {
                    $sql = "UPDATE categorias SET eliminado = 1, activo = 0
                            WHERE myuser_id = ? AND id = ? AND eliminado = 0";

                    $stmt = $this->conn->prepare($sql);
                    if (!$stmt) throw new RuntimeException($this->conn->error);
                    $stmt->bind_param("si", $myuser_id, $id);
                    if (!$stmt->execute()) throw new RuntimeException($stmt->error);

                    $eliminada = $stmt->affected_rows > 0;
                    $stmt->close();

                    $json = [
                        'status' => $eliminada ? 'ok' : 'error',
                        'answer' => $eliminada ? 'Categoría eliminada correctamente' : 'La categoría ya fue eliminada',
                        'code' => $eliminada ? 200 : 404
                    ];
                }
            }
        } catch (Throwable $e) {
            error_log('eliminarCategoria: ' . $e->getMessage());
            $json = ['status' => 'error', 'answer' => 'No fue posible eliminar la categoría', 'code' => 500];
        }
    }

    return $json;
}

}
?>