<?php
mb_internal_encoding('UTF-8'); // Set internal encoding for mb_string functions
require_once '../auth.php';
require_once '../db_connect.php';

start_secure_session();
require_admin('../index.php'); // Ensure only admins can edit items

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $item_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $name = trim($_POST['name'] ?? '');
    $category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    $location_id = filter_input(INPUT_POST, 'location_id', FILTER_VALIDATE_INT);
    $found_date = trim($_POST['found_date'] ?? '');
    $description = trim($_POST['description'] ?? '');
    // Barcode is not directly editable here. User who registered it also not directly editable here.

    $status_input = null;
    if (is_admin()) { // is_admin() checks for admin or superAdmin
        $status_input = trim($_POST['status'] ?? '');
    }

    // Validation
    if (!$item_id) {
        header('Location: /home.php?error=invaliditemid_edit'); // Assuming an admin item management page
        exit();
    }

    $current_db_status = null;
    $sql_get_status = "SELECT status FROM items WHERE id = ?";
    $stmt_get_status = $conn->prepare($sql_get_status);
    if ($stmt_get_status) {
        $stmt_get_status->bind_param("i", $item_id);
        $stmt_get_status->execute();
        $result_status = $stmt_get_status->get_result();
        if ($row_status = $result_status->fetch_assoc()) {
            $current_db_status = $row_status['status'];
        }
        $stmt_get_status->close();
    } else {
        error_log("SQL Prepare Error (get_item_status for edit restriction): " . $conn->error);
        // Redirect with a generic error, or let other validations catch it if item not found
        header('Location: /home.php?error=dberror_fetch_status_edit');
        exit();
    }

    if ($current_db_status === null) { // Item not found
        header('Location: /home.php?error=itemnotfound_edit_restriction');
        exit();
    }

    if (in_array($current_db_status, ['Devolvido', 'Doado'])) {
        // Item is currently Devolvido or Doado.
        // Only allow update if an admin is changing the status to something else (e.g., back to Pendente).
        // $status_input was already retrieved and validated for allowed values if is_admin().

        $is_status_being_changed_by_admin = false;
        if (is_admin() && isset($_POST['status'])) { // Check if status was submitted
             $new_status_from_form = trim($_POST['status']); // Already retrieved as $status_input
             if ($new_status_from_form !== $current_db_status) {
                 $is_status_being_changed_by_admin = true;
             }
        }

        if (!$is_status_being_changed_by_admin) {
            // If status is not being changed by an admin (or if user is not admin),
            // and the item is Devolvido/Doado, then block all edits.
            $_SESSION['admin_page_error_message'] = "Não é possível editar itens com status '" . htmlspecialchars($current_db_status) . "', a menos que um administrador altere o status.";
             // Redirect back to edit page to show the message, or home
            header('Location: edit_item_page.php?id=' . $item_id . '&error=edit_not_allowed_status');
            exit();
        }
        // If an admin IS changing the status, the update will proceed.
        // The existing logic correctly builds the SQL to update status and any other fields.
    }

    if (empty($name) || $category_id === false || $location_id === false || empty($found_date)) {
        header('Location: edit_item_page.php?id=' . $item_id . '&error=emptyfields_edititem'); // Assuming an edit item page
        exit();
    }

    if (mb_strlen($name) > 255) {
        header('Location: edit_item_page.php?id=' . $item_id . '&error=nametoolong_edititem');
        exit();
    }
    if (mb_strlen($name) < 3) {
        header('Location: edit_item_page.php?id=' . $item_id . '&error=nametooshort_edititem');
        exit();
    }
    if ($description !== null && mb_strlen($description) > 1000) { // Example limit for description
        header('Location: edit_item_page.php?id=' . $item_id . '&error=descriptiontoolong_edititem');
        exit();
    }

    if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $found_date)) {
        header('Location: edit_item_page.php?id=' . $item_id . '&error=invaliddateformat_edititem');
        exit();
    }

    if (is_admin() && !empty($status_input)) {
        if (!in_array($status_input, ['Pendente', 'Devolvido', 'Doado'])) {
            header('Location: edit_item_page.php?id=' . $item_id . '&error=invalidstatus_edititem');
            exit();
        }
    }

    // Note: If category_id changes, barcode does not change as per requirements.
    // If a new barcode logic were needed upon category change, it would be complex and involve
    // checking if the new barcode would collide, potentially re-generating sequence.
    // For this implementation, barcode remains fixed.

    $sql_base_update = "UPDATE items SET name = ?, category_id = ?, location_id = ?, found_date = ?, description = ?";
    $types = "siiss"; // Types for the base fields
    $params = [$name, $category_id, $location_id, $found_date, $description];

    if (is_admin() && !empty($status_input)) {
        // Only add status to update if user is admin and status is valid (already checked)
        $sql_base_update .= ", status = ?";
        $types .= "s";
        $params[] = $status_input;
    }

    $sql_base_update .= " WHERE id = ?";
    $types .= "i";
    $params[] = $item_id;

    $stmt = $conn->prepare($sql_base_update);

    if ($stmt === false) {
        error_log("SQL Prepare Error (edit_item): " . $conn->error);
        header('Location: /home.php?error=sqlerror_edititem');
        exit();
    }

    // Use call_user_func_array for dynamic bind_param
    // Ensure params are passed by reference for bind_param if not already
    $bind_params_ref = [];
    foreach ($params as $key => $value) {
        $bind_params_ref[$key] = &$params[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], array_merge([$types], $bind_params_ref));

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            header('Location: /home.php?success=itemupdated&id=' . $item_id);
        } else {
            // No rows affected could mean data was same or ID not found
            header('Location: /home.php?message=itemnochange_edit&id=' . $item_id);
        }
    } else {
        error_log("SQL Execute Error (edit_item): " . $stmt->error);
        header('Location: edit_item_page.php?id=' . $item_id . '&error=itemupdatefailed');
    }
    $stmt->close();

} else {
    // Not a POST request
    // Redirect to an overview page, or an error page if accessed directly without POST
    header('Location: /home.php?error=invalidrequest_edititem'); // Or simply home.php for non-admins
    exit();
}

$conn->close();
?>
