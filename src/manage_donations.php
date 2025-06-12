<?php
require_once 'auth.php'; // For session and access control
require_once 'db_connect.php'; // Though not strictly needed yet, good practice for admin pages

start_secure_session();
require_admin('home.php'); // Redirect non-admins to home.php

require_once 'templates/header.php';
?>

<div class="container admin-container">
    <h2>Termos de Doação</h2>

    <p class="info-message">Esta funcionalidade referente aos termos de doação está em desenvolvimento.</p>

    <p style="margin-top: 20px;">
        <a href="/home.php" class="button-secondary">Voltar para Home</a>
    </p>
</div>

<?php
require_once 'templates/footer.php';
?>
