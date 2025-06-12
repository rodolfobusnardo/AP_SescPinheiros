<?php
if (!function_exists('is_admin')) {
    require_once __DIR__ . '/../auth.php';
}
start_secure_session();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Achados e Perdidos</title>
    <link rel="stylesheet" href="/style.css">
</head>
<body>
    <header>
        <h1>Sistema de Achados e Perdidos</h1>
        <nav>
            <ul>
                <li><a href="/home.php">Home</a></li>
                <li><a href="/register_item_page.php">Cadastrar Item</a></li>
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
                    <li><a href="/index.php">Login</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>
    <main>
