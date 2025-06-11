<?php
// feed.php
// Página principal del feed de publicaciones.

// Incluir la configuración de la base de datos y asegurar que la sesión esté iniciada
require_once '../db/config.php';
// Incluir el archivo de lógica y display de comentarios
require_once 'comentarios.php'; // Usa require_once en lugar de include para evitar duplicidad

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../logueo/login.php");
    exit();
}

// Función para sanear la entrada de texto
function sanitize_input($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

// --- Procesar eliminación de publicación ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['eliminar_publicacion'])) {
    $publicacion_id = filter_input(INPUT_POST, 'publicacion_id', FILTER_VALIDATE_INT);
    $usuario_id_sesion = $_SESSION['usuario_id'];

    if (!$publicacion_id) {
        $_SESSION['error'] = "ID de publicación no válido.";
        header("Location: feed.php");
        exit();
    }

    // Verificar que la publicación pertenece al usuario
    $stmt = $conn->prepare("SELECT usuario_id, imagen FROM publicaciones WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $publicacion_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if ($row && $row['usuario_id'] == $usuario_id_sesion) {
            // Eliminar la imagen de la publicación si existe
            if (!empty($row['imagen']) && file_exists("../" . $row['imagen'])) {
                unlink("../" . $row['imagen']);
            }

            // Eliminar la publicación (los likes y comentarios se eliminarán en cascada por las FK)
            $stmt_delete = $conn->prepare("DELETE FROM publicaciones WHERE id = ?");
            if ($stmt_delete) {
                $stmt_delete->bind_param("i", $publicacion_id);
                if ($stmt_delete->execute()) {
                    $_SESSION['mensaje'] = "Publicación eliminada correctamente.";
                } else {
                    $_SESSION['error'] = "Error al eliminar la publicación: " . $stmt_delete->error;
                }
                $stmt_delete->close();
            } else {
                $_SESSION['error'] = "Error de preparación de la consulta para eliminar: " . $conn->error;
            }
        } else {
            $_SESSION['error'] = "No tienes permiso para eliminar esta publicación o no existe.";
        }
    } else {
        $_SESSION['error'] = "Error de preparación de la consulta de verificación: " . $conn->error;
    }
    header("Location: feed.php");
    exit();
}

// --- Procesar edición de publicación ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['editar_publicacion'])) {
    $publicacion_id = filter_input(INPUT_POST, 'publicacion_id', FILTER_VALIDATE_INT);
    $contenido = sanitize_input($_POST['contenido'] ?? '');
    $usuario_id_sesion = $_SESSION['usuario_id'];

    if (!$publicacion_id) {
        $_SESSION['error'] = "ID de publicación no válido.";
        header("Location: feed.php");
        exit();
    }

    if (empty($contenido)) {
        $_SESSION['error'] = "El contenido de la publicación no puede estar vacío.";
        header("Location: feed.php");
        exit();
    }

    // Verificar que la publicación pertenece al usuario
    $stmt = $conn->prepare("SELECT usuario_id FROM publicaciones WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $publicacion_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if ($row && $row['usuario_id'] == $usuario_id_sesion) {
            $stmt_update = $conn->prepare("UPDATE publicaciones SET contenido = ? WHERE id = ?");
            if ($stmt_update) {
                $stmt_update->bind_param("si", $contenido, $publicacion_id);
                if ($stmt_update->execute()) {
                    $_SESSION['mensaje'] = "Publicación actualizada correctamente.";
                } else {
                    $_SESSION['error'] = "Error al actualizar la publicación: " . $stmt_update->error;
                }
                $stmt_update->close();
            } else {
                $_SESSION['error'] = "Error de preparación de la consulta para actualizar: " . $conn->error;
            }
        } else {
            $_SESSION['error'] = "No tienes permiso para editar esta publicación o no existe.";
        }
    } else {
        $_SESSION['error'] = "Error de preparación de la consulta de verificación: " . $conn->error;
    }
    header("Location: feed.php");
    exit();
}

// --- Procesar nueva publicación ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['publicar'])) {
    if (!isset($_SESSION['usuario_id'])) {
        $_SESSION['error'] = "Debes iniciar sesión para publicar.";
        header("Location: feed.php");
        exit();
    }

    $contenido = sanitize_input($_POST['contenido'] ?? '');
    $usuario_id = $_SESSION['usuario_id'];
    $imagen = '';
    $error_subida = false;

    // Validar que el contenido no esté vacío o que se haya subido una imagen
    if (empty($contenido) && empty($_FILES['imagen']['name'])) {
        $_SESSION['error'] = "La publicación no puede estar vacía.";
        header("Location: feed.php");
        exit();
    }

    // Procesar imagen si se subió
    if (!empty($_FILES['imagen']['name'])) {
        $file = $_FILES['imagen'];

        // Validar errores de subida de PHP
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = "Error al subir la imagen: " . $file['error'];
            $error_subida = true;
        } else {
            $target_dir = "../uploads/publicaciones/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0755, true);
            }

            $imageFileType = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

            // Validar tipo de archivo (solo imágenes)
            if (!in_array($imageFileType, $allowed_extensions)) {
                $_SESSION['error'] = "Solo se permiten imágenes (JPG, JPEG, PNG, GIF) para publicaciones.";
                $error_subida = true;
            }

            // Verificar si es una imagen real
            if (!$error_subida) {
                $check = getimagesize($file['tmp_name']);
                if ($check === false) {
                    $_SESSION['error'] = "El archivo subido no es una imagen válida.";
                    $error_subida = true;
                }
            }

            // Generar nombre único para la imagen
            if (!$error_subida) {
                $new_filename = uniqid('post_') . '.' . $imageFileType;
                $target_file = $target_dir . $new_filename;

                if (move_uploaded_file($file['tmp_name'], $target_file)) {
                    $imagen = "uploads/publicaciones/" . $new_filename;
                } else {
                    $_SESSION['error'] = "Error al mover el archivo subido.";
                    $error_subida = true;
                }
            }
        }
    }

    if (!$error_subida && !isset($_SESSION['error'])) { // Solo si no hubo errores previos de validación de archivo o contenido
        $stmt = $conn->prepare("INSERT INTO publicaciones (usuario_id, contenido, imagen) VALUES (?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("iss", $usuario_id, $contenido, $imagen);
            if ($stmt->execute()) {
                $_SESSION['mensaje'] = "Publicación creada correctamente.";
            } else {
                $_SESSION['error'] = "Error al crear la publicación: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $_SESSION['error'] = "Error de preparación de la consulta: " . $conn->error;
        }
    }
    header("Location: feed.php");
    exit();
}

// Obtener publicaciones con sus comentarios y likes
// La subconsulta EXISTS verifica si el usuario actual ha dado like a la publicación
$sql = "SELECT p.*, u.nombre, u.foto_perfil,
               COUNT(DISTINCT l.id) as likes_count,
               COUNT(DISTINCT c.id) as comentarios_count,
               EXISTS(SELECT 1 FROM likes WHERE publicacion_id = p.id AND usuario_id = ?) as liked_by_me
        FROM publicaciones p
        JOIN usuarios u ON p.usuario_id = u.id
        LEFT JOIN likes l ON p.id = l.publicacion_id
        LEFT JOIN comentarios c ON p.id = c.publicacion_id
        GROUP BY p.id
        ORDER BY p.fecha_publicacion DESC";

$stmt_publicaciones = $conn->prepare($sql);
if ($stmt_publicaciones) {
    $stmt_publicaciones->bind_param("i", $_SESSION['usuario_id']);
    $stmt_publicaciones->execute();
    $result_publicaciones = $stmt_publicaciones->get_result();
    $stmt_publicaciones->close();
} else {
    // Manejar el error de preparación de la consulta
    $result_publicaciones = false;
    $_SESSION['error'] = "Error al obtener las publicaciones: " . $conn->error;
}


// Obtener solicitudes de amistad recibidas
$sql_solicitudes = "SELECT COUNT(*) as count FROM solicitudes_amistad
                   WHERE id_destinatario = ? AND estado = 'pendiente'";
$stmt_solicitudes = $conn->prepare($sql_solicitudes);
if ($stmt_solicitudes) {
    $stmt_solicitudes->bind_param("i", $_SESSION['usuario_id']);
    $stmt_solicitudes->execute();
    $result_solicitudes = $stmt_solicitudes->get_result();
    $solicitudes_pendientes = $result_solicitudes->fetch_assoc()['count'];
    $stmt_solicitudes->close();
} else {
    $solicitudes_pendientes = 0; // Por defecto, si hay un error
    $_SESSION['error'] = "Error al obtener solicitudes de amistad: " . $conn->error;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Feed de Publicaciones - ByteGol</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <div class="header">
        <div class="logo">ByteGol</div>
        <a href="../logueo/logout.php" class="logout-button">Cerrar Sesión</a>
    </div>

    <div class="container">
        <div class="nav">
            <a href="feed.php"><i class="fas fa-home"></i> Inicio</a>
            <a href="buscar.php"><i class="fas fa-search"></i> Buscar</a>
            <a href="amigos.php">
                <i class="fas fa-user-friends"></i> Amigos
                <?php if ($solicitudes_pendientes > 0): ?>
                    <span class="notification-badge"><?php echo $solicitudes_pendientes; ?></span>
                <?php endif; ?>
            </a>
            <a href="perfil.php?id=<?php echo $_SESSION['usuario_id']; ?>"><i class="fas fa-user"></i> Perfil</a>
            <?php if (isset($_SESSION['rol']) && in_array($_SESSION['rol'], ['admin', 'superadmin'])): ?>
                <a href="../admin/gestionar_usuarios.php" class="admin-link"><i class="fas fa-user-shield"></i> Panel Admin</a>
            <?php endif; ?>
        </div>

        <?php
        // Mostrar mensajes de éxito o error
        if (isset($_SESSION['mensaje'])) {
            echo "<p class='success'>{$_SESSION['mensaje']}</p>";
            unset($_SESSION['mensaje']);
        }
        if (isset($_SESSION['error'])) {
            echo "<p class='error'>{$_SESSION['error']}</p>";
            unset($_SESSION['error']);
        }
        ?>

        <div class="post-form">
            <form method="post" action="feed.php" enctype="multipart/form-data" id="new-post-form">
                <textarea name="contenido" placeholder="¿Qué estás pensando?" rows="3"></textarea>
                <div class="post-form-actions">
                    <label for="imagen_publicacion" class="attach-btn" title="Adjuntar imagen">
                        <i class="fas fa-camera"></i>
                    </label>
                    <input type="file" name="imagen" id="imagen_publicacion" class="file-input" accept="image/*">
                    <button type="submit" name="publicar">Publicar</button>
                </div>
            </form>
        </div>

        <div class="posts-container">
            <?php
            if ($result_publicaciones && $result_publicaciones->num_rows > 0) {
                while($row = $result_publicaciones->fetch_assoc()) {
                    $publicacion_id = $row['id'];
                    $es_mi_publicacion = ($row['usuario_id'] == $_SESSION['usuario_id']);
                    $liked = $row['liked_by_me'];

                    echo "<div class='post' id='post-$publicacion_id'>";
                    echo "<div class='post-header'>";
                    echo "<a href='perfil.php?id=" . $row['usuario_id'] . "'>";
                    echo "<img src='" . (!empty($row['foto_perfil']) ? '../' . htmlspecialchars($row['foto_perfil']) : '../uploads/profile/default-profile.png') . "' class='post-profile-picture'>";
                    echo "</a>";
                    echo "<div>";
                    echo "<a href='perfil.php?id=" . $row['usuario_id'] . "' class='post-author'>" . htmlspecialchars($row['nombre']) . "</a>";
                    echo "<span class='post-date'>" . htmlspecialchars($row['fecha_publicacion']) . "</span>";
                    echo "</div>";

                    // Menú de opciones para publicaciones
                    if ($es_mi_publicacion) {
                        echo "<div class='post-options-menu'>";
                        echo "<button class='options-toggle-btn' onclick='togglePostOptions({$publicacion_id})'><i class='fas fa-ellipsis-h'></i></button>";
                        echo "<div class='options-dropdown-content' id='post-dropdown-{$publicacion_id}'>";
                        echo "<button type='button' onclick='toggleEditForm({$publicacion_id}); togglePostOptions({$publicacion_id})'><i class='fas fa-edit'></i> Editar</button>";
                        echo "<button type='button' class='delete-btn' onclick='showDeletePostModal({$publicacion_id}); togglePostOptions({$publicacion_id})'><i class='fas fa-trash-alt'></i> Eliminar</button>";
                        echo "</div>"; // Cierre de options-dropdown-content
                        echo "</div>"; // Cierre de post-options-menu
                    }
                    echo "</div>"; // Cierre de post-header

                    // Contenido de la publicación
                    echo "<div class='post-content' id='contenido-$publicacion_id'>";
                    echo "<p>" . nl2br(htmlspecialchars($row['contenido'])) . "</p>";
                    echo "</div>";

                    // Imagen de la publicación
                    if (!empty($row['imagen'])) {
                        $extension = pathinfo($row['imagen'], PATHINFO_EXTENSION);
                        if (in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'gif'])) {
                            echo "<div class='post-image'>";
                            echo "<img src='../" . htmlspecialchars($row['imagen']) . "' alt='Publicación'>";
                            echo "</div>";
                        }
                    }

                    // Formulario de edición de publicación (oculto inicialmente)
                    if ($es_mi_publicacion) {
                        echo "<div class='edit-form' id='edit-form-$publicacion_id' style='display:none;'>";
                        echo "<form method='post' class='edit-post-form'>"; // Añadida clase para JS
                        echo "<input type='hidden' name='publicacion_id' value='$publicacion_id'>";
                        echo "<textarea name='contenido' rows='3'>" . htmlspecialchars($row['contenido']) . "</textarea>";
                        echo "<div class='edit-actions'>";
                        echo "<button type='submit' name='editar_publicacion' class='save-btn'>Guardar</button>";
                        echo "<button type='button' class='cancel-btn' onclick='toggleEditForm($publicacion_id)'>Cancelar</button>";
                        echo "</div>";
                        echo "</form>";
                        echo "</div>";
                    }

                    // Acciones (like y comentar)
                    echo "<div class='post-actions'>";
                    // Formulario de like que ahora usará AJAX
                    echo "<form class='like-form' data-publicacion-id='$publicacion_id'>";
                    echo "<input type='hidden' name='publicacion_id' value='$publicacion_id'>";
                    echo "<button type='submit' name='dar_like' class='like-btn " . ($liked ? 'liked' : '') . "'>";
                    echo "<i class='fas fa-thumbs-up'></i> <span class='like-count'>" . htmlspecialchars($row['likes_count']) . "</span> Me gusta";
                    echo "</button>";
                    echo "</form>";

                    echo "<button class='comment-btn' onclick=\"toggleComments('$publicacion_id')\">";
                    echo "<i class='fas fa-comment'></i> <span class='comment-count'>" . htmlspecialchars($row['comentarios_count']) . "</span> Comentarios";
                    echo "</button>";
                    echo "</div>";

                    // Sección de comentarios (se llama a la función del archivo comentarios.php)
                    displayCommentsSection($publicacion_id, $conn, $es_mi_publicacion);

                    echo "</div>"; // Cierre de post
                }
            } else {
                echo "<p class='no-posts-message'>No hay publicaciones aún.</p>";
            }
            ?>
        </div>
    </div>

    <div id="deletePostModal" class="modal">
        <div class="modal-content">
            <span class="close-button" onclick="closeDeletePostModal()">&times;</span>
            <h3>Confirmar Eliminación</h3>
            <p>¿Estás seguro de que quieres eliminar esta publicación? Esta acción es irreversible y también eliminará todos sus comentarios y likes.</p>
            <div class="modal-actions">
                <button class="cancel-btn" onclick="closeDeletePostModal()">Cancelar</button>
                <form method="post" style="display: inline;" id="confirm-delete-post-form">
                    <input type="hidden" name="publicacion_id" id="modal-delete-post-id">
                    <button type="submit" name="eliminar_publicacion" class="delete-btn">Eliminar</button>
                </form>
            </div>
        </div>
    </div>

    <div id="deleteCommentModal" class="modal">
        <div class="modal-content">
            <span class="close-button" onclick="closeDeleteCommentModal()">&times;</span>
            <h3>Confirmar Eliminación</h3>
            <p>¿Estás seguro de que quieres eliminar este comentario? Esta acción es irreversible.</p>
            <div class="modal-actions">
                <button class="cancel-btn" onclick="closeDeleteCommentModal()">Cancelar</button>
                <form method="post" style="display: inline;" id="confirm-delete-comment-form">
                    <input type="hidden" name="comentario_id" id="modal-delete-comment-id">
                    <button type="submit" name="eliminar_comentario" class="delete-btn">Eliminar</button>
                </form>
            </div>
        </div>
    </div>


    <script>
    // Función para mostrar/ocultar el formulario de edición de publicación
    function toggleEditForm(publicacionId) {
        const form = document.getElementById('edit-form-' + publicacionId);
        const contenido = document.getElementById('contenido-' + publicacionId);

        if (form.style.display === 'block') {
            form.style.display = 'none';
            contenido.style.display = 'block';
        } else {
            form.style.display = 'block';
            contenido.style.display = 'none';
            // Enfocar en el textarea al abrir el formulario
            form.querySelector('textarea[name="contenido"]').focus();
        }
    }

    // Función para mostrar/ocultar comentarios
    function toggleComments(postId) {
        const commentsSection = document.getElementById('comments-' + postId);
        if (commentsSection.style.display === 'block') {
            commentsSection.style.display = 'none';
        } else {
            commentsSection.style.display = 'block';
            // Enfocar en el input de comentario al abrir la sección
            commentsSection.querySelector('.comment-input').focus();
        }
    }

    // Función para mostrar/ocultar formulario de edición de comentario
    function toggleEditCommentForm(commentId) {
        const form = document.getElementById('edit-comment-form-' + commentId);
        const text = document.getElementById('comment-text-' + commentId);

        if (form.style.display === 'block') {
            form.style.display = 'none';
            if (text) text.style.display = 'block';
        } else {
            form.style.display = 'block';
            if (text) text.style.display = 'none';
            form.querySelector('.edit-comment-textarea').focus();
        }
    }

    // --- Funcionalidad de Menú de Opciones para Publicaciones ---
    function togglePostOptions(publicacionId) {
        const dropdown = document.getElementById('post-dropdown-' + publicacionId);
        // Cerrar otros dropdowns si están abiertos
        document.querySelectorAll('.options-dropdown-content').forEach(d => {
            if (d.id !== 'post-dropdown-' + publicacionId && d.id.startsWith('post-dropdown-')) {
                d.style.display = 'none';
            }
        });
        dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
    }

    // --- Funcionalidad de Menú de Opciones para Comentarios ---
    function toggleCommentOptions(commentId) {
        const dropdown = document.getElementById('comment-dropdown-' + commentId);
        // Cerrar otros dropdowns si están abiertos
        document.querySelectorAll('.options-dropdown-content').forEach(d => {
            if (d.id !== 'comment-dropdown-' + commentId && d.id.startsWith('comment-dropdown-')) {
                d.style.display = 'none';
            }
        });
        dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
    }

    // Cerrar dropdowns si se hace clic fuera
    window.onclick = function(event) {
        if (!event.target.matches('.options-toggle-btn') && !event.target.closest('.options-dropdown-content')) {
            document.querySelectorAll('.options-dropdown-content').forEach(d => {
                d.style.display = 'none';
            });
        }
    }

    // --- Modales de confirmación ---
    function showDeletePostModal(publicacionId) {
        document.getElementById('modal-delete-post-id').value = publicacionId;
        document.getElementById('deletePostModal').style.display = 'flex';
    }

    function closeDeletePostModal() {
        document.getElementById('deletePostModal').style.display = 'none';
    }

    function showDeleteCommentModal(commentId) {
        document.getElementById('modal-delete-comment-id').value = commentId;
        document.getElementById('deleteCommentModal').style.display = 'flex';
    }

    function closeDeleteCommentModal() {
        document.getElementById('deleteCommentModal').style.display = 'none';
    }

    // Actualizar contador de likes sin recargar la página (AJAX)
    document.querySelectorAll('.like-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const likeBtn = this.querySelector('.like-btn');
            const likeCountSpan = this.querySelector('.like-count');

            fetch('like_post.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.statusText);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    if (data.action === 'liked') {
                        likeBtn.classList.add('liked');
                    } else {
                        likeBtn.classList.remove('liked');
                    }
                    likeCountSpan.textContent = data.new_likes_count;
                } else {
                    alert('Error en la operación de like: ' + (data.message || 'Error desconocido.'));
                }
            })
            .catch(error => {
                console.error('Error en la solicitud AJAX de like:', error);
                alert('Hubo un problema al procesar el like. Por favor, inténtalo de nuevo.');
            });
        });
    });

    // Validación del formulario de publicación (se mantiene igual, pero ahora con ID)
    document.getElementById('new-post-form').addEventListener('submit', function(e) {
        const contenido = this.querySelector('textarea[name="contenido"]').value.trim();
        const imagen = this.querySelector('input[name="imagen"]').files.length > 0;

        if (contenido === '' && !imagen) {
            alert('La publicación no puede estar vacía. Por favor, escribe algo o adjunta una imagen.');
            e.preventDefault();
        }
    });

    // Auto-ajuste de textarea para comentarios
    document.querySelectorAll('.comment-input, .edit-comment-textarea, .post-form textarea, .edit-form textarea').forEach(textarea => {
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
    });

    // Función para mostrar/ocultar el input de archivo al hacer clic en el ícono de adjuntar
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.attach-btn').forEach(button => {
            const fileInput = button.nextElementSibling; // Asume que el input[type="file"] es el siguiente hermano

            if (fileInput && fileInput.type === 'file') {
                button.addEventListener('click', () => {
                    fileInput.click();
                });
            }
        });
    });

    </script>
</body>
</html>