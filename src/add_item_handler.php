<?php
mb_internal_encoding('UTF-8'); // Set internal encoding for mb_string functions
require_once 'auth.php'; // Includes session_start() via start_secure_session()
require_once 'db_connect.php';

start_secure_session(); // Ensure session is started
require_login(); // Redirects if not logged in

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name'] ?? '');
    $category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    $location_id = filter_input(INPUT_POST, 'location_id', FILTER_VALIDATE_INT);
    $found_date = trim($_POST['found_date'] ?? '');
    $description = trim($_POST['description'] ?? '');
    if (empty($description)) {
        $description = null; // Store as NULL in DB if empty after trim
    }
    $user_id = $_SESSION['user_id'];

    // Basic validation
    if (empty($name) || $category_id === false || $location_id === false || empty($found_date)) {
        header('Location: register_item_page.php?error=emptyitemfields');
        exit();
    }

    // Additional validation: name length
    if (mb_strlen($name) > 255) {
        header('Location: register_item_page.php?error=nametoolong');
        exit();
    }
    if (mb_strlen($name) < 3) {
        header('Location: register_item_page.php?error=nametooshort');
        exit();
    }
    // Description length (TEXT can be large, but a practical limit is good)
    if ($description !== null && mb_strlen($description) > 1000) { // Example limit for description
        header('Location: register_item_page.php?error=descriptiontoolong');
        exit();
    }


    // Validate date format (basic validation, YYYY-MM-DD)
    // The browser input type="date" should enforce this, but server-side validation is good.
    if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $found_date)) {
        header('Location: register_item_page.php?error=invaliddateformat');
        exit();
    }

    // Barcode Generation
    // 1. Fetch category code
    $category_code = '';
    $sql_cat_code = "SELECT code FROM categories WHERE id = ?";
    $stmt_cat_code = $conn->prepare($sql_cat_code);
    if ($stmt_cat_code) {
        $stmt_cat_code->bind_param("i", $category_id);
        $stmt_cat_code->execute();
        $result_cat_code = $stmt_cat_code->get_result();
        if ($cat_row = $result_cat_code->fetch_assoc()) {
            $category_code = $cat_row['code'];
        }
        $stmt_cat_code->close();
    }
    if (empty($category_code)) {
        header('Location: register_item_page.php?error=invalidcategory');
        exit();
    }

    // 2. Determine next sequential number for that category
    $next_seq_num = 1;
    // MAX(CAST(SUBSTRING_INDEX(barcode, '-', -1) AS UNSIGNED))
    // This part can be tricky if barcode format changes or if it's NULL.
    // Let's assume barcodes are like 'CODE-000001' and never NULL for this logic to work.
    // A safer way might be to have a separate sequence column if items can exist without barcodes initially.
    // For now, we'll filter by category_id and parse existing barcodes.
    $sql_seq = "SELECT MAX(CAST(SUBSTRING_INDEX(barcode, CONCAT(?, '-'), -1) AS UNSIGNED)) as max_seq FROM items WHERE category_id = ? AND barcode LIKE CONCAT(?, '-%')";
    $stmt_seq = $conn->prepare($sql_seq);
    if ($stmt_seq) {
        $stmt_seq->bind_param("sis", $category_code, $category_id, $category_code);
        $stmt_seq->execute();
        $result_seq = $stmt_seq->get_result();
        if ($seq_row = $result_seq->fetch_assoc()) {
            if ($seq_row['max_seq'] !== null) {
                $next_seq_num = intval($seq_row['max_seq']) + 1;
            }
        }
        $stmt_seq->close();
    } else {
        error_log("SQL Prepare Error (seq_num): " . $conn->error);
        header('Location: register_item_page.php?error=seqgen_error');
        exit();
    }

    $barcode_sequential_part = str_pad($next_seq_num, 6, '0', STR_PAD_LEFT);
    $barcode = $category_code . '-' . $barcode_sequential_part;

    // Ensure generated barcode is unique (rare collision, but good practice)
    $sql_barcode_check = "SELECT id FROM items WHERE barcode = ?";
    $stmt_barcode_check = $conn->prepare($sql_barcode_check);
    $stmt_barcode_check->bind_param("s", $barcode);
    $stmt_barcode_check->execute();
    if ($stmt_barcode_check->get_result()->num_rows > 0) {
        // This is a rare case, might indicate an issue with sequence generation or manual entry
        // For now, error out. A more robust system might retry with a new number.
        $stmt_barcode_check->close();
        header('Location: register_item_page.php?error=barcodegeneration_collision');
        exit();
    }
    $stmt_barcode_check->close();


    $sql_insert = "INSERT INTO items (name, category_id, location_id, found_date, description, user_id, barcode) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt_insert = $conn->prepare($sql_insert);

    if ($stmt_insert === false) {
        error_log("SQL Prepare Error (insert_item): " . $conn->error);
        header('Location: register_item_page.php?error=sqlerror_item_insert');
        exit();
    }

    $stmt_insert->bind_param("siissis", $name, $category_id, $location_id, $found_date, $description, $user_id, $barcode);

    if ($stmt_insert->execute()) {
        header('Location: home.php?message=itemadded&barcode=' . urlencode($barcode));
        exit();
    } else {
        error_log("SQL Execute Error (insert_item): " . $stmt_insert->error);
        header('Location: register_item_page.php?error=itemaddfailed');
        exit();
    }
    $stmt_insert->close();

} else {
    // Not a POST request
    header('Location: register_item_page.php');
    exit();
}

$conn->close();
?>

