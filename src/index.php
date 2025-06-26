<?php
// Inclui e inicia a sessão segura ANTES de qualquer outra lógica ou saída.
require_once __DIR__ . '/auth.php';
start_secure_session();

// If user is already logged in, redirect to home.php
if (isset($_SESSION['user_id'])) {
    header('Location: home.php');
    exit();
}

require_once 'templates/header.php';
?>

<div class="container login-container">
    <h2>Login</h2>

    <?php
    if (isset($_GET['error'])) {
        $errorMessage = '';
        switch ($_GET['error']) {
            case 'emptyfields':
                $errorMessage = 'Por favor, preencha usuário e senha.';
                break;
            case 'sqlerror':
                $errorMessage = 'Erro no sistema. Tente novamente mais tarde.';
                break;
            case 'wrongpassword':
                $errorMessage = 'Senha incorreta.';
                break;
            case 'nouser':
                $errorMessage = 'Usuário não encontrado.';
                break;
            case 'pleaselogin':
                $errorMessage = 'Por favor, faça login para continuar.';
                break;
            default:
                $errorMessage = 'Ocorreu um erro desconhecido.';
        }
        echo '<p class="error-message">' . htmlspecialchars($errorMessage) . '</p>';
    }
    if (isset($_GET['message']) && $_GET['message'] == 'loggedout') {
        echo '<p class="success-message">Você foi desconectado com sucesso.</p>';
    }
    ?>

    <form action="login_handler.php" method="POST">
        <div>
            <label for="username">Usuário:</label>
            <input type="text" id="username" name="username" required>
        </div>
        <div>
            <label for="password">Senha:</label>
            <input type="password" id="password" name="password" required>
        </div>
        <div>
            <button type="submit">Entrar</button>
        </div>
    </form>
</div>

<?php require_once 'templates/footer.php'; ?>
