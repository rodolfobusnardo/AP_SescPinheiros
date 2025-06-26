<?php
require_once '../auth.php';
require_once '../db_connect.php';

start_secure_session();
require_super_admin('../index.php?error=unauthorized_admin_area');

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];

    switch ($action) {
        case 'register_user':
            // ... (código de registrar usuário como está) ...
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = trim($_POST['role'] ?? '');
            // full_name é opcional, então pode ser uma string vazia, que será convertida para NULL se a coluna do DB permitir.
            $full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : null;

            // Campos obrigatórios são username, password, role
            if (empty($username) || empty($password) || empty($role)) {
                $_SESSION['temp_form_data'] = $_POST;
                header('Location: manage_users.php?error=emptyfields_adduser'); // Usar chave de erro existente
                exit();
            }

            if (strlen($username) < 3) {
                $_SESSION['temp_form_data'] = $_POST;
                header('Location: manage_users.php?error=usernametooshort_adduser');
                exit();
            }
            if (strlen($username) > 255) {
                $_SESSION['temp_form_data'] = $_POST;
                header('Location: manage_users.php?error=usernametoolong_adduser');
                exit();
            }
            // Validar full_name somente se fornecido e não for apenas espaços em branco
            if ($full_name !== null && strlen($full_name) > 255) {
                $_SESSION['temp_form_data'] = $_POST;
                header('Location: manage_users.php?error=fullname_toolong'); // Nova chave de erro para manage_users.php
                exit();
            }
            if (strlen($password) < 6) {
                $_SESSION['temp_form_data'] = $_POST;
                header('Location: manage_users.php?error=passwordshort'); // Usar chave de erro existente
                exit();
            }
            if (!in_array($role, ['common', 'admin', 'admin-aprovador', 'superAdmin'])) {
                header('Location: manage_users.php?error=invalidrole'); // Usar chave de erro existente
                exit();
            }

            $sql_check_user = "SELECT id FROM users WHERE username = ?";
            $stmt_check = $conn->prepare($sql_check_user);
            $stmt_check->bind_param("s", $username);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            if ($result_check->num_rows > 0) {
                $stmt_check->close();
                header('Location: manage_users.php?error=usernameexists_adduser');
                exit();
            }
            $stmt_check->close();

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $sql_insert = "INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)"; // Adicionado full_name
            $stmt_insert = $conn->prepare($sql_insert);
            if ($stmt_insert === false) {
                error_log("SQL Prepare Error (register_user): " . $conn->error);
                $_SESSION['temp_form_data'] = $_POST;
                header('Location: manage_users.php?error=sqlerror_adduser');
                exit();
            }
            $stmt_insert->bind_param("ssss", $username, $hashed_password, $full_name, $role); // Adicionado 's' para full_name

            if ($stmt_insert->execute()) {
                unset($_SESSION['temp_form_data']); // Limpar dados do formulário em caso de sucesso
                header('Location: manage_users.php?success=useradded');
            } else {
                error_log("SQL Execute Error (register_user): " . $stmt_insert->error);
                header('Location: manage_users.php?error=adduserfailed');
            }
            $stmt_insert->close();
            exit();

        case 'edit_user':
            // ... (código de editar usuário como está) ...
            $user_id_to_edit = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
            $new_username = trim($_POST['username'] ?? '');
            $new_full_name = trim($_POST['full_name'] ?? ''); // Novo campo
            $new_role = trim($_POST['role'] ?? '');
            // $is_donation_approver = isset($_POST['is_donation_approver']) ? 1 : 0; // Removido

            if (!$user_id_to_edit) {
                header('Location: manage_users.php?error=invaliduserid_edit');
                exit();
            }

            // Adicionar new_full_name à verificação de campos vazios
            // Nota: O formulário de edição tem 'username' e 'full_name' como required. 'role' também.
            if (empty($new_username) || empty($new_full_name) || empty($new_role)) {
                $_SESSION['page_error_message'] = 'Todos os campos (Usuário, Nome Completo, Função) são obrigatórios.';
                header('Location: edit_user_page.php?id=' . $user_id_to_edit . '&error=emptyfields_edituser_fullname');
                exit();
            }
            if (strlen($new_username) < 3 || strlen($new_username) > 255) {
                $_SESSION['page_error_message'] = 'Nome de usuário (login) deve ter entre 3 e 255 caracteres.';
                header('Location: edit_user_page.php?id=' . $user_id_to_edit . '&error=username_length_edituser');
                exit();
            }
            if (strlen($new_full_name) > 255) {
                $_SESSION['page_error_message'] = 'Nome completo não pode exceder 255 caracteres.';
                header('Location: edit_user_page.php?id=' . $user_id_to_edit . '&error=fullname_too_long_edituser');
                exit();
            }
            if (!in_array($new_role, ['common', 'admin', 'admin-aprovador', 'superAdmin'])) { // Adicionado admin-aprovador
                $_SESSION['page_error_message'] = 'Função inválida selecionada.';
                header('Location: edit_user_page.php?id=' . $user_id_to_edit . '&error=invalidrole_edit');
                exit();
            }

            $sql_current_user = "SELECT username, role FROM users WHERE id = ?";
            $stmt_current = $conn->prepare($sql_current_user);
            if (!$stmt_current) {
                error_log("SQL Prepare Error (fetch_current_user_edit): " . $conn->error);
                header('Location: manage_users.php?error=sql_error_fetch_edit');
                exit();
            }
            $stmt_current->bind_param("i", $user_id_to_edit);
            $stmt_current->execute();
            $result_current = $stmt_current->get_result();
            if ($result_current->num_rows !== 1) {
                $stmt_current->close();
                header('Location: manage_users.php?error=usernotfound_edit');
                exit();
            }
            $current_user_data = $result_current->fetch_assoc();
            $stmt_current->close();

            if ($current_user_data['username'] === 'admin') { // This username is hardcoded as the primary admin
                if ($new_username !== 'admin') { // Name change prevention
                    header('Location: edit_user_page.php?id=' . $user_id_to_edit . '&error=cannotchangeadminuser');
                    exit();
                }
                // Role change prevention: Prevent demoting the primary 'admin' (now 'superAdmin')
                if ($new_role === 'common' || $new_role === 'admin') {
                    header('Location: edit_user_page.php?id=' . $user_id_to_edit . '&error=cannotchangeroleadmin');
                    exit();
                }
            }

            if ($new_username !== $current_user_data['username']) {
                $sql_check_new_user = "SELECT id FROM users WHERE username = ?";
                $stmt_check_new = $conn->prepare($sql_check_new_user);
                $stmt_check_new->bind_param("s", $new_username);
                $stmt_check_new->execute();
                if ($stmt_check_new->get_result()->num_rows > 0) {
                    $stmt_check_new->close();
                    header('Location: edit_user_page.php?id=' . $user_id_to_edit . '&error=usernameexists_edit');
                    exit();
                }
                $stmt_check_new->close();
            }

            // Atualizar SQL para incluir full_name e remover is_donation_approver
            $sql_update = "UPDATE users SET username = ?, full_name = ?, role = ? WHERE id = ?";
            $stmt_update = $conn->prepare($sql_update);
            if ($stmt_update === false) {
                error_log("SQL Prepare Error (edit_user): " . $conn->error);
                $_SESSION['page_error_message'] = 'Erro de banco de dados ao preparar atualização do usuário.';
                header('Location: edit_user_page.php?id=' . $user_id_to_edit . '&error=sqlerror_edituser');
                exit();
            }
            // Bind parameters: username, full_name, role, user_id (s, s, s, i)
            $stmt_update->bind_param("sssi", $new_username, $new_full_name, $new_role, $user_id_to_edit);

            if ($stmt_update->execute()) {
                // $_SESSION['admin_page_success_message'] = 'Usuário atualizado com sucesso!'; // manage_users.php já lida com ?success=
                header('Location: manage_users.php?success=userupdated&edited_id=' . $user_id_to_edit);
            } else {
                error_log("SQL Execute Error (edit_user): " . $stmt_update->error);
                header('Location: edit_user_page.php?id=' . $user_id_to_edit . '&error=updateuserfailed');
            }
            $stmt_update->close();
            exit();

        case 'delete_user':
            $user_id_to_delete = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);

            if (!$user_id_to_delete) {
                header('Location: manage_users.php?error=invaliduserid_delete');
                exit();
            }

            $sql_get_user_delete = "SELECT username FROM users WHERE id = ?";
            $stmt_get_delete = $conn->prepare($sql_get_user_delete);
            if(!$stmt_get_delete){
                error_log("SQL Prepare Error (get_user_for_delete): " . $conn->error);
                header('Location: manage_users.php?error=sql_error_get_user_delete');
                exit();
            }
            $stmt_get_delete->bind_param("i", $user_id_to_delete);
            $stmt_get_delete->execute();
            $result_get_delete = $stmt_get_delete->get_result();
            if ($result_get_delete->num_rows !== 1) {
                $stmt_get_delete->close();
                header('Location: manage_users.php?error=usernotfound_delete');
                exit();
            }
            $user_to_delete_data = $result_get_delete->fetch_assoc();
            $stmt_get_delete->close();

            if ($user_to_delete_data['username'] === 'admin') {
                header('Location: manage_users.php?error=cannotdeleteadmin');
                exit();
            }
            if ($user_id_to_delete == $_SESSION['user_id']) {
                header('Location: manage_users.php?error=cannotdeleteself');
                exit();
            }

            // ***** O BLOCO QUE VERIFICAVA ITENS FOI REMOVIDO DAQUI *****

            $sql_delete = "DELETE FROM users WHERE id = ?";
            $stmt_delete = $conn->prepare($sql_delete);
            if ($stmt_delete === false) {
                error_log("SQL Prepare Error (delete_user): " . $conn->error);
                header('Location: manage_users.php?error=sqlerror_deleteuser');
                exit();
            }
            $stmt_delete->bind_param("i", $user_id_to_delete);

            if ($stmt_delete->execute()) {
                if ($stmt_delete->affected_rows > 0) {
                     $_SESSION['admin_page_success_message'] = 'Usuário excluído com sucesso!';
                    header('Location: manage_users.php?success=userdeleted');
                } else {
                    header('Location: manage_users.php?error=usernotfoundordeletefailed');
                }
            } else {
                error_log("SQL Execute Error (delete_user): " . $stmt_delete->error . " - User ID: " . $user_id_to_delete);
                if ($conn->errno == 1451) {
                     $_SESSION['admin_page_error_message'] = 'Este usuário não pode ser excluído. Verifique as dependências no banco de dados (possivelmente itens ainda vinculados e a regra ON DELETE SET NULL não funcionou corretamente no schema).';
                    header('Location: manage_users.php?error=deletefailed_fkey');
                } else {
                    header('Location: manage_users.php?error=deleteuserfailed');
                }
            }
            $stmt_delete->close();
            exit();

        case 'reset_password':
            // ... (código de resetar senha como está) ...
            $user_id_reset = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
            $new_password = $_POST['new_password'] ?? '';

            if (!$user_id_reset) {
                header('Location: manage_users.php?error=invaliduserid_reset');
                exit();
            }
            // ... (resto das validações do reset_password) ...
            if (empty($new_password)) {
                header('Location: manage_users.php?error=emptypassword_reset&uid_reset=' . $user_id_reset);
                exit();
            }
            if (strlen($new_password) < 6) {
                header('Location: manage_users.php?error=passwordshort_reset&uid_reset=' . $user_id_reset);
                exit();
            }

            $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
            $sql_reset_pass = "UPDATE users SET password = ? WHERE id = ?";
            $stmt_reset_pass = $conn->prepare($sql_reset_pass);
            if ($stmt_reset_pass === false) {
                error_log("SQL Prepare Error (reset_password): " . $conn->error);
                header('Location: manage_users.php?error=sqlerror_reset&uid_reset=' . $user_id_reset);
                exit();
            }
            $stmt_reset_pass->bind_param("si", $hashed_new_password, $user_id_reset);

            if ($stmt_reset_pass->execute()) {
                if ($stmt_reset_pass->affected_rows > 0) {
                    $_SESSION['admin_page_success_message'] = 'Senha do usuário redefinida com sucesso!';
                    header('Location: manage_users.php?success=passwordreset');
                } else {
                     $_SESSION['admin_page_error_message'] = 'Nenhuma alteração na senha ou usuário não encontrado.';
                    header('Location: manage_users.php?error=resetnopchangeoruser&uid_reset=' . $user_id_reset);
                }
            } else {
                error_log("SQL Execute Error (reset_password): " . $stmt_reset_pass->error);
                header('Location: manage_users.php?error=resetfailed&uid_reset=' . $user_id_reset);
            }
            $stmt_reset_pass->close();
            exit();

        default:
            header('Location: manage_users.php?error=unknownaction');
            exit();
    }
} else {
    header('Location: manage_users.php');
    exit();
}

$conn->close();
?>