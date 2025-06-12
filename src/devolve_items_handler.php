<?php
require_once 'auth.php';
require_once 'db_connect.php';

start_secure_session();
require_login(); // User must be logged in to mark items as returned

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['item_ids']) && is_array($_POST['item_ids'])) {
        $item_ids_raw = $_POST['item_ids'];
        $item_ids_to_devolve = [];
        $processed_count = 0;
        $error_count = 0;

        // Validate and sanitize item IDs
        foreach ($item_ids_raw as $id_raw) {
            $id_sanitized = filter_var($id_raw, FILTER_VALIDATE_INT);
            if ($id_sanitized) {
                $item_ids_to_devolve[] = $id_sanitized;
            }
        }

        if (empty($item_ids_to_devolve)) {
            header('Location: home.php?error=noitemselected_devolve');
            exit();
        }

        // Prepare statement for updating item status
        // We only update items that are currently 'Pendente'
        $sql_update_status = "UPDATE items SET status = 'Devolvido' WHERE id = ? AND status = 'Pendente'";
        $stmt_update = $conn->prepare($sql_update_status);

        if ($stmt_update) {
            foreach ($item_ids_to_devolve as $item_id) {
                $stmt_update->bind_param("i", $item_id);
                if ($stmt_update->execute()) {
                    if ($stmt_update->affected_rows > 0) {
                        $processed_count++;
                    } else {
                        // Item not found, not 'Pendente', or other issue
                        error_log("Devolve Handler: No rows affected for item ID " . $item_id . ". May not be 'Pendente' or does not exist.");
                        $error_count++;
                    }
                } else {
                    error_log("Devolve Handler: SQL Execute Error for item ID " . $item_id . ": " . $stmt_update->error);
                    $error_count++;
                }
            }
            $stmt_update->close();

            if ($processed_count > 0 && $error_count == 0) {
                header('Location: home.php?success=itemsdevolvidos&count=' . $processed_count);
            } elseif ($processed_count > 0 && $error_count > 0) {
                header('Location: home.php?warning=itemsdevolvidosemerros&success_count=' . $processed_count . '&error_count=' . $error_count);
            } elseif ($processed_count == 0 && $error_count > 0) {
                header('Location: home.php?error=devolvefailed_all');
            } else { // No items processed, no errors (e.g. all items were not 'Pendente')
                header('Location: home.php?message=nodevolutionneeded');
            }
            exit();

        } else {
            error_log("Devolve Handler: SQL Prepare Error: " . $conn->error);
            header('Location: home.php?error=sqlprepare_devolve');
            exit();
        }

    } else {
        // No item_ids provided or not an array
        header('Location: home.php?error=noitemids_devolve');
        exit();
    }
} else {
    // Not a POST request, redirect to home
    header('Location: home.php');
    exit();
}

$conn->close();
?>
