<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db_connect.php';

require_admin('/index.php?error=unauthorized_donation_submit');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['home_page_error_message'] = 'Acesso inválido ao handler de doação.';
    header('Location: /home.php');
    exit();
}

$conn->begin_transaction();

try {
    // --- 1. Retrieve and Sanitize Form Data ---
    $responsible_donation_from_session = $_SESSION['username'] ?? ''; // Should match the readonly field
    $user_id_from_session = $_SESSION['user_id'] ?? null;

    $donation_date = trim($_POST['donation_date'] ?? '');
    $donation_time = trim($_POST['donation_time'] ?? '');
    $institution_name = trim($_POST['institution_name'] ?? '');
    $institution_cnpj = trim($_POST['institution_cnpj'] ?? '');
    $institution_ie = trim($_POST['institution_ie'] ?? '');
    $institution_responsible_name = trim($_POST['institution_responsible_name'] ?? '');
    $institution_phone = trim($_POST['institution_phone'] ?? '');
    $institution_address_street = trim($_POST['institution_address_street'] ?? '');
    $institution_address_number = trim($_POST['institution_address_number'] ?? '');
    $institution_address_bairro = trim($_POST['institution_address_bairro'] ?? '');
    $institution_address_cidade = trim($_POST['institution_address_cidade'] ?? '');
    $institution_address_estado = trim($_POST['institution_address_estado'] ?? '');
    $institution_address_cep = trim($_POST['institution_address_cep'] ?? '');
    $signature_data_base64 = $_POST['signature_data'] ?? '';
    $item_ids_str_for_donation = trim($_POST['item_ids_for_donation'] ?? '');

    // --- 2. Validate Inputs ---
    $errors = [];
    if (empty($user_id_from_session)) {
        $errors[] = "Sessão de usuário inválida. Faça login novamente.";
    }
    if (empty($responsible_donation_from_session)) { // Should not happen if user_id is set
        $errors[] = "Nome do responsável pela doação (usuário logado) não encontrado.";
    }
    if (empty($donation_date)) $errors[] = "Data da doação é obrigatória.";
    if (empty($donation_time)) $errors[] = "Hora da doação é obrigatória.";
    if (empty($institution_name)) $errors[] = "Nome da instituição é obrigatório.";
    if (empty($institution_responsible_name)) $errors[] = "Nome do responsável da instituição é obrigatório.";
    if (empty($signature_data_base64)) $errors[] = "Assinatura é obrigatória.";
    if (empty($item_ids_str_for_donation)) $errors[] = "Nenhum item ID fornecido para doação.";

    // Validate date format (YYYY-MM-DD)
    if (!empty($donation_date) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $donation_date)) {
        $errors[] = "Formato de data inválido. Use AAAA-MM-DD.";
    }
    // Validate time format (HH:MM)
    if (!empty($donation_time) && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $donation_time)) {
        $errors[] = "Formato de hora inválido. Use HH:MM.";
    }

    $item_ids_array = [];
    if (!empty($item_ids_str_for_donation)) {
        $raw_ids = explode(',', $item_ids_str_for_donation);
        foreach ($raw_ids as $id_str) {
            $id_int = filter_var(trim($id_str), FILTER_VALIDATE_INT);
            if ($id_int !== false && $id_int > 0) {
                $item_ids_array[] = $id_int;
            }
        }
        if (empty($item_ids_array)) {
            $errors[] = "Os IDs dos itens fornecidos são inválidos ou estão vazios.";
        }
    }

    if (!empty($errors)) {
        throw new Exception(implode("<br>", $errors));
    }

    // --- 3. Process and Save Signature Image ---
    if (!preg_match('/^data:image\/png;base64,/', $signature_data_base64)) {
        throw new Exception("Formato de dados da assinatura inválido.");
    }
    $base64_data = base64_decode(preg_replace('/^data:image\/png;base64,/', '', $signature_data_base64));
    if ($base64_data === false) {
        throw new Exception("Falha ao decodificar dados da assinatura.");
    }

    $upload_dir = __DIR__ . '/../uploads/donation_signatures/';
    // IMPORTANT: Ensure this directory exists and is writable by the web server.
    // You might need to create it manually: mkdir -p uploads/donation_signatures && chmod 775 uploads/donation_signatures
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0775, true)) { // Attempt to create if not exists
             error_log("Failed to create signature upload directory: " . $upload_dir);
             throw new Exception("Erro no servidor: Diretório de upload de assinaturas não existe e não pôde ser criado.");
        }
    }

    $signature_filename = 'sig_term_' . time() . '_' . $user_id_from_session . '.png';
    $signature_file_path_full = $upload_dir . $signature_filename;
    $signature_db_path = 'uploads/donation_signatures/' . $signature_filename;

    if (!file_put_contents($signature_file_path_full, $base64_data)) {
        error_log("Failed to save signature image to: " . $signature_file_path_full);
        throw new Exception("Falha ao salvar a imagem da assinatura.");
    }

    // --- 4. Database Operations ---
    // A. Verify Item Status
    $placeholders_check = implode(',', array_fill(0, count($item_ids_array), '?'));
    $sql_check_items = "SELECT id, status FROM items WHERE id IN ($placeholders_check)";
    $stmt_check_items = $conn->prepare($sql_check_items);
    if (!$stmt_check_items) throw new Exception("Erro ao preparar verificação de itens: " . $conn->error);

    $types_check = str_repeat('i', count($item_ids_array));
    $stmt_check_items->bind_param($types_check, ...$item_ids_array);
    $stmt_check_items->execute();
    $result_check_items = $stmt_check_items->get_result();
    $found_items_map = [];
    while ($item_row = $result_check_items->fetch_assoc()) {
        $found_items_map[$item_row['id']] = $item_row['status'];
    }
    $stmt_check_items->close();

    $problematic_items_info = [];
    foreach ($item_ids_array as $req_id) {
        if (!isset($found_items_map[$req_id])) {
            $problematic_items_info[] = "ID " . htmlspecialchars($req_id) . " (não encontrado)";
        } elseif ($found_items_map[$req_id] !== 'Pendente') {
            $problematic_items_info[] = "ID " . htmlspecialchars($req_id) . " (status: " . htmlspecialchars($found_items_map[$req_id]) . ")";
        }
    }

    if (!empty($problematic_items_info)) {
        // Potentially delete already saved signature if we abort here? Or leave for cleanup script.
        // For now, just error out.
        unlink($signature_file_path_full); // Attempt to delete saved signature if items are problematic
        throw new Exception("Alguns itens não podem ser doados: " . implode(', ', $problematic_items_info) . ". Apenas itens com status 'Pendente' são permitidos.");
    }


    // B. Insert into donation_terms
    $sql_insert_term = "INSERT INTO donation_terms (user_id, responsible_donation, donation_date, donation_time,
                        institution_name, institution_cnpj, institution_ie, institution_responsible_name, institution_phone,
                        institution_address_street, institution_address_number, institution_address_bairro,
                        institution_address_cidade, institution_address_estado, institution_address_cep,
                        signature_image_path, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Aguardando Aprovação')";
    $stmt_insert_term = $conn->prepare($sql_insert_term);
    if (!$stmt_insert_term) throw new Exception("Erro ao preparar inserção do termo: " . $conn->error);

    $stmt_insert_term->bind_param("isssssssssssssss",
        $user_id_from_session, $responsible_donation_from_session, $donation_date, $donation_time,
        $institution_name, $institution_cnpj, $institution_ie, $institution_responsible_name, $institution_phone,
        $institution_address_street, $institution_address_number, $institution_address_bairro,
        $institution_address_cidade, $institution_address_estado, $institution_address_cep,
        $signature_db_path
    );
    if (!$stmt_insert_term->execute()) throw new Exception("Erro ao salvar termo de doação: " . $stmt_insert_term->error);
    $term_id = $conn->insert_id;
    $stmt_insert_term->close();

    // C. Insert into donation_term_items
    $sql_insert_term_item = "INSERT INTO donation_term_items (term_id, item_id) VALUES (?, ?)";
    $stmt_insert_term_item = $conn->prepare($sql_insert_term_item);
    if (!$stmt_insert_term_item) throw new Exception("Erro ao preparar inserção dos itens do termo: " . $conn->error);
    foreach ($item_ids_array as $item_id) {
        $stmt_insert_term_item->bind_param("ii", $term_id, $item_id);
        if (!$stmt_insert_term_item->execute()) throw new Exception("Erro ao associar item ID " . htmlspecialchars($item_id) . " ao termo: " . $stmt_insert_term_item->error);
    }
    $stmt_insert_term_item->close();

    // D. Update items status
    $placeholders_update = implode(',', array_fill(0, count($item_ids_array), '?'));
    $sql_update_items = "UPDATE items SET status = 'Aguardando Aprovação' WHERE id IN ($placeholders_update)";
    $stmt_update_items = $conn->prepare($sql_update_items);
    if (!$stmt_update_items) throw new Exception("Erro ao preparar atualização dos itens: " . $conn->error);

    $types_update = str_repeat('i', count($item_ids_array));
    $stmt_update_items->bind_param($types_update, ...$item_ids_array);
    if (!$stmt_update_items->execute()) throw new Exception("Erro ao atualizar status dos itens: " . $stmt_update_items->error);

    if ($stmt_update_items->affected_rows != count($item_ids_array)) {
        // This might indicate some items were not updated, could be a problem or already in desired state (though check A should prevent this)
        error_log("Warning: Number of items updated (" . $stmt_update_items->affected_rows . ") does not match expected (" . count($item_ids_array) . ") for term ID " . $term_id);
    }
    $stmt_update_items->close();

    // --- 5. Transaction Management and Redirect ---
    $conn->commit();
    $_SESSION['home_page_success_message'] = "Termo de doação (ID: {$term_id}) enviado para aprovação com sucesso!";
    header('Location: /home.php');
    exit();

} catch (Exception $e) {
    $conn->rollback();
    error_log("Donation Submission Error: " . $e->getMessage());
    $_SESSION['generate_donation_page_error_message'] = $e->getMessage();
    // Pass back original item_ids if redirecting to the form page to allow retrying.
    $redirect_url = 'generate_donation_term_page.php';
    if (!empty($item_ids_str_for_donation)) {
        $redirect_url .= '?item_ids=' . urlencode($item_ids_str_for_donation);
    }
    header('Location: ' . $redirect_url);
    exit();
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
?>
