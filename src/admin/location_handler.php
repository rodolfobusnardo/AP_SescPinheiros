<?php
header('Content-Type: application/json'); // Default content type for get_location responses
require_once '../auth.php';
require_once '../db_connect.php';

start_secure_session();
require_admin('../index.php', 'Acesso negado. Funcionalidade administrativa.');

$response = ['status' => 'error', 'message' => 'Ação inválida.'];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action == 'add_location') {
        $name = trim($_POST['name'] ?? '');

        if (empty($name)) {
            header('Location: manage_locations.php?error=emptyfields_addloc');
            exit();
        }
        if (strlen($name) > 255) {
            header('Location: manage_locations.php?error=locname_too_long');
            exit();
        }
        if (strlen($name) < 3) {
            header('Location: manage_locations.php?error=locname_too_short');
            exit();
        }
        // Check for uniqueness of name
        $sql_check = "SELECT id FROM locations WHERE name = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("s", $name);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        if ($result_check->num_rows > 0) {
            header('Location: manage_locations.php?error=loc_exists');
            $stmt_check->close();
            exit();
        }
        $stmt_check->close();

        $sql = "INSERT INTO locations (name) VALUES (?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $name);
        if ($stmt->execute()) {
            header('Location: manage_locations.php?success=loc_added');
        } else {
            error_log("SQL Error (add_location): " . $stmt->error);
            header('Location: manage_locations.php?error=add_loc_failed');
        }
        $stmt->close();
        exit();

    } elseif ($action == 'edit_location') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $name = trim($_POST['name'] ?? '');

        if (!$id || empty($name)) {
            header('Location: manage_locations.php?error=emptyfields_editloc&id=' . $id);
            exit();
        }
        if (strlen($name) > 255) {
            header('Location: manage_locations.php?error=locname_too_long_edit&id=' . $id);
            exit();
        }
        if (strlen($name) < 3) {
            header('Location: manage_locations.php?error=locname_too_short_edit&id=' . $id);
            exit();
        }

        // Check for uniqueness of name (excluding current location ID)
        $sql_check = "SELECT id FROM locations WHERE name = ? AND id != ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("si", $name, $id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        if ($result_check->num_rows > 0) {
            header('Location: manage_locations.php?error=loc_exists_edit&id=' . $id);
            $stmt_check->close();
            exit();
        }
        $stmt_check->close();

        $sql = "UPDATE locations SET name = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $name, $id);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                header('Location: manage_locations.php?success=loc_updated');
            } else {
                header('Location: manage_locations.php?message=loc_nochange&id=' . $id);
            }
        } else {
            error_log("SQL Error (edit_location): " . $stmt->error);
            header('Location: manage_locations.php?error=edit_loc_failed&id=' . $id);
        }
        $stmt->close();
        exit();
    }
    // If other POST actions were to return JSON, they would be here.
    // For now, add/edit redirect.

} elseif ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['action']) && $_GET['action'] == 'get_location') {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        $response['message'] = 'ID da localização inválido.';
        echo json_encode($response);
        exit();
    }

    $sql = "SELECT id, name FROM locations WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($location = $result->fetch_assoc()) {
        $response['status'] = 'success';
        $response['data'] = $location;
        $response['message'] = 'Localização encontrada.';
    } else {
        $response['message'] = 'Localização não encontrada.';
    }
    $stmt->close();
    echo json_encode($response);
    exit();

} else {
    // Invalid request method or no action specified for POST, or unknown GET action
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        header('Location: manage_locations.php?error=invalid_action');
        exit();
    }
    $response['message'] = 'Ação GET inválida ou não especificada.';
    echo json_encode($response);
    exit();
}

$conn->close();
?>
