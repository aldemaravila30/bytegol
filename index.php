<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ByteGol</title>
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

        /* ===== CONTENEDOR PRINCIPAL ===== */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px; /* Padding horizontal para evitar que el contenido toque los bordes */
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        /* ===== HEADER CON TOGGLE DE MODO OSCURO ===== */
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
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            background-clip: text;
            -webkit-background-clip: text;
            color: transparent;
        }

        .theme-toggle {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .theme-toggle label {
            font-size: 0.9rem;
            color: var(--text-light);
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: var(--gray);
            transition: .4s;
            border-radius: 24px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 4px;
            bottom: 4px;
            background-color: var(--white); /* Este se volverá oscuro en dark mode */
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: var(--primary);
        }

        input:checked + .slider:before {
            transform: translateX(26px);
        }

        /* ===== SECCIÓN DE BIENVENIDA ===== */
        .welcome-section {
            text-align: center;
            padding: 4rem 1rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: var(--white); /* El texto será blanco en este gradiente */
            border-radius: 20px;
            margin: 2rem auto;
            max-width: 1000px;
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
        }

        .welcome-section::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: rotate 15s linear infinite;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .title {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            font-weight: 700;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .subtitle {
            font-size: 1.2rem;
            margin-bottom: 2.5rem;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.6;
        }

        /* ===== GRUPO DE BOTONES ===== */
        .button-group {
            display: flex;
            justify-content: center;
            gap: 1rem;
            flex-wrap: wrap; /* Permite que los botones se envuelvan a la siguiente línea */
        }

        .button {
            display: inline-block;
            padding: 1rem 2rem;
            border-radius: 50px;
            font-weight: 600;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
            text-decoration: none;
            position: relative;
            overflow: hidden;
            text-align: center; /* Centrar texto en el botón */
        }

        .button::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(120deg, rgba(255,255,255,0.3), transparent);
            transform: translateX(-100%);
            transition: transform 0.6s;
        }

        .button:hover::before {
            transform: translateX(100%);
        }

        .button-primary {
            background: var(--white); /* Este será oscuro en dark mode */
            color: var(--primary);
            border: 2px solid var(--white); /* Este será oscuro en dark mode */
        }

        .button-primary:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }

        .button-secondary {
            background: transparent;
            color: var(--white); /* Este será oscuro en dark mode */
            border: 2px solid var(--white); /* Este será oscuro en dark mode */
        }

        .button-secondary:hover {
            background: rgba(255,255,255,0.1);
        }

        /* ===== SECCIÓN DE CARACTERÍSTICAS ===== */
        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); /* Ajuste para móviles */
            gap: 2rem;
            margin: 3rem auto;
            max-width: 1000px;
        }

        .feature {
            background: var(--white); /* Este será oscuro en dark mode */
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            text-align: center;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .feature:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
        }

        .feature::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(98,0,238,0.05), rgba(3,218,198,0.05));
            z-index: 0;
        }

        .feature > * {
            position: relative;
            z-index: 1;
        }

        .feature-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            background-clip: text;
            -webkit-background-clip: text;
            color: transparent;
        }

        .feature h3 {
            margin-bottom: 0.75rem;
            color: var(--primary);
        }

        .feature p {
            color: var(--text-light);
        }

        /* ===== FOOTER ===== */
        .footer {
            background-color: var(--dark); /* Este será oscuro en dark mode */
            color: var(--white); /* El texto será blanco en dark mode */
            padding: 2rem 0;
            margin-top: auto;
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

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .header {
                padding: 1rem 1rem; /* Reducir padding en pantallas más pequeñas */
            }
            .logo {
                font-size: 1.3rem; /* Reducir tamaño de logo */
            }
            .theme-toggle label {
                font-size: 0.8rem;
            }

            .welcome-section {
                padding: 3rem 1rem;
                margin: 1.5rem auto; /* Ajustar margen */
            }

            .title {
                font-size: 2rem;
            }

            .subtitle {
                font-size: 1.1rem;
                margin-bottom: 2rem;
            }

            .button-group {
                flex-direction: column; /* Apilar botones verticalmente */
                gap: 0.8rem; /* Reducir espacio entre botones */
            }

            .button {
                width: 80%; /* Hacer botones más anchos en móviles */
                max-width: 300px; /* Limitar ancho máximo */
                margin: 0 auto; /* Centrar botones apilados */
                padding: 0.9rem 1.5rem; /* Ajustar padding */
                font-size: 0.95rem; /* Ajustar tamaño de fuente */
            }

            .features {
                grid-template-columns: 1fr; /* Una columna para características en móviles */
                margin: 2rem auto;
                padding: 0 15px; /* Padding para los lados */
            }

            .feature {
                padding: 1.5rem; /* Ajustar padding */
            }

            .footer-links {
                flex-direction: column; /* Apilar enlaces del footer */
                gap: 0.8rem; /* Espacio entre enlaces apilados */
            }

            .footer-links a {
                font-size: 0.9rem; /* Reducir tamaño de fuente de enlaces */
            }

            .copyright {
                font-size: 0.8rem;
            }
        }

        @media (max-width: 480px) {
            .header {
                padding: 0.8rem 1rem;
            }
            .logo {
                font-size: 1.2rem;
            }
            .theme-toggle label {
                display: none; /* Ocultar "Modo oscuro" para ahorrar espacio */
            }

            .welcome-section {
                padding: 2.5rem 1rem;
                margin: 1rem auto;
            }

            .title {
                font-size: 1.8rem;
            }

            .subtitle {
                font-size: 1rem;
                margin-bottom: 1.8rem;
            }

            .button {
                width: 90%; /* Hacer botones casi al 100% en móviles más pequeños */
                padding: 0.8rem 1.2rem;
                font-size: 0.9rem;
            }

            .feature {
                padding: 1.2rem;
            }

            .feature-icon {
                font-size: 2rem;
            }
            .feature h3 {
                font-size: 1.1rem;
            }
            .feature p {
                font-size: 0.9rem;
            }
        }

        /* ===== ANIMACIONES ===== */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        /* Aplicar animaciones a elementos */
        .feature {
            animation: fadeIn 0.4s ease-out forwards;
        }

        .button {
            animation: fadeIn 0.3s ease-out forwards;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="logo">ByteGol</div>
        <div class="theme-toggle">
            <label for="theme-toggle">Modo oscuro</label>
            <label class="toggle-switch">
                <input type="checkbox" id="theme-toggle">
                <span class="slider"></span>
            </label>
        </div>
    </header>

    <div class="container">
        <div class="welcome-section">
            <h1 class="title">Bienvenido a ByteGol</h1>
            <p class="subtitle">Conéctate con amigos, comparte momentos y descubre contenido interesante</p>

            <div class="button-group">
                <a href="registro/registro.php" class="button button-primary">Registrarse</a>
                <a href="logueo/login.php" class="button button-secondary">Iniciar Sesión</a>
            </div>
        </div>

        <div class="features">
            <div class="feature">
                <div class="feature-icon">👥</div>
                <h3>Conecta con amigos</h3>
                <p>Encuentra y conecta con personas de todo el mundo</p>
            </div>
            <div class="feature">
                <div class="feature-icon">📸</div>
                <h3>Comparte momentos</h3>
                <p>Publica fotos, videos y actualizaciones</p>
            </div>
            <div class="feature">
                <div class="feature-icon">💬</div>
                <h3>Chatea en tiempo real</h3>
                <p>Mantén conversaciones privadas o en grupo</p>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="footer-content">
            <div class="footer-logo">ByteGol</div>
            <div class="footer-links">
                <a href="index.php">Inicio</a>
                <a href="zZz/Politica.php">Política de Privacidad</a>
                <a href="zZz/terminos.php">Términos y Condiciones</a>
                <a href="zZz/contacto.php">Contacto</a>
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