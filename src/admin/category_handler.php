<?php
header('Content-Type: application/json'); // Default content type for get_category responses
require_once '../auth.php';
require_once '../db_connect.php';

start_secure_session();
// For POST actions, require admin. For GET (like get_category), could be more lenient if needed,
// but generally, category details are admin-viewable/editable.
// Let's enforce admin for all actions in this handler for simplicity.
require_admin('../index.php', 'Acesso negado. Funcionalidade administrativa.');

$response = ['status' => 'error', 'message' => 'Ação inválida.'];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action == 'add_category') {
        $name = trim($_POST['name'] ?? '');
        $code = trim(strtoupper($_POST['code'] ?? '')); // Convert code to uppercase

        if (empty($name) || empty($code)) {
            header('Location: manage_categories.php?error=emptyfields_addcat');
            exit();
        }
        if (strlen($name) > 255) {
            header('Location: manage_categories.php?error=catname_too_long');
            exit();
        }
        if (strlen($name) < 3) {
            header('Location: manage_categories.php?error=catname_too_short');
            exit();
        }
        if (strlen($code) > 10) {
             header('Location: manage_categories.php?error=code_too_long');
             exit();
        }
        if (!preg_match('/^[A-Z0-9_]+$/', $code)) { // Code is uppercased, so check against A-Z
            header('Location: manage_categories.php?error=code_invalid_format');
            exit();
        }
        // Check for uniqueness of name and code
        $sql_check = "SELECT id FROM categories WHERE name = ? OR code = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("ss", $name, $code);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        if ($result_check->num_rows > 0) {
            header('Location: manage_categories.php?error=cat_exists');
            $stmt_check->close();
            exit();
        }
        $stmt_check->close();

        $sql = "INSERT INTO categories (name, code) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $name, $code);
        if ($stmt->execute()) {
            header('Location: manage_categories.php?success=cat_added');
        } else {
            error_log("SQL Error (add_category): " . $stmt->error);
            header('Location: manage_categories.php?error=add_cat_failed');
        }
        $stmt->close();
        exit();

    } elseif ($action == 'edit_category') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $name = trim($_POST['name'] ?? '');
        $code = trim(strtoupper($_POST['code'] ?? ''));

        if (!$id || empty($name) || empty($code)) {
            header('Location: manage_categories.php?error=emptyfields_editcat&id=' . $id);
            exit();
        }
        if (strlen($name) > 255) {
            header('Location: manage_categories.php?error=catname_too_long_edit&id=' . $id);
            exit();
        }
        if (strlen($name) < 3) {
            header('Location: manage_categories.php?error=catname_too_short_edit&id=' . $id);
            exit();
        }
        if (strlen($code) > 10) {
             header('Location: manage_categories.php?error=code_too_long_edit&id=' . $id);
             exit();
        }
        if (!preg_match('/^[A-Z0-9_]+$/', $code)) { // Code is uppercased
            header('Location: manage_categories.php?error=code_invalid_format_edit&id=' . $id);
            exit();
        }

        // Check for uniqueness of name and code (excluding current category ID)
        $sql_check = "SELECT id FROM categories WHERE (name = ? OR code = ?) AND id != ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("ssi", $name, $code, $id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        if ($result_check->num_rows > 0) {
            header('Location: manage_categories.php?error=cat_exists_edit&id=' . $id);
            $stmt_check->close();
            exit();
        }
        $stmt_check->close();

        $sql = "UPDATE categories SET name = ?, code = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $name, $code, $id);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                header('Location: manage_categories.php?success=cat_updated');
            } else {
                 // No rows affected could mean data was same or ID not found
                header('Location: manage_categories.php?message=cat_nochange&id=' . $id);
            }
        } else {
            error_log("SQL Error (edit_category): " . $stmt->error);
            header('Location: manage_categories.php?error=edit_cat_failed&id=' . $id);
        }
        $stmt->close();
        exit();
    }
    // For POST actions that are not add/edit, but expect JSON response
    // This part is reached if an action is POSTed but not add/edit and expects JSON
    // However, the current structure redirects for add/edit.
    // If 'get_category' were a POST action (not typical for GET), it would be here.
    // For now, we assume 'get_category' will be a GET request.

} elseif ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['action']) && $_GET['action'] == 'get_category') {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        $response['message'] = 'ID da categoria inválido.';
        echo json_encode($response);
        exit();
    }

    $sql = "SELECT id, name, code FROM categories WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($category = $result->fetch_assoc()) {
        $response['status'] = 'success';
        $response['data'] = $category;
        $response['message'] = 'Categoria encontrada.';
    } else {
        $response['message'] = 'Categoria não encontrada.';
    }
    $stmt->close();
    echo json_encode($response);
    exit();

} else {
    // Invalid request method or no action specified for POST, or unknown GET action
    // For POST, redirect to manage_categories. For GET, output JSON error.
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        header('Location: manage_categories.php?error=invalid_action');
        exit();
    }
    // For GET requests that are not 'get_category'
    $response['message'] = 'Ação GET inválida ou não especificada.';
    echo json_encode($response);
    exit();
}

$conn->close();
?>
