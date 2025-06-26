<?php
// This script is intended to be included by other PHP files (e.g., home.php, admin/manage_items.php)
// It populates the $items array. Session start and login checks should be handled by the calling script.

require_once 'db_connect.php'; // Ensures $conn is available

// Initialize filters
$filter_category_id = filter_input(INPUT_GET, 'filter_category_id', FILTER_VALIDATE_INT);
$filter_location_id = filter_input(INPUT_GET, 'filter_location_id', FILTER_VALIDATE_INT);
$filter_found_date_start = trim($_GET['filter_found_date_start'] ?? '');
$filter_found_date_end = trim($_GET['filter_found_date_end'] ?? '');
$filter_days_waiting_min = filter_input(INPUT_GET, 'filter_days_waiting_min', FILTER_VALIDATE_INT);
$filter_days_waiting_max = filter_input(INPUT_GET, 'filter_days_waiting_max', FILTER_VALIDATE_INT);
$filter_status = trim($_GET['filter_status'] ?? '');
$filter_item_name = trim($_GET['filter_item_name'] ?? '');
// Add more filters as needed: $filter_barcode, $filter_text_search (name, description)

$items = [];
$sql_conditions = [];
$sql_params_types = "";
$sql_params_values = [];

$sql_base = "SELECT
                i.id, i.name, i.status, i.found_date, i.description, i.registered_at, i.barcode,
                c.name AS category_name, c.code AS category_code,
                l.name AS location_name,
                u.username AS registered_by_username,
                u.full_name AS registered_by_full_name, /* Adicionado full_name do usuário */
                dd.id AS devolution_document_id
             FROM items i
             JOIN categories c ON i.category_id = c.id
             JOIN locations l ON i.location_id = l.id
             LEFT JOIN users u ON i.user_id = u.id
             LEFT JOIN devolution_documents dd ON i.id = dd.item_id";

if ($filter_category_id) {
    $sql_conditions[] = "i.category_id = ?";
    $sql_params_types .= "i";
    $sql_params_values[] = $filter_category_id;
}
if ($filter_location_id) {
    $sql_conditions[] = "i.location_id = ?";
    $sql_params_types .= "i";
    $sql_params_values[] = $filter_location_id;
}
if (!empty($filter_found_date_start) && preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $filter_found_date_start)) {
    $sql_conditions[] = "i.found_date >= ?";
    $sql_params_types .= "s";
    $sql_params_values[] = $filter_found_date_start;
}
if (!empty($filter_found_date_end) && preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $filter_found_date_end)) {
    $sql_conditions[] = "i.found_date <= ?";
    $sql_params_types .= "s";
    $sql_params_values[] = $filter_found_date_end;
}

// Filtering by days_waiting (calculated from found_date)
if ($filter_days_waiting_min !== null && $filter_days_waiting_min >= 0) {
    // Items found on or before (CURDATE() - min_days)
    $sql_conditions[] = "i.found_date <= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
    $sql_params_types .= "i";
    $sql_params_values[] = $filter_days_waiting_min;
}
if ($filter_days_waiting_max !== null && $filter_days_waiting_max >= 0) {
    // Items found on or after (CURDATE() - max_days)
    // Ensure min is less than max if both are used; this logic handles individual bounds.
    $sql_conditions[] = "i.found_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
    $sql_params_types .= "i";
    $sql_params_values[] = $filter_days_waiting_max;
}

// Filter by status (only 'Pendente' or 'Devolvido' are currently functional for filtering)
if (!empty($filter_status) && in_array($filter_status, ['Pendente', 'Devolvido'])) {
    $sql_conditions[] = "i.status = ?";
    $sql_params_types .= "s";
    $sql_params_values[] = $filter_status;
}

// Filter by item name (AND logic for multiple words)
if (!empty($filter_item_name)) {
    $name_words = explode(' ', $filter_item_name);
    $name_words = array_filter($name_words); // Remove empty strings resulting from multiple spaces

    if (!empty($name_words)) {
        foreach ($name_words as $word) {
            $sql_conditions[] = "i.name LIKE ?";
            $sql_params_types .= "s";
            $sql_params_values[] = "%" . $word . "%";
        }
    }
}
// Example: min_days=7, max_days=30 means found between 30 days ago and 7 days ago.
// found_date <= (today - 7 days) AND found_date >= (today - 30 days)


$sql_query = $sql_base;
if (!empty($sql_conditions)) {
    $sql_query .= " WHERE " . implode(" AND ", $sql_conditions);
}
$sql_query .= " ORDER BY i.registered_at DESC";


$stmt = $conn->prepare($sql_query);

if ($stmt === false) {
    error_log("SQL Prepare Error (get_items_handler): " . $conn->error . " Query: " . $sql_query);
    // Optionally, set an error message in session to be displayed by the calling page
    // if (session_status() == PHP_SESSION_ACTIVE) { $_SESSION['error_message'] = "Could not retrieve items due to a query preparation error."; }
} else {
    if (!empty($sql_params_types) && !empty($sql_params_values)) {
        $stmt->bind_param($sql_params_types, ...$sql_params_values);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result === false) {
        error_log("SQL Execute Error (get_items_handler): " . $stmt->error);
        // if (session_status() == PHP_SESSION_ACTIVE) { $_SESSION['error_message'] = "Could not retrieve items due to a query execution error."; }
    } else {
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                // Calculate "Tempo aguardando devolução" (days_waiting)
                $found_date_obj = date_create($row['found_date']);
                $current_date_obj = date_create(date('Y-m-d'));
                $interval = date_diff($found_date_obj, $current_date_obj);
                $row['days_waiting'] = $interval->days;
                $items[] = $row;
            }
        }
    }
    $stmt->close();
}

// $conn->close(); // Connection usually closed by the script that includes this one.

// If this script is accessed via AJAX, output JSON and exit.
// (e.g., from home.php dynamic filtering)
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json'); // Moved here
    echo json_encode($items);
    exit();
}

// Otherwise, the $items array is populated and available to the including PHP script.
// Ensure no whitespace after the closing tag
?>