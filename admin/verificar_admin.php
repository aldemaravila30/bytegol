<?php
// Este archivo inicia la sesión y verifica si el usuario es un administrador o superadministrador.

// Incluir configuración. config.php ya inicia la sesión.
require_once '../db/config.php';

// 1. Verificar si el usuario ha iniciado sesión en absoluto.
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../logueo/login.php');
    exit();
}

// 2. Verificar si el rol en la sesión es 'admin' o 'superadmin'.
// Usamos un array para que sea más fácil de mantener.
$allowed_roles = ['admin', 'superadmin'];
if (!isset($_SESSION['rol']) || !in_array($_SESSION['rol'], $allowed_roles)) {
    // Si no tiene un rol permitido, lo enviamos al feed.
    header('Location: ../publicaciones/feed.php?error=acceso_denegado');
    exit();
}

// Opcional pero recomendado: Verificación adicional contra la base de datos
// para asegurar que los privilegios no han sido revocados durante la sesión actual.
$stmt = $conn->prepare("SELECT rol, estado FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $_SESSION['usuario_id']);
$stmt->execute();
$result = $stmt->get_result();
$usuario = $result->fetch_assoc();

// Si el usuario no existe, su rol ha sido revocado/cambiado, o está suspendido, cerramos la sesión.
if (!$usuario || !in_array($usuario['rol'], $allowed_roles) || $usuario['estado'] !== 'activo') {
    session_destroy();
    header('Location: ../logueo/login.php?error=credenciales_invalidas');
    exit();
}
?>