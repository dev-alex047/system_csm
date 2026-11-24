<?php 
class consultas{
    private $token;
    private $conn;
    //private $usuarioOperador = 1; // ID real del operador en la tabla users
    private $usuarioOperador = 0;

    public function __construct() {
        //$this->token = $session;
        $this->usuarioOperador = isset($_SESSION['csm_user_id']) ? (int)$_SESSION['csm_user_id'] : 0;
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

 
public function getResumenInicial($data = []) {
    $json = [];
    $inicio = isset($data["fecha_inicio"]) ? trim((string)$data["fecha_inicio"]) : date("Y-m-01");
    $fin = isset($data["fecha_fin"]) ? trim((string)$data["fecha_fin"]) : date("Y-m-d");
    $fechaInicio = DateTime::createFromFormat("!Y-m-d", $inicio);
    $fechaFin = DateTime::createFromFormat("!Y-m-d", $fin);

    if (!$this->conn) {
        $json = ["status"=>"error","answer"=>"No hay conexión con MySQL.","code"=>500];
    } elseif (!$fechaInicio || !$fechaFin || $fechaInicio->format("Y-m-d") !== $inicio || $fechaFin->format("Y-m-d") !== $fin || $inicio > $fin) {
        $json = ["status"=>"warning","answer"=>"Selecciona un rango de fechas válido.","code"=>400];
    } elseif ($fechaInicio->diff($fechaFin)->days > 366) {
        $json = ["status"=>"warning","answer"=>"El periodo máximo permitido es de 366 días.","code"=>400];
    } else {
        try {
            $consultasPeriodo = [
                "ventas" => "SELECT COUNT(*) cantidad, COALESCE(SUM(total_amount),0) importe FROM sales WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)",
                "salidas" => "SELECT COUNT(*) cantidad, COALESCE(SUM(amount),0) importe FROM account_moves WHERE type = 'SALIDA' AND status = 'PAGADO' AND `date` >= ? AND `date` < DATE_ADD(?, INTERVAL 1 DAY)",
                "ultimas_ventas" => "SELECT id, client_name, payment_method, total_amount, created_at FROM sales WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY) ORDER BY created_at DESC, id DESC LIMIT 8",
                "movimientos" => "SELECT id, `date`, type, origin, user_reference_name, amount, status FROM account_moves WHERE `date` >= ? AND `date` < DATE_ADD(?, INTERVAL 1 DAY) ORDER BY `date` DESC, id DESC LIMIT 8"
            ];

            $resultados = [];

            foreach ($consultasPeriodo as $clave => $sql) {
                $stmt = $this->conn->prepare($sql);
                if (!$stmt) throw new RuntimeException($this->conn->error);
                $stmt->bind_param("ss", $inicio, $fin);
                if (!$stmt->execute()) throw new RuntimeException($stmt->error);
                $result = $stmt->get_result();

                if ($clave === "ultimas_ventas" || $clave === "movimientos") {
                    $resultados[$clave] = [];
                    while ($fila = $result->fetch_assoc()) $resultados[$clave][] = $fila;
                } else {
                    $resultados[$clave] = $result->fetch_assoc();
                }
                $stmt->close();
            }

            $consultasSaldo = [
                "caja" => "SELECT COUNT(*) cantidad, COALESCE(SUM(current_balance),0) saldo FROM cash_registers WHERE kind = 'CASH' AND is_active = 1",
                "cobrar" => "SELECT COUNT(*) cantidad, COALESCE(SUM(pending_amount),0) saldo FROM receivables WHERE status <> 'CANCELADO' AND pending_amount > 0",
                "pagar" => "SELECT COUNT(*) cantidad, COALESCE(SUM(pending_amount),0) saldo FROM payables WHERE status <> 'CANCELADO' AND pending_amount > 0"
            ];

            foreach ($consultasSaldo as $clave => $sql) {
                $result = $this->conn->query($sql);
                if (!$result) throw new RuntimeException($this->conn->error);
                $resultados[$clave] = $result->fetch_assoc();
                $result->free();
            }

            $json["status"] = "ok";
            $json["answer"] = "Resumen consultado correctamente.";
            $json["results"] = [
                "periodo" => ["inicio"=>$inicio,"fin"=>$fin],
                "ventas" => $resultados["ventas"],
                "salidas" => $resultados["salidas"],
                "caja" => $resultados["caja"],
                "cobrar" => $resultados["cobrar"],
                "pagar" => $resultados["pagar"],
                "ultimas_ventas" => $resultados["ultimas_ventas"],
                "movimientos" => $resultados["movimientos"]
            ];
            $json["code"] = 200;

        } catch (Throwable $e) {
            
            $json = ["status"=>"error","answer"=>"No fue posible obtener el resumen contable.","code"=>500];
        }
    }

    return $json;
}



private function ejecutarSqlVenta($sql, $tipos = '', $parametros = []) {
    $stmt = $this->conn->prepare($sql);
    if (!$stmt) throw new RuntimeException($this->conn->error, (int)$this->conn->errno);

    if ($tipos !== '') {
        $argumentos = [$tipos];
        foreach ($parametros as $i => &$valor) $argumentos[] = &$valor;
        unset($valor);
        if (!call_user_func_array([$stmt, 'bind_param'], $argumentos)) {
            $error = $stmt->error;
            $stmt->close();
            throw new RuntimeException($error);
        }
    }

    if (!$stmt->execute()) {
        $error = $stmt->error;
        $codigo = (int)$stmt->errno;
        $stmt->close();
        throw new RuntimeException($error, $codigo);
    }
    return $stmt;
}

private function centavosVenta($valor) {
    if (!is_string($valor) && !is_numeric($valor)) return null;
    $valor = trim((string)$valor);
    if (!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $valor)) return null;
    $partes = explode('.', $valor, 2);
    return (int)$partes[0] * 100 + (int)str_pad(isset($partes[1]) ? $partes[1] : '', 2, '0');
}


private function unidadCatalogoCodigo($codigo) {
    if ($codigo === '') return null;

    $stmt = $this->ejecutarSqlVenta(
        "SELECT unit FROM products WHERE code = ? LIMIT 2",
        "s", [$codigo]
    );

    $resultado = $stmt->get_result();
    $primera = $resultado->fetch_assoc();
    $segunda = $resultado->fetch_assoc();
    $stmt->close();

    // Solo aceptar una coincidencia inequívoca.
    if (!$primera || $segunda) return null;

    $unidad = trim((string)$primera["unit"]);
    if ($unidad === '') return null;

    if (strlen($unidad) > 40) {
        throw new InvalidArgumentException("La unidad del catálogo excede 40 caracteres.");
    }

    return $unidad;
}

private function unidadVenta($item, $codigo) {
    $catalogo = $this->unidadCatalogoCodigo($codigo);

    if ($catalogo !== null) {
        return $catalogo;
    }

    $unidad = strtoupper($this->textoVenta($item, "unidad", 40));

    
    /*$permitidas = [
        "PZA", "PAQ", "CAJA", "KG", "G", "TON",
        "M", "M2", "M3", "L", "ML", "BULTO",
        "SACO", "ROLLO", "JGO", "SERV",
        "HORA", "DIA", "VIAJE"
    ];*/


$permitidas = [
    "PZA", "PAQ", "CAJA", "SET", "KG", "G",
    "TON", "M", "M2", "M3", "L", "ML",
    "BULTO", "SACO", "ROLLO", "JGO",
    "SERV", "HORA", "DIA", "VIAJE"
];


    if (!in_array($unidad, $permitidas, true)) {
        throw new InvalidArgumentException("Selecciona una unidad válida para cada artículo.");
    }

    return $unidad;
}


public function buscarProductosVenta($data = []) {
    if (!$this->conn) {
        return [
            "status" => "error",
            "answer" => "No existe conexión con MySQL.",
            "code" => 500
        ];
    }

    try {
        $buscar = $this->textoVenta($data, "buscar", 100);

        if (strlen($buscar) < 2) {
            return [
                "status" => "ok",
                "results" => [],
                "code" => 200
            ];
        }

        $escapado = str_replace(
            ["!", "%", "_"],
            ["!!", "!%", "!_"],
            $buscar
        );

        $contiene = "%" . $escapado . "%";
        $empieza = $escapado . "%";

        $stmt = $this->ejecutarSqlVenta(
            "SELECT id, code, name, unit, public_price
             FROM products
             WHERE name LIKE ? ESCAPE '!'
                OR code LIKE ? ESCAPE '!'
                OR barcode LIKE ? ESCAPE '!'
             ORDER BY
                CASE
                    WHEN code = ? THEN 0
                    WHEN code LIKE ? ESCAPE '!' THEN 1
                    WHEN name LIKE ? ESCAPE '!' THEN 2
                    ELSE 3
                END,
                name ASC
             LIMIT 12",
            "ssssss",
            [
                $contiene,
                $contiene,
                $contiene,
                $buscar,
                $empieza,
                $empieza
            ]
        );

        $productos = [];

        $resultado = $stmt->get_result();

        while ($fila = $resultado->fetch_assoc()) {
            $productos[] = [
                "id" => (int)$fila["id"],
                "codigo" => (string)($fila["code"] ?? ""),
                "nombre" => (string)$fila["name"],
                "unidad" => (string)($fila["unit"] ?? ""),
                "precio" => $fila["public_price"] === null
                    ? ""
                    : number_format(
                        (float)$fila["public_price"],
                        2,
                        ".",
                        ""
                    )
            ];
        }

        $stmt->close();

        return [
            "status" => "ok",
            "answer" => "Productos consultados.",
            "results" => $productos,
            "code" => 200
        ];

    } catch (InvalidArgumentException $e) {
        return [
            "status" => "warning",
            "answer" => $e->getMessage(),
            "code" => 400
        ];

    } catch (Throwable $e) {
      

        return [
            "status" => "error",
            "answer" => "No fue posible buscar productos.",
            "code" => 500
        ];
    }
}


public function getUnidadProducto($data = []) {
    if (!$this->conn) {
        return [
            "status"=>"error",
            "answer"=>"No existe conexión con MySQL.",
            "code"=>500
        ];
    }

    try {
        $codigo = $this->textoVenta($data, "codigo", 80);
        $unidad = $this->unidadCatalogoCodigo($codigo);

        return [
            "status"=>"ok",
            "answer"=>"Unidad consultada correctamente.",
            "results"=>[
                "encontrado"=>$unidad !== null,
                "unidad"=>$unidad === null ? "" : $unidad
            ],
            "code"=>200
        ];
    } catch (InvalidArgumentException $e) {
        return [
            "status"=>"warning",
            "answer"=>$e->getMessage(),
            "code"=>400
        ];
    } catch (Throwable $e) {
      

        return [
            "status"=>"error",
            "answer"=>"No fue posible consultar la unidad.",
            "code"=>500
        ];
    }
}


private function textoVenta($datos, $campo, $maximo) {
    $valor = isset($datos[$campo]) ? $datos[$campo] : '';
    if (!is_string($valor) && !is_numeric($valor)) throw new InvalidArgumentException('Dato inválido: ' . $campo);
    $valor = trim((string)$valor);
    if (strlen($valor) > $maximo || strpos($valor, "\0") !== false) throw new InvalidArgumentException('El campo ' . $campo . ' excede su longitud permitida.');
    return $valor;
}

private function fechaOperacion($datos, $cajaId = null) {
    $ahora = date("Y-m-d H:i:s");
    $valor = isset($datos["fecha_operacion"]) ? $datos["fecha_operacion"] : "";

    if ($valor === "" || $valor === null) return $ahora;

    if (!is_string($valor) ||
        !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/D', $valor)) {
        throw new InvalidArgumentException("Formato de fecha inválido.");
    }

    $stmt = $this->ejecutarSqlVenta(
        "SELECT role_id FROM users WHERE id = ? AND is_active = 1 LIMIT 1",
        "i", [$this->usuarioOperador]
    );
    $usuario = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$usuario || (int)$usuario["role_id"] !== 1) {
        throw new InvalidArgumentException("Solo un administrador puede cambiar la fecha de una operación.");
    }

    $fecha = DateTime::createFromFormat("!Y-m-d\TH:i", $valor);

    if (!$fecha || $fecha->format("Y-m-d\TH:i") !== $valor) {
        throw new InvalidArgumentException("La fecha indicada no es válida.");
    }

    $fechaSql = $fecha->format("Y-m-d H:i:s");

    if ($fechaSql > $ahora || $fechaSql < "2000-01-01 00:00:00") {
        throw new InvalidArgumentException("Selecciona una fecha pasada válida, no futura.");
    }

    if ($cajaId !== null && $cajaId > 0) {
        $stmt = $this->ejecutarSqlVenta(
            "SELECT opened_at FROM cash_shifts
             WHERE cash_register_id = ? AND status = 'ABIERTO'
             ORDER BY id DESC LIMIT 1",
            "i", [$cajaId]
        );
        $turno = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$turno) {
            throw new InvalidArgumentException("La caja no tiene un turno abierto.");
        }

        if ($fechaSql < $turno["opened_at"]) {
            throw new InvalidArgumentException(
                "La fecha del movimiento no puede ser anterior a la apertura del turno de caja."
            );
        }

        /*if ($fechaSql < $turno["opened_at"]) {
            $fechaSql = $turno["opened_at"];
        }*/
    }

    return $fechaSql;
}

public function getCajasVenta() {
    $json = [];

    if (!$this->conn) {
        $json = ['status'=>'error','answer'=>'No existe conexión con MySQL.','code'=>500];
    } else {
        try {
                        
            $cajas = [];
            $stmt = $this->ejecutarSqlVenta("SELECT r.id,r.name,r.current_balance FROM cash_registers r WHERE r.kind='CASH' AND r.is_active=1 AND EXISTS (SELECT 1 FROM cash_shifts s WHERE s.cash_register_id=r.id AND s.status='ABIERTO') ORDER BY r.name ASC");
            $result = $stmt->get_result();

            while ($fila = $result->fetch_assoc()) {
                $fila['id'] = (int)$fila['id'];
                $cajas[] = $fila;
            }
            $stmt->close();
            $json = ['status'=>'ok','answer'=>'Cajas consultadas correctamente.','results'=>$cajas,'code'=>200];
        } catch (Throwable $e) {
            
            $json = ['status'=>'error','answer'=>'No fue posible consultar las cajas.','code'=>500];
        }
    }

    return $json;
}


public function getVentas($data = []) {
    $json = [];

    if (!$this->conn) {
        $json["status"] = "error";
        $json["answer"] = "No hay conexión con MySQL.";
        $json["code"] = 500;
    } else {
        try {
            $buscar = isset($data["buscar"]) ? trim((string)$data["buscar"]) : "";
            $tipo = isset($data["tipo"]) ? (string)$data["tipo"] : "TODAS";
            $inicio = isset($data["inicio"]) ? (string)$data["inicio"] : "";
            $fin = isset($data["fin"]) ? (string)$data["fin"] : "";
            $pagina = max(1, min(100000, isset($data["pagina"]) ? (int)$data["pagina"] : 1));
            $limite = 12;

            if (strlen($buscar) > 100 || !in_array($tipo, ["TODAS", "NOTA", "RAPIDA"], true)) {
                throw new InvalidArgumentException("Revisa los filtros de búsqueda.");
            }

            foreach ([$inicio, $fin] as $fecha) {
                if ($fecha === "") continue;
                $valida = DateTime::createFromFormat("!Y-m-d", $fecha);
                if (!$valida || $valida->format("Y-m-d") !== $fecha) {
                    throw new InvalidArgumentException("Indica fechas válidas.");
                }
            }
            
            if ($inicio !== "" && $fin !== "" && $inicio > $fin) {
                throw new InvalidArgumentException("La fecha inicial no puede superar la final.");
            }

            $where = " WHERE s.corp_id = ?
            AND s.sale_kind IN ('NOTA','RAPIDA')";

            $tipos = "s";
            $params = [$this->corpClientes()];


            if ($tipo !== "TODAS") {
                $where .= " AND s.sale_kind = ?";
                $tipos .= "s";
                $params[] = $tipo;
            }

            if ($inicio !== "") {
                $where .= " AND s.created_at >= ?";
                $tipos .= "s";
                $params[] = $inicio . " 00:00:00";
            }

            if ($fin !== "") {
                $where .= " AND s.created_at <= ?";
                $tipos .= "s";
                $params[] = $fin . " 23:59:59";
            }

            if ($buscar !== "") {
                $patron = "%" . str_replace(["!", "%", "_"], ["!!", "!%", "!_"], $buscar) . "%";
                $where .= " AND (
                    s.note_folio LIKE ? ESCAPE '!'
                    OR s.ticket_barcode LIKE ? ESCAPE '!'
                    OR s.client_name LIKE ? ESCAPE '!'
                    OR s.seller_name LIKE ? ESCAPE '!'
                    OR EXISTS (
                        SELECT 1 FROM sale_items si
                        WHERE si.sale_id = s.id
                        AND si.manual_name LIKE ? ESCAPE '!'
                    )
                )";
                $tipos .= "sssss";
                array_push($params, $patron, $patron, $patron, $patron, $patron);
            }

            $stmt = $this->ejecutarSqlVenta(
                "SELECT COUNT(*) AS total FROM sales s" . $where,
                $tipos, $params
            );
            $total = (int)$stmt->get_result()->fetch_assoc()["total"];
            $stmt->close();

            $paginas = max(1, (int)ceil($total / $limite));
            $pagina = min($pagina, $paginas);
            $offset = ($pagina - 1) * $limite;

            $sql = "SELECT s.id, s.sale_kind, s.note_folio,
                 s.ticket_barcode, s.cliente_id,
    s.client_name, s.seller_name,
                s.created_at, s.payment_method, s.total_amount,
                s.initial_paid, s.pending_amount
                FROM sales s" . $where . "
                ORDER BY s.created_at DESC, s.id DESC
                LIMIT ? OFFSET ?";

            $stmt = $this->ejecutarSqlVenta(
                $sql, $tipos . "ii", array_merge($params, [$limite, $offset])
            );
            $ventas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $posiciones = [];
            $ids = [];

            foreach ($ventas as $i => &$venta) {
                $venta["id"] = (int)$venta["id"];
                $venta["items"] = [];
                $posiciones[$venta["id"]] = $i;
                $ids[] = $venta["id"];
            }
            unset($venta);

            if ($ids) {
                $marcas = implode(",", array_fill(0, count($ids), "?"));

                $stmt = $this->ejecutarSqlVenta(
                    "SELECT sale_id, quantity, price, total, manual_name, manual_code, unit_code AS unidad 
                    FROM sale_items WHERE sale_id IN (" . $marcas . ")
                    ORDER BY sale_id, id",
                    str_repeat("i", count($ids)), $ids
                );

                $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();

                foreach ($items as $item) {
                    $ventas[$posiciones[(int)$item["sale_id"]]]["items"][] = $item;
                }
            }

            $json["status"] = "ok";
            $json["answer"] = "Ventas consultadas correctamente.";
            $json["results"] = [
                "ventas" => $ventas,
                "total" => $total,
                "pagina" => $pagina,
                "paginas" => $paginas
            ];
            $json["code"] = 200;

        } catch (InvalidArgumentException $e) {
            $json["status"] = "warning";
            $json["answer"] = $e->getMessage();
            $json["code"] = 400;
        } catch (Throwable $e) {
            
            $json["status"] = "error";
            $json["answer"] = "No fue posible consultar el historial de ventas.";
            $json["code"] = 500;
        }
    }

    return $json;
}


private function resolverClienteVentaNota($corpId, $clienteId, $nombre) {
    $registro = null;

    if ($clienteId !== null) {
        $stmt = $this->ejecutarSqlVenta(
            "SELECT id, nombre, apellido, tipo
             FROM clientes
             WHERE corp_id = ?
               AND id = ?
               AND eliminado = 0
             LIMIT 1 FOR UPDATE",
            "si",
            [$corpId, $clienteId]
        );

        $registro = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$registro) {
            throw new InvalidArgumentException(
                "El cliente seleccionado ya no está disponible."
            );
        }

    } elseif ($nombre !== '') {
        $stmt = $this->ejecutarSqlVenta(
            "SELECT id, nombre, apellido, tipo
             FROM clientes
             WHERE corp_id = ?
               AND eliminado = 0
               AND TRIM(CONCAT_WS(' ', nombre, apellido))
                   COLLATE utf8mb4_general_ci
                   = ? COLLATE utf8mb4_general_ci
             LIMIT 2 FOR UPDATE",
            "ss",
            [$corpId, $nombre]
        );

        $resultado = $stmt->get_result();
        $registro = $resultado->fetch_assoc();
        $segunda = $resultado->fetch_assoc();
        $stmt->close();

        if ($segunda) {
            throw new InvalidArgumentException(
                "Existe más de un cliente con ese nombre. Selecciona el cliente correcto del catálogo."
            );
        }
    }

    if ($registro) {
    $idCliente = (int)$registro['id'];
    $tipoCliente = (int)$registro['tipo'];

    $nombreCompleto = trim(
        (string)$registro['nombre'] . ' ' .
        (string)($registro['apellido'] ?? '')
    );

    if ($nombreCompleto === '' || strlen($nombreCompleto) > 100) {
        throw new InvalidArgumentException(
            "El nombre del cliente no es válido para la nota."
        );
    }

    // CONVERTIR PROSPECTO EN CLIENTE
    if ($tipoCliente === 0) {
        $stmt = $this->ejecutarSqlVenta(
            "UPDATE clientes
             SET tipo = 1,
                 fechaEditado = NOW()
             WHERE corp_id = ?
               AND id = ?
               AND tipo = 0
               AND eliminado = 0",
            "si",
            [$corpId, $idCliente]
        );

        $stmt->close();

    } elseif ($tipoCliente !== 1) {
        throw new InvalidArgumentException(
            "El tipo de cliente no es válido."
        );
    }

    return [
        'id' => $idCliente,
        'nombre' => $nombreCompleto,
        'nuevo' => false,
        'convertido' => $tipoCliente === 0
    ];
}

    if ($nombre === '') {
        return [
            'id' => null,
            'nombre' => 'Público general',
            'nuevo' => false,
            'convertido' => false
        ];
    }

    $stmt = $this->ejecutarSqlVenta(
        "INSERT INTO clientes (
            corp_id,
            nombre,
            apellido,
            tipo,
            tipo_precio_id,
            limite_credito,
            formAlta,
            eliminado
        ) VALUES (
            ?, ?, NULL, 1, 1, 0, 'ORGANICO', 0
        )",
        "ss",
        [$corpId, $nombre]
    );

    $idNuevo = (int)$this->conn->insert_id;
    $stmt->close();

    if ($idNuevo <= 0) {
        throw new RuntimeException(
            "No fue posible obtener el ID del nuevo cliente."
        );
    }

    return [
        'id' => $idNuevo,
        'nombre' => $nombre,
        'nuevo' => true,
        'convertido' => false
    ];
}



public function guardarVentaNota($data) {
    $json = [];
   $transaccion = false;
    $token = '';
    $etapa = "validaciones";
 

    if (!$this->conn) {
        $json = ['status'=>'error','answer'=>'No existe conexión con MySQL.','code'=>500];
    } else {
        try {
            $venta = isset($data['venta']) && is_string($data['venta']) ? json_decode($data['venta'], true) : null;
            if (!is_array($venta)) throw new InvalidArgumentException('No se recibieron datos válidos de la venta.');

$modoFecha = isset($venta["modo_fecha"])
    ? (string)$venta["modo_fecha"]
    : "";

$fechaSolicitada = isset($venta["fecha_operacion"])
    ? trim((string)$venta["fecha_operacion"])
    : "";

if (!in_array($modoFecha, ["AUTOMATICA", "MANUAL"], true)) {
    throw new InvalidArgumentException(
        "Actualiza la página. Se detectó una versión anterior del formulario."
    );
}

if ($modoFecha === "MANUAL" && $fechaSolicitada === "") {
    throw new InvalidArgumentException(
        "No se recibió la fecha seleccionada por el administrador."
    );
}

if ($modoFecha === "AUTOMATICA" && $fechaSolicitada !== "") {
    throw new InvalidArgumentException(
        "La modalidad de fecha no coincide con la fecha enviada."
    );
}


            $token = $this->textoVenta($venta, 'solicitud_id', 32);
            if (!preg_match('/^[a-f0-9]{32}$/D', $token)) throw new InvalidArgumentException('Identificador de solicitud inválido.');

            //$cliente = '';
            $cliente = $this->textoVenta($venta, 'cliente', 100);
            $cliente = preg_replace('/\s+/u', ' ', $cliente);
            $vendedor = $this->textoVenta($venta, 'vendedor', 100);
            $direccion = $this->textoVenta($venta, 'direccion', 255);
            $telefono = $this->textoVenta($venta, 'telefono', 50);
            $folioManual = $this->textoVenta($venta, 'folio', 50);
            $observaciones = $this->textoVenta($venta, 'observaciones', 500);
            $forma = $this->textoVenta($venta, 'forma_pago', 30);
            $metodoAbono = $this->textoVenta($venta, 'metodo_abono', 30);
            $referencia = $this->textoVenta($venta, 'referencia', 80);
            $referenciaAbono = $this->textoVenta($venta, 'referencia_abono', 80);
            $cajaId = isset($venta['caja_id']) ? (int)$venta['caja_id'] : 0;

            if ($vendedor === '') {
                throw new InvalidArgumentException(
                    'Debes indicar el vendedor.'
                );
            }
            
            

            /*$clienteId = filter_var(
                $venta['cliente_id'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            if ($clienteId === false) {
                throw new InvalidArgumentException(
                    'Debes seleccionar un cliente registrado.'
                );
            }

            $corpId = $this->corpClientes();

            $stmt = $this->ejecutarSqlVenta(
                "SELECT id, nombre, apellido, tipo
                 FROM clientes
                 WHERE corp_id = ?
                   AND id = ?
                   AND eliminado = 0
                 LIMIT 1",
                "si",
                [$corpId, $clienteId]
            );

            $registroCliente = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$registroCliente) {
                throw new InvalidArgumentException(
                    'El cliente seleccionado no existe o fue eliminado.'
                );
            }

            if ((int)$registroCliente['tipo'] !== 1) {
                throw new InvalidArgumentException(
                    'No puedes registrar ventas a prospectos.'
                );
            }

            $cliente = trim(
                (string)$registroCliente['nombre'] . ' ' .
                (string)($registroCliente['apellido'] ?? '')
            );

            if ($cliente === '' || strlen($cliente) > 100) {
                throw new InvalidArgumentException(
                    'El nombre del cliente no es válido para la nota.'
                );
            }*/

                $corpId = $this->corpClientes();
$clienteId = null;

if (
    isset($venta['cliente_id']) &&
    $venta['cliente_id'] !== ''
) {
    $clienteId = filter_var(
        $venta['cliente_id'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($clienteId === false) {
        throw new InvalidArgumentException(
            'El identificador del cliente no es válido.'
        );
    }
}

$requiereCliente = in_array(
    $forma,
    ['CREDITO', 'PARCIALIDADES'],
    true
);

if ($requiereCliente && $clienteId === null && $cliente === '') {
    throw new InvalidArgumentException(
        'Indica el nombre del cliente para registrar una venta con saldo pendiente.'
    );
}


            if ($folioManual !== '' && !preg_match('~^[A-Za-z0-9][A-Za-z0-9._/-]{0,49}$~D', $folioManual)) throw new InvalidArgumentException('El folio solamente permite letras, números, puntos, guiones y diagonales.');
            if (!in_array($forma, ['EFECTIVO','TRANSFERENCIA','CREDITO','PARCIALIDADES'], true)) throw new InvalidArgumentException('Modalidad de pago inválida.');
            if (!isset($venta['items']) || !is_array($venta['items']) || count($venta['items']) < 1 || count($venta['items']) > 100) throw new InvalidArgumentException('Agrega entre 1 y 100 artículos.');

            $items = [];
            $totalCentavos = 0;

            foreach ($venta['items'] as $index => $item) {
                if (!is_array($item) || (isset($item['origen']) && $item['origen'] !== 'MANUAL')) throw new InvalidArgumentException('Origen de artículo no permitido.');

                $nombre = $this->textoVenta($item, 'nombre', 200);
                $codigo = $this->textoVenta($item, 'codigo', 80);
                $unidad = $this->unidadVenta($item, $codigo);
                $cantidad100 = $this->centavosVenta(isset($item['cantidad']) ? $item['cantidad'] : '');
                $precioCentavos = $this->centavosVenta(isset($item['precio']) ? $item['precio'] : '');

                if ($nombre === '' || $cantidad100 === null || $cantidad100 < 1 || $cantidad100 > 10000000 || $precioCentavos === null || $precioCentavos > 100000000) {
                    throw new InvalidArgumentException('Revisa descripción, cantidad o precio del artículo ' . ($index + 1) . '.');
                }

                $importeCentavos = (int)round(($cantidad100 * $precioCentavos) / 100);
                if ($importeCentavos > 9999999999) throw new InvalidArgumentException('El importe de un artículo supera el máximo permitido.');

                $totalCentavos += $importeCentavos;
                if ($totalCentavos > 9999999999) throw new InvalidArgumentException('El total supera $99,999,999.99.');

                $items[] = [
                    'nombre'=>$nombre,
                    'codigo'=>$codigo,
                    'cantidad'=>number_format($cantidad100 / 100, 2, '.', ''),
                    'unidad'=>$unidad,
                    'precio'=>number_format($precioCentavos / 100, 2, '.', ''),
                    'importe'=>number_format($importeCentavos / 100, 2, '.', '')
                ];
            }

            if ($totalCentavos <= 0) throw new InvalidArgumentException('El total de la venta debe ser mayor que cero.');

            $pagadoCentavos = 0;
            $metodoCobro = '';
            $referenciaCobro = '';

            if ($forma === 'EFECTIVO' || $forma === 'TRANSFERENCIA') {
                $pagadoCentavos = $totalCentavos;
                $metodoCobro = $forma;
                $referenciaCobro = $forma === 'TRANSFERENCIA' ? $referencia : '';
            } elseif ($forma === 'PARCIALIDADES') {
                $pagadoCentavos = $this->centavosVenta(isset($venta['abono']) ? $venta['abono'] : '');
                if ($pagadoCentavos === null || $pagadoCentavos <= 0 || $pagadoCentavos >= $totalCentavos) throw new InvalidArgumentException('El abono debe ser mayor que cero y menor que el total.');
                if (!in_array($metodoAbono, ['EFECTIVO','TRANSFERENCIA'], true)) throw new InvalidArgumentException('Método del abono inválido.');
                $metodoCobro = $metodoAbono;
                $referenciaCobro = $metodoAbono === 'TRANSFERENCIA' ? $referenciaAbono : '';
            }

            if ($pagadoCentavos > 0 && $metodoCobro === 'EFECTIVO' && $cajaId <= 0) throw new InvalidArgumentException('Selecciona una caja activa para recibir efectivo.');

            $saldoCentavos = $totalCentavos - $pagadoCentavos;
            $total = number_format($totalCentavos / 100, 2, '.', '');
            $pagado = number_format($pagadoCentavos / 100, 2, '.', '');
            $saldo = number_format($saldoCentavos / 100, 2, '.', '');

            if ($this->usuarioOperador <= 0) throw new InvalidArgumentException('Configura el usuario operador en consultas.php.');

            $stmt = $this->ejecutarSqlVenta('SELECT id FROM users WHERE id = ? LIMIT 1', 'i', [$this->usuarioOperador]);
            $existeUsuario = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$existeUsuario) throw new InvalidArgumentException('El usuario operador configurado no existe en users.');

            
            //$stmt = $this->ejecutarSqlVenta('SELECT id, note_folio FROM sales WHERE request_token = ? LIMIT 1', 's', [$token]);
            
            $stmt = $this->ejecutarSqlVenta(
    "SELECT id, note_folio, created_at FROM sales WHERE request_token = ? LIMIT 1",
    "s",
    [$token]
);
            $anterior = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($anterior) {

if ($modoFecha === "MANUAL") {
    $fechaEsperada = str_replace("T", " ", $fechaSolicitada);

    if (substr((string)$anterior["created_at"], 0, 16) !== $fechaEsperada) {
        throw new InvalidArgumentException(
            "Esta nota ya fue registrada con una fecha diferente. " .
            "No se modificó la venta existente."
        );
    }
}

                $json = [
                    'status'=>'ok','answer'=>'Esta nota ya estaba registrada; no se duplicó.',
                    'results'=>['id'=>(int)$anterior['id'],'folio'=>$anterior['note_folio'],'duplicada'=>true],
                    'code'=>200
                ];
            } else {
                $fecha = date('Y-m-d H:i:s');
                $idCajaVenta = $pagadoCentavos > 0 && $metodoCobro === 'EFECTIVO' ? $cajaId : null;
                //$fechaOperacion = $this->fechaOperacion($venta, $idCajaVenta);
                $fechaOperacion = $this->fechaOperacion($venta);

// Fecha del movimiento de efectivo: corresponde al turno actual.
$fechaCobro = $idCajaVenta !== null ? $fecha : $fechaOperacion;
                $folioInicial = $folioManual !== '' ? $folioManual : null;
                $ticketInicial = null;

                if (!$this->conn->begin_transaction()) throw new RuntimeException('No fue posible iniciar la transacción.');
                $transaccion = true;

                if ($idCajaVenta !== null) {
                    $stmt = $this->ejecutarSqlVenta("SELECT id FROM cash_registers WHERE id = ? AND kind = 'CASH' AND is_active = 1 FOR UPDATE", 'i', [$idCajaVenta]);
                    $cajaValida = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    
                    if (!$cajaValida) throw new InvalidArgumentException('La caja seleccionada ya no está activa.');
                    
                    $stmt = $this->ejecutarSqlVenta("SELECT id FROM cash_shifts WHERE cash_register_id = ? AND status = 'ABIERTO' LIMIT 1", "i", [$idCajaVenta]);
$turnoAbierto = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$turnoAbierto) throw new InvalidArgumentException("Debes abrir un turno de caja antes de recibir efectivo.");
                }

                $etapa = "resolver_cliente";

$datosCliente = $this->resolverClienteVentaNota(
    $corpId,
    $clienteId,
    $cliente
);

$clienteId = $datosCliente['id'];
$cliente = $datosCliente['nombre'];
$clienteNuevo = $datosCliente['nuevo'];
$clienteConvertido = $datosCliente['convertido'];

if ($saldoCentavos > 0 && $clienteId === null) {
    throw new InvalidArgumentException(
        'No se puede registrar una cuenta por cobrar sin cliente.'
    );
}

                $etapa = "insertar_venta";
                

$stmt = $this->ejecutarSqlVenta(
    "INSERT INTO sales (
        user_id, corp_id, client_name, cliente_id,
        client_address, client_phone, payment_method,
        bank_reference, cash_register_id, pending_amount,
        total_amount, ticket_barcode, notes, created_at,
        updated_at, sale_kind, note_folio, seller_name,
        initial_paid, request_token
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
    "ississssisssssssssss",
    [
        $this->usuarioOperador,
        $corpId,
        $cliente,
        $clienteId,
        $direccion,
        $telefono,
        $forma,
        $referenciaCobro,
        $idCajaVenta,
        $saldo,
        $total,
        $ticketInicial,
        $observaciones,
        $fechaOperacion,
        $fecha,
        'NOTA',
        $folioInicial,
        $vendedor,
        $pagado,
        $token
    ]
);


                $stmt->close();

                $idVenta = (int)$this->conn->insert_id;
                $folio = $folioInicial !== null ? $folioInicial : 'N-' . str_pad((string)$idVenta, 8, '0', STR_PAD_LEFT);

                $stmt = $this->ejecutarSqlVenta('UPDATE sales SET note_folio = ?, ticket_barcode = ? WHERE id = ?', 'ssi', [$folio,$folio,$idVenta]);
                $stmt->close();


$stmtFecha = $this->ejecutarSqlVenta(
    "SELECT created_at FROM sales WHERE id = ? LIMIT 1",
    "i",
    [$idVenta]
);

$filaFecha = $stmtFecha->get_result()->fetch_assoc();
$stmtFecha->close();

$fechaGuardada = isset($filaFecha["created_at"])
    ? (string)$filaFecha["created_at"]
    : "";

if ($modoFecha === "MANUAL") {
    $fechaEsperada = str_replace("T", " ", $fechaSolicitada);

    if (substr($fechaGuardada, 0, 16) !== $fechaEsperada) {
        throw new RuntimeException(
            "La fecha almacenada no coincide con la fecha seleccionada."
        );
    }
}


                $etapa = "insertar_articulos";
                
$stmtItem = $this->conn->prepare(
    "INSERT INTO sale_items
    (sale_id,product_id,quantity,price,total,item_origin,manual_name,manual_code,unit_code)
    VALUES (?,NULL,?,?,?,'MANUAL',?,?,?)"
);

if (!$stmtItem) throw new RuntimeException($this->conn->error);

$cantidad = $precio = $importe = '0.00';
$nombreArticulo = $codigoArticulo = $unidadArticulo = '';

if (!$stmtItem->bind_param(
    'issssss',
    $idVenta, $cantidad, $precio, $importe,
    $nombreArticulo, $codigoArticulo, $unidadArticulo
)) {
    throw new RuntimeException($stmtItem->error);
}

foreach ($items as $item) {
    $cantidad = $item['cantidad'];
    $precio = $item['precio'];
    $importe = $item['importe'];
    $nombreArticulo = $item['nombre'];
    $codigoArticulo = $item['codigo'];
    $unidadArticulo = $item['unidad'];

    if (!$stmtItem->execute()) {
        $error = new RuntimeException($stmtItem->error);
        $stmtItem->close();
        throw $error;
    }
}

$stmtItem->close();


                /*if ($saldoCentavos > 0) {
                    $stmt = $this->ejecutarSqlVenta(
                        "INSERT INTO receivables (sale_id,client_id,client_name,user_id,total_amount,paid_amount,pending_amount,status,due_date,created_at,updated_at) VALUES (?,NULL,?,?,?,?,?,'PENDIENTE',NULL,?,?)",
                        'isisssss', [$idVenta,$cliente,$this->usuarioOperador,$total,$pagado,$saldo,$fecha,$fecha]
                    );
                    $stmt->close();
                }


$stmt = $this->ejecutarSqlVenta(
    "UPDATE receivables SET document_reference=? WHERE sale_id=?",
    "si",[$folio,$idVenta]
);
$stmt->close();*/


$etapa = "registrar_cuenta_cobrar";

if ($saldoCentavos > 0) {
    $descripcionCuenta = "Venta con nota " . $folio;

    

$stmt = $this->ejecutarSqlVenta(
    "INSERT INTO receivables (
        sale_id, client_id, client_name, user_id,
        total_amount, paid_amount, pending_amount,
        status, due_date, created_at, updated_at,
        description, document_reference, request_token
    ) VALUES (
        ?,?,?,?,?,?,?,
        'PENDIENTE',NULL,?,?,?,?,?
    )",
    "iisissssssss",
    [
        $idVenta,
        $clienteId,
        $cliente,
        $this->usuarioOperador,
        $total,
        $pagado,
        $saldo,
        $fechaOperacion,
        $fecha,
        $descripcionCuenta,
        $folio,
        $token
    ]
);



    $stmt->close();
}

$etapa = "registrar_cobro";
                if ($pagadoCentavos > 0) {
                    $origen = $metodoCobro === 'EFECTIVO' ? 'CAJA' : 'BANCO';
                    $referenciaMovimiento = $referenciaCobro !== '' ? $referenciaCobro : $folio;
                    $notaMovimiento = 'Cobro inicial de venta';

                    $stmt = $this->ejecutarSqlVenta(
                        "INSERT INTO account_moves (`date`,`type`,`origin`,cash_register_id,user_reference_name,reference,amount,user_id,link_type,link_id,ticket_number,status,note,notes,created_at,updated_at) VALUES (?,'VENTA',?,?,?,?,?,?,'VENTA',?,?,'PAGADO',?,?,?,?)",
                        
'ssisssiisssss', [
    $fechaCobro,
    $origen,
    $idCajaVenta,
    $vendedor,
    $referenciaMovimiento,
    $pagado,
    $this->usuarioOperador,
    $idVenta,
    $folio,
    $notaMovimiento,
    $observaciones,
    $fecha,
    $fecha
]

                    );
                    $stmt->close();

                    if ($metodoCobro === 'EFECTIVO') {
                        $descripcion = 'Cobro de venta ' . $folio;

                        $stmt = $this->ejecutarSqlVenta(
                            "INSERT INTO cash_flows (cash_register_id,user_id,amount,`type`,description,reference,created_at) VALUES (?,?,?,'IN',?,?,?)",
                            'iissss', [$idCajaVenta,$this->usuarioOperador,$pagado,$descripcion,$folio,$fecha]
                        );
                        $stmt->close();

                        $stmt = $this->ejecutarSqlVenta(
                            "UPDATE cash_registers SET current_balance = current_balance + ?, updated_at = ? WHERE id = ? AND kind = 'CASH' AND is_active = 1",
                            'ssi', [$pagado,$fecha,$idCajaVenta]
                        );
                        if ($stmt->affected_rows !== 1) throw new RuntimeException('No se pudo actualizar la caja.');
                        $stmt->close();
                    }
                }

                $etapa = "confirmar_transaccion";
                if (!$this->conn->commit()) throw new RuntimeException('No fue posible confirmar la transacción.');
                $transaccion = false;

                $json = [
                    'status'=>'ok',
                    'answer'=>'Venta registrada correctamente.',
                    'results'=>[
    'id' => $idVenta,
    'folio' => $folio,
    'total' => $total,
    'recibido' => $pagado,
    'saldo' => $saldo,
    'forma_pago' => $forma,
    'cliente_id' => $clienteId,
    'cliente' => $cliente,
    'cliente_nuevo' => $clienteNuevo,
    'cliente_convertido' => $clienteConvertido,
    'duplicada' => false
],
                    'code'=>200
                ];
            }
        } catch (InvalidArgumentException $e) {
            if ($transaccion) $this->conn->rollback();
            $json = ['status'=>'warning','answer'=>$e->getMessage(),'code'=>400];
        } catch (Throwable $e) {
            if ($transaccion) $this->conn->rollback();
            
            //$json = ['status'=>'error','answer'=>'No fue posible confirmar el registro de la venta.','code'=>500];




$json["status"] = "error";
$json["answer"] = "No fue posible confirmar el registro de la venta.";
$json["code"] = 500;
$json["diagnostico"] = [
    "etapa" => $etapa,
    "codigo" => (int)$e->getCode()
];


            if ((int)$e->getCode() === 1062) {
                try {
                    //$stmt = $this->ejecutarSqlVenta('SELECT id, note_folio FROM sales WHERE request_token = ? LIMIT 1', 's', [$token]);
                    
$stmt = $this->ejecutarSqlVenta(
    "SELECT id, note_folio, created_at
     FROM sales
     WHERE request_token = ?
     LIMIT 1",
    "s",
    [$token]
);

                    
                    $repetida = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    if (
    $repetida &&
    (
        $modoFecha !== "MANUAL" ||
        substr((string)$repetida["created_at"], 0, 16) ===
        str_replace("T", " ", $fechaSolicitada)
    )
) {
                        $json = [
                            'status'=>'ok','answer'=>'La venta ya había sido registrada; no se duplicó.',
                            'results'=>['id'=>(int)$repetida['id'],'folio'=>$repetida['note_folio'],'duplicada'=>true],
                            'code'=>200
                        ];
                    } else {
                        $json = ['status'=>'warning','answer'=>'El folio ya está utilizado en otra nota.','code'=>409];
                    }
                } catch (Throwable $otroError) {
                    
                }
            }
        }
    }

    return $json;
}


private function getDatosNotaVentaPdf($idVenta)
{
    $json = array();

    //$stmt = $this->conn->prepare("SELECT id, note_folio, client_name, client_address, client_phone, seller_name, payment_method, bank_reference, total_amount, initial_paid, pending_amount, notes, created_at FROM sales WHERE id = ? AND sale_kind = 'NOTA' LIMIT 1");
    
    
$stmt = $this->conn->prepare("SELECT s.id, s.note_folio, s.client_name, s.client_address,
    s.client_phone, s.seller_name, s.payment_method, s.bank_reference,
    s.total_amount, s.initial_paid, s.pending_amount, s.notes, s.created_at,
    r.id AS cuenta_id, r.total_amount AS cuenta_total, r.paid_amount AS cuenta_pagado,
    r.pending_amount AS cuenta_saldo, r.status AS cuenta_estado
    FROM sales s
    LEFT JOIN receivables r ON r.sale_id = s.id
    WHERE s.id = ? AND s.sale_kind = 'NOTA' LIMIT 1");


    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("i", $idVenta);

    if (!$stmt->execute()) {
        $stmt->close();
        return null;
    }

    $result = $stmt->get_result();
    $venta = $result->fetch_assoc();
    $stmt->close();

    if (!$venta) {
        return null;
    }


$total = $this->centavosVenta($venta["total_amount"]);
$saldo = $this->centavosVenta($venta["pending_amount"]);
$inicial = $this->centavosVenta($venta["initial_paid"]);
$tieneCuenta = $venta["cuenta_id"] !== null;

$cuentaLiquidada = $tieneCuenta && $venta["cuenta_estado"] === "PAGADO"
    && $this->centavosVenta($venta["cuenta_total"]) === $total
    && $this->centavosVenta($venta["cuenta_pagado"]) === $total
    && $this->centavosVenta($venta["cuenta_saldo"]) === 0;

$venta["pagada"] = $total !== null && $total > 0 && $saldo === 0
    && ($tieneCuenta ? $cuentaLiquidada : $inicial === $total);

$venta["pagado_total"] = ($total !== null && $saldo !== null && $saldo >= 0 && $saldo <= $total)
    ? number_format(($total - $saldo) / 100, 2, ".", "") : $venta["initial_paid"];


    //$stmtItems = $this->conn->prepare("SELECT quantity, price, total, manual_name, manual_code FROM sale_items WHERE sale_id = ? ORDER BY id ASC");
    
$stmtItems = $this->conn->prepare(
    "SELECT si.quantity, si.price, si.total, si.manual_name, si.manual_code,
            COALESCE(
                NULLIF(TRIM(si.unit_code), ''),
                NULLIF(TRIM(p.unit), ''),
                pc.unit,
                '-'
            ) AS unidad
     FROM sale_items si
     LEFT JOIN products p ON p.id = si.product_id
     LEFT JOIN (
         SELECT code, MAX(NULLIF(TRIM(unit), '')) AS unit
         FROM products
         WHERE code IS NOT NULL AND code <> ''
         GROUP BY code
         HAVING COUNT(*) = 1
     ) pc ON pc.code = si.manual_code
     WHERE si.sale_id = ?
     ORDER BY si.id ASC"
);

    
    if (!$stmtItems) {
        return null;
    }

    $stmtItems->bind_param("i", $idVenta);

    if (!$stmtItems->execute()) {
        $stmtItems->close();
        return null;
    }

    $resultItems = $stmtItems->get_result();
    $venta["items"] = array();

    while ($item = $resultItems->fetch_assoc()) {
        $venta["items"][] = $item;
    }

    $stmtItems->close();

    return $venta;
}

public function generarPdfNotaVenta($idVenta)
{
    $json = array();

    if (!$this->conn) {
        $json["status"] = "error";
        $json["answer"] = "No hay conexión con MySQL.";
        $json["code"] = 500;
        return $json;
    }

    if ($idVenta <= 0) {
        $json["status"] = "warning";
        $json["answer"] = "ID de venta inválido.";
        $json["code"] = 400;
        return $json;
    }

    try {
        $venta = $this->getDatosNotaVentaPdf($idVenta);

        if (!$venta) {
            $json["status"] = "warning";
            $json["answer"] = "No se encontró la nota de venta.";
            $json["code"] = 404;
            return $json;
        }

        $pdf = new notaVenta();
        $archivo = $pdf->generarArchivo($venta);

        $json["status"] = "ok";
        $json["answer"] = "Nota de venta generada correctamente.";
        $json["results"] = array(
            "id" => (int)$venta["id"],
            "folio" => $venta["note_folio"],
            "archivo" => $archivo["ruta_web"],
            "nombre" => $archivo["nombre"]
        );
        $json["code"] = 200;
    
} catch (Throwable $e) {

    $json["status"] = "error";
    $json["answer"] = "No fue posible generar la nota de venta.";
    $json["code"] = 500;

    if (defined("DEBUG_NOTA_PDF") && DEBUG_NOTA_PDF) {
        $json["debug"] = [
            "mensaje" => $e->getMessage(),
            "archivo" => basename($e->getFile()),
            "linea" => $e->getLine()
        ];
    }
}

    return $json;
}


public function guardarVentaRapida($data) {
    $json = [];
    $transaccion = false;
    $token = '';

    try {
        $venta = isset($data["venta"]) && is_string($data["venta"]) ? json_decode($data["venta"], true) : null;
        if (!is_array($venta)) throw new InvalidArgumentException("Datos de venta inválidos.");


$modoFecha = isset($venta["modo_fecha"])
    ? (string)$venta["modo_fecha"]
    : "";

$fechaSolicitada = isset($venta["fecha_operacion"])
    ? trim((string)$venta["fecha_operacion"])
    : "";

if (!in_array($modoFecha, ["AUTOMATICA", "MANUAL"], true)) {
    throw new InvalidArgumentException(
        "Actualiza la página. Se detectó una versión anterior del formulario."
    );
}

if ($modoFecha === "MANUAL" && $fechaSolicitada === "") {
    throw new InvalidArgumentException(
        "No se recibió la fecha seleccionada por el administrador."
    );
}

if ($modoFecha === "AUTOMATICA" && $fechaSolicitada !== "") {
    throw new InvalidArgumentException(
        "La modalidad de fecha no coincide con la fecha enviada."
    );
}


        $token = $this->textoVenta($venta, "solicitud_id", 32);
        if (!preg_match('/^[a-f0-9]{32}$/D', $token)) throw new InvalidArgumentException("Identificador de solicitud inválido.");

        $forma = $this->textoVenta($venta, "forma_pago", 30);
        $referencia = $this->textoVenta($venta, "referencia", 80);
        $observaciones = $this->textoVenta($venta, "observaciones", 500);
        $cajaId = isset($venta["caja_id"]) && ctype_digit((string)$venta["caja_id"]) ? (int)$venta["caja_id"] : 0;

        if (!in_array($forma, ["EFECTIVO", "TRANSFERENCIA"], true)) throw new InvalidArgumentException("Solo se admite efectivo o transferencia.");
        if ($forma === "EFECTIVO" && $cajaId <= 0) throw new InvalidArgumentException("Selecciona una caja para recibir efectivo.");
        if ($forma !== "TRANSFERENCIA") $referencia = "";
        if (!isset($venta["items"]) || !is_array($venta["items"]) || count($venta["items"]) < 1 || count($venta["items"]) > 100) throw new InvalidArgumentException("Agrega entre 1 y 100 artículos.");

        $items = [];
        $totalCentavos = 0;

        foreach ($venta["items"] as $i => $item) {
            if (!is_array($item) || ($item["origen"] ?? "") !== "MANUAL") throw new InvalidArgumentException("Origen de artículo no válido.");

            $nombre = $this->textoVenta($item, "nombre", 200);
            $codigo = $this->textoVenta($item, "codigo", 80);
            $unidad = $this->unidadVenta($item, $codigo);
            $cantidad100 = $this->centavosVenta($item["cantidad"] ?? "");
            $precioCentavos = $this->centavosVenta($item["precio"] ?? "");

            if ($nombre === "" || $cantidad100 === null || $cantidad100 < 1 || $cantidad100 > 10000000 || $precioCentavos === null || $precioCentavos > 100000000) {
                throw new InvalidArgumentException("Revisa el artículo " . ($i + 1) . ".");
            }

            $importeCentavos = intdiv($cantidad100 * $precioCentavos + 50, 100);
            $totalCentavos += $importeCentavos;

            if ($totalCentavos > 9999999999) throw new InvalidArgumentException("El total supera el máximo permitido.");

            $items[] = [
                "nombre" => $nombre,
                "codigo" => $codigo,
                "cantidad" => number_format($cantidad100 / 100, 2, ".", ""),
                "unidad" => $unidad,
                "precio" => number_format($precioCentavos / 100, 2, ".", ""),
                "importe" => number_format($importeCentavos / 100, 2, ".", "")
            ];
        }

        if ($totalCentavos <= 0) throw new InvalidArgumentException("El total debe ser mayor que cero.");

        $total = number_format($totalCentavos / 100, 2, ".", "");
        $usuario = (int)$this->usuarioOperador;
        if ($usuario <= 0) throw new InvalidArgumentException("Configura el operador.");

        $stmt = $this->ejecutarSqlVenta(
            "SELECT id, sale_kind, ticket_barcode, total_amount, payment_method, created_at FROM sales WHERE request_token = ? LIMIT 1",
            "s", [$token]
        );
        $anterior = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($anterior) {


if ($modoFecha === "MANUAL") {
    $fechaEsperada = str_replace("T", " ", $fechaSolicitada);

    if (substr((string)$anterior["created_at"], 0, 16) !== $fechaEsperada) {
        throw new InvalidArgumentException(
            "Esta solicitud ya tiene una venta registrada con otra fecha. " .
            "No se modificó el registro existente."
        );
    }
}


            if ($anterior["sale_kind"] !== "RAPIDA" || $anterior["payment_method"] !== $forma || $this->centavosVenta($anterior["total_amount"]) !== $totalCentavos) {
                throw new InvalidArgumentException("Este identificador ya se utilizó para otra operación.");
            }

            $json["status"] = "ok";
            $json["answer"] = "Esta venta ya estaba registrada; no se duplicó.";
            $json["results"] = [
                "id" => (int)$anterior["id"],
                "referencia" => $anterior["ticket_barcode"],
                "total" => $anterior["total_amount"],
                "forma_pago" => $forma,
                "duplicada" => true
            ];
            $json["code"] = 200;
        } else {
            $stmt = $this->ejecutarSqlVenta("SELECT id FROM users WHERE id = ? LIMIT 1", "i", [$usuario]);
            $existeUsuario = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$existeUsuario) throw new InvalidArgumentException("El operador configurado no existe.");

            if (!$this->conn->begin_transaction()) throw new RuntimeException("No se pudo iniciar la transacción.");
            $transaccion = true;

            $fecha = date("Y-m-d H:i:s");
            $idCajaVenta = $forma === "EFECTIVO" ? $cajaId : null;
            //$fechaOperacion = $this->fechaOperacion($venta, $idCajaVenta);
            $fechaOperacion = $this->fechaOperacion($venta);
            $fechaCobro = $idCajaVenta !== null ? $fecha : $fechaOperacion;

            if ($idCajaVenta !== null) {
                $stmt = $this->ejecutarSqlVenta(
                    "SELECT id FROM cash_registers WHERE id = ? AND kind = 'CASH' AND is_active = 1 FOR UPDATE",
                    "i", [$idCajaVenta]
                );
                $caja = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$caja) throw new InvalidArgumentException("La caja no existe o está inactiva.");

                $stmt = $this->ejecutarSqlVenta(
                    "SELECT id FROM cash_shifts WHERE cash_register_id = ? AND status = 'ABIERTO' LIMIT 1 FOR UPDATE",
                    "i", [$idCajaVenta]
                );
                $turno = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$turno) throw new InvalidArgumentException("Abre un turno de caja antes de cobrar en efectivo.");
            }

            
$stmt = $this->ejecutarSqlVenta(
    "INSERT INTO sales (
        user_id, corp_id, client_name, client_address,
        client_phone, payment_method, bank_reference,
        cash_register_id, pending_amount, total_amount,
        ticket_barcode, notes, created_at, updated_at,
        sale_kind, note_folio, seller_name, initial_paid,
        request_token
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
    "issssssisssssssssss",
    [
        $usuario,
        $this->corpClientes(),
        "Mostrador",
        "",
        "",
        $forma,
        $referencia,
        $idCajaVenta,
        "0.00",
        $total,
        null,
        $observaciones,
        $fechaOperacion,
        $fecha,
        "RAPIDA",
        null,
        "Mostrador",
        $total,
        $token
    ]
);

            $stmt->close();

            $idVenta = (int)$this->conn->insert_id;
            $numero = "VR-" . str_pad((string)$idVenta, 8, "0", STR_PAD_LEFT);

            $stmt = $this->ejecutarSqlVenta(
                "UPDATE sales SET ticket_barcode = ? WHERE id = ?",
                "si", [$numero, $idVenta]
            );
            $stmt->close();


if ($modoFecha === "MANUAL") {
    $stmtFecha = $this->ejecutarSqlVenta(
        "SELECT created_at FROM sales WHERE id = ? LIMIT 1",
        "i",
        [$idVenta]
    );

    $filaFecha = $stmtFecha->get_result()->fetch_assoc();
    $stmtFecha->close();

    $fechaGuardada = (string)($filaFecha["created_at"] ?? "");
    $fechaEsperada = str_replace("T", " ", $fechaSolicitada);

    if (substr($fechaGuardada, 0, 16) !== $fechaEsperada) {
        throw new RuntimeException(
            "La fecha almacenada no coincide con la fecha seleccionada."
        );
    }
}


            
$stmtItem = $this->conn->prepare(
    "INSERT INTO sale_items
    (sale_id,product_id,quantity,price,total,item_origin,manual_name,manual_code,unit_code)
    VALUES (?,NULL,?,?,?,'MANUAL',?,?,?)"
);

if (!$stmtItem) {
    throw new RuntimeException($this->conn->error);
}

$cantidad = $precio = $importe = "0.00";
$nombreArticulo = $codigoArticulo = $unidadArticulo = "";

if (!$stmtItem->bind_param(
    "issssss",
    $idVenta, $cantidad, $precio, $importe,
    $nombreArticulo, $codigoArticulo, $unidadArticulo
)) {
    throw new RuntimeException($stmtItem->error);
}

foreach ($items as $item) {
    $cantidad = $item["cantidad"];
    $precio = $item["precio"];
    $importe = $item["importe"];
    $nombreArticulo = $item["nombre"];
    $codigoArticulo = $item["codigo"];
    $unidadArticulo = $item["unidad"];

    if (!$stmtItem->execute()) {
        $error = new RuntimeException($stmtItem->error);
        $stmtItem->close();
        throw $error;
    }
}

$stmtItem->close();


            $origen = $forma === "EFECTIVO" ? "CAJA" : "BANCO";
            $referenciaMovimiento = $referencia !== "" ? $referencia : $numero;

            $stmt = $this->ejecutarSqlVenta(
                "INSERT INTO account_moves (`date`,`type`,`origin`,cash_register_id,user_reference_name,reference,amount,user_id,link_type,link_id,ticket_number,status,note,notes,created_at,updated_at) VALUES (?,'VENTA',?,?,?,?,?,?,'VENTA',?,?,'PAGADO',?,?,?,?)",
                "ssisssiisssss",
                [$fechaCobro, $origen, $idCajaVenta, "Mostrador", $referenciaMovimiento, $total, $usuario, $idVenta, $numero, "Cobro de venta rápida", $observaciones, $fecha, $fecha]
            );
            $stmt->close();

            if ($idCajaVenta !== null) {
                $stmt = $this->ejecutarSqlVenta(
                    "INSERT INTO cash_flows (cash_register_id,user_id,amount,`type`,description,reference,created_at) VALUES (?,?,?,'IN',?,?,?)",
                    "iissss",
                    [$idCajaVenta, $usuario, $total, "Cobro venta rápida " . $numero, $numero, $fecha]
                );
                $stmt->close();

                $stmt = $this->ejecutarSqlVenta(
                    "UPDATE cash_registers SET current_balance = current_balance + ?, updated_at = ? WHERE id = ? AND kind = 'CASH' AND is_active = 1",
                    "ssi", [$total, $fecha, $idCajaVenta]
                );

                if ($stmt->affected_rows !== 1) throw new RuntimeException("No se pudo actualizar el saldo de la caja.");
                $stmt->close();
            }

            if (!$this->conn->commit()) throw new RuntimeException("No fue posible confirmar la venta.");
            $transaccion = false;

            $json["status"] = "ok";
            $json["answer"] = "Venta rápida registrada correctamente.";
            $json["results"] = [
                "id" => $idVenta,
                "referencia" => $numero,
                "total" => $total,
                "forma_pago" => $forma,
                "duplicada" => false
            ];
            $json["code"] = 200;
        }
    } catch (InvalidArgumentException $e) {
        if ($transaccion) $this->conn->rollback();

        $json["status"] = "warning";
        $json["answer"] = $e->getMessage();
        $json["code"] = 400;
    } catch (Throwable $e) {
        if ($transaccion) $this->conn->rollback();
        

        $json["status"] = "error";
        $json["answer"] = "No fue posible guardar la venta rápida.";
        $json["code"] = 500;

        if ((int)$e->getCode() === 1062 && $token !== "") {
            try {
                $stmt = $this->ejecutarSqlVenta(
                    "SELECT id,sale_kind,ticket_barcode,total_amount,payment_method,created_at FROM sales WHERE request_token = ? LIMIT 1",
                    "s", [$token]
                );
                $repetida = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                
if (
    $repetida &&
    $repetida["sale_kind"] === "RAPIDA" &&
    $repetida["payment_method"] === $forma &&
    $this->centavosVenta($repetida["total_amount"]) === $totalCentavos &&
    (
        $modoFecha !== "MANUAL" ||
        substr((string)$repetida["created_at"], 0, 16) ===
        str_replace("T", " ", $fechaSolicitada)
    )
) {

                    $json["status"] = "ok";
                    $json["answer"] = "La venta ya estaba registrada; no se duplicó.";
                    $json["results"] = [
                        "id" => (int)$repetida["id"],
                        "referencia" => $repetida["ticket_barcode"],
                        "total" => $repetida["total_amount"],
                        "forma_pago" => $forma,
                        "duplicada" => true
                    ];
                    $json["code"] = 200;
                } else {
                    $json["status"] = "warning";
                    $json["answer"] = "Identificador único utilizado por otra operación.";
                    $json["code"] = 409;
                }
            } catch (Throwable $otroError) {
                
            }
        }
    }

    return $json;
}


private function resumenMovimientosCaja($cajaId, $inicioId, $finId = null) {
    $json = [];
    $extra = $finId === null ? "" : " AND id <= ?";
    $parametros = [$cajaId, $inicioId];
    $tipos = "ii";

    if ($finId !== null) {
        $parametros[] = $finId;
        $tipos .= "i";
    }

    $sql = "SELECT COALESCE(SUM(CASE WHEN `type` = 'IN' THEN amount ELSE 0 END),0) AS ingresos,
            COALESCE(SUM(CASE WHEN `type` = 'OUT' THEN amount ELSE 0 END),0) AS salidas,
            COALESCE(MAX(id),0) AS ultimo
            FROM cash_flows WHERE cash_register_id = ? AND id > ?" . $extra;

    $stmt = $this->ejecutarSqlVenta($sql, $tipos, $parametros);
    $resultado = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $json["ingresos"] = $resultado["ingresos"];
    $json["salidas"] = $resultado["salidas"];
    $json["ultimo"] = (int)$resultado["ultimo"];

    return $json;
}

public function getControlCaja($data = []) {
    $json = [];

    if (!$this->conn) {
        $json["status"] = "error";
        $json["answer"] = "No hay conexión con MySQL.";
        $json["code"] = 500;
    } else {
        try {
            $stmt = $this->ejecutarSqlVenta("SELECT id, name, current_balance FROM cash_registers WHERE kind = 'CASH' AND is_active = 1 ORDER BY name ASC");
            $cajas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $id = isset($data["caja_id"]) ? (int)$data["caja_id"] : 0;
            if ($id <= 0 && !empty($cajas)) {
                $id = (int)$cajas[0]["id"];
            }

            $existe = false;
            foreach ($cajas as $caja) {
                if ((int)$caja["id"] === $id) {
                    $existe = true;
                    break;
                }
            }

            if ($id > 0 && !$existe) {
                throw new InvalidArgumentException("Selecciona una caja activa.");
            }

            $turno = null;
            $movimientos = [];

            if ($id > 0) {
                $stmt = $this->ejecutarSqlVenta("SELECT * FROM cash_shifts WHERE cash_register_id = ? ORDER BY id DESC LIMIT 1", "i", [$id]);
                $turno = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($turno) {
                    $inicioId = (int)$turno["opening_flow_id"];
                    $finId = null;

                    if ($turno["status"] === "CERRADO") {
                        if ($turno["closing_flow_id"] === null) {
                            throw new RuntimeException("El turno cerrado no tiene un movimiento final válido.");
                        }
                        $finId = (int)$turno["closing_flow_id"];
                    }

                    $totales = $this->resumenMovimientosCaja($id, $inicioId, $finId);

                    if ($turno["status"] === "ABIERTO") {
                        $ingresos = $this->centavosVenta($totales["ingresos"]);
                        $salidas = $this->centavosVenta($totales["salidas"]);
                        $fondo = $this->centavosVenta($turno["opening_balance"]);

                        if ($ingresos === null || $salidas === null || $fondo === null) {
                            throw new RuntimeException("No fue posible calcular los importes del turno.");
                        }

                        $turno["ingresos"] = $totales["ingresos"];
                        $turno["salidas"] = $totales["salidas"];
                        $turno["esperado"] = number_format(($fondo + $ingresos - $salidas) / 100, 2, ".", "");
                    } else {
                        $turno["ingresos"] = $turno["income_cash"];
                        $turno["salidas"] = $turno["expense_cash"];
                        $turno["esperado"] = $turno["expected_balance"];
                    }

                    $extra = $finId === null ? "" : " AND id <= ?";
                    $parametros = [$id, $inicioId];
                    $tipos = "ii";

                    if ($finId !== null) {
                        $parametros[] = $finId;
                        $tipos .= "i";
                    }

                    $sql = "SELECT id, created_at, `type`, description, reference, amount
                            FROM cash_flows WHERE cash_register_id = ? AND id > ?" . $extra . "
                            ORDER BY id DESC LIMIT 15";

                    $stmt = $this->ejecutarSqlVenta($sql, $tipos, $parametros);
                    $movimientos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
                }
            }

            $json["status"] = "ok";
            $json["answer"] = "Control de caja consultado correctamente.";
            $json["results"] = [
                "cajas" => $cajas,
                "caja_id" => $id,
                "turno" => $turno,
                "movimientos" => $movimientos
            ];
            $json["code"] = 200;

        } catch (InvalidArgumentException $e) {
            $json["status"] = "warning";
            $json["answer"] = $e->getMessage();
            $json["code"] = 400;
        } catch (Throwable $e) {
            
            $json["status"] = "error";
            $json["answer"] = "No fue posible consultar el control de caja.";
            $json["code"] = 500;
        }
    }

    return $json;
}

public function abrirCaja($data) {
    $json = [];
    $transaccion = false;

    if (!$this->conn) {
        $json["status"] = "error";
        $json["answer"] = "No hay conexión con MySQL.";
        $json["code"] = 500;
    } else {
        try {
            $id = isset($data["caja_id"]) ? (int)$data["caja_id"] : 0;
            $fondoCentavos = $this->centavosVenta(isset($data["fondo"]) ? $data["fondo"] : "");

            if ($id <= 0 || $fondoCentavos === null) {
                throw new InvalidArgumentException("Selecciona una caja y un fondo inicial válido.");
            }

            if ($this->usuarioOperador <= 0) {
                throw new InvalidArgumentException("Configura el usuario operador.");
            }

            $stmt = $this->ejecutarSqlVenta("SELECT id FROM users WHERE id = ? LIMIT 1", "i", [$this->usuarioOperador]);
            $usuario = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$usuario) {
                throw new InvalidArgumentException("El usuario operador no existe.");
            }

            if (!$this->conn->begin_transaction()) {
                throw new RuntimeException("No fue posible iniciar la transacción.");
            }
            $transaccion = true;

            $stmt = $this->ejecutarSqlVenta("SELECT id, current_balance FROM cash_registers WHERE id = ? AND kind = 'CASH' AND is_active = 1 FOR UPDATE", "i", [$id]);
            $caja = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$caja) {
                throw new InvalidArgumentException("La caja seleccionada no existe o está inactiva.");
            }

            $stmt = $this->ejecutarSqlVenta("SELECT id FROM cash_shifts WHERE cash_register_id = ? AND status = 'ABIERTO' LIMIT 1", "i", [$id]);
            $turnoAbierto = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($turnoAbierto) {
                throw new InvalidArgumentException("Esta caja ya tiene un turno abierto.");
            }

            $saldoActual = $this->centavosVenta($caja["current_balance"]);

            if ($saldoActual === null) {
                throw new RuntimeException("El saldo registrado de la caja no es válido.");
            }

            $fecha = date("Y-m-d H:i:s");
            $fondo = number_format($fondoCentavos / 100, 2, ".", "");

            if ($saldoActual !== $fondoCentavos) {
                $diferencia = $fondoCentavos - $saldoActual;
                $tipo = $diferencia > 0 ? "IN" : "OUT";
                $monto = number_format(abs($diferencia) / 100, 2, ".", "");
                $descripcion = "Ajuste del fondo al abrir turno";

                $stmt = $this->ejecutarSqlVenta(
                    "INSERT INTO cash_flows (cash_register_id, user_id, amount, `type`, description, reference, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)",
                    "iisssss",
                    [$id, $this->usuarioOperador, $monto, $tipo, $descripcion, "AJUSTE_APERTURA", $fecha]
                );
                $stmt->close();

                $stmt = $this->ejecutarSqlVenta(
                    "UPDATE cash_registers SET current_balance = ?, updated_at = ? WHERE id = ? AND kind = 'CASH' AND is_active = 1",
                    "ssi",
                    [$fondo, $fecha, $id]
                );

                if ($stmt->affected_rows !== 1) {
                    throw new RuntimeException("No se pudo actualizar el fondo inicial de la caja.");
                }
                $stmt->close();
            }

            $stmt = $this->ejecutarSqlVenta("SELECT COALESCE(MAX(id), 0) AS ultimo FROM cash_flows WHERE cash_register_id = ?", "i", [$id]);
            $ultimoMovimiento = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $base = (int)$ultimoMovimiento["ultimo"];

            $stmt = $this->ejecutarSqlVenta(
                "INSERT INTO cash_shifts (cash_register_id, user_id, opened_at, opening_balance, opening_flow_id, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'ABIERTO', ?, ?)",
                "iississ",
                [$id, $this->usuarioOperador, $fecha, $fondo, $base, $fecha, $fecha]
            );
            $stmt->close();

            $turnoId = (int)$this->conn->insert_id;

            if (!$this->conn->commit()) {
                throw new RuntimeException("No fue posible confirmar la apertura de caja.");
            }
            $transaccion = false;

            $json["status"] = "ok";
            $json["answer"] = "Caja abierta correctamente.";
            $json["results"] = [
                "turno_id" => $turnoId,
                "caja_id" => $id,
                "fondo_inicial" => $fondo,
                "fecha_apertura" => $fecha,
                "estado" => "ABIERTO"
            ];
            $json["code"] = 200;

        } catch (InvalidArgumentException $e) {
            if ($transaccion) {
                $this->conn->rollback();
            }
            $json["status"] = "warning";
            $json["answer"] = $e->getMessage();
            $json["code"] = 400;
        } catch (Throwable $e) {
            if ($transaccion) {
                $this->conn->rollback();
            }
            
            $json["status"] = "error";
            $json["answer"] = "No fue posible abrir la caja.";
            $json["code"] = 500;
        }
    }

    return $json;
}

public function cerrarCaja($data) {
    $json = [];
    $transaccion = false;

    if (!$this->conn) {
        $json["status"] = "error";
        $json["answer"] = "No hay conexión con MySQL.";
        $json["code"] = 500;
    } else {
        try {
            /*$idCaja = isset($data["caja_id"]) ? (int)$data["caja_id"] : 0;

$rawDenominaciones = isset($data["denominaciones"]) && is_string($data["denominaciones"])
    ? $data["denominaciones"] : "";*/


$idCaja = isset($data["caja_id"]) ? (int)$data["caja_id"] : 0;
$contadoCentavos = $this->centavosVenta(isset($data["contado"]) ? $data["contado"] : "");

$rawDenominaciones = isset($data["denominaciones"]) && is_string($data["denominaciones"])
    ? $data["denominaciones"] : "";


if (strlen($rawDenominaciones) > 600) {
    throw new InvalidArgumentException("El desglose de efectivo es demasiado grande.");
}

$denominaciones = json_decode($rawDenominaciones, true);
$permitidas = [100000, 50000, 20000, 10000, 5000, 2000, 1000, 500, 200, 100, 50];
$desgloseSeguro = [];
$totalDesglose = 0;

if (!is_array($denominaciones) || count($denominaciones) !== count($permitidas)) {
    throw new InvalidArgumentException("El desglose de billetes y monedas está incompleto.");
}

foreach ($permitidas as $centavos) {
    $clave = (string)$centavos;

    if (!array_key_exists($clave, $denominaciones)) {
        throw new InvalidArgumentException("Falta una denominación en el arqueo.");
    }

    $cantidad = $denominaciones[$clave];

    if (!is_int($cantidad) || $cantidad < 0 || $cantidad > 100000) {
        throw new InvalidArgumentException("Cantidad inválida para la denominación de $" . number_format($centavos / 100, 2));
    }

    $totalDesglose += $centavos * $cantidad;

    if ($totalDesglose > 9999999999) {
        throw new InvalidArgumentException("El efectivo contado supera el máximo permitido.");
    }

    $desgloseSeguro[$clave] = $cantidad;
}

if ($contadoCentavos === null || $contadoCentavos !== $totalDesglose) {
    throw new InvalidArgumentException("El total contado no coincide con el desglose de efectivo.");
}

$desgloseJson = json_encode($desgloseSeguro);

if ($desgloseJson === false) {
    throw new RuntimeException("No fue posible preparar el desglose del arqueo.");
}

            //$contadoCentavos = $this->centavosVenta(isset($data["contado"]) ? $data["contado"] : "");

            if ($idCaja <= 0 || $contadoCentavos === null) {
                throw new InvalidArgumentException("Selecciona una caja e ingresa el efectivo contado.");
            }

            if (!$this->conn->begin_transaction()) {
                throw new RuntimeException("No fue posible iniciar la transacción.");
            }
            $transaccion = true;

            $stmt = $this->ejecutarSqlVenta(
                "SELECT id, current_balance FROM cash_registers WHERE id = ? AND kind = 'CASH' AND is_active = 1 FOR UPDATE",
                "i", [$idCaja]
            );
            $caja = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$caja) {
                throw new InvalidArgumentException("La caja seleccionada no existe o está inactiva.");
            }

            $stmt = $this->ejecutarSqlVenta(
                "SELECT * FROM cash_shifts WHERE cash_register_id = ? AND status = 'ABIERTO' LIMIT 1 FOR UPDATE",
                "i", [$idCaja]
            );
            $turno = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$turno) {
                throw new InvalidArgumentException("Esta caja no tiene un turno abierto.");
            }

            $totales = $this->resumenMovimientosCaja($idCaja, (int)$turno["opening_flow_id"]);
            $ingresos = $this->centavosVenta($totales["ingresos"]);
            $salidas = $this->centavosVenta($totales["salidas"]);
            $fondo = $this->centavosVenta($turno["opening_balance"]);
            $saldoActual = $this->centavosVenta($caja["current_balance"]);

            if ($ingresos === null || $salidas === null || $fondo === null || $saldoActual === null) {
                throw new RuntimeException("No fue posible verificar los importes de caja.");
            }

            $esperadoCentavos = $fondo + $ingresos - $salidas;

            if ($esperadoCentavos < 0 || $esperadoCentavos !== $saldoActual) {
                throw new RuntimeException("El saldo registrado no coincide con los movimientos de caja.");
            }

            $diferenciaCentavos = $contadoCentavos - $esperadoCentavos;
            $ultimoMovimiento = max((int)$totales["ultimo"], (int)$turno["opening_flow_id"]);
            $fecha = date("Y-m-d H:i:s");

            $ingresosFormato = number_format($ingresos / 100, 2, ".", "");
            $salidasFormato = number_format($salidas / 100, 2, ".", "");
            $esperadoFormato = number_format($esperadoCentavos / 100, 2, ".", "");
            $contadoFormato = number_format($contadoCentavos / 100, 2, ".", "");
            $diferenciaFormato = number_format($diferenciaCentavos / 100, 2, ".", "");

            
$stmt = $this->ejecutarSqlVenta(
    "UPDATE cash_shifts
     SET status = 'CERRADO',
         closed_at = ?,
         closing_flow_id = ?,
         income_cash = ?,
         expense_cash = ?,
         expected_balance = ?,
         counted_balance = ?,
         difference_amount = ?,
         denomination_breakdown = ?,
         updated_at = ?
     WHERE id = ? AND status = 'ABIERTO'",
    "sisssssssi",
    [
        $fecha,
        $ultimoMovimiento,
        $ingresosFormato,
        $salidasFormato,
        $esperadoFormato,
        $contadoFormato,
        $diferenciaFormato,
        $desgloseJson,
        $fecha,
        (int)$turno["id"]
    ]
);


            if ($stmt->affected_rows !== 1) {
                throw new RuntimeException("No fue posible cerrar el turno.");
            }
            $stmt->close();

            if (!$this->conn->commit()) {
                throw new RuntimeException("No fue posible confirmar el cierre de caja.");
            }
            $transaccion = false;

            $estadoArqueo = "CUADRADO";
            if ($diferenciaCentavos > 0) $estadoArqueo = "SOBRANTE";
            if ($diferenciaCentavos < 0) $estadoArqueo = "FALTANTE";

            $json["status"] = "ok";
            $json["answer"] = "Corte de caja registrado correctamente.";
            $json["results"] = [
                "turno_id" => (int)$turno["id"],
                "caja_id" => $idCaja,
                "fondo_inicial" => $turno["opening_balance"],
                "ingresos" => $ingresosFormato,
                "salidas" => $salidasFormato,
                "esperado" => $esperadoFormato,
                "contado" => $contadoFormato,
                "diferencia" => $diferenciaFormato,
                "arqueo" => $estadoArqueo,
                "fecha_cierre" => $fecha,
                "estado" => "CERRADO"
            ];
            $json["code"] = 200;

        } catch (InvalidArgumentException $e) {
            if ($transaccion) $this->conn->rollback();
            $json["status"] = "warning";
            $json["answer"] = $e->getMessage();
            $json["code"] = 400;
        } catch (Throwable $e) {
            if ($transaccion) $this->conn->rollback();
            
            $json["status"] = "error";
            $json["answer"] = "No fue posible realizar el cierre de caja.";
            $json["code"] = 500;
        }
    }

    return $json;
}

public function getEgresos() {
    $json = [];

    if (!$this->conn) {
        $json["status"] = "error";
        $json["answer"] = "No hay conexión con MySQL.";
        $json["code"] = 500;
    } else {
        try {
            $stmt = $this->ejecutarSqlVenta(
                "SELECT r.id, r.name, r.current_balance
                 FROM cash_registers r
                 WHERE r.kind = 'CASH' AND r.is_active = 1
                 AND EXISTS (
                     SELECT 1 FROM cash_shifts s
                     WHERE s.cash_register_id = r.id AND s.status = 'ABIERTO'
                 ) ORDER BY r.name ASC"
            );

            $cajas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $stmt = $this->ejecutarSqlVenta(
                "SELECT id, `date`, origin, cash_register_id, user_reference_name,
                 expense_category, reference, amount, ticket_number, note, notes
                 FROM account_moves
                 WHERE `type` = 'SALIDA' AND status = 'PAGADO'
AND (link_type IS NULL OR link_type <> 'CXP')
                 ORDER BY `date` DESC, id DESC LIMIT 20"
            );

            $egresos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $json["status"] = "ok";
            $json["answer"] = "Egresos consultados correctamente.";
            $json["results"] = [
                "cajas" => $cajas,
                "egresos" => $egresos
            ];
            $json["code"] = 200;

        } catch (Throwable $e) {
            
            $json["status"] = "error";
            $json["answer"] = "No fue posible consultar los egresos.";
            $json["code"] = 500;
        }
    }

    return $json;
}

public function guardarEgreso($data) {
    $json = [];
    $transaccion = false;
    $token = "";

    if (!$this->conn) {
        $json["status"] = "error";
        $json["answer"] = "No hay conexión con MySQL.";
        $json["code"] = 500;
    } else {
        try {
            $token = $this->textoVenta($data, "solicitud_id", 32);
            $beneficiario = $this->textoVenta($data, "beneficiario", 100);
            $categoria = $this->textoVenta($data, "categoria", 30);
            $metodo = $this->textoVenta($data, "metodo", 20);
            $referencia = $this->textoVenta($data, "referencia", 80);
            $notas = $this->textoVenta($data, "notas", 500);
            $montoCentavos = $this->centavosVenta(isset($data["monto"]) ? $data["monto"] : "");
            $idCaja = isset($data["caja_id"]) ? (int)$data["caja_id"] : 0;

            $categorias = [
                "PROVEEDORES" => "Proveedores / Materiales",
                "COMBUSTIBLE" => "Combustible",
                "MANTENIMIENTO" => "Reparaciones / Mantenimiento",
                "OPERATIVOS" => "Gastos operativos varios"
            ];

            if (!preg_match('/^[a-f0-9]{32}$/D', $token)) {
                throw new InvalidArgumentException("Identificador de solicitud inválido.");
            }
            if ($beneficiario === "") {
                throw new InvalidArgumentException("Indica el beneficiario o proveedor.");
            }
            if (!isset($categorias[$categoria])) {
                throw new InvalidArgumentException("Selecciona una categoría de gasto válida.");
            }
            if (!in_array($metodo, ["EFECTIVO", "TRANSFERENCIA"], true)) {
                throw new InvalidArgumentException("Selecciona una forma de salida válida.");
            }
            if ($montoCentavos === null || $montoCentavos <= 0) {
                throw new InvalidArgumentException("El monto del gasto debe ser mayor que cero.");
            }
            if ($metodo === "EFECTIVO" && $idCaja <= 0) {
                throw new InvalidArgumentException("Selecciona la caja de donde saldrá el efectivo.");
            }

            $stmt = $this->ejecutarSqlVenta(
                "SELECT id, ticket_number FROM account_moves WHERE request_token = ? LIMIT 1",
                "s", [$token]
            );
            $anterior = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($anterior) {
                $json["status"] = "ok";
                $json["answer"] = "Este egreso ya estaba registrado; no se duplicó.";
                $json["results"] = [
                    "id" => (int)$anterior["id"],
                    "folio" => $anterior["ticket_number"],
                    "duplicado" => true
                ];
                $json["code"] = 200;
            } else {
                if (!$this->conn->begin_transaction()) {
                    throw new RuntimeException("No fue posible iniciar la transacción.");
                }
                $transaccion = true;

                $fecha = date("Y-m-d H:i:s");
                $monto = number_format($montoCentavos / 100, 2, ".", "");
                $origen = $metodo === "EFECTIVO" ? "CAJA" : "BANCO";
                $idCajaMovimiento = $metodo === "EFECTIVO" ? $idCaja : null;
                $fechaOperacion = $this->fechaOperacion($data, $idCajaMovimiento);

                if ($idCajaMovimiento !== null) {
                    $stmt = $this->ejecutarSqlVenta(
                        "SELECT id, current_balance FROM cash_registers
                         WHERE id = ? AND kind = 'CASH' AND is_active = 1 FOR UPDATE",
                        "i", [$idCaja]
                    );
                    $caja = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    if (!$caja) {
                        throw new InvalidArgumentException("La caja no existe o está inactiva.");
                    }

                    $stmt = $this->ejecutarSqlVenta(
                        "SELECT id FROM cash_shifts
                         WHERE cash_register_id = ? AND status = 'ABIERTO' LIMIT 1",
                        "i", [$idCaja]
                    );
                    $turno = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    if (!$turno) {
                        throw new InvalidArgumentException("Debes abrir un turno antes de registrar un gasto en efectivo.");
                    }

                    $saldoCaja = $this->centavosVenta($caja["current_balance"]);

                    if ($saldoCaja === null) {
                        throw new RuntimeException("El saldo de caja es inválido.");
                    }
                    if ($saldoCaja < $montoCentavos) {
                        throw new InvalidArgumentException("La caja no tiene efectivo suficiente para registrar este gasto.");
                    }
                }

                $notaMovimiento = $categorias[$categoria];

                $stmt = $this->ejecutarSqlVenta(
                    "INSERT INTO account_moves
                    (`date`, `type`, `origin`, cash_register_id, user_reference_name,
                    reference, amount, user_id, link_type, link_id, ticket_number,
                    status, note, notes, created_at, updated_at, expense_category, request_token)
                    VALUES (?, 'SALIDA', ?, ?, ?, ?, ?, ?, NULL, NULL, NULL,
                    'PAGADO', ?, ?, ?, ?, ?, ?)",
                    "ssisssissssss",
                    [$fechaOperacion, $origen, $idCajaMovimiento, $beneficiario,
                    $referencia, $monto, $this->usuarioOperador,
                    $notaMovimiento, $notas, $fecha, $fecha, $categoria, $token]
                );
                $stmt->close();

                $idEgreso = (int)$this->conn->insert_id;
                $folio = "EG-" . str_pad((string)$idEgreso, 8, "0", STR_PAD_LEFT);

                $stmt = $this->ejecutarSqlVenta(
                    "UPDATE account_moves SET ticket_number = ? WHERE id = ?",
                    "si", [$folio, $idEgreso]
                );
                $stmt->close();

                if ($idCajaMovimiento !== null) {
                    $descripcion = "Egreso " . $folio . " - " . $beneficiario;

                    $stmt = $this->ejecutarSqlVenta(
                        "INSERT INTO cash_flows
                        (cash_register_id, user_id, amount, `type`, description, reference, created_at)
                        VALUES (?, ?, ?, 'OUT', ?, ?, ?)",
                        "iissss",
                        [$idCaja, $this->usuarioOperador, $monto, $descripcion, $folio, $fechaOperacion]
                    );
                    $stmt->close();

                    $stmt = $this->ejecutarSqlVenta(
                        "UPDATE cash_registers
                         SET current_balance = current_balance - ?, updated_at = ?
                         WHERE id = ? AND kind = 'CASH' AND is_active = 1
                         AND current_balance >= ?",
                        "ssis", [$monto, $fecha, $idCaja, $monto]
                    );

                    if ($stmt->affected_rows !== 1) {
                        throw new RuntimeException("No fue posible descontar el saldo de la caja.");
                    }
                    $stmt->close();
                }

                if (!$this->conn->commit()) {
                    throw new RuntimeException("No fue posible confirmar el egreso.");
                }
                $transaccion = false;

                $json["status"] = "ok";
                $json["answer"] = "Egreso registrado correctamente.";
                $json["results"] = [
                    "id" => $idEgreso,
                    "folio" => $folio,
                    "beneficiario" => $beneficiario,
                    "categoria" => $categoria,
                    "metodo" => $metodo,
                    "monto" => $monto,
                    "caja_id" => $idCajaMovimiento,
                    "fecha" => $fecha,
                    "duplicado" => false
                ];
                $json["code"] = 200;
            }

        } catch (InvalidArgumentException $e) {
            if ($transaccion) $this->conn->rollback();
            $json["status"] = "warning";
            $json["answer"] = $e->getMessage();
            $json["code"] = 400;
        } catch (Throwable $e) {
            if ($transaccion) $this->conn->rollback();
            
            $json["status"] = "error";
            $json["answer"] = "No fue posible registrar el egreso.";
            $json["code"] = 500;

            if ((int)$e->getCode() === 1062 && $token !== "") {
                try {
                    $stmt = $this->ejecutarSqlVenta(
                        "SELECT id, ticket_number FROM account_moves WHERE request_token = ? LIMIT 1",
                        "s", [$token]
                    );
                    $repetido = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    if ($repetido) {
                        $json["status"] = "ok";
                        $json["answer"] = "Este egreso ya estaba registrado; no se duplicó.";
                        $json["results"] = [
                            "id" => (int)$repetido["id"],
                            "folio" => $repetido["ticket_number"],
                            "duplicado" => true
                        ];
                        $json["code"] = 200;
                    }
                } catch (Throwable $otroError) {
                    
                }
            }
        }
    }

    return $json;
}


private function metaCuenta($tipo) {
    if ($tipo === "COBRAR") return ["tabla"=>"receivables","nombre"=>"client_name","relacion"=>"sale_id"];
    if ($tipo === "PAGAR") return ["tabla"=>"payables","nombre"=>"supplier_name","relacion"=>"purchase_id"];
    throw new InvalidArgumentException("Selecciona un tipo de cuenta válido.");
}


public function getCuentas($data = []) {
    $json = [];
    if (!$this->conn) {
        $json["status"]="error"; $json["answer"]="No hay conexión con MySQL."; $json["code"]=500;
    } else {
        try {
            $tipo = isset($data["tipo"]) ? trim((string)$data["tipo"]) : "";
            $meta = $this->metaCuenta($tipo);

            //$meta = $this->metaCuenta(isset($data["tipo"]) ? $data["tipo"] : "");
            $tabla = $meta["tabla"]; $nombre = $meta["nombre"];
            $buscar = $this->textoVenta($data,"buscar",100);
            $estado = isset($data["estado"]) ? trim((string)$data["estado"]) : "TODAS";
            if (!in_array($estado,["TODAS","PENDIENTE","VENCIDO","PAGADO","CANCELADO","HOY"],true)) throw new InvalidArgumentException("Filtro de estado inválido.");

           
            $where = " WHERE 1=1"; $tipos = ""; $params = [];

            if ($tipo === "COBRAR" && isset($data["cliente_id"])) {
                $clienteIdFiltro = filter_var(
                    $data["cliente_id"],
                    FILTER_VALIDATE_INT,
                    ["options" => ["min_range" => 1]]
                );

                if ($clienteIdFiltro === false) {
                    throw new InvalidArgumentException(
                        "El ID del cliente es inválido."
                    );
                }

                $where .= " AND client_id = ?";
                $tipos .= "i";
                $params[] = $clienteIdFiltro;
            }

            $resumenWhere = " WHERE status <> 'CANCELADO'";
            $resumenTipos = "";
            $resumenParams = [];

            if ($tipo === "COBRAR" && isset($clienteIdFiltro)) {
                $resumenWhere .= " AND client_id = ?";
                $resumenTipos = "i";
                $resumenParams[] = $clienteIdFiltro;
            }

            $sqlResumen = "SELECT
                COUNT(*) AS total,
                COALESCE(SUM(
                    CASE WHEN status = 'PENDIENTE'
                    THEN pending_amount ELSE 0 END
                ), 0) AS pendiente,
                COALESCE(SUM(
                    CASE WHEN status = 'PENDIENTE'
                        AND due_date < CURDATE()
                    THEN pending_amount ELSE 0 END
                ), 0) AS vencido,
                COALESCE(SUM(
                    CASE WHEN status = 'PENDIENTE'
                        AND due_date IS NULL
                    THEN 1 ELSE 0 END
                ), 0) AS sin_vencimiento
                FROM `$tabla`" . $resumenWhere;

            $stmt = $this->ejecutarSqlVenta(
                $sqlResumen,
                $resumenTipos,
                $resumenParams
            );

            $resumen = $stmt->get_result()->fetch_assoc();
            $stmt->close();


            if ($buscar !== "") {
                $where .= " AND (`$nombre` LIKE ? OR document_reference LIKE ? OR CAST(id AS CHAR) LIKE ?)";
                $patron = "%" . $buscar . "%";
                $tipos .= "sss";
                array_push($params, $patron, $patron, $patron);
            }
            if ($estado === "PENDIENTE") $where .= " AND status='PENDIENTE' AND pending_amount>0";
            if ($estado === "VENCIDO") $where .= " AND status='PENDIENTE' AND pending_amount>0 AND due_date<CURDATE()";
            if ($estado === "PAGADO") $where .= " AND status='PAGADO'";
            if ($estado === "HOY") $where .= " AND status='PENDIENTE' AND pending_amount>0 AND due_date=CURDATE()";
            if ($estado === "CANCELADO") $where .= " AND status='CANCELADO'";

            
$camposFechas = $tipo === "COBRAR"
    ? ", (SELECT s.created_at
           FROM sales s
           WHERE s.id = `$tabla`.sale_id
           LIMIT 1) AS fecha_venta,
         (SELECT s.registered_at
           FROM sales s
           WHERE s.id = `$tabla`.sale_id
           LIMIT 1) AS fecha_registro_utc"
    : ", NULL AS fecha_venta,
         NULL AS fecha_registro_utc";

            $campoCliente = $tipo === "COBRAR"
                ? "client_id AS cliente_id,"
                : "NULL AS cliente_id,";


            $sql = "SELECT id, `$nombre` AS nombre,
                           $campoCliente
                           total_amount, paid_amount, pending_amount,
                           status, due_date, document_reference,
                           description, created_at
                           $camposFechas
                    FROM `$tabla`" . $where . "
                    ORDER BY
                        CASE WHEN status='PENDIENTE' THEN 0 ELSE 1 END,
                        due_date IS NULL,
                        due_date ASC,
                        id DESC
                    LIMIT 200";



            $stmt = $this->ejecutarSqlVenta($sql,$tipos,$params);
            $cuentas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();


foreach ($cuentas as &$cuenta) {
    $cuenta["fecha_registro"] = null;

    if (!empty($cuenta["fecha_registro_utc"])) {
        $fechaUtc = new DateTimeImmutable(
            $cuenta["fecha_registro_utc"],
            new DateTimeZone("UTC")
        );

        $cuenta["fecha_registro"] = $fechaUtc
            ->setTimezone(new DateTimeZone("America/Mexico_City"))
            ->format("Y-m-d H:i:s");
    }

    unset($cuenta["fecha_registro_utc"]);
}

unset($cuenta);


            $json["status"]="ok"; $json["answer"]="Cuentas consultadas correctamente.";
            $json["results"]=["resumen"=>$resumen,"cuentas"=>$cuentas];
            $json["code"]=200;
        } catch (InvalidArgumentException $e) {
            $json["status"]="warning"; $json["answer"]=$e->getMessage(); $json["code"]=400;
        } catch (Throwable $e) {
           
            $json["status"]="error"; $json["answer"]="No fue posible consultar las cuentas."; $json["code"]=500;
        }
    }
    return $json;
}

public function getDatos(){
    $json = [];

    return $json;
}

public function crearCuenta($data) {
    $json = []; $token = "";
    if (!$this->conn) {
        $json["status"]="error"; $json["answer"]="No hay conexión con MySQL."; $json["code"]=500;
    } else {
        try {
            $tipo = isset($data["tipo"]) ? $data["tipo"] : "";
            $meta = $this->metaCuenta($tipo);
            $tabla = $meta["tabla"]; $nombreCol = $meta["nombre"];
            $token = $this->textoVenta($data,"solicitud_id",32);
            $nombre = $this->textoVenta($data,"nombre",100);
            $referencia = $this->textoVenta($data,"referencia",80);
            $concepto = $this->textoVenta($data,"concepto",255);
            $fechaVence = $this->textoVenta($data,"vencimiento",10);
            $montoCentavos = $this->centavosVenta(isset($data["monto"]) ? $data["monto"] : "");

            $clienteId = null;

            if ($tipo === "COBRAR") {
                $clienteId = filter_var(
                    $data["cliente_id"] ?? null,
                    FILTER_VALIDATE_INT,
                    ["options" => ["min_range" => 1]]
                );

                if ($clienteId === false) {
                    throw new InvalidArgumentException(
                        "Debes seleccionar un cliente registrado."
                    );
                }

                $stmt = $this->ejecutarSqlVenta(
                    "SELECT id, nombre, apellido, tipo
                     FROM clientes
                     WHERE corp_id = ?
                       AND id = ?
                       AND eliminado = 0
                     LIMIT 1",
                    "si",
                    [$this->corpClientes(), $clienteId]
                );

                $registroCliente = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$registroCliente) {
                    throw new InvalidArgumentException(
                        "El cliente seleccionado no existe."
                    );
                }

                if ((int)$registroCliente["tipo"] !== 1) {
                    throw new InvalidArgumentException(
                        "No puedes registrar créditos a prospectos."
                    );
                }

                $nombre = trim(
                    (string)$registroCliente["nombre"] . " " .
                    (string)($registroCliente["apellido"] ?? "")
                );

                if ($nombre === "" || strlen($nombre) > 100) {
                    throw new InvalidArgumentException(
                        "El nombre del cliente no es válido para el crédito."
                    );
                }
            }

            if (!preg_match('/^[a-f0-9]{32}$/D',$token)) throw new InvalidArgumentException("Solicitud inválida.");
            if ($nombre==="") throw new InvalidArgumentException("Indica el cliente o proveedor.");
            if ($concepto==="") throw new InvalidArgumentException("Describe el origen de la deuda.");
            if ($montoCentavos===null || $montoCentavos<=0) throw new InvalidArgumentException("El importe debe ser mayor que cero.");

            if ($fechaVence!=="") {
                $fecha = DateTime::createFromFormat("!Y-m-d",$fechaVence);
                if (!$fecha || $fecha->format("Y-m-d")!==$fechaVence) throw new InvalidArgumentException("Fecha de vencimiento inválida.");
            } else $fechaVence = null;

            $stmt = $this->ejecutarSqlVenta("SELECT id FROM `$tabla` WHERE request_token=? LIMIT 1","s",[$token]);
            $anterior = $stmt->get_result()->fetch_assoc(); $stmt->close();

            if ($anterior) {
                $json["status"]="ok"; $json["answer"]="Esta cuenta ya estaba registrada; no se duplicó.";
                $json["results"]=["id"=>(int)$anterior["id"],"duplicado"=>true]; $json["code"]=200;
            } else {
                $ahora = date("Y-m-d H:i:s");
                $fechaOperacion = $this->fechaOperacion($data);
                $monto = number_format($montoCentavos/100,2,".","");
                $relacion = $tipo==="COBRAR" ? "sale_id,client_id" : "purchase_id,supplier_id";
                //$sql = "INSERT INTO `$tabla` ($relacion,`$nombreCol`,user_id,total_amount,paid_amount,pending_amount,status,due_date,created_at,updated_at,description,document_reference,request_token) VALUES (NULL,NULL,?,?,?,0,?,'PENDIENTE',?,?,?,?,?,?)";
                //$stmt = $this->ejecutarSqlVenta($sql,"sissssssss",[$nombre,$this->usuarioOperador,$monto,$monto,$fechaVence,$ahora,$ahora,$concepto,$referencia,$token]);
                

                $idRelacionado = $tipo === "COBRAR"
                    ? $clienteId
                    : null;

                
                $sql = "INSERT INTO `$tabla`
                    ($relacion, `$nombreCol`, user_id,
                     total_amount, paid_amount, pending_amount,
                     status, due_date, created_at, updated_at,
                     description, document_reference, request_token)
                    VALUES (
                        NULL, ?, ?, ?, ?, 0, ?,
                        'PENDIENTE', ?, ?, ?, ?, ?, ?
                    )";

                $stmt = $this->ejecutarSqlVenta(
                    $sql,
                    "isissssssss",
                    [
                        $idRelacionado,
                        $nombre,
                        $this->usuarioOperador,
                        $monto,
                        $monto,
                        $fechaVence,
                        $fechaOperacion,
                        $ahora,
                        $concepto,
                        $referencia,
                        $token
                    ]
                );



                
                $stmt->close();
                $id = (int)$this->conn->insert_id;
                $json["status"]="ok"; $json["answer"]="Cuenta registrada correctamente.";
                $json["results"]=["id"=>$id,"duplicado"=>false]; $json["code"]=200;
            }
        } catch (InvalidArgumentException $e) {
            $json["status"]="warning"; $json["answer"]=$e->getMessage(); $json["code"]=400;
        } catch (Throwable $e) {
            
            $json["status"]="error"; $json["answer"]="No fue posible registrar la cuenta. Si la solicitud se procesó, puedes reintentar con el mismo identificador."; $json["code"]=500;
        }
    }
    return $json;
}


public function getDetalleCuenta($data) {
    $json = [];
    if (!$this->conn) {
        $json["status"]="error"; $json["answer"]="No hay conexión con MySQL."; $json["code"]=500;
    } else {
        try {
            $tipo = isset($data["tipo"]) ? $data["tipo"] : "";
            $meta = $this->metaCuenta($tipo);
            $id = isset($data["id"]) ? (int)$data["id"] : 0;
            if ($id<=0) throw new InvalidArgumentException("Cuenta inválida.");
            $tabla = $meta["tabla"]; $nombre = $meta["nombre"];

            $stmt = $this->ejecutarSqlVenta("SELECT *, `$nombre` AS nombre FROM `$tabla` WHERE id=? LIMIT 1","i",[$id]);
            $cuenta = $stmt->get_result()->fetch_assoc(); $stmt->close();
            if (!$cuenta) throw new InvalidArgumentException("No se encontró la cuenta.");

            $stmt = $this->ejecutarSqlVenta("SELECT id,amount,method,cash_register_id,reference,notes,created_at FROM account_settlements WHERE account_type=? AND account_id=? ORDER BY id DESC","si",[$tipo,$id]);
            $historial = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();


if ($tipo==="COBRAR" && (int)$cuenta["sale_id"]>0) {
    $stmt = $this->ejecutarSqlVenta("SELECT initial_paid,cash_register_id,bank_reference,
       note_folio,created_at,registered_at
FROM sales
WHERE id=? LIMIT 1","i",[(int)$cuenta["sale_id"]]);
    $venta = $stmt->get_result()->fetch_assoc(); $stmt->close();


            if ($venta) {
                $cuenta["fecha_venta"] = $venta["created_at"];
                $cuenta["fecha_registro"] = null;

                
                $fechaTexto = trim(
                    (string)($venta["registered_at"] ?? "")
                );

                if (
                    $fechaTexto !== "" &&
                    $fechaTexto !== "0000-00-00 00:00:00"
                ) {
                    $zonaUtc = new DateTimeZone("UTC");

                    $fechaUtc = DateTimeImmutable::createFromFormat(
                        "!Y-m-d H:i:s",
                        $fechaTexto,
                        $zonaUtc
                    );

                    if (
                        $fechaUtc !== false &&
                        $fechaUtc->format("Y-m-d H:i:s") === $fechaTexto
                    ) {
                        $cuenta["fecha_registro"] = $fechaUtc
                            ->setTimezone(
                                new DateTimeZone("America/Mexico_City")
                            )
                            ->format("Y-m-d H:i:s");
                    }
                }

            }


    if ($venta && (float)$venta["initial_paid"]>0) {
        $referencia = trim((string)$venta["bank_reference"]);
        if ($referencia==="") $referencia = (string)$venta["note_folio"];
        $historial[] = [
            "id"=>-(int)$cuenta["sale_id"],
            "amount"=>$venta["initial_paid"],
            "method"=>$venta["cash_register_id"] ? "EFECTIVO" : "TRANSFERENCIA",
            "cash_register_id"=>$venta["cash_register_id"],
            "reference"=>"Anticipo inicial · ".$referencia,
            "notes"=>"Registrado al crear la venta",
            "created_at"=>$venta["created_at"]
        ];
        usort($historial,function($a,$b){return strcmp($b["created_at"],$a["created_at"]);});
    }
}


            $json["status"]="ok"; $json["answer"]="Detalle consultado correctamente.";
            $json["results"]=["cuenta"=>$cuenta,"historial"=>$historial]; $json["code"]=200;
        } catch (InvalidArgumentException $e) {
            $json["status"]="warning"; $json["answer"]=$e->getMessage(); $json["code"]=400;
        } catch (Throwable $e) {
           
            $json["status"]="error"; $json["answer"]="No fue posible obtener el historial."; $json["code"]=500;
        }
    }
    return $json;
}


public function registrarAbonoCuenta($data) {
    $json = []; $transaccion = false; $token = "";
    if (!$this->conn) {
        $json["status"]="error"; $json["answer"]="No hay conexión con MySQL."; $json["code"]=500;
    } else {
        try {
            $tipo = isset($data["tipo"]) ? $data["tipo"] : "";
            $meta = $this->metaCuenta($tipo);
            $tabla = $meta["tabla"]; $nombreCol = $meta["nombre"];
            $id = isset($data["id"]) ? (int)$data["id"] : 0;
            $cajaId = isset($data["caja_id"]) ? (int)$data["caja_id"] : 0;
            $token = $this->textoVenta($data,"solicitud_id",32);
            $metodo = $this->textoVenta($data,"metodo",20);
            $referencia = $this->textoVenta($data,"referencia",100);
            $notas = $this->textoVenta($data,"notas",500);
            $centavos = $this->centavosVenta(isset($data["monto"]) ? $data["monto"] : "");

            if ($id<=0 || !preg_match('/^[a-f0-9]{32}$/D',$token)) throw new InvalidArgumentException("Identificador de operación inválido.");
            if (!in_array($metodo,["EFECTIVO","TRANSFERENCIA"],true)) throw new InvalidArgumentException("Método de pago inválido.");
            if ($centavos===null || $centavos<=0) throw new InvalidArgumentException("Captura un importe válido.");
            if ($metodo==="EFECTIVO" && $cajaId<=0) throw new InvalidArgumentException("Selecciona una caja abierta.");
            if ($metodo==="TRANSFERENCIA" && $referencia==="") throw new InvalidArgumentException("Captura la referencia de la transferencia.");

            $stmt = $this->ejecutarSqlVenta("SELECT id,account_type,account_id FROM account_settlements WHERE request_token=? LIMIT 1","s",[$token]);
            $anterior = $stmt->get_result()->fetch_assoc(); $stmt->close();

            if ($anterior) {
                if ($anterior["account_type"]!==$tipo || (int)$anterior["account_id"]!==$id) throw new InvalidArgumentException("La solicitud ya pertenece a otra cuenta.");
                $json["status"]="ok"; $json["answer"]="Este pago ya estaba registrado; no se duplicó.";
                $json["results"]=["id"=>(int)$anterior["id"],"duplicado"=>true]; $json["code"]=200;
            } else {
                if (!$this->conn->begin_transaction()) throw new RuntimeException("No fue posible iniciar la transacción.");
                $transaccion = true;

                $relacion = $meta["relacion"];
                $stmt = $this->ejecutarSqlVenta("SELECT id,`$nombreCol` AS nombre,total_amount,paid_amount,pending_amount,status,created_at,`$relacion` AS documento_id FROM `$tabla` WHERE id=? FOR UPDATE","i",[$id]);
                $cuenta = $stmt->get_result()->fetch_assoc(); $stmt->close();
                if (!$cuenta) throw new InvalidArgumentException("La cuenta no existe.");
                if ($cuenta["status"]!=="PENDIENTE") throw new InvalidArgumentException("Esta cuenta ya está liquidada o cancelada.");

                $saldoAnterior = $this->centavosVenta($cuenta["pending_amount"]);
                $pagadoAnterior = $this->centavosVenta($cuenta["paid_amount"]);
                $totalCuenta = $this->centavosVenta($cuenta["total_amount"]);
                if ($saldoAnterior===null || $pagadoAnterior===null || $totalCuenta===null || $saldoAnterior+$pagadoAnterior!==$totalCuenta) throw new RuntimeException("Los importes de la cuenta son inconsistentes.");
                if ($centavos>$saldoAnterior) throw new InvalidArgumentException("El abono no puede superar el saldo pendiente.");

                if ($tipo==="COBRAR" && (int)$cuenta["documento_id"]>0) {
                    $stmt = $this->ejecutarSqlVenta("SELECT pending_amount FROM sales WHERE id=? FOR UPDATE","i",[(int)$cuenta["documento_id"]]);
                    $venta = $stmt->get_result()->fetch_assoc(); $stmt->close();
                    if (!$venta || $this->centavosVenta($venta["pending_amount"])!==$saldoAnterior) throw new RuntimeException("El saldo de la venta no coincide con su cuenta por cobrar.");
                }

                $idCajaMovimiento = $metodo==="EFECTIVO" ? $cajaId : null;
                $saldoCaja = null;
                if ($idCajaMovimiento!==null) {
                    $stmt = $this->ejecutarSqlVenta("SELECT current_balance FROM cash_registers WHERE id=? AND kind='CASH' AND is_active=1 FOR UPDATE","i",[$cajaId]);
                    $caja = $stmt->get_result()->fetch_assoc(); $stmt->close();
                    if (!$caja) throw new InvalidArgumentException("La caja no está activa.");

                    $stmt = $this->ejecutarSqlVenta("SELECT id FROM cash_shifts WHERE cash_register_id=? AND status='ABIERTO' LIMIT 1 FOR UPDATE","i",[$cajaId]);
                    $turno = $stmt->get_result()->fetch_assoc(); $stmt->close();
                    if (!$turno) throw new InvalidArgumentException("Debes tener un turno abierto.");

                    $saldoCaja = $this->centavosVenta($caja["current_balance"]);
                    if ($saldoCaja===null) throw new RuntimeException("El saldo de caja es inválido.");
                    if ($tipo==="PAGAR" && $saldoCaja<$centavos) throw new InvalidArgumentException("No hay efectivo suficiente en la caja.");
                }

                $ahora = date("Y-m-d H:i:s");
                $fechaOperacion = $this->fechaOperacion($data, $idCajaMovimiento);
                
                if ($fechaOperacion < $cuenta["created_at"]) {
                    throw new InvalidArgumentException("El pago no puede tener una fecha anterior al registro de la deuda.");
                }

                $monto = number_format($centavos/100,2,".","");
                $saldoNuevo = number_format(($saldoAnterior-$centavos)/100,2,".","");
                $pagadoNuevo = number_format(($pagadoAnterior+$centavos)/100,2,".","");
                $estadoNuevo = $saldoAnterior===$centavos ? "PAGADO" : "PENDIENTE";
                $origen = $metodo==="EFECTIVO" ? "CAJA" : "BANCO";
                $movTipo = $tipo==="COBRAR" ? "ENTRADA" : "SALIDA";
                $notaMovimiento = $tipo==="COBRAR" ? "Abono a cuenta por cobrar" : "Pago de cuenta por pagar";
                $categoria = $tipo==="PAGAR" ? "PROVEEDORES" : null;

                $stmt = $this->ejecutarSqlVenta(
                    "INSERT INTO account_settlements (account_type,account_id,amount,method,cash_register_id,reference,notes,user_id,request_token,created_at) VALUES (?,?,?,?,?,?,?,?,?,?)",
                    "sissississ",[$tipo,$id,$monto,$metodo,$idCajaMovimiento,$referencia,$notas,$this->usuarioOperador,$token,$fechaOperacion]
                );
                $stmt->close();
                $idAbono = (int)$this->conn->insert_id;
                $folio = ($tipo==="COBRAR" ? "CXC-" : "CXP-").str_pad((string)$idAbono,8,"0",STR_PAD_LEFT);
                $refMovimiento = $referencia!=="" ? $referencia : $folio;

                $stmt = $this->ejecutarSqlVenta(
                    "INSERT INTO account_moves (`date`,`type`,`origin`,cash_register_id,user_reference_name,reference,amount,user_id,link_type,link_id,ticket_number,status,note,notes,created_at,updated_at,expense_category,request_token) VALUES (?,?,?,?,?,?,?,?,?,?,?,'PAGADO',?,?,?,?,?,?)",
                    "sssisssisisssssss",
                    [$fechaOperacion,$movTipo,$origen,$idCajaMovimiento,$cuenta["nombre"],$refMovimiento,$monto,$this->usuarioOperador,$tipo==="COBRAR" ? "CXC" : "CXP",$id,$folio,$notaMovimiento,$notas,$ahora,$ahora,$categoria,$token]
                );
                $stmt->close();
                $idMovimiento = (int)$this->conn->insert_id;

                $stmt = $this->ejecutarSqlVenta("UPDATE account_settlements SET account_move_id=? WHERE id=?","ii",[$idMovimiento,$idAbono]); $stmt->close();
                //$stmt = $this->ejecutarSqlVenta("UPDATE `$tabla` SET paid_amount=?,pending_amount=?,status=?,updated_at=? WHERE id=?","ssssi",[$pagadoNuevo,$saldoNuevo,$estadoNuevo,$ahora,$id]);
                

                $stmt = $this->ejecutarSqlVenta(
                    "UPDATE `$tabla`
                     SET paid_amount = ?,
                         pending_amount = ?,
                         status = ?,
                         updated_at = ?
                     WHERE id = ?
                       AND status = 'PENDIENTE'",
                    "ssssi",
                    [
                        $pagadoNuevo,
                        $saldoNuevo,
                        $estadoNuevo,
                        $ahora,
                        $id
                    ]
                );


                
                if ($stmt->affected_rows!==1) throw new RuntimeException("No fue posible actualizar la cuenta.");
                $stmt->close();

                if ($tipo==="COBRAR" && (int)$cuenta["documento_id"]>0) {
                    $stmt = $this->ejecutarSqlVenta("UPDATE sales SET pending_amount=?,updated_at=? WHERE id=?","ssi",[$saldoNuevo,$ahora,(int)$cuenta["documento_id"]]);
                    if ($stmt->affected_rows!==1) throw new RuntimeException("No se actualizó el saldo de la venta.");
                    $stmt->close();
                }

                if ($idCajaMovimiento!==null) {
                    $flujo = $tipo==="COBRAR" ? "IN" : "OUT";
                    $descripcion = $notaMovimiento." ".$folio;
                    $stmt = $this->ejecutarSqlVenta("INSERT INTO cash_flows (cash_register_id,user_id,amount,`type`,description,reference,created_at) VALUES (?,?,?,?,?,?,?)","iisssss",[$cajaId,$this->usuarioOperador,$monto,$flujo,$descripcion,$folio,$fechaOperacion]);
                    $stmt->close();

                    if ($tipo==="COBRAR") {
                        $sqlCaja = "UPDATE cash_registers SET current_balance=current_balance+?,updated_at=? WHERE id=? AND kind='CASH' AND is_active=1";
                        $paramsCaja = [$monto,$ahora,$cajaId]; $tiposCaja = "ssi";
                    } else {
                        $sqlCaja = "UPDATE cash_registers SET current_balance=current_balance-?,updated_at=? WHERE id=? AND kind='CASH' AND is_active=1 AND current_balance>=?";
                        $paramsCaja = [$monto,$ahora,$cajaId,$monto]; $tiposCaja = "ssis";
                    }
                    $stmt = $this->ejecutarSqlVenta($sqlCaja,$tiposCaja,$paramsCaja);
                    if ($stmt->affected_rows!==1) throw new RuntimeException("No fue posible actualizar la caja.");
                    $stmt->close();
                }

                if (!$this->conn->commit()) throw new RuntimeException("No fue posible confirmar la transacción.");
                $transaccion = false;
                $json["status"]="ok"; $json["answer"]=$tipo==="COBRAR" ? "Abono registrado correctamente." : "Pago registrado correctamente.";
                $json["results"]=["id"=>$idAbono,"folio"=>$folio,"saldo"=>$saldoNuevo,"estado"=>$estadoNuevo,"duplicado"=>false]; $json["code"]=200;
            }
        } catch (InvalidArgumentException $e) {
            if ($transaccion) $this->conn->rollback();
            $json["status"]="warning"; $json["answer"]=$e->getMessage(); $json["code"]=400;
        } catch (Throwable $e) {
            if ($transaccion) $this->conn->rollback();
            
            $json["status"]="error"; $json["answer"]="No fue posible confirmar el pago. Reintenta con el mismo identificador para evitar duplicados."; $json["code"]=500;
            if ((int)$e->getCode()===1062 && $token!=="") {
                try {
                    $stmt = $this->ejecutarSqlVenta("SELECT id,account_type,account_id FROM account_settlements WHERE request_token=? LIMIT 1","s",[$token]);
                    $repetido = $stmt->get_result()->fetch_assoc(); $stmt->close();
                    if ($repetido && $repetido["account_type"]===$tipo && (int)$repetido["account_id"]===$id) {
                        $json["status"]="ok"; $json["answer"]="El pago ya estaba registrado; no se duplicó.";
                        $json["results"]=["id"=>(int)$repetido["id"],"duplicado"=>true]; $json["code"]=200;
                    }
                } catch (Throwable $otroError) {
                    
                }
            }
        }
    }
    return $json;
}



    private function carteraAbonoGlobal($clienteId, $bloquear = false) {
        $corpId = $this->corpClientes();

        $stmt = $this->ejecutarSqlVenta(
            "SELECT id, nombre, apellido
             FROM clientes
             WHERE corp_id = ? AND id = ?
               AND tipo = '1' AND eliminado = 0
             LIMIT 1",
            "si",
            [$corpId, $clienteId]
        );

        $cliente = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$cliente) {
            throw new InvalidArgumentException(
                "El cliente no existe o no está activo."
            );
        }

        $nombre = trim(
            $cliente["nombre"] . " " . ($cliente["apellido"] ?? "")
        );

        $sql = "SELECT
                    r.id,
                    r.sale_id,
                    r.client_name,
                    r.created_at,
                    r.due_date,
                    r.total_amount,
                    r.paid_amount,
                    r.pending_amount,
                    COALESCE(
                        s.created_at, r.created_at
                    ) AS fecha_origen
                FROM receivables r
                LEFT JOIN sales s ON s.id = r.sale_id
                WHERE r.client_id = ?
                  AND r.status = 'PENDIENTE'
                  AND r.pending_amount > 0
                  
AND (
    r.sale_id IS NULL
    OR (
        s.corp_id = ?
        AND s.cliente_id = r.client_id
    )
)

                
                ORDER BY
                    CASE
                        WHEN r.due_date IS NOT NULL
                             AND r.due_date < CURDATE()
                        THEN 0
                        WHEN r.due_date IS NOT NULL
                        THEN 1
                        ELSE 2
                    END,

                    r.due_date ASC,
                    COALESCE(
                        s.created_at, r.created_at
                    ) ASC,
                    r.id ASC";

        if ($bloquear) {
            $sql .= " FOR UPDATE";
        }

        $stmt = $this->ejecutarSqlVenta(
            $sql,
            "is",
            [$clienteId, $corpId]
        );

        $cuentas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $totalCentavos = 0;
        $datosFirma = [];

        foreach ($cuentas as $cuenta) {
            $saldo = $this->centavosVenta(
                $cuenta["pending_amount"]
            );

            $pagado = $this->centavosVenta(
                $cuenta["paid_amount"]
            );

            $total = $this->centavosVenta(
                $cuenta["total_amount"]
            );

            if (
                $saldo === null ||
                $pagado === null ||
                $total === null ||
                $saldo + $pagado !== $total
            ) {
                throw new RuntimeException(
                    "Hay una cuenta con importes inconsistentes."
                );
            }

            $totalCentavos += $saldo;

            $datosFirma[] = [
                (int)$cuenta["id"],
                (string)$cuenta["pending_amount"],
                (string)$cuenta["due_date"],
                (string)$cuenta["fecha_origen"]
            ];
        }

        return [
            "nombre" => $nombre,
            "cuentas" => $cuentas,
            "centavos" => $totalCentavos,
            "firma" => hash(
                "sha256",
                json_encode($datosFirma)
            )
        ];
    }


    public function getAbonoGlobalCliente($data = []) {
        if (!$this->conn) {
            return [
                "status" => "error",
                "code" => 500,
                "answer" => "No hay conexión con MySQL."
            ];
        }

        try {
            $clienteId = filter_var(
                $data["cliente_id"] ?? null,
                FILTER_VALIDATE_INT,
                ["options" => ["min_range" => 1]]
            );

            if ($clienteId === false) {
                throw new InvalidArgumentException(
                    "Selecciona un cliente válido."
                );
            }

            $cartera = $this->carteraAbonoGlobal($clienteId);
            $filas = [];

            foreach ($cartera["cuentas"] as $cuenta) {
                $filas[] = [
                    "id" => (int)$cuenta["id"],
                    "documento" => $cuenta["sale_id"],
                    "fecha_origen" => $cuenta["fecha_origen"],
                    "vencimiento" => $cuenta["due_date"],
                    "saldo" => $cuenta["pending_amount"]
                ];
            }

            return [
                "status" => "ok",
                "code" => 200,
                "answer" => "Cartera consultada correctamente.",
                "results" => [
                    "cliente_id" => $clienteId,
                    "nombre" => $cartera["nombre"],
                    "saldo_total" => number_format(
                        $cartera["centavos"] / 100,
                        2, ".", ""
                    ),
                    "firma" => $cartera["firma"],
                    "cuentas" => $filas
                ]
            ];

        } catch (InvalidArgumentException $e) {
            return [
                "status" => "warning",
                "code" => 400,
                "answer" => $e->getMessage()
            ];

        } catch (Throwable $e) {
            return [
                "status" => "error",
                "code" => 500,
                "answer" => "No fue posible consultar la cartera."
            ];
        }
    }

    public function registrarAbonoGlobalCliente($data = []) {
        if (!$this->conn) {
            return [
                "status" => "error",
                "code" => 500,
                "answer" => "No hay conexión con MySQL."
            ];
        }

        $transaccion = false;

        try {
            $clienteId = filter_var(
                $data["cliente_id"] ?? null,
                FILTER_VALIDATE_INT,
                ["options" => ["min_range" => 1]]
            );

            $token = $this->textoVenta(
                $data, "solicitud_id", 32
            );

            $firma = $this->textoVenta(
                $data, "firma", 64
            );

            $metodo = $this->textoVenta(
                $data, "metodo", 20
            );

            $referencia = $this->textoVenta(
                $data, "referencia", 100
            );

            $notas = $this->textoVenta(
                $data, "notas", 450
            );

            $centavos = $this->centavosVenta(
                $data["monto"] ?? ""
            );

            $cajaId = isset($data["caja_id"])
                ? (int)$data["caja_id"]
                : 0;

            if (
                $clienteId === false ||
                !preg_match('/^[a-f0-9]{32}$/D', $token) ||
                !preg_match('/^[a-f0-9]{64}$/D', $firma)
            ) {
                throw new InvalidArgumentException(
                    "Identificador de solicitud inválido."
                );
            }

            if ($centavos === null || $centavos <= 0) {
                throw new InvalidArgumentException(
                    "El abono debe ser mayor que cero."
                );
            }

            if (!in_array(
                $metodo,
                ["EFECTIVO", "TRANSFERENCIA"],
                true
            )) {
                throw new InvalidArgumentException(
                    "Método de cobro inválido."
                );
            }

            if ($metodo === "EFECTIVO" && $cajaId <= 0) {
                throw new InvalidArgumentException(
                    "Selecciona una caja abierta."
                );
            }

            if (
                $metodo === "TRANSFERENCIA" &&
                $referencia === ""
            ) {
                throw new InvalidArgumentException(
                    "Captura la referencia bancaria."
                );
            }

            if (!$this->conn->begin_transaction()) {
                throw new RuntimeException(
                    "No se inició la transacción."
                );
            }

            $transaccion = true;

            $stmt = $this->ejecutarSqlVenta(
                "SELECT id, amount, origin,
                        cash_register_id, reference, note
                 FROM account_moves
                 WHERE request_token = ?
                 LIMIT 1 FOR UPDATE",
                "s",
                [$token]
            );

            $previo = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($previo) {
                $stmt = $this->ejecutarSqlVenta(
                    "SELECT COUNT(*) AS n
                     FROM account_settlements a
                     INNER JOIN receivables r
                         ON r.id = a.account_id
                     WHERE a.account_move_id = ?
                       AND a.account_type = 'COBRAR'
                       AND r.client_id = ?",
                    "ii",
                    [(int)$previo["id"], $clienteId]
                );

                $coincide = (int)$stmt
                    ->get_result()
                    ->fetch_assoc()["n"];

                $stmt->close();

                if (
                    $coincide === 0 ||
                    $previo["note"] !== "Abono global de cliente" ||
                    $this->centavosVenta(
                        $previo["amount"]
                    ) !== $centavos ||
                    $previo["origin"] !== (
                        $metodo === "EFECTIVO" ? "CAJA" : "BANCO"
                    ) ||
                    (
                        $metodo === "EFECTIVO" &&
                        (int)$previo["cash_register_id"] !== $cajaId
                    ) ||
                    (
                        $metodo === "TRANSFERENCIA" &&
                        $previo["reference"] !== $referencia
                    )
                ) {
                    throw new InvalidArgumentException(
                        "Esta solicitud pertenece a otro cobro."
                    );
                }

                $this->conn->rollback();
                $transaccion = false;

                return [
                    "status" => "ok",
                    "code" => 200,
                    "answer" => "El cobro ya estaba registrado.",
                    "results" => [
                        "duplicado" => true,
                        "movimiento_id" => (int)$previo["id"]
                    ]
                ];
            }

            $cartera = $this->carteraAbonoGlobal(
                $clienteId,
                true
            );

            if (!hash_equals(
                $cartera["firma"],
                $firma
            )) {
                throw new InvalidArgumentException(
                    "Los saldos cambiaron. Revisa nuevamente " .
                    "la distribución del abono."
                );
            }

            if (
                !$cartera["cuentas"] ||
                $centavos > $cartera["centavos"]
            ) {
                throw new InvalidArgumentException(
                    "El abono supera la deuda disponible."
                );
            }

            $idCajaMovimiento = $metodo === "EFECTIVO"
                ? $cajaId
                : null;

            if ($idCajaMovimiento !== null) {
                $stmt = $this->ejecutarSqlVenta(
                    "SELECT current_balance
                     FROM cash_registers
                     WHERE id = ?
                       AND kind = 'CASH'
                       AND is_active = 1
                     FOR UPDATE",
                    "i",
                    [$cajaId]
                );

                $caja = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$caja) {
                    throw new InvalidArgumentException(
                        "La caja no está disponible."
                    );
                }

                $stmt = $this->ejecutarSqlVenta(
                    "SELECT id FROM cash_shifts
                     WHERE cash_register_id = ?
                       AND status = 'ABIERTO'
                     LIMIT 1 FOR UPDATE",
                    "i",
                    [$cajaId]
                );

                $turno = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$turno) {
                    throw new InvalidArgumentException(
                        "La caja no tiene turno abierto."
                    );
                }
            }

            $ahora = date("Y-m-d H:i:s");

            $fechaOperacion = $this->fechaOperacion(
                $data,
                $idCajaMovimiento
            );

            $restante = $centavos;
            $aplicaciones = [];

            foreach ($cartera["cuentas"] as $cuenta) {
                if ($restante === 0) {
                    break;
                }

                $saldo = $this->centavosVenta(
                    $cuenta["pending_amount"]
                );

                $aplicar = min($restante, $saldo);

                if (
                    $fechaOperacion < $cuenta["created_at"]
                ) {
                    throw new InvalidArgumentException(
                        "La fecha del cobro es anterior " .
                        "al registro de una de las cuentas."
                    );
                }

                $aplicaciones[] = [
                    "id" => (int)$cuenta["id"],
                    "sale_id" => (int)$cuenta["sale_id"],
                    "anterior" => $saldo,
                    "aplicar" => $aplicar,
                    "pagado" => $this->centavosVenta(
                        $cuenta["paid_amount"]
                    )
                ];

                $restante -= $aplicar;
            }

            if ($restante !== 0) {
                throw new RuntimeException(
                    "No se pudo distribuir el importe."
                );
            }

            $monto = number_format(
                $centavos / 100, 2, ".", ""
            );

            $folio = "CXCG-" . strtoupper(
                substr($token, 0, 10)
            );

            $refMovimiento = $referencia !== ""
                ? $referencia
                : $folio;

            $stmt = $this->ejecutarSqlVenta(
                "INSERT INTO account_moves
                 (`date`, `type`, `origin`,
                  cash_register_id, user_reference_name,
                  reference, amount, user_id,
                  link_type, link_id, ticket_number,
                  status, note, notes,
                  created_at, updated_at,
                  expense_category, request_token)
                 VALUES (
                     ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                     'PAGADO', ?, ?, ?, ?, ?, ?
                 )",
                "sssisssisisssssss",
                [
                    $fechaOperacion,
                    "ENTRADA",
                    $metodo === "EFECTIVO"
                        ? "CAJA"
                        : "BANCO",
                    $idCajaMovimiento,
                    $cartera["nombre"],
                    $refMovimiento,
                    $monto,
                    $this->usuarioOperador,
                    "CXC",
                    $aplicaciones[0]["id"],
                    $folio,
                    "Abono global de cliente",
                    $notas,
                    $ahora,
                    $ahora,
                    null,
                    $token
                ]
            );

            $stmt->close();

            $movimientoId = (int)$this->conn->insert_id;
            $detalle = [];

            foreach ($aplicaciones as $aplicacion) {
                $abonado = number_format(
                    $aplicacion["aplicar"] / 100,
                    2, ".", ""
                );

                $saldoNuevo = number_format(
                    (
                        $aplicacion["anterior"] -
                        $aplicacion["aplicar"]
                    ) / 100,
                    2, ".", ""
                );

                $pagadoNuevo = number_format(
                    (
                        $aplicacion["pagado"] +
                        $aplicacion["aplicar"]
                    ) / 100,
                    2, ".", ""
                );

                $estadoNuevo = (
                    $aplicacion["aplicar"] ===
                    $aplicacion["anterior"]
                ) ? "PAGADO" : "PENDIENTE";

                $tokenCuenta = substr(
                    hash(
                        "sha256",
                        "CXC-GLOBAL|" . $token . "|" .
                        $aplicacion["id"]
                    ),
                    0,
                    32
                );

                $notaGrupo = "[GLOBAL " . $token . "] " .
                    $notas;

                $stmt = $this->ejecutarSqlVenta(
                    "INSERT INTO account_settlements
                     (account_type, account_id,
                      account_move_id, amount, method,
                      cash_register_id, reference,
                      notes, user_id, request_token,
                      created_at)
                     VALUES (
                         'COBRAR',
                         ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                     )",
                    "iissississ",
                    [
                        $aplicacion["id"],
                        $movimientoId,
                        $abonado,
                        $metodo,
                        $idCajaMovimiento,
                        $refMovimiento,
                        $notaGrupo,
                        $this->usuarioOperador,
                        $tokenCuenta,
                        $fechaOperacion
                    ]
                );

                $stmt->close();

                $stmt = $this->ejecutarSqlVenta(
                    "UPDATE receivables
                     SET paid_amount = ?,
                         pending_amount = ?,
                         status = ?,
                         updated_at = ?
                     WHERE id = ?
                       AND client_id = ?
                       AND status = 'PENDIENTE'
                       AND pending_amount = ?",
                    "ssssiis",
                    [
                        $pagadoNuevo,
                        $saldoNuevo,
                        $estadoNuevo,
                        $ahora,
                        $aplicacion["id"],
                        $clienteId,
                        number_format(
                            $aplicacion["anterior"] / 100,
                            2, ".", ""
                        )
                    ]
                );

                if ($stmt->affected_rows !== 1) {
                    throw new RuntimeException(
                        "Cambió el saldo de un crédito."
                    );
                }

                $stmt->close();

                if ($aplicacion["sale_id"] > 0) {
                    $stmt = $this->ejecutarSqlVenta(
                        "SELECT pending_amount
                         FROM sales
                         WHERE id = ? AND corp_id = ?
                         FOR UPDATE",
                        "is",
                        [
                            $aplicacion["sale_id"],
                            $this->corpClientes()
                        ]
                    );

                    $venta = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    if (
                        !$venta ||
                        $this->centavosVenta(
                            $venta["pending_amount"]
                        ) !== $aplicacion["anterior"]
                    ) {
                        throw new RuntimeException(
                            "El saldo de la venta no coincide " .
                            "con el crédito."
                        );
                    }

                    $stmt = $this->ejecutarSqlVenta(
                        "UPDATE sales
                         SET pending_amount = ?,
                             updated_at = ?
                         WHERE id = ? AND corp_id = ?",
                        "ssis",
                        [
                            $saldoNuevo,
                            $ahora,
                            $aplicacion["sale_id"],
                            $this->corpClientes()
                        ]
                    );

                    if ($stmt->affected_rows !== 1) {
                        throw new RuntimeException(
                            "No se actualizó la venta."
                        );
                    }

                    $stmt->close();
                }

                $detalle[] = [
                    "credito_id" => $aplicacion["id"],
                    "aplicado" => $abonado,
                    "saldo" => $saldoNuevo,
                    "estado" => $estadoNuevo
                ];
            }

            if ($idCajaMovimiento !== null) {
                $stmt = $this->ejecutarSqlVenta(
                    "INSERT INTO cash_flows
                     (cash_register_id, user_id,
                      amount, `type`, description,
                      reference, created_at)
                     VALUES (?, ?, ?, 'IN', ?, ?, ?)",
                    "iissss",
                    [
                        $cajaId,
                        $this->usuarioOperador,
                        $monto,
                        "Abono global a cliente",
                        $folio,
                        $fechaOperacion
                    ]
                );

                $stmt->close();

                $stmt = $this->ejecutarSqlVenta(
                    "UPDATE cash_registers
                     SET current_balance =
                             current_balance + ?,
                         updated_at = ?
                     WHERE id = ?
                       AND kind = 'CASH'
                       AND is_active = 1",
                    "ssi",
                    [$monto, $ahora, $cajaId]
                );

                if ($stmt->affected_rows !== 1) {
                    throw new RuntimeException(
                        "No fue posible actualizar la caja."
                    );
                }

                $stmt->close();
            }

            if (!$this->conn->commit()) {
                throw new RuntimeException(
                    "No se pudo confirmar el cobro."
                );
            }

            $transaccion = false;

            return [
                "status" => "ok",
                "code" => 200,
                "answer" => "Abono global registrado correctamente.",
                "results" => [
                    "duplicado" => false,
                    "movimiento_id" => $movimientoId,
                    "folio" => $folio,
                    "aplicaciones" => $detalle,
                    "saldo_total" => number_format(
                        (
                            $cartera["centavos"] -
                            $centavos
                        ) / 100,
                        2, ".", ""
                    )
                ]
            ];

        } catch (InvalidArgumentException $e) {
            if ($transaccion) {
                $this->conn->rollback();
            }

            return [
                "status" => "warning",
                "code" => 400,
                "answer" => $e->getMessage()
            ];

        } catch (Throwable $e) {
            if ($transaccion) {
                $this->conn->rollback();
            }

            return [
                "status" => "error",
                "code" => 500,
                "answer" => "No se pudo confirmar el abono global. " .
                    "Reintenta con el mismo identificador " .
                    "para evitar duplicados."
            ];
        }
    }



    public function getPersonasCuentas($data = []) {
        if (!$this->conn) {
            return [
                "status" => "error",
                "code" => 500,
                "answer" => "No hay conexión con MySQL."
            ];
        }

        try {
            $tipo = $this->textoVenta($data, "tipo", 10);
            $meta = $this->metaCuenta($tipo);
            $buscar = $this->textoVenta($data, "buscar", 100);

            if ($tipo === "COBRAR") {
                $sql = "
                    SELECT
                        r.client_id AS cliente_id,
                        COALESCE(
                            MAX(NULLIF(
                                TRIM(CONCAT_WS(' ', c.nombre, c.apellido)),
                                ''
                            )),
                            MAX(r.client_name)
                        ) AS nombre,
                        COUNT(*) AS creditos,
                        COALESCE(SUM(r.total_amount), 0) AS total,
                        COALESCE(SUM(r.paid_amount), 0) AS abonado,
                        COALESCE(SUM(r.pending_amount), 0) AS pendiente,
                        SUM(
                            CASE
                                WHEN r.status = 'PENDIENTE'
                                    AND r.pending_amount > 0
                                    AND r.due_date < CURDATE()
                                THEN 1 ELSE 0
                            END
                        ) AS vencidos,
                        MIN(
                            CASE
                                WHEN r.status = 'PENDIENTE'
                                    AND r.pending_amount > 0
                                    AND r.due_date >= CURDATE()
                                THEN r.due_date
                            END
                        ) AS proximo_pago
                    FROM receivables r
                    LEFT JOIN clientes c
                        ON c.corp_id = ?
                        AND c.id = r.client_id
                    WHERE r.status <> 'CANCELADO'
                    GROUP BY
                        r.client_id,
                        CASE
                            WHEN r.client_id IS NULL THEN r.id
                            ELSE 0
                        END
                ";

                $tipos = "s";
                $params = [$this->corpClientes()];

                if ($buscar !== "") {
                    $sql .= "
                        HAVING nombre LIKE ?
                            OR CAST(cliente_id AS CHAR) = ?
                    ";

                    $tipos .= "ss";
                    $params[] = "%" . $buscar . "%";
                    $params[] = $buscar;
                }

            } else {
                $tabla = $meta["tabla"];
                $col = $meta["nombre"];

                $sql = "
                    SELECT
                        NULL AS cliente_id,
                        `$col` AS nombre,
                        COUNT(*) AS creditos,
                        COALESCE(SUM(total_amount), 0) AS total,
                        COALESCE(SUM(paid_amount), 0) AS abonado,
                        COALESCE(SUM(pending_amount), 0) AS pendiente,
                        SUM(
                            CASE
                                WHEN status = 'PENDIENTE'
                                    AND pending_amount > 0
                                    AND due_date < CURDATE()
                                THEN 1 ELSE 0
                            END
                        ) AS vencidos,
                        MIN(
                            CASE
                                WHEN status = 'PENDIENTE'
                                    AND pending_amount > 0
                                    AND due_date >= CURDATE()
                                THEN due_date
                            END
                        ) AS proximo_pago
                    FROM `$tabla`
                    WHERE status <> 'CANCELADO'
                ";

                $tipos = "";
                $params = [];

                if ($buscar !== "") {
                    $sql .= " AND `$col` LIKE ?";
                    $tipos = "s";
                    $params[] = "%" . $buscar . "%";
                }

                $sql .= " GROUP BY `$col`";
            }

            $sql .= "
                ORDER BY pendiente DESC, nombre ASC
                LIMIT 200
            ";

            $stmt = $this->ejecutarSqlVenta(
                $sql,
                $tipos,
                $params
            );

            $personas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            return [
                "status" => "ok",
                "code" => 200,
                "answer" => "Personas consultadas.",
                "results" => [
                    "personas" => $personas
                ]
            ];

        } catch (InvalidArgumentException $e) {
            return [
                "status" => "warning",
                "code" => 400,
                "answer" => $e->getMessage()
            ];

        } catch (Throwable $e) {
         

            return [
                "status" => "error",
                "code" => 500,
                "answer" => "No fue posible consultar los deudores o proveedores.",
                'error' => $e->getMessage()
            ];
        }
    }




public function getAbonosCuentas($data = []) {
    $json=[];
    if (!$this->conn) {
        $json=["status"=>"error","answer"=>"No hay conexión con MySQL.","code"=>500];
    } else {
        try {
            $tipo=$data["tipo"] ?? "";
            $meta=$this->metaCuenta($tipo);
            $tabla=$meta["tabla"]; $col=$meta["nombre"];
            $buscar=$this->textoVenta($data,"buscar",100);
            $inicio=$this->textoVenta($data,"fecha_inicio",10);
            $fin=$this->textoVenta($data,"fecha_fin",10);
            if ($inicio!=="" || $fin!=="") {
                $a=DateTime::createFromFormat("!Y-m-d",$inicio);
                $b=DateTime::createFromFormat("!Y-m-d",$fin);
                if (!$a || !$b || $a->format("Y-m-d")!==$inicio || $b->format("Y-m-d")!==$fin || $inicio>$fin) throw new InvalidArgumentException("El rango de fechas no es válido.");
            }
            
$sql="SELECT a.id,a.account_id,r.`$col` AS nombre,a.amount,a.method,a.reference,a.created_at,'REGISTRADO' AS origen FROM account_settlements a INNER JOIN `$tabla` r ON r.id=a.account_id WHERE a.account_type=?";
$tipos="s"; $params=[$tipo];
if ($buscar!=="") {$sql.=" AND (r.`$col` LIKE ? OR COALESCE(a.reference,'') LIKE ? OR CAST(a.account_id AS CHAR) LIKE ?)";$patron="%".$buscar."%";$tipos.="sss";array_push($params,$patron,$patron,$patron);}
if ($inicio!=="") {$sql.=" AND a.created_at>=? AND a.created_at<DATE_ADD(?,INTERVAL 1 DAY)";$tipos.="ss";array_push($params,$inicio,$fin);}
$sql.=" ORDER BY a.created_at DESC,a.id DESC LIMIT 200";
$stmt=$this->ejecutarSqlVenta($sql,$tipos,$params);
$movimientos=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();

if ($tipo==="COBRAR") {
    $sql="SELECT -s.id AS id,r.id AS account_id,r.client_name AS nombre,s.initial_paid AS amount,CASE WHEN s.cash_register_id IS NOT NULL THEN 'EFECTIVO' ELSE 'TRANSFERENCIA' END AS method,COALESCE(NULLIF(s.bank_reference,''),s.note_folio) AS reference,s.created_at,'INICIAL' AS origen FROM receivables r INNER JOIN sales s ON s.id=r.sale_id WHERE s.initial_paid>0";
    $tipos="";$params=[];
    if ($buscar!=="") {$sql.=" AND (r.client_name LIKE ? OR COALESCE(s.bank_reference,s.note_folio,'') LIKE ? OR CAST(r.id AS CHAR) LIKE ?)";$patron="%".$buscar."%";$tipos.="sss";$params=[$patron,$patron,$patron];}
    if ($inicio!=="") {$sql.=" AND s.created_at>=? AND s.created_at<DATE_ADD(?,INTERVAL 1 DAY)";$tipos.="ss";array_push($params,$inicio,$fin);}
    $sql.=" ORDER BY s.created_at DESC,s.id DESC LIMIT 200";
    $stmt=$this->ejecutarSqlVenta($sql,$tipos,$params);
    $iniciales=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();
    $movimientos=array_merge($movimientos,$iniciales);
    usort($movimientos,function($a,$b){
        $orden=strcmp($b["created_at"],$a["created_at"]);
        return $orden!==0 ? $orden : ((int)$b["id"] <=> (int)$a["id"]);
    });
    $movimientos=array_slice($movimientos,0,200);
}

            $json=["status"=>"ok","answer"=>"Abonos y pagos consultados.","results"=>["movimientos"=>$movimientos],"code"=>200];
        } catch (InvalidArgumentException $e) {
            $json=["status"=>"warning","answer"=>$e->getMessage(),"code"=>400];
        } catch (Throwable $e) {
            
            $json=["status"=>"error","answer"=>"No fue posible consultar los abonos o pagos.","code"=>500];
        }
    }
    return $json;
}


public function generarDocumentosNotaVenta($data) {
    $json=[]; $transaccion=false; $firmaTmp=''; $creados=[];
    if (!$this->conn) return ['status'=>'error','answer'=>'Sin conexión con MySQL.','code'=>500];

    try {
        $id=isset($data['id'])?(int)$data['id']:0;
        $formato=$this->textoVenta($data,'formato',12);
        $fuente=$this->textoVenta($data,'firma_tipo',15);

        if ($id<=0 || !in_array($formato,['COMPACTA','CORPORATIVA','AMBAS'],true)) {
            throw new InvalidArgumentException('Selecciona documentos válidos.');
        }
        if (!in_array($fuente,['PREDEFINIDA','DIBUJADA'],true)) {
            throw new InvalidArgumentException('Selecciona una firma válida.');
        }

        $venta=$this->getDatosNotaVentaPdf($id);
        if (!$venta || empty($venta['items'])) {
            throw new InvalidArgumentException('No se encontró la venta o sus conceptos.');
        }

        $nombre=$fuente==='PREDEFINIDA'
            ? 'C. HERMINIO PEREZ BERNABE'
            : $this->textoVenta($data,'firmante',100);

        if ($nombre==='') throw new InvalidArgumentException('Indica el nombre de quien firma.');

        if (!$this->conn->begin_transaction()) {
            throw new RuntimeException('No se pudo iniciar la operación.');
        }
        $transaccion=true;

        $stmt=$this->ejecutarSqlVenta(
            "SELECT document_signature_png,document_signer,document_signature_source
             FROM sales WHERE id=? AND sale_kind='NOTA' FOR UPDATE",
            "i",[$id]
        );
        $guardada=$stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$guardada) throw new InvalidArgumentException('La venta ya no existe.');

        $firmaExistia=$guardada['document_signature_png']!==null
            && $guardada['document_signature_png']!=='';

        if ($firmaExistia) {
            $png=$guardada['document_signature_png'];
            $nombre=$guardada['document_signer'];
        } else {
            if ($fuente==='PREDEFINIDA') {
                $rutaFirma=__DIR__.'/../recursos/firma.png';
                if (!is_file($rutaFirma)) {
                    throw new RuntimeException('Falta recursos/firma.png.');
                }
                $png=file_get_contents($rutaFirma);
            } else {
                $entrada=isset($data['firma_png'])?(string)$data['firma_png']:'';
                if (strlen($entrada)>480000 ||
                    !preg_match('~^data:image/png;base64,([A-Za-z0-9+/=]+)$~D',$entrada,$m)) {
                    throw new InvalidArgumentException('La firma dibujada no tiene un formato válido.');
                }
                $png=base64_decode($m[1],true);
            }

            if (!is_string($png) || strlen($png)<70 ||
                strlen($png)>($fuente==='PREDEFINIDA'?1500000:350000)) {
                throw new InvalidArgumentException('La firma supera el tamaño permitido o es inválida.');
            }

            $info=@getimagesizefromstring($png);
            if (!$info || $info['mime']!=='image/png' ||
                $info[0]>4000 || $info[1]>1400 ||
                $info[0]<1 || $info[1]<1) {
                throw new InvalidArgumentException('La firma debe ser una imagen PNG válida.');
            }

            $stmt=$this->ejecutarSqlVenta(
                "UPDATE sales SET document_signature_png=?,
                 document_signer=?,document_signature_source=?,
                 document_signed_at=NOW() WHERE id=?",
                "sssi",[$png,$nombre,$fuente,$id]
            );
            $stmt->close();
        }

        if (!$this->conn->commit()) throw new RuntimeException('No se pudo confirmar la firma.');
        $transaccion=false;

        $firmaTmp=tempnam(sys_get_temp_dir(),'firma_nv_');
        if (!$firmaTmp || file_put_contents($firmaTmp,$png)===false) {
            throw new RuntimeException('No se pudo preparar la imagen de la firma.');
        }

        $archivos=[];

        if ($formato==='COMPACTA' || $formato==='AMBAS') {
            $pdf=new notaVenta();
            $archivo=$pdf->generarArchivo($venta,$firmaTmp);
            $creados[]=$archivo['ruta_fisica'];
            $archivos[]=[
                'tipo'=>'COMPACTA',
                'archivo'=>$archivo['ruta_web'],
                'nombre'=>$archivo['nombre']
            ];
        }

        if ($formato==='CORPORATIVA' || $formato==='AMBAS') {
            $pdf=new notaVentaCompleta($venta,$firmaTmp,$nombre);
            $archivo=$pdf->generarArchivo();
            $creados[]=$archivo['ruta_fisica'];
            $archivos[]=[
                'tipo'=>'CORPORATIVA',
                'archivo'=>$archivo['ruta_web'],
                'nombre'=>$archivo['nombre']
            ];
        }

        $json=[
            'status'=>'ok',
            'answer'=>'Documentos generados correctamente.',
            'results'=>[
                'archivos'=>$archivos,
                'folio'=>$venta['note_folio'],
                'firma_reutilizada'=>$firmaExistia
            ],
            'code'=>200
        ];

    } catch (InvalidArgumentException $e) {
        if ($transaccion) $this->conn->rollback();
        $json=['status'=>'warning','answer'=>$e->getMessage(),'code'=>400];

    } catch (Throwable $e) {
        if ($transaccion) $this->conn->rollback();
        foreach ($creados as $ruta) @unlink($ruta);
      
        $json=[
            'status'=>'error',
            'answer'=>'No se pudieron generar los PDF. Los importes de la venta permanecen intactos.',
            'code'=>500
        ];

    } finally {
        if ($firmaTmp && is_file($firmaTmp)) @unlink($firmaTmp);
    }
    return $json;
}


public function generarConcentradoCxc($data) {
    $json = [];
    if (!$this->conn) return ['status'=>'error','answer'=>'Sin conexión a MySQL.','code'=>500];

    try {
        $id = isset($data['id']) ? (int)$data['id'] : 0;
        $agrupar = isset($data['agrupar']) && (string)$data['agrupar']==='1';
        if ($id<=0) throw new InvalidArgumentException('Selecciona una cuenta válida.');

        $stmt=$this->ejecutarSqlVenta(
            'SELECT id,client_id,client_name FROM receivables WHERE id=? LIMIT 1',
            'i',[$id]
        );
        $origen=$stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$origen) throw new InvalidArgumentException('No se encontró la cuenta.');

        $where='r.id=?'; $tipos='i'; $params=[$id];

        if ($agrupar) {
            $clienteId = (int)$origen["client_id"];

            if ($clienteId <= 0) {
                throw new InvalidArgumentException(
                    "Este crédito no tiene un cliente vinculado. " .
                    "Asígnale un cliente antes de generar su concentrado."
                );
            }

            $where = "r.client_id = ?";
            $tipos = "i";
            $params = [$clienteId];
        }

        $sql="SELECT r.id,r.sale_id,r.description,r.document_reference,
                     r.total_amount,r.paid_amount,r.pending_amount,
                     r.created_at AS fecha_cuenta,
                     s.note_folio,s.created_at AS fecha_venta,s.sale_kind
              FROM receivables r
              LEFT JOIN sales s ON s.id=r.sale_id
              WHERE ($where) AND r.status='PENDIENTE'
                AND r.pending_amount>0
              ORDER BY COALESCE(s.created_at,r.created_at),r.id
              LIMIT 151";

        $stmt=$this->ejecutarSqlVenta($sql,$tipos,$params);
        $cuentas=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (!$cuentas) throw new InvalidArgumentException('No hay saldos pendientes para este reporte.');
        if (count($cuentas)>150) throw new InvalidArgumentException('El concentrado supera 150 cuentas. Se necesita dividirlo por periodos.');

        $ids=[]; $indices=[]; $total=0; $abonado=0; $pendiente=0; $fechas=[];

        foreach ($cuentas as $k=>&$c) {
            if ((int)$c['sale_id']>0 && $c['sale_kind']!=='NOTA') {
                throw new RuntimeException('Existe una cuenta vinculada a una venta sin nota.');
            }

            $c['items']=[];
            $c['folio']=$c['note_folio'] ?: ($c['document_reference'] ?: 'CXC-'.$c['id']);
            $c['fecha']=$c['fecha_venta'] ?: $c['fecha_cuenta'];
            $fechas[]=substr($c['fecha'],0,10);

            $original=$this->centavosVenta($c['total_amount']);
            $pagado=$this->centavosVenta($c['paid_amount']);
            $saldo=$this->centavosVenta($c['pending_amount']);
            if ($original===null || $pagado===null || $saldo===null ||
                $original-$pagado!==$saldo) {
                throw new RuntimeException('La cuenta '.$c['id'].' tiene importes inconsistentes.');
            }
            $total+=$original;
            $abonado+=$pagado;
            $pendiente+=$saldo;

            if ((int)$c['sale_id']>0) {
                $idVenta=(int)$c['sale_id'];
                if (isset($indices[$idVenta])) {
                    throw new RuntimeException('Hay cuentas duplicadas para la venta '.$idVenta);
                }
                $ids[]=$idVenta;
                $indices[$idVenta]=$k;
            }
        }
        unset($c);

        if ($ids) {
            $marcas=implode(',',array_fill(0,count($ids),'?'));
            /*$stmt=$this->ejecutarSqlVenta(
                "SELECT sale_id,product_id,manual_name,manual_code,quantity,price,total
                 FROM sale_items WHERE sale_id IN ($marcas) ORDER BY sale_id,id",
                str_repeat('i',count($ids)),$ids
            );*/

$stmt = $this->ejecutarSqlVenta(
    "SELECT si.sale_id, si.product_id, si.manual_name,
            si.manual_code, si.quantity, si.price, si.total,
            
COALESCE(
    NULLIF(TRIM(si.unit_code), ''),
    NULLIF(TRIM(p_id.unit), ''),
    p_codigo.unit,
    '-'
) AS unidad

     FROM sale_items si
     LEFT JOIN products p_id
         ON p_id.id = si.product_id
     LEFT JOIN (
         SELECT code, MAX(NULLIF(TRIM(unit), '')) AS unit
         FROM products
         WHERE code IS NOT NULL AND code <> ''
         GROUP BY code
         HAVING COUNT(*) = 1
     ) p_codigo
         ON p_codigo.code = si.manual_code
     WHERE si.sale_id IN ($marcas)
     ORDER BY si.sale_id, si.id",
    str_repeat('i', count($ids)),
    $ids
);

            $filas=$stmt->get_result();

            while ($item=$filas->fetch_assoc()) {
                if (!$item['manual_name']) {
                    $item['manual_name']=(int)$item['product_id']>0
                        ? 'Artículo #'.$item['product_id']
                        : 'Artículo sin descripción';
                }
                $cuentas[$indices[(int)$item['sale_id']]]['items'][]=$item;
            }
            $stmt->close();
        }

        foreach ($cuentas as &$c) {
            if (!$c['items']) {
                $c['items'][]=[
                    'manual_name'=>$c['description'] ?: 'Cuenta sin desglose de artículos',
                    'manual_code'=>'',
                    'quantity'=>null,
                    'price'=>null,
                    'total'=>$c['total_amount'],
                    'unidad'=>'-',
                ];
            }
        }
        unset($c);

        sort($fechas);

        $datos=[
            'cliente'=>$origen['client_name'],
            'cuentas'=>$cuentas,
            'desde'=>$fechas[0],
            'hasta'=>$fechas[count($fechas)-1],
            'total'=>$total/100,
            'abonado'=>$abonado/100,
            'pendiente'=>$pendiente/100
        ];

        $pdf=new reporteCxcSanMartinPdf($datos);
        $archivo=$pdf->generarArchivo();

        $json=[
            'status'=>'ok',
            'answer'=>'Concentrado generado correctamente.',
            'code'=>200,
            'results'=>[
                'archivo'=>$archivo['ruta_web'],
                'nombre'=>$archivo['nombre'],
                'cuentas'=>count($cuentas),
                'pendiente'=>$datos['pendiente']
            ]
        ];

    } catch (InvalidArgumentException $e) {
        $json=['status'=>'warning','answer'=>$e->getMessage(),'code'=>400];
    } catch (Throwable $e) {
        
        $json=['status'=>'error','answer'=>'No fue posible generar el concentrado.','code'=>500];
    }
    return $json;
}


public function generarTicketVenta($data) {
    $json = [];

    if (!$this->conn) {
        $json["status"] = "error";
        $json["answer"] = "No hay conexión con MySQL.";
        $json["code"] = 500;
    } else {
        try {
            $idTexto = isset($data["id"]) && is_scalar($data["id"]) ? (string)$data["id"] : "";
            $anchoTexto = isset($data["ancho"]) && is_scalar($data["ancho"]) ? (string)$data["ancho"] : "";

            if (!ctype_digit($idTexto) || (int)$idTexto <= 0 || !in_array($anchoTexto, ["58", "80"], true)) {
                throw new InvalidArgumentException("Selecciona una venta y un ancho de 58 u 80 mm.");
            }

            $id = (int)$idTexto;
            $ancho = (int)$anchoTexto;

            $stmt = $this->ejecutarSqlVenta(
                "SELECT s.id,s.sale_kind,s.note_folio,s.ticket_barcode,
                        s.client_name,s.client_address,s.client_phone,s.seller_name,
                        s.payment_method,s.bank_reference,s.total_amount,
                        s.initial_paid,s.pending_amount,s.created_at,
                        (SELECT r.pending_amount FROM receivables r
                         WHERE r.sale_id=s.id ORDER BY r.id DESC LIMIT 1) AS saldo_cuenta
                 FROM sales s
                 WHERE s.id=? AND s.sale_kind IN ('NOTA','RAPIDA')
                 LIMIT 1",
                "i",
                [$id]
            );

            $venta = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$venta) {
                throw new DomainException("No existe una venta válida con ese ID.");
            }

            $stmt = $this->ejecutarSqlVenta(
                "SELECT si.quantity,si.price,si.total,si.manual_code,
                        COALESCE(
                            NULLIF(TRIM(si.manual_name),''),
                            NULLIF(TRIM(p_id.name),''),
                            'ARTICULO'
                        ) AS descripcion,
                        
COALESCE(
    NULLIF(TRIM(si.unit_code),''),
    NULLIF(TRIM(p_id.unit),''),
    NULLIF(TRIM(pc.unit),''),
    '-'
) AS unidad

                 FROM sale_items si
                 LEFT JOIN products p_id ON p_id.id=si.product_id
                 LEFT JOIN (
                     SELECT code,MAX(unit) AS unit
                     FROM products
                     WHERE code IS NOT NULL AND code<>''
                     GROUP BY code
                     HAVING COUNT(*)=1
                 ) pc ON pc.code=si.manual_code
                 WHERE si.sale_id=?
                 ORDER BY si.id",
                "i",
                [$id]
            );

            $articulos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            if (!$articulos) {
                throw new DomainException("La venta no contiene artículos para imprimir.");
            }

            $venta["saldo_ticket"] = $venta["saldo_cuenta"] !== null
                ? $venta["saldo_cuenta"]
                : $venta["pending_amount"];

            $pdf = new ticketVentaTermico($venta, $articulos, $ancho);
            $archivo = $pdf->generarArchivo();

            $json["status"] = "ok";
            $json["answer"] = "Ticket generado correctamente.";
            $json["results"] = [
                "id" => $id,
                "ancho" => $ancho,
                "nombre" => $archivo["nombre"],
                "archivo" => $archivo["ruta_web"]
            ];
            $json["code"] = 200;

        } catch (InvalidArgumentException $e) {
            $json["status"] = "warning";
            $json["answer"] = $e->getMessage();
            $json["code"] = 400;

        } catch (DomainException $e) {
            $json["status"] = "warning";
            $json["answer"] = $e->getMessage();
            $json["code"] = 404;

        } catch (Throwable $e) {
            

            $json["status"] = "error";
            $json["answer"] = "No fue posible generar el ticket.";
            $json["code"] = 500;
        }
    }

    return $json;
}

//Nuevas funciones
private function enteroCosteo($valor, int $decimales): int {
    if (!is_string($valor) && !is_int($valor)) {
        throw new InvalidArgumentException('Importe numérico inválido.');
    }

    $valor = trim((string)$valor);

    if (!preg_match('/^\d{1,8}(?:\.\d{1,' . $decimales . '})?$/D', $valor)) {
        throw new InvalidArgumentException(
            'Revisa los números; utiliza punto decimal y valores no negativos.'
        );
    }

    $partes = explode('.', $valor, 2);
    $escala = 10 ** $decimales;

    return ((int)$partes[0] * $escala) +
        (int)str_pad($partes[1] ?? '', $decimales, '0');
}

public function getCatalogosCosteo(array $data = []): array {
    $json = [];

    try {
        if (!$this->conn) {
            throw new RuntimeException('Conexión no disponible.');
        }

        $stmt = $this->ejecutarSqlVenta(
            'SELECT role_id FROM users WHERE id=? AND is_active=1 LIMIT 1',
            'i',
            [$this->usuarioOperador]
        );
        $operador = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$operador || (int)$operador['role_id'] !== 1) {
            throw new DomainException(
                'Solo administración puede consultar costos y utilidades.'
            );
        }

        
$incluirEliminados = ($data['incluir_eliminados'] ?? '0') === '1';
$filtro = $incluirEliminados ? '' : ' AND eliminado=0';
$filtroTarifa = $incluirEliminados ? '' :
    ' AND t.eliminado=0 AND m.activo=1 AND m.eliminado=0 AND l.activo=1 AND l.eliminado=0';

$sqls = [
    'materiales' => "SELECT id,codigo,nombre,unidad,costo_unitario,activo,eliminado
        FROM materiales_costeo WHERE activo=1 {$filtro} ORDER BY nombre",

    'listas' => "SELECT id,nombre,activo,eliminado
        FROM listas_precios_costeo WHERE activo=1 {$filtro} ORDER BY nombre",

    'vehiculos' => "SELECT id,nombre,tipo,unidad_capacidad,capacidad,
        rendimiento_km_litro,costo_fijo_viaje,vin,tipo_transporte,
        configuracion_vehicular,peso_bruto_kg,carga_util_kg,
        tanque_litros,combustible,activo,eliminado
        FROM vehiculos_costeo WHERE activo=1 {$filtro} ORDER BY nombre",

    'destinos' => "SELECT
    id, nombre, distancia_ida_km, casetas_por_viaje,
    calle, numero_exterior, numero_interior, colonia,
    municipio, estado, codigo_postal, referencias,
    latitud, longitud, activo, eliminado
    FROM destinos_costeo
    WHERE activo=1 {$filtro}
    ORDER BY nombre",

    'tarifas' => "SELECT t.material_id,t.lista_id,t.recargo_pct,t.activo,t.eliminado,
        COALESCE(m.nombre,'Material no disponible') AS material_nombre,
        COALESCE(l.nombre,'Lista no disponible') AS lista_nombre
        FROM tarifas_material_costeo t
        LEFT JOIN materiales_costeo m ON m.id=t.material_id
        LEFT JOIN listas_precios_costeo l ON l.id=t.lista_id
        WHERE t.activo=1 {$filtroTarifa}
        ORDER BY material_nombre,lista_nombre"
];


        $resultados = [];

        foreach ($sqls as $clave => $sql) {
            $stmt = $this->ejecutarSqlVenta($sql);
            $resultados[$clave] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }

        $json = [
            'status' => 'ok',
            'answer' => 'Catálogos consultados.',
            'results' => $resultados,
            'code' => 200
        ];

    } catch (DomainException $e) {
        $json = [
            'status' => 'warning',
            'answer' => $e->getMessage(),
            'code' => 403
        ];

    } catch (Throwable $e) {
        

        $json = [
            'status' => 'error',
            'answer' => 'No fue posible consultar los catálogos.',
            'code' => 500
        ];
    }

    return $json;
}


public function cotizarMaterial(array $data, bool $guardar = false): array {
    $json = [];
    $transaccion = false;

    try {
        if (!$this->conn) {
            throw new RuntimeException('Conexión no disponible.');
        }

        if (!isset($data['datos']) || !is_string($data['datos'])) {
            throw new InvalidArgumentException('Faltan los datos de la cotización.');
        }

        $d = json_decode($data['datos'], true, 32, JSON_THROW_ON_ERROR);

        if (!is_array($d)) {
            throw new InvalidArgumentException('Los datos deben ser un objeto JSON.');
        }

        // 1. VALIDAR IDENTIFICADORES
        $ids = [];

        foreach (['material_id','vehiculo_id','destino_id','lista_id'] as $clave) {
            $id = filter_var(
                $d[$clave] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            if ($id === false || $id === null) {
                throw new InvalidArgumentException(
                    'Selecciona material, vehículo, destino y lista válidos.'
                );
            }

            $ids[$clave] = (int)$id;
        }

        $cantidad = $this->enteroCosteo($d['cantidad'] ?? '', 2);

        if ($cantidad < 1 || $cantidad > 10000000) {
            throw new InvalidArgumentException(
                'Cantidad permitida: 0.01 a 100,000.00.'
            );
        }

        $cliente = $d['cliente'] ?? '';

        if (!is_string($cliente)) {
            throw new InvalidArgumentException('El nombre del cliente es inválido.');
        }

        $cliente = trim($cliente);

        if (strlen($cliente) > 400 ||
            preg_match_all('/./us', $cliente) > 100 ||
            str_contains($cliente, "\0")) {
            throw new InvalidArgumentException('Nombre de cliente inválido.');
        }

        $solicitud = $d['solicitud_id'] ?? '';

        if (!is_string($solicitud)) {
            throw new InvalidArgumentException('Identificador de solicitud inválido.');
        }

        if ($guardar && ($cliente === '' ||
            !preg_match('/^[a-f0-9]{32}$/D', $solicitud))) {
            throw new InvalidArgumentException(
                'Para guardar, indica cliente y solicitud_id hexadecimal de 32 caracteres.'
            );
        }

        // 2. VALIDAR GASTOS CAPTURADOS
        $entradas = [];

        foreach ([
            'precio_diesel_litro',
            'chofer_por_viaje',
            'carga_por_viaje',
            'descarga_por_viaje',
            'flete_contratado_por_viaje',
            'otros_gastos_total'
        ] as $clave) {
            $entradas[$clave] = $this->enteroCosteo($d[$clave] ?? '0', 2);

            if ($entradas[$clave] > 100000000) {
                throw new InvalidArgumentException(
                    'Hay un gasto por encima del máximo permitido.'
                );
            }
        }

        $normalizado = array_merge(
            $ids,
            ['cantidad' => $cantidad, 'cliente' => $cliente],
            $entradas
        );

        $huella = hash(
            'sha256',
            json_encode($normalizado, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
        );

        // 3. VALIDAR PERMISOS
        $stmt = $this->ejecutarSqlVenta(
            'SELECT role_id FROM users WHERE id=? AND is_active=1 LIMIT 1',
            'i',
            [$this->usuarioOperador]
        );

        $operador = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$operador || (int)$operador['role_id'] !== 1) {
            throw new DomainException(
                'Solo administración puede calcular y guardar costos.'
            );
        }

        // 4. EVITAR SOLICITUDES DUPLICADAS
        if ($guardar) {
            $this->conn->begin_transaction();
            $transaccion = true;

            $stmt = $this->ejecutarSqlVenta(
                'SELECT id,huella,precio_unitario,total_venta,utilidad
                 FROM cotizaciones_costeo
                 WHERE solicitud_id=? LIMIT 1 FOR UPDATE',
                's',
                [$solicitud]
            );

            $anterior = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        } else {
            $anterior = null;
        }

        if ($anterior) {
            if (!hash_equals($anterior['huella'], $huella)) {
                throw new InvalidArgumentException(
                    'Este identificador ya se utilizó con datos distintos.'
                );
            }

            $json = [
                'status' => 'ok',
                'answer' => 'La cotización ya estaba guardada.',
                'results' => [
                    'id' => (int)$anterior['id'],
                    'precio_unitario' => $anterior['precio_unitario'],
                    'total_venta' => $anterior['total_venta'],
                    'utilidad' => $anterior['utilidad'],
                    'duplicada' => true
                ],
                'code' => 200
            ];
        } else {
            // 5. OBTENER DATOS REALES DESDE MYSQL
            
$stmt = $this->ejecutarSqlVenta(
    "SELECT
        m.nombre AS material,m.unidad,m.costo_unitario,
        v.nombre AS vehiculo,v.tipo,v.tipo_transporte,
        v.combustible,v.unidad_capacidad,v.capacidad,
        v.rendimiento_km_litro,v.costo_fijo_viaje,
        z.nombre AS destino,z.distancia_ida_km,
        z.casetas_por_viaje,
        l.nombre AS lista,t.recargo_pct
    FROM materiales_costeo m
    INNER JOIN tarifas_material_costeo t
        ON t.material_id=m.id
        AND t.lista_id=?
        AND t.activo=1 AND t.eliminado=0
    INNER JOIN listas_precios_costeo l
        ON l.id=t.lista_id
        AND l.activo=1 AND l.eliminado=0
    INNER JOIN vehiculos_costeo v
        ON v.id=?
        AND v.activo=1 AND v.eliminado=0
    INNER JOIN destinos_costeo z
        ON z.id=?
        AND z.activo=1 AND z.eliminado=0
    WHERE m.id=?
        AND m.activo=1 AND m.eliminado=0
    LIMIT 1",
    "iiii",
    [
        $ids['lista_id'],
        $ids['vehiculo_id'],
        $ids['destino_id'],
        $ids['material_id']
    ]
);


            $cat = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$cat) {
                throw new InvalidArgumentException(
                    'Material, lista, vehículo o destino no disponibles.'
                );
            }


if ($cat['tipo_transporte'] !== 'TERRESTRE') {
    throw new InvalidArgumentException(
        'El cálculo actual está disponible para transporte terrestre.'
    );
}

if ($cat['tipo'] === 'PROPIO' &&
    $cat['combustible'] !== null &&
    $cat['combustible'] !== 'DIESEL') {
    throw new InvalidArgumentException(
        'Este combustible requiere configurar su método de costeo antes de cotizar.'
    );
}


            if ($cat['unidad'] !== $cat['unidad_capacidad']) {
                throw new InvalidArgumentException(
                    'Material y vehículo deben usar la misma unidad de capacidad.'
                );
            }

            // 6. CALCULAR CANTIDAD DE VIAJES
            $cap = $this->enteroCosteo($cat['capacidad'], 2);
            $costo4 = $this->enteroCosteo($cat['costo_unitario'], 4);
            $recargo100 = $this->enteroCosteo($cat['recargo_pct'], 2);

            if ($cap <= 0 || $costo4 > 1000000000 || $recargo100 > 20000) {
                throw new InvalidArgumentException(
                    'Revisa capacidad, costo del material o porcentaje de utilidad.'
                );
            }

            $viajes = intdiv($cantidad + $cap - 1, $cap);

            if ($viajes > 500) {
                throw new InvalidArgumentException(
                    'Se requieren más de 500 viajes; divide la operación.'
                );
            }

            // Importe del material en centavos.
            $costoMaterial = intdiv($costo4 * $cantidad + 5000, 10000);

            // 7. CALCULAR GASTOS LOGÍSTICOS
            $costoFijo = $this->enteroCosteo($cat['costo_fijo_viaje'], 2);
            $casetas = $this->enteroCosteo($cat['casetas_por_viaje'], 2);
            $distancia = $this->enteroCosteo($cat['distancia_ida_km'], 2);
            $rendimiento = $this->enteroCosteo($cat['rendimiento_km_litro'], 2);

            if ($distancia > 500000) {
                throw new InvalidArgumentException(
                    'Distancia superior a 5,000 km por trayecto.'
                );
            }

            $costos = [
                'COMBUSTIBLE' => 0,
                'CASETAS' => 0,
                'CHOFER' => 0,
                'CARGA' => $entradas['carga_por_viaje'] * $viajes,
                'DESCARGA' => $entradas['descarga_por_viaje'] * $viajes,
                'VEHICULO' => 0,
                'FLETE_CONTRATADO' => 0,
                'OTROS' => $entradas['otros_gastos_total']
            ];

            if ($cat['tipo'] === 'PROPIO') {
                if ($rendimiento <= 0 ||
                    $entradas['precio_diesel_litro'] <= 0 ||
                    $distancia <= 0) {
                    throw new InvalidArgumentException(
                        'Configura distancia, rendimiento y precio del combustible para vehículo propio.'
                    );
                }

                if ($entradas['flete_contratado_por_viaje'] > 0) {
                    throw new InvalidArgumentException(
                        'No agregues flete contratado a un vehículo propio.'
                    );
                }

                $numerador = $distancia * 2 * $viajes *
                    $entradas['precio_diesel_litro'];

                $costos['COMBUSTIBLE'] = intdiv(
                    $numerador + intdiv($rendimiento, 2),
                    $rendimiento
                );

                $costos['CASETAS'] = $casetas * $viajes;
                $costos['CHOFER'] = $entradas['chofer_por_viaje'] * $viajes;
                $costos['VEHICULO'] = $costoFijo * $viajes;
            } else {
                if ($entradas['flete_contratado_por_viaje'] <= 0) {
                    throw new InvalidArgumentException(
                        'Indica el costo del flete contratado.'
                    );
                }

                if ($entradas['precio_diesel_litro'] ||
                    $entradas['chofer_por_viaje']) {
                    throw new InvalidArgumentException(
                        'No dupliques chofer ni combustible incluidos en el flete contratado.'
                    );
                }

                $costos['FLETE_CONTRATADO'] =
                    $entradas['flete_contratado_por_viaje'] * $viajes;
            }

            // 8. COSTO TOTAL, PRECIO Y UTILIDAD
            $costoEntrega = array_sum($costos);
            $costoTotal = $costoMaterial + $costoEntrega;

            if ($costoTotal <= 0 || $costoTotal > 9999999999) {
                throw new InvalidArgumentException(
                    'Costo total fuera del rango permitido.'
                );
            }

            // Incremento sobre costo, no margen sobre venta.
            $objetivo = intdiv(
                $costoTotal * (10000 + $recargo100) + 5000,
                10000
            );

            // Redondeo hacia arriba al centavo por unidad.
            $precioUnitario = intdiv(
                $objetivo * 100 + $cantidad - 1,
                $cantidad
            );

            $ventaTotal = intdiv(
                $precioUnitario * $cantidad + 50,
                100
            );

            if ($ventaTotal > 9999999999 ||
                $precioUnitario > 9999999999) {
                throw new InvalidArgumentException(
                    'Precio final fuera del rango permitido.'
                );
            }

            $utilidad = $ventaTotal - $costoTotal;
            $ajuste = $ventaTotal - $objetivo;

            $mxn = static fn(int $centavos): string =>
                number_format($centavos / 100, 2, '.', '');

            $resultado = [
                'material' => $cat['material'],
                'vehiculo' => $cat['vehiculo'],
                'destino' => $cat['destino'],
                'lista' => $cat['lista'],
                'unidad' => $cat['unidad'],
                'cantidad' => number_format($cantidad / 100, 2, '.', ''),
                'viajes' => $viajes,
                'costo_unitario_material' => $cat['costo_unitario'],
                'recargo_porcentaje' => $cat['recargo_pct'],
                'costo_material' => $mxn($costoMaterial),
                'costos_entrega' => $mxn($costoEntrega),
                'costo_total' => $mxn($costoTotal),
                'precio_unitario' => $mxn($precioUnitario),
                'total_venta' => $mxn($ventaTotal),
                'utilidad' => $mxn($utilidad),
                'ajuste_redondeo' => $mxn($ajuste),
                'costos' => array_map($mxn, $costos)
            ];

            // 9. GUARDAR SOLO CUANDO SE SOLICITE
            if ($guardar) {
                $valores = [
                    $solicitud,
                    $huella,
                    $ids['material_id'],
                    $ids['vehiculo_id'],
                    $ids['destino_id'],
                    $ids['lista_id'],
                    $cliente,
                    $cat['material'],
                    $cat['vehiculo'],
                    $cat['destino'],
                    $cat['lista'],
                    $cat['unidad'],
                    $resultado['cantidad'],
                    $viajes,
                    $cat['costo_unitario'],
                    $cat['recargo_pct'],
                    $resultado['costo_material'],
                    $resultado['costos_entrega'],
                    $resultado['costo_total'],
                    $resultado['precio_unitario'],
                    $resultado['total_venta'],
                    $resultado['utilidad'],
                    $resultado['ajuste_redondeo']
                ];

                $columnas = [
                    'solicitud_id','huella','material_id',
                    'vehiculo_id','destino_id','lista_id','cliente',
                    'nombre_material','nombre_vehiculo','nombre_destino',
                    'nombre_lista','unidad','cantidad','viajes',
                    'costo_unitario_material','recargo_porcentaje',
                    'costo_material','costos_entrega','costo_total',
                    'precio_unitario','total_venta','utilidad',
                    'ajuste_redondeo'
                ];

                $sql = 'INSERT INTO cotizaciones_costeo (`' .
                    implode('`,`', $columnas) . '`) VALUES (' .
                    implode(',', array_fill(0, count($columnas), '?')) .
                    ') ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)';

                $stmt = $this->ejecutarSqlVenta(
                    $sql,
                    str_repeat('s', count($valores)),
                    $valores
                );

                $nuevo = $stmt->affected_rows === 1;
                $cotizacionId = (int)$this->conn->insert_id;
                $stmt->close();

                if (!$nuevo) {
                    $stmt = $this->ejecutarSqlVenta(
                        'SELECT id,huella,precio_unitario,total_venta,utilidad
                         FROM cotizaciones_costeo WHERE id=? LIMIT 1',
                        'i',
                        [$cotizacionId]
                    );

                    $anterior = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    if (!$anterior ||
                        !hash_equals($anterior['huella'], $huella)) {
                        throw new InvalidArgumentException(
                            'Identificador reutilizado con datos distintos.'
                        );
                    }

                    $resultado = [
                        'id' => $cotizacionId,
                        'precio_unitario' => $anterior['precio_unitario'],
                        'total_venta' => $anterior['total_venta'],
                        'utilidad' => $anterior['utilidad'],
                        'duplicada' => true
                    ];
                } else {
                    $stmt = $this->conn->prepare(
                        'INSERT INTO cotizaciones_costos
                         (cotizacion_id,concepto,importe)
                         VALUES (?,?,?)'
                    );

                    if (!$stmt) {
                        throw new RuntimeException($this->conn->error);
                    }

                    $concepto = '';
                    $importe = '0.00';

                    $stmt->bind_param(
                        'iss',
                        $cotizacionId,
                        $concepto,
                        $importe
                    );

                    foreach ($costos as $nombre => $valor) {
                        if ($valor === 0) continue;

                        $concepto = $nombre;
                        $importe = $mxn($valor);
                        $stmt->execute();
                    }

                    $stmt->close();

                    $resultado['id'] = $cotizacionId;
                    $resultado['duplicada'] = false;
                }
            }

            $json = [
                'status' => 'ok',
                'answer' => $guardar
                    ? 'Cotización guardada.'
                    : 'Costeo calculado.',
                'results' => $resultado,
                'code' => 200
            ];
        }

        if ($transaccion) {
            $this->conn->commit();
            $transaccion = false;
        }

    } catch (DomainException $e) {
        if ($transaccion) $this->conn->rollback();

        $json = [
            'status' => 'warning',
            'answer' => $e->getMessage(),
            'code' => 403
        ];

    } catch (InvalidArgumentException | JsonException $e) {
        if ($transaccion) $this->conn->rollback();

        $json = [
            'status' => 'warning',
            'answer' => $e instanceof JsonException
                ? 'JSON de cotización inválido.'
                : $e->getMessage(),
            'code' => 400
        ];

    } catch (Throwable $e) {
        if ($transaccion) $this->conn->rollback();

        

        $json = [
            'status' => 'error',
            'answer' => 'No fue posible calcular o guardar la cotización.',
            'code' => 500
        ];
    }

    return $json;
}


public function guardarCatalogoCosteo(array $data): array {
    $json = [];

    try {
        if (!$this->conn) {
            throw new RuntimeException('Conexión no disponible.');
        }

        // PERMISOS DE ADMINISTRACIÓN
        $stmt = $this->ejecutarSqlVenta(
            'SELECT role_id FROM users WHERE id=? AND is_active=1 LIMIT 1',
            'i',
            [$this->usuarioOperador]
        );

        $usuario = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$usuario || (int)$usuario['role_id'] !== 1) {
            throw new DomainException(
                'No tienes permiso para modificar los catálogos.'
            );
        }

        $tipo = $data['tipo'] ?? '';

        $tablas = [
            'material' => 'materiales_costeo',
            'vehiculo' => 'vehiculos_costeo',
            'destino' => 'destinos_costeo',
            'lista' => 'listas_precios_costeo'
        ];

        if (!is_string($tipo) ||
            (!isset($tablas[$tipo]) && $tipo !== 'tarifa')) {
            throw new InvalidArgumentException('Tipo de catálogo inválido.');
        }

        $id = filter_var(
            $data['id'] ?? 0,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 0]]
        );

        if ($id === false) {
            throw new InvalidArgumentException('Identificador inválido.');
        }

        $leer = static function(string $campo, int $maximo = 150) use ($data): string {
            $valor = $data[$campo] ?? '';

            if (!is_string($valor) && !is_int($valor)) {
                throw new InvalidArgumentException("Campo {$campo} inválido.");
            }

            $valor = trim((string)$valor);

            if ($valor === '' || strlen($valor) > $maximo ||
                preg_match('/[\x00-\x1F]/', $valor)) {
                throw new InvalidArgumentException(
                    "Revisa el campo {$campo}."
                );
            }

            return $valor;
        };

        $decimal = static function(int $valor, int $decimales): string {
            $escala = 10 ** $decimales;

            return sprintf(
                '%d.%0' . $decimales . 'd',
                intdiv($valor, $escala),
                $valor % $escala
            );
        };

        $campos = [];
        $valores = [];

        switch ($tipo) {
            case 'material':
                $codigo = strtoupper($leer('codigo', 50));
                $nombre = $leer('nombre');
                $unidad = strtoupper($leer('unidad', 12));
                $costo = $this->enteroCosteo($data['costo_unitario'] ?? '', 4);

                if (!preg_match('/^[A-Z0-9_-]{2,50}$/D', $codigo) ||
                    !preg_match('/^[A-Z0-9_]{1,12}$/D', $unidad)) {
                    throw new InvalidArgumentException(
                        'Código o unidad de medida inválidos.'
                    );
                }

                $campos = ['codigo','nombre','unidad','costo_unitario'];
                $valores = [
                    $codigo,$nombre,$unidad,$decimal($costo, 4)
                ];
                break;

            
case 'vehiculo':
    $nombre = $leer('nombre');
    $tipoVehiculo = strtoupper($leer('tipo_vehiculo', 15));
    $unidad = strtoupper($leer('unidad_capacidad', 12));

    if (!in_array($tipoVehiculo, ['PROPIO', 'CONTRATADO'], true) ||
        !in_array($unidad, ['PZA', 'BULTO', 'M3', 'TON', 'KG', 'VIAJE'], true)) {
        throw new InvalidArgumentException(
            'Tipo de vehículo o unidad de capacidad inválidos.'
        );
    }

    $capacidad = $this->enteroCosteo($data['capacidad'] ?? '', 2);
    $rendimiento = $this->enteroCosteo($data['rendimiento_km_litro'] ?? '0', 2);
    $costoFijo = $this->enteroCosteo($data['costo_fijo_viaje'] ?? '0', 2);

    if ($capacidad <= 0 ||
        ($tipoVehiculo === 'PROPIO' && $rendimiento <= 0)) {
        throw new InvalidArgumentException(
            'Configura una capacidad válida y el rendimiento del vehículo propio.'
        );
    }

    $campos = [
        'nombre', 'tipo', 'unidad_capacidad', 'capacidad',
        'rendimiento_km_litro', 'costo_fijo_viaje'
    ];

    $valores = [
        $nombre, $tipoVehiculo, $unidad,
        $decimal($capacidad, 2),
        $decimal($rendimiento, 2),
        $decimal($costoFijo, 2)
    ];

    $configuraciones = [
        'Motocicleta', 'VL', 'C2', 'C3', 'C2R2', 'C3R2',
        'C2R3', 'C3R3', 'T2S1', 'T2S2', 'T2S3',
        'T3S1', 'T3S2', 'T3S3', 'T2S1R2', 'T2S2R2',
        'T2S1R3', 'T3S1R2', 'T3S1R3', 'T3S2R2',
        'T3S2R3', 'T3S2R4', 'T2S2S2', 'T3S2S2',
        'T3S3S2', 'OTROEVGP', 'OTROSG', 'GPLUTA',
        'GPLUTB', 'GPLUTC', 'GPLUTD', 'GPLATA',
        'GPLATB', 'GPLATC', 'GPLATD'
    ];

    $adicionales = [
        'vin', 'tipo_transporte', 'configuracion_vehicular',
        'peso_bruto_kg', 'carga_util_kg',
        'tanque_litros', 'combustible'
    ];

    $extras = [];

    foreach ($adicionales as $campo) {
        if (!array_key_exists($campo, $data)) continue;

        if (!is_string($data[$campo]) && !is_int($data[$campo])) {
            throw new InvalidArgumentException("Dato inválido: {$campo}.");
        }

        $valor = trim((string)$data[$campo]);
        $valor = $valor === '' ? null : $valor;

        if ($campo === 'vin' && $valor !== null) {
            $valor = strtoupper($valor);

            if (!preg_match('/^[A-HJ-NPR-Z0-9]{17}$/D', $valor)) {
                throw new InvalidArgumentException(
                    'El VIN debe tener 17 caracteres válidos, sin I, O ni Q.'
                );
            }
        }

        
if ($campo === 'tipo_transporte') {
    if ($valor === null && $id > 0) continue;

    $valor = $valor === null ? 'TERRESTRE' : strtoupper($valor);

    if (!in_array($valor, ['TERRESTRE', 'MARITIMO', 'AEREO'], true)) {
        throw new InvalidArgumentException(
            'Selecciona un tipo de transporte válido.'
        );
    }
}


        if ($campo === 'configuracion_vehicular' && $valor !== null) {
            $valor = strtoupper($valor);
            if ($valor === 'MOTOCICLETA') $valor = 'Motocicleta';

            if (!in_array($valor, $configuraciones, true)) {
                throw new InvalidArgumentException(
                    'Configuración vehicular no permitida.'
                );
            }
        }

        if ($campo === 'combustible' && $valor !== null) {
            $valor = strtoupper($valor);

            if (!in_array($valor, [
                'GASOLINA', 'DIESEL', 'GAS_LP', 'ELECTRICO', 'OTRO'
            ], true)) {
                throw new InvalidArgumentException(
                    'Tipo de combustible inválido.'
                );
            }
        }

        if (in_array($campo, [
            'peso_bruto_kg', 'carga_util_kg', 'tanque_litros'
        ], true) && $valor !== null) {
            $numero = $this->enteroCosteo($valor, 2);

            if ($numero <= 0) {
                throw new InvalidArgumentException(
                    "El campo {$campo} debe ser mayor que cero."
                );
            }

            $valor = $decimal($numero, 2);
        }

        $campos[] = $campo;
        $valores[] = $valor;
        $extras[$campo] = $valor;
    }

    
$anteriores = [];

if ($id > 0 && (
    array_key_exists('peso_bruto_kg', $extras) ||
    array_key_exists('carga_util_kg', $extras)
)) {
    $stmt = $this->ejecutarSqlVenta(
        "SELECT peso_bruto_kg,carga_util_kg
         FROM vehiculos_costeo
         WHERE id=? AND eliminado=0 LIMIT 1",
        'i',
        [$id]
    );

    $anteriores = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();
}

$pesoBruto = array_key_exists('peso_bruto_kg', $extras)
    ? $extras['peso_bruto_kg']
    : ($anteriores['peso_bruto_kg'] ?? null);

$cargaUtil = array_key_exists('carga_util_kg', $extras)
    ? $extras['carga_util_kg']
    : ($anteriores['carga_util_kg'] ?? null);

if ($pesoBruto !== null && $cargaUtil !== null &&
    $this->enteroCosteo($cargaUtil, 2) >
    $this->enteroCosteo($pesoBruto, 2)) {
    throw new InvalidArgumentException(
        'La carga útil no puede superar el peso bruto del vehículo.'
    );
}


    break;


                        case 'destino':
                $nombre = $leer('nombre');
                $distancia = $this->enteroCosteo($data['distancia_ida_km'] ?? '', 2);
                $casetas = $this->enteroCosteo($data['casetas_por_viaje'] ?? '0', 2);

                if ($distancia > 500000) {
                    throw new InvalidArgumentException('La distancia no puede superar 5,000 km.');
                }

                $campos = ['nombre', 'distancia_ida_km', 'casetas_por_viaje'];
                $valores = [$nombre, $decimal($distancia, 2), $decimal($casetas, 2)];

                $opcionales = [
                    'calle' => 150,
                    'numero_exterior' => 20,
                    'numero_interior' => 20,
                    'colonia' => 120,
                    'municipio' => 120,
                    'estado' => 100,
                    'codigo_postal' => 10,
                    'referencias' => 255
                ];

                foreach ($opcionales as $campo => $maximo) {
                    $valor = $data[$campo] ?? '';

                    if (!is_string($valor) && !is_numeric($valor)) {
                        throw new InvalidArgumentException("Dato inválido: {$campo}.");
                    }

                    $valor = trim((string)$valor);

                    if (strlen($valor) > $maximo || preg_match('/[\x00-\x1F\x7F]/', $valor)) {
                        throw new InvalidArgumentException("Revisa el campo {$campo}.");
                    }

                    $campos[] = $campo;
                    $valores[] = $valor === '' ? null : $valor;
                }

                $latitud = trim((string)($data['latitud'] ?? ''));
                $longitud = trim((string)($data['longitud'] ?? ''));

                if (($latitud === '') !== ($longitud === '')) {
                    throw new InvalidArgumentException(
                        'Captura ambas coordenadas o deja las dos vacías.'
                    );
                }

                if ($latitud !== '') {
                    $patron = '/^-?\d{1,3}(?:\.\d{1,7})?$/D';

                    if (!preg_match($patron, $latitud) ||
                        !preg_match($patron, $longitud) ||
                        (float)$latitud < -90 || (float)$latitud > 90 ||
                        (float)$longitud < -180 || (float)$longitud > 180) {
                        throw new InvalidArgumentException(
                            'Las coordenadas geográficas no son válidas.'
                        );
                    }
                }

                $campos[] = 'latitud';
                $campos[] = 'longitud';
                $valores[] = $latitud === '' ? null : $latitud;
                $valores[] = $longitud === '' ? null : $longitud;
                break;

            case 'lista':
                $campos = ['nombre'];
                $valores = [$leer('nombre', 100)];
                break;

            case 'tarifa':
                $materialId = filter_var(
                    $data['material_id'] ?? null,
                    FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 1]]
                );
                $listaId = filter_var(
                    $data['lista_id'] ?? null,
                    FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 1]]
                );

                if (!$materialId || !$listaId) {
                    throw new InvalidArgumentException(
                        'Selecciona material y lista de precios.'
                    );
                }

                $recargo = $this->enteroCosteo(
                    $data['recargo_pct'] ?? '', 2
                );

                if ($recargo > 20000) {
                    throw new InvalidArgumentException(
                        'El incremento no puede superar el 200%.'
                    );
                }

                $stmt = $this->ejecutarSqlVenta(
                    'SELECT m.id
                     FROM materiales_costeo m
                     JOIN listas_precios_costeo l ON l.id=?
                     WHERE m.id=? AND m.activo=1 AND m.eliminado=0
                     AND l.activo=1 AND l.eliminado=0 LIMIT 1',
                    'ii',
                    [$listaId, $materialId]
                );

                $existen = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$existen) {
                    throw new InvalidArgumentException(
                        'Material o lista de precios no disponibles.'
                    );
                }


$stmt = $this->ejecutarSqlVenta(
    "SELECT eliminado FROM tarifas_material_costeo
     WHERE material_id=? AND lista_id=? LIMIT 1",
    "ii",
    [$materialId, $listaId]
);

$tarifaExistente = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($tarifaExistente && (int)$tarifaExistente['eliminado'] === 1) {
    throw new InvalidArgumentException(
        'Esta tarifa fue eliminada. Debes restaurarla antes de modificarla.'
    );
}


                $sql = 'INSERT INTO tarifas_material_costeo
                    (material_id,lista_id,recargo_pct,activo)
                    VALUES (?,?,?,1)
                    ON DUPLICATE KEY UPDATE
                    recargo_pct=VALUES(recargo_pct),activo=1';

                $stmt = $this->ejecutarSqlVenta(
                    $sql,
                    'iis',
                    [$materialId, $listaId, $decimal($recargo, 2)]
                );

                $stmt->close();

                $json = [
                    'status' => 'ok',
                    'answer' => 'Tarifa guardada correctamente.',
                    'results' => [
                        'material_id' => $materialId,
                        'lista_id' => $listaId
                    ],
                    'code' => 200
                ];
                break;
        }

        if ($tipo !== 'tarifa') {
            $tabla = $tablas[$tipo];

            if ($id > 0) {
                $stmt = $this->ejecutarSqlVenta(
                    "SELECT id FROM {$tabla} WHERE id = ? AND eliminado = 0 LIMIT 1",
                    'i',
                    [$id]
                );

                $existe = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$existe) {
                    throw new InvalidArgumentException(
                        'El registro que intentas editar no existe.'
                    );
                }

                $asignaciones = implode(',', array_map(
                    static fn($col) => "`{$col}`=?",
                    $campos
                ));

                $sql = "UPDATE {$tabla} SET {$asignaciones} WHERE id = ? AND eliminado = 0";
                $valores[] = $id;
            } else {
                $columnas = '`' . implode('`,`', $campos) . '`,`activo`';

                $sql = "INSERT INTO {$tabla} ({$columnas}) VALUES (" .
                    implode(',', array_fill(0, count($campos), '?')) .
                    ',1)';
            }

            $stmt = $this->ejecutarSqlVenta(
                $sql,
                str_repeat('s', count($valores)),
                $valores
            );

            $registroId = $id > 0 ? $id : (int)$this->conn->insert_id;
            $stmt->close();

            $json = [
                'status' => 'ok',
                'answer' => $id > 0
                    ? 'Registro actualizado correctamente.'
                    : 'Registro creado correctamente.',
                'results' => [
                    'id' => $registroId,
                    'tipo' => $tipo
                ],
                'code' => 200
            ];
        }

    } catch (DomainException $e) {
        $json = [
            'status' => 'warning',
            'answer' => $e->getMessage(),
            'code' => 403
        ];

    } catch (InvalidArgumentException $e) {
        $json = [
            'status' => 'warning',
            'answer' => $e->getMessage(),
            'code' => 400
        ];

    } catch (mysqli_sql_exception $e) {
        

        $json = [
            'status' => 'error',
            'answer' => $e->getCode() == 1062
                ? 'Ya existe un registro con esos datos.'
                : 'No fue posible guardar el catálogo.',
            'code' => $e->getCode() == 1062 ? 409 : 500
        ];

    } catch (Throwable $e) {
        

        $json = [
            'status' => 'error',
            'answer' => 'Ocurrió un error al guardar el catálogo.',
            'code' => 500
        ];
    }

    return $json;
}

public function cambiarEstadoCatalogoCosteo(array $data): array {
    $json = [];
    $transaccion = false;

    try {
        if (!$this->conn) {
            throw new RuntimeException('Conexión no disponible.');
        }

        $stmt = $this->ejecutarSqlVenta(
            "SELECT role_id FROM users
             WHERE id=? AND is_active=1 LIMIT 1",
            "i",
            [$this->usuarioOperador]
        );
        $usuario = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$usuario || (int)$usuario['role_id'] !== 1) {
            throw new DomainException('No tienes permisos para esta operación.');
        }

        $tablas = [
            'material' => 'materiales_costeo',
            'vehiculo' => 'vehiculos_costeo',
            'destino' => 'destinos_costeo',
            'lista' => 'listas_precios_costeo',
            'tarifa' => 'tarifas_material_costeo'
        ];

        $tipo = $data['tipo'] ?? '';
        $accion = $data['operacion'] ?? '';

        if (!is_string($tipo) || !isset($tablas[$tipo])) {
            throw new InvalidArgumentException('Catálogo inválido.');
        }

        if (!in_array($accion, ['eliminar', 'restaurar'], true)) {
            throw new InvalidArgumentException('Operación no permitida.');
        }

        $tabla = $tablas[$tipo];
        $eliminado = $accion === 'eliminar' ? 1 : 0;

        if ($tipo === 'tarifa') {
            $materialId = filter_var(
                $data['material_id'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );
            $listaId = filter_var(
                $data['lista_id'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            if (!$materialId || !$listaId) {
                throw new InvalidArgumentException('Tarifa inválida.');
            }

            $condicion = 'material_id=? AND lista_id=?';
            $tipos = 'ii';
            $valores = [$materialId, $listaId];
        } else {
            $id = filter_var(
                $data['id'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            if (!$id) {
                throw new InvalidArgumentException('ID inválido.');
            }

            $condicion = 'id=?';
            $tipos = 'i';
            $valores = [$id];
        }

        $this->conn->begin_transaction();
        $transaccion = true;

        $stmt = $this->ejecutarSqlVenta(
            "SELECT eliminado FROM {$tabla}
             WHERE {$condicion} LIMIT 1 FOR UPDATE",
            $tipos,
            $valores
        );

        $registro = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$registro) {
            throw new InvalidArgumentException('El registro no existe.');
        }

        $cambio = (int)$registro['eliminado'] !== $eliminado;

        if ($cambio) {
            $stmt = $this->ejecutarSqlVenta(
                "UPDATE {$tabla} SET eliminado=?
                 WHERE {$condicion}",
                'i' . $tipos,
                array_merge([$eliminado], $valores)
            );
            $stmt->close();
        }

        $this->conn->commit();
        $transaccion = false;

        $json['status'] = 'ok';
        $json['answer'] = $eliminado
            ? 'Registro eliminado correctamente.'
            : 'Registro restaurado correctamente.';
        $json['results'] = [
            'tipo' => $tipo,
            'eliminado' => $eliminado,
            'actualizado' => $cambio
        ];
        $json['code'] = 200;

    } catch (DomainException $e) {
        if ($transaccion) $this->conn->rollback();
        $json = ['status'=>'warning','answer'=>$e->getMessage(),'code'=>403];

    } catch (InvalidArgumentException $e) {
        if ($transaccion) $this->conn->rollback();
        $json = ['status'=>'warning','answer'=>$e->getMessage(),'code'=>400];

    } catch (Throwable $e) {
        if ($transaccion) $this->conn->rollback();
       
        $json = [
            'status'=>'error',
            'answer'=>'No fue posible actualizar el estado del registro.',
            'code'=>500
        ];
    }

    return $json;
}

    private function corpClientes(): string {
        $corp = isset($_SESSION['csm_corp_id'])
            ? trim((string)$_SESSION['csm_corp_id']) : '1';

        if ($corp === '' || strlen($corp) > 150) {
            throw new RuntimeException('Corporativo no válido.');
        }

        return $corp;
    }

    private function exigirAdminClientes(): void {
        $stmt = $this->ejecutarSqlVenta(
            'SELECT role_id FROM users WHERE id=? AND is_active=1 LIMIT 1',
            'i', [$this->usuarioOperador]
        );

        $usuario = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$usuario || (int)$usuario['role_id'] !== 1) {
            throw new DomainException('Esta operación requiere permisos de administrador.');
        }
    }

    public function getClientes(array $data = []): array {
        try {
            if (!$this->conn) throw new RuntimeException('Sin conexión.');

            
$stmt = $this->ejecutarSqlVenta(
    "SELECT
        corp_id,
        id,
        nombre,
        apellido,
        razon_social,
        rfc,
        alias,
        correo,
        correo_empresarial,
        numero,
        movil,
        domicilio,
        calle,
        numext,
        numint,
        colonia,
        municipio,
        cp,
        estado,
        pais,
        latitud,
        longitud,
        tipo,
        grupo_id,
        tipo_precio_id,
        limite_credito,
        notas,
        formAlta,
        fechaAlta,
        fechaEditado
     FROM clientes
     WHERE corp_id = ?
       AND eliminado = 0
     ORDER BY nombre ASC, apellido ASC, id ASC",
    "s",
    [$this->corpClientes()]
);


            $clientes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            return [
                'status'=>'ok',
                'code'=>200,
                'answer'=>'Clientes consultados.',
                'results'=>$clientes
            ];
        } catch (Throwable $e) {
            
            return [
                'status'=>'error',
                'code'=>500,
                'answer'=>'No fue posible consultar clientes.'
            ];
        }
    }

    public function guardarCliente(array $data = []): array {
        try {
            if (!$this->conn) throw new RuntimeException('Sin conexión.');

            $this->exigirAdminClientes();
            $corp = $this->corpClientes();

            $id = filter_var(
                $data['id'] ?? 0,
                FILTER_VALIDATE_INT,
                ['options'=>['min_range'=>0]]
            );

            if ($id === false) {
                throw new InvalidArgumentException('ID inválido.');
            }

            
$longitudes = [
    'nombre' => 100,
    'apellido' => 150,
    'razon_social' => 200,
    'rfc' => 13,
    'alias' => 150,
    'correo' => 190,
    'correo_empresarial' => 190,
    'numero' => 30,
    'movil' => 30,
    'domicilio' => 255,
    'calle' => 150,
    'numext' => 20,
    'numint' => 20,
    'colonia' => 150,
    'municipio' => 120,
    'cp' => 10,
    'estado' => 100,
    'pais' => 100
];


            $valores = [];

            foreach ($longitudes as $campo=>$maximo) {
                $texto = $this->textoVenta($data, $campo, $maximo);
                $valores[$campo] = $texto === '' ? null : $texto;
            }


$notas = $this->textoVenta($data, 'notas', 3000);
$valores['notas'] = $notas === '' ? null : $notas;

if ($valores['rfc'] !== null) {
    $rfc = strtoupper($valores['rfc']);
    $rfc = str_replace('ñ', 'Ñ', $rfc);

    if (!preg_match(
        '/^[A-ZÑ&]{3,4}[0-9]{6}[A-Z0-9]{3}$/u',
        $rfc
    )) {
        throw new InvalidArgumentException(
            'El RFC debe contener 12 o 13 caracteres válidos.'
        );
    }

    $valores['rfc'] = $rfc;
}

if (
    $valores['correo_empresarial'] !== null &&
    !filter_var(
        $valores['correo_empresarial'],
        FILTER_VALIDATE_EMAIL
    )
) {
    throw new InvalidArgumentException(
        'El correo empresarial no es válido.'
    );
}


            if ($valores['nombre'] === null) {
                throw new InvalidArgumentException(
                    'Escribe el nombre del cliente.'
                );
            }

            if ($valores['correo'] !== null &&
                !filter_var($valores['correo'], FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('El correo no es válido.');
            }

            $tipo = (string)($data['tipo'] ?? '1');

            if (!in_array($tipo, ['0','1'], true)) {
                throw new InvalidArgumentException(
                    'Tipo de cliente inválido.'
                );
            }

            $valores['tipo'] = $tipo;

            $credito = trim((string)($data['limite_credito'] ?? '0'));

            if ($credito === '') $credito = '0';

            if (!preg_match(
                '/^\d{1,13}(?:\.\d{1,2})?$/D',
                $credito
            )) {
                throw new InvalidArgumentException(
                    'Límite de crédito inválido.'
                );
            }

            $valores['limite_credito'] = $credito;


if (
    isset($data['grupo_id']) &&
    !is_scalar($data['grupo_id'])
) {
    throw new InvalidArgumentException(
        'El grupo seleccionado no es válido.'
    );
}

$grupo = trim((string)($data['grupo_id'] ?? ''));

$valores['grupo_id'] = null;

if ($grupo !== '') {
    if (!ctype_digit($grupo) || (int)$grupo <= 0) {
        throw new InvalidArgumentException(
            'Selecciona un grupo válido.'
        );
    }

    $grupoId = (int)$grupo;

    $stmt = $this->ejecutarSqlVenta(
        "SELECT id
         FROM clientes_grupos
         WHERE corp_id = ?
           AND id = ?
           AND eliminado = 0
         LIMIT 1",
        "si",
        [$corp, $grupoId]
    );

    $grupoExiste = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$grupoExiste) {
        throw new InvalidArgumentException(
            'El grupo seleccionado no existe o fue eliminado.'
        );
    }

    $valores['grupo_id'] = $grupoId;
}

if (
    isset($data['tipo_precio_id']) &&
    !is_scalar($data['tipo_precio_id'])
) {
    throw new InvalidArgumentException(
        'El tipo de precio no es válido.'
    );
}

$tipoPrecio = trim(
    (string)($data['tipo_precio_id'] ?? '1')
);

if ($tipoPrecio === '') {
    $tipoPrecio = '1';
}

if (!preg_match('/^(10|[1-9])$/D', $tipoPrecio)) {
    throw new InvalidArgumentException(
        'El tipo de precio debe estar entre 1 y 10.'
    );
}

$valores['tipo_precio_id'] = (int)$tipoPrecio;


            $coordenadas = [];

            foreach (['latitud'=>90, 'longitud'=>180] as $campo=>$limite) {
                $valor = trim((string)($data[$campo] ?? ''));

                if ($valor === '') {
                    $coordenadas[$campo] = null;
                } elseif (
                    !is_numeric($valor) ||
                    !is_finite((float)$valor) ||
                    abs((float)$valor) > $limite
                ) {
                    throw new InvalidArgumentException(
                        'Coordenadas inválidas.'
                    );
                } else {
                    $coordenadas[$campo] = number_format(
                        (float)$valor, 8, '.', ''
                    );
                }
            }

            if (
                ($coordenadas['latitud'] === null) !==
                ($coordenadas['longitud'] === null)
            ) {
                throw new InvalidArgumentException(
                    'Indica ambas coordenadas o ninguna.'
                );
            }

            $valores = array_merge($valores, $coordenadas);

            $nombreCompleto = trim(
                $valores['nombre'] . ' ' . ($valores['apellido'] ?? '')
            );

            $stmt = $this->ejecutarSqlVenta(
                "SELECT id FROM clientes
                 WHERE corp_id=? AND eliminado=0 AND id<>?
                   AND TRIM(CONCAT_WS(' ',nombre,apellido))
                       COLLATE utf8mb4_general_ci
                       = ? COLLATE utf8mb4_general_ci
                 LIMIT 1",
                'sis', [$corp, $id, $nombreCompleto]
            );

            $duplicado = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($duplicado) {
                return [
                    'status'=>'warning',
                    'code'=>409,
                    'answer'=>'Ya existe un cliente activo con ese nombre completo.'
                ];
            }

            $columnas = array_keys($valores);
            $parametros = array_values($valores);

            if ($id === 0) {
                $sql = 'INSERT INTO clientes (`corp_id`,`formAlta`,`' .
                    implode('`,`', $columnas) .
                    '`) VALUES (?,\'ORGANICO\',' .
                    implode(',', array_fill(0, count($columnas), '?')) .
                    ')';

                $stmt = $this->ejecutarSqlVenta(
                    $sql,
                    str_repeat('s', count($columnas) + 1),
                    array_merge([$corp], $parametros)
                );

                $stmt->close();
                $id = (int)$this->conn->insert_id;
            } else {
                $stmt = $this->ejecutarSqlVenta(
                    'SELECT id FROM clientes
                     WHERE corp_id=? AND id=? AND eliminado=0 LIMIT 1',
                    'si', [$corp, $id]
                );

                $existe = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$existe) {
                    return [
                        'status'=>'warning',
                        'code'=>404,
                        'answer'=>'El cliente ya no está disponible.'
                    ];
                }

                $asignaciones = [];

                foreach ($columnas as $columna) {
                    $asignaciones[] = '`' . $columna . '`=?';
                }

                $sql = 'UPDATE clientes SET ' .
    implode(', ', $asignaciones) .
    ', fechaEditado=NOW()
     WHERE corp_id=? AND id=? AND eliminado=0';

                $stmt = $this->ejecutarSqlVenta(
                    $sql,
                    str_repeat('s', count($columnas)) . 'si',
                    array_merge($parametros, [$corp, $id])
                );

                $stmt->close();
            }

            return [
                'status'=>'ok',
                'code'=>200,
                'answer'=>'Cliente guardado correctamente.',
                'results'=>['id'=>$id]
            ];
        } catch (DomainException $e) {
            return [
                'status'=>'warning',
                'code'=>403,
                'answer'=>$e->getMessage()
            ];
        } catch (InvalidArgumentException $e) {
            return [
                'status'=>'warning',
                'code'=>400,
                'answer'=>$e->getMessage()
            ];
        } catch (Throwable $e) {
            
            return [
                'status'=>'error',
                'code'=>500,
                'answer'=>'No fue posible guardar el cliente.'
            ];
        }
    }

    public function eliminarCliente(array $data = []): array {
        try {
            if (!$this->conn) throw new RuntimeException('Sin conexión.');

            $this->exigirAdminClientes();

            $id = filter_var(
                $data['id'] ?? 0,
                FILTER_VALIDATE_INT,
                ['options'=>['min_range'=>1]]
            );

            if ($id === false) {
                throw new InvalidArgumentException('ID inválido.');
            }

            
$stmt = $this->ejecutarSqlVenta(
    "UPDATE clientes
     SET eliminado = 1,
         fechaEliminado = NOW(),
         fechaEditado = NOW()
     WHERE corp_id = ?
       AND id = ?
       AND eliminado = 0",
    "si",
    [$this->corpClientes(), $id]
);


            $cambiados = $stmt->affected_rows;
            $stmt->close();

            if ($cambiados === 0) {
                return [
                    'status'=>'warning',
                    'code'=>404,
                    'answer'=>'El cliente ya no está disponible.'
                ];
            }

            return [
                'status'=>'ok',
                'code'=>200,
                'answer'=>'Cliente eliminado del catálogo.'
            ];
        } catch (DomainException $e) {
            return [
                'status'=>'warning',
                'code'=>403,
                'answer'=>$e->getMessage()
            ];
        } catch (InvalidArgumentException $e) {
            return [
                'status'=>'warning',
                'code'=>400,
                'answer'=>$e->getMessage()
            ];
        } catch (Throwable $e) {
            
            return [
                'status'=>'error',
                'code'=>500,
                'answer'=>'No fue posible eliminar el cliente.'
            ];
        }
    }


    public function getCatalogosClientes(): array {
        try {
            if (!$this->conn) {
                throw new RuntimeException('Sin conexión con MySQL.');
            }

            $corp = $this->corpClientes();

            $stmt = $this->ejecutarSqlVenta(
                "SELECT id, nombre
                 FROM clientes_grupos
                 WHERE corp_id = ?
                   AND eliminado = 0
                 ORDER BY nombre ASC, id ASC",
                "s",
                [$corp]
            );

            $grupos = $stmt->get_result()
                ->fetch_all(MYSQLI_ASSOC);

            $stmt->close();

            $precios = [];

            for ($i = 1; $i <= 10; $i++) {
                $precios[] = [
                    'id' => $i,
                    'nombre' => 'Tipo de precio ' . $i
                ];
            }

            return [
                'status' => 'ok',
                'code' => 200,
                'answer' => 'Catálogos consultados correctamente.',
                'results' => [
                    'grupos' => $grupos,
                    'precios' => $precios
                ]
            ];

        } catch (Throwable $e) {
           

            return [
                'status' => 'error',
                'code' => 500,
                'answer' => 'No fue posible consultar los catálogos.'
            ];
        }
    }


    public function guardarCatalogoCliente(array $data = []): array {
        try {
            if (!$this->conn) {
                throw new RuntimeException('Sin conexión con MySQL.');
            }

            $this->exigirAdminClientes();

            $tipo = $this->textoVenta(
                $data,
                'tipo_catalogo',
                20
            );

            if ($tipo !== 'grupo') {
                throw new InvalidArgumentException(
                    'Solo se permite crear grupos de clientes.'
                );
            }

            $nombre = $this->textoVenta(
                $data,
                'nombre',
                80
            );

            if ($nombre === '') {
                throw new InvalidArgumentException(
                    'Escribe el nombre del grupo.'
                );
            }

            $corp = $this->corpClientes();

            $stmt = $this->ejecutarSqlVenta(
                "SELECT id
                 FROM clientes_grupos
                 WHERE corp_id = ?
                   AND nombre = ?
                   AND eliminado = 0
                 LIMIT 1",
                "ss",
                [$corp, $nombre]
            );

            $existe = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($existe) {
                return [
                    'status' => 'warning',
                    'code' => 409,
                    'answer' => 'Ya existe un grupo con ese nombre.'
                ];
            }

            $stmt = $this->ejecutarSqlVenta(
                "INSERT INTO clientes_grupos (
                    corp_id,
                    nombre,
                    eliminado,
                    fechaAlta
                ) VALUES (?, ?, 0, NOW())",
                "ss",
                [$corp, $nombre]
            );

            $stmt->close();

            $id = (int)$this->conn->insert_id;

            return [
                'status' => 'ok',
                'code' => 200,
                'answer' => 'Grupo creado correctamente.',
                'results' => [
                    'id' => $id,
                    'nombre' => $nombre
                ]
            ];

        } catch (DomainException $e) {
            return [
                'status' => 'warning',
                'code' => 403,
                'answer' => $e->getMessage()
            ];

        } catch (InvalidArgumentException $e) {
            return [
                'status' => 'warning',
                'code' => 400,
                'answer' => $e->getMessage()
            ];

        } catch (Throwable $e) {
          

            return [
                'status' => 'error',
                'code' => 500,
                'answer' => 'No fue posible crear el grupo.'
            ];
        }
    }



public function getColaboradores(array $data = []): array {
    $json = [];

    try {
        if (!$this->conn) throw new RuntimeException('No hay conexión con MySQL.');
        $this->exigirAdminClientes();
        $corp = $this->corpClientes();
        $buscar = $this->textoVenta($data, 'buscar', 100);
        $pagina = filter_var($data['pagina'] ?? 1, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if ($pagina === false || $pagina > 100000) throw new InvalidArgumentException('Página inválida.');

        $limite = 12;
        $where = 'WHERE corp_id=? AND eliminado=0';
        $tipos = 's';
        $params = [$corp];

        if ($buscar !== '') {
            $patron = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $buscar) . '%';
            $where .= " AND (nombre LIKE ? ESCAPE '!' OR apellidos LIKE ? ESCAPE '!'
                         OR CONCAT_WS(' ', nombre, apellidos) LIKE ? ESCAPE '!'
                         OR correo LIKE ? ESCAPE '!' OR correo_empresarial LIKE ? ESCAPE '!'
                         OR telefono LIKE ? ESCAPE '!' OR movil LIKE ? ESCAPE '!'
                         OR area LIKE ? ESCAPE '!' OR cargo LIKE ? ESCAPE '!')";
            $tipos .= str_repeat('s', 9);
            $params = array_merge($params, array_fill(0, 9, $patron));
        }

        $stmt = $this->ejecutarSqlVenta("SELECT COUNT(*) AS total FROM colaboradores $where", $tipos, $params);
        $total = (int)$stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();

        $paginas = max(1, (int)ceil($total / $limite));
        $pagina = min($pagina, $paginas);
        $offset = ($pagina - 1) * $limite;

        $stmt = $this->ejecutarSqlVenta(
            "SELECT corp_id, id, usuario_id, nombre, apellidos, correo, correo_empresarial,
                    telefono, movil, curp, rfc, nss, area, cargo, fecha_ingreso,
                    sindicalizado, horas_semanales, dias_trabajo, tipo_pago, salario_mensual,
                    direccion, colonia, municipio, codigo_postal, estado, pais, foto_ruta,
                    observaciones, fechaAlta, fechaEditado
             FROM colaboradores $where ORDER BY id DESC LIMIT ? OFFSET ?",
            $tipos . 'ii', array_merge($params, [$limite, $offset])
        );

        $colaboradores = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($colaboradores as &$colaborador) {
            $colaborador['id'] = (int)$colaborador['id'];
            $colaborador['usuario_id'] = $colaborador['usuario_id'] === null ? null : (int)$colaborador['usuario_id'];
            $colaborador['sindicalizado'] = (int)$colaborador['sindicalizado'];
        }
        unset($colaborador);

        $json['status'] = 'ok';
        $json['answer'] = 'Colaboradores consultados correctamente.';
        $json['results'] = compact('colaboradores', 'total', 'pagina', 'paginas');
        $json['code'] = 200;
    } catch (DomainException $e) {
        $json = ['status'=>'warning', 'answer'=>$e->getMessage(), 'code'=>403];
    } catch (InvalidArgumentException $e) {
        $json = ['status'=>'warning', 'answer'=>$e->getMessage(), 'code'=>400];
    } catch (Throwable $e) {
        $json = ['status'=>'error', 'answer'=>'No fue posible consultar colaboradores.', 'code'=>500];
    }

    return $json;
}



public function guardarColaborador(array $data = []): array {
    $json = [];

    try {
        if (!$this->conn) throw new RuntimeException('No hay conexión con MySQL.');
        $this->exigirAdminClientes();
        $corp = $this->corpClientes();
        $id = filter_var($data['id'] ?? 0, FILTER_VALIDATE_INT, ['options'=>['min_range'=>0]]);
        if ($id === false) throw new InvalidArgumentException('Identificador del colaborador inválido.');

        $limites = [
            'nombre'=>100, 'apellidos'=>150, 'correo'=>190, 'correo_empresarial'=>190,
            'telefono'=>30, 'movil'=>30, 'curp'=>18, 'rfc'=>13, 'nss'=>11,
            'area'=>100, 'cargo'=>100, 'direccion'=>255, 'colonia'=>150,
            'municipio'=>120, 'codigo_postal'=>10, 'estado'=>100, 'pais'=>100,
            'observaciones'=>3000
        ];

        $campos = [];
        foreach ($limites as $campo => $maximo) {
            $valor = $this->textoVenta($data, $campo, $maximo);
            $campos[$campo] = $valor === '' ? null : $valor;
        }

        if ($campos['nombre'] === null || $campos['apellidos'] === null) {
            throw new InvalidArgumentException('Escribe el nombre y los apellidos del colaborador.');
        }

        if ($campos['pais'] === null) $campos['pais'] = 'México';

        foreach (['correo', 'correo_empresarial'] as $campo) {
            if ($campos[$campo] !== null && !filter_var($campos[$campo], FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Revisa el formato de ' . $campo . '.');
            }
        }

        foreach (['curp', 'rfc', 'nss'] as $campo) {
            if ($campos[$campo] !== null) $campos[$campo] = strtoupper($campos[$campo]);
        }

        if ($campos['curp'] !== null && !preg_match('/^[A-Z0-9]{18}$/D', $campos['curp'])) {
            throw new InvalidArgumentException('La CURP debe tener 18 caracteres válidos.');
        }

        if ($campos['rfc'] !== null && !preg_match('/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/uD', $campos['rfc'])) {
            throw new InvalidArgumentException('El RFC no es válido.');
        }

        if ($campos['nss'] !== null && !preg_match('/^\d{11}$/D', $campos['nss'])) {
            throw new InvalidArgumentException('El NSS debe tener 11 dígitos.');
        }

        $fecha = $this->textoVenta($data, 'fecha_ingreso', 10);
        if ($fecha !== '') {
            $fechaValida = DateTime::createFromFormat('!Y-m-d', $fecha);
            if (!$fechaValida || $fechaValida->format('Y-m-d') !== $fecha) {
                throw new InvalidArgumentException('La fecha de ingreso no es válida.');
            }
        }
        $campos['fecha_ingreso'] = $fecha === '' ? null : $fecha;

        $sindicalizado = $this->textoVenta($data, 'sindicalizado', 1);
        if ($sindicalizado === '') $sindicalizado = '0';
        if (!in_array($sindicalizado, ['0', '1'], true)) {
            throw new InvalidArgumentException('El valor de sindicalizado no es válido.');
        }
        $campos['sindicalizado'] = (int)$sindicalizado;

        $horas = $this->textoVenta($data, 'horas_semanales', 6);
        if ($horas !== '' && (!preg_match('/^\d{1,3}(?:\.\d{1,2})?$/D', $horas) || (float)$horas > 168)) {
            throw new InvalidArgumentException('Las horas semanales deben estar entre 0 y 168.');
        }
        $campos['horas_semanales'] = $horas === '' ? null : $horas;

        $salario = $this->textoVenta($data, 'salario_mensual', 15);
        if ($salario !== '' && !preg_match('/^\d{1,12}(?:\.\d{1,2})?$/D', $salario)) {
            throw new InvalidArgumentException('Indica un salario mensual válido.');
        }
        $campos['salario_mensual'] = $salario === '' ? null : $salario;

        $tipoPago = $this->textoVenta($data, 'tipo_pago', 10);
        if ($tipoPago !== '' && !in_array($tipoPago, ['SEMANAL', 'QUINCENAL', 'MENSUAL'], true)) {
            throw new InvalidArgumentException('Selecciona un tipo de pago válido.');
        }
        $campos['tipo_pago'] = $tipoPago === '' ? null : $tipoPago;

        $dias = $data['dias_trabajo'] ?? [];
        if (is_string($dias)) $dias = $dias === '' ? [] : explode(',', $dias);
        if (!is_array($dias)) throw new InvalidArgumentException('Los días de trabajo son inválidos.');

        $seleccion = [];
        foreach ($dias as $dia) {
            if (!is_scalar($dia) || !preg_match('/^[1-7]$/D', trim((string)$dia))) {
                throw new InvalidArgumentException('Selecciona días de trabajo válidos.');
            }
            $seleccion[(int)$dia] = true;
        }

        ksort($seleccion);
        $campos['dias_trabajo'] = $seleccion ? implode(',', array_keys($seleccion)) : null;

        // usuario_id y foto_ruta se conservan; no se aceptan desde POST.
        $columnas = array_keys($campos);
        $parametros = array_values($campos);

        if ($id === 0) {
            $sql = 'INSERT INTO colaboradores (`corp_id`,`' . implode('`,`', $columnas) . '`)
                    VALUES (?,' . implode(',', array_fill(0, count($columnas), '?')) . ')';

            $stmt = $this->ejecutarSqlVenta(
                $sql, 's' . str_repeat('s', count($columnas)),
                array_merge([$corp], $parametros)
            );
            $stmt->close();

            $id = (int)$this->conn->insert_id;
            $mensaje = 'Colaborador registrado correctamente.';
        } else {
            $stmt = $this->ejecutarSqlVenta(
                'SELECT id FROM colaboradores WHERE corp_id=? AND id=? AND eliminado=0 LIMIT 1',
                'si', [$corp, $id]
            );

            $existe = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$existe) throw new OutOfBoundsException('El colaborador no existe o fue eliminado.');

            $asignaciones = array_map(static fn($columna) => '`' . $columna . '`=?', $columnas);
            $sql = 'UPDATE colaboradores SET ' . implode(', ', $asignaciones) . ', fechaEditado=NOW()
                    WHERE corp_id=? AND id=? AND eliminado=0';

            $stmt = $this->ejecutarSqlVenta(
                $sql, str_repeat('s', count($columnas)) . 'si',
                array_merge($parametros, [$corp, $id])
            );
            $stmt->close();

            $mensaje = 'Colaborador actualizado correctamente.';
        }

        $json['status'] = 'ok';
        $json['answer'] = $mensaje;
        $json['results'] = ['id'=>$id, 'corp_id'=>$corp];
        $json['code'] = 200;
    } catch (DomainException $e) {
        $json = ['status'=>'warning', 'answer'=>$e->getMessage(), 'code'=>403];
    } catch (OutOfBoundsException $e) {
        $json = ['status'=>'warning', 'answer'=>$e->getMessage(), 'code'=>404];
    } catch (InvalidArgumentException $e) {
        $json = ['status'=>'warning', 'answer'=>$e->getMessage(), 'code'=>400];
    } catch (Throwable $e) {
       
        $json = ['status'=>'error', 'answer'=>'No fue posible guardar el colaborador.', 'code'=>500];
    }

    return $json;
}



public function eliminarColaborador(array $data = []): array {
    $json = [];

    try {
        if (!$this->conn) throw new RuntimeException('No hay conexión con MySQL.');
        $this->exigirAdminClientes();
        $corp = $this->corpClientes();
        $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);

        if ($id === false || $id === null) {
            throw new InvalidArgumentException('Identificador inválido.');
        }

        $stmt = $this->ejecutarSqlVenta(
            'SELECT usuario_id FROM colaboradores WHERE corp_id=? AND id=? AND eliminado=0 LIMIT 1',
            'si', [$corp, $id]
        );

        $colaborador = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$colaborador) {
            throw new OutOfBoundsException('El colaborador ya no está disponible.');
        }

        if ($colaborador['usuario_id'] !== null) {
            throw new DomainException('Desactiva primero la cuenta de acceso vinculada al colaborador.');
        }

        $stmt = $this->ejecutarSqlVenta(
            'UPDATE colaboradores SET eliminado=1, fechaEliminado=NOW(), fechaEditado=NOW()
             WHERE corp_id=? AND id=? AND eliminado=0',
            'si', [$corp, $id]
        );

        $afectados = $stmt->affected_rows;
        $stmt->close();

        if ($afectados !== 1) {
            throw new OutOfBoundsException('El colaborador ya no está disponible.');
        }

        $json['status'] = 'ok';
        $json['answer'] = 'Colaborador eliminado correctamente.';
        $json['results'] = ['id'=>$id];
        $json['code'] = 200;
    } catch (DomainException $e) {
        $json = ['status'=>'warning', 'answer'=>$e->getMessage(), 'code'=>403];
    } catch (OutOfBoundsException $e) {
        $json = ['status'=>'warning', 'answer'=>$e->getMessage(), 'code'=>404];
    } catch (InvalidArgumentException $e) {
        $json = ['status'=>'warning', 'answer'=>$e->getMessage(), 'code'=>400];
    } catch (Throwable $e) {
       
        $json = ['status'=>'error', 'answer'=>'No fue posible eliminar el colaborador.', 'code'=>500];
    }

    return $json;
}


//auxiliare


private function valeId($data, string $campo, bool $obligatorio = true): ?int {
    $valor = $data[$campo] ?? null;

    if (($valor === null || $valor === '') && !$obligatorio) {
        return null;
    }

    $id = filter_var(
        $valor,
        FILTER_VALIDATE_INT,
        ['options'=>['min_range'=>1]]
    );

    if ($id === false || $id === null) {
        throw new InvalidArgumentException(
            'Identificador inválido: ' . $campo . '.'
        );
    }

    return $id;
}

private function valeCantidad4($valor, bool $permitirCero = false): int {
    if (!is_string($valor) && !is_int($valor)) {
        throw new InvalidArgumentException('Cantidad inválida.');
    }

    $texto = trim((string)$valor);

    if (!preg_match('/^\d{1,10}(?:\.\d{1,4})?$/D', $texto)) {
        throw new InvalidArgumentException(
            'La cantidad admite hasta cuatro decimales.'
        );
    }

    $partes = explode('.', $texto, 2);

    $numero = (int)$partes[0] * 10000 +
        (int)str_pad($partes[1] ?? '', 4, '0');

    if ($numero <= 0 && !$permitirCero) {
        throw new InvalidArgumentException(
            'La cantidad debe ser mayor que cero.'
        );
    }

    return $numero;
}

private function valeBloquear(string $corp): string {
    $nombre = 'csm_vales_' . substr(
        hash('sha256', $corp),
        0,
        40
    );

    $stmt = $this->ejecutarSqlVenta(
        'SELECT GET_LOCK(?, 8) AS obtenido',
        's',
        [$nombre]
    );

    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ((int)($fila['obtenido'] ?? 0) !== 1) {
        throw new RuntimeException(
            'Otra operación está procesando vales. Intenta nuevamente.'
        );
    }

    return $nombre;
}

private function valeLiberar(?string $nombre): void {
    if ($nombre === null) return;

    try {
        $stmt = $this->ejecutarSqlVenta(
            'SELECT RELEASE_LOCK(?)',
            's',
            [$nombre]
        );

        $stmt->close();
    } catch (Throwable $e) {
        
    }
}

public function getPreparacionValesMaterial(array $data = []): array {
    $json = [];
    $etapa = 'inicio';

    try {
        if (!$this->conn) {
            throw new RuntimeException('Sin conexión con MySQL.');
        }

        $this->exigirAdminClientes();

        $corp = $this->corpClientes();
        $ventaId = $this->valeId($data, 'venta_id');

        $etapa = 'consultar_venta';

        $stmt = $this->ejecutarSqlVenta(
            "SELECT id, note_folio, sale_kind, cliente_id,
                    client_name, created_at
             FROM sales
             WHERE corp_id=?
               AND id=?
               AND sale_kind='NOTA'
             LIMIT 1",
            'si',
            [$corp, $ventaId]
        );

        $venta = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$venta) {
            throw new OutOfBoundsException(
                'La nota no existe en este corporativo.'
            );
        }

        $stmt = $this->ejecutarSqlVenta(
            "SELECT
                si.id AS venta_item_id,
                si.product_id AS producto_id,
                COALESCE(
                    NULLIF(si.manual_name, ''),
                    p.name,
                    'Artículo'
                ) AS descripcion_producto,
                COALESCE(
                    NULLIF(si.manual_code, ''),
                    p.code
                ) AS codigo_producto,
                COALESCE(
                    NULLIF(si.unit_code, ''),
                    p.unit
                ) AS unidad,
                si.quantity AS cantidad_vendida,
                COALESCE(v.cantidad_asignada, 0) AS cantidad_asignada,
                GREATEST(
                    si.quantity - COALESCE(v.cantidad_asignada, 0),
                    0
                ) AS cantidad_disponible
             FROM sale_items si
             LEFT JOIN products p
                ON p.id=si.product_id
             LEFT JOIN (
                SELECT venta_item_id,
                       SUM(cantidad) AS cantidad_asignada
                FROM vales_material
                WHERE corp_id=?
                  AND venta_id=?
                  AND tipo='CARGA'
                  AND estado<>'ANULADO'
                  AND eliminado=0
                GROUP BY venta_item_id
             ) v ON v.venta_item_id=si.id
             WHERE si.sale_id=?
             ORDER BY si.id",
            'sii',
            [$corp, $ventaId, $ventaId]
        );

        $articulos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $stmt = $this->ejecutarSqlVenta(
            "SELECT
                v.id, v.tipo, v.origen, v.venta_id,
                v.venta_item_id, v.producto_id,
                v.cliente_id, v.colaborador_id,
                v.vehiculo_id, v.carga_id,
                v.codigo_embarque, v.descripcion_producto,
                v.codigo_producto, v.cantidad, v.unidad,
                v.indicaciones, v.estado, v.fechaAlta,
                v.fechaRealizado,
                CONCAT_WS(' ', co.nombre, co.apellidos) AS colaborador,
                CONCAT_WS(' ', cl.nombre, cl.apellido) AS destinatario,
                cl.domicilio, cl.colonia, cl.municipio,
                cl.estado AS estado_cliente,
                cl.cp AS codigo_postal_cliente,
                ve.nombre AS vehiculo
             FROM vales_material v
             LEFT JOIN colaboradores co
                ON co.corp_id=v.corp_id
                AND co.id=v.colaborador_id
             LEFT JOIN clientes cl
                ON cl.corp_id=v.corp_id
                AND cl.id=v.cliente_id
             LEFT JOIN vales_material carga
                ON carga.corp_id=v.corp_id
                AND carga.id=v.carga_id
             LEFT JOIN vehiculos_costeo ve
                ON ve.id=COALESCE(
                    v.vehiculo_id,
                    carga.vehiculo_id
                )
             WHERE v.corp_id=?
               AND v.venta_id=?
               AND v.eliminado=0
             ORDER BY v.id DESC",
            'si',
            [$corp, $ventaId]
        );

        $vales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($vales as &$vale) {
            $prefijo = $vale['tipo'] === 'CARGA'
                ? 'VC-' : 'VD-';

            $vale['folio'] = $prefijo .
                str_pad((string)$vale['id'], 6, '0', STR_PAD_LEFT);
        }

        unset($vale);

        $stmt = $this->ejecutarSqlVenta(
            "SELECT id, nombre, apellidos
             FROM colaboradores
             WHERE corp_id=?
               AND eliminado=0
             ORDER BY nombre, apellidos, id",
            's',
            [$corp]
        );

        $colaboradores = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $stmt = $this->ejecutarSqlVenta(
            "SELECT id, nombre, tipo, unidad_capacidad, capacidad
             FROM vehiculos_costeo
             WHERE activo=1
               AND eliminado=0
             ORDER BY nombre"
        );

        $vehiculos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $json = [
            'status'=>'ok',
            'code'=>200,
            'answer'=>'Información de la nota y vales consultada.',
            'results'=>[
                'venta'=>$venta,
                'articulos'=>$articulos,
                'vales'=>$vales,
                'colaboradores'=>$colaboradores,
                'vehiculos'=>$vehiculos
            ]
        ];

    } catch (DomainException $e) {
        $json = [
            'status'=>'warning',
            'code'=>403,
            'answer'=>$e->getMessage()
        ];
    } catch (OutOfBoundsException $e) {
        $json = [
            'status'=>'warning',
            'code'=>404,
            'answer'=>$e->getMessage()
        ];
    } catch (InvalidArgumentException $e) {
        $json = [
            'status'=>'warning',
            'code'=>400,
            'answer'=>$e->getMessage()
        ];
    } catch (Throwable $e) {
        $json = [
            'status'=>'error',
            'code'=>500,
            'answer'=>'No fue posible consultar los vales.',
            'error' => $e->getMessage()
        ];
    }

    return $json;
}



public function guardarValeMaterial(array $data = []): array {
    $json = [];
    $bloqueo = null;

    try {
        if (!$this->conn) {
            throw new RuntimeException('Sin conexión con MySQL.');
        }

        $this->exigirAdminClientes();

        $corp = $this->corpClientes();

        $id = filter_var(
            $data['id'] ?? 0,
            FILTER_VALIDATE_INT,
            ['options'=>['min_range'=>0]]
        );

        if ($id === false) {
            throw new InvalidArgumentException('ID de vale inválido.');
        }

        $ventaId = $this->valeId($data, 'venta_id');
        $itemId = $this->valeId($data, 'venta_item_id');
        $colaboradorId = $this->valeId($data, 'colaborador_id');
        $clienteId = $this->valeId($data, 'cliente_id', false);
        $vehiculoId = $this->valeId($data, 'vehiculo_id', false);
        $cargaId = $this->valeId($data, 'carga_id', false);

        $tipo = strtoupper(
            $this->textoVenta($data, 'tipo', 8)
        );

        $cantidad = $this->valeCantidad4(
            $data['cantidad'] ?? ''
        );

        $cantidadSql = number_format(
            $cantidad / 10000, 4, '.', ''
        );

        $indicaciones = $this->textoVenta(
            $data, 'indicaciones', 500
        );

        $embarque = $this->textoVenta(
            $data, 'codigo_embarque', 40
        );

        $solicitud = $id === 0
            ? strtolower($this->textoVenta($data, 'solicitud_id', 32))
            : null;

        if (!in_array($tipo, ['CARGA', 'DESCARGA'], true)) {
            throw new InvalidArgumentException(
                'Selecciona CARGA o DESCARGA.'
            );
        }

        if ($tipo === 'CARGA' && $cargaId !== null) {
            throw new InvalidArgumentException(
                'Una carga no debe tener carga_id.'
            );
        }

        if ($tipo === 'DESCARGA' &&
            ($cargaId === null || $vehiculoId !== null)) {
            throw new InvalidArgumentException(
                'La descarga necesita una carga de origen y no debe indicar vehículo propio.'
            );
        }

        if ($id === 0 &&
            !preg_match('/^[a-f0-9]{32}$/D', $solicitud)) {
            throw new InvalidArgumentException(
                'Identificador de solicitud inválido.'
            );
        }

        $bloqueo = $this->valeBloquear($corp);

        // Obtener el artículo desde la venta real.
        $stmt = $this->ejecutarSqlVenta(
            "SELECT
                si.id,
                si.product_id,
                si.quantity,
                COALESCE(
                    NULLIF(si.manual_name, ''),
                    p.name
                ) AS descripcion_producto,
                COALESCE(
                    NULLIF(si.manual_code, ''),
                    p.code
                ) AS codigo_producto,
                COALESCE(
                    NULLIF(si.unit_code, ''),
                    p.unit
                ) AS unidad,
                s.cliente_id
             FROM sale_items si
             INNER JOIN sales s
                ON s.id=si.sale_id
             LEFT JOIN products p
                ON p.id=si.product_id
             WHERE s.corp_id=?
               AND s.id=?
               AND si.id=?
               AND s.sale_kind='NOTA'
             LIMIT 1",
            'sii',
            [$corp, $ventaId, $itemId]
        );

        $articulo = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$articulo) {
            throw new OutOfBoundsException(
                'El artículo no pertenece a esa nota.'
            );
        }

        if (!$articulo['cliente_id']) {
            throw new InvalidArgumentException(
                'La nota no tiene cliente registrado.'
            );
        }

        if (!trim((string)$articulo['descripcion_producto']) ||
            !trim((string)$articulo['unidad'])) {
            throw new InvalidArgumentException(
                'El artículo necesita nombre y unidad.'
            );
        }

        // Comprobar que el colaborador pertenezca al corporativo.
        $stmt = $this->ejecutarSqlVenta(
            "SELECT id
             FROM colaboradores
             WHERE corp_id=?
               AND id=?
               AND eliminado=0
             LIMIT 1",
            'si',
            [$corp, $colaboradorId]
        );

        $colaborador = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$colaborador) {
            throw new InvalidArgumentException(
                'El colaborador seleccionado no está disponible.'
            );
        }

        // El cliente predeterminado es el de la nota.
        if ($clienteId === null) {
            $clienteId = (int)$articulo['cliente_id'];
        }

        $stmt = $this->ejecutarSqlVenta(
            "SELECT id
             FROM clientes
             WHERE corp_id=?
               AND id=?
               AND tipo=1
               AND eliminado=0
             LIMIT 1",
            'si',
            [$corp, $clienteId]
        );

        $cliente = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$cliente) {
            throw new InvalidArgumentException(
                'El cliente o destinatario no está disponible.'
            );
        }

        // El vehículo solo se asigna directamente a una carga.
        if ($tipo === 'CARGA' && $vehiculoId !== null) {
            $stmt = $this->ejecutarSqlVenta(
                "SELECT id
                 FROM vehiculos_costeo
                 WHERE id=?
                   AND activo=1
                   AND eliminado=0
                 LIMIT 1",
                'i',
                [$vehiculoId]
            );

            $vehiculo = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$vehiculo) {
                throw new InvalidArgumentException(
                    'El vehículo seleccionado no está disponible.'
                );
            }
        }

        // Una descarga hereda el embarque y vehículo de la carga.
        if ($tipo === 'DESCARGA') {
            $stmt = $this->ejecutarSqlVenta(
                "SELECT id, cantidad, codigo_embarque, estado
                 FROM vales_material
                 WHERE corp_id=?
                   AND id=?
                   AND venta_id=?
                   AND venta_item_id=?
                   AND tipo='CARGA'
                   AND estado<>'ANULADO'
                   AND eliminado=0
                 LIMIT 1",
                'siii',
                [$corp, $cargaId, $ventaId, $itemId]
            );

            $carga = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$carga) {
                throw new InvalidArgumentException(
                    'La carga de origen no es válida.'
                );
            }

            $embarque = (string)($carga['codigo_embarque'] ?? '');
            $vehiculoId = null;
        }

        // Al editar, mantener la identidad original del vale.
        if ($id > 0) {
            $stmt = $this->ejecutarSqlVenta(
                "SELECT tipo, venta_id, venta_item_id,
                        carga_id, estado, vehiculo_id,
                        codigo_embarque
                 FROM vales_material
                 WHERE corp_id=?
                   AND id=?
                   AND eliminado=0
                 LIMIT 1",
                'si',
                [$corp, $id]
            );

            $actual = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$actual) {
                throw new OutOfBoundsException(
                    'Vale no encontrado.'
                );
            }

            if ($actual['estado'] !== 'PENDIENTE') {
                throw new InvalidArgumentException(
                    'Solo puedes editar vales pendientes.'
                );
            }

            if ($actual['tipo'] !== $tipo ||
                (int)$actual['venta_id'] !== $ventaId ||
                (int)$actual['venta_item_id'] !== $itemId ||
                (int)($actual['carga_id'] ?? 0) !== (int)($cargaId ?? 0)) {
                throw new InvalidArgumentException(
                    'No puedes cambiar el tipo, venta, artículo o carga. Anula y crea otro vale.'
                );
            }
        }

        // Evitar crear dos veces la misma solicitud.
        if ($id === 0) {
            $stmt = $this->ejecutarSqlVenta(
                "SELECT id, venta_id, venta_item_id, tipo,
                        cantidad, colaborador_id, cliente_id,
                        vehiculo_id, carga_id, codigo_embarque,
                        indicaciones, estado
                 FROM vales_material
                 WHERE corp_id=?
                   AND solicitud_id=?
                 LIMIT 1",
                'ss',
                [$corp, $solicitud]
            );

            $anterior = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($anterior) {
                if ((int)$anterior['venta_id'] !== $ventaId ||
                    (int)$anterior['venta_item_id'] !== $itemId ||
                    $anterior['tipo'] !== $tipo ||
                    (int)$anterior['colaborador_id'] !== $colaboradorId ||
                    (int)$anterior['cliente_id'] !== $clienteId ||
                    (int)($anterior['vehiculo_id'] ?? 0) !== (int)($vehiculoId ?? 0) ||
                    (int)($anterior['carga_id'] ?? 0) !== (int)($cargaId ?? 0) ||
                    (string)($anterior['codigo_embarque'] ?? '') !== $embarque ||
                    (string)($anterior['indicaciones'] ?? '') !== $indicaciones ||
                    $this->valeCantidad4((string)$anterior['cantidad']) !== $cantidad) {
                    throw new InvalidArgumentException(
                        'La solicitud ya se utilizó con datos diferentes.'
                    );
                }

                $json = [
                    'status'=>'ok',
                    'code'=>200,
                    'answer'=>'Esta solicitud ya estaba registrada.',
                    'results'=>[
                        'id'=>(int)$anterior['id'],
                        'estado'=>$anterior['estado'],
                        'duplicada'=>true
                    ]
                ];

                return $json;
            }
        }

        if ($tipo === 'CARGA') {
            // Sumar todas las cargas vigentes del mismo artículo.
            $stmt = $this->ejecutarSqlVenta(
                "SELECT COALESCE(SUM(cantidad), 0) AS suma
                 FROM vales_material
                 WHERE corp_id=?
                   AND venta_id=?
                   AND venta_item_id=?
                   AND tipo='CARGA'
                   AND estado<>'ANULADO'
                   AND eliminado=0
                   AND id<>?",
                'siii',
                [$corp, $ventaId, $itemId, $id]
            );

            $sumaTexto = (string)$stmt->get_result()
                ->fetch_assoc()['suma'];

            $stmt->close();

            $totalCargas = $this->valeCantidad4(
                $sumaTexto, true
            );

            $vendido = $this->valeCantidad4(
                (string)$articulo['quantity']
            );

            if ($totalCargas + $cantidad > $vendido) {
                throw new InvalidArgumentException(
                    'La suma de cargas supera la cantidad vendida.'
                );
            }

            if ($id > 0) {
                $stmt = $this->ejecutarSqlVenta(
                    "SELECT COALESCE(SUM(cantidad), 0) AS suma
                     FROM vales_material
                     WHERE corp_id=?
                       AND carga_id=?
                       AND tipo='DESCARGA'
                       AND estado<>'ANULADO'
                       AND eliminado=0",
                    'si',
                    [$corp, $id]
                );

                $descargado = (string)$stmt->get_result()
                    ->fetch_assoc()['suma'];

                $stmt->close();

                $totalDescargas = $this->valeCantidad4(
                    $descargado, true
                );

                if ($cantidad < $totalDescargas) {
                    throw new InvalidArgumentException(
                        'La carga no puede ser menor que sus descargas asignadas.'
                    );
                }

                if ($totalDescargas > 0 &&
                    ((int)($actual['vehiculo_id'] ?? 0) !== (int)($vehiculoId ?? 0) ||
                     (string)($actual['codigo_embarque'] ?? '') !== $embarque)) {
                    throw new InvalidArgumentException(
                        'Con descargas asignadas no puedes cambiar el vehículo ni el embarque.'
                    );
                }
            }

            // Todas las cargas de un embarque usan el mismo vehículo.
            if ($embarque !== '') {
                $stmt = $this->ejecutarSqlVenta(
                    "SELECT id
                     FROM vales_material
                     WHERE corp_id=?
                       AND venta_id=?
                       AND tipo='CARGA'
                       AND codigo_embarque=?
                       AND estado<>'ANULADO'
                       AND eliminado=0
                       AND id<>?
                       AND NOT (vehiculo_id <=> ?)
                     LIMIT 1",
                    'sisii',
                    [$corp, $ventaId, $embarque, $id, $vehiculoId]
                );

                $otroVehiculo = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($otroVehiculo) {
                    throw new InvalidArgumentException(
                        'Todas las cargas del embarque deben utilizar el mismo vehículo.'
                    );
                }
            }

        } else {
            // La suma de descargas no puede superar su carga de origen.
            $stmt = $this->ejecutarSqlVenta(
                "SELECT COALESCE(SUM(cantidad), 0) AS suma
                 FROM vales_material
                 WHERE corp_id=?
                   AND carga_id=?
                   AND tipo='DESCARGA'
                   AND estado<>'ANULADO'
                   AND eliminado=0
                   AND id<>?",
                'sii',
                [$corp, $cargaId, $id]
            );

            $suma = (string)$stmt->get_result()
                ->fetch_assoc()['suma'];

            $stmt->close();

            if ($this->valeCantidad4($suma, true) + $cantidad >
                $this->valeCantidad4((string)$carga['cantidad'])) {
                throw new InvalidArgumentException(
                    'La suma de descargas supera la cantidad de la carga.'
                );
            }
        }

        if ($id === 0) {
            $campos = [
                'corp_id'=>$corp,
                'solicitud_id'=>$solicitud,
                'tipo'=>$tipo,
                'origen'=>'VENTA',
                'venta_id'=>$ventaId,
                'venta_item_id'=>$itemId,
                'producto_id'=>$articulo['product_id'],
                'cliente_id'=>$clienteId,
                'colaborador_id'=>$colaboradorId,
                'vehiculo_id'=>$vehiculoId,
                'carga_id'=>$cargaId,
                'codigo_embarque'=>$embarque === '' ? null : $embarque,
                'descripcion_producto'=>$articulo['descripcion_producto'],
                'codigo_producto'=>$articulo['codigo_producto'],
                'cantidad'=>$cantidadSql,
                'unidad'=>$articulo['unidad'],
                'indicaciones'=>$indicaciones === '' ? null : $indicaciones,
                'registrado_por'=>$this->usuarioOperador
            ];

            $columnas = array_keys($campos);

            $sql = 'INSERT INTO vales_material (`' .
                implode('`,`', $columnas) . '`) VALUES (' .
                implode(',', array_fill(0, count($columnas), '?')) .
                ')';

            $stmt = $this->ejecutarSqlVenta(
                $sql,
                str_repeat('s', count($columnas)),
                array_values($campos)
            );

            $id = (int)$this->conn->insert_id;
            $stmt->close();

        } else {
            $stmt = $this->ejecutarSqlVenta(
                "UPDATE vales_material
                 SET cliente_id=?,
                     colaborador_id=?,
                     vehiculo_id=?,
                     codigo_embarque=?,
                     cantidad=?,
                     indicaciones=?,
                     fechaEditado=NOW()
                 WHERE corp_id=?
                   AND id=?
                   AND estado='PENDIENTE'
                   AND eliminado=0",
                'sssssssi',
                [
                    $clienteId,
                    $colaboradorId,
                    $vehiculoId,
                    $embarque === '' ? null : $embarque,
                    $cantidadSql,
                    $indicaciones === '' ? null : $indicaciones,
                    $corp,
                    $id
                ]
            );

            $stmt->close();
        }

        $json = [
            'status'=>'ok',
            'code'=>200,
            'answer'=>'Vale guardado correctamente.',
            'results'=>[
                'id'=>$id,
                'duplicada'=>false
            ]
        ];

    } catch (DomainException $e) {
        $json = [
            'status'=>'warning',
            'code'=>403,
            'answer'=>$e->getMessage()
        ];
    } catch (OutOfBoundsException $e) {
        $json = [
            'status'=>'warning',
            'code'=>404,
            'answer'=>$e->getMessage()
        ];
    } catch (InvalidArgumentException $e) {
        $json = [
            'status'=>'warning',
            'code'=>400,
            'answer'=>$e->getMessage()
        ];
    } catch (Throwable $e) {
        $json = [
            'status'=>'error',
            'code'=>500,
            'answer'=>'No fue posible guardar el vale.',
            'error' => $e->getMessage()
        ];
    } finally {
        $this->valeLiberar($bloqueo);
    }

    return $json;
}

public function confirmarValeMaterial(array $data = []): array {
    $json = [];
    $bloqueo = null;

    try {
        if (!$this->conn) {
            throw new RuntimeException('Sin conexión con MySQL.');
        }

        $this->exigirAdminClientes();

        $corp = $this->corpClientes();
        $id = $this->valeId($data, 'id');

        $bloqueo = $this->valeBloquear($corp);

        $stmt = $this->ejecutarSqlVenta(
            "SELECT id, tipo, venta_id, venta_item_id,
                    carga_id, estado, eliminado
             FROM vales_material
             WHERE corp_id=?
               AND id=?
             LIMIT 1",
            'si',
            [$corp, $id]
        );

        $vale = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$vale ||
            (int)$vale['eliminado'] === 1 ||
            $vale['estado'] === 'ANULADO') {
            throw new OutOfBoundsException(
                'El vale no está disponible.'
            );
        }

        if ($vale['estado'] !== 'REALIZADO') {
            if ($vale['tipo'] === 'DESCARGA') {
                $stmt = $this->ejecutarSqlVenta(
                    "SELECT id
                     FROM vales_material
                     WHERE corp_id=?
                       AND id=?
                       AND tipo='CARGA'
                       AND venta_id=?
                       AND venta_item_id=?
                       AND estado='REALIZADO'
                       AND eliminado=0
                     LIMIT 1",
                    'siii',
                    [
                        $corp,
                        $vale['carga_id'],
                        $vale['venta_id'],
                        $vale['venta_item_id']
                    ]
                );

                $cargaConfirmada = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$cargaConfirmada) {
                    throw new InvalidArgumentException(
                        'Primero debes confirmar la carga del material.'
                    );
                }
            }

            $stmt = $this->ejecutarSqlVenta(
                "UPDATE vales_material
                 SET estado='REALIZADO',
                     realizado_por=?,
                     fechaRealizado=NOW(),
                     fechaEditado=NOW()
                 WHERE corp_id=?
                   AND id=?
                   AND estado='PENDIENTE'
                   AND eliminado=0",
                'isi',
                [$this->usuarioOperador, $corp, $id]
            );

            $afectados = $stmt->affected_rows;
            $stmt->close();

            if ($afectados !== 1) {
                throw new RuntimeException(
                    'No se confirmó la operación.'
                );
            }
        }

        $json = [
            'status'=>'ok',
            'code'=>200,
            'answer'=>$vale['estado'] === 'REALIZADO'
                ? 'El vale ya estaba confirmado.'
                : 'Operación confirmada correctamente.',
            'results'=>[
                'id'=>$id,
                'estado'=>'REALIZADO'
            ]
        ];

    } catch (DomainException $e) {
        $json = [
            'status'=>'warning',
            'code'=>403,
            'answer'=>$e->getMessage()
        ];
    } catch (OutOfBoundsException $e) {
        $json = [
            'status'=>'warning',
            'code'=>404,
            'answer'=>$e->getMessage()
        ];
    } catch (InvalidArgumentException $e) {
        $json = [
            'status'=>'warning',
            'code'=>400,
            'answer'=>$e->getMessage()
        ];
    } catch (Throwable $e) {

        $json = [
            'status'=>'error',
            'code'=>500,
            'answer'=>'No fue posible confirmar el vale.',
            'error' => $e->getMessage()
        ];
    } finally {
        $this->valeLiberar($bloqueo);
    }

    return $json;
}



public function anularValeMaterial(array $data = []): array {
    $json = [];
    $bloqueo = null;

    try {
        if (!$this->conn) {
            throw new RuntimeException('Sin conexión con MySQL.');
        }

        $this->exigirAdminClientes();

        $corp = $this->corpClientes();
        $id = $this->valeId($data, 'id');
        $motivo = $this->textoVenta($data, 'motivo', 300);

        if (strlen($motivo) < 5) {
            throw new InvalidArgumentException(
                'Indica el motivo de la anulación.'
            );
        }

        $bloqueo = $this->valeBloquear($corp);

        $stmt = $this->ejecutarSqlVenta(
            "SELECT tipo, estado, eliminado
             FROM vales_material
             WHERE corp_id=?
               AND id=?
             LIMIT 1",
            'si',
            [$corp, $id]
        );

        $vale = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$vale || (int)$vale['eliminado'] === 1) {
            throw new OutOfBoundsException(
                'El vale no está disponible.'
            );
        }

        if ($vale['estado'] !== 'PENDIENTE') {
            throw new InvalidArgumentException(
                'Solo es posible anular vales pendientes.'
            );
        }

        if ($vale['tipo'] === 'CARGA') {
            $stmt = $this->ejecutarSqlVenta(
                "SELECT id
                 FROM vales_material
                 WHERE corp_id=?
                   AND carga_id=?
                   AND tipo='DESCARGA'
                   AND estado<>'ANULADO'
                   AND eliminado=0
                 LIMIT 1",
                'si',
                [$corp, $id]
            );

            $descarga = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($descarga) {
                throw new InvalidArgumentException(
                    'Anula primero las descargas relacionadas con esta carga.'
                );
            }
        }

        $stmt = $this->ejecutarSqlVenta(
            "UPDATE vales_material
             SET estado='ANULADO',
                 anulado_por=?,
                 fechaAnulado=NOW(),
                 motivo_anulacion=?,
                 fechaEditado=NOW()
             WHERE corp_id=?
               AND id=?
               AND estado='PENDIENTE'
               AND eliminado=0",
            'issi',
            [
                $this->usuarioOperador,
                $motivo,
                $corp,
                $id
            ]
        );

        $afectados = $stmt->affected_rows;
        $stmt->close();

        if ($afectados !== 1) {
            throw new RuntimeException(
                'No se pudo anular el vale.'
            );
        }

        $json = [
            'status'=>'ok',
            'code'=>200,
            'answer'=>'Vale anulado; se conserva en el historial.',
            'results'=>[
                'id'=>$id,
                'estado'=>'ANULADO'
            ]
        ];

    } catch (DomainException $e) {
        $json = [
            'status'=>'warning',
            'code'=>403,
            'answer'=>$e->getMessage()
        ];
    } catch (OutOfBoundsException $e) {
        $json = [
            'status'=>'warning',
            'code'=>404,
            'answer'=>$e->getMessage()
        ];
    } catch (InvalidArgumentException $e) {
        $json = [
            'status'=>'warning',
            'code'=>400,
            'answer'=>$e->getMessage()
        ];
    } catch (Throwable $e) {
        $json = [
            'status'=>'error',
            'code'=>500,
            'answer'=>'No fue posible anular el vale.',
            'error' => $e->getMessage()
        ];
    } finally {
        $this->valeLiberar($bloqueo);
    }

    return $json;
}


private function datoEnteroTasa(
    array $data,
    string $campo,
    int $defecto,
    int $minimo,
    int $maximo
): int {
    $valor = $data[$campo] ?? $defecto;

    if (
        !is_scalar($valor) ||
        !preg_match('/^\d+$/D', trim((string)$valor))
    ) {
        throw new InvalidArgumentException(
            'Valor inválido: ' . $campo . '.'
        );
    }

    $numero = filter_var(
        trim((string)$valor),
        FILTER_VALIDATE_INT
    );

    if (
        $numero === false ||
        $numero < $minimo ||
        $numero > $maximo
    ) {
        throw new InvalidArgumentException(
            'El campo ' . $campo . ' está fuera de rango.'
        );
    }

    return (int)$numero;
}


private function datoDecimalTasa(
    array $data,
    string $campo,
    string $defecto,
    int $escala,
    float $maximo
): string {
    $valor = $data[$campo] ?? $defecto;

    if (!is_scalar($valor)) {
        throw new InvalidArgumentException(
            'Valor inválido: ' . $campo . '.'
        );
    }

    $texto = trim((string)$valor);

    if (
        !preg_match(
            '/^\d{1,11}(?:\.\d{1,' . $escala . '})?$/D',
            $texto
        ) ||
        (float)$texto > $maximo
    ) {
        throw new InvalidArgumentException(
            'Importe o porcentaje inválido: ' . $campo . '.'
        );
    }

    return number_format(
        (float)$texto,
        $escala,
        '.',
        ''
    );
}


private function datoEnumTasa(
    array $data,
    string $campo,
    ?string $defecto,
    array $permitidos
): ?string {
    $valor = $data[$campo] ?? $defecto;

    if ($valor === null || $valor === '') {
        return $defecto;
    }

    if (!is_string($valor)) {
        throw new InvalidArgumentException(
            'Valor inválido: ' . $campo . '.'
        );
    }

    $valor = strtoupper(trim($valor));

    if (!in_array($valor, $permitidos, true)) {
        throw new InvalidArgumentException(
            'Opción inválida: ' . $campo . '.'
        );
    }

    return $valor;
}


private function datoFechaTasa(
    array $data,
    string $campo
): ?string {
    $valor = $data[$campo] ?? '';

    if ($valor === null || $valor === '') {
        return null;
    }

    if (!is_string($valor)) {
        throw new InvalidArgumentException(
            'Fecha inválida: ' . $campo . '.'
        );
    }

    $fecha = DateTime::createFromFormat(
        '!Y-m-d',
        trim($valor)
    );

    if (
        !$fecha ||
        $fecha->format('Y-m-d') !== trim($valor)
    ) {
        throw new InvalidArgumentException(
            'Fecha inválida: ' . $campo . '.'
        );
    }

    return trim($valor);
}


private function normalizarTasaFinanciamiento(
    array $data,
    string $corp
): array {
    $v = [];

    $decimales = [
        'monto_limite_inferior' => ['0', 2, 99999999.99],
        'monto_limite_superior' => ['0', 2, 99999999.99],
        'tasa_interes' => ['0', 4, 1000],
        'tasa_interes_moratorio' => ['0', 4, 1000],
        'comision_apertura' => ['0', 4, 99999999.99],
        'porcentaje_capital_inicial' => ['0', 4, 100],
        'porcentaje_capital_final' => ['0', 4, 100],
        'porcentaje_pago_inicial' => ['0', 4, 100],
        'bono_apertura' => ['0', 4, 99999999.99],
        'bono_pago_puntual' => ['0', 4, 99999999.99],
        'bono_pago_normal' => ['0', 4, 99999999.99]
    ];

    foreach ($decimales as $campo => $regla) {
        $v[$campo] = $this->datoDecimalTasa(
            $data,
            $campo,
            $regla[0],
            $regla[1],
            $regla[2]
        );
    }

    if (
        (float)$v['monto_limite_inferior'] <= 0 ||
        (float)$v['monto_limite_superior'] <
        (float)$v['monto_limite_inferior']
    ) {
        throw new InvalidArgumentException(
            'El rango de montos no es válido.'
        );
    }

    $enteros = [
        'numero_pagos' => [1, 1, 1000],
        'numero_dias' => [0, 0, 3650],
        'cuotas_gracia' => [0, 0, 1000],
        'renovacion_numero_pagos' => [0, 0, 1000],
        'ciclos_limite_inferior' => [0, 0, 999999],
        'ciclos_limite_superior' => [99999, 0, 999999],
        'semaforo_amarillo_mora' => [3, 0, 1000],
        'requiere_aval' => [0, 0, 1],
        'requiere_beneficiario' => [0, 0, 1],
        'aplica_todos_grupos' => [1, 0, 1],
        'permite_creditos_activos' => [1, 0, 1],
        'activo' => [1, 0, 1]
    ];

    foreach ($enteros as $campo => $regla) {
        $v[$campo] = $this->datoEnteroTasa(
            $data,
            $campo,
            $regla[0],
            $regla[1],
            $regla[2]
        );
    }

    if (
        $v['cuotas_gracia'] >= $v['numero_pagos'] ||
        $v['renovacion_numero_pagos'] > $v['numero_pagos']
    ) {
        throw new InvalidArgumentException(
            'Revisa el número de pagos, gracia y renovación.'
        );
    }

    if (
        $v['ciclos_limite_superior'] <
        $v['ciclos_limite_inferior']
    ) {
        throw new InvalidArgumentException(
            'El rango de ciclos no es válido.'
        );
    }

    $opciones = [
        'periodicidad_pago' => [
            'MENSUAL',
            [
                'DIARIO', 'SEMANAL', 'CATORCENAL',
                'QUINCENAL', 'MENSUAL', 'PERSONALIZADO'
            ]
        ],
        'base_tasa_interes' => [
            null,
            ['TOTAL', 'ANUAL', 'MENSUAL', 'POR_PAGO']
        ],
        'base_tasa_moratoria' => [
            null,
            ['DIARIA', 'MENSUAL', 'ANUAL', 'POR_PAGO']
        ],
        'tipo_interes_moratorio' => [
            'SIMPLE',
            ['SIMPLE', 'COMPUESTO']
        ],
        'tipo_comision_apertura' => [
            'MONTO',
            ['MONTO', 'PORCENTAJE']
        ],
        'tipo_amortizacion' => [
            'FLAT',
            ['FLAT', 'AMERICANA', 'FRANCESA', 'DECRECIENTE']
        ],
        'tipo_bono_apertura' => [
            'MONTO',
            ['MONTO', 'PORCENTAJE']
        ],
        'tipo_bono_puntual' => [
            'MONTO',
            ['MONTO', 'PORCENTAJE']
        ],
        'tipo_bono_pago_normal' => [
            'MONTO',
            ['MONTO', 'PORCENTAJE']
        ]
    ];

    foreach ($opciones as $campo => $regla) {
        $v[$campo] = $this->datoEnumTasa(
            $data,
            $campo,
            $regla[0],
            $regla[1]
        );
    }

    if (
        $v['periodicidad_pago'] === 'PERSONALIZADO' &&
        $v['numero_dias'] < 1
    ) {
        throw new InvalidArgumentException(
            'Indica los días de la periodicidad personalizada.'
        );
    }

    if (
        $v['periodicidad_pago'] !== 'PERSONALIZADO' &&
        $v['numero_dias'] !== 0
    ) {
        throw new InvalidArgumentException(
            'Los días personalizados solo aplican a ' .
            'periodicidad personalizada.'
        );
    }

    if (
        (float)$v['tasa_interes'] > 0 &&
        $v['base_tasa_interes'] === null
    ) {
        throw new InvalidArgumentException(
            'Selecciona la base del interés ordinario.'
        );
    }

    if (
        (float)$v['tasa_interes_moratorio'] > 0 &&
        $v['base_tasa_moratoria'] === null
    ) {
        throw new InvalidArgumentException(
            'Selecciona la base del interés moratorio.'
        );
    }

    $tiposImportes = [
        'comision_apertura' => 'tipo_comision_apertura',
        'bono_apertura' => 'tipo_bono_apertura',
        'bono_pago_puntual' => 'tipo_bono_puntual',
        'bono_pago_normal' => 'tipo_bono_pago_normal'
    ];

    foreach ($tiposImportes as $importe => $tipo) {
        if (
            $v[$tipo] === 'PORCENTAJE' &&
            (float)$v[$importe] > 100
        ) {
            throw new InvalidArgumentException(
                'El porcentaje de ' . $importe . ' supera el 100%.'
            );
        }
    }

    // Un grupo concreto o todos los grupos.
    $v['grupo_cliente_id'] = null;

    if ($v['aplica_todos_grupos'] === 0) {
        $idGrupo = $this->datoEnteroTasa(
            $data,
            'grupo_cliente_id',
            0,
            1,
            2147483647
        );

        $stmt = $this->ejecutarSqlVenta(
            "SELECT id
             FROM clientes_grupos
             WHERE corp_id = ?
               AND id = ?
               AND eliminado = 0
             LIMIT 1",
            "si",
            [$corp, $idGrupo]
        );

        $grupo = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$grupo) {
            throw new InvalidArgumentException(
                'El grupo de clientes no existe o fue eliminado.'
            );
        }

        $v['grupo_cliente_id'] = $idGrupo;
    }

    $v['vigente_desde'] = $this->datoFechaTasa(
        $data,
        'vigente_desde'
    );

    $v['vigente_hasta'] = $this->datoFechaTasa(
        $data,
        'vigente_hasta'
    );

    if (
        $v['vigente_desde'] !== null &&
        $v['vigente_hasta'] !== null &&
        $v['vigente_desde'] > $v['vigente_hasta']
    ) {
        throw new InvalidArgumentException(
            'La fecha de vigencia inicial supera la final.'
        );
    }

    $v['titulo_contrato'] = $this->textoVenta(
        $data,
        'titulo_contrato',
        255
    );

    if ($v['titulo_contrato'] === '') {
        $v['titulo_contrato'] = null;
    }

    $contrato = $data['contrato_perfil'] ?? '';

    if (
        !is_string($contrato) ||
        strlen($contrato) > 100000 ||
        strpos($contrato, "\0") !== false
    ) {
        throw new InvalidArgumentException(
            'El texto del contrato no es válido o supera 100 KB.'
        );
    }

    $v['contrato_perfil'] = trim($contrato) === ''
        ? null
        : trim($contrato);

    // Nombre automático si no se recibe uno.
    $nombre = $this->textoVenta(
        $data,
        'nombre',
        255
    );

    if ($nombre === '') {
        $periodos = [
            'DIARIO' => 'días',
            'SEMANAL' => 'semanas',
            'CATORCENAL' => 'catorcenas',
            'QUINCENAL' => 'quincenas',
            'MENSUAL' => 'meses'
        ];

        if ($v['periodicidad_pago'] === 'PERSONALIZADO') {
            $plazo = $v['numero_pagos'] === 1
                ? 'a ' . $v['numero_dias'] . ' días'
                : 'en ' . $v['numero_pagos'] .
                  ' pagos cada ' . $v['numero_dias'] . ' días';
        } else {
            $plazo = 'en ' . $v['numero_pagos'] . ' ' .
                $periodos[$v['periodicidad_pago']];
        }

        $interes = (float)$v['tasa_interes'] > 0
            ? 'al ' .
              rtrim(rtrim($v['tasa_interes'], '0'), '.') .
              '% de interés (' . $v['base_tasa_interes'] . ')'
            : 'sin intereses';

        $nombre =
            '$' . number_format(
                (float)$v['monto_limite_inferior'],
                2, '.', ','
            ) .
            ' a $' . number_format(
                (float)$v['monto_limite_superior'],
                2, '.', ','
            ) .
            ' ' . $plazo .
            ' ' . $interes .
            ' | ' .
            ucfirst(strtolower($v['tipo_amortizacion']));

        if (strlen($nombre) > 255) {
            throw new InvalidArgumentException(
                'El nombre generado supera 255 caracteres.'
            );
        }
    }

    $v['nombre'] = $nombre;

    return $v;
}



public function getTasasFinanciamiento(array $data = []): array {
    try {
        if (!$this->conn) {
            throw new RuntimeException('Sin conexión.');
        }

        $this->exigirAdminClientes();
        $corp = $this->corpClientes();

        $id = $this->datoEnteroTasa(
            $data, 'id', 0, 0, PHP_INT_MAX
        );

        // Consulta individual para editar.
        if ($id > 0) {
            $stmt = $this->ejecutarSqlVenta(
                "SELECT *
                 FROM tasas_financiamiento
                 WHERE corp_id = ?
                   AND id = ?
                   AND eliminado = 0
                 LIMIT 1",
                "si",
                [$corp, $id]
            );

            $tasa = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$tasa) {
                throw new OutOfBoundsException(
                    'La tasa no existe o fue eliminada.'
                );
            }

            return [
                'status' => 'ok',
                'code' => 200,
                'answer' => 'Tasa consultada.',
                'results' => [
                    'tasa' => $tasa
                ]
            ];
        }

        $buscar = $this->textoVenta(
            $data, 'buscar', 100
        );

        $filtroActivo = $this->datoEnumTasa(
            $data,
            'filtro_activo',
            'TODOS',
            ['TODOS', 'ACTIVOS', 'INACTIVOS']
        );

        $pagina = $this->datoEnteroTasa(
            $data, 'pagina', 1, 1, 100000
        );

        $limite = 12;

        $where = " WHERE corp_id = ?
                   AND eliminado = 0";

        $tipos = "s";
        $params = [$corp];

        if ($filtroActivo !== 'TODOS') {
            $where .= " AND activo = ?";
            $tipos .= "i";
            $params[] = $filtroActivo === 'ACTIVOS' ? 1 : 0;
        }

        if ($buscar !== '') {
            $patron = '%' . str_replace(
                ['!', '%', '_'],
                ['!!', '!%', '!_'],
                $buscar
            ) . '%';

            $where .= " AND nombre LIKE ? ESCAPE '!'";
            $tipos .= "s";
            $params[] = $patron;
        }

        $stmt = $this->ejecutarSqlVenta(
            "SELECT COUNT(*) AS total
             FROM tasas_financiamiento" . $where,
            $tipos,
            $params
        );

        $total = (int)$stmt->get_result()
            ->fetch_assoc()['total'];

        $stmt->close();

        $paginas = max(
            1,
            (int)ceil($total / $limite)
        );

        $pagina = min($pagina, $paginas);
        $offset = ($pagina - 1) * $limite;

        $stmt = $this->ejecutarSqlVenta(
            "SELECT
                id,
                nombre,
                monto_limite_inferior,
                monto_limite_superior,
                numero_pagos,
                periodicidad_pago,
                numero_dias,
                tasa_interes,
                base_tasa_interes,
                tipo_amortizacion,

                requiere_aval,
                requiere_beneficiario,
                tipo_comision_apertura,
                comision_apertura,
                tipo_bono_apertura,
                bono_apertura,
                tipo_bono_puntual,
                bono_pago_puntual,
                tipo_bono_pago_normal,
                bono_pago_normal,
                semaforo_amarillo_mora,

                grupo_cliente_id,
                aplica_todos_grupos,
                activo,
                vigente_desde,
                vigente_hasta,
                fecha_alta,
                fecha_actualizacion
             FROM tasas_financiamiento" .
             $where .
             " ORDER BY id DESC
               LIMIT ? OFFSET ?",
            $tipos . "ii",
            array_merge(
                $params,
                [$limite, $offset]
            )
        );

        $tasas = $stmt->get_result()
            ->fetch_all(MYSQLI_ASSOC);

        $stmt->close();

        return [
            'status' => 'ok',
            'code' => 200,
            'answer' => 'Tasas consultadas.',
            'results' => [
                'tasas' => $tasas,
                'total' => $total,
                'pagina' => $pagina,
                'paginas' => $paginas
            ]
        ];

    } catch (DomainException $e) {
        return [
            'status' => 'warning',
            'code' => 403,
            'answer' => $e->getMessage()
        ];
    } catch (OutOfBoundsException $e) {
        return [
            'status' => 'warning',
            'code' => 404,
            'answer' => $e->getMessage()
        ];
    } catch (InvalidArgumentException $e) {
        return [
            'status' => 'warning',
            'code' => 400,
            'answer' => $e->getMessage()
        ];
    } catch (Throwable $e) {
        return [
            'status' => 'error',
            'code' => 500,
            'answer' => 'No fue posible consultar las tasas.'
        ];
    }
}



public function guardarTasaFinanciamiento(
    array $data = []
): array {
    $lockAdquirido = false;
    $lockNombre = '';

    try {
        if (!$this->conn) {
            throw new RuntimeException('Sin conexión.');
        }

        $this->exigirAdminClientes();

        $corp = $this->corpClientes();

        $id = $this->datoEnteroTasa(
            $data,
            'id',
            0,
            0,
            PHP_INT_MAX
        );

        $valores = $this->normalizarTasaFinanciamiento(
            $data,
            $corp
        );

        $token = '';

        if ($id === 0) {
            $token = $this->textoVenta(
                $data,
                'solicitud_id',
                32
            );

            if (!preg_match('/^[a-f0-9]{32}$/D', $token)) {
                throw new InvalidArgumentException(
                    'Identificador de solicitud inválido.'
                );
            }
        }

        // Evitar creaciones simultáneas desde la aplicación.
        $lockNombre = 'csm_tasa_' . sha1($corp);

        $stmt = $this->ejecutarSqlVenta(
            "SELECT GET_LOCK(?, 5) AS adquirido",
            "s",
            [$lockNombre]
        );

        $adquirido = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (
            !$adquirido ||
            (int)$adquirido['adquirido'] !== 1
        ) {
            throw new RuntimeException(
                'No fue posible bloquear el catálogo de tasas.'
            );
        }

        $lockAdquirido = true;

        if ($id === 0) {
            // Una misma solicitud no crea dos tasas.
            $stmt = $this->ejecutarSqlVenta(
                "SELECT id
                 FROM tasas_financiamiento
                 WHERE corp_id = ?
                   AND request_token = ?
                 LIMIT 1",
                "ss",
                [$corp, $token]
            );

            $anterior = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($anterior) {
                return [
                    'status' => 'warning',
                    'code' => 409,
                    'answer' => 'Esta solicitud ya creó una tasa; no se duplicó.',
                    'results' => [
                        'id' => (int)$anterior['id'],
                        'duplicada' => true
                    ]
                ];
            }
        } else {
            // Comprobar que la tasa existe y sigue disponible.
            $stmt = $this->ejecutarSqlVenta(
                "SELECT fecha_actualizacion
                 FROM tasas_financiamiento
                 WHERE corp_id = ?
                   AND id = ?
                   AND eliminado = 0
                 LIMIT 1",
                "si",
                [$corp, $id]
            );

            $anterior = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$anterior) {
                throw new OutOfBoundsException(
                    'La tasa no existe o fue eliminada.'
                );
            }

            // Evitar guardar un formulario desactualizado.
            $version = $this->textoVenta(
                $data,
                'version',
                19
            );

            if (
                $version === '' ||
                $version !== (string)$anterior['fecha_actualizacion']
            ) {
                return [
                    'status' => 'warning',
                    'code' => 409,
                    'answer' =>
                        'La tasa cambió o el formulario está ' .
                        'desactualizado. Vuelve a consultarla.'
                ];
            }
        }

        // No repetir nombres dentro del mismo corporativo.
        $stmt = $this->ejecutarSqlVenta(
            "SELECT id
             FROM tasas_financiamiento
             WHERE corp_id = ?
               AND nombre = ?
               AND eliminado = 0
               AND id <> ?
             LIMIT 1",
            "ssi",
            [$corp, $valores['nombre'], $id]
        );

        $nombreExiste = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($nombreExiste) {
            return [
                'status' => 'warning',
                'code' => 409,
                'answer' => 'Ya existe una tasa con ese nombre.'
            ];
        }

        $columnas = array_keys($valores);
        $params = array_values($valores);

        if ($id === 0) {
            // Crear una sola fila en MyISAM.
            $cols =
                '`corp_id`,`request_token`,' .
                '`creado_por`,`actualizado_por`,`' .
                implode('`,`', $columnas) . '`';

            $sql = 'INSERT INTO tasas_financiamiento (' .
                $cols . ') VALUES (' .
                implode(
                    ',',
                    array_fill(
                        0,
                        count($columnas) + 4,
                        '?'
                    )
                ) . ')';

            $stmt = $this->ejecutarSqlVenta(
                $sql,
                'ssii' . str_repeat('s', count($columnas)),
                array_merge(
                    [
                        $corp,
                        $token,
                        $this->usuarioOperador,
                        $this->usuarioOperador
                    ],
                    $params
                )
            );

            $stmt->close();

            $id = (int)$this->conn->insert_id;

            $mensaje =
                'Tasa de financiamiento creada correctamente.';

        } else {
            // Editar únicamente columnas autorizadas.
            $asignaciones = [];

            foreach ($columnas as $columna) {
                $asignaciones[] = '`' . $columna . '`=?';
            }

            $sql =
                'UPDATE tasas_financiamiento SET ' .
                implode(',', $asignaciones) .
                ', actualizado_por=?, fecha_actualizacion=NOW()
                 WHERE corp_id=?
                   AND id=?
                   AND eliminado=0
                   AND fecha_actualizacion=?';

            $stmt = $this->ejecutarSqlVenta(
                $sql,
                str_repeat('s', count($columnas)) . 'isis',
                array_merge(
                    $params,
                    [
                        $this->usuarioOperador,
                        $corp,
                        $id,
                        $version
                    ]
                )
            );

            $stmt->close();

            $mensaje =
                'Tasa de financiamiento actualizada correctamente.';
        }

        return [
            'status' => 'ok',
            'code' => 200,
            'answer' => $mensaje,
            'results' => [
                'id' => $id
            ]
        ];

    } catch (DomainException $e) {
        return [
            'status' => 'warning',
            'code' => 403,
            'answer' => $e->getMessage()
        ];
    } catch (OutOfBoundsException $e) {
        return [
            'status' => 'warning',
            'code' => 404,
            'answer' => $e->getMessage()
        ];
    } catch (InvalidArgumentException $e) {
        return [
            'status' => 'warning',
            'code' => 400,
            'answer' => $e->getMessage()
        ];
    } catch (Throwable $e) {
        if ((int)$e->getCode() === 1062) {
            return [
                'status' => 'warning',
                'code' => 409,
                'answer' =>
                    'Identificador o nombre duplicado. ' .
                    'Consulta el catálogo.'
            ];
        }

        return [
            'status' => 'error',
            'code' => 500,
            'answer' => 'No fue posible guardar la tasa.'
        ];

    } finally {
        // Liberar el bloqueo incluso si hubo un error.
        if ($lockAdquirido && $this->conn) {
            try {
                $stmt = $this->ejecutarSqlVenta(
                    "SELECT RELEASE_LOCK(?)",
                    "s",
                    [$lockNombre]
                );

                $stmt->close();

            } catch (Throwable $ignorado) {
            }
        }
    }
}



public function cambiarEstadoTasaFinanciamiento(
    array $data = []
): array {
    try {
        if (!$this->conn) {
            throw new RuntimeException('Sin conexión.');
        }

        $this->exigirAdminClientes();

        $id = $this->datoEnteroTasa(
            $data, 'id', 0, 1, PHP_INT_MAX
        );

        $activo = $this->datoEnteroTasa(
            $data, 'activo', -1, 0, 1
        );

        $stmt = $this->ejecutarSqlVenta(
            "UPDATE tasas_financiamiento
             SET activo = ?,
                 actualizado_por = ?,
                 fecha_actualizacion = NOW()
             WHERE corp_id = ?
               AND id = ?
               AND eliminado = 0
               AND activo <> ?",
            "iisii",
            [
                $activo,
                $this->usuarioOperador,
                $this->corpClientes(),
                $id,
                $activo
            ]
        );

        $afectadas = $stmt->affected_rows;
        $stmt->close();

        if ($afectadas !== 1) {
            return [
                'status' => 'warning',
                'code' => 409,
                'answer' =>
                    'La tasa no existe, fue eliminada ' .
                    'o ya tiene ese estado.'
            ];
        }

        return [
            'status' => 'ok',
            'code' => 200,
            'answer' => $activo
                ? 'Tasa activada.'
                : 'Tasa desactivada.',
            'results' => [
                'id' => $id,
                'activo' => $activo
            ]
        ];

    } catch (DomainException $e) {
        return [
            'status' => 'warning',
            'code' => 403,
            'answer' => $e->getMessage()
        ];

    } catch (InvalidArgumentException $e) {
        return [
            'status' => 'warning',
            'code' => 400,
            'answer' => $e->getMessage()
        ];

    } catch (Throwable $e) {
        return [
            'status' => 'error',
            'code' => 500,
            'answer' =>
                'No fue posible cambiar el estado de la tasa.'
        ];
    }
}



public function eliminarTasaFinanciamiento(
    array $data = []
): array {
    try {
        if (!$this->conn) {
            throw new RuntimeException('Sin conexión.');
        }

        $this->exigirAdminClientes();

        $id = $this->datoEnteroTasa(
            $data, 'id', 0, 1, PHP_INT_MAX
        );

        $stmt = $this->ejecutarSqlVenta(
            "UPDATE tasas_financiamiento
             SET eliminado = 1,
                 activo = 0,
                 eliminado_por = ?,
                 fecha_eliminacion = NOW(),
                 actualizado_por = ?,
                 fecha_actualizacion = NOW()
             WHERE corp_id = ?
               AND id = ?
               AND eliminado = 0",
            "iisi",
            [
                $this->usuarioOperador,
                $this->usuarioOperador,
                $this->corpClientes(),
                $id
            ]
        );

        $afectadas = $stmt->affected_rows;
        $stmt->close();

        if ($afectadas !== 1) {
            return [
                'status' => 'warning',
                'code' => 404,
                'answer' => 'La tasa ya no está disponible.'
            ];
        }

        return [
            'status' => 'ok',
            'code' => 200,
            'answer' => 'Tasa eliminada lógicamente.',
            'results' => [
                'id' => $id,
                'eliminado' => 1
            ]
        ];

    } catch (DomainException $e) {
        return [
            'status' => 'warning',
            'code' => 403,
            'answer' => $e->getMessage()
        ];

    } catch (InvalidArgumentException $e) {
        return [
            'status' => 'warning',
            'code' => 400,
            'answer' => $e->getMessage()
        ];

    } catch (Throwable $e) {
        return [
            'status' => 'error',
            'code' => 500,
            'answer' => 'No fue posible eliminar la tasa.'
        ];
    }
}





}
?>