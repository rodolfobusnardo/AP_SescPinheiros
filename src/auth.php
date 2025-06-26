<?php

function start_secure_session() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
}

function is_logged_in() {
    start_secure_session();
    return isset($_SESSION['user_id']);
}

function require_login($redirect_url = '/index.php') { // Padrão com /
    if (!is_logged_in()) {
        header("Location: " . $redirect_url . "?error=pleaselogin");
        exit();
    }
}

function is_admin() {
    if (!is_logged_in()) {
        return false;
    }
    return isset($_SESSION['user_role']) &&
           ($_SESSION['user_role'] === 'admin' ||
            $_SESSION['user_role'] === 'admin-aprovador' ||
            $_SESSION['user_role'] === 'superAdmin');
}

function is_super_admin() {
    if (!is_logged_in()) {
        return false;
    }
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'superAdmin';
}

function require_admin($redirect_url = '/home.php', $error_message = 'Acesso negado. Permissões de administrador necessárias.') {
    if (!is_admin()) {
        // CORREÇÃO: Garante que a URL sempre use o caminho absoluto.
        if ($redirect_url === '/index.php' || $redirect_url === '/home.php') {
             header("Location: " . $redirect_url . "?error=" . urlencode($error_message));
        } else {
            // Fallback para a home page com caminho absoluto.
            header("Location: /home.php?error=" . urlencode($error_message));
        }
        exit();
    }
}

function require_super_admin($redirect_url = '/home.php', $error_message = 'Acesso negado. Permissões de super administrador necessárias.') {
    if (!is_super_admin()) {
        // CORREÇÃO: Garante que a URL sempre use o caminho absoluto.
        if ($redirect_url === '/index.php' || $redirect_url === '/home.php') {
             header("Location: " . $redirect_url . "?error=" . urlencode($error_message));
        } else {
            // Fallback para a home page com caminho absoluto.
            header("Location: /home.php?error=" . urlencode($error_message));
        }
        exit();
    }
}

?>