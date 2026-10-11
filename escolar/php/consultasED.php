<?php 
class consultasED{
    public function __construct($sesion) {

        $jwt = new JWTSL();
        
        try {
            $token = $jwt->decrypt($sesion);
            $myPosUser = $token['jti'] ?? '';// myposid
            $colaborador = $token['colaborador'] ?? '';
            $myPosid = $token['myposid'] ?? ''; // clienteid
        } catch (Exception $ex) {
            $json['status'] = 'error';
            $json['code'] = "IA-001";
            $json['answer'] = "IA-001: No se pudo desencriptar el token";
            return $json;
        }

        $this->myposId = $myPosUser;
        $this->empleadoId = $colaborador;
        $this->clienteId = $myPosid;

        $this->connectToDatabase();
    }

    private function connectToDatabase() {
        $conexion = new Conexion();
        $this->conn = $conexion->getConnection();

        if ($this->conn->connect_error) {
            $json = [];
            $json["status"] = "error";
            $json["answer"] = "Error de conexión a la base de datos aizu"; 
            $json["code"] = 501;
            return $json; 
        }
        $this->conn->set_charset('utf8');
    }

    public function guardarTokens($plataforma, $accessT, $refreshT, $fechaActual){
        $mypos_id = "";
        $json = [];

        $query2 = "SELECT * FROM apis WHERE mypos_id = ? AND plataforma = ?";
        $stmt2 = $this->conn->prepare($query2);

        if ($stmt2) {
            $stmt2->bind_param("ss", $this->myposId, $plataforma);
            $stmt2->execute();
            $result = $stmt2->get_result();
        
            if ($result->num_rows == 0) {
                
                $query = "INSERT INTO `apis`(`mypos_id`,`plataforma`,`token`,`token_refresh`,`ultima_actualizacion`) 
                          VALUES (?, ?, ?, ?, ?)";

                $stmt = $this->conn->prepare($query);
                
                if ($stmt) {
                    $stmt->bind_param("sssss", $this->myposId, $plataforma, $accessT, $refreshT, $fechaActual);
        
                    if ($stmt->execute()) {
                        $json["status"] = "ok";
                        $json["answer"] = "Tokens guardados correctamente";
                        $json["code"] = 200;
                    } else {
                        $json["status"] = "error";
                        $json["answer"] = "Error al guardar los tokens";
                        $json["code"] = 500;
                    }
        
                    $stmt->close();
                } else {
                    $json["status"] = "error";
                    $json["answer"] = "Error en la preparación de la consulta:";
                    $json["code"] = 500;
                }
            } else {
                $sql = "UPDATE apis SET token = ?, token_refresh = ?, ultima_actualizacion = ? WHERE mypos_id = ? AND plataforma = ?";  
                $stmt3 = $this->conn->prepare($sql);
                $stmt3->bind_param('sssss', $accessT, $refreshT, $fechaActual, $this->myposId, $plataforma); 
            
                $success = $stmt3->execute();
            
                if ($success) {
                    $json['status'] = 'ok';
                    $json['answer'] = 'AccesToken actualizado correctamente';
                    $json['code'] = '200';
                } else {
                    $json['status'] = 'error';
                    $json['answer'] = 'No se pudo actualizar el token';
                    $json['code'] = '500';
                }
            }
        
            $stmt2->close();
        } else {
            $json["status"] = "error";
            $json["answer"] = "Error en la preparación de la consulta SELECT:";
            $json["code"] = 500;
        }
        
        return $json;

    }

    public function getTokens($plataforma){
       
        $json = [];

        $sql = "SELECT token, token_refresh FROM apis WHERE mypos_id = ? AND plataforma = ?";

        $stmt = $this->conn->prepare($sql);
        //$stmt->bind_param('ss', $this->myposId, $plataforma);
        $stmt->bind_param('ss', $mypos_id, $plataforma);  
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
      
        if ($row !== null) {
            $json['status'] = 'ok';
            $json['answer'] = 'Tokens obtenidos correctamente';
            $json['access_token'] = $row['token'];
            $json['refresh_token'] = $row['token_refresh'];
            $json['code'] = 200;
        } else {
            $json['status'] = 'error';
            $json['answer'] = 'Error al obtener los tokens';
            $json['code'] = 500;
        }

        return $json;
    }

    public function updateAccToken($accessT, $plataforma, $timestamp = ""){
        $mypos_id = "";
        $json = [];
        if(!empty($timestamp)){
            $sql = "UPDATE apis SET token = ?, ultima_actualizacion = ? WHERE mypos_id = ? AND plataforma = ?";  
            $stmt = $this->conn->prepare($sql);
            //$stmt->bind_param('ssss', $accessT, $timestamp, $this->myposId, $plataforma);
            $stmt->bind_param('ssss', $accessT, $timestamp, $mypos_id, $plataforma); 
        }else{
            $sql = "UPDATE apis SET token = ? WHERE mypos_id = ? AND plataforma = ?";  
            $stmt = $this->conn->prepare($sql);
            //$stmt->bind_param('sss', $accessT, $this->myposId, $plataforma);    
            $stmt->bind_param('sss', $accessT, $mypos_id, $plataforma);    
        }
    
        $success = $stmt->execute();
    
        if ($success) {
            $json['status'] = 'ok';
            $json['answer'] = 'AccesToken actualizado correctamente';
            $json['code'] = '200';
        } else {
            $json['status'] = 'error';
            $json['answer'] = 'No se pudo actualizar el token';
            $json['code'] = '500';
        }

        $stmt->close();

        return $json;
    }
    
}
?>