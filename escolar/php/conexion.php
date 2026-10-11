<?php

class conexion {
    private $conn;

    public function __construct() {
        $servername = "localhost";
        $username = "dev047";
        $password = "devSCP117.";
        $dbname = "gestion_escolar";

        $this->conn = new mysqli($servername, $username, $password, $dbname);

        if ($this->conn->connect_error) {
            $json = []; 
            $json["status"] = "error";
            $json["answer"] =  $this->conn->connect_error;
            $json["code"] = 501;
            print(json_encode($json));
            exit();
        }
    }

    public function getConnection() {
        return $this->conn;
    }

    public function closeConnection() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}
?>