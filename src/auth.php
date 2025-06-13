<?php

function start_secure_session() {
    if (session_status() == PHP_SESSION_NONE) {
        // TODO: Consider adding more session security settings here in a real app,
        // e.g., session_regenerate_id(), cookie parameters (httponly, secure, samesite)
        // For now, basic start is fine as per previous setup.
        session_start();
    }
}

function is_logged_in() {
    start_secure_session(); // Ensure session is started
    return isset($_SESSION['user_id']);
}

function require_login($redirect_url = 'index.php') {
    if (!is_logged_in()) {
        // Store the page they were trying to access, to redirect back after login (optional)
        // $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
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

function require_admin($redirect_url = 'home.php', $error_message = 'Acesso negado. Permissões de administrador necessárias.') {
    if (!is_admin()) {
        // In a real app, you might want to log this attempt.
        // Redirect to a non-privileged page with an error message.
        // Ensure the redirect URL is appropriate (e.g., not an admin page itself).
        if ($redirect_url === 'index.php' || $redirect_url === 'home.php') {
             header("Location: " . $redirect_url . "?error=" . urlencode($error_message));
        } else {
            // Fallback if a specific non-admin page isn't obvious
            header("Location: home.php?error=" . urlencode($error_message));
        }
        exit();
    }
}

function require_super_admin($redirect_url = 'home.php', $error_message = 'Acesso negado. Permissões de super administrador necessárias.') {
    if (!is_super_admin()) {
        if ($redirect_url === 'index.php' || $redirect_url === 'home.php') {
             header("Location: " . $redirect_url . "?error=" . urlencode($error_message));
        } else {
            // Fallback if a specific non-admin page isn't obvious
            // Potentially redirect to an admin dashboard if one exists and they are an admin but not superadmin
            // For now, keeping it simple:
            header("Location: home.php?error=" . urlencode($error_message));
        }
        exit();
    }
}

?>
