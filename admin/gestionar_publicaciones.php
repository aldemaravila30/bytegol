<?php
require_once 'verificar_admin.php'; // Seguridad: Asegura que solo los administradores accedan a esta página.
require_once '../db/config.php';   // Incluye la configuración de la base de datos y funciones CSRF.

$mensaje = ''; // Variable para almacenar mensajes de éxito.
$error = '';   // Variable para almacenar mensajes de error.

// --- Lógica para eliminar contenido (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Validación del token CSRF para todas las operaciones POST sensibles.
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['admin_error'] = "Error de seguridad: Token CSRF inválido.";
        header('Location: gestionar_publicaciones.php');
        exit();
    }

    if (isset($_POST['eliminar_publicacion'])) {
        $publicacion_id = (int)$_POST['publicacion_id'];

        $conn->begin_transaction();
        try {
            $stmt_img = $conn->prepare("SELECT imagen FROM publicaciones WHERE id = ?");
            if (!$stmt_img) throw new Exception("Error al preparar la consulta de imagen: " . $conn->error);
            $stmt_img->bind_param("i", $publicacion_id);
            $stmt_img->execute();
            $result_img = $stmt_img->get_result();
            $row_img = $result_img->fetch_assoc();

            if ($row_img && !empty($row_img['imagen']) && file_exists('../' . $row_img['imagen'])) {
                unlink('../' . $row_img['imagen']);
            }

            $stmt = $conn->prepare("DELETE FROM publicaciones WHERE id = ?");
            if (!$stmt) throw new Exception("Error al preparar la consulta de eliminación de publicación: " . $conn->error);
            $stmt->bind_param("i", $publicacion_id);
            if ($stmt->execute()) {
                $conn->commit();
                $_SESSION['admin_message'] = "Publicación eliminada correctamente.";
            } else {
                throw new Exception("Error al eliminar la publicación de la base de datos: " . $stmt->error);
            }
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['admin_error'] = "Error al eliminar la publicación: " . $e->getMessage();
        }
        header('Location: gestionar_publicaciones.php');
        exit();
    }

    if (isset($_POST['eliminar_comentario'])) {
        $comentario_id = (int)$_POST['comentario_id'];
        $stmt = $conn->prepare("DELETE FROM comentarios WHERE id = ?");
        if (!$stmt) {
            $_SESSION['admin_error'] = "Error al preparar la consulta de eliminación de comentario: " . $conn->error;
            header('Location: gestionar_publicaciones.php');
            exit();
        }
        $stmt->bind_param("i", $comentario_id);
        if ($stmt->execute()) {
            $_SESSION['admin_message'] = "Comentario eliminado correctamente.";
        } else {
            $_SESSION['admin_error'] = "Error al eliminar el comentario: " . $stmt->error;
        }
        header('Location: gestionar_publicaciones.php');
        exit();
    }
}

// --- Recuperar mensajes/errores de la sesión ---
if (isset($_SESSION['admin_message'])) {
    $mensaje = $_SESSION['admin_message'];
    unset($_SESSION['admin_message']);
}
if (isset($_SESSION['admin_error'])) {
    $error = $_SESSION['admin_error'];
    unset($_SESSION['admin_error']);
}

// --- Obtener todas las publicaciones (con foto de perfil del autor) ---
$sql_publicaciones = "SELECT p.id, p.contenido, p.imagen, p.fecha_publicacion, u.nombre AS autor, u.id AS autor_id, u.foto_perfil
                     FROM publicaciones p
                     JOIN usuarios u ON p.usuario_id = u.id
                     ORDER BY p.fecha_publicacion DESC";

$result_publicaciones = $conn->query($sql_publicaciones);
$publicaciones = []; 

if ($result_publicaciones) {
    while ($row = $result_publicaciones->fetch_assoc()) {
        $publicaciones[] = $row;
    }
} else {
    $error = "Error al cargar las publicaciones: " . $conn->error;
}

$csrf_token = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrar Publicaciones</title>
    <link rel="stylesheet" href="style_admin.css">
    <style>
        /* Ajuste específico para el contenedor de publicaciones, para que no sea tan ancho como el de usuarios */
        .admin-container {
            max-width: 800px; /* Aquí se sobrescribe el max-width general de style_admin.css */
        }
    </style>
</head>
<body>
    <nav>
        <a href="gestionar_usuarios.php">Administrar Usuarios</a>
        <a href="gestionar_publicaciones.php">Administrar Publicaciones</a>
        <a href="../publicaciones/feed.php">Ir al Feed</a>
        <a href="../logueo/logout.php">Cerrar Sesión</a>
    </nav>

    <div class="admin-container">
        <h2>Administrar Publicaciones</h2>

        <?php if (!empty($mensaje)): ?><div class="message success"><?php echo htmlspecialchars($mensaje); ?></div><?php endif; ?>
        <?php if (!empty($error)): ?><div class="message error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if (empty($publicaciones)): ?><p style="text-align: center;">No hay publicaciones para administrar.</p><?php endif; ?>

        <?php foreach ($publicaciones as $pub): ?>
            <div class="post-admin-card">
                <div class="post-header-info">
                    <img src="<?php echo !empty($pub['foto_perfil']) ? '../' . htmlspecialchars($pub['foto_perfil']) : '../uploads/profile/default-profile.png'; ?>" alt="Foto de perfil" class="author-pic">
                    <div class="author-details">
                        <a href="../publicaciones/perfil.php?id=<?php echo htmlspecialchars($pub['autor_id']); ?>" class="author-name"><?php echo htmlspecialchars($pub['autor']); ?></a>
                        <div class="post-date"><?php echo htmlspecialchars($pub['fecha_publicacion']); ?></div>
                    </div>
                </div>

                <div class="post-content-admin">
                    <p><?php echo nl2br(htmlspecialchars($pub['contenido'])); ?></p>
                    <?php if (!empty($pub['imagen'])): ?>
                        <div class="post-image-container">
                            <a href="<?php echo '../' . htmlspecialchars($pub['imagen']); ?>" target="_blank" title="Ver imagen completa">
                                <img src="<?php echo '../' . htmlspecialchars($pub['imagen']); ?>" alt="Imagen de publicación">
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="post-actions-admin">
                    <form method="POST" onsubmit="return confirm('¡ADVERTENCIA!\nEliminar esta publicación también borrará todos sus comentarios y likes.\nEsta acción no se puede deshacer.\n\n¿Continuar?');">
                        <input type="hidden" name="publicacion_id" value="<?php echo $pub['id']; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <button type="submit" name="eliminar_publicacion" class="btn-delete">Eliminar Publicación</button>
                    </form>
                </div>

                <div class="comment-section">
                    <?php
                    // Recuperar comentarios (con foto de perfil del autor del comentario)
                    $stmt_comentarios = $conn->prepare("SELECT c.id, c.contenido, u.nombre AS autor_comentario, u.foto_perfil FROM comentarios c JOIN usuarios u ON c.usuario_id = u.id WHERE c.publicacion_id = ? ORDER BY c.fecha_comentario ASC");
                    if ($stmt_comentarios) {
                        $stmt_comentarios->bind_param("i", $pub['id']);
                        $stmt_comentarios->execute();
                        $resultado_comentarios = $stmt_comentarios->get_result();

                        if ($resultado_comentarios->num_rows > 0) {
                            echo "<h5>Comentarios (" . $resultado_comentarios->num_rows . ")</h5>";
                            while ($com = $resultado_comentarios->fetch_assoc()):
                            ?>
                            <div class="comment-admin">
                                <img src="<?php echo !empty($com['foto_perfil']) ? '../' . htmlspecialchars($com['foto_perfil']) : '../uploads/profile/default-profile.png'; ?>" alt="Foto de perfil" class="commenter-pic">
                                <div class="comment-body">
                                    <strong><?php echo htmlspecialchars($com['autor_comentario']); ?>:</strong>
                                    <span><?php echo htmlspecialchars($com['contenido']); ?></span>
                                </div>
                                <form method="POST" onsubmit="return confirm('¿Eliminar este comentario?');">
                                    <input type="hidden" name="comentario_id" value="<?php echo $com['id']; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                    <button type="submit" name="eliminar_comentario" class="btn-delete" style="font-size: 0.8em; padding: 4px 8px;">Eliminar</button>
                                </form>
                            </div>
                            <?php
                            endwhile;
                        } else {
                            echo "<p style='font-size: 0.9em; color: #777; margin-top: 15px;'>No hay comentarios en esta publicación.</p>";
                        }
                    } else {
                        error_log("Error al preparar la consulta de comentarios: " . $conn->error);
                        echo "<p style='font-size: 0.9em; color: #cc0000;'>Error al cargar los comentarios.</p>";
                    }
                    ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>