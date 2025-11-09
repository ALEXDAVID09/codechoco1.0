<?php
require_once 'config.php';

// IMPORTANTE: Manejar logout ANTES de cualquier salida HTML
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit();
}

// Verificar si el usuario está autenticado
if (!isset($_SESSION['admin_loggedin']) || $_SESSION['admin_loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

// Conectar a la base de datos usando la función de config.php
$pdo = conectarDB();

// Función auxiliar para determinar el estado correcto
function obtenerEstadoNormalizado($estado) {
    if (is_null($estado) || trim($estado) === '') {
        return 'pendiente';
    }
    $estado_limpio = trim(strtolower($estado));
    if (in_array($estado_limpio, ['en_proceso', 'en proceso', 'enproceso', 'proceso'])) {
        return 'en_proceso';
    }
    if (in_array($estado_limpio, ['resuelto', 'resueltas', 'completado', 'finalizado'])) {
        return 'resuelto';
    }
    if (in_array($estado_limpio, ['archivado', 'archivo'])) {
        return 'archivado';
    }
    return 'pendiente';
}

// Variables para mensajes
$error = null;
$success_message = null;

// Procesar exportación a Excel
// Procesar exportación a Excel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['exportar_excel'])) {
    try {
        $filtro_estado = $_POST['filtro_exportar'] ?? 'todos';
        
        // Construir consulta según el filtro
        $where_clause = "";
        $params = [];
        
        if ($filtro_estado !== 'todos') {
            if ($filtro_estado === 'archivado') {
                $where_clause = "WHERE TRIM(LOWER(estado)) = 'archivado'";
            } elseif ($filtro_estado === 'activos') {
                $where_clause = "WHERE (estado IS NULL OR TRIM(LOWER(estado)) NOT IN ('archivado'))";
            } else {
                $estado_normalizado = obtenerEstadoNormalizado($filtro_estado);
                if ($estado_normalizado === 'pendiente') {
                    $where_clause = "WHERE (estado IS NULL OR TRIM(estado) = '' OR TRIM(LOWER(estado)) = 'pendiente')";
                } else {
                    $where_clause = "WHERE TRIM(LOWER(estado)) LIKE ?";
                    $params[] = '%' . $estado_normalizado . '%';
                }
            }
        }
        
        $export_query = "SELECT 
            codigo_seguimiento,
            tipo,
            descripcion,
            nombre_denunciante,
            email_denunciante,
            contacto_denunciante,
            estado,
            fecha,
            latitud,
            longitud,
            (SELECT COUNT(*) FROM denuncia_fotos WHERE denuncia_id = d.id) as fotos_count,
            (SELECT COUNT(*) FROM denuncia_actualizaciones WHERE denuncia_id = d.id) as actualizaciones_count
        FROM denuncias d 
        $where_clause 
        ORDER BY fecha DESC";
        
        $stmt = $pdo->prepare($export_query);
        $stmt->execute($params);
        $denuncias = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($denuncias)) {
            $error = "No hay denuncias para exportar con el filtro seleccionado.";
        } else {
            // Nombre del archivo
            $filename = 'denuncias_' . $filtro_estado . '_' . date('Y-m-d_H-i-s') . '.xls';
            
            // Headers para Excel
            header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Expires: 0');
            
            // BOM para UTF-8
            echo "\xEF\xBB\xBF";
            
            // Crear tabla HTML (Excel lo interpreta perfectamente)
            echo '<table border="1">';
            
            // Encabezados
            echo '<thead>';
            echo '<tr style="background-color: #2E8B57; color: white; font-weight: bold;">';
            echo '<th>Codigo Seguimiento</th>';
            echo '<th>Tipo</th>';
            echo '<th>Descripcion</th>';
            echo '<th>Nombre Denunciante</th>';
            echo '<th>Email</th>';
            echo '<th>Contacto</th>';
            echo '<th>Estado</th>';
            echo '<th>Fecha</th>';
            echo '<th>Latitud</th>';
            echo '<th>Longitud</th>';
            echo '<th>Fotos</th>';
            echo '<th>Actualizaciones</th>';
            echo '</tr>';
            echo '</thead>';
            
            // Datos
            echo '<tbody>';
            foreach ($denuncias as $denuncia) {
                $estado_normalizado = obtenerEstadoNormalizado($denuncia['estado']);
                
                // Limpiar descripción
                $descripcion = $denuncia['descripcion'] ?? 'Sin descripcion';
                $descripcion = preg_replace('/\s+/', ' ', $descripcion);
                $descripcion = trim($descripcion);
                $descripcion = htmlspecialchars($descripcion);
                
                // Formatear fecha
                $fecha = 'Sin fecha';
                if (!empty($denuncia['fecha'])) {
                    try {
                        $fecha = date('d/m/Y H:i', strtotime($denuncia['fecha']));
                    } catch (Exception $e) {
                        $fecha = $denuncia['fecha'];
                    }
                }
                
                // Formatear coordenadas
                $latitud = '';
                $longitud = '';
                if (!empty($denuncia['latitud'])) {
                    $latitud = number_format((float)$denuncia['latitud'], 6, '.', '');
                }
                if (!empty($denuncia['longitud'])) {
                    $longitud = number_format((float)$denuncia['longitud'], 6, '.', '');
                }
                
                // Color según estado
                $color_fondo = '#FFFFFF';
                switch($estado_normalizado) {
                    case 'pendiente': $color_fondo = '#FFF3CD'; break;
                    case 'en_proceso': $color_fondo = '#CCE5FF'; break;
                    case 'resuelto': $color_fondo = '#D1E7DD'; break;
                    case 'archivado': $color_fondo = '#E2E3E5'; break;
                }
                
                echo '<tr>';
                echo '<td>' . htmlspecialchars($denuncia['codigo_seguimiento'] ?? 'SIN-CODIGO') . '</td>';
                echo '<td>' . htmlspecialchars($denuncia['tipo'] ?? 'Sin tipo') . '</td>';
                echo '<td>' . $descripcion . '</td>';
                echo '<td>' . htmlspecialchars($denuncia['nombre_denunciante'] ?? 'Anonimo') . '</td>';
                echo '<td>' . htmlspecialchars($denuncia['email_denunciante'] ?? 'Sin email') . '</td>';
                echo '<td>' . htmlspecialchars($denuncia['contacto_denunciante'] ?? 'Sin contacto') . '</td>';
                echo '<td style="background-color: ' . $color_fondo . ';">' . htmlspecialchars(ucfirst(str_replace('_', ' ', $estado_normalizado))) . '</td>';
                echo '<td>' . $fecha . '</td>';
                echo '<td>' . $latitud . '</td>';
                echo '<td>' . $longitud . '</td>';
                echo '<td style="text-align: center;">' . ($denuncia['fotos_count'] ?? 0) . '</td>';
                echo '<td style="text-align: center;">' . ($denuncia['actualizaciones_count'] ?? 0) . '</td>';
                echo '</tr>';
            }
            echo '</tbody>';
            
            // Pie de tabla
            echo '<tfoot>';
            echo '<tr style="background-color: #F8F9FA;">';
            echo '<td colspan="12" style="text-align: center; font-style: italic;">';
            echo 'Total de registros: ' . count($denuncias) . ' | Generado: ' . date('d/m/Y H:i:s');
            echo '</td>';
            echo '</tr>';
            echo '</tfoot>';
            
            echo '</table>';
            exit();
        }
    } catch (Exception $e) {
        $error = "Error al exportar: " . $e->getMessage();
    }
}
// Procesar archivado de denuncias
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['archivar_denuncia'])) {
    try {
        $denuncia_id = (int)$_POST['denuncia_id'];
        $motivo_archivo = trim($_POST['motivo_archivo']);
        
        // Buscar la denuncia para verificar que existe y NO está archivada
        $check_query = "SELECT id, estado FROM denuncias WHERE id = ? AND (estado IS NULL OR TRIM(LOWER(estado)) != 'archivado')";
        $check_stmt = $pdo->prepare($check_query);
        $check_stmt->execute([$denuncia_id]);
        
        if ($check_stmt->rowCount() > 0) {
            $denuncia_actual = $check_stmt->fetch(PDO::FETCH_ASSOC);
            $estado_previo = obtenerEstadoNormalizado($denuncia_actual['estado']);
            
            $pdo->beginTransaction();
            
            try {
                // Actualizar estado a archivado y guardar el estado anterior
                $update_query = "UPDATE denuncias SET estado = 'archivado', estado_anterior = ? WHERE id = ?";
                $stmt = $pdo->prepare($update_query);
                $stmt->execute([$estado_previo, $denuncia_id]);
                
                // Registrar la acción en el historial
                $insert_update = "INSERT INTO denuncia_actualizaciones (denuncia_id, descripcion, fecha, responsable, estado_anterior, estado_nuevo) VALUES (?, ?, NOW(), ?, ?, ?)";
                $stmt2 = $pdo->prepare($insert_update);
                $descripcion_completa = "ARCHIVADO - " . $motivo_archivo;
                $stmt2->execute([$denuncia_id, $descripcion_completa, "Administrador", $estado_previo, 'archivado']);
                
                $pdo->commit();
                $success_message = "Denuncia archivada correctamente. Estado anterior: " . ucfirst(str_replace('_', ' ', $estado_previo));
                
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
        } else {
            $error = "La denuncia no existe o ya está archivada";
        }
    } catch (Exception $e) {
        $error = "Error al archivar la denuncia: " . $e->getMessage();
    }
}

// Procesar desarchivar denuncias
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['desarchivar_denuncia'])) {
    try {
        $denuncia_id = (int)$_POST['denuncia_id'];
        
        // Buscar la denuncia archivada Y obtener su estado anterior
        $check_query = "SELECT id, estado_anterior FROM denuncias WHERE id = ? AND TRIM(LOWER(estado)) = 'archivado'";
        $check_stmt = $pdo->prepare($check_query);
        $check_stmt->execute([$denuncia_id]);
        
        if ($check_stmt->rowCount() > 0) {
            $denuncia_data = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Decidir qué estado usar
            $nuevo_estado = isset($_POST['nuevo_estado_desarchivar']) && !empty($_POST['nuevo_estado_desarchivar']) 
                ? trim($_POST['nuevo_estado_desarchivar']) 
                : ($denuncia_data['estado_anterior'] ?? 'pendiente');
            
            $pdo->beginTransaction();
            
            try {
                // Restaurar el estado Y limpiar estado_anterior
                $update_query = "UPDATE denuncias SET estado = ?, estado_anterior = NULL WHERE id = ?";
                $stmt = $pdo->prepare($update_query);
                $stmt->execute([$nuevo_estado, $denuncia_id]);
                
                // Registrar en historial
                $insert_update = "INSERT INTO denuncia_actualizaciones (denuncia_id, descripcion, fecha, responsable, estado_anterior, estado_nuevo) VALUES (?, ?, NOW(), ?, ?, ?)";
                $stmt2 = $pdo->prepare($insert_update);
                $descripcion = "DESARCHIVADO - Restaurado a: " . ucfirst(str_replace('_', ' ', $nuevo_estado));
                $stmt2->execute([$denuncia_id, $descripcion, "Administrador", 'archivado', $nuevo_estado]);
                
                $pdo->commit();
                $success_message = "Denuncia desarchivada y restaurada a: " . ucfirst(str_replace('_', ' ', $nuevo_estado));
                
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
        } else {
            $error = "La denuncia no existe o no está archivada";
        }
    } catch (Exception $e) {
        $error = "Error al desarchivar la denuncia: " . $e->getMessage();
    }
}

// Procesar actualizaciones de estado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_estado'])) {
    try {
        $denuncia_id = (int)$_POST['denuncia_id'];
        $nuevo_estado = trim($_POST['nuevo_estado']);
        $descripcion_actualizacion = trim($_POST['descripcion_actualizacion']);
        
        // Verificar que la denuncia existe
        $check_query = "SELECT id, estado FROM denuncias WHERE id = ?";
        $check_stmt = $pdo->prepare($check_query);
        $check_stmt->execute([$denuncia_id]);
        
        if ($check_stmt->rowCount() > 0) {
            $denuncia_actual = $check_stmt->fetch(PDO::FETCH_ASSOC);
            $estado_anterior = obtenerEstadoNormalizado($denuncia_actual['estado']);
            
            $pdo->beginTransaction();
            try {
                // Actualizar estado de la denuncia
                $update_query = "UPDATE denuncias SET estado = ? WHERE id = ?";
                $stmt = $pdo->prepare($update_query);
                $resultado = $stmt->execute([$nuevo_estado, $denuncia_id]);
                
                if ($resultado) {
                    // Agregar actualización con estados
                    $insert_update = "INSERT INTO denuncia_actualizaciones (denuncia_id, descripcion, fecha, responsable, estado_anterior, estado_nuevo) VALUES (?, ?, NOW(), ?, ?, ?)";
                    $stmt2 = $pdo->prepare($insert_update);
                    $stmt2->execute([$denuncia_id, $descripcion_actualizacion, "Administrador", $estado_anterior, $nuevo_estado]);
                    
                    $pdo->commit();
                    $success_message = "Denuncia actualizada correctamente.";
                } else {
                    $pdo->rollBack();
                    $error = "Error al actualizar la denuncia";
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
        } else {
            $error = "Denuncia no encontrada";
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Obtener filtro actual
$filtro_actual = $_GET['filtro'] ?? 'activos';

// Construir WHERE clause según el filtro
$where_clause = "";
$params = [];

switch ($filtro_actual) {
    case 'todos':
        // Sin filtro
        break;
    case 'archivado':
        $where_clause = "WHERE TRIM(LOWER(d.estado)) = 'archivado'";
        break;
    case 'activos':
        $where_clause = "WHERE (d.estado IS NULL OR TRIM(LOWER(d.estado)) NOT IN ('archivado'))";
        break;
    case 'pendientes':
        $where_clause = "WHERE (d.estado IS NULL OR TRIM(d.estado) = '' OR TRIM(LOWER(d.estado)) = 'pendiente')";
        break;
    case 'proceso':
        $where_clause = "WHERE TRIM(LOWER(d.estado)) IN ('en_proceso', 'en proceso', 'enproceso', 'proceso')";
        break;
    case 'resueltos':
        $where_clause = "WHERE TRIM(LOWER(d.estado)) IN ('resuelto', 'resueltas', 'completado', 'finalizado')";
        break;
}

// Obtener estadísticas mejoradas
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE 
        WHEN d.estado IS NULL OR TRIM(d.estado) = '' OR TRIM(LOWER(d.estado)) = 'pendiente' 
        THEN 1 ELSE 0 
    END) as pendientes,
    SUM(CASE 
        WHEN TRIM(LOWER(d.estado)) IN ('en_proceso', 'en proceso', 'enproceso', 'proceso') 
        THEN 1 ELSE 0 
    END) as en_proceso,
    SUM(CASE 
        WHEN TRIM(LOWER(d.estado)) IN ('resuelto', 'resueltas', 'completado', 'finalizado') 
        THEN 1 ELSE 0 
    END) as resueltas,
    SUM(CASE 
        WHEN TRIM(LOWER(d.estado)) = 'archivado'
        THEN 1 ELSE 0 
    END) as archivadas,
    SUM(CASE 
        WHEN d.estado IS NULL OR TRIM(LOWER(d.estado)) NOT IN ('archivado')
        THEN 1 ELSE 0 
    END) as activas
FROM denuncias d";

$stats_result = $pdo->query($stats_query);
$stats = $stats_result->fetch(PDO::FETCH_ASSOC);

// Obtener denuncias según filtro con paginación
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Contar total de registros para paginación
$count_query = "SELECT COUNT(*) FROM denuncias d $where_clause";
$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($params);
$total_registros = $count_stmt->fetchColumn();
$total_paginas = ceil($total_registros / $per_page);

// Obtener denuncias con paginación
$denuncias_query = "SELECT d.*, d.estado_anterior,
    (SELECT COUNT(*) FROM denuncia_fotos WHERE denuncia_id = d.id) as fotos_count,
    (SELECT COUNT(*) FROM denuncia_actualizaciones WHERE denuncia_id = d.id) as actualizaciones_count,
    CASE 
        WHEN TIME(d.fecha) = '00:00:00' THEN DATE(d.fecha)
        ELSE d.fecha 
    END as fecha_mostrar
FROM denuncias d 
$where_clause
ORDER BY 
    CASE WHEN TRIM(LOWER(d.estado)) = 'archivado' THEN 1 ELSE 0 END,
    d.fecha DESC 
LIMIT $per_page OFFSET $offset";

$denuncias_result = $pdo->query($denuncias_query);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - CodeChoco Denuncias</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-green: #2E8B57;
            --secondary-green: #228B22;
            --accent-gold: #DAA520;
            --earth-brown: #8B4513;
            --river-blue: #4682B4;
            --light-green: #90EE90;
            --dark-green: #1C5F3C;
            --warm-orange: #FF8C00;
            --archive-gray: #6C757D;
            --bg-light: #F5F7FA;
            --text-dark: #2C3E50;
            --shadow: rgba(46, 139, 87, 0.15);
        }

        * {
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: linear-gradient(135deg, var(--bg-light) 0%, #E8F4F0 100%);
            color: var(--text-dark);
            min-height: 100vh;
        }

        .navbar-custom {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--secondary-green) 100%);
            box-shadow: 0 4px 20px var(--shadow);
            padding: 1rem 0;
        }

        .navbar-brand {
            color: white !important;
            font-weight: 700;
            font-size: 1.4rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .navbar-brand:hover {
            color: var(--accent-gold) !important;
            transform: scale(1.05);
            transition: all 0.3s ease;
        }

        .nav-link {
            color: rgba(255,255,255,0.9) !important;
            font-weight: 500;
            padding: 0.5rem 1rem !important;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            color: var(--accent-gold) !important;
            background: rgba(255,255,255,0.1);
            transform: translateY(-2px);
        }

        .stats-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 8px 25px var(--shadow);
            transition: all 0.3s ease;
            border: none;
            overflow: hidden;
            position: relative;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(46, 139, 87, 0.25);
        }

        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-green), var(--accent-gold));
        }

        .stats-card .card-body {
            padding: 2rem 1.5rem;
        }

        .stats-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0.5rem 0;
        }

        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
        }

        .icon-total { background: linear-gradient(135deg, var(--river-blue), #5DADE2); }
        .icon-active { background: linear-gradient(135deg, var(--primary-green), var(--secondary-green)); }
        .icon-pending { background: linear-gradient(135deg, var(--warm-orange), #F39C12); }
        .icon-process { background: linear-gradient(135deg, var(--river-blue), #3498DB); }
        .icon-resolved { background: linear-gradient(135deg, var(--primary-green), var(--secondary-green)); }
        .icon-archived { background: linear-gradient(135deg, var(--archive-gray), #868e96); }

        .estado-badge {
            padding: 8px 16px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 2px solid;
            transition: all 0.3s ease;
        }

        .estado-pendiente {
            background: linear-gradient(135deg, #FFF3CD, #FFECB5);
            color: #856404;
            border-color: #F0D86C;
        }

        .estado-en_proceso {
            background: linear-gradient(135deg, #CCE5FF, #B3D9FF);
            color: #004085;
            border-color: #7DB8E8;
        }

        .estado-resuelto {
            background: linear-gradient(135deg, #D1E7DD, #BFE3C7);
            color: #0F5132;
            border-color: #A3D9A5;
        }

        .estado-archivado {
            background: linear-gradient(135deg, #E2E3E5, #D6D8DB);
            color: #383D41;
            border-color: #ADB2B5;
            opacity: 0.8;
        }

        .estado-vacio {
            background: linear-gradient(135deg, #F8D7DA, #F1B4B9);
            color: #721C24;
            border-color: #E88A95;
        }

        .main-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px var(--shadow);
            border: none;
            overflow: hidden;
        }

        .main-card .card-header {
            background: linear-gradient(135deg, var(--primary-green), var(--secondary-green));
            color: white;
            padding: 1.5rem 2rem;
            border: none;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .table-custom {
            margin: 0;
        }

        .table-custom thead th {
            background: linear-gradient(135deg, #F8F9FA, #E9ECEF);
            color: var(--text-dark);
            font-weight: 600;
            border: none;
            padding: 1rem;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table-custom tbody tr {
            transition: all 0.3s ease;
            border: none;
        }

        .table-custom tbody tr:hover {
            background: linear-gradient(135deg, #F0F8F5, #E8F4F0);
            transform: scale(1.01);
        }

        .table-custom td {
            padding: 1rem;
            vertical-align: middle;
            border: none;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }

        /* Filas archivadas con estilo especial */
        .fila-archivada {
            opacity: 0.7;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef) !important;
        }

        .fila-archivada:hover {
            background: linear-gradient(135deg, #e9ecef, #dee2e6) !important;
        }

        .btn-custom {
            border-radius: 10px;
            font-weight: 500;
            padding: 8px 16px;
            transition: all 0.3s ease;
            border: none;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.85rem;
        }

        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary-green), var(--secondary-green));
            color: white;
        }

        .btn-primary-custom:hover {
            background: linear-gradient(135deg, var(--secondary-green), var(--dark-green));
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(46, 139, 87, 0.4);
        }

        .btn-archive {
            background: linear-gradient(135deg, var(--archive-gray), #868e96);
            color: white;
        }

        .btn-archive:hover {
            background: linear-gradient(135deg, #495057, #343a40);
            transform: translateY(-2px);
        }

        .btn-unarchive {
            background: linear-gradient(135deg, var(--river-blue), #3498DB);
            color: white;
        }

        .btn-unarchive:hover {
            background: linear-gradient(135deg, #2980B9, #1F618D);
            transform: translateY(-2px);
        }

        .codigo-seguimiento {
            background: linear-gradient(135deg, var(--primary-green), var(--secondary-green));
            color: white;
            padding: 6px 12px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .tipo-badge {
            background: linear-gradient(135deg, var(--accent-gold), #B8860B);
            color: white;
            padding: 6px 12px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.8rem;
        }

        .archivos-info {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .archivo-count {
            background: rgba(46, 139, 87, 0.1);
            color: var(--primary-green);
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary-green), var(--secondary-green));
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 20px;
            text-align: center;
            box-shadow: 0 10px 30px var(--shadow);
        }

        .page-header h1 {
            font-weight: 700;
            margin: 0;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .page-header p {
            margin: 0.5rem 0 0 0;
            opacity: 0.9;
            font-size: 1.1rem;
        }

        .filter-tabs {
            background: white;
            border-radius: 15px;
            padding: 1rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px var(--shadow);
        }

        .filter-tab {
            background: transparent;
            border: 2px solid var(--primary-green);
            color: var(--primary-green);
            padding: 8px 16px;
            margin: 0 5px;
            border-radius: 25px;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .filter-tab:hover {
            background: var(--primary-green);
            color: white;
            transform: translateY(-2px);
        }

        .filter-tab.active {
            background: var(--primary-green);
            color: white;
        }

        .export-section {
            background: linear-gradient(135deg, #fff, #f8f9fa);
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px var(--shadow);
        }

        .fade-in-up {
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .pagination {
            justify-content: center;
            margin-top: 2rem;
        }

        .page-link {
            color: var(--primary-green);
            border-color: var(--primary-green);
        }

        .page-link:hover {
            background-color: var(--primary-green);
            border-color: var(--primary-green);
            color: white;
        }

        .page-item.active .page-link {
            background-color: var(--primary-green);
            border-color: var(--primary-green);
        }

/* Estilos para las tabs de gestión */
.nav-tabs .nav-link {
    color: var(--text-dark) !important;
    border: 2px solid transparent;
    font-weight: 500;
    background-color: #f8f9fa;
}

.nav-tabs .nav-link:hover {
    border-color: var(--primary-green);
    color: var(--primary-green) !important;
    background-color: rgba(46, 139, 87, 0.05);
}

.nav-tabs .nav-link.active {
    color: var(--primary-green) !important;
    background-color: rgba(46, 139, 87, 0.1);
    border-color: var(--primary-green) var(--primary-green) transparent;
    font-weight: 600;
}

        .tab-content {
            padding: 1.5rem 0;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-shield-alt me-2"></i>CodeChoco Admin
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php" target="_blank">
                    <i class="fas fa-external-link-alt me-1"></i>Ver Sitio
                </a>
                <a class="nav-link" href="?logout=1">
                    <i class="fas fa-sign-out-alt me-1"></i>Cerrar Sesión
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Header de página -->
        <div class="page-header fade-in-up">
            <h1><i class="fas fa-tachometer-alt me-2"></i>Panel de Administración</h1>
            <p>Gestión Avanzada de Denuncias - Quibdó, Chocó</p>
        </div>

        <!-- Alertas -->
        <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Sección de Exportación -->
        <div class="export-section">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h5 class="mb-2" style="color: var(--primary-green);">
                        <i class="fas fa-file-excel me-2"></i>Exportar Denuncias a Excel
                    </h5>
                    <p class="text-muted mb-0">Descarga las denuncias en formato CSV compatible con Excel para análisis y respaldo.</p>
                </div>
                <div class="col-md-4">
                    <form method="POST" class="d-flex gap-2">
                        <select name="filtro_exportar" class="form-select form-select-sm">
                            <option value="todos">Todas las denuncias</option>
                            <option value="activos">Solo activas</option>
                            <option value="pendiente">Solo pendientes</option>
                            <option value="en_proceso">En proceso</option>
                            <option value="resuelto">Resueltas</option>
                            <option value="archivado">Archivadas</option>
                        </select>
                        <button type="submit" name="exportar_excel" class="btn btn-success btn-custom">
                            <i class="fas fa-download me-1"></i>Exportar
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Estadísticas Mejoradas -->
        <div class="row mb-4">
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="stats-card card">
                    <div class="card-body text-center">
                        <div class="stats-icon icon-total">
                            <i class="fas fa-database fa-lg text-white"></i>
                        </div>
                        <div class="stats-number text-info"><?= isset($stats['total']) ? $stats['total'] : 0 ?></div>
                        <p class="text-muted mb-0 fw-500">Total</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="stats-card card">
                    <div class="card-body text-center">
                        <div class="stats-icon icon-active">
                            <i class="fas fa-bolt fa-lg text-white"></i>
                        </div>
                        <div class="stats-number" style="color: var(--primary-green);"><?= isset($stats['activas']) ? $stats['activas'] : 0 ?></div>
                        <p class="text-muted mb-0 fw-500">Activas</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="stats-card card">
                    <div class="card-body text-center">
                        <div class="stats-icon icon-pending">
                            <i class="fas fa-clock fa-lg text-white"></i>
                        </div>
                        <div class="stats-number" style="color: var(--warm-orange);"><?= isset($stats['pendientes']) ? $stats['pendientes'] : 0 ?></div>
                        <p class="text-muted mb-0 fw-500">Pendientes</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="stats-card card">
                    <div class="card-body text-center">
                        <div class="stats-icon icon-process">
                            <i class="fas fa-cogs fa-lg text-white"></i>
                        </div>
                        <div class="stats-number" style="color: var(--river-blue);"><?= isset($stats['en_proceso']) ? $stats['en_proceso'] : 0 ?></div>
                        <p class="text-muted mb-0 fw-500">En Proceso</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="stats-card card">
                    <div class="card-body text-center">
                        <div class="stats-icon icon-resolved">
                            <i class="fas fa-check-circle fa-lg text-white"></i>
                        </div>
                        <div class="stats-number" style="color: var(--primary-green);"><?= isset($stats['resueltas']) ? $stats['resueltas'] : 0 ?></div>
                        <p class="text-muted mb-0 fw-500">Resueltas</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="stats-card card">
                    <div class="card-body text-center">
                        <div class="stats-icon icon-archived">
                            <i class="fas fa-archive fa-lg text-white"></i>
                        </div>
                        <div class="stats-number" style="color: var(--archive-gray);"><?= isset($stats['archivadas']) ? $stats['archivadas'] : 0 ?></div>
                        <p class="text-muted mb-0 fw-500">Archivadas</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtros de navegación -->
        <div class="filter-tabs text-center">
            <h6 class="mb-3" style="color: var(--primary-green);">
                <i class="fas fa-filter me-2"></i>Filtrar Denuncias
            </h6>
            <div>
                <a href="?filtro=activos" class="filter-tab <?= $filtro_actual === 'activos' ? 'active' : '' ?>">
                    <i class="fas fa-bolt me-1"></i>Activas (<?= $stats['activas'] ?>)
                </a>
                <a href="?filtro=pendientes" class="filter-tab <?= $filtro_actual === 'pendientes' ? 'active' : '' ?>">
                    <i class="fas fa-clock me-1"></i>Pendientes (<?= $stats['pendientes'] ?>)
                </a>
                <a href="?filtro=proceso" class="filter-tab <?= $filtro_actual === 'proceso' ? 'active' : '' ?>">
                    <i class="fas fa-cogs me-1"></i>En Proceso (<?= $stats['en_proceso'] ?>)
                </a>
                <a href="?filtro=resueltos" class="filter-tab <?= $filtro_actual === 'resueltos' ? 'active' : '' ?>">
                    <i class="fas fa-check-circle me-1"></i>Resueltas (<?= $stats['resueltas'] ?>)
                </a>
                <a href="?filtro=archivado" class="filter-tab <?= $filtro_actual === 'archivado' ? 'active' : '' ?>">
                    <i class="fas fa-archive me-1"></i>Archivadas (<?= $stats['archivadas'] ?>)
                </a>
                <a href="?filtro=todos" class="filter-tab <?= $filtro_actual === 'todos' ? 'active' : '' ?>">
                    <i class="fas fa-list me-1"></i>Todas (<?= $stats['total'] ?>)
                </a>
            </div>
        </div>

        <!-- Lista de Denuncias -->
        <div class="main-card card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    <?php
                    $titulo_filtro = [
                        'activos' => 'Denuncias Activas',
                        'pendientes' => 'Denuncias Pendientes',
                        'proceso' => 'Denuncias en Proceso',
                        'resueltos' => 'Denuncias Resueltas',
                        'archivado' => 'Denuncias Archivadas',
                        'todos' => 'Todas las Denuncias'
                    ];
                    echo $titulo_filtro[$filtro_actual] ?? 'Gestión de Denuncias';
                    ?>
                </h5>
                <div class="text-white">
                    <small>
                        <i class="fas fa-info-circle me-1"></i>
                        Mostrando <?= min($per_page, $total_registros - $offset) ?> de <?= $total_registros ?> registros
                        <?php if ($total_paginas > 1): ?>
                            - Página <?= $page ?> de <?= $total_paginas ?>
                        <?php endif; ?>
                    </small>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-custom">
                        <thead>
                            <tr>
                                <th><i class="fas fa-barcode me-1"></i>Código</th>
                                <th><i class="fas fa-tag me-1"></i>Tipo</th>
                                <th><i class="fas fa-user me-1"></i>Denunciante</th>
                                <th><i class="fas fa-calendar me-1"></i>Fecha</th>
                                <th><i class="fas fa-flag me-1"></i>Estado</th>
                                <th><i class="fas fa-paperclip me-1"></i>Archivos</th>
                                <th><i class="fas fa-cog me-1"></i>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($total_registros === 0): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No se encontraron denuncias</h5>
                                    <p class="text-muted">No hay denuncias que coincidan con el filtro seleccionado.</p>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php while ($denuncia = $denuncias_result->fetch(PDO::FETCH_ASSOC)): ?>
                            <?php 
                                $estado_normalizado = obtenerEstadoNormalizado($denuncia['estado']);
                                $estado_texto = ucfirst(str_replace('_', ' ', $estado_normalizado));
                                $es_archivada = ($estado_normalizado === 'archivado');
                            ?>
                            <tr <?= $es_archivada ? 'class="fila-archivada"' : '' ?>>
                                <td>
                                    <span class="codigo-seguimiento"><?= htmlspecialchars($denuncia['codigo_seguimiento'] ?? 'SIN-CÓDIGO') ?></span>
                                    <?php if ($es_archivada): ?>
                                    <br><small class="text-muted"><i class="fas fa-archive me-1"></i>Archivada</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="tipo-badge"><?= htmlspecialchars($denuncia['tipo'] ?? 'Sin tipo') ?></span>
                                </td>
                                <td>
                                    <div>
                                        <strong><?= htmlspecialchars($denuncia['nombre_denunciante'] ?? 'Anónimo') ?></strong>
                                        <br><small class="text-muted"><?= htmlspecialchars($denuncia['email_denunciante'] ?? 'Sin email') ?></small>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    if (isset($denuncia['fecha_mostrar'])) {
                                        $fecha = $denuncia['fecha_mostrar'];
                                        if (strpos($fecha, ':') !== false) {
                                            echo '<i class="fas fa-calendar-alt text-muted me-1"></i>' . date('d/m/Y', strtotime($fecha));
                                            echo '<br><small class="text-muted"><i class="fas fa-clock me-1"></i>' . date('H:i', strtotime($fecha)) . '</small>';
                                        } else {
                                            echo '<i class="fas fa-calendar-alt text-muted me-1"></i>' . date('d/m/Y', strtotime($fecha));
                                            echo '<br><small class="text-muted"><i class="fas fa-info-circle me-1"></i>Solo fecha</small>';
                                        }
                                    } else {
                                        echo '<i class="fas fa-question-circle text-muted me-1"></i>Sin fecha';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <span class="estado-badge estado-<?= $estado_normalizado ?>">
                                        <?= $estado_texto ?>
                                    </span>
                                    <?php if (is_null($denuncia['estado']) || trim($denuncia['estado']) === ''): ?>
                                    <br><small class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i>Estado vacío</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="archivos-info">
                                        <span class="archivo-count">
                                            <i class="fas fa-camera me-1"></i><?= $denuncia['fotos_count'] ?? 0 ?>
                                        </span>
                                        <span class="archivo-count">
                                            <i class="fas fa-comments me-1"></i><?= $denuncia['actualizaciones_count'] ?? 0 ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <button class="btn btn-outline-success btn-custom btn-sm" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#modal<?= $denuncia['id'] ?>" 
                                            title="Ver detalles">
                                        <i class="fas fa-eye"></i> Ver
                                    </button>
                                </td>
                            </tr>

                            <!-- Modal para cada denuncia -->
                            <div class="modal fade" id="modal<?= $denuncia['id'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-xl">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">
                                                <i class="fas fa-file-alt me-2"></i>
                                                Denuncia #<?= htmlspecialchars($denuncia['codigo_seguimiento'] ?? 'SIN-CÓDIGO') ?>
                                                <?php if ($es_archivada): ?>
                                                <span class="badge bg-secondary ms-2">
                                                    <i class="fas fa-archive me-1"></i>Archivada
                                                </span>
                                                <?php endif; ?>
                                            </h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <div class="card mb-3" style="border: 1px solid rgba(46, 139, 87, 0.2);">
                                                        <div class="card-header" style="background: linear-gradient(135deg, #F0F8F5, #E8F4F0); color: var(--text-dark); font-weight: 600;">
                                                            <i class="fas fa-info-circle me-2"></i>Información de la Denuncia
                                                        </div>
                                                        <div class="card-body">
                                                            <div class="mb-3">
                                                                <label class="fw-bold text-muted">Tipo:</label>
                                                                <div><span class="tipo-badge"><?= htmlspecialchars($denuncia['tipo'] ?? 'Sin tipo') ?></span></div>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="fw-bold text-muted">Estado Actual:</label>
                                                                <div>
                                                                    <span class="estado-badge estado-<?= $estado_normalizado ?>">
                                                                        <?= $estado_texto ?>
                                                                    </span>
                                                                    <?php if (is_null($denuncia['estado']) || trim($denuncia['estado']) === ''): ?>
                                                                    <br><small class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i>Estado original vacío - Mostrado como pendiente</small>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="fw-bold text-muted">Descripción:</label>
                                                                <div class="p-3 rounded" style="background: linear-gradient(135deg, #F8F9FA, #E9ECEF);">
                                                                    <?= nl2br(htmlspecialchars($denuncia['descripcion'] ?? 'Sin descripción')) ?>
                                                                </div>
                                                            </div>
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <label class="fw-bold text-muted">Fecha:</label>
                                                                    <div>
                                                                        <?php
                                                                        if (isset($denuncia['fecha_mostrar'])) {
                                                                            $fecha = $denuncia['fecha_mostrar'];
                                                                            if (strpos($fecha, ':') !== false) {
                                                                                echo '<i class="fas fa-calendar-alt text-muted me-1"></i>' . date('d/m/Y H:i', strtotime($fecha));
                                                                            } else {
                                                                                echo '<i class="fas fa-calendar-alt text-muted me-1"></i>' . date('d/m/Y', strtotime($fecha));
                                                                                echo '<br><small class="text-muted">Solo fecha registrada</small>';
                                                                            }
                                                                        } else {
                                                                            echo '<i class="fas fa-question-circle text-muted me-1"></i>Sin fecha registrada';
                                                                        }
                                                                        ?>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <label class="fw-bold text-muted">Ubicación:</label>
                                                                    <div>
                                                                        <i class="fas fa-map-marker-alt text-muted me-1"></i>
                                                                        <?php
                                                                        $lat = $denuncia['latitud'] ?? null;
                                                                        $lng = $denuncia['longitud'] ?? null;
                                                                        if ($lat && $lng) {
                                                                            echo $lat . ', ' . $lng;
                                                                        } else {
                                                                            echo 'Sin coordenadas';
                                                                        }
                                                                        ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="card mb-3" style="border: 1px solid rgba(46, 139, 87, 0.2);">
                                                        <div class="card-header" style="background: linear-gradient(135deg, #F0F8F5, #E8F4F0); color: var(--text-dark); font-weight: 600;">
                                                            <i class="fas fa-user-circle me-2"></i>Datos del Denunciante
                                                        </div>
                                                        <div class="card-body">
                                                            <div class="mb-3">
                                                                <label class="fw-bold text-muted">Nombre:</label>
                                                                <div><i class="fas fa-user text-muted me-2"></i><?= htmlspecialchars($denuncia['nombre_denunciante'] ?? 'Anónimo') ?></div>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="fw-bold text-muted">Email:</label>
                                                                <div><i class="fas fa-envelope text-muted me-2"></i><?= htmlspecialchars($denuncia['email_denunciante'] ?? 'Sin email') ?></div>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="fw-bold text-muted">Contacto:</label>
                                                                <div><i class="fas fa-phone text-muted me-2"></i><?= htmlspecialchars($denuncia['contacto_denunciante'] ?? 'No proporcionado') ?></div>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="fw-bold text-muted">Archivos adjuntos:</label>
                                                                <div class="archivos-info">
                                                                    <span class="archivo-count">
                                                                        <i class="fas fa-camera me-1"></i><?= $denuncia['fotos_count'] ?? 0 ?> fotos
                                                                    </span>
                                                                    <span class="archivo-count">
                                                                        <i class="fas fa-comments me-1"></i><?= $denuncia['actualizaciones_count'] ?? 0 ?> actualizaciones
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <hr style="border-color: rgba(46, 139, 87, 0.2);">
                                            
                                            <!-- Sección de gestión según estado -->
                                            <?php if (!$es_archivada): ?>
                                            <!-- Actualizar Estado o Archivar -->
                                            <div class="card" style="border: 1px solid rgba(46, 139, 87, 0.2);">
                                                <div class="card-header" style="background: linear-gradient(135deg, var(--primary-green), var(--secondary-green)); color: white;">
                                                    <h6 class="mb-0"><i class="fas fa-edit me-2"></i>Gestionar Denuncia</h6>
                                                </div>
                                                <div class="card-body">
                                                    <!-- Tabs para cambiar entre actualizar y archivar -->
                                                    <ul class="nav nav-tabs mb-3" id="gestionTabs<?= $denuncia['id'] ?>" role="tablist">
                                                        <li class="nav-item" role="presentation">
                                                            <button class="nav-link active" id="actualizar-tab-<?= $denuncia['id'] ?>" data-bs-toggle="tab" data-bs-target="#actualizar-<?= $denuncia['id'] ?>" type="button" role="tab">
                                                                <i class="fas fa-edit me-1"></i>Actualizar Estado
                                                            </button>
                                                        </li>
                                                        <li class="nav-item" role="presentation">
                                                            <button class="nav-link" id="archivar-tab-<?= $denuncia['id'] ?>" data-bs-toggle="tab" data-bs-target="#archivar-<?= $denuncia['id'] ?>" type="button" role="tab">
                                                                <i class="fas fa-archive me-1"></i>Archivar
                                                            </button>
                                                        </li>
                                                    </ul>

                                                    <div class="tab-content" id="gestionTabContent<?= $denuncia['id'] ?>">
                                                        <!-- Tab Actualizar Estado -->
                                                        <div class="tab-pane fade show active" id="actualizar-<?= $denuncia['id'] ?>" role="tabpanel">
                                                            <form method="POST" onsubmit="return confirmarActualizacion(this);">
                                                                <input type="hidden" name="denuncia_id" value="<?= $denuncia['id'] ?>">
                                                                <div class="row">
                                                                    <div class="col-md-4 mb-3">
                                                                        <label class="form-label fw-bold">Nuevo Estado:</label>
                                                                        <select name="nuevo_estado" class="form-select" required style="border: 2px solid rgba(46, 139, 87, 0.2);">
                                                                            <option value="">Seleccionar estado</option>
                                                                            <option value="pendiente" <?= $estado_normalizado == 'pendiente' ? 'selected' : '' ?>>⏳ Pendiente</option>
                                                                            <option value="en_proceso" <?= $estado_normalizado == 'en_proceso' ? 'selected' : '' ?>>🔄 En Proceso</option>
                                                                            <option value="resuelto" <?= $estado_normalizado == 'resuelto' ? 'selected' : '' ?>>✅ Resuelto</option>
                                                                        </select>
                                                                    </div>
                                                                    <div class="col-md-8 mb-3">
                                                                        <label class="form-label fw-bold">Descripción de la actualización:</label>
                                                                        <input type="text" name="descripcion_actualizacion" class="form-control" placeholder="Describe los cambios realizados..." required maxlength="255" style="border: 2px solid rgba(46, 139, 87, 0.2);">
                                                                    </div>
                                                                </div>
                                                                <div class="d-flex gap-2 justify-content-end">
                                                                    <button type="submit" name="actualizar_estado" class="btn btn-primary-custom btn-custom">
                                                                        <i class="fas fa-save me-2"></i>Actualizar Estado
                                                                    </button>
                                                                </div>
                                                            </form>
                                                        </div>

                                                        <!-- Tab Archivar -->
                                                        <div class="tab-pane fade" id="archivar-<?= $denuncia['id'] ?>" role="tabpanel">
                                                            <div class="text-center mb-3">
                                                                <i class="fas fa-archive fa-3x text-muted mb-2"></i>
                                                                <h6>Archivar esta denuncia</h6>
                                                                <p class="text-muted small">La denuncia será movida al archivo pero no se eliminará. Podrá recuperarla más tarde si es necesario.</p>
                                                            </div>
                                                            <div class="alert alert-info">
                                                                <i class="fas fa-info-circle me-2"></i>
                                                                <strong>El archivado es reversible:</strong> Las denuncias archivadas se conservan para estadísticas y pueden reactivarse cuando sea necesario.
                                                            </div>
                                                            <form method="POST" onsubmit="return confirmarArchivado(this);">
                                                                <input type="hidden" name="denuncia_id" value="<?= $denuncia['id'] ?>">
                                                                <div class="mb-3">
                                                                    <label class="form-label fw-bold">Motivo del archivado:</label>
                                                                    <select name="motivo_archivo" class="form-select" required style="border: 2px solid rgba(108, 117, 125, 0.3);">
                                                                        <option value="">Seleccionar motivo</option>
                                                                        <option value="Caso cerrado - Resuelto satisfactoriamente">Caso cerrado - Resuelto satisfactoriamente</option>
                                                                        <option value="Duplicado - Ya existe otra denuncia similar">Duplicado - Ya existe otra denuncia similar</option>
                                                                        <option value="Información insuficiente - No se puede procesar">Información insuficiente - No se puede procesar</option>
                                                                        <option value="Fuera de jurisdicción - No corresponde a esta entidad">Fuera de jurisdicción - No corresponde a esta entidad</option>
                                                                        <option value="Solicitud del denunciante - Retiro voluntario">Solicitud del denunciante - Retiro voluntario</option>
                                                                        <option value="Archivo temporal - Pendiente de más información">Archivo temporal - Pendiente de más información</option>
                                                                        <option value="Otro motivo - Ver descripción">Otro motivo - Ver descripción</option>
                                                                    </select>
                                                                </div>
                                                                <div class="d-flex gap-2 justify-content-end">
                                                                    <button type="submit" name="archivar_denuncia" class="btn btn-archive btn-custom">
                                                                        <i class="fas fa-archive me-2"></i>Archivar Denuncia
                                                                    </button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php else: ?>
                                            <!-- Denuncia Archivada - Opción para desarchivar -->
                                            <div class="card" style="border: 1px solid rgba(108, 117, 125, 0.3);">
                                                <div class="card-header" style="background: linear-gradient(135deg, var(--archive-gray), #868e96); color: white;">
                                                    <h6 class="mb-0"><i class="fas fa-archive me-2"></i>Denuncia Archivada</h6>
                                                </div>
                                                <div class="card-body">
                                                    <div class="text-center mb-3">
                                                        <i class="fas fa-archive fa-3x text-muted mb-3"></i>
                                                        <h6 class="text-muted">Esta denuncia ha sido archivada</h6>
                                                        <p class="text-muted">Las denuncias archivadas se conservan para fines estadísticos e históricos pero no están activas en el sistema.</p>
                                                    </div>
                                                    
                                                    <?php if ($denuncia['estado_anterior'] && $denuncia['estado_anterior'] !== '' && $denuncia['estado_anterior'] !== 'null'): ?>
                                                    <div class="alert alert-success mb-3">
                                                        <i class="fas fa-history me-2"></i>
                                                        <strong>Estado anterior:</strong> <?= ucfirst(str_replace('_', ' ', htmlspecialchars($denuncia['estado_anterior']))) ?>
                                                        <br><small>Al desarchivar, se restaurará automáticamente a este estado</small>
                                                    </div>
                                                    <?php endif; ?>

                                                    <form method="POST" onsubmit="return confirmarDesarchivado(this);">
                                                        <input type="hidden" name="denuncia_id" value="<?= $denuncia['id'] ?>">
                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold">Nuevo estado para la denuncia:</label>
                                                            <select name="nuevo_estado_desarchivar" class="form-select" required style="border: 2px solid rgba(70, 130, 180, 0.3);">
                                                                <option value="">Seleccionar estado</option>
                                                                <option value="pendiente" <?= ($denuncia['estado_anterior'] ?? '') == 'pendiente' ? 'selected' : '' ?>>⏳ Pendiente - Requiere atención</option>
                                                                <option value="en_proceso" <?= ($denuncia['estado_anterior'] ?? '') == 'en_proceso' ? 'selected' : '' ?>>🔄 En Proceso - Siendo revisada</option>
                                                                <option value="resuelto" <?= ($denuncia['estado_anterior'] ?? '') == 'resuelto' ? 'selected' : '' ?>>✅ Resuelto - Ya fue solucionada</option>
                                                            </select>
                                                            <small class="text-muted">
                                                                <i class="fas fa-info-circle me-1"></i>
                                                                <?php if ($denuncia['estado_anterior']): ?>
                                                                Estado anterior preseleccionado
                                                                <?php else: ?>
                                                                Seleccione el estado al que desea restaurar
                                                                <?php endif; ?>
                                                            </small>
                                                        </div>
                                                        <div class="alert alert-warning">
                                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                                            <strong>Atención:</strong> Al desarchivar, la denuncia aparecerá nuevamente en las listas activas y estadísticas.
                                                        </div>
                                                        <div class="d-flex gap-2 justify-content-end">
                                                            <button type="submit" name="desarchivar_denuncia" class="btn btn-unarchive btn-custom">
                                                                <i class="fas fa-undo me-2"></i>Desarchivar Denuncia
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Paginación -->
        <?php if ($total_paginas > 1): ?>
        <nav aria-label="Paginación de denuncias">
            <ul class="pagination">
                <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?filtro=<?= $filtro_actual ?>&page=<?= $page - 1 ?>">
                        <i class="fas fa-chevron-left"></i> Anterior
                    </a>
                </li>
                <?php endif; ?>

                <?php
                // Mostrar páginas
                $inicio = max(1, $page - 2);
                $fin = min($total_paginas, $page + 2);
                
                if ($inicio > 1) {
                    echo '<li class="page-item"><a class="page-link" href="?filtro=' . $filtro_actual . '&page=1">1</a></li>';
                    if ($inicio > 2) {
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                }

                for ($i = $inicio; $i <= $fin; $i++) {
                    $active = ($i == $page) ? 'active' : '';
                    echo '<li class="page-item ' . $active . '"><a class="page-link" href="?filtro=' . $filtro_actual . '&page=' . $i . '">' . $i . '</a></li>';
                }

                if ($fin < $total_paginas) {
                    if ($fin < $total_paginas - 1) {
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                    echo '<li class="page-item"><a class="page-link" href="?filtro=' . $filtro_actual . '&page=' . $total_paginas . '">' . $total_paginas . '</a></li>';
                }
                ?>

                <?php if ($page < $total_paginas): ?>
                <li class="page-item">
                    <a class="page-link" href="?filtro=<?= $filtro_actual ?>&page=<?= $page + 1 ?>">
                        Siguiente <i class="fas fa-chevron-right"></i>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>

        <!-- Footer informativo -->
        <div class="mt-5 text-center">
            <div class="card main-card">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h5 class="mb-2" style="color: var(--primary-green);">
                                <i class="fas fa-leaf me-2"></i>CodeChoco - Sistema Avanzado de Gestión
                            </h5>
                            <p class="text-muted mb-0">
                                Sistema mejorado con archivado inteligente y exportación a Excel. 
                                Protegiendo el patrimonio natural de Quibdó, Chocó con tecnología responsable.
                            </p>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="d-flex justify-content-end gap-3">
                                <div class="text-center">
                                    <i class="fas fa-database fa-2x" style="color: var(--primary-green);"></i>
                                    <div class="small text-muted">Datos Seguros</div>
                                </div>
                                <div class="text-center">
                                    <i class="fas fa-file-excel fa-2x" style="color: var(--river-blue);"></i>
                                    <div class="small text-muted">Exportación</div>
                                </div>
                                <div class="text-center">
                                    <i class="fas fa-archive fa-2x" style="color: var(--accent-gold);"></i>
                                    <div class="small text-muted">Archivado</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Función para confirmar actualización de estado
        function confirmarActualizacion(form) {
            const select = form.querySelector('select[name="nuevo_estado"]');
            const descripcion = form.querySelector('input[name="descripcion_actualizacion"]');
            
            if (!select.value) {
                alert('Por favor selecciona un estado');
                return false;
            }
            
            if (!descripcion.value.trim()) {
                alert('Por favor describe la actualización');
                return false;
            }
            
            const estadoTexto = select.options[select.selectedIndex].text;
            const confirmMsg = `¿Confirma cambiar el estado a "${estadoTexto}"?\n\nDescripción: ${descripcion.value}`;
            
            if (confirm(confirmMsg)) {
                const submitBtn = form.querySelector('button[type="submit"]');
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Actualizando...';
                submitBtn.disabled = true;
                return true;
            }
            return false;
        }

        // Función para confirmar archivado
        function confirmarArchivado(form) {
            const motivo = form.querySelector('select[name="motivo_archivo"]');
            
            if (!motivo.value) {
                alert('Por favor selecciona un motivo para archivar');
                return false;
            }
            
            const motivoTexto = motivo.options[motivo.selectedIndex].text;
            const confirmMsg = `¿Confirma archivar esta denuncia?\n\nMotivo: ${motivoTexto}\n\nNota: El archivado es reversible.`;
            
            if (confirm(confirmMsg)) {
                const submitBtn = form.querySelector('button[type="submit"]');
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Archivando...';
                submitBtn.disabled = true;
                return true;
            }
            return false;
        }

        // Función para confirmar desarchivado
        function confirmarDesarchivado(form) {
            const estado = form.querySelector('select[name="nuevo_estado_desarchivar"]');
            
            if (!estado.value) {
                alert('Por favor selecciona un estado para la denuncia');
                return false;
            }
            
            const estadoTexto = estado.options[estado.selectedIndex].text;
            const confirmMsg = `¿Confirma desarchivar esta denuncia?\n\nSe restaurará al estado: ${estadoTexto}`;
            
            if (confirm(confirmMsg)) {
                const submitBtn = form.querySelector('button[type="submit"]');
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Desarchivando...';
                submitBtn.disabled = true;
                return true;
            }
            return false;
        }

        // Auto-cerrar alertas después de 5 segundos
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(alert => {
                setTimeout(() => {
                    const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                    bsAlert.close();
                }, 5000);
            });
        });

        // Efectos hover para las tarjetas de estadísticas
        document.querySelectorAll('.stats-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-8px) scale(1.02)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });

        // Animación para los filtros activos
        document.querySelectorAll('.filter-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                // Agregar efecto de carga
                if (!this.classList.contains('active')) {
                    this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Cargando...';
                }
            });
        });

        // Actualizar contadores en tiempo real (opcional)
        function actualizarContadores() {
            // Esta función podría implementarse con AJAX para actualizar
            // los contadores sin recargar la página
        }

        // Tooltips mejorados
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>
</body>
</html>