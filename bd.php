<?php

// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'tinyedit');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Función para obtener la conexión
function getConnection() {
    static $mysqli = null;
    
    if ($mysqli === null) {
    // Intentar conexión por TCP usando DB_HOST_TCP y DB_PORT
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        
        if ($mysqli->connect_error) {
            die("Error de conexión: " . $mysqli->connect_error);
        }
        
        $mysqli->set_charset(DB_CHARSET);
    }
    
    return $mysqli;
}

// Función auxiliar para escapar datos
function escape($value) {
    $db = getConnection();
    return $db->real_escape_string($value);
}