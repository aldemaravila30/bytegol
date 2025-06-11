<?php
// publicaciones/like_post.php

// Asegúrate de que esta ruta sea correcta para tu config.php
include '../db/config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['publicacion_id'])) {
    $publicacion_id = (int)$_POST['publicacion_id'];
    $usuario_id = $_SESSION['usuario_id'];

    $conn->begin_transaction();

    try {
        // 1. Verificar si el usuario ya dio like
        $stmt = $conn->prepare("SELECT id FROM likes WHERE publicacion_id = ? AND usuario_id = ?");
        if (!$stmt) {
            throw new Exception("Error al preparar la consulta de verificación de like: " . $conn->error);
        }
        $stmt->bind_param("ii", $publicacion_id, $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Si ya existe un like, lo eliminamos (dislike)
            $stmt_delete = $conn->prepare("DELETE FROM likes WHERE publicacion_id = ? AND usuario_id = ?");
            if (!$stmt_delete) {
                throw new Exception("Error al preparar la consulta de eliminar like: " . $conn->error);
            }
            $stmt_delete->bind_param("ii", $publicacion_id, $usuario_id);
            $stmt_delete->execute();
            $action = 'disliked';
        } else {
            // Si no existe, lo insertamos (like)
            $stmt_insert = $conn->prepare("INSERT INTO likes (publicacion_id, usuario_id) VALUES (?, ?)");
            if (!$stmt_insert) {
                throw new Exception("Error al preparar la consulta de insertar like: " . $conn->error);
            }
            $stmt_insert->bind_param("ii", $publicacion_id, $usuario_id);
            $stmt_insert->execute();
            $action = 'liked';
        }

        // 2. Contar el nuevo número total de likes para la publicación
        $stmt_count = $conn->prepare("SELECT COUNT(*) as likes_count FROM likes WHERE publicacion_id = ?");
        if (!$stmt_count) {
            throw new Exception("Error al preparar la consulta de conteo de likes: " . $conn->error);
        }
        $stmt_count->bind_param("i", $publicacion_id);
        $stmt_count->execute();
        $result_count = $stmt_count->get_result();
        $likes_data = $result_count->fetch_assoc();
        $new_likes_count = $likes_data['likes_count'];

        $conn->commit(); // Confirmar la transacción

        echo json_encode([
            'success' => true,
            'action' => $action,
            'new_likes_count' => $new_likes_count,
            'publicacion_id' => $publicacion_id
        ]);

    } catch (Exception $e) {
        $conn->rollback(); // Revertir la transacción en caso de error
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }

    $conn->close();
    exit();
} else {
    echo json_encode(['success' => false, 'message' => 'Solicitud inválida.']);
    exit();
}
?>