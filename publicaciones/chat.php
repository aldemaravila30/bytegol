<?php
include '../db/config.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../logueo/login.php");
    exit();
}

// Obtener ID del usuario con el que chatear
$chat_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Verificar si son amigos
$sql = "SELECT * FROM solicitudes_amistad
        WHERE ((id_remitente = {$_SESSION['usuario_id']} AND id_destinatario = $chat_id)
        OR (id_remitente = $chat_id AND id_destinatario = {$_SESSION['usuario_id']}))
        AND estado = 'aceptada'";
$result = $conn->query($sql);

if ($result->num_rows == 0) {
    die("No puedes chatear con este usuario porque no son amigos.");
}

// Obtener información del usuario
$sql = "SELECT * FROM usuarios WHERE id = $chat_id";
$result = $conn->query($sql);
$usuario = $result->fetch_assoc();

// Procesar envío de mensaje
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['mensaje']) && !empty($_POST['mensaje'])) {
        $mensaje = $conn->real_escape_string($_POST['mensaje']);
        // Verificar si la columna 'tipo' existe
        $column_check = $conn->query("SHOW COLUMNS FROM mensajes LIKE 'tipo'");
        if ($column_check->num_rows > 0) {
            $sql = "INSERT INTO mensajes (id_remitente, id_destinatario, mensaje, tipo)
                    VALUES ({$_SESSION['usuario_id']}, $chat_id, '$mensaje', 'texto')";
        } else {
            $sql = "INSERT INTO mensajes (id_remitente, id_destinatario, mensaje)
                    VALUES ({$_SESSION['usuario_id']}, $chat_id, '$mensaje')";
        }
        $conn->query($sql);
    }

    // Procesar envío de imagen
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] == UPLOAD_ERR_OK) {
        $target_dir = "../uploads/mensajes/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $target_file = $target_dir . basename($_FILES["imagen"]["name"]);
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        // Verificar si es una imagen real
        $check = getimagesize($_FILES["imagen"]["tmp_name"]);
        if ($check === false) {
            $_SESSION['error'] = "El archivo no es una imagen.";
            header("Location: chat.php?id=$chat_id");
            exit();
        }

        // Limitar tipos de archivo
        $allowed_types = ['jpg', 'png', 'jpeg', 'gif'];
        if (!in_array($imageFileType, $allowed_types)) {
            $_SESSION['error'] = "Solo se permiten archivos JPG, JPEG, PNG y GIF.";
            header("Location: chat.php?id=$chat_id");
            exit();
        }

        // Limitar tamaño (5MB)
        if ($_FILES["imagen"]["size"] > 5000000) {
            $_SESSION['error'] = "La imagen es demasiado grande (máximo 5MB).";
            header("Location: chat.php?id=$chat_id");
            exit();
        }

        // Mover el archivo
        if (move_uploaded_file($_FILES["imagen"]["tmp_name"], $target_file)) {
            $imagen_path = $conn->real_escape_string($target_file);
            // Verificar si la columna 'tipo' existe
            $column_check = $conn->query("SHOW COLUMNS FROM mensajes LIKE 'tipo'");
            if ($column_check->num_rows > 0) {
                $sql = "INSERT INTO mensajes (id_remitente, id_destinatario, mensaje, tipo)
                        VALUES ({$_SESSION['usuario_id']}, $chat_id, '$imagen_path', 'imagen')";
            } else {
                $sql = "INSERT INTO mensajes (id_remitente, id_destinatario, mensaje)
                        VALUES ({$_SESSION['usuario_id']}, $chat_id, '$imagen_path')";
            }
            $conn->query($sql);
        } else {
            $_SESSION['error'] = "Hubo un error al subir la imagen.";
        }
    }

    // Procesar eliminación de mensaje
    if (isset($_POST['eliminar_mensaje'])) {
        $mensaje_id = intval($_POST['mensaje_id']);
        $sql = "DELETE FROM mensajes WHERE id = $mensaje_id AND id_remitente = {$_SESSION['usuario_id']}";
        $conn->query($sql);
    }

    // Procesar eliminación de chat completo
    if (isset($_POST['eliminar_chat'])) {
        $sql = "DELETE FROM mensajes
                WHERE (id_remitente = {$_SESSION['usuario_id']} AND id_destinatario = $chat_id)
                OR (id_remitente = $chat_id AND id_destinatario = {$_SESSION['usuario_id']})";
        $conn->query($sql);
        $_SESSION['mensaje'] = "Chat eliminado completamente.";
        header("Location: chat.php?id=$chat_id");
        exit();
    }

    $_SESSION['mensaje'] = "Mensaje enviado con éxito";
    header("Location: chat.php?id=$chat_id");
    exit();
}

// Obtener mensajes
$sql = "SELECT mensajes.*, usuarios.nombre, usuarios.foto_perfil
        FROM mensajes
        JOIN usuarios ON mensajes.id_remitente = usuarios.id
        WHERE (id_remitente = {$_SESSION['usuario_id']} AND id_destinatario = $chat_id)
        OR (id_remitente = $chat_id AND id_destinatario = {$_SESSION['usuario_id']})
        ORDER BY fecha ASC";
$result_mensajes = $conn->query($sql);

// Obtener solicitudes de amistad pendientes para notificaciones
$sql_solicitudes = "SELECT COUNT(*) as count FROM solicitudes_amistad
                   WHERE id_destinatario = {$_SESSION['usuario_id']} AND estado = 'pendiente'";
$result_solicitudes = $conn->query($sql_solicitudes);
$solicitudes_pendientes = $result_solicitudes->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Chat con <?php echo htmlspecialchars($usuario['nombre']); ?></title>
    <link rel="stylesheet" href="../css/styless.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            margin: 0;
            padding: 0;
        }

        .chat-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            height: 85vh;
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .chat-header {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid #e0e0e0;
            margin-bottom: 20px;
        }

        .chat-picture {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 15px;
            border: 2px solid #6200ee;
        }

        .chat-info {
            flex: 1;
        }

        .chat-name {
            font-weight: 600;
            font-size: 18px;
            margin-bottom: 5px;
            color: #333;
        }

        .chat-status {
            color: #666;
            font-size: 14px;
        }

        .messages-container {
            flex: 1;
            overflow-y: auto;
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 8px;
            display: flex;
            flex-direction: column;
        }

        .message {
            margin-bottom: 15px;
            padding: 12px 18px;
            border-radius: 18px;
            max-width: 70%;
            word-wrap: break-word;
            display: flex;
            flex-direction: column;
            position: relative;
            margin-left: 10px;
            margin-right: 10px;
        }

        .message-received {
            background: #e3f2fd;
            align-self: flex-start;
            border-bottom-left-radius: 0;
        }

        .message-sent {
            background: #6200ee;
            color: white;
            align-self: flex-end;
            border-bottom-right-radius: 0;
        }

        .message-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 12px;
        }

        .message-sender {
            font-weight: 500;
            color: #6200ee;
        }

        .message-time {
            color: #999;
        }

        .message-content {
            margin-top: 5px;
            font-size: 15px;
            line-height: 1.4;
        }

        .message-image {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            cursor: pointer;
            margin-top: 5px;
            object-fit: cover;
        }

        .message-actions {
            position: absolute;
            top: 10px;
            right: 10px;
            display: none;
        }

        .message:hover .message-actions {
            display: block;
        }

        .message-actions button {
            background: none;
            border: none;
            cursor: pointer;
            color: #666;
            font-size: 16px;
            padding: 5px;
        }

        .message-actions button:hover {
            color: #333;
        }

        .chat-actions {
            margin-left: auto;
            position: relative;
        }

        .chat-actions-button {
            background: none;
            border: none;
            cursor: pointer;
            color: #666;
            font-size: 20px;
            padding: 5px;
        }

        .chat-actions-menu {
            position: absolute;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            display: none;
            z-index: 100;
            min-width: 150px;
        }

        .chat-actions-menu a {
            display: block;
            padding: 8px 15px;
            color: #333;
            text-decoration: none;
        }

        .chat-actions-menu a:hover {
            background: #f5f5f5;
        }

        .message-form {
            display: flex;
            padding: 15px;
            background-color: white;
            border-top: 1px solid #e0e0e0;
        }

        .message-input {
            flex: 1;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 20px;
            font-size: 15px;
            outline: none;
            transition: border-color 0.3s;
        }

        .message-input:focus {
            border-color: #6200ee;
        }

        .message-button {
            margin-left: 10px;
            padding: 10px 20px;
            background: #6200ee;
            color: white;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 500;
            transition: background 0.3s;
        }

        .message-button:hover {
            background: #4a00b4;
        }

        .file-input-container {
            position: relative;
            overflow: hidden;
            display: inline-block;
            margin-left: 10px;
        }

        .file-input {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .file-input-label {
            background: #6200ee;
            color: white;
            padding: 10px 15px;
            border-radius: 20px;
            cursor: pointer;
            display: inline-block;
        }

        .file-input-label:hover {
            background: #4a00b4;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .header h2 {
            margin: 0;
            font-size: 20px;
        }

        .back-button {
            margin-right: auto;
            margin-left: 10px;
            color: #6200ee;
            text-decoration: none;
            font-weight: 500;
        }

        .logout-button {
            background: #d32f2f;
            padding: 8px 15px;
            border-radius: 4px;
            color: white;
            text-decoration: none;
            font-weight: 500;
        }

        .logout-button:hover {
            background: #b71c1c;
        }

        .nav {
            display: flex;
            justify-content: center;
            background: white;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .nav a {
            margin: 0 15px;
            text-decoration: none;
            color: #333;
            font-weight: 500;
            padding: 8px 15px;
            border-radius: 4px;
            transition: all 0.3s;
        }

        .nav a:hover {
            background: #f0f0f0;
            color: #6200ee;
        }

        .nav a.active {
            background: #e3f2fd;
            color: #6200ee;
            font-weight: 600;
        }

        .notification-badge {
            background: #ff3d00;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            font-size: 12px;
            margin-left: 5px;
        }

        .success {
            color: #2e7d32;
            background: #e8f5e9;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            text-align: center;
            font-size: 15px;
        }

        .error {
            color: #d32f2f;
            background: #ffebee;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            text-align: center;
            font-size: 15px;
        }

        .empty-chat {
            text-align: center;
            color: #666;
            margin-top: 50px;
            font-size: 16px;
        }

        .empty-chat-icon {
            font-size: 40px;
            margin-bottom: 15px;
            color: #6200ee;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.8);
            overflow: auto;
        }

        .modal-content {
            margin: auto;
            display: block;
            max-width: 80%;
            max-height: 80%;
        }

        .close-modal {
            position: absolute;
            top: 15px;
            right: 35px;
            color: #f1f1f1;
            font-size: 40px;
            font-weight: bold;
            transition: 0.3s;
            cursor: pointer;
        }

        .close-modal:hover {
            color: #bbb;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>Chat con <?php echo htmlspecialchars($usuario['nombre']); ?></h2>
        <a href="feed.php" class="back-button">← Volver al Feed</a>
        <a href="../logueo/logout.php" class="logout-button">Cerrar Sesión</a>
    </div>

    <div class="nav">
        <a href="feed.php">Feed</a>
        <a href="buscar.php">Buscar Usuarios</a>
        <a href="amigos.php" class="<?php echo ($solicitudes_pendientes > 0) ? 'active' : ''; ?>">
            Mis Amigos
            <?php if ($solicitudes_pendientes > 0): ?>
                <span class="notification-badge"><?php echo $solicitudes_pendientes; ?></span>
            <?php endif; ?>
        </a>
        <a href="perfil.php?id=<?php echo $_SESSION['usuario_id']; ?>">Mi Perfil</a>
    </div>

    <div class="chat-container">
        <?php if (isset($_SESSION['mensaje'])) {
            echo "<p class='success'>{$_SESSION['mensaje']}</p>";
            unset($_SESSION['mensaje']);
        } ?>

        <?php if (isset($_SESSION['error'])) {
            echo "<p class='error'>{$_SESSION['error']}</p>";
            unset($_SESSION['error']);
        } ?>

        <div class="chat-header">
            <img src="<?php echo !empty($usuario['foto_perfil']) ? '../' . $usuario['foto_perfil'] : '../uploads/profile/default-profile.png'; ?>"
                 alt="Foto de perfil" class="chat-picture">

            <div class="chat-info">
                <div class="chat-name"><?php echo htmlspecialchars($usuario['nombre']); ?></div>
                <div class="chat-status">En línea</div>
            </div>

            <div class="chat-actions">
                <button class="chat-actions-button" id="chat-actions-button">⋮</button>
                <div class="chat-actions-menu" id="chat-actions-menu">
                    <form method="post">
                        <button type="submit" name="eliminar_chat" style="width: 100%; text-align: left; background: none; border: none; padding: 8px 15px; cursor: pointer;">
                            Eliminar chat completo
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="messages-container" id="messages-container">
            <?php
            if ($result_mensajes->num_rows > 0) {
                while($row = $result_mensajes->fetch_assoc()) {
                    $es_mio = ($row['id_remitente'] == $_SESSION['usuario_id']);
                    $clase = $es_mio ? 'message-sent' : 'message-received';
                    echo "<div class='message $clase'>";

                    // Determinar si es una imagen
                    $es_imagen = false;
                    if (isset($row['tipo']) && $row['tipo'] === 'imagen') {
                        $es_imagen = true;
                    } else {
                        // Si no hay columna 'tipo', verificar si el mensaje es una ruta de imagen
                        $es_imagen = (strpos($row['mensaje'], '../uploads/mensajes/') === 0 ||
                                     strpos($row['mensaje'], 'uploads/mensajes/') === 0);
                    }

                    if ($es_imagen) {
                        echo "<div class='message-info'>";
                        echo "<span class='message-sender'>" . htmlspecialchars($row['nombre']) . "</span>";
                        echo "<span class='message-time'>" . date('H:i', strtotime($row['fecha'])) . "</span>";
                        echo "</div>";
                        echo "<img src='" . htmlspecialchars($row['mensaje']) . "' class='message-image' onclick='openModal(\"" . htmlspecialchars($row['mensaje']) . "\")'>";
                    } else {
                        echo "<div class='message-info'>";
                        echo "<span class='message-sender'>" . htmlspecialchars($row['nombre']) . "</span>";
                        echo "<span class='message-time'>" . date('H:i', strtotime($row['fecha'])) . "</span>";
                        echo "</div>";
                        echo "<div class='message-content'>" . nl2br(htmlspecialchars($row['mensaje'])) . "</div>";
                    }

                    if ($es_mio) {
                        echo "<div class='message-actions'>";
                        echo "<form method='post' style='display: inline;'>";
                        echo "<input type='hidden' name='mensaje_id' value='" . $row['id'] . "'>";
                        echo "<button type='submit' name='eliminar_mensaje' title='Eliminar mensaje'>✕</button>";
                        echo "</form>";
                        echo "</div>";
                    }

                    echo "</div>";
                }
            } else {
                echo "<div class='empty-chat'>";
                echo "<div class='empty-chat-icon'>💬</div>";
                echo "<p>No hay mensajes aún. ¡Inicia la conversación!</p>";
                echo "</div>";
            }
            ?>
        </div>

        <form method="post" action="chat.php?id=<?php echo $chat_id; ?>" class="message-form" enctype="multipart/form-data">
            <input type="text" name="mensaje" class="message-input" placeholder="Escribe tu mensaje aquí...">
            <div class="file-input-container">
                <input type="file" name="imagen" id="file-input" class="file-input" accept="image/*">
                <label for="file-input" class="file-input-label">📎</label>
            </div>
            <button type="submit" class="message-button">Enviar</button>
        </form>
    </div>

    <!-- Modal para ver imágenes -->
    <div id="imageModal" class="modal">
        <span class="close-modal" id="closeModal">&times;</span>
        <img class="modal-content" id="modalImage">
    </div>

    <script>
        // Desplazar al final del contenedor de mensajes
        document.addEventListener('DOMContentLoaded', function() {
            const messagesContainer = document.getElementById('messages-container');
            messagesContainer.scrollTop = messagesContainer.scrollHeight;

            // Menú de acciones del chat
            const chatActionsButton = document.getElementById('chat-actions-button');
            const chatActionsMenu = document.getElementById('chat-actions-menu');

            chatActionsButton.addEventListener('click', function(e) {
                e.stopPropagation();
                chatActionsMenu.style.display = chatActionsMenu.style.display === 'block' ? 'none' : 'block';
            });

            document.addEventListener('click', function() {
                chatActionsMenu.style.display = 'none';
            });

            // Modal para imágenes
            const modal = document.getElementById('imageModal');
            const modalImg = document.getElementById('modalImage');
            const closeModal = document.getElementById('closeModal');

            function openModal(imgSrc) {
                modal.style.display = "block";
                modalImg.src = imgSrc;
            }

            closeModal.onclick = function() {
                modal.style.display = "none";
            }

            modal.onclick = function(event) {
                if (event.target == modal) {
                    modal.style.display = "none";
                }
            }
        });
    </script>
</body>
</html>
