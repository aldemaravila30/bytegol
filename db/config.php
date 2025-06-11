<?php
// C:\xampp\htdocs\ByteGol\db\config.php

// Configuración de la sesión (deben ir antes de session_start())
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);

// Iniciar sesión solo si no está activa
if (session_status() == PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 86400,
        'cookie_path' => '/',
        'cookie_domain' => '',
        'cookie_httponly' => true,
        'use_strict_mode' => true
    ]);
}

// Configuración de la base de datos
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "red_social"; // Asegúrate que este nombre de base de datos sea el correcto si lo cambiaste a 'red_social' en tu código.
                     // Según tu código anterior, era 'bytegol'. Si es 'red_social', ajústalo aquí.

// Crear conexión
try {
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Verificar conexión
    if ($conn->connect_error) {
        throw new Exception("Error de conexión a la base de datos: " . $conn->connect_error);
    }

    // Función para sanitizar entradas
    // ¡IMPORTANTE! Envuelve la función en if (!function_exists())
    if (!function_exists('sanitizeInput')) {
        function sanitizeInput($data) {
            global $conn; // Acceder a la conexión para real_escape_string
            $data = trim($data);
            $data = stripslashes($data);
            $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
            return $conn->real_escape_string($data);
        }
    }

    // Función para generar tokens CSRF
    // ¡IMPORTANTE! Envuelve la función en if (!function_exists())
    if (!function_exists('generateCSRFToken')) {
        function generateCSRFToken() {
            if (empty($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
            return $_SESSION['csrf_token'];
        }
    }

    // Función para validar tokens CSRF
    // ¡IMPORTANTE! Envuelve la función en if (!function_exists())
    if (!function_exists('validateCSRFToken')) {
        function validateCSRFToken($token) {
            return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
        }
    }

} catch (Exception $e) {
    // Manejo de errores
    error_log($e->getMessage()); // Registrar el error para depuración sin exponerlo al usuario.
    die("Error de conexión a la base de datos. Por favor, inténtalo más tarde."); // Mensaje amigable al usuario.
}
?>