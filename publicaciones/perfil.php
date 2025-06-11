<?php
include '../db/config.php'; // Incluye la configuración de la base de datos y funciones CSRF.

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../logueo/login.php");
    exit();
}

// Obtener ID del perfil a mostrar (si no se especifica, mostrar el propio)
$perfil_id = isset($_GET['id']) ? intval($_GET['id']) : $_SESSION['usuario_id'];

// --- Define $es_mi_perfil here, before any POST logic that uses it ---
$es_mi_perfil = ($perfil_id == $_SESSION['usuario_id']);

// Mensajes de éxito/error (para la edición de perfil y otras acciones)
$mensaje_perfil = '';
$error_perfil = '';

// --- Lógica para procesar SOLICITUDES POST generales (actualizaciones, cambios, etc.) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- Validar CSRF token para todas las acciones POST ---
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error_perfil = "Error de seguridad: Token CSRF inválido.";
    } else {

        // --- Lógica para procesar la ACTUALIZACIÓN DEL PERFIL (nombre, email) ---
        if (isset($_POST['actualizar_perfil'])) {
            if ($perfil_id == $_SESSION['usuario_id']) { // Solo el propio usuario puede editar su perfil
                $nuevo_nombre = trim($_POST['nombre'] ?? '');
                $nuevo_email = trim($_POST['email'] ?? '');

                // Validaciones básicas
                if (empty($nuevo_nombre) || empty($nuevo_email)) {
                    $error_perfil = "El nombre y el email son obligatorios.";
                } elseif (!filter_var($nuevo_email, FILTER_VALIDATE_EMAIL)) {
                    $error_perfil = "El formato del email no es válido.";
                } else {
                    // Verificar si el nuevo email ya existe para otro usuario
                    $stmt_check_email = $conn->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
                    if (!$stmt_check_email) {
                        $error_perfil = "Error interno al preparar la verificación de email: " . $conn->error;
                    } else {
                        $stmt_check_email->bind_param("si", $nuevo_email, $_SESSION['usuario_id']);
                        $stmt_check_email->execute();
                        $result_check_email = $stmt_check_email->get_result();

                        if ($result_check_email->num_rows > 0) {
                            $error_perfil = "El email '" . htmlspecialchars($nuevo_email) . "' ya está en uso por otra cuenta.";
                        } else {
                            // Actualizar en la base de datos
                            $stmt_update = $conn->prepare("UPDATE usuarios SET nombre = ?, email = ? WHERE id = ?");
                            if (!$stmt_update) {
                                $error_perfil = "Error interno al preparar la actualización: " . $conn->error;
                            } else {
                                $stmt_update->bind_param("ssi", $nuevo_nombre, $nuevo_email, $_SESSION['usuario_id']);
                                if ($stmt_update->execute()) {
                                    // Actualizar la sesión si el usuario cambió su propio perfil
                                    $_SESSION['usuario_nombre'] = $nuevo_nombre;
                                    $_SESSION['usuario_email'] = $nuevo_email;
                                    $mensaje_perfil = "Perfil actualizado correctamente.";
                                } else {
                                    $error_perfil = "Error al actualizar el perfil: " . $stmt_update->error;
                                }
                            }
                        }
                    }
                }
            } else {
                $error_perfil = "No tienes permiso para editar este perfil.";
            }
        }

        // --- Lógica para procesar el CAMBIO DE CONTRASEÑA ---
        if (isset($_POST['cambiar_contrasena'])) {
            if ($perfil_id == $_SESSION['usuario_id']) { // Solo el propio usuario puede cambiar su contraseña
                $current_password = $_POST['current_password'] ?? '';
                $new_password = $_POST['new_password'] ?? '';
                $confirm_new_password = $_POST['confirm_new_password'] ?? '';

                if ($new_password !== $confirm_new_password) {
                    $error_perfil = "La nueva contraseña y su confirmación no coinciden.";
                }
                elseif (empty($new_password) || strlen($new_password) < 6) {
                    $error_perfil = "La nueva contraseña debe tener al menos 6 caracteres.";
                }
                else {
                    $stmt_check_password = $conn->prepare("SELECT contrasena FROM usuarios WHERE id = ?");
                    if (!$stmt_check_password) {
                        $error_perfil = "Error interno al preparar la verificación de contraseña: " . $conn->error;
                    } else {
                        $stmt_check_password->bind_param("i", $_SESSION['usuario_id']);
                        $stmt_check_password->execute();
                        $result_check_password = $stmt_check_password->get_result();
                        $user_data = $result_check_password->fetch_assoc();

                        if ($user_data && password_verify($current_password, $user_data['contrasena'])) {
                            $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                            $stmt_update_password = $conn->prepare("UPDATE usuarios SET contrasena = ? WHERE id = ?");
                            if (!$stmt_update_password) {
                                $error_perfil = "Error interno al preparar la actualización de contraseña: " . $conn->error;
                            } else {
                                $stmt_update_password->bind_param("si", $hashed_password, $_SESSION['usuario_id']);
                                if ($stmt_update_password->execute()) {
                                    $mensaje_perfil = "Contraseña actualizada correctamente. ¡Recuerda tu nueva contraseña!";
                                } else {
                                    $error_perfil = "Error al actualizar la contraseña: " . $stmt_update_password->error;
                                }
                            }
                        } else {
                            $error_perfil = "La contraseña actual es incorrecta.";
                        }
                    }
                }
            } else {
                $error_perfil = "No tienes permiso para cambiar la contraseña de este perfil.";
            }
        }

        // --- Lógica para procesar eliminación de foto de perfil ---
        if (isset($_POST['eliminar_foto'])) {
            if ($es_mi_perfil) {
                $sql_foto = "SELECT foto_perfil FROM usuarios WHERE id = ?";
                $stmt_foto = $conn->prepare($sql_foto);
                $stmt_foto->bind_param("i", $_SESSION['usuario_id']);
                $stmt_foto->execute();
                $result_foto = $stmt_foto->get_result();
                $row_foto = $result_foto->fetch_assoc();

                if ($row_foto && !empty($row_foto['foto_perfil']) && $row_foto['foto_perfil'] !== 'uploads/profile/default-profile.png') {
                    $ruta_completa_foto = '../' . $row_foto['foto_perfil'];
                    if (file_exists($ruta_completa_foto)) {
                        unlink($ruta_completa_foto);
                    }
                    $stmt_update_foto = $conn->prepare("UPDATE usuarios SET foto_perfil = 'uploads/profile/default-profile.png' WHERE id = ?");
                    $stmt_update_foto->bind_param("i", $_SESSION['usuario_id']);
                    if ($stmt_update_foto->execute()) {
                        $mensaje_perfil = "Foto de perfil eliminada y reemplazada por la predeterminada.";
                    } else {
                        $error_perfil = "Error al actualizar la foto de perfil en la base de datos.";
                    }
                } else {
                    $error_perfil = "No hay foto de perfil para eliminar o ya es la predeterminada.";
                }
            } else {
                $error_perfil = "No tienes permiso para eliminar la foto de este perfil.";
            }
        }

        // --- Lógica para procesar subida de nueva foto de perfil ---
        if (isset($_FILES['nueva_foto']) && $_FILES['nueva_foto']['error'] == UPLOAD_ERR_OK) {
            if ($es_mi_perfil) {
                $target_dir = "../uploads/profile/";
                $imageFileType = strtolower(pathinfo($_FILES["nueva_foto"]["name"], PATHINFO_EXTENSION));
                $unique_filename = uniqid('profile_') . '.' . $imageFileType;
                $target_file = $target_dir . $unique_filename;
                $db_path = 'uploads/profile/' . $unique_filename;

                $check = getimagesize($_FILES["nueva_foto"]["tmp_name"]);
                if ($check === false) {
                    $error_perfil = "El archivo no es una imagen.";
                } elseif ($_FILES["nueva_foto"]["size"] > 5000000) {
                    $error_perfil = "Lo siento, tu archivo es demasiado grande (máx. 5MB).";
                } elseif (!in_array($imageFileType, ["jpg", "png", "jpeg", "gif"])) {
                    $error_perfil = "Lo siento, solo se permiten archivos JPG, JPEG, PNG & GIF.";
                } else {
                    $sql_foto_anterior = "SELECT foto_perfil FROM usuarios WHERE id = ?";
                    $stmt_foto_anterior = $conn->prepare($sql_foto_anterior);
                    $stmt_foto_anterior->bind_param("i", $_SESSION['usuario_id']);
                    $stmt_foto_anterior->execute();
                    $result_foto_anterior = $stmt_foto_anterior->get_result();
                    $row_foto_anterior = $result_foto_anterior->fetch_assoc();

                    if ($row_foto_anterior && !empty($row_foto_anterior['foto_perfil']) && $row_foto_anterior['foto_perfil'] !== 'uploads/profile/default-profile.png') {
                        $ruta_anterior_completa = '../' . $row_foto_anterior['foto_perfil'];
                        if (file_exists($ruta_anterior_completa)) {
                            unlink($ruta_anterior_completa);
                        }
                    }

                    if (move_uploaded_file($_FILES["nueva_foto"]["tmp_name"], $target_file)) {
                        $stmt_update_foto = $conn->prepare("UPDATE usuarios SET foto_perfil = ? WHERE id = ?");
                        $stmt_update_foto->bind_param("si", $db_path, $_SESSION['usuario_id']);
                        if ($stmt_update_foto->execute()) {
                            $mensaje_perfil = "Foto de perfil subida correctamente.";
                        } else {
                            $error_perfil = "Error al actualizar la foto en la base de datos: " . $stmt_update_foto->error;
                        }
                    } else {
                        $error_perfil = "Hubo un error al subir tu archivo.";
                    }
                }
            } else {
                $error_perfil = "No tienes permiso para subir fotos a este perfil.";
            }
        }

        // --- Lógica para procesar ACCIONES DE AMISTAD ---
        if (isset($_POST['enviar_solicitud']) && isset($_POST['destinatario_id'])) {
            $destinatario_id = intval($_POST['destinatario_id']);

            if ($destinatario_id === $_SESSION['usuario_id']) {
                $error_perfil = "No puedes enviarte una solicitud de amistad a ti mismo.";
            } else {
                $stmt_check_relacion = $conn->prepare("SELECT COUNT(*) FROM solicitudes_amistad WHERE (id_remitente = ? AND id_destinatario = ?) OR (id_remitente = ? AND id_destinatario = ? AND estado = 'pendiente') OR (id_remitente = ? AND id_destinatario = ? AND estado = 'aceptada')");
                $stmt_check_relacion->bind_param("iiiiii", $_SESSION['usuario_id'], $destinatario_id, $destinatario_id, $_SESSION['usuario_id'], $_SESSION['usuario_id'], $destinatario_id);
                $stmt_check_relacion->execute();
                $result_check_relacion = $stmt_check_relacion->get_result();
                $row_check_relacion = $result_check_relacion->fetch_row();

                if ($row_check_relacion[0] > 0) {
                    $error_perfil = "Ya existe una solicitud pendiente o ya son amigos con este usuario.";
                } else {
                    $stmt_enviar_solicitud = $conn->prepare("INSERT INTO solicitudes_amistad (id_remitente, id_destinatario, estado) VALUES (?, ?, 'pendiente')");
                    if ($stmt_enviar_solicitud) {
                        $stmt_enviar_solicitud->bind_param("ii", $_SESSION['usuario_id'], $destinatario_id);
                        if ($stmt_enviar_solicitud->execute()) {
                            $mensaje_perfil = "Solicitud de amistad enviada correctamente.";
                        } else {
                            $error_perfil = "Error al enviar la solicitud de amistad: " . $stmt_enviar_solicitud->error;
                        }
                    } else {
                        $error_perfil = "Error interno al preparar la solicitud de amistad.";
                    }
                }
            }
        }
        
        if (isset($_POST['cancelar_solicitud']) && isset($_POST['solicitud_id'])) {
            $solicitud_id = intval($_POST['solicitud_id']);
            $stmt_cancelar_solicitud = $conn->prepare("DELETE FROM solicitudes_amistad WHERE id = ? AND id_remitente = ? AND estado = 'pendiente'");
            if ($stmt_cancelar_solicitud) {
                $stmt_cancelar_solicitud->bind_param("ii", $solicitud_id, $_SESSION['usuario_id']);
                if ($stmt_cancelar_solicitud->execute()) {
                    if ($stmt_cancelar_solicitud->affected_rows > 0) {
                        $mensaje_perfil = "Solicitud de amistad cancelada.";
                    } else {
                        $error_perfil = "No se encontró la solicitud o no tienes permiso para cancelarla.";
                    }
                } else {
                    $error_perfil = "Error al cancelar la solicitud de amistad: " . $stmt_cancelar_solicitud->error;
                }
            } else {
                $error_perfil = "Error interno al preparar la cancelación de solicitud.";
            }
        }

        if (isset($_POST['eliminar_amigo']) && isset($_POST['amigo_id'])) {
            $amigo_id = intval($_POST['amigo_id']);
            $stmt_eliminar_amigo = $conn->prepare("DELETE FROM solicitudes_amistad WHERE (id_remitente = ? AND id_destinatario = ?) OR (id_remitente = ? AND id_destinatario = ?) AND estado = 'aceptada'");
            if ($stmt_eliminar_amigo) {
                $stmt_eliminar_amigo->bind_param("iiii", $_SESSION['usuario_id'], $amigo_id, $amigo_id, $_SESSION['usuario_id']);
                if ($stmt_eliminar_amigo->execute()) {
                    if ($stmt_eliminar_amigo->affected_rows > 0) {
                        $mensaje_perfil = "Amigo eliminado correctamente.";
                    } else {
                        $error_perfil = "No se encontró la amistad o ya no son amigos.";
                    }
                } else {
                    $error_perfil = "Error al eliminar amigo: " . $stmt_eliminar_amigo->error;
                }
            } else {
                $error_perfil = "Error interno al preparar la eliminación de amigo.";
            }
        }

        if (isset($_POST['eliminar_publicacion']) && isset($_POST['publicacion_id'])) {
            $publicacion_id = intval($_POST['publicacion_id']);
            $stmt_check_post = $conn->prepare("SELECT usuario_id, imagen FROM publicaciones WHERE id = ?");
            if (!$stmt_check_post) {
                $error_perfil = "Error interno al preparar la verificación de publicación: " . $conn->error;
            } else {
                $stmt_check_post->bind_param("i", $publicacion_id);
                $stmt_check_post->execute();
                $result_check_post = $stmt_check_post->get_result();
                $post_data = $result_check_post->fetch_assoc();

                if ($post_data && $post_data['usuario_id'] == $_SESSION['usuario_id']) {
                    if (!empty($post_data['imagen'])) {
                        $ruta_imagen_completa = '../' . $post_data['imagen'];
                        if (file_exists($ruta_imagen_completa)) {
                            unlink($ruta_imagen_completa);
                        }
                    }

                    $stmt_delete_post = $conn->prepare("DELETE FROM publicaciones WHERE id = ?");
                    if ($stmt_delete_post) {
                        $stmt_delete_post->bind_param("i", $publicacion_id);
                        if ($stmt_delete_post->execute()) {
                            $mensaje_perfil = "Publicación eliminada correctamente.";
                        } else {
                            $error_perfil = "Error al eliminar la publicación: " . $stmt_delete_post->error;
                        }
                    } else {
                        $error_perfil = "Error interno al preparar la eliminación de publicación.";
                    }
                } else {
                    $error_perfil = "No tienes permiso para eliminar esta publicación o no existe.";
                }
            }
        }
    } 
} 

// ... tu código PHP existente ...

// Obtener información del perfil (después de posibles actualizaciones)
$sql = "SELECT id, nombre, email, foto_perfil, fecha_registro, biografia FROM usuarios WHERE id = ?";
$stmt_usuario = $conn->prepare($sql);
if (!$stmt_usuario) {
    die("Error al preparar la consulta de usuario: " . $conn->error);
}
$stmt_usuario->bind_param("i", $perfil_id);
$stmt_usuario->execute();
$result_usuario = $stmt_usuario->get_result();

if ($result_usuario->num_rows == 0) {
    die("Usuario no encontrado.");
}
$usuario = $result_usuario->fetch_assoc();

// --- NUEVA LÓGICA: Verificar y corregir la foto de perfil al cargar la página si el archivo físico no existe ---
$ruta_foto_absoluta_servidor = '';
$foto_perfil_a_mostrar = '';

if (!empty($usuario['foto_perfil'])) {
    // Construir la ruta absoluta en el servidor
    // __DIR__ es la ubicación de perfil.php (ej: /var/www/html/red_social/vistas)
    // /../ sube un nivel (ej: /var/www/html/red_social)
    // $usuario['foto_perfil'] es la ruta relativa desde la raíz del proyecto (ej: uploads/profile/mi_foto.png)
    $ruta_foto_absoluta_servidor = realpath(__DIR__ . '/../' . $usuario['foto_perfil']); 
    
    // Verificar si la foto de perfil registrada en la DB existe en el servidor y no es la predeterminada
    // Si la foto_perfil en la DB no es la predeterminada Y el archivo no existe o no es un archivo válido
    if ($usuario['foto_perfil'] !== 'uploads/profile/default-profile.png' && (!file_exists($ruta_foto_absoluta_servidor) || !is_file($ruta_foto_absoluta_servidor))) {
        // La foto de perfil registrada en la DB no existe en el servidor o no es un archivo.
        // Corregir la base de datos para usar la foto por defecto.
        $stmt_reset_foto = $conn->prepare("UPDATE usuarios SET foto_perfil = 'uploads/profile/default-profile.png' WHERE id = ?");
        if ($stmt_reset_foto) {
            $stmt_reset_foto->bind_param("i", $usuario['id']);
            $stmt_reset_foto->execute();
            // No es necesario verificar el resultado de execute aquí, si falla, simplemente la foto_perfil_a_mostrar
            // seguirá siendo la por defecto, lo cual es el comportamiento deseado en caso de error.
        }
        $foto_perfil_a_mostrar = '../uploads/profile/default-profile.png';
    } else {
        // La foto existe en el servidor o ya es la por defecto
        $foto_perfil_a_mostrar = '../' . htmlspecialchars($usuario['foto_perfil']);
    }
} else {
    // No hay ruta de foto en la DB, usar la por defecto
    $foto_perfil_a_mostrar = '../uploads/profile/default-profile.png';
}
// --- FIN DE LA NUEVA LÓGICA ---

// Recargar la página si se actualizó la foto para ver el cambio instantáneamente
if (isset($_POST['eliminar_foto']) || (isset($_FILES['nueva_foto']) && $_FILES['nueva_foto']['error'] == UPLOAD_ERR_OK)) {
    if (empty($error_perfil)) { // Solo recargar si no hubo error
        header("Location: perfil.php");
        exit();
    }
}


// Obtener solicitudes de amistad pendientes para notificaciones (si aplica)
$solicitudes_pendientes = 0;
if ($es_mi_perfil) {
    $sql_solicitudes = "SELECT COUNT(*) as count FROM solicitudes_amistad
                       WHERE id_destinatario = ? AND estado = 'pendiente'";
    $stmt_solicitudes = $conn->prepare($sql_solicitudes);
    if ($stmt_solicitudes) {
        $stmt_solicitudes->bind_param("i", $_SESSION['usuario_id']);
        $stmt_solicitudes->execute();
        $result_solicitudes = $stmt_solicitudes->get_result();
        $solicitudes_pendientes = $result_solicitudes->fetch_assoc()['count'];
    }
}

// Generar CSRF token para los formularios
$csrf_token = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil de <?php echo htmlspecialchars($usuario['nombre']); ?></title>
    
    <style>
        :root {
            --color-primario: #007bff;
            --color-primario-hover: #0056b3;
            --color-success: #28a745;
            --color-danger: #dc3545;
            --color-danger-hover: #c82333;
            --color-info: #17a2b8;
            --color-info-hover: #138496;
            --color-fondo: #f0f2f5;
            --color-surface: #ffffff;
            --color-texto: #333;
            --color-texto-secundario: #777;
            --border-radius: 8px;
            --box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            margin: 0;
            background-color: var(--color-fondo);
            color: var(--color-texto);
            line-height: 1.6;
        }

        .header-nav {
            background-color: #333;
            color: white;
            padding: 1rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
        }

        .header-nav .logo a { color: white; text-decoration: none; font-size: 1.5rem; font-weight: bold; }
        .header-nav .nav-links { display: flex; flex-wrap: wrap; justify-content: center; gap: 1rem; }
        .nav-links a { color: white; text-decoration: none; padding: 0.5rem; border-radius: 4px; transition: background-color 0.2s ease; }
        .nav-links a:hover, .nav-links a:focus { background-color: rgba(255, 255, 255, 0.1); }
        .notification-badge { background-color: var(--color-danger); color: white; border-radius: 50%; padding: 0.1em 0.5em; font-size: 0.8rem; margin-left: -5px; vertical-align: top; }
        .main-container { width: 100%; max-width: 900px; margin: 1rem auto; padding: 0 1rem; }
        .profile-container { background-color: var(--color-surface); border-radius: var(--border-radius); box-shadow: var(--box-shadow); padding: 1.5rem; }
        
        .message { padding: 1rem; margin-bottom: 1rem; border-radius: var(--border-radius); font-weight: 500; text-align: center; border: 1px solid transparent; }
        .message.success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }

        .profile-header { text-align: center; margin-bottom: 2rem; }
        .profile-picture-wrapper { position: relative; width: 150px; height: 150px; margin: 0 auto 1rem; }
        .profile-picture { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; border: 5px solid #ddd; }
        .profile-name { font-size: 2rem; color: var(--color-texto); margin: 0; font-weight: 600; }
        .profile-date { color: var(--color-texto-secundario); font-size: 0.9rem; }

        .profile-actions { margin-top: 1.5rem; display: flex; flex-direction: column; align-items: center; gap: 1rem; }
        .profile-actions button, .profile-actions a {
            display: inline-block; width: 100%; max-width: 320px; background-color: var(--color-primario);
            color: white; border: none; padding: 0.75rem 1rem; border-radius: var(--border-radius);
            cursor: pointer; text-decoration: none; text-align: center; font-size: 1rem;
            font-weight: 500; transition: background-color 0.2s ease, transform 0.1s ease;
        }
        .profile-actions button:hover, .profile-actions a:hover { background-color: var(--color-primario-hover); transform: translateY(-2px); }
        .profile-actions .edit-profile-button { background-color: var(--color-success); }
        .profile-actions .delete-button { background-color: var(--color-danger); }
        .profile-actions .delete-button:hover { background-color: var(--color-danger-hover); }
        
        .profile-section { width: 100%; margin-top: 2.5rem; padding-top: 1.5rem; border-top: 1px solid #eee; }
        .profile-section h3 { text-align: center; color: #444; margin-top: 0; margin-bottom: 2rem; font-weight: 600; }
        
        .form-section { background-color: #f9f9f9; padding: 1.5rem; border-radius: var(--border-radius); box-shadow: inset 0 0 5px rgba(0,0,0,0.05); margin-bottom: 2rem; }
        .form-section label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: #555; }
        .form-section input[type="text"], .form-section input[type="email"], .form-section input[type="password"], .form-section input[type="file"] {
            width: 100%; padding: 0.75rem; margin-bottom: 1rem; border: 1px solid #ccc; border-radius: 4px; font-size: 1rem;
        }
        .form-section input[type="file"] { padding: 0.5rem; }
        .form-section button {
            width: 100%; padding: 0.8rem 1rem; border: none; border-radius: var(--border-radius); color: white;
            background-color: var(--color-primario); font-size: 1rem; font-weight: 500; cursor: pointer;
            transition: background-color 0.2s ease;
        }
        .form-section button:hover { background-color: var(--color-primario-hover); }
        
        .form-photo-upload { text-align: center; }
        .form-photo-upload button { background-color: var(--color-info); }
        .form-photo-upload .delete-photo { background-color: var(--color-danger); margin-top: 0.5rem; }
        .form-photo-upload .delete-photo:hover { background-color: var(--color-danger-hover); }

        .toggle-gestion-button {
            background-color: var(--color-info);
            color: white; border: none; padding: 0.75rem 1.5rem; border-radius: var(--border-radius);
            cursor: pointer; font-size: 1rem; font-weight: 500;
            transition: background-color 0.2s ease;
            display: inline-block;
            width: auto;
        }
        .toggle-gestion-button:hover { background-color: var(--color-info-hover); }

        .post { background-color: var(--color-surface); border: 1px solid #e0e0e0; border-radius: var(--border-radius); padding: 1rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05); position: relative; }
        .post p { margin: 0 0 1rem 0; word-wrap: break-word; }
        .post-image img { max-width: 100%; height: auto; border-radius: var(--border-radius); margin-top: 1rem; }
        .post small { color: var(--color-texto-secundario); font-size: 0.85rem; }
        .delete-post-button { position: absolute; top: 1rem; right: 1rem; background-color: var(--color-danger); color: white; border: none; padding: 0.3rem 0.6rem; border-radius: 4px; cursor: pointer; font-size: 0.8rem; opacity: 0.8; transition: opacity 0.2s ease, background-color 0.2s ease; }
        .delete-post-button:hover { background-color: var(--color-danger-hover); opacity: 1; }

        @media (min-width: 768px) {
            .header-nav { flex-direction: row; justify-content: space-between; padding: 1rem 2rem; }
            .header-nav .nav-links { gap: 1.5rem; }
            .main-container { padding: 0 2rem; margin: 2rem auto; }
            .profile-container { padding: 2.5rem; }
            .profile-actions { flex-direction: row; justify-content: center; }
            .profile-actions button, .profile-actions a { width: auto; }
            .form-section button { width: auto; min-width: 150px; }
            .edit-forms-container { display: flex; flex-direction: column; gap: 2rem; }
        }
        
        @media (min-width: 992px) {
            .edit-forms-container { flex-direction: row; }
            .form-section { flex: 1; }
        }
    </style>
</head>
<body>
    <header class="header-nav">
        <div class="logo">
            <a href="feed.php">ByteGol</a>
        </div>
        <nav class="nav-links">
            <a href="feed.php">Inicio</a>
            <a href="perfil.php">Mi Perfil</a>
            <a href="amigos.php">
                Amigos
                <?php if ($solicitudes_pendientes > 0): ?>
                    <span class="notification-badge"><?php echo $solicitudes_pendientes; ?></span>
                <?php endif; ?>
            </a>
            <a href="buscar.php">Buscar</a>
            <?php if (isset($_SESSION['rol']) && in_array($_SESSION['rol'], ['admin', 'superadmin'])): ?>
                <a href="../admin/gestionar_usuarios.php" style="color: #e74c3c; font-weight: bold;">Panel Admin</a>
            <?php endif; ?>
            <a href="../logueo/logout.php">Cerrar Sesión</a>
        </nav>
    </header>

    <main class="main-container">
        <div class="profile-container">
            <?php if (!empty($mensaje_perfil)): ?>
                <div class="message success"><?php echo htmlspecialchars($mensaje_perfil); ?></div>
            <?php endif; ?>
            <?php if (!empty($error_perfil)): ?>
                <div class="message error"><?php echo htmlspecialchars($error_perfil); ?></div>
            <?php endif; ?>

           <header class="profile-header">
                <div class="profile-picture-wrapper">
                     <img src="<?php echo $foto_perfil_a_mostrar; ?>" alt="Foto de perfil" class="profile-picture">
                </div>
                <h1 class="profile-name"><?php echo htmlspecialchars($usuario['nombre']); ?></h1>
                <p class="profile-date">Miembro desde: <?php echo htmlspecialchars(date("d/m/Y", strtotime($usuario['fecha_registro']))); ?></p>
            </header>

            <?php if (!$es_mi_perfil): ?>
                <?php
                $son_amigos = false;
                $solicitud_pendiente = false;
                $solicitud_recibida = false;
                $solicitud_id_amistad = null;

                $stmt_amistad = $conn->prepare("SELECT id, estado, id_remitente FROM solicitudes_amistad WHERE (id_remitente = ? AND id_destinatario = ?) OR (id_remitente = ? AND id_destinatario = ?)");
                if ($stmt_amistad) {
                    $stmt_amistad->bind_param("iiii", $_SESSION['usuario_id'], $perfil_id, $perfil_id, $_SESSION['usuario_id']);
                    $stmt_amistad->execute();
                    $result_amistad = $stmt_amistad->get_result();
                    $relacion = $result_amistad->fetch_assoc();

                    if ($relacion) {
                        $solicitud_id_amistad = $relacion['id'];
                        if ($relacion['estado'] === 'aceptada') {
                            $son_amigos = true;
                        } elseif ($relacion['estado'] === 'pendiente') {
                            if ($relacion['id_remitente'] === $_SESSION['usuario_id']) {
                                $solicitud_pendiente = true;
                            } else {
                                $solicitud_recibida = true;
                            }
                        }
                    }
                }
                ?>
                <div class="profile-actions">
                    <?php if ($son_amigos): ?>
                        <a href="chat.php?id=<?php echo $perfil_id; ?>" class="chat-button">Chatear</a>
                        <form method="post" style="margin:0;">
                            <input type="hidden" name="amigo_id" value="<?php echo $perfil_id; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <button type="submit" name="eliminar_amigo" class="delete-button" onclick="return confirm('¿Estás seguro de que quieres eliminar a este amigo?');">Eliminar Amigo</button>
                        </form>
                    <?php elseif ($solicitud_pendiente): ?>
                        <p>Solicitud de amistad enviada.</p>
                        <form method="post" style="margin:0;">
                            <input type="hidden" name="solicitud_id" value="<?php echo htmlspecialchars($solicitud_id_amistad); ?>">
                            <input type="hidden" name="cancelar_solicitud" value="1">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <button type="submit" class="delete-button">Cancelar Solicitud</button>
                        </form>
                    <?php elseif ($solicitud_recibida): ?>
                        <p>Tienes una solicitud de amistad de este usuario.</p>
                        <a href="amigos.php" class="edit-profile-button">Responder Solicitud</a>
                    <?php else: ?>
                        <form method="post" style="margin:0;">
                            <input type="hidden" name="destinatario_id" value="<?php echo $perfil_id; ?>">
                            <input type="hidden" name="enviar_solicitud" value="1">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <button type="submit">Enviar Solicitud de Amistad</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($es_mi_perfil): ?>
                <div class="profile-section">
                    <div style="text-align: center; margin-bottom: 1.5rem;">
                         <button id="toggleGestionBtn" class="toggle-gestion-button">Gestionar Mi Perfil</button>
                    </div>
                    
                    <div id="gestionPerfilContainer" style="display: none;">
                        <div class="form-section form-photo-upload">
                             <form action="perfil.php" method="post" enctype="multipart/form-data" style="width:100%;">
                                <label for="nueva_foto">Cambiar Foto de Perfil:</label>
                                <input type="file" name="nueva_foto" id="nueva_foto" accept="image/*">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                <button type="submit">Subir Nueva Foto</button>
                            </form>
                            <form action="perfil.php" method="post" style="width:100%; margin-top: 1rem;">
                                <input type="hidden" name="eliminar_foto" value="1">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                <button type="submit" class="delete-photo" onclick="return confirm('¿Estás seguro de que quieres eliminar tu foto de perfil? Se usará una por defecto.');">Eliminar Foto Actual</button>
                            </form>
                        </div>
                        
                        <div class="edit-forms-container">
                            <div class="form-section">
                                 <form action="perfil.php" method="post">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                    <input type="hidden" name="actualizar_perfil" value="1">
                                    <label for="nombre">Nombre:</label>
                                    <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($usuario['nombre']); ?>" required>
                                    <label for="email">Email:</label>
                                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($usuario['email']); ?>" required>
                                    <button type="submit">Guardar Cambios</button>
                                </form>
                            </div>

                            <div class="form-section">
                                <form action="perfil.php" method="post">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                    <input type="hidden" name="cambiar_contrasena" value="1">
                                    <label for="current_password">Contraseña Actual:</label>
                                    <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
                                    <label for="new_password">Nueva Contraseña:</label>
                                    <input type="password" id="new_password" name="new_password" required autocomplete="new-password">
                                    <label for="confirm_new_password">Confirmar Nueva Contraseña:</label>
                                    <input type="password" id="confirm_new_password" name="confirm_new_password" required autocomplete="new-password">
                                    <button type="submit">Cambiar Contraseña</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="profile-section">
                <h3>Publicaciones</h3>
                <?php
                $sql_publicaciones = "SELECT id, contenido, imagen, fecha_publicacion, usuario_id FROM publicaciones WHERE usuario_id = ? ORDER BY fecha_publicacion DESC";
                $stmt_publicaciones = $conn->prepare($sql_publicaciones);
                if (!$stmt_publicaciones) {
                    echo "<p class='message error'>Error al cargar publicaciones: " . $conn->error . "</p>";
                } else {
                    $stmt_publicaciones->bind_param("i", $perfil_id);
                    $stmt_publicaciones->execute();
                    $result_publicaciones = $stmt_publicaciones->get_result();

                    if ($result_publicaciones->num_rows > 0) {
                        while($row = $result_publicaciones->fetch_assoc()) {
                            $publicacion_id = $row['id'];
                            $es_mi_publicacion = ($row['usuario_id'] == $_SESSION['usuario_id']);

                            echo "<div class='post'>";

                            if ($es_mi_publicacion) {
                                echo "<form method='post' style='margin:0;'>";
                                echo "<input type='hidden' name='publicacion_id' value='$publicacion_id'>";
                                echo "<input type='hidden' name='csrf_token' value='" . htmlspecialchars($csrf_token) . "'>";
                                echo "<button type='submit' name='eliminar_publicacion' class='delete-post-button' title='Eliminar publicación' onclick='return confirm(\"¿Estás seguro de que quieres eliminar esta publicación?\")'>&times;</button>";
                                echo "</form>";
                            }

                            echo "<p>" . nl2br(htmlspecialchars($row['contenido'])) . "</p>";

                            if (!empty($row['imagen'])) {
                                echo "<div class='post-image'>";
                                echo "<img src='../" . htmlspecialchars($row['imagen']) . "' alt='Imagen de la publicación'>";
                                echo "</div>";
                            }
                            
                            echo "<small>" . htmlspecialchars(date("d/m/Y, H:i", strtotime($row['fecha_publicacion']))) . "</small>";
                            echo "</div>";
                        }
                    } else {
                        echo "<p style='text-align: center; color: var(--color-texto-secundario);'>Este usuario no tiene publicaciones aún.</p>";
                    }
                }
                ?>
            </div>
        </div>
    </main>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Busca el botón y el contenedor solo si el usuario está viendo su propio perfil
        const toggleBtn = document.getElementById('toggleGestionBtn');
        const formsContainer = document.getElementById('gestionPerfilContainer');

        // Si ambos elementos existen, añade la funcionalidad de toggle
        if (toggleBtn && formsContainer) {
            toggleBtn.addEventListener('click', function() {
                // Comprueba el estado actual del display
                const isHidden = formsContainer.style.display === 'none' || formsContainer.style.display === '';
                
                if (isHidden) {
                    formsContainer.style.display = 'block'; // Muestra el contenedor
                    toggleBtn.textContent = 'Ocultar Gestión'; // Cambia el texto del botón
                } else {
                    formsContainer.style.display = 'none'; // Oculta el contenedor
                    toggleBtn.textContent = 'Gestionar Mi Perfil'; // Restaura el texto del botón
                }
            });

            // Si hay un mensaje de error o éxito dentro de la sección de gestión, la mostramos por defecto
            // para que el usuario vea el resultado de su acción.
            const messageInside = formsContainer.querySelector('.message.error, .message.success');
            if(messageInside) {
                formsContainer.style.display = 'block';
                toggleBtn.textContent = 'Ocultar Gestión';
            }
        }
    });
    </script>

</body>
</html>