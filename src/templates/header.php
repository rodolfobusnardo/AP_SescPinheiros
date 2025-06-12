<?php
if (!function_exists('is_admin')) {
    require_once __DIR__ . '/../auth.php';
}
start_secure_session();
require_once __DIR__ . '/../db_connect.php';

$unidade_nome_settings = 'Sistema de Achados e Perdidos'; // Default title
if (isset($conn) && $conn instanceof mysqli) {
    $stmt_settings = $conn->prepare("SELECT unidade_nome FROM settings WHERE config_id = 1");
    if ($stmt_settings) {
        $stmt_settings->execute();
        $result_settings = $stmt_settings->get_result();
        if ($result_settings->num_rows > 0) {
            $row_settings = $result_settings->fetch_assoc();
            if (!empty($row_settings['unidade_nome'])) {
                $unidade_nome_settings = htmlspecialchars($row_settings['unidade_nome']);
            }
        }
        $stmt_settings->close();
    } else {
        error_log("Failed to prepare statement for settings in header: " . $conn->error);
    }
} else {
    error_log("Database connection not available in header.php or not a mysqli object.");
}

$is_index_page = basename($_SERVER['PHP_SELF']) == 'index.php';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $unidade_nome_settings; ?></title>
    <link rel="stylesheet" href="/style.css">
</head>
<body>
    <header>
        <h1><?php echo $unidade_nome_settings; ?></h1>
        <nav>
            <ul>
                <?php if (!$is_index_page): ?>
                    <li><a href="/home.php">Home</a></li>
                    <li><a href="/register_item_page.php">Cadastrar Item</a></li>
                <?php endif; ?>
                <?php if (is_super_admin()): ?>
                    <li><a href="/admin/manage_users.php">Usuários</a></li>
                    <li><a href="/admin/settings_page.php">Configurações</a></li>
                <?php endif; ?>
                <?php if (is_admin()): // This will still show for admin and superAdmin for other links ?>
                    <li><a href="/admin/manage_categories.php">Categorias</a></li>
                    <li><a href="/admin/manage_locations.php">Locais</a></li>
                    <li><a href="/manage_devolutions.php">Termos de Devolução</a></li>
                    <li><a href="/manage_donations.php">Termos de Doação</a></li>
                <?php endif; ?>
                <?php if (is_logged_in()): ?>
                    <li><a href="/logout_handler.php">Sair (<?php echo htmlspecialchars($_SESSION['username'] ?? 'Usuário'); ?>)</a></li>
                <?php else: ?>
                    <?php if (!$is_index_page): ?>
                        <li><a href="/index.php">Login</a></li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
        </nav>
    </header>
    <main>
