<?php
include '../db/config.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../logueo/login.php");
    exit();
}

// Obtener solicitudes de amistad recibidas
$sql = "SELECT usuarios.*, solicitudes_amistad.id as solicitud_id
        FROM solicitudes_amistad
        JOIN usuarios ON solicitudes_amistad.id_remitente = usuarios.id
        WHERE solicitudes_amistad.id_destinatario = {$_SESSION['usuario_id']}
        AND solicitudes_amistad.estado = 'pendiente'";
$result = $conn->query($sql);
$solicitudes_recibidas = [];

while ($row = $result->fetch_assoc()) {
    $solicitudes_recibidas[] = $row;
}

// Obtener solicitudes de amistad enviadas
$sql = "SELECT usuarios.*, solicitudes_amistad.id as solicitud_id
        FROM solicitudes_amistad
        JOIN usuarios ON solicitudes_amistad.id_destinatario = usuarios.id
        WHERE solicitudes_amistad.id_remitente = {$_SESSION['usuario_id']}
        AND solicitudes_amistad.estado = 'pendiente'";
$result = $conn->query($sql);
$solicitudes_enviadas = [];

while ($row = $result->fetch_assoc()) {
    $solicitudes_enviadas[] = $row;
}

// Procesar aceptación de solicitud
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['aceptar_solicitud'])) {
    $solicitud_id = intval($_POST['solicitud_id']);
    $sql = "UPDATE solicitudes_amistad SET estado = 'aceptada' WHERE id = $solicitud_id";
    $conn->query($sql);
    $_SESSION['mensaje'] = "Solicitud de amistad aceptada";
    header("Location: amigos.php");
    exit();
}

// Procesar rechazo de solicitud
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['rechazar_solicitud'])) {
    $solicitud_id = intval($_POST['solicitud_id']);
    $sql = "UPDATE solicitudes_amistad SET estado = 'rechazada' WHERE id = $solicitud_id";
    $conn->query($sql);
    $_SESSION['mensaje'] = "Solicitud de amistad rechazada";
    header("Location: amigos.php");
    exit();
}

// Procesar cancelación de solicitud
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cancelar_solicitud'])) {
    $solicitud_id = intval($_POST['solicitud_id']);
    $sql = "DELETE FROM solicitudes_amistad WHERE id = $solicitud_id";
    $conn->query($sql);
    $_SESSION['mensaje'] = "Solicitud de amistad cancelada";
    header("Location: amigos.php");
    exit();
}

// Procesar eliminación de amigo
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['eliminar_amigo'])) {
    $amigo_id = intval($_POST['amigo_id']);

    // Eliminar la relación de amistad (en ambos sentidos si es necesario)
    $sql = "DELETE FROM solicitudes_amistad
            WHERE ((id_remitente = {$_SESSION['usuario_id']} AND id_destinatario = $amigo_id)
            OR (id_remitente = $amigo_id AND id_destinatario = {$_SESSION['usuario_id']}))
            AND estado = 'aceptada'";
    $conn->query($sql);

    $_SESSION['mensaje'] = "Amigo eliminado correctamente";
    header("Location: amigos.php");
    exit();
}

// Obtener lista de amigos
$sql = "SELECT usuarios.*
        FROM solicitudes_amistad
        JOIN usuarios ON
            (solicitudes_amistad.id_destinatario = usuarios.id AND solicitudes_amistad.id_remitente = {$_SESSION['usuario_id']})
            OR (solicitudes_amistad.id_remitente = usuarios.id AND solicitudes_amistad.id_destinatario = {$_SESSION['usuario_id']})
        WHERE solicitudes_amistad.estado = 'aceptada'
        AND usuarios.id != {$_SESSION['usuario_id']}";
$result = $conn->query($sql);
$amigos = [];

while ($row = $result->fetch_assoc()) {
    $amigos[] = $row;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Mis Amigos</title>
    <link rel="stylesheet" href="../css/style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    </head>
<body>
    <div class="header">
        <div class="logo">Amigos</div> <a href="../logueo/logout.php" class="logout-button">Cerrar Sesión</a>
    </div>

    <div class="nav">
        <a href="feed.php">Feed</a>
        <a href="buscar.php">Buscar Usuarios</a>
        <a href="amigos.php">
            Mis Amigos
            <?php if (count($solicitudes_recibidas) > 0): ?>
                <span class="notification-badge"><?php echo count($solicitudes_recibidas); ?></span>
            <?php endif; ?>
        </a>
        <a href="perfil.php?id=<?php echo $_SESSION['usuario_id']; ?>">Mi Perfil</a>

        <?php // --- CAMBIO REALIZADO AQUÍ ---
        if (isset($_SESSION['rol']) && in_array($_SESSION['rol'], ['admin', 'superadmin'])): ?>
            <a href="../admin/gestionar_usuarios.php" class="admin-link">Panel Admin</a>
        <?php endif; ?>
    </div>

    <div class="container friends-container"> <?php if (isset($_SESSION['mensaje'])) {
            // Reutiliza la clase 'success' existente en style.css
            echo "<p class='success'>{$_SESSION['mensaje']}</p>";
            unset($_SESSION['mensaje']);
        } ?>

        <div class="section">
            <h3 class="section-title">Solicitudes Recibidas</h3>

            <?php if (empty($solicitudes_recibidas)): ?>
                <p class="no-results-message">No tienes solicitudes de amistad pendientes.</p>
            <?php else: ?>
                <?php foreach ($solicitudes_recibidas as $solicitud): ?>
                    <div class="friend-item">
                        <img src="<?php echo !empty($solicitud['foto_perfil']) ? '../' . $solicitud['foto_perfil'] : '../uploads/profile/default-profile.png'; ?>"
                             alt="Foto de perfil" class="friend-picture">

                        <div class="friend-info">
                            <div class="friend-name">
                                <a href="perfil.php?id=<?php echo $solicitud['id']; ?>">
                                    <?php echo htmlspecialchars($solicitud['nombre']); ?>
                                </a>
                            </div>
                            <div class="friend-email"><?php echo htmlspecialchars($solicitud['email']); ?></div>
                        </div>

                        <div class="friend-actions">
                            <form method="post">
                                <input type="hidden" name="solicitud_id" value="<?php echo $solicitud['solicitud_id']; ?>">
                                <button type="submit" name="aceptar_solicitud" class="accept-button">Aceptar</button>
                                <button type="submit" name="rechazar_solicitud" class="reject-button">Rechazar</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="section">
            <h3 class="section-title">Solicitudes Enviadas</h3>

            <?php if (empty($solicitudes_enviadas)): ?>
                <p class="no-results-message">No has enviado solicitudes de amistad pendientes.</p>
            <?php else: ?>
                <?php foreach ($solicitudes_enviadas as $solicitud): ?>
                    <div class="friend-item">
                        <img src="<?php echo !empty($solicitud['foto_perfil']) ? '../' . $solicitud['foto_perfil'] : '../uploads/profile/default-profile.png'; ?>"
                             alt="Foto de perfil" class="friend-picture">

                        <div class="friend-info">
                            <div class="friend-name">
                                <a href="perfil.php?id=<?php echo $solicitud['id']; ?>">
                                    <?php echo htmlspecialchars($solicitud['nombre']); ?>
                                </a>
                            </div>
                            <div class="friend-email"><?php echo htmlspecialchars($solicitud['email']); ?></div>
                        </div>

                        <div class="friend-actions">
                            <form method="post">
                                <input type="hidden" name="solicitud_id" value="<?php echo $solicitud['solicitud_id']; ?>">
                                <button type="submit" name="cancelar_solicitud" class="cancel-button">Cancelar</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="section">
            <h3 class="section-title">Mis Amigos</h3>

            <?php if (empty($amigos)): ?>
                <p class="no-results-message">No tienes amigos aún. ¡Envía solicitudes de amistad!</p>
            <?php else: ?>
                <?php foreach ($amigos as $amigo): ?>
                    <div class="friend-item">
                        <img src="<?php echo !empty($amigo['foto_perfil']) ? '../' . $amigo['foto_perfil'] : '../uploads/profile/default-profile.png'; ?>"
                             alt="Foto de perfil" class="friend-picture">

                        <div class="friend-info">
                            <div class="friend-name">
                                <a href="perfil.php?id=<?php echo $amigo['id']; ?>">
                                    <?php echo htmlspecialchars($amigo['nombre']); ?>
                                </a>
                            </div>
                            <div class="friend-email"><?php echo htmlspecialchars($amigo['email']); ?></div>
                        </div>

                        <div class="friend-actions">
                            <a href="chat.php?id=<?php echo $amigo['id']; ?>" class="chat-button">Chatear</a>
                            <form method="post">
                                <input type="hidden" name="amigo_id" value="<?php echo $amigo['id']; ?>">
                                <button type="submit" name="eliminar_amigo" class="delete-button">Eliminar</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>