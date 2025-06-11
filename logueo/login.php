<?php
// logueo/login.php
include '../db/config.php'; // Asegúrate de que config.php incluya session_start()

// Asegúrate de que session_start() esté aquí si no lo está en config.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Función de saneamiento de entrada (si no la tienes en config.php)
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Verificar si el usuario ya está logueado
if (isset($_SESSION['usuario_id'])) {
    header("Location: ../publicaciones/feed.php");
    exit();
}

$error = ''; // Inicializar la variable de error

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = sanitizeInput($_POST['email']);
    $contrasena = $_POST['contrasena'];

    // Usar sentencia preparada para evitar SQL Injection
    $stmt = $conn->prepare("SELECT id, nombre, contrasena, foto_perfil, rol, estado FROM usuarios WHERE email=?");
    
    if ($stmt === false) {
        $error = "Error al preparar la consulta: " . $conn->error;
    } else {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            
            // 1. Verificar si la contraseña es correcta
            if (password_verify($contrasena, $row['contrasena'])) {
                
                // 2. Verificar si la cuenta está activa
                if ($row['estado'] === 'suspendido') {
                    $error = "Tu cuenta ha sido suspendida. Contacta al soporte.";
                } else {
                    // 3. Iniciar sesión y guardar datos importantes
                    session_regenerate_id(true); // Regenerar ID de sesión para prevenir Session Fixation
                    $_SESSION['usuario_id'] = $row['id'];
                    $_SESSION['usuario_nombre'] = $row['nombre'];
                    $_SESSION['foto_perfil'] = $row['foto_perfil'];
                    $_SESSION['rol'] = $row['rol']; 

                    header("Location: ../publicaciones/feed.php");
                    exit();
                }
            } else {
                $error = "Contraseña incorrecta.";
            }
        } else {
            $error = "Usuario no encontrado.";
        }
        $stmt->close(); // Cerrar el statement
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio de Sesión</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Tus estilos CSS aquí */
        :root {
            --primary: #6200ee;
            --primary-light: #9e47ff;
            --secondary: #03dac6;
            --accent: #ff3d00;
            --text: #212121;
            --text-light: #757575;
            --bg: #f8f9fa; /* Fondo general claro */
            --white: #ffffff; /* Elementos blancos/claros como cards, headers */
            --dark: #121212; /* Fondo de footer/elementos oscuros en modo claro */
            --gray: #e0e0e0;
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.1);
            --shadow-lg: 0 8px 24px rgba(0,0,0,0.15);
            --border-radius: 12px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            line-height: 1.6;
            color: var(--text);
            background-color: var(--bg);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            transition: background-color 0.5s, color 0.5s;
        }

        body.dark-mode {
            --text: #f5f5f5;
            --text-light: #a0a0a0;
            --bg: #1a1a1a; /* Fondo general oscuro */
            --white: #2e2e2e; /* Elementos que eran blancos ahora son un gris oscuro */
            --dark: #121212; /* El footer permanece muy oscuro o se ajusta a un tono más oscuro aún */
            --gray: #333;
            --shadow-sm: 0 2px 4px rgba(255,255,255,0.05);
            --shadow-md: 0 4px 12px rgba(255,255,255,0.1);
            --shadow-lg: 0 8px 24px rgba(255,255,255,0.15);
        }

        /* Asegurar que el body use flex para el pie de página fijo */
        html, body {
            height: 100%;
        }

        /* ===== CONTENEDOR PRINCIPAL (para centrar el formulario) ===== */
        .container {
            flex: 1; /* Permite que el contenedor ocupe el espacio disponible */
            display: flex;
            justify-content: center; /* Centrar horizontalmente */
            align-items: center; /* Centrar verticalmente */
            padding: 20px; /* Padding general para el contenido */
            width: 100%; /* Asegurar que ocupe todo el ancho */
        }

        h2 {
            color: var(--primary);
            margin-bottom: 25px;
            font-size: 2rem;
            font-weight: 700;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-light);
            font-weight: 500;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%; /* Ocupa todo el ancho del contenedor del formulario */
            padding: 12px;
            border: 1px solid var(--gray);
            border-radius: 8px;
            font-size: 1rem;
            color: var(--text);
            background-color: var(--bg); /* Este será oscuro en dark mode */
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        input[type="email"]:focus,
        input[type="password"]:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(98, 0, 238, 0.2);
            outline: none;
        }

        .error-message {
            color: var(--accent);
            background-color: rgba(255, 61, 0, 0.1);
            border: 1px solid var(--accent);
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            text-align: left; /* Asegurar alineación a la izquierda */
        }
        
        .success-message { /* Nuevo estilo para mensajes de éxito */
            color: #28a745; /* Verde */
            background-color: rgba(40, 167, 69, 0.1);
            border: 1px solid #28a745;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            text-align: left;
        }

        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: var(--white); /* El texto será blanco en dark mode */
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.3s, transform 0.3s, box-shadow 0.3s;
            box-shadow: var(--shadow-sm);
        }

        button:hover {
            opacity: 0.9;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .register-link {
            margin-top: 25px;
            font-size: 0.95rem;
            color: var(--text-light);
        }

        .register-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s;
        }

        .register-link a:hover {
            color: var(--primary-light);
            text-decoration: underline;
        }

        /* ESTILOS DEL HEADER */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
            background-color: var(--white); /* Este será oscuro en dark mode */
            box-shadow: var(--shadow-sm);
            position: sticky;
            top: 0;
            z-index: 100;
            width: 100%;
        }

        .header .logo {
            font-size: 1.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            background-clip: text;
            -webkit-background-clip: text;
            color: transparent;
            text-decoration: none;
        }

        .header .logo:hover {
            opacity: 0.9;
        }

        /* FOOTER */
        .footer {
            background-color: var(--dark); /* Este será oscuro en dark mode */
            color: var(--white); /* El texto será blanco en dark mode */
            padding: 2rem 0;
            margin-top: auto;
            width: 100%;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .footer-logo {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            background-clip: text;
            -webkit-background-clip: text;
            color: transparent;
        }

        .footer-links {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .footer-links a {
            color: var(--text-light); /* Este será un gris más claro en dark mode */
            text-decoration: none;
            transition: color 0.3s;
        }

        .footer-links a:hover {
            color: var(--white); /* Este será un gris oscuro en dark mode */
        }

        .copyright {
            font-size: 0.9rem;
            color: var(--text-light); /* Este será un gris más claro en dark mode */
        }

        /* ===== Estilos Específicos para Formularios (container del formulario) ===== */
        .form-container { /* Añadido para dar estilos al div que envuelve el formulario */
            max-width: 400px;
            width: 100%; /* Ocupa el 100% del ancho disponible en el contenedor flex */
            padding: 30px;
            background-color: var(--white); /* Este será oscuro en dark mode */
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            text-align: center;
            margin: 20px; /* Margen para evitar que toque los bordes del viewport */
        }


        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .header {
                padding: 1rem 1rem;
            }
            .header .logo {
                font-size: 1.3rem;
            }
            .form-container {
                padding: 25px; /* Ajustar padding del formulario */
                margin: 15px; /* Ajustar margen para móviles */
            }
            h2 {
                font-size: 1.8rem;
            }
            .footer-links {
                flex-direction: column;
                gap: 0.8rem;
            }
            .footer-links a {
                font-size: 0.9rem;
            }
            .copyright {
                font-size: 0.8rem;
            }
        }

        @media (max-width: 480px) {
            .header {
                padding: 0.8rem 1rem;
            }
            .header .logo {
                font-size: 1.2rem;
            }
            .form-container {
                padding: 20px;
                margin: 10px; /* Margen más pequeño en pantallas muy pequeñas */
            }
            h2 {
                font-size: 1.6rem;
            }
            input[type="email"],
            input[type="password"],
            button {
                padding: 10px; /* Reducir padding de inputs y botones */
                font-size: 0.9rem;
            }
            .error-message, .success-message {
                font-size: 0.8rem;
                padding: 8px;
            }
            .register-link, .register-link a {
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <a href="../index.php" class="logo">ByteGol</a>
    </header>

    <div class="container">
        <div class="form-container"> <h2>Iniciar Sesión</h2>
            <?php if (!empty($error)): ?>
                <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <?php if (isset($_SESSION['mensaje'])): ?>
                <p class="success-message"><?php echo htmlspecialchars($_SESSION['mensaje']); unset($_SESSION['mensaje']); ?></p>
            <?php endif; ?>
            <form action="login.php" method="POST">
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required aria-label="Correo electrónico">
                </div>
                <div class="form-group">
                    <label for="contrasena">Contraseña:</label>
                    <input type="password" id="contrasena" name="contrasena" required aria-label="Contraseña">
                </div>
                <button type="submit">Iniciar Sesión</button>
            </form>
            <p class="register-link">¿No tienes una cuenta? <a href="../registro/registro.php">Regístrate aquí</a></p>
        </div>
    </div>

    <footer class="footer">
        <div class="footer-content">
            <div class="footer-logo">ByteGol</div>
            <div class="footer-links">
                <a href="../index.php">Inicio</a>
                <a href="../zZz/Politica.php">Política de Privacidad</a>
                <a href="../zZz/terminos.php">Términos y Condiciones</a>
                <a href="../zZz/contacto.php">Contacto</a>
            </div>
            <div class="copyright">
                &copy; <?php echo date('Y'); ?> ByteGol. Todos los derechos reservados.
            </div>
        </div>
    </footer>
        <script>
        // Toggle de modo oscuro
        const themeToggle = document.getElementById('theme-toggle');
        const body = document.body;

        // Verificar preferencia del usuario
        if (localStorage.getItem('darkMode') === 'enabled') {
            body.classList.add('dark-mode');
            themeToggle.checked = true;
        }

        // Event listener para el toggle
        themeToggle.addEventListener('change', function() {
            if (this.checked) {
                body.classList.add('dark-mode');
                localStorage.setItem('darkMode', 'enabled');
            } else {
                body.classList.remove('dark-mode');
                localStorage.setItem('darkMode', 'disabled');
            }
        });
    </script>
</body>
</html>