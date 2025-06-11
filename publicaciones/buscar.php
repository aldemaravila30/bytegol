<?php
// buscar.php
// Lógica para buscar usuarios y gestionar solicitudes de amistad.

// Incluir la configuración de la base de datos y utilidades.
require_once '../db/config.php';
require_once __DIR__ . '/../db/utils.php'; // Incluye el archivo de utilidades para sanitize_input()

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../logueo/login.php");
    exit();
}

$busqueda = filter_input(INPUT_GET, 'q', FILTER_SANITIZE_STRING) ?? '';
$usuarios = []; // Inicializa un array vacío para los resultados
$usuario_id_sesion = $_SESSION['usuario_id'];

// --- Lógica para buscar usuarios ---
if (!empty($busqueda)) {
    // Determinar si la búsqueda es un correo electrónico o un nombre
    if (filter_var($busqueda, FILTER_VALIDATE_EMAIL)) {
        // Si es un correo válido, buscar exactamente ese correo
        $sql = "SELECT id, nombre, foto_perfil FROM usuarios WHERE id != ? AND email = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("is", $usuario_id_sesion, $busqueda);
        } else {
            $_SESSION['error'] = "Error de preparación de la consulta de búsqueda por email: " . $conn->error;
        }
    } else {
        // Si no es un correo, buscar por nombre
        $sql = "SELECT id, nombre, foto_perfil FROM usuarios WHERE id != ? AND nombre LIKE CONCAT('%', ?, '%')";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("is", $usuario_id_sesion, $busqueda);
        } else {
            $_SESSION['error'] = "Error de preparación de la consulta de búsqueda por nombre: " . $conn->error;
        }
    }

    if (isset($stmt) && $stmt) {
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $usuarios[] = $row;
        }
        $stmt->close();
    }
}

// --- Lógica para obtener el estado de amistad de cada usuario encontrado ---
// Esto se hace en un bucle separado para cada usuario encontrado
// Esto puede ser ineficiente si hay muchísimos usuarios, pero es manejable para un buscador interactivo.
// Si el número de resultados es muy grande, se podría optimizar con un JOIN.
foreach ($usuarios as &$usuario) { // Usar & para modificar el array original
    $target_user_id = $usuario['id'];

    // 1. Verificar si ya son amigos
    $sql_amigos = "SELECT COUNT(*) FROM amigos WHERE (id_usuario1 = ? AND id_usuario2 = ?) OR (id_usuario1 = ? AND id_usuario2 = ?)";
    $stmt_amigos = $conn->prepare($sql_amigos);
    if ($stmt_amigos) {
        $stmt_amigos->bind_param("iiii", $usuario_id_sesion, $target_user_id, $target_user_id, $usuario_id_sesion);
        $stmt_amigos->execute();
        $stmt_amigos->bind_result($count_amigos);
        $stmt_amigos->fetch();
        $stmt_amigos->close();
        $usuario['son_amigos'] = ($count_amigos > 0);
    } else {
        $usuario['son_amigos'] = false;
        error_log("Error de preparación de la consulta de amistad: " . $conn->error); // Log de error
    }

    // 2. Verificar si hay una solicitud pendiente enviada por el usuario actual
    $sql_solicitud_enviada = "SELECT COUNT(*) FROM solicitudes_amistad WHERE id_remitente = ? AND id_destinatario = ? AND estado = 'pendiente'";
    $stmt_solicitud_enviada = $conn->prepare($sql_solicitud_enviada);
    if ($stmt_solicitud_enviada) {
        $stmt_solicitud_enviada->bind_param("ii", $usuario_id_sesion, $target_user_id);
        $stmt_solicitud_enviada->execute();
        $stmt_solicitud_enviada->bind_result($count_enviada);
        $stmt_solicitud_enviada->fetch();
        $stmt_solicitud_enviada->close();
        $usuario['solicitud_enviada'] = ($count_enviada > 0);
    } else {
        $usuario['solicitud_enviada'] = false;
        error_log("Error de preparación de la consulta de solicitud enviada: " . $conn->error);
    }

    // 3. Verificar si hay una solicitud pendiente recibida por el usuario actual
    $sql_solicitud_recibida = "SELECT COUNT(*) FROM solicitudes_amistad WHERE id_remitente = ? AND id_destinatario = ? AND estado = 'pendiente'";
    $stmt_solicitud_recibida = $conn->prepare($sql_solicitud_recibida);
    if ($stmt_solicitud_recibida) {
        $stmt_solicitud_recibida->bind_param("ii", $target_user_id, $usuario_id_sesion);
        $stmt_solicitud_recibida->execute();
        $stmt_solicitud_recibida->bind_result($count_recibida);
        $stmt_solicitud_recibida->fetch();
        $stmt_solicitud_recibida->close();
        $usuario['solicitud_recibida'] = ($count_recibida > 0);
    } else {
        $usuario['solicitud_recibida'] = false;
        error_log("Error de preparación de la consulta de solicitud recibida: " . $conn->error);
    }
}
unset($usuario); // Romper la referencia del último elemento

// --- Obtener solicitudes de amistad pendientes para notificaciones en el nav ---
$sql_solicitudes_pendientes_nav = "SELECT COUNT(*) as count FROM solicitudes_amistad
                                   WHERE id_destinatario = ? AND estado = 'pendiente'";
$stmt_solicitudes_pendientes_nav = $conn->prepare($sql_solicitudes_pendientes_nav);
$solicitudes_pendientes_nav = 0;
if ($stmt_solicitudes_pendientes_nav) {
    $stmt_solicitudes_pendientes_nav->bind_param("i", $usuario_id_sesion);
    $stmt_solicitudes_pendientes_nav->execute();
    $result_solicitudes_pendientes_nav = $stmt_solicitudes_pendientes_nav->get_result();
    $solicitudes_pendientes_nav = $result_solicitudes_pendientes_nav->fetch_assoc()['count'];
    $stmt_solicitudes_pendientes_nav->close();
} else {
    error_log("Error al obtener solicitudes de amistad para el nav: " . $conn->error);
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Buscar Usuarios - ByteGol</title>
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
            <a href="buscar.php" class="active"><i class="fas fa-search"></i> Buscar</a>
            <a href="amigos.php">
                <i class="fas fa-user-friends"></i> Amigos
                <?php if ($solicitudes_pendientes_nav > 0): ?>
                    <span class="notification-badge"><?php echo $solicitudes_pendientes_nav; ?></span>
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

        <div class="search-section">
            <h2>Buscar Usuarios</h2>
            <form action="buscar.php" method="get" class="search-form">
                <input type="text" name="q" placeholder="Buscar por nombre o email..." value="<?php echo htmlspecialchars($busqueda); ?>">
                <button type="submit"><i class="fas fa-search"></i></button>
            </form>
        </div>

        <div class="search-results">
            <?php if (!empty($usuarios)): ?>
                <?php foreach ($usuarios as $usuario): ?>
                    <div class="user-result">
                        <img src="<?php echo !empty($usuario['foto_perfil']) ? '../' . htmlspecialchars($usuario['foto_perfil']) : '../uploads/profile/default-profile.png'; ?>"
                             alt="Foto de perfil" class="user-picture">

                        <div class="user-info">
                            <div class="user-name">
                                <a href="perfil.php?id=<?php echo $usuario['id']; ?>">
                                    <?php echo htmlspecialchars($usuario['nombre']); ?>
                                </a>
                            </div>
                        </div>

                        <div class="user-actions">
                            <?php if ($usuario['son_amigos']): ?>
                                <span class="status-badge status-success"><i class="fas fa-check-circle"></i> Amigos</span>
                                <a href="chat.php?id=<?php echo $usuario['id']; ?>" class="action-button primary-button"><i class="fas fa-comment-dots"></i> Chatear</a>
                            <?php elseif ($usuario['solicitud_enviada']): ?>
                                <span class="status-badge status-info"><i class="fas fa-hourglass-half"></i> Solicitud enviada</span>
                            <?php elseif ($usuario['solicitud_recibida']): ?>
                                <span class="status-badge status-warning"><i class="fas fa-exclamation-circle"></i> Solicitud recibida</span>
                                <a href="amigos.php" class="action-button secondary-button"><i class="fas fa-reply"></i> Responder solicitud</a>
                            <?php else: ?>
                                <a href="perfil.php?id=<?php echo $usuario['id']; ?>" class="action-button default-button"><i class="fas fa-user"></i> Ver perfil</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php elseif (!empty($busqueda)): ?>
                <p class="no-results-message">No se encontraron usuarios que coincidan con "<?php echo htmlspecialchars($busqueda); ?>".</p>
            <?php else: ?>
                <p class="no-results-message">Ingresa un nombre o email para buscar usuarios.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>