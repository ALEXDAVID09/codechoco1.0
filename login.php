<?php
session_start();

// Configuración de la base de datos
$host = 'localhost:3309'; // Ajusta según tu configuración
$dbname = 'codechoco_denuncias';
$username = 'root'; // Ajusta según tu configuración
$password = ''; // Ajusta según tu configuración

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Configuración de seguridad
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');

// Si ya está logueado, redirigir al panel
if (isset($_SESSION['admin_loggedin']) && $_SESSION['admin_loggedin'] === true) {
    header('Location: admin.php');
    exit();
}

// Configuración de intentos de login
$max_attempts = 5;
$lockout_time = 300; // 5 minutos

// Inicializar contador de intentos si no existe
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt_time'] = 0;
}

$error_message = '';
$success_message = '';
$is_locked_out = false;
$show_register = false;

// Verificar si el usuario está bloqueado temporalmente
if ($_SESSION['login_attempts'] >= $max_attempts) {
    $time_since_last_attempt = time() - $_SESSION['last_attempt_time'];
    
    if ($time_since_last_attempt < $lockout_time) {
        $remaining_time = $lockout_time - $time_since_last_attempt;
        $error_message = "Demasiados intentos fallidos. Por favor espere ".ceil($remaining_time/60)." minutos antes de intentar nuevamente.";
        $is_locked_out = true;
    } else {
        // Reiniciar contador si ha pasado el tiempo de bloqueo
        $_SESSION['login_attempts'] = 0;
    }
}

// Función para actualizar intentos de login en BD
function updateLoginAttempts($pdo, $username, $success = false) {
    try {
        if ($success) {
            $stmt = $pdo->prepare("UPDATE administradores SET intentos_login = 0, bloqueado_hasta = NULL, ultimo_acceso = NOW() WHERE username = ?");
        } else {
            $stmt = $pdo->prepare("UPDATE administradores SET intentos_login = intentos_login + 1, bloqueado_hasta = IF(intentos_login >= 4, DATE_ADD(NOW(), INTERVAL 5 MINUTE), NULL) WHERE username = ?");
        }
        $stmt->execute([$username]);
    } catch(PDOException $e) {
        // Log error but don't expose to user
        error_log("Error updating login attempts: " . $e->getMessage());
    }
}

// Procesar registro de nuevo administrador
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register']) && !$is_locked_out) {
    $reg_username = trim($_POST['reg_username']);
    $reg_email = trim($_POST['reg_email']);
    $reg_password = $_POST['reg_password'];
    $reg_confirm_password = $_POST['reg_confirm_password'];
    $reg_nombre_completo = trim($_POST['reg_nombre_completo']);
    $reg_telefono = trim($_POST['reg_telefono']);
    
    // Validaciones
    if (empty($reg_username) || empty($reg_email) || empty($reg_password) || empty($reg_nombre_completo)) {
        $error_message = 'Por favor complete todos los campos obligatorios.';
    } elseif ($reg_password !== $reg_confirm_password) {
        $error_message = 'Las contraseñas no coinciden.';
    } elseif (strlen($reg_password) < 8) {
        $error_message = 'La contraseña debe tener al menos 8 caracteres.';
    } else {
        try {
            // Verificar si el usuario ya existe
            $stmt = $pdo->prepare("SELECT id FROM administradores WHERE username = ? OR email = ?");
            $stmt->execute([$reg_username, $reg_email]);
            
            if ($stmt->rowCount() > 0) {
                $error_message = 'El usuario o email ya existe.';
            } else {
                // Crear nuevo administrador
                $password_hash = password_hash($reg_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO administradores (username, email, password_hash, nombre_completo, telefono, rol, estado) VALUES (?, ?, ?, ?, ?, 'Admin', 'Activo')");
                $stmt->execute([$reg_username, $reg_email, $password_hash, $reg_nombre_completo, $reg_telefono]);
                
                $success_message = 'Administrador registrado exitosamente. Ahora puede iniciar sesión.';
                $show_register = false; // Redirigir al formulario de login
            }
        } catch(PDOException $e) {
            $error_message = 'Error al registrar el administrador. Intente nuevamente.';
            error_log("Registration error: " . $e->getMessage());
        }
    }
}

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login']) && !$is_locked_out) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    // Validación básica
    if (empty($username) || empty($password)) {
        $error_message = 'Por favor complete todos los campos.';
    } else {
        try {
            // Buscar administrador en la base de datos
            $stmt = $pdo->prepare("SELECT id, username, password_hash, nombre_completo, rol, estado, intentos_login, bloqueado_hasta FROM administradores WHERE username = ? AND estado = 'Activo'");
            $stmt->execute([$username]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($admin) {
                // Verificar si está bloqueado
                if ($admin['bloqueado_hasta'] && strtotime($admin['bloqueado_hasta']) > time()) {
                    $error_message = 'Cuenta temporalmente bloqueada. Intente más tarde.';
                } elseif (password_verify($password, $admin['password_hash'])) {
                    // Login exitoso
                    $_SESSION['admin_loggedin'] = true;
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_username'] = $admin['username'];
                    $_SESSION['admin_nombre'] = $admin['nombre_completo'];
                    $_SESSION['admin_rol'] = $admin['rol'];
                    $_SESSION['login_time'] = time();
                    $_SESSION['login_attempts'] = 0;
                    
                    // Actualizar BD
                    updateLoginAttempts($pdo, $username, true);
                    
                    // Regenerar ID de sesión por seguridad
                    session_regenerate_id(true);
                    
                    header('Location: admin.php');
                    exit();
                } else {
                    // Login fallido
                    $_SESSION['login_attempts']++;
                    $_SESSION['last_attempt_time'] = time();
                    
                    updateLoginAttempts($pdo, $username, false);
                    
                    $remaining_attempts = $max_attempts - $_SESSION['login_attempts'];
                    
                    if ($remaining_attempts > 0) {
                        $error_message = 'Credenciales incorrectas. Le quedan '.$remaining_attempts.' intentos.';
                    } else {
                        $error_message = "Demasiados intentos fallidos. Por favor espere ".ceil($lockout_time/60)." minutos antes de intentar nuevamente.";
                    }
                }
            } else {
                // Usuario no encontrado
                $_SESSION['login_attempts']++;
                $_SESSION['last_attempt_time'] = time();
                
                $remaining_attempts = $max_attempts - $_SESSION['login_attempts'];
                
                if ($remaining_attempts > 0) {
                    $error_message = 'Credenciales incorrectas. Le quedan '.$remaining_attempts.' intentos.';
                } else {
                    $error_message = "Demasiados intentos fallidos. Por favor espere ".ceil($lockout_time/60)." minutos antes de intentar nuevamente.";
                }
            }
        } catch(PDOException $e) {
            $error_message = 'Error de conexión. Intente nuevamente.';
            error_log("Login error: " . $e->getMessage());
        }
    }
}

// Generar token CSRF si no existe
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Toggle para mostrar formulario de registro
if (isset($_GET['register'])) {
    $show_register = true;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administración - CodeChoco Quibdó</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --choco-green: #2D5016;
            --choco-green-light: #4A7C28;
            --choco-yellow: #F4D03F;
            --choco-blue: #1B4F72;
            --choco-blue-light: #2E86AB;
            --choco-gold: #D4AF37;
            --text-dark: #2C3E50;
            --text-light: #5D6D7E;
            --bg-light: #F8F9FA;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--choco-green) 0%, var(--choco-green-light) 50%, var(--choco-blue) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            width: 100%;
            max-width: 450px;
            position: relative;
        }

        .login-header {
            background: linear-gradient(135deg, var(--choco-green) 0%, var(--choco-green-light) 50%, var(--choco-blue) 100%);
            padding: 40px 30px;
            text-align: center;
            color: white;
            position: relative;
        }

        .login-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 20"><defs><radialGradient id="g"><stop offset="20%" stop-color="%23ffffff" stop-opacity="0.1"/><stop offset="50%" stop-color="%23ffffff" stop-opacity="0.05"/><stop offset="100%" stop-color="%23ffffff" stop-opacity="0"/></radialGradient></defs><rect width="100" height="20" fill="url(%23g)"/></svg>');
            opacity: 0.1;
        }

        .logo-container {
            margin-bottom: 20px;
            position: relative;
            z-index: 2;
        }

        .logo-container img {
            width: 80px;
            height: 80px;
            object-fit: contain;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            padding: 10px;
            border: 2px solid rgba(255, 255, 255, 0.2);
        }

        .logo-placeholder {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            border: 2px solid rgba(255, 255, 255, 0.2);
            color: white;
            font-size: 24px;
        }

        .login-header h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
            position: relative;
            z-index: 2;
        }

        .login-header p {
            font-size: 16px;
            opacity: 0.9;
            position: relative;
            z-index: 2;
        }

        .choco-badge {
            background: linear-gradient(45deg, var(--choco-yellow), var(--choco-gold));
            color: var(--text-dark);
            padding: 8px 20px;
            border-radius: 25px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 1rem;
            position: relative;
            z-index: 2;
        }

        .login-body {
            padding: 40px 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--choco-green);
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 16px;
            background: white;
            color: #333;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--choco-green);
            box-shadow: 0 0 0 3px rgba(45, 80, 22, 0.1);
            transform: translateY(-1px);
        }

        .btn-primary {
            width: 100%;
            background: linear-gradient(135deg, var(--choco-green) 0%, var(--choco-green-light) 50%, var(--choco-blue) 100%);
            color: white;
            border: none;
            padding: 16px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 15px;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(45, 80, 22, 0.3);
        }

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .btn-secondary {
            width: 100%;
            background: transparent;
            color: var(--choco-green);
            border: 2px solid var(--choco-green);
            padding: 14px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 15px;
        }

        .btn-secondary:hover {
            background: var(--choco-green);
            color: white;
            transform: translateY(-1px);
        }

        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            border: none;
        }

        .alert-danger {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #dc2626;
        }

        .alert-success {
            background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
            color: #059669;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--choco-green);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
            justify-content: center;
            width: 100%;
            padding: 10px;
        }

        .back-link:hover {
            color: var(--choco-green-light);
            background: rgba(45, 80, 22, 0.05);
            border-radius: 8px;
        }

        .toggle-link {
            text-align: center;
            margin: 20px 0;
        }

        .toggle-link a {
            color: var(--choco-green);
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: color 0.3s ease;
        }

        .toggle-link a:hover {
            color: var(--choco-green-light);
            text-decoration: underline;
        }

        .row {
            margin: 0 -10px;
        }

        .col-6 {
            padding: 0 10px;
        }

        .form-text {
            font-size: 12px;
            color: var(--text-light);
            margin-top: 4px;
        }

        @media (max-width: 480px) {
            .login-card {
                margin: 10px;
                max-width: 100%;
            }
            
            .login-header {
                padding: 30px 20px;
            }
            
            .login-body {
                padding: 30px 20px;
            }

            .choco-badge {
                font-size: 0.8rem;
                padding: 6px 16px;
            }
        }

        .form-container {
            transition: all 0.3s ease;
        }

        .slide-in {
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        /* Animaciones */
        .pulse-animation {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <div class="logo-container">
                <img src="assets/images/images.png" alt="CodeChoco Logo">
            </div>
            <div class="choco-badge">
                <i class="fas fa-map-marker-alt me-2"></i>Quibdó, Chocó - Colombia
            </div>
            <h1>CodeChoco</h1>
            <p>Panel de Administración • Quibdó</p>
        </div>
        
        <div class="login-body">
            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?= htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <?= htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <?php if (!$show_register): ?>
                <!-- FORMULARIO DE LOGIN -->
                <div class="form-container slide-in">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        
                        <div class="form-group">
                            <label for="username" class="form-label">
                                <i class="fas fa-user me-1"></i>
                                Usuario
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="username" 
                                   name="username" 
                                   placeholder="Ingrese su usuario"
                                   value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username'], ENT_QUOTES, 'UTF-8') : '' ?>"
                                   required
                                   <?= $is_locked_out ? 'disabled' : '' ?>>
                        </div>

                        <div class="form-group">
                            <label for="password" class="form-label">
                                <i class="fas fa-lock me-1"></i>
                                Contraseña
                            </label>
                            <input type="password" 
                                   class="form-control" 
                                   id="password" 
                                   name="password" 
                                   placeholder="Ingrese su contraseña"
                                   required
                                   <?= $is_locked_out ? 'disabled' : '' ?>>
                        </div>

                        <button type="submit" name="login" class="btn-primary pulse-animation" <?= $is_locked_out ? 'disabled' : '' ?>>
                            <i class="fas fa-sign-in-alt me-2"></i>
                            Iniciar Sesión
                        </button>
                    </form>

                    <div class="toggle-link">
                        <a href="?register=1">
                            <i class="fas fa-user-plus me-1"></i>
                            ¿No tienes cuenta? Regístrate aquí
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <!-- FORMULARIO DE REGISTRO -->
                <div class="form-container slide-in">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        
                        <div class="form-group">
                            <label for="reg_nombre_completo" class="form-label">
                                <i class="fas fa-id-card me-1"></i>
                                Nombre Completo *
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="reg_nombre_completo" 
                                   name="reg_nombre_completo" 
                                   placeholder="Ingrese su nombre completo"
                                   value="<?= isset($_POST['reg_nombre_completo']) ? htmlspecialchars($_POST['reg_nombre_completo'], ENT_QUOTES, 'UTF-8') : '' ?>"
                                   required>
                        </div>

                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label for="reg_username" class="form-label">
                                        <i class="fas fa-user me-1"></i>
                                        Usuario *
                                    </label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="reg_username" 
                                           name="reg_username" 
                                           placeholder="Usuario"
                                           value="<?= isset($_POST['reg_username']) ? htmlspecialchars($_POST['reg_username'], ENT_QUOTES, 'UTF-8') : '' ?>"
                                           required>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label for="reg_telefono" class="form-label">
                                        <i class="fas fa-phone me-1"></i>
                                        Teléfono
                                    </label>
                                    <input type="tel" 
                                           class="form-control" 
                                           id="reg_telefono" 
                                           name="reg_telefono" 
                                           placeholder="Teléfono"
                                           value="<?= isset($_POST['reg_telefono']) ? htmlspecialchars($_POST['reg_telefono'], ENT_QUOTES, 'UTF-8') : '' ?>">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="reg_email" class="form-label">
                                <i class="fas fa-envelope me-1"></i>
                                Email *
                            </label>
                            <input type="email" 
                                   class="form-control" 
                                   id="reg_email" 
                                   name="reg_email" 
                                   placeholder="correo@ejemplo.com"
                                   value="<?= isset($_POST['reg_email']) ? htmlspecialchars($_POST['reg_email'], ENT_QUOTES, 'UTF-8') : '' ?>"
                                   required>
                        </div>

                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label for="reg_password" class="form-label">
                                        <i class="fas fa-lock me-1"></i>
                                        Contraseña *
                                    </label>
                                    <input type="password" 
                                           class="form-control" 
                                           id="reg_password" 
                                           name="reg_password" 
                                           placeholder="Contraseña"
                                           required>
                                    <div class="form-text">Mínimo 8 caracteres</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label for="reg_confirm_password" class="form-label">
                                        <i class="fas fa-lock me-1"></i>
                                        Confirmar *
                                    </label>
                                    <input type="password" 
                                           class="form-control" 
                                           id="reg_confirm_password" 
                                           name="reg_confirm_password" 
                                           placeholder="Confirmar"
                                           required>
                                </div>
                            </div>
                        </div>

                        <button type="submit" name="register" class="btn-primary">
                            <i class="fas fa-user-plus me-2"></i>
                            Registrar Administrador
                        </button>
                    </form>

                    <div class="toggle-link">
                        <a href="login.php">
                            <i class="fas fa-sign-in-alt me-1"></i>
                            ¿Ya tienes cuenta? Inicia sesión
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <a href="index.php" class="back-link">
                <i class="fas fa-arrow-left"></i>
                Volver al sitio principal
            </a>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Validación en tiempo real para el formulario de registro
        document.addEventListener('DOMContentLoaded', function() {
            const passwordField = document.getElementById('reg_password');
            const confirmPasswordField = document.getElementById('reg_confirm_password');
            
            if (passwordField && confirmPasswordField) {
                function validatePasswords() {
                    const password = passwordField.value;
                    const confirmPassword = confirmPasswordField.value;
                    
                    if (password.length > 0 && password.length < 8) {
                        passwordField.style.borderColor = '#dc2626';
                    } else {
                        passwordField.style.borderColor = '#e1e5e9';
                    }
                    
                    if (confirmPassword.length > 0 && password !== confirmPassword) {
                        confirmPasswordField.style.borderColor = '#dc2626';
                    } else {
                        confirmPasswordField.style.borderColor = '#e1e5e9';
                    }
                }
                
                passwordField.addEventListener('input', validatePasswords);
                confirmPasswordField.addEventListener('input', validatePasswords);
            }
        });
    </script>
</body>
</html>