<?php
// db/utils.php
// Archivo de funciones de utilidad comunes

/**
 * Sanea la entrada de texto para prevenir ataques XSS y limpiar espacios.
 *
 * @param string $data La cadena de texto a sanear.
 * @return string La cadena de texto saneada.
 */
function sanitize_input($data) {
    // trim() elimina espacios en blanco al inicio y al final
    // stripslashes() elimina las barras invertidas que PHP podría añadir automáticamente
    // htmlspecialchars() convierte caracteres especiales en entidades HTML para prevenir XSS
    return htmlspecialchars(stripslashes(trim($data)));
}

// Puedes añadir más funciones de utilidad aquí si son usadas por múltiples archivos.
// Por ejemplo:
// function redirect($url) { header("Location: " . $url); exit(); }
// function display_flash_messages() { /* ... */ }

?>