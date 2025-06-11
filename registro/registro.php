<?php
// Incluir el archivo de configuración de la base de datos
include '../db/config.php';

// Iniciar sesión si no está iniciada (esto es crucial para usar $_SESSION['mensaje'])
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Inicializar la variable de error
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanear y validar las entradas
    $nombre = trim($_POST['nombre']);
    $email = trim($_POST['email']);
    $contrasena = $_POST['contrasena'];
    $confirmar_contrasena = $_POST['confirmar_contrasena'];

    // Validaciones del lado del servidor
    if (empty($nombre) || empty($email) || empty($contrasena) || empty($confirmar_contrasena)) {
        $error = "Todos los campos son obligatorios.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "El formato del email no es válido.";
    } elseif ($contrasena !== $confirmar_contrasena) {
        $error = "Las contraseñas no coinciden.";
    } elseif (strlen($contrasena) < 6) { // Ejemplo de validación de longitud de contraseña
        $error = "La contraseña debe tener al menos 6 caracteres.";
    } else {
        // ==================================================================
        // INICIO DE LA MODIFICACIÓN: VERIFICAR SI EL NOMBRE YA EXISTE
        // ==================================================================
        $stmt_check_nombre = $conn->prepare("SELECT id FROM usuarios WHERE nombre = ?");
        if ($stmt_check_nombre === false) {
            $error = "Error al preparar la consulta de verificación de nombre: " . $conn->error;
        } else {
            $stmt_check_nombre->bind_param("s", $nombre);
            $stmt_check_nombre->execute();
            $result_check_nombre = $stmt_check_nombre->get_result();

            if ($result_check_nombre->num_rows > 0) {
                // Si el nombre ya existe, establece el error y termina el proceso.
                $error = "El nombre de usuario '" . htmlspecialchars($nombre) . "' ya está en uso. Por favor, elige otro.";
            } else {
                // Si el nombre está disponible, procedemos a verificar el email (código que ya tenías)
                $stmt_check_email = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
                if ($stmt_check_email === false) {
                    $error = "Error al preparar la consulta de verificación de email: " . $conn->error;
                } else {
                    $stmt_check_email->bind_param("s", $email);
                    $stmt_check_email->execute();
                    $result_check_email = $stmt_check_email->get_result();

                    if ($result_check_email->num_rows > 0) {
                        $error = "El email ya está registrado.";
                    } else {
                        // Hashear la contraseña de forma segura
                        $contrasena_hash = password_hash($contrasena, PASSWORD_BCRYPT);

                        // Se asigna un rol predeterminado de 'usuario' al registrar
                        $rol_predeterminado = 'usuario'; 
                        $stmt_insert = $conn->prepare("INSERT INTO usuarios (nombre, email, contrasena, rol) VALUES (?, ?, ?, ?)");
                        if ($stmt_insert === false) {
                            $error = "Error al preparar la consulta de inserción: " . $conn->error;
                        } else {
                            $stmt_insert->bind_param("ssss", $nombre, $email, $contrasena_hash, $rol_predeterminado);

                            if ($stmt_insert->execute()) {
                                $_SESSION['mensaje'] = "Registro exitoso. Ahora puedes iniciar sesión.";
                                header("Location: ../logueo/login.php");
                                exit();
                            } else {
                                $error = "Error al registrar usuario: " . $stmt_insert->error;
                            }
                            $stmt_insert->close();
                        }
                    }
                    $stmt_check_email->close();
                }
            }
            $stmt_check_nombre->close();
        }
        // ==================================================================
        // FIN DE LA MODIFICACIÓN
        // ==================================================================
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ===== VARIABLES DE DISEÑO ===== */
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

        /* ===== ESTILOS BASE ===== */
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

        /* ===== ENCABEZADO ===== */
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

        /* ===== FOOTER ===== */
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

        /* ===== Estilos Específicos para Formularios ===== */
        .form-container {
            max-width: 400px;
            width: 100%; /* Ocupa el 100% del ancho disponible en el contenedor flex */
            padding: 30px;
            background-color: var(--white); /* Este será oscuro en dark mode */
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            text-align: center;
            margin: 20px; /* Margen para evitar que toque los bordes del viewport */
        }

        .form-container h2 {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 1.5rem;
            font-weight: 700;
        }

        .form-container input[type="text"],
        .form-container input[type="email"],
        .form-container input[type="password"] {
            width: 100%; /* Ocupa todo el ancho del contenedor del formulario */
            padding: 12px;
            margin-bottom: 15px;
            border: 1px solid var(--gray);
            border-radius: 8px;
            box-sizing: border-box; /* Incluir padding y borde en el ancho total */
            font-size: 1rem;
            color: var(--text);
            background-color: var(--bg); /* Este será oscuro en dark mode */
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        .form-container input[type="text"]:focus,
        .form-container input[type="email"]:focus,
        .form-container input[type="password"]:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(98, 0, 238, 0.2);
            outline: none;
        }

        .form-container input[type="submit"] {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: var(--white); /* Este será oscuro en dark mode */
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.1rem;
            font-weight: 600;
            transition: opacity 0.3s, transform 0.3s, box-shadow 0.3s;
            box-shadow: var(--shadow-sm);
        }

        .form-container input[type="submit"]:hover {
            opacity: 0.9;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .form-container .error {
            color: var(--accent);
            background-color: rgba(255, 61, 0, 0.1);
            border: 1px solid var(--accent);
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            text-align: left;
        }

        .form-container p {
            margin-top: 15px;
            color: var(--text-light);
        }

        .form-container p a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s;
        }

        .form-container p a:hover {
            color: var(--primary-light);
            text-decoration: underline;
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
            .form-container h2 {
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
            .form-container h2 {
                font-size: 1.6rem;
            }
            input[type="text"],
            input[type="email"],
            input[type="password"],
            input[type="submit"] {
                padding: 10px; /* Reducir padding de inputs y botones */
                font-size: 0.9rem;
            }
            .form-container .error {
                font-size: 0.8rem;
                padding: 8px;
            }
            .form-container p, .form-container p a {
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
        <div class="form-container">
            <h2>Registro</h2>

            <?php if (isset($error) && !empty($error)) echo "<p class='error'>". htmlspecialchars($error) ."</p>"; ?>

            <form method="post" action="registro.php">
                <input type="text" name="nombre" placeholder="Nombre" required aria-label="Nombre de usuario" value="<?php echo isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : ''; ?>"><br>
                <input type="email" name="email" placeholder="Email" required aria-label="Correo electrónico" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"><br>
                <input type="password" name="contrasena" placeholder="Contraseña" required aria-label="Contraseña"><br>
                <input type="password" name="confirmar_contrasena" placeholder="Confirmar Contraseña" required aria-label="Confirmar contraseña"><br>
                <input type="submit" value="Registrar">
            </form>
            <p>¿Ya tienes cuenta? <a href="../logueo/login.php">Inicia sesión aquí</a></p>
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
            if(themeToggle) themeToggle.checked = true;
        }

        // Event listener para el toggle
        if(themeToggle) {
            themeToggle.addEventListener('change', function() {
                if (this.checked) {
                    body.classList.add('dark-mode');
                    localStorage.setItem('darkMode', 'enabled');
                } else {
                    body.classList.remove('dark-mode');
                    localStorage.setItem('darkMode', 'disabled');
                }
            });
        }
    </script>
</body>
</html>