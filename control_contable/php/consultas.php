<?php 
class consultas{
    private $token;
    private $conn;
    private $usuarioOperador = 1; // ID real del operador en la tabla users

    public function __construct() {
        //$this->token = $session;
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
            error_log("getResumenInicial: " . $e->getMessage());
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

private function textoVenta($datos, $campo, $maximo) {
    $valor = isset($datos[$campo]) ? $datos[$campo] : '';
    if (!is_string($valor) && !is_numeric($valor)) throw new InvalidArgumentException('Dato inválido: ' . $campo);
    $valor = trim((string)$valor);
    if (strlen($valor) > $maximo || strpos($valor, "\0") !== false) throw new InvalidArgumentException('El campo ' . $campo . ' excede su longitud permitida.');
    return $valor;
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
            error_log('getCajasVenta: ' . $e->getMessage());
            $json = ['status'=>'error','answer'=>'No fue posible consultar las cajas.','code'=>500];
        }
    }

    return $json;
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

            $token = $this->textoVenta($venta, 'solicitud_id', 32);
            if (!preg_match('/^[a-f0-9]{32}$/D', $token)) throw new InvalidArgumentException('Identificador de solicitud inválido.');

            $cliente = $this->textoVenta($venta, 'cliente', 100);
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

            if ($cliente === '' || $vendedor === '') throw new InvalidArgumentException('Completa el cliente y el vendedor.');
            if ($folioManual !== '' && !preg_match('~^[A-Za-z0-9][A-Za-z0-9._/-]{0,49}$~D', $folioManual)) throw new InvalidArgumentException('El folio solamente permite letras, números, puntos, guiones y diagonales.');
            if (!in_array($forma, ['EFECTIVO','TRANSFERENCIA','CREDITO','PARCIALIDADES'], true)) throw new InvalidArgumentException('Modalidad de pago inválida.');
            if (!isset($venta['items']) || !is_array($venta['items']) || count($venta['items']) < 1 || count($venta['items']) > 100) throw new InvalidArgumentException('Agrega entre 1 y 100 artículos.');

            $items = [];
            $totalCentavos = 0;

            foreach ($venta['items'] as $index => $item) {
                if (!is_array($item) || (isset($item['origen']) && $item['origen'] !== 'MANUAL')) throw new InvalidArgumentException('Origen de artículo no permitido.');

                $nombre = $this->textoVenta($item, 'nombre', 200);
                $codigo = $this->textoVenta($item, 'codigo', 80);
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

            $stmt = $this->ejecutarSqlVenta('SELECT id, note_folio FROM sales WHERE request_token = ? LIMIT 1', 's', [$token]);
            $anterior = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($anterior) {
                $json = [
                    'status'=>'ok','answer'=>'Esta nota ya estaba registrada; no se duplicó.',
                    'results'=>['id'=>(int)$anterior['id'],'folio'=>$anterior['note_folio'],'duplicada'=>true],
                    'code'=>200
                ];
            } else {
                $fecha = date('Y-m-d H:i:s');
                $idCajaVenta = $pagadoCentavos > 0 && $metodoCobro === 'EFECTIVO' ? $cajaId : null;
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

                $etapa = "insertar_venta";
                $stmt = $this->ejecutarSqlVenta(
                    'INSERT INTO sales (user_id,client_name,client_address,client_phone,payment_method,bank_reference,cash_register_id,pending_amount,total_amount,ticket_barcode,notes,created_at,updated_at,sale_kind,note_folio,seller_name,initial_paid,request_token) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                    'isssssisssssssssss',
                    [$this->usuarioOperador,$cliente,$direccion,$telefono,$forma,$referenciaCobro,$idCajaVenta,$saldo,$total,$ticketInicial,$observaciones,$fecha,$fecha,'NOTA',$folioInicial,$vendedor,$pagado,$token]
                );
                $stmt->close();

                $idVenta = (int)$this->conn->insert_id;
                $folio = $folioInicial !== null ? $folioInicial : 'N-' . str_pad((string)$idVenta, 8, '0', STR_PAD_LEFT);

                $stmt = $this->ejecutarSqlVenta('UPDATE sales SET note_folio = ?, ticket_barcode = ? WHERE id = ?', 'ssi', [$folio,$folio,$idVenta]);
                $stmt->close();

                $etapa = "insertar_articulos";
                $stmtItem = $this->conn->prepare("INSERT INTO sale_items (sale_id,product_id,quantity,price,total,item_origin,manual_name,manual_code) VALUES (?,NULL,?,?,?,'MANUAL',?,?)");
                if (!$stmtItem) throw new RuntimeException($this->conn->error, (int)$this->conn->errno);

                $cantidad = $precio = $importe = '0.00';
                $nombreArticulo = $codigoArticulo = '';
                $stmtItem->bind_param('isssss', $idVenta, $cantidad, $precio, $importe, $nombreArticulo, $codigoArticulo);

                foreach ($items as $item) {
                    $cantidad = $item['cantidad'];
                    $precio = $item['precio'];
                    $importe = $item['importe'];
                    $nombreArticulo = $item['nombre'];
                    $codigoArticulo = $item['codigo'];

                    if (!$stmtItem->execute()) {
                        $error = new RuntimeException($stmtItem->error, (int)$stmtItem->errno);
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
        "INSERT INTO receivables
        (sale_id, client_id, client_name, user_id, total_amount,
        paid_amount, status, due_date, created_at, updated_at,
        description, document_reference, request_token)
        VALUES (?, NULL, ?, ?, ?, ?, 'PENDIENTE', NULL, ?, ?, ?, ?, ?)",
        "isisssssss",
        [
            $idVenta,
            $cliente,
            $this->usuarioOperador,
            $total,
            $pagado,
            $fecha,
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
                        'ssisssiisssss', [$fecha,$origen,$idCajaVenta,$vendedor,$referenciaMovimiento,$pagado,$this->usuarioOperador,$idVenta,$folio,$notaMovimiento,$observaciones,$fecha,$fecha]
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
                        'id'=>$idVenta,'folio'=>$folio,'total'=>$total,
                        'recibido'=>$pagado,'saldo'=>$saldo,
                        'forma_pago'=>$forma,'duplicada'=>false
                    ],
                    'code'=>200
                ];
            }
        } catch (InvalidArgumentException $e) {
            if ($transaccion) $this->conn->rollback();
            $json = ['status'=>'warning','answer'=>$e->getMessage(),'code'=>400];
        } catch (Throwable $e) {
            if ($transaccion) $this->conn->rollback();
            //error_log('guardarVentaNota: ' . $e->getMessage());
            //$json = ['status'=>'error','answer'=>'No fue posible confirmar el registro de la venta.','code'=>500];


error_log("guardarVentaNota [" . $etapa . "] Código " . $e->getCode() . ": " . $e->getMessage());

$json["status"] = "error";
$json["answer"] = "No fue posible confirmar el registro de la venta.";
$json["code"] = 500;
$json["diagnostico"] = [
    "etapa" => $etapa,
    "codigo" => (int)$e->getCode()
];


            if ((int)$e->getCode() === 1062) {
                try {
                    $stmt = $this->ejecutarSqlVenta('SELECT id, note_folio FROM sales WHERE request_token = ? LIMIT 1', 's', [$token]);
                    $repetida = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    if ($repetida) {
                        $json = [
                            'status'=>'ok','answer'=>'La venta ya había sido registrada; no se duplicó.',
                            'results'=>['id'=>(int)$repetida['id'],'folio'=>$repetida['note_folio'],'duplicada'=>true],
                            'code'=>200
                        ];
                    } else {
                        $json = ['status'=>'warning','answer'=>'El folio ya está utilizado en otra nota.','code'=>409];
                    }
                } catch (Throwable $otroError) {
                    error_log('Validar venta duplicada: ' . $otroError->getMessage());
                }
            }
        }
    }

    return $json;
}


private function getDatosNotaVentaPdf($idVenta)
{
    $json = array();

    $stmt = $this->conn->prepare("SELECT id, note_folio, client_name, client_address, client_phone, seller_name, payment_method, bank_reference, total_amount, initial_paid, pending_amount, notes, created_at FROM sales WHERE id = ? AND sale_kind = 'NOTA' LIMIT 1");
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

    $stmtItems = $this->conn->prepare("SELECT quantity, price, total, manual_name, manual_code FROM sale_items WHERE sale_id = ? ORDER BY id ASC");
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
    error_log("generarPdfNotaVenta: " . $e->getMessage());

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
                "precio" => number_format($precioCentavos / 100, 2, ".", ""),
                "importe" => number_format($importeCentavos / 100, 2, ".", "")
            ];
        }

        if ($totalCentavos <= 0) throw new InvalidArgumentException("El total debe ser mayor que cero.");

        $total = number_format($totalCentavos / 100, 2, ".", "");
        $usuario = (int)$this->usuarioOperador;
        if ($usuario <= 0) throw new InvalidArgumentException("Configura el operador.");

        $stmt = $this->ejecutarSqlVenta(
            "SELECT id, sale_kind, ticket_barcode, total_amount, payment_method FROM sales WHERE request_token = ? LIMIT 1",
            "s", [$token]
        );
        $anterior = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($anterior) {
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
                "INSERT INTO sales (user_id,client_name,client_address,client_phone,payment_method,bank_reference,cash_register_id,pending_amount,total_amount,ticket_barcode,notes,created_at,updated_at,sale_kind,note_folio,seller_name,initial_paid,request_token) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                "isssssisssssssssss",
                [$usuario, "Mostrador", "", "", $forma, $referencia, $idCajaVenta, "0.00", $total, null, $observaciones, $fecha, $fecha, "RAPIDA", null, "Mostrador", $total, $token]
            );
            $stmt->close();

            $idVenta = (int)$this->conn->insert_id;
            $numero = "VR-" . str_pad((string)$idVenta, 8, "0", STR_PAD_LEFT);

            $stmt = $this->ejecutarSqlVenta(
                "UPDATE sales SET ticket_barcode = ? WHERE id = ?",
                "si", [$numero, $idVenta]
            );
            $stmt->close();

            $stmtItem = $this->conn->prepare(
                "INSERT INTO sale_items (sale_id,product_id,quantity,price,total,item_origin,manual_name,manual_code) VALUES (?,NULL,?,?,?,'MANUAL',?,?)"
            );
            if (!$stmtItem) throw new RuntimeException($this->conn->error, (int)$this->conn->errno);

            $cantidad = $precio = $importe = "0.00";
            $nombreArticulo = $codigoArticulo = "";

            if (!$stmtItem->bind_param("isssss", $idVenta, $cantidad, $precio, $importe, $nombreArticulo, $codigoArticulo)) {
                throw new RuntimeException("No se pudieron preparar los artículos.");
            }

            foreach ($items as $item) {
                $cantidad = $item["cantidad"];
                $precio = $item["precio"];
                $importe = $item["importe"];
                $nombreArticulo = $item["nombre"];
                $codigoArticulo = $item["codigo"];

                if (!$stmtItem->execute()) {
                    $error = new RuntimeException($stmtItem->error, (int)$stmtItem->errno);
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
                [$fecha, $origen, $idCajaVenta, "Mostrador", $referenciaMovimiento, $total, $usuario, $idVenta, $numero, "Cobro de venta rápida", $observaciones, $fecha, $fecha]
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
        error_log("guardarVentaRapida: " . $e->getMessage());

        $json["status"] = "error";
        $json["answer"] = "No fue posible guardar la venta rápida.";
        $json["code"] = 500;

        if ((int)$e->getCode() === 1062 && $token !== "") {
            try {
                $stmt = $this->ejecutarSqlVenta(
                    "SELECT id,sale_kind,ticket_barcode,total_amount,payment_method FROM sales WHERE request_token = ? LIMIT 1",
                    "s", [$token]
                );
                $repetida = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($repetida && $repetida["sale_kind"] === "RAPIDA" && $repetida["payment_method"] === $forma && $this->centavosVenta($repetida["total_amount"]) === $totalCentavos) {
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
                error_log("Verificar duplicado venta rápida: " . $otroError->getMessage());
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
            error_log("getControlCaja: " . $e->getMessage());
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
            error_log("abrirCaja: " . $e->getMessage());
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
            error_log("cerrarCaja: " . $e->getMessage());
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
            error_log("getEgresos: " . $e->getMessage());
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
                    [$fecha, $origen, $idCajaMovimiento, $beneficiario,
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
                        [$idCaja, $this->usuarioOperador, $monto, $descripcion, $folio, $fecha]
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
            error_log("guardarEgreso: " . $e->getMessage());

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
                    error_log("Verificar egreso duplicado: " . $otroError->getMessage());
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
            $meta = $this->metaCuenta(isset($data["tipo"]) ? $data["tipo"] : "");
            $tabla = $meta["tabla"]; $nombre = $meta["nombre"];
            $buscar = $this->textoVenta($data,"buscar",100);
            $estado = isset($data["estado"]) ? trim((string)$data["estado"]) : "TODAS";
            if (!in_array($estado,["TODAS","PENDIENTE","VENCIDO","PAGADO","CANCELADO","HOY"],true)) throw new InvalidArgumentException("Filtro de estado inválido.");

            $stmt = $this->ejecutarSqlVenta("SELECT COUNT(*) AS total, COALESCE(SUM(CASE WHEN status='PENDIENTE' THEN pending_amount ELSE 0 END),0) AS pendiente, COALESCE(SUM(CASE WHEN status='PENDIENTE' AND due_date<CURDATE() THEN pending_amount ELSE 0 END),0) AS vencido, SUM(CASE WHEN status='PENDIENTE' AND due_date IS NULL THEN 1 ELSE 0 END) AS sin_vencimiento FROM `$tabla` WHERE status<>'CANCELADO'");
            $resumen = $stmt->get_result()->fetch_assoc(); $stmt->close();

            $where = " WHERE 1=1"; $tipos = ""; $params = [];
            if ($buscar !== "") {
                $where .= " AND (`$nombre` LIKE ? OR document_reference LIKE ? OR CAST(id AS CHAR) LIKE ?)";
                $patron = "%".$buscar."%"; $tipos .= "sss"; $params = [$patron,$patron,$patron];
            }
            if ($estado === "PENDIENTE") $where .= " AND status='PENDIENTE' AND pending_amount>0";
            if ($estado === "VENCIDO") $where .= " AND status='PENDIENTE' AND pending_amount>0 AND due_date<CURDATE()";
            if ($estado === "PAGADO") $where .= " AND status='PAGADO'";
            if ($estado === "HOY") $where .= " AND status='PENDIENTE' AND pending_amount>0 AND due_date=CURDATE()";
            if ($estado === "CANCELADO") $where .= " AND status='CANCELADO'";

            $sql = "SELECT id, `$nombre` AS nombre, total_amount, paid_amount, pending_amount, status, due_date, document_reference, description, created_at FROM `$tabla`".$where." ORDER BY CASE WHEN status='PENDIENTE' THEN 0 ELSE 1 END, due_date IS NULL, due_date ASC, id DESC LIMIT 200";
            $stmt = $this->ejecutarSqlVenta($sql,$tipos,$params);
            $cuentas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();

            $json["status"]="ok"; $json["answer"]="Cuentas consultadas correctamente.";
            $json["results"]=["resumen"=>$resumen,"cuentas"=>$cuentas];
            $json["code"]=200;
        } catch (InvalidArgumentException $e) {
            $json["status"]="warning"; $json["answer"]=$e->getMessage(); $json["code"]=400;
        } catch (Throwable $e) {
            error_log("getCuentas: ".$e->getMessage());
            $json["status"]="error"; $json["answer"]="No fue posible consultar las cuentas."; $json["code"]=500;
        }
    }
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
                $monto = number_format($montoCentavos/100,2,".","");
                $relacion = $tipo==="COBRAR" ? "sale_id,client_id" : "purchase_id,supplier_id";
                //$sql = "INSERT INTO `$tabla` ($relacion,`$nombreCol`,user_id,total_amount,paid_amount,pending_amount,status,due_date,created_at,updated_at,description,document_reference,request_token) VALUES (NULL,NULL,?,?,?,0,?,'PENDIENTE',?,?,?,?,?,?)";
                //$stmt = $this->ejecutarSqlVenta($sql,"sissssssss",[$nombre,$this->usuarioOperador,$monto,$monto,$fechaVence,$ahora,$ahora,$concepto,$referencia,$token]);
                
$sql = "INSERT INTO `$tabla`
    ($relacion, `$nombreCol`, user_id, total_amount, paid_amount,
    status, due_date, created_at, updated_at, description,
    document_reference, request_token)
    VALUES (NULL, NULL, ?, ?, ?, 0, 'PENDIENTE', ?, ?, ?, ?, ?, ?)";

$stmt = $this->ejecutarSqlVenta(
    $sql,
    "sisssssss",
    [
        $nombre,
        $this->usuarioOperador,
        $monto,
        $fechaVence,
        $ahora,
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
            error_log("crearCuenta: ".$e->getMessage());
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
    $stmt = $this->ejecutarSqlVenta("SELECT initial_paid,cash_register_id,bank_reference,note_folio,created_at FROM sales WHERE id=? LIMIT 1","i",[(int)$cuenta["sale_id"]]);
    $venta = $stmt->get_result()->fetch_assoc(); $stmt->close();
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
            error_log("getDetalleCuenta: ".$e->getMessage());
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
                $stmt = $this->ejecutarSqlVenta("SELECT id,`$nombreCol` AS nombre,total_amount,paid_amount,pending_amount,status,`$relacion` AS documento_id FROM `$tabla` WHERE id=? FOR UPDATE","i",[$id]);
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
                    "sissississ",[$tipo,$id,$monto,$metodo,$idCajaMovimiento,$referencia,$notas,$this->usuarioOperador,$token,$ahora]
                );
                $stmt->close();
                $idAbono = (int)$this->conn->insert_id;
                $folio = ($tipo==="COBRAR" ? "CXC-" : "CXP-").str_pad((string)$idAbono,8,"0",STR_PAD_LEFT);
                $refMovimiento = $referencia!=="" ? $referencia : $folio;

                $stmt = $this->ejecutarSqlVenta(
                    "INSERT INTO account_moves (`date`,`type`,`origin`,cash_register_id,user_reference_name,reference,amount,user_id,link_type,link_id,ticket_number,status,note,notes,created_at,updated_at,expense_category,request_token) VALUES (?,?,?,?,?,?,?,?,?,?,?,'PAGADO',?,?,?,?,?,?)",
                    "sssisssisisssssss",
                    [$ahora,$movTipo,$origen,$idCajaMovimiento,$cuenta["nombre"],$refMovimiento,$monto,$this->usuarioOperador,$tipo==="COBRAR" ? "CXC" : "CXP",$id,$folio,$notaMovimiento,$notas,$ahora,$ahora,$categoria,$token]
                );
                $stmt->close();
                $idMovimiento = (int)$this->conn->insert_id;

                $stmt = $this->ejecutarSqlVenta("UPDATE account_settlements SET account_move_id=? WHERE id=?","ii",[$idMovimiento,$idAbono]); $stmt->close();
                //$stmt = $this->ejecutarSqlVenta("UPDATE `$tabla` SET paid_amount=?,pending_amount=?,status=?,updated_at=? WHERE id=?","ssssi",[$pagadoNuevo,$saldoNuevo,$estadoNuevo,$ahora,$id]);
                
$stmt = $this->ejecutarSqlVenta(
    "UPDATE `$tabla` SET paid_amount=?,status=?,updated_at=? WHERE id=?",
    "sssi",
    [$pagadoNuevo,$estadoNuevo,$ahora,$id]
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
                    $stmt = $this->ejecutarSqlVenta("INSERT INTO cash_flows (cash_register_id,user_id,amount,`type`,description,reference,created_at) VALUES (?,?,?,?,?,?,?)","iisssss",[$cajaId,$this->usuarioOperador,$monto,$flujo,$descripcion,$folio,$ahora]);
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
            error_log("registrarAbonoCuenta: ".$e->getMessage());
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
                    error_log("Verificar abono duplicado: ".$otroError->getMessage());
                }
            }
        }
    }
    return $json;
}


public function getPersonasCuentas($data = []) {
    $json = [];
    if (!$this->conn) {
        $json = ["status"=>"error","answer"=>"No hay conexión con MySQL.","code"=>500];
    } else {
        try {
            $meta = $this->metaCuenta($data["tipo"] ?? "");
            $tabla = $meta["tabla"]; $col = $meta["nombre"];
            $buscar = $this->textoVenta($data,"buscar",100);
            $sql = "SELECT `$col` AS nombre,COUNT(*) AS creditos,COALESCE(SUM(total_amount),0) AS total,COALESCE(SUM(paid_amount),0) AS abonado,COALESCE(SUM(pending_amount),0) AS pendiente,SUM(CASE WHEN status='PENDIENTE' AND pending_amount>0 AND due_date<CURDATE() THEN 1 ELSE 0 END) AS vencidos,MIN(CASE WHEN status='PENDIENTE' AND pending_amount>0 AND due_date>=CURDATE() THEN due_date END) AS proximo_pago FROM `$tabla` WHERE status<>'CANCELADO'";
            $tipos=""; $params=[];
            if ($buscar!=="") {$sql.=" AND `$col` LIKE ?";$tipos="s";$params=["%".$buscar."%"];}
            $sql.=" GROUP BY `$col` ORDER BY pendiente DESC,nombre ASC LIMIT 200";
            $stmt=$this->ejecutarSqlVenta($sql,$tipos,$params);
            $personas=$stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
            $json=["status"=>"ok","answer"=>"Personas consultadas.","results"=>["personas"=>$personas],"code"=>200];
        } catch (InvalidArgumentException $e) {
            $json=["status"=>"warning","answer"=>$e->getMessage(),"code"=>400];
        } catch (Throwable $e) {
            error_log("getPersonasCuentas: ".$e->getMessage());
            $json=["status"=>"error","answer"=>"No fue posible consultar los deudores o proveedores.","code"=>500];
        }
    }
    return $json;
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
            error_log("getAbonosCuentas: ".$e->getMessage());
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
        error_log('generarDocumentosNotaVenta: '.$e->getMessage());
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
            if ((int)$origen['client_id']>0) {
                $where='r.client_id=?';
                $params=[(int)$origen['client_id']];
            } else {
                $where='r.client_id IS NULL AND BINARY r.client_name=BINARY ?';
                $tipos='s';
                $params=[$origen['client_name']];
            }
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
            $stmt=$this->ejecutarSqlVenta(
                "SELECT sale_id,product_id,manual_name,manual_code,quantity,price,total
                 FROM sale_items WHERE sale_id IN ($marcas) ORDER BY sale_id,id",
                str_repeat('i',count($ids)),$ids
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
                    'total'=>$c['total_amount']
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
        error_log('generarConcentradoCxc: '.$e->getMessage());
        $json=['status'=>'error','answer'=>'No fue posible generar el concentrado.','code'=>500];
    }
    return $json;
}


    
}
?>