<?php
// File: src/admin/manage_users.php
require_once '../auth.php';
require_once '../db_connect.php';

start_secure_session();
require_super_admin('../index.php'); // Redirect to login if not logged in or not admin

// Fetch all users for the list
$users = [];
$sql_users = "SELECT id, username, role FROM users ORDER BY username ASC";
$result_users = $conn->query($sql_users);
if ($result_users && $result_users->num_rows > 0) {
    while ($row = $result_users->fetch_assoc()) {
        $users[] = $row;
    }
} elseif ($result_users === false) {
    // Handle query error for fetching users
    error_log("SQL Error (fetch_users): " . $conn->error);
    // You might want to set an error message to display on the page
    $_SESSION['page_error_message'] = "Erro ao carregar lista de usuários.";
}

// Include header after all logic, before any HTML output
// The header might try to use $_SESSION['username'] etc.
require_once '../templates/header.php';
?>

<div class="container admin-container">
    <h2>Gerenciamento de Usuários</h2>

    <?php
    // Display messages from redirects
    if (isset($_GET['success'])) {
        $success_messages = [
            'useradded' => 'Novo usuário adicionado com sucesso!',
            'passwordreset' => 'Senha do usuário redefinida com sucesso!',
            'userupdated' => 'Usuário atualizado com sucesso!',
            'userdeleted' => 'Usuário excluído com sucesso!'
        ];
        if (isset($success_messages[$_GET['success']])) {
            echo '<p class="success-message">' . htmlspecialchars($success_messages[$_GET['success']]) . '</p>';
        }
    }
    if (isset($_GET['error'])) {
        $error_messages = [
            // Register user errors
            'emptyfields_adduser' => 'Nome de usuário e senha são obrigatórios para adicionar usuário.',
            'usernametoolong_adduser' => 'Nome de usuário muito longo (máx 255).',
            'usernametooshort_adduser' => 'Nome de usuário muito curto (mín 3).',
            'passwordshort' => 'A senha deve ter pelo menos 6 caracteres.',
            'invalidrole' => 'Função inválida selecionada.',
            'usernameexists' => 'Este nome de usuário já existe.',
            'sqlerror_adduser' => 'Erro de banco de dados ao adicionar usuário.',
            'adduserfailed' => 'Falha ao adicionar novo usuário.',
            // Reset password errors
            'invaliduserid_reset' => 'ID de usuário inválido para redefinição de senha.',
            'emptypassword_reset' => 'Nova senha não pode ser vazia.',
            'passwordshort_reset' => 'A nova senha deve ter pelo menos 6 caracteres.',
            'sqlerror_reset' => 'Erro de banco de dados ao redefinir senha.',
            'usernotfound_reset' => 'Usuário não encontrado para redefinição de senha.',
            'resetfailed' => 'Falha ao redefinir a senha.',
            // Edit user errors
            'invaliduserid_edit' => 'ID de usuário inválido para edição.',
            'emptyfields_edituser' => 'Nome de usuário e função são obrigatórios para editar.',
            'usernametoolong_edituser' => 'Nome de usuário muito longo (máx 255) ao editar.',
            'usernametooshort_edituser' => 'Nome de usuário muito curto (mín 3) ao editar.',
            'sqlerror_fetchuser' => 'Erro ao buscar dados do usuário para edição.',
            'usernotfound_edit' => 'Usuário não encontrado para edição.',
            'cannotrenameadmin' => 'O nome de usuário do administrador principal não pode ser alterado.',
            'cannotchangeroleadmin' => 'A função do administrador principal não pode ser alterada.',
            'usernameexists_edit' => 'Erro ao atualizar: Nome de usuário já em uso por outro usuário.',
            'sqlerror_updateuser' => 'Erro de banco de dados ao atualizar usuário.',
            'updateuserfailed' => 'Falha ao atualizar o usuário.',
            // Delete user errors
            'invaliduserid_delete' => 'ID de usuário inválido para exclusão.',
            'cannotdeleteself' => 'Você não pode excluir sua própria conta.',
            'sqlerror_getuser_delete' => 'Erro ao buscar dados do usuário para exclusão.',
            'usernotfound_delete' => 'Usuário não encontrado para exclusão.',
            'cannotdeleteadmin' => 'Não é permitido excluir o administrador principal.',
            'userhasitems_delete' => 'Este usuário não pode ser excluído pois possui itens registrados no sistema.',
            'sqlerror_deleteuser' => 'Erro de banco de dados ao excluir usuário.',
            'deleteuserfailed' => 'Falha ao excluir o usuário.',
            'deletefailed_notfound' => 'Falha ao excluir: usuário não encontrado.',
            // General
            'unknownaction' => 'Ação desconhecida.',
            'usernotfound' => 'Usuário não encontrado.' // Generic
        ];
        $error_key = $_GET['error'];
        $display_message = $error_messages[$error_key] ?? 'Ocorreu um erro desconhecido.';
        echo '<p class="error-message">' . htmlspecialchars($display_message) . '</p>';
    }
    if (isset($_SESSION['page_error_message'])) {
        echo '<p class="error-message">' . htmlspecialchars($_SESSION['page_error_message']) . '</p>';
        unset($_SESSION['page_error_message']); // Clear message after displaying
    }
    ?>

    <h3>Registrar Novo Usuário</h3>
    <form action="user_management_handler.php" method="POST" class="form-admin">
        <input type="hidden" name="action" value="register_user">
        <div>
            <label for="username_reg">Usuário:</label>
            <input type="text" id="username_reg" name="username" required>
        </div>
        <div>
            <label for="password_reg">Senha (mín. 6 caracteres):</label>
            <input type="password" id="password_reg" name="password" required minlength="6">
        </div>
        <div>
            <label for="role_reg">Função:</label>
            <select id="role_reg" name="role" required>
                <option value="common">Comum</option>
                <option value="admin">Admin</option>
                <option value="admin-aprovador">Admin Aprovador</option>
                <option value="superAdmin">SuperAdmin</option>
            </select>
        </div>
        <div>
            <button type="submit">Registrar Usuário</button>
        </div>
    </form>

    <hr> <!-- Inline style removed, covered by .admin-container hr -->

    <h3>Lista de Usuários</h3>
    <?php if (!empty($users)): ?>
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Usuário</th>
                <th>Função</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
            <tr>
                <td><?php echo htmlspecialchars($user['id']); ?></td>
                <td><?php echo htmlspecialchars($user['username']); ?></td>
                <td><?php echo htmlspecialchars($user['role']); ?></td>
                <td class="actions-cell">
                    <a href="edit_user_page.php?id=<?php echo htmlspecialchars($user['id']); ?>" class="button-edit">Editar</a>

                    <?php // Password reset form (kept separate for clarity or if styling differs significantly) ?>
                    <?php if (!($user['username'] === 'admin' && $_SESSION['username'] !== 'admin')): // Prevent non-primary admin from changing primary admin's password here ?>
                         <?php // Also, generally the primary admin might not want to reset their own password via this form. ?>
                         <?php if ($user['username'] !== 'admin' || ($_SESSION['username'] === 'admin' && $user['id'] == $_SESSION['user_id'] ) ): // Admin can reset own, or any non-admin user ?>
                            <form action="user_management_handler.php" method="POST" class="form-inline" onsubmit="return confirmPasswordReset(this);">
                                <input type="hidden" name="action" value="reset_password">
                                <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['id']); ?>">
                                <label for="new_password_<?php echo $user['id']; ?>" class="sr-only">Nova Senha:</label>
                                <input type="password" id="new_password_<?php echo $user['id']; ?>" name="new_password" placeholder="Nova Senha (mín. 6)" required minlength="6">
                                <button type="submit" class="button-secondary">Resetar Senha</button>
                            </form>
                        <?php else: ?>
                             <small><em>(Reset Indisponível)</em></small>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($user['username'] !== 'admin' && $user['id'] != $_SESSION['user_id']): ?>
                        <form action="user_management_handler.php" method="POST" onsubmit="return confirm('Tem certeza que deseja excluir este usuário? Esta ação não pode ser desfeita.');">
                            <input type="hidden" name="action" value="delete_user">
                            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['id']); ?>">
                            <button type="submit" class="button-delete">Excluir</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p>Nenhum usuário encontrado.</p>
    <?php endif; ?>
</div>

<script>
function confirmPasswordReset(form) {
    const newPassword = form.new_password.value;
    if (newPassword.length < 6) {
        alert("A nova senha deve ter pelo menos 6 caracteres.");
        return false;
    }
    return confirm("Tem certeza que deseja redefinir a senha para este usuário?");
}
</script>

<?php
// Close connection if it was opened and not closed by other includes
if (isset($conn) && $conn instanceof mysqli) {
    // $conn->close(); // Usually closed at the end of script execution or by footer if applicable
}
require_once '../templates/footer.php';
?>
