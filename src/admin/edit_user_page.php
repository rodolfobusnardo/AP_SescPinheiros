<?php
// File: src/admin/edit_user_page.php
require_once '../auth.php';
require_once '../db_connect.php';

start_secure_session();
require_super_admin('../index.php'); // Redirect if not admin

$user_id_to_edit = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$user_data = null;
$page_error = $_SESSION['page_error_message'] ?? null; // Check for session errors from previous attempts
unset($_SESSION['page_error_message']); // Clear it after fetching

if (!$user_id_to_edit) {
    $_SESSION['page_error_message'] = "ID de usuário inválido ou não fornecido.";
    header('Location: manage_users.php');
    exit();
}

// Fetch current user data
$sql_user = "SELECT id, username, role FROM users WHERE id = ?";
$stmt_user = $conn->prepare($sql_user);

if ($stmt_user) {
    $stmt_user->bind_param("i", $user_id_to_edit);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    if ($result_user->num_rows === 1) {
        $user_data = $result_user->fetch_assoc();
    } else {
        $_SESSION['page_error_message'] = "Usuário não encontrado para edição (ID: " . htmlspecialchars($user_id_to_edit) . ").";
        header('Location: manage_users.php');
        exit();
    }
    $stmt_user->close();
} else {
    error_log("SQL Prepare Error (fetch_user_for_edit): " . $conn->error);
    $_SESSION['page_error_message'] = "Erro de banco de dados ao buscar usuário.";
    header('Location: manage_users.php');
    exit();
}

// This must be included AFTER any potential redirects and session messages are set
require_once '../templates/header.php';
?>

<div class="container admin-container">
    <h2>Editar Usuário: <?php echo htmlspecialchars($user_data['username']); ?></h2>

    <?php
    // Display messages from redirects (e.g., after a failed update attempt)
    if (isset($_GET['success']) && $_GET['success'] == 'userupdated') { // Though success usually redirects to list
        echo '<p class="success-message">Usuário atualizado com sucesso!</p>';
    }
    if (isset($_GET['error'])) {
        $error_key = $_GET['error'];
         // Use the same error message array from manage_users.php or define specific ones
        $error_messages = [
            'emptyfields_edituser' => 'Nome de usuário e função são obrigatórios.',
            'usernametoolong_edituser' => 'Nome de usuário muito longo (máx 255).',
            'usernametooshort_edituser' => 'Nome de usuário muito curto (mín 3).',
            'sqlerror_fetchuser' => 'Erro ao buscar dados do usuário para edição.', // Should not happen here if already fetched
            'usernotfound_edit' => 'Usuário não encontrado para edição.', // Should not happen here
            'cannotrenameadmin' => 'O nome de usuário do administrador principal não pode ser alterado.',
            'cannotchangeroleadmin' => 'A função do administrador principal não pode ser alterada.',
            'usernameexists_edit' => 'Erro ao atualizar: Nome de usuário já em uso por outro usuário.',
            'sqlerror_updateuser' => 'Erro de banco de dados ao atualizar usuário.',
            'updateuserfailed' => 'Falha ao atualizar o usuário.',
        ];
        $display_message = $error_messages[$error_key] ?? 'Ocorreu um erro desconhecido ao tentar atualizar.';
        echo '<p class="error-message">' . htmlspecialchars($display_message) . '</p>';
    }
    if ($page_error) { // Display errors set in PHP before redirect or header include
         echo '<p class="error-message">' . htmlspecialchars($page_error) . '</p>';
    }
    ?>

    <?php if ($user_data): // Should always be true if we haven't exited ?>
    <form action="user_management_handler.php" method="POST" class="form-admin">
        <input type="hidden" name="action" value="edit_user">
        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_data['id']); ?>">

        <div>
            <label for="username">Nome de Usuário:</label>
            <input type="text" id="username" name="username"
                   value="<?php echo htmlspecialchars($user_data['username']); ?>"
                   <?php if ($user_data['username'] === 'admin') echo 'readonly'; ?>
                   required minlength="3" maxlength="255">
            <?php if ($user_data['username'] === 'admin'): ?>
                <small class="form-text text-muted">O nome de usuário 'admin' não pode ser alterado.</small>
            <?php endif; ?>
        </div>

        <div>
            <label for="role">Função:</label>
            <?php $is_main_admin_user = ($user_data['username'] === 'admin'); ?>
            <select id="role" name="role" <?php if ($is_main_admin_user) echo 'disabled'; ?> required>
                <option value="common" <?php if ($user_data['role'] === 'common') echo 'selected'; ?>>Comum</option>
                <option value="admin" <?php if ($user_data['role'] === 'admin') echo 'selected'; ?>>Admin</option>
                <option value="superAdmin" <?php if ($user_data['role'] === 'superAdmin') echo 'selected'; ?>>SuperAdmin</option>
            </select>
            <?php if ($is_main_admin_user): ?>
                <input type="hidden" name="role" value="<?php echo htmlspecialchars($user_data['role']); ?>">
                <small class="form-text text-muted">A função do usuário 'admin' não pode ser alterada.</small>
            <?php endif; ?>
        </div>

        <div style="margin-top: 10px;">
            <p><small>A alteração de senha é realizada através da funcionalidade "Resetar Senha" na página de listagem de usuários.</small></p>
        </div>

        <div class="form-action-buttons-group">
            <a href="manage_users.php" class="button-secondary">Cancelar</a>
            <button type="submit" class="button-primary">Salvar Alterações</button>
        </div>
    </form>
    <?php else: ?>
        <?php // This part should ideally not be reached if redirects for invalid user work correctly ?>
        <p class="error-message">Não foi possível carregar os dados do usuário para edição.</p>
        <p><a href="manage_users.php">Voltar para a lista de usuários</a></p>
    <?php endif; ?>
</div>

<?php
if (isset($conn) && $conn instanceof mysqli) {
    // $conn->close(); // Usually closed by PHP or footer
}
require_once '../templates/footer.php';
?>
