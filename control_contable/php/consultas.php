<?php 
class consultas{
    private $token;
    private $conn;
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


    
}
?>