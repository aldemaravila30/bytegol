<?php
// comentarios.php
// Lógica para procesar la creación, edición y eliminación de comentarios.

// Asegurar que la sesión esté iniciada. Es crucial que este archivo tenga acceso a $_SESSION.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Incluir la configuración de la base de datos.
// Se asume que este archivo está en 'vistas/' y 'config.php' en 'db/'.
require_once __DIR__ . '/../db/config.php';


// --- Procesar nuevo comentario ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['comentar'])) {
    if (!isset($_SESSION['usuario_id'])) {
        $_SESSION['error'] = "Debes iniciar sesión para comentar.";
        header("Location: feed.php");
        exit();
    }

    $publicacion_id = filter_input(INPUT_POST, 'publicacion_id', FILTER_VALIDATE_INT);
    $contenido = sanitize_input($_POST['contenido'] ?? '');
    $usuario_id = $_SESSION['usuario_id'];
    $imagen = '';
    $error_subida = false;

    // Validar ID de publicación
    if (!$publicacion_id) {
        $_SESSION['error'] = "ID de publicación no válido.";
        header("Location: feed.php");
        exit();
    }

    // Validar que al menos haya contenido o una imagen
    if (empty($contenido) && empty($_FILES['imagen_comentario']['name'])) {
        $_SESSION['error'] = "El comentario no puede estar vacío.";
        header("Location: feed.php");
        exit();
    }

    // Procesar imagen/archivo del comentario si se subió
    if (!empty($_FILES['imagen_comentario']['name'])) {
        $file = $_FILES['imagen_comentario'];

        // Validar errores de subida de PHP
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = "Error al subir el archivo: " . $file['error'];
            $error_subida = true;
        } else {
            $target_dir = "../uploads/comentarios/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0755, true);
            }

            $fileType = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt'];

            // Validar tipo de archivo
            if (!in_array($fileType, $allowed_extensions)) {
                $_SESSION['error'] = "Solo se permiten imágenes (JPG, JPEG, PNG, GIF) y documentos (PDF, DOC, DOCX, XLS, XLSX, TXT) para comentarios.";
                $error_subida = true;
            }

            // Si es una imagen, verificar si es real
            if (!$error_subida && in_array($fileType, ['jpg', 'jpeg', 'png', 'gif'])) {
                $check = getimagesize($file['tmp_name']);
                if ($check === false) {
                    $_SESSION['error'] = "El archivo subido no es una imagen válida.";
                    $error_subida = true;
                }
            }

            // Generar nombre único para el archivo
            if (!$error_subida) {
                $new_filename = uniqid('comment_') . '.' . $fileType;
                $target_file = $target_dir . $new_filename;

                if (move_uploaded_file($file['tmp_name'], $target_file)) {
                    $imagen = "uploads/comentarios/" . $new_filename;
                } else {
                    $_SESSION['error'] = "Error al mover el archivo subido.";
                    $error_subida = true;
                }
            }
        }
    }

    if (!$error_subida && !isset($_SESSION['error'])) { // Solo si no hubo errores previos de validación de archivo o contenido
        $stmt = $conn->prepare("INSERT INTO comentarios (publicacion_id, usuario_id, contenido, imagen) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("iiss", $publicacion_id, $usuario_id, $contenido, $imagen);
            if ($stmt->execute()) {
                $_SESSION['mensaje'] = "Comentario publicado correctamente.";
            } else {
                $_SESSION['error'] = "Error al publicar el comentario: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $_SESSION['error'] = "Error de preparación de la consulta: " . $conn->error;
        }
    }
    header("Location: feed.php");
    exit();
}

// --- Procesar eliminación de comentario ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['eliminar_comentario'])) {
    if (!isset($_SESSION['usuario_id'])) {
        $_SESSION['error'] = "Debes iniciar sesión para eliminar comentarios.";
        header("Location: feed.php");
        exit();
    }

    $comentario_id = filter_input(INPUT_POST, 'comentario_id', FILTER_VALIDATE_INT);
    $usuario_id_sesion = $_SESSION['usuario_id'];

    if (!$comentario_id) {
        $_SESSION['error'] = "ID de comentario no válido.";
        header("Location: feed.php");
        exit();
    }

    // Verificar que el comentario pertenece al usuario o que el usuario es dueño de la publicación
    $stmt = $conn->prepare("SELECT c.usuario_id, p.usuario_id as publicacion_usuario_id, c.imagen
                           FROM comentarios c
                           JOIN publicaciones p ON c.publicacion_id = p.id
                           WHERE c.id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $comentario_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if ($row && ($row['usuario_id'] == $usuario_id_sesion || $row['publicacion_usuario_id'] == $usuario_id_sesion)) {
            // Eliminar la imagen/archivo del comentario si existe
            if (!empty($row['imagen']) && file_exists("../" . $row['imagen'])) {
                unlink("../" . $row['imagen']);
            }

            // Eliminar el comentario
            $stmt_delete = $conn->prepare("DELETE FROM comentarios WHERE id = ?");
            if ($stmt_delete) {
                $stmt_delete->bind_param("i", $comentario_id);
                if ($stmt_delete->execute()) {
                    $_SESSION['mensaje'] = "Comentario eliminado correctamente.";
                } else {
                    $_SESSION['error'] = "Error al eliminar el comentario: " . $stmt_delete->error;
                }
                $stmt_delete->close();
            } else {
                $_SESSION['error'] = "Error de preparación de la consulta para eliminar: " . $conn->error;
            }
        } else {
            $_SESSION['error'] = "No tienes permiso para eliminar este comentario o no existe.";
        }
    } else {
        $_SESSION['error'] = "Error de preparación de la consulta de verificación: " . $conn->error;
    }
    header("Location: feed.php");
    exit();
}

// --- Procesar edición de comentario ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['editar_comentario'])) {
    if (!isset($_SESSION['usuario_id'])) {
        $_SESSION['error'] = "Debes iniciar sesión para editar comentarios.";
        header("Location: feed.php");
        exit();
    }

    $comentario_id = filter_input(INPUT_POST, 'comentario_id', FILTER_VALIDATE_INT);
    $contenido = sanitize_input($_POST['contenido'] ?? '');
    $usuario_id_sesion = $_SESSION['usuario_id'];

    if (!$comentario_id) {
        $_SESSION['error'] = "ID de comentario no válido.";
        header("Location: feed.php");
        exit();
    }

    if (empty($contenido)) {
        $_SESSION['error'] = "El contenido del comentario no puede estar vacío.";
        header("Location: feed.php");
        exit();
    }

    // Verificar que el comentario pertenece al usuario
    $stmt = $conn->prepare("SELECT usuario_id FROM comentarios WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $comentario_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if ($row && $row['usuario_id'] == $usuario_id_sesion) {
            $stmt_update = $conn->prepare("UPDATE comentarios SET contenido = ? WHERE id = ?");
            if ($stmt_update) {
                $stmt_update->bind_param("si", $contenido, $comentario_id);
                if ($stmt_update->execute()) {
                    $_SESSION['mensaje'] = "Comentario actualizado correctamente.";
                } else {
                    $_SESSION['error'] = "Error al actualizar el comentario: " . $stmt_update->error;
                }
                $stmt_update->close();
            } else {
                $_SESSION['error'] = "Error de preparación de la consulta para actualizar: " . $conn->error;
            }
        } else {
            $_SESSION['error'] = "No tienes permiso para editar este comentario o no existe.";
        }
    } else {
        $_SESSION['error'] = "Error de preparación de la consulta de verificación: " . $conn->error;
    }
    header("Location: feed.php");
    exit();
}

/**
 * Función para mostrar la sección de comentarios de una publicación.
 * Esta función se llamará desde feed.php para renderizar los comentarios.
 *
 * @param int $publicacion_id El ID de la publicación.
 * @param mysqli $conn La conexión a la base de datos.
 * @param bool $es_mi_publicacion Verdadero si la publicación pertenece al usuario actual.
 */
function displayCommentsSection($publicacion_id, $conn, $es_mi_publicacion) {
    $usuario_id_sesion = $_SESSION['usuario_id'] ?? null; // Obtener el ID de usuario de la sesión

    // Obtener comentarios de esta publicación
    $stmt_comentarios = $conn->prepare("SELECT c.*, u.nombre, u.foto_perfil
                                        FROM comentarios c
                                        JOIN usuarios u ON c.usuario_id = u.id
                                        WHERE c.publicacion_id = ?
                                        ORDER BY c.fecha_comentario ASC");
    $stmt_comentarios->bind_param("i", $publicacion_id);
    $stmt_comentarios->execute();
    $result_comentarios = $stmt_comentarios->get_result();

    echo "<div class='comments-section' id='comments-$publicacion_id' style='display:none;'>";
    echo "<div class='comment-form-container'>"; // Nuevo contenedor para el formulario de comentario
    echo "<form method='post' enctype='multipart/form-data' class='comment-form'>"; // Añadido clase para CSS
    echo "<input type='hidden' name='publicacion_id' value='$publicacion_id'>";
    echo "<textarea name='contenido' class='comment-input' placeholder='Escribe un comentario...' rows='1'></textarea>"; // Usar textarea para multi-línea
    echo "<label for='imagen-comentario-$publicacion_id' class='attach-btn'><i class='fas fa-paperclip'></i></label>";
    echo "<input type='file' id='imagen-comentario-$publicacion_id' name='imagen_comentario' class='file-input' accept='image/*, application/pdf, .doc, .docx, .xls, .xlsx, .txt'>";
    echo "<button type='submit' name='comentar' class='comment-submit'>Publicar</button>";
    echo "</form>";
    echo "</div>"; // Cierre de comment-form-container

    // Mostrar comentarios existentes
    if ($result_comentarios->num_rows > 0) {
        while($comentario = $result_comentarios->fetch_assoc()) {
            $es_mi_comentario = ($comentario['usuario_id'] == $usuario_id_sesion);
            $puedo_eliminar = ($es_mi_comentario || $es_mi_publicacion);

            echo "<div class='comment'>";
            echo "<a href='perfil.php?id=" . $comentario['usuario_id'] . "'>"; // Enlace a perfil del usuario
            echo "<img src='" . (!empty($comentario['foto_perfil']) ? '../' . htmlspecialchars($comentario['foto_perfil']) : '../uploads/profile/default-profile.png') . "' class='comment-profile'>";
            echo "</a>";
            echo "<div class='comment-content-wrapper'>";
            echo "<a href='perfil.php?id=" . $comentario['usuario_id'] . "' class='comment-author'>" . htmlspecialchars($comentario['nombre']) . "</a>"; // Enlace a perfil del usuario
            echo "<span class='comment-date'>" . htmlspecialchars($comentario['fecha_comentario']) . "</span>"; // Agregado para mostrar la fecha

            // Contenido del comentario
            if (!empty($comentario['contenido'])) {
                echo "<div class='comment-text' id='comment-text-{$comentario['id']}'>" . nl2br(htmlspecialchars($comentario['contenido'])) . "</div>";
            }

            // Archivo adjunto al comentario
            if (!empty($comentario['imagen'])) {
                $extension_comentario = pathinfo($comentario['imagen'], PATHINFO_EXTENSION);
                $icono_comentario = '<i class="fas fa-file"></i>'; // Icono por defecto

                // Determinar el icono según el tipo de archivo
                switch (strtolower($extension_comentario)) {
                    case 'pdf': $icono_comentario = '<i class="fas fa-file-pdf"></i>'; break;
                    case 'doc':
                    case 'docx': $icono_comentario = '<i class="fas fa-file-word"></i>'; break;
                    case 'xls':
                    case 'xlsx': $icono_comentario = '<i class="fas fa-file-excel"></i>'; break;
                    case 'txt': $icono_comentario = '<i class="fas fa-file-alt"></i>'; break;
                }

                if (in_array(strtolower($extension_comentario), ['jpg', 'jpeg', 'png', 'gif'])) {
                    echo "<img src='../" . htmlspecialchars($comentario['imagen']) . "' class='comment-image' alt='Comentario'>";
                } else {
                    echo "<div class='comment-file'>";
                    echo "<a href='../" . htmlspecialchars($comentario['imagen']) . "' target='_blank'>";
                    echo $icono_comentario . " " . htmlspecialchars(basename($comentario['imagen']));
                    echo "</a>";
                    echo "</div>";
                }
            }

            // Formulario de edición de comentario (oculto por defecto)
            if ($es_mi_comentario) {
                echo "<div class='edit-comment-form' id='edit-comment-form-{$comentario['id']}' style='display:none;'>";
                echo "<form method='post'>";
                echo "<input type='hidden' name='comentario_id' value='{$comentario['id']}'>";
                echo "<textarea name='contenido' class='edit-comment-textarea'>" . htmlspecialchars($comentario['contenido']) . "</textarea>";
                echo "<div class='edit-comment-actions'>";
                echo "<button type='submit' name='editar_comentario' class='save-btn'>Guardar</button>";
                echo "<button type='button' class='cancel-btn' onclick='toggleEditCommentForm({$comentario['id']})'>Cancelar</button>";
                echo "</div>";
                echo "</form>";
                echo "</div>";
            }

            echo "</div>"; // Cierre de comment-content-wrapper

            // Menú de opciones para comentarios
            if ($puedo_eliminar || $es_mi_comentario) {
                echo "<div class='comment-options-menu'>";
                echo "<button class='options-toggle-btn' onclick='toggleCommentOptions({$comentario['id']})'><i class='fas fa-ellipsis-h'></i></button>";
                echo "<div class='options-dropdown-content' id='comment-dropdown-{$comentario['id']}'>";
                if ($es_mi_comentario) {
                    echo "<button type='button' onclick='toggleEditCommentForm({$comentario['id']}); toggleCommentOptions({$comentario['id']})'><i class='fas fa-edit'></i> Editar</button>";
                }
                if ($puedo_eliminar) {
                    echo "<button type='button' class='delete-btn' onclick='showDeleteCommentModal({$comentario['id']}); toggleCommentOptions({$comentario['id']})'><i class='fas fa-trash-alt'></i> Eliminar</button>";
                }
                echo "</div>"; // Cierre de options-dropdown-content
                echo "</div>"; // Cierre de comment-options-menu
            }

            echo "</div>"; // Cierre de comment
        }
    } else {
        echo "<p class='no-comments-message'>No hay comentarios aún.</p>";
    }
    echo "</div>"; // Cierre de comments-section
    $stmt_comentarios->close();
}
?>