<?php
require_once '../auth.php'; // For session and access control
require_once '../db_connect.php'; // For database connection

start_secure_session();
require_admin('../home.php'); // Redirect non-admins to home.php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['term_id'])) {
        $term_id = filter_input(INPUT_POST, 'term_id', FILTER_VALIDATE_INT);
        $admin_user_id = $_SESSION['user_id'] ?? null;
        $admin_identifier = $admin_user_id ? "[Admin ID: {$admin_user_id}]" : "[Admin ID: UNKNOWN]";

        if ($term_id === false || $term_id === null) {
            $_SESSION['error_message'] = "ID do Termo de Doação inválido.";
            header("Location: ../manage_donations.php");
            exit();
        }

        if ($admin_user_id === null) {
            $_SESSION['error_message'] = "Não foi possível identificar o administrador. Sessão inválida.";
            error_log("Critical: Admin user_id not found in session during term approval for term_id: " . $term_id . ". Action attempted by unknown admin.");
            header("Location: ../manage_donations.php");
            exit();
        } else {
            // Fetch admin details for logging
            $stmt_admin_details = $conn->prepare("SELECT username, full_name FROM users WHERE id = ?");
            if ($stmt_admin_details) {
                $stmt_admin_details->bind_param("i", $admin_user_id);
                $stmt_admin_details->execute();
                $result_admin_details = $stmt_admin_details->get_result();
                if ($admin_details = $result_admin_details->fetch_assoc()) {
                    $admin_display_log = !empty(trim($admin_details['full_name'] ?? '')) ? $admin_details['full_name'] : $admin_details['username'];
                    $admin_identifier = "[Admin: {$admin_display_log} (ID: {$admin_user_id})]";
                }
                $stmt_admin_details->close();
            }
        }

        $conn->begin_transaction();

        try {
            // 1. Update the donation_terms status to 'Doado' (or another approved status)
            // Also set approved_at and approved_by_user_id
            $stmt_term = $conn->prepare(
                "UPDATE donation_terms SET status = 'Doado', approved_at = NOW(), approved_by_user_id = ? WHERE term_id = ? AND status = 'Aguardando Aprovação'"
            );
            if (!$stmt_term) throw new Exception("Erro ao preparar atualização do termo: " . $conn->error . " por " . $admin_identifier);
            
            $stmt_term->bind_param("ii", $admin_user_id, $term_id);
            $stmt_term->execute();

            if ($stmt_term->affected_rows === 0) {
                throw new Exception("Nenhum termo de doação foi atualizado. Verifique se o ID do termo (" . $term_id . ") é válido e se o status era 'Aguardando Aprovação'. Ação por " . $admin_identifier);
            }
            $stmt_term->close();

            // 2. Get all item_ids associated with this term_id from donation_term_items
            $stmt_get_items = $conn->prepare("SELECT item_id FROM donation_term_items WHERE term_id = ?");
            if (!$stmt_get_items) throw new Exception("Erro ao preparar busca de itens do termo: " . $conn->error . " por " . $admin_identifier);

            $stmt_get_items->bind_param("i", $term_id);
            $stmt_get_items->execute();
            $result_items = $stmt_get_items->get_result();
            
            $item_ids_to_update = [];
            while ($row = $result_items->fetch_assoc()) {
                $item_ids_to_update[] = $row['item_id'];
            }
            $stmt_get_items->close();

            // 3. Update the status of related items to 'Doado' in the 'items' table
            if (!empty($item_ids_to_update)) {
                $placeholders = implode(',', array_fill(0, count($item_ids_to_update), '?'));
                $sql_update_items = "UPDATE items SET status = 'Doado' WHERE id IN ($placeholders) AND status = 'Aguardando Aprovação'";
                
                $stmt_update_items = $conn->prepare($sql_update_items);
                if (!$stmt_update_items) throw new Exception("Erro ao preparar atualização dos itens: " . $conn->error . " por " . $admin_identifier);

                $types = str_repeat('i', count($item_ids_to_update));
                $stmt_update_items->bind_param($types, ...$item_ids_to_update);
                $stmt_update_items->execute();
                
                $stmt_update_items->close();
            }

            $conn->commit();

            $_SESSION['success_message'] = "Termo de Doação ID: " . htmlspecialchars($term_id) . " aprovado com sucesso. Itens relacionados marcados como 'Doado'.";
            header("Location: ../manage_donations.php");
            exit();

        } catch (mysqli_sql_exception $db_exception) {
            $conn->rollback();
            error_log("Database error during term approval for term_id " . $term_id . " by " . $admin_identifier . ": " . $db_exception->getMessage());
            $_SESSION['error_message'] = "Erro no banco de dados ao aprovar termo de doação. Consulte o log para detalhes.";
            header("Location: ../manage_donations.php");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            error_log("General error during term approval for term_id " . $term_id . " by " . $admin_identifier . ": " . $e->getMessage());
            $_SESSION['error_message'] = "Erro ao aprovar termo de doação: " . $e->getMessage();
            header("Location: ../manage_donations.php");
            exit();
        } finally {
            // Ensure statements are closed if they were prepared
            if (isset($stmt_term) && $stmt_term instanceof mysqli_stmt) $stmt_term->close();
            if (isset($stmt_get_items) && $stmt_get_items instanceof mysqli_stmt) $stmt_get_items->close();
            if (isset($stmt_update_items) && $stmt_update_items instanceof mysqli_stmt) $stmt_update_items->close();
            if (isset($conn) && $conn instanceof mysqli) $conn->close();
        }
    } else {
        $_SESSION['error_message'] = "ID do Termo de Doação não fornecido.";
        header("Location: ../manage_donations.php");
        exit();
    }
} else {
    $_SESSION['error_message'] = "Método de requisição inválido.";
    header("Location: ../manage_donations.php");
    exit();
}
?>
