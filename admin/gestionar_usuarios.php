<?php
require_once 'verificar_admin.php'; // Ahora verifica 'admin' o 'superadmin'.
require_once '../db/config.php';

$mensaje = '';
$error = '';

// --- Lógica para manejar las acciones del formulario (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['usuario_id'])) {
    $usuario_id = (int)$_POST['usuario_id'];
    $loggedInUserRole = $_SESSION['rol']; // Rol del admin que realiza la acción.

    // Proteger contra auto-modificación del propio administrador logueado.
    if ($usuario_id === $_SESSION['usuario_id']) {
        $_SESSION['admin_error'] = "No puedes modificar tu propia cuenta desde aquí.";
        header('Location: gestionar_usuarios.php');
        exit();
    }

    // Validación del token CSRF.
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['admin_error'] = "Error de seguridad: Token CSRF inválido.";
        header('Location: gestionar_usuarios.php');
        exit();
    }

    // Obtener el rol del usuario que se va a modificar (usuario objetivo).
    $stmt_target = $conn->prepare("SELECT rol FROM usuarios WHERE id = ?");
    $stmt_target->bind_param("i", $usuario_id);
    $stmt_target->execute();
    $target_user = $stmt_target->get_result()->fetch_assoc();
    $target_user_role = $target_user ? $target_user['rol'] : null;

    // --- LÓGICA DE PERMISOS ---
    // Un 'admin' no puede modificar a otro 'admin' o a un 'superadmin'.
    if ($loggedInUserRole === 'admin' && ($target_user_role === 'admin' || $target_user_role === 'superadmin')) {
        $_SESSION['admin_error'] = "No tienes permisos para modificar a otro administrador.";
        header('Location: gestionar_usuarios.php');
        exit();
    }
    // Nadie puede modificar a un 'superadmin'.
    // Esta es la parte que ya existe y previene la modificación de superadmin a nivel de POST.
    if ($target_user_role === 'superadmin') {
        $_SESSION['admin_error'] = "El rol de SuperAdmin no puede ser modificado o eliminado.";
        header('Location: gestionar_usuarios.php');
        exit();
    }

    // --- ACCIONES ---
    if (isset($_POST['cambiar_rol'])) {
        // Solo el superadmin puede cambiar roles.
        if ($loggedInUserRole !== 'superadmin') {
            $_SESSION['admin_error'] = "No tienes permisos para cambiar roles.";
            header('Location: gestionar_usuarios.php');
            exit();
        }
        $nuevo_rol = $_POST['cambiar_rol'] === 'admin' ? 'admin' : 'usuario';
        $stmt = $conn->prepare("UPDATE usuarios SET rol = ? WHERE id = ?");
        $stmt->bind_param("si", $nuevo_rol, $usuario_id);
        if ($stmt->execute()) {
            $_SESSION['admin_message'] = "Rol del usuario actualizado.";
        } else {
            $_SESSION['admin_error'] = "Error al actualizar el rol: " . $stmt->error;
        }
        header('Location: gestionar_usuarios.php');
        exit();

    } elseif (isset($_POST['cambiar_estado'])) {
        $nuevo_estado = $_POST['cambiar_estado'] === 'suspendido' ? 'suspendido' : 'activo';
        $stmt = $conn->prepare("UPDATE usuarios SET estado = ? WHERE id = ?");
        $stmt->bind_param("si", $nuevo_estado, $usuario_id);
        if ($stmt->execute()) {
            $_SESSION['admin_message'] = "Estado del usuario actualizado.";
        } else {
            $_SESSION['admin_error'] = "Error al actualizar el estado: " . $stmt->error;
        }
        header('Location: gestionar_usuarios.php');
        exit();

    } elseif (isset($_POST['eliminar_usuario'])) {
        // La lógica de permisos ya se comprobó arriba, así que procedemos.
        $conn->begin_transaction();
        try {
            $stmt_foto = $conn->prepare("SELECT foto_perfil FROM usuarios WHERE id = ?");
            $stmt_foto->bind_param("i", $usuario_id);
            $stmt_foto->execute();
            $result_foto = $stmt_foto->get_result();
            $row_foto = $result_foto->fetch_assoc();

            if ($row_foto && !empty($row_foto['foto_perfil']) && $row_foto['foto_perfil'] !== 'uploads/profile/default-profile.png' && file_exists('../' . $row_foto['foto_perfil'])) {
                unlink('../' . $row_foto['foto_perfil']);
            }

            $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
            $stmt->bind_param("i", $usuario_id);
            if ($stmt->execute()) {
                $conn->commit();
                $_SESSION['admin_message'] = "Usuario y todo su contenido eliminado correctamente.";
            } else {
                throw new Exception("Error al eliminar el usuario de la base de datos: " . $stmt->error);
            }
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['admin_error'] = "Error al eliminar el usuario: " . $e->getMessage();
        }
        header('Location: gestionar_usuarios.php');
        exit();
    }
}

// --- Recuperar mensajes y errores de la sesión ---
if (isset($_SESSION['admin_message'])) {
    $mensaje = $_SESSION['admin_message'];
    unset($_SESSION['admin_message']);
}
if (isset($_SESSION['admin_error'])) {
    $error = $_SESSION['admin_error'];
    unset($_SESSION['admin_error']);
}

// --- Obtener todos los usuarios para mostrar en la tabla ---
$sql_usuarios = "SELECT id, nombre, email, rol, estado, fecha_registro, foto_perfil FROM usuarios ORDER BY id ASC";
$result_usuarios = $conn->query($sql_usuarios);
$usuarios = [];
if ($result_usuarios) {
    while ($row = $result_usuarios->fetch_assoc()) {
        $usuarios[] = $row;
    }
} else {
    $error = "Error al cargar los usuarios: " . $conn->error;
}

$csrf_token = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrar Usuarios</title>
    <link rel="stylesheet" href="style_admin.css">
    </head>
<body>
    <nav> <a href="gestionar_usuarios.php" style="color: white; margin: 0 15px; text-decoration: none; font-weight: bold;">Administrar Usuarios</a>
        <a href="gestionar_publicaciones.php" style="color: white; margin: 0 15px; text-decoration: none; font-weight: bold;">Administrar Publicaciones</a>
        <a href="../publicaciones/feed.php" style="color: white; margin: 0 15px; text-decoration: none;">Ir al Feed</a>
        <a href="../logueo/logout.php" style="color: white; margin: 0 15px; text-decoration: none;">Cerrar Sesión</a>
    </nav>

    <div class="admin-container">
        <h2>Administrar Usuarios</h2>

        <?php if (!empty($mensaje)): ?><div class="message success"><?php echo htmlspecialchars($mensaje); ?></div><?php endif; ?>
        <?php if (!empty($error)): ?><div class="message error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <table class="table-users">
            <thead>
                <tr>
                    <th>ID</th><th>Foto</th><th>Nombre</th><th>Email</th><th>Rol</th><th>Estado</th><th>Registro</th><th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($usuarios)): ?>
                    <tr><td colspan="8" style="text-align: center;">No hay usuarios registrados.</td></tr>
                <?php endif; ?>
                <?php foreach ($usuarios as $usuario): ?>
                    <?php
                        // --- Lógica de visualización de permisos ---
                        $isSuperAdmin = $_SESSION['rol'] === 'superadmin';
                        $isSelf = $usuario['id'] === $_SESSION['usuario_id'];
                        $targetIsSuperAdmin = $usuario['rol'] === 'superadmin';
                        $targetIsAdmin = $usuario['rol'] === 'admin';
                        $targetIsUser = $usuario['rol'] === 'usuario';

                        // ¿Se puede cambiar el rol? Solo si eres SuperAdmin y NO es un SuperAdmin (el objetivo)
                        $canChangeRole = $isSuperAdmin && !$targetIsSuperAdmin;
                        // ¿Se puede cambiar el estado? Si eres SuperAdmin (a todos excepto a ti mismo y al SuperAdmin objetivo) O si eres Admin y el objetivo es un usuario
                        $canChangeStatus = !$isSelf && !$targetIsSuperAdmin && ($isSuperAdmin || (!$targetIsAdmin && !$targetIsSuperAdmin));
                        // ¿Se puede eliminar? Igual que cambiar estado
                        $canDelete = !$isSelf && !$targetIsSuperAdmin && ($isSuperAdmin || (!$targetIsAdmin && !$targetIsSuperAdmin));
                    ?>
                    <tr>
                        <td data-label="ID"><?php echo htmlspecialchars($usuario['id']); ?></td>
                        <td data-label="Foto">
                            <div class="user-details">
                                <img src="<?php echo !empty($usuario['foto_perfil']) ? '../' . htmlspecialchars($usuario['foto_perfil']) : '../uploads/profile/default-profile.png'; ?>" alt="Foto de perfil">
                            </div>
                        </td>
                        <td data-label="Nombre"><?php echo htmlspecialchars($usuario['nombre']); ?></td>
                        <td data-label="Email"><?php echo htmlspecialchars($usuario['email']); ?></td>
                        <td data-label="Rol">
                            <?php if ($isSelf): ?>
                                <span class="self-account-label"><?php echo htmlspecialchars($usuario['rol']); ?> (Tu cuenta)</span>
                            <?php elseif ($canChangeRole): ?>
                                <form method="POST" style="display: inline-block;">
                                    <input type="hidden" name="usuario_id" value="<?php echo $usuario['id']; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                    <select name="cambiar_rol" onchange="this.form.submit()">
                                        <option value="usuario" <?php echo $usuario['rol'] === 'usuario' ? 'selected' : ''; ?>>Usuario</option>
                                        <option value="admin" <?php echo $usuario['rol'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    </select>
                                </form>
                            <?php else: ?>
                                <span><?php echo htmlspecialchars($usuario['rol']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Estado">
                            <?php if ($isSelf): ?>
                                <span class="self-account-label"><?php echo htmlspecialchars($usuario['estado']); ?> (Tu cuenta)</span>
                            <?php elseif ($canChangeStatus): ?>
                                <form method="POST" style="display: inline-block;">
                                    <input type="hidden" name="usuario_id" value="<?php echo $usuario['id']; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                    <select name="cambiar_estado" onchange="this.form.submit()">
                                        <option value="activo" <?php echo $usuario['estado'] === 'activo' ? 'selected' : ''; ?>>Activo</option>
                                        <option value="suspendido" <?php echo $usuario['estado'] === 'suspendido' ? 'selected' : ''; ?>>Suspendido</option>
                                    </select>
                                </form>
                            <?php else: ?>
                                <span class="self-account-label">(No editable)</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Registro"><?php echo htmlspecialchars($usuario['fecha_registro']); ?></td>
                        <td data-label="Acciones">
                            <?php if ($isSelf): ?>
                                <span class="self-account-label">(No editable)</span>
                            <?php elseif ($canDelete): ?>
                                <form method="POST" onsubmit="return confirm('¡ADVERTENCIA!\nEstás a punto de eliminar a este usuario y todo su contenido.\nEsta acción no se puede deshacer.\n\n¿Continuar?');">
                                    <input type="hidden" name="usuario_id" value="<?php echo $usuario['id']; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                    <button type="submit" name="eliminar_usuario" class="btn-delete">Eliminar</button>
                                </form>
                            <?php else: ?>
                                <span class="self-account-label">(No editable)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>