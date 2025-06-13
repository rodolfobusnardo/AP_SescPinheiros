<?php
require_once 'auth.php'; // Includes start_secure_session()
require_once 'db_connect.php';

// Access Control: 'admin', 'admin-aprovador', or 'superAdmin'
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'admin-aprovador', 'superAdmin'])) {
    $_SESSION['manage_donations_page_message'] = 'Você não tem permissão para acessar esta página.'; // Message for manage_donations
    $_SESSION['manage_donations_page_message_type'] = 'error';
    header('Location: home.php'); // Redirect to home, as manage_donations might also be restricted
    exit();
}

$term_id = filter_input(INPUT_GET, 'term_id', FILTER_VALIDATE_INT);
$term_data = null;
$item_summary_text = 'Nenhum item associado ou erro ao carregar.';
$page_error_message = '';

if (!$term_id || $term_id <= 0) {
    $_SESSION['manage_donations_page_message'] = 'ID do termo de doação inválido ou não fornecido.';
    $_SESSION['manage_donations_page_message_type'] = 'error';
    header('Location: manage_donations.php');
    exit();
}

// Fetch donation term details
$sql_term = "SELECT dt.*, u.username AS registered_by_username
             FROM donation_terms dt
             LEFT JOIN users u ON dt.user_id = u.id
             WHERE dt.term_id = ?";
$stmt_term = $conn->prepare($sql_term);

if ($stmt_term) {
    $stmt_term->bind_param("i", $term_id);
    if ($stmt_term->execute()) {
        $result_term = $stmt_term->get_result();
        if ($result_term->num_rows === 1) {
            $term_data = $result_term->fetch_assoc();
            if ($term_data['status'] !== 'Doado') {
                $page_error_message = "Este termo de doação (ID: " . htmlspecialchars($term_id) . ") não está com status 'Doado'. Status atual: " . htmlspecialchars($term_data['status']) . ".";
                $term_data = null;
            }
        } else {
            $page_error_message = "Termo de doação com ID " . htmlspecialchars($term_id) . " não encontrado.";
        }
    } else {
        error_log("Error executing term fetch for view_donation_term_page (Term ID: $term_id): " . $stmt_term->error);
        $page_error_message = "Erro ao buscar dados do termo de doação. Tente novamente.";
    }
    $stmt_term->close();
} else {
    error_log("Error preparing term fetch for view_donation_term_page: " . $conn->error);
    $page_error_message = "Erro crítico ao preparar a busca dos dados do termo. Contacte o suporte.";
}

if ($term_data) {
    $item_summary_parts = [];
    $sql_summary = "SELECT c.name AS category_name, COUNT(dti.item_id) AS item_count
                    FROM donation_term_items dti
                    JOIN items i ON dti.item_id = i.id
                    JOIN categories c ON i.category_id = c.id
                    WHERE dti.term_id = ?
                    GROUP BY c.name
                    ORDER BY c.name ASC";

    $stmt_summary = $conn->prepare($sql_summary);
    if ($stmt_summary) {
        $stmt_summary->bind_param("i", $term_data['term_id']);
        if ($stmt_summary->execute()) {
            $result_summary_items = $stmt_summary->get_result();
            while ($summary_item = $result_summary_items->fetch_assoc()) {
                $item_summary_parts[] = htmlspecialchars($summary_item['item_count']) . "x " . htmlspecialchars($summary_item['category_name']);
            }
            if (!empty($item_summary_parts)) {
                $item_summary_text = implode(', ', $item_summary_parts) . ".";
            } else {
                $item_summary_text = "Nenhum item individual listado para este termo (verifique o processo de registro).";
            }
        } else {
            error_log("Error executing item summary for view_donation_term_page (Term ID: {$term_data['term_id']}): " . $stmt_summary->error);
            $item_summary_text = 'Erro ao carregar o resumo dos itens.';
        }
        $stmt_summary->close();
    } else {
        error_log("Error preparing item summary for view_donation_term_page: " . $conn->error);
        $item_summary_text = 'Erro crítico ao preparar o resumo dos itens.';
    }
}

require_once 'templates/header.php';
?>

<div class="container view-term-container">
    <?php if (!empty($page_error_message)): ?>
        <h2>Erro ao Visualizar Termo</h2>
        <p class="error-message"><?php echo htmlspecialchars($page_error_message); ?></p>
        <p><a href="manage_donations.php" class="button-secondary">Voltar para Lista de Doações</a></p>
    <?php elseif ($term_data): ?>
        <h2>Termo de Doação - ID: <?php echo htmlspecialchars($term_data['term_id']); ?></h2>

        <div class="term-section">
            <h3>Dados da Doação</h3>
            <p><strong>ID do Termo:</strong> <?php echo htmlspecialchars($term_data['term_id']); ?></p>
            <p><strong>Data e Hora da Doação:</strong>
                <?php
                $donation_datetime_str = $term_data['donation_date'] . ' ' . $term_data['donation_time'];
                if (strlen($term_data['donation_time']) == 5) $donation_datetime_str .= ':00';
                $donation_datetime = DateTime::createFromFormat('Y-m-d H:i:s', $donation_datetime_str);
                echo htmlspecialchars($donation_datetime ? $donation_datetime->format('d/m/Y H:i:s') : 'Data/Hora Inválida');
                ?>
            </p>
            <p><strong>Responsável pela Doação (Sistema):</strong> <?php echo htmlspecialchars($term_data['responsible_donation']); ?></p>
            <p><strong>Registrado Por (Usuário):</strong> <?php echo htmlspecialchars($term_data['registered_by_username'] ?? 'N/A'); ?></p>
            <p><strong>Status do Termo:</strong> <span class="status-<?php echo strtolower(htmlspecialchars($term_data['status'])); ?>"><?php echo htmlspecialchars($term_data['status']); ?></span></p>
        </div>

        <div class="term-section">
            <h3>Instituição Recebedora</h3>
            <p><strong>Nome da Instituição:</strong> <?php echo htmlspecialchars($term_data['institution_name']); ?></p>
            <p><strong>CNPJ:</strong> <?php echo htmlspecialchars($term_data['institution_cnpj'] ?? 'N/A'); ?></p>
            <p><strong>IE (Inscrição Estadual):</strong> <?php echo htmlspecialchars($term_data['institution_ie'] ?? 'N/A'); ?></p>
            <p><strong>Nome do Responsável (Instituição):</strong> <?php echo htmlspecialchars($term_data['institution_responsible_name']); ?></p>
            <p><strong>Telefone:</strong> <?php echo htmlspecialchars($term_data['institution_phone'] ?? 'N/A'); ?></p>
            <p><strong>Endereço:</strong>
                <?php
                $address_parts = [
                    htmlspecialchars($term_data['institution_address_street'] ?? ''),
                    htmlspecialchars($term_data['institution_address_number'] ?? ''),
                    htmlspecialchars($term_data['institution_address_bairro'] ?? ''),
                    htmlspecialchars($term_data['institution_address_cidade'] ?? ''),
                    htmlspecialchars($term_data['institution_address_estado'] ?? ''),
                    htmlspecialchars($term_data['institution_address_cep'] ?? '')
                ];
                echo implode(', ', array_filter($address_parts));
                if (empty(array_filter($address_parts))) echo 'N/A';
                ?>
            </p>
        </div>

        <div class="term-section">
            <h3>Itens Doados</h3>
            <p><?php echo $item_summary_text; // Already sanitized or error message ?></p>
        </div>

        <div class="term-section">
            <h3>Assinatura do Recebedor</h3>
            <img src="/<?php echo htmlspecialchars($term_data['signature_image_path']); ?>" alt="Assinatura do Recebedor" class="signature-image">
        </div>

        <div class="term-actions no-print" style="margin-top: 30px;">
            <a href="manage_donations.php" class="button-secondary">Voltar</a>
            <button onclick="window.print();" class="button-primary">Imprimir Termo</button>
        </div>

    <?php else: ?>
        <p class="error-message">Não foi possível carregar os dados do termo de doação. Verifique se o ID é válido e tente novamente.</p>
        <p><a href="manage_donations.php" class="button-secondary">Voltar para Lista de Doações</a></p>
    <?php endif; ?>
</div>

<style>
    .view-term-container .term-section { margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
    .view-term-container .term-section h3 { margin-top: 0; color: #337ab7; }
    .view-term-container p { margin-bottom: 8px; }
    .signature-image { border: 1px solid #ddd; max-width: 400px; height: auto; }
    @media print {
        .no-print, header, footer, nav, .button-secondary, .button-primary { display: none !important; }
        .container, .view-term-container { width: 100% !important; margin: 0 !important; padding: 0 !important; border: none !important; box-shadow: none !important;}
        .term-section { border-bottom: none; page-break-inside: avoid; }
        .signature-image { max-width: 300px; } /* Adjust size for print if needed */
    }
</style>

<?php
require_once 'templates/footer.php';
?>
