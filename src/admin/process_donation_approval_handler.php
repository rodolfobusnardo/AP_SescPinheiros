<?php
require_once '../auth.php'; // Includes start_secure_session()
require_once '../db_connect.php';

// Access Control: Only 'admin-aprovador' or 'superAdmin'
if (!isset($_SESSION['user_role']) || ($_SESSION['user_role'] !== 'admin-aprovador' && $_SESSION['user_role'] !== 'superAdmin')) {
    $_SESSION['approval_action_message'] = 'Acesso negado. Requer função de Admin Aprovador ou SuperAdmin para esta ação.';
    $_SESSION['approval_action_success'] = false;
    header('Location: approve_donations_page.php');
    exit();
}

// Use $_REQUEST to allow action/term_id from GET (for approve) or POST (for decline with reason)
$action = strval($_REQUEST['action'] ?? '');
$term_id = filter_var($_REQUEST['term_id'] ?? 0, FILTER_VALIDATE_INT);

$reproval_reason = null;

if ($action === 'decline') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $_SESSION['approval_action_message'] = 'Ação de reprovação inválida (requer POST).';
        $_SESSION['approval_action_success'] = false;
        header('Location: approve_donations_page.php');
        exit();
    }
    $reproval_reason = trim($_POST['reproval_reason'] ?? '');
    if (empty($reproval_reason)) {
        $_SESSION['approval_action_message'] = 'O motivo da reprovação é obrigatório.';
        $_SESSION['approval_action_success'] = false;
        header('Location: approve_donations_page.php');
        exit();
    }
}


if (!$term_id || $term_id <= 0 || !in_array($action, ['approve', 'decline'])) {
    $_SESSION['approval_action_message'] = 'Ação inválida ou ID do termo não fornecido.';
    $_SESSION['approval_action_success'] = false;
    header('Location: approve_donations_page.php');
    exit();
}

$conn->begin_transaction();
$success = false;
$message = '';

try {
    // Fetch donation term details
    $sql_fetch_term = "SELECT status, signature_image_path FROM donation_terms WHERE term_id = ?";
    $stmt_fetch_term = $conn->prepare($sql_fetch_term);
    if (!$stmt_fetch_term) throw new Exception("Erro ao preparar busca do termo: " . $conn->error);
    $stmt_fetch_term->bind_param("i", $term_id);
    $stmt_fetch_term->execute();
    $result_term = $stmt_fetch_term->get_result();

    if ($result_term->num_rows === 0) {
        throw new Exception("Termo de doação ID " . htmlspecialchars($term_id) . " não encontrado.");
    }
    $term_data = $result_term->fetch_assoc();
    $stmt_fetch_term->close();

    if ($term_data['status'] !== 'Aguardando Aprovação') {
        throw new Exception("O Termo ID " . htmlspecialchars($term_id) . " não está aguardando aprovação (status atual: " . htmlspecialchars($term_data['status']) . "). Ação não permitida.");
    }

    // Fetch associated item IDs
    $item_ids = [];
    $sql_fetch_items = "SELECT item_id FROM donation_term_items WHERE term_id = ?";
    $stmt_fetch_items = $conn->prepare($sql_fetch_items);
    if (!$stmt_fetch_items) throw new Exception("Erro ao preparar busca dos itens do termo: " . $conn->error);
    $stmt_fetch_items->bind_param("i", $term_id);
    $stmt_fetch_items->execute();
    $result_items = $stmt_fetch_items->get_result();
    while ($row_item = $result_items->fetch_assoc()) {
        $item_ids[] = $row_item['item_id'];
    }
    $stmt_fetch_items->close();

    error_log("Processing Term ID: $term_id - Action: $action - Fetched item IDs for status update: " . print_r($item_ids, true));


    if (empty($item_ids) && $action === 'approve') {
        error_log("Warning: Term ID {$term_id} has no associated items during approval attempt.");
    }


    if ($action === 'approve') {
        // Update donation_terms status to 'Doado'
        $sql_update_term = "UPDATE donation_terms SET status = 'Doado', reproval_reason = NULL WHERE term_id = ?";
        $stmt_update_term = $conn->prepare($sql_update_term);
        if (!$stmt_update_term) throw new Exception("Erro ao preparar atualização do termo: " . $conn->error);
        $stmt_update_term->bind_param("i", $term_id);
        if (!$stmt_update_term->execute() || $stmt_update_term->affected_rows === 0) {
            throw new Exception("Falha ao aprovar o termo ID " . htmlspecialchars($term_id) . ". Nenhuma linha afetada ou erro: " . $stmt_update_term->error);
        }
        $stmt_update_term->close();

        if (!empty($item_ids)) {
            $item_placeholders = implode(',', array_fill(0, count($item_ids), '?'));
            $sql_update_items = "UPDATE items SET status = 'Doado' WHERE id IN ($item_placeholders)";
            $stmt_update_items = $conn->prepare($sql_update_items);
            if (!$stmt_update_items) throw new Exception("Erro ao preparar atualização dos itens: " . $conn->error);

            $types = str_repeat('i', count($item_ids));
            $stmt_update_items->bind_param($types, ...$item_ids);
            if (!$stmt_update_items->execute()) {
                throw new Exception("Falha ao atualizar status dos itens para 'Doado': " . $stmt_update_items->error);
            }
            error_log("Term ID: $term_id - Approve action - Item update SQL: " . $sql_update_items);
            error_log("Term ID: $term_id - Approve action - Affected rows for item status update to 'Doado': " . $stmt_update_items->affected_rows);
            $stmt_update_items->close();
        }
        $message = "Termo de doação ID " . htmlspecialchars($term_id) . " APROVADO com sucesso.";
        $success = true;

    } elseif ($action === 'decline') {
        $sql_update_term_status = "UPDATE donation_terms SET status = 'Reprovado', reproval_reason = ? WHERE term_id = ?";
        $stmt_update_term_status = $conn->prepare($sql_update_term_status);
        if (!$stmt_update_term_status) throw new Exception("Erro ao preparar atualização do status do termo para Reprovado: " . $conn->error);
        $stmt_update_term_status->bind_param("si", $reproval_reason, $term_id);
        if (!$stmt_update_term_status->execute() || $stmt_update_term_status->affected_rows === 0) {
            throw new Exception("Falha ao reprovar o termo ID " . htmlspecialchars($term_id) . " (status não atualizado): " . $stmt_update_term_status->error);
        }
        $stmt_update_term_status->close();

        if (!empty($item_ids)) {
            $item_placeholders = implode(',', array_fill(0, count($item_ids), '?'));
            $sql_revert_items = "UPDATE items SET status = 'Pendente' WHERE id IN ($item_placeholders)";
            $stmt_revert_items = $conn->prepare($sql_revert_items);
            if (!$stmt_revert_items) throw new Exception("Erro ao preparar reversão dos itens: " . $conn->error);

            $types = str_repeat('i', count($item_ids));
            $stmt_revert_items->bind_param($types, ...$item_ids);
            if (!$stmt_revert_items->execute()) {
                throw new Exception("Falha ao reverter status dos itens para 'Pendente': " . $stmt_revert_items->error);
            }
            error_log("Term ID: $term_id - Decline action - Item revert SQL: " . $sql_revert_items);
            error_log("Term ID: $term_id - Decline action - Affected rows for item status update to 'Pendente': " . $stmt_revert_items->affected_rows);
            $stmt_revert_items->close();
        }

        $message = "Termo de doação ID " . htmlspecialchars($term_id) . " REPROVADO com sucesso. Motivo: " . htmlspecialchars($reproval_reason);
        $success = true;
    }

    $conn->commit();

} catch (Exception $e) {
    $conn->rollback();
    error_log("Erro ao processar termo de doação ID " . $term_id . ": " . $e->getMessage());
    $message = $e->getMessage();
    $success = false;
} finally {
    $_SESSION['approval_action_message'] = $message;
    $_SESSION['approval_action_success'] = $success;
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
    header('Location: approve_donations_page.php');
    exit();
}
?>
