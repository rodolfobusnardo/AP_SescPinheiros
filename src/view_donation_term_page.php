<?php
require_once 'auth.php'; // Includes start_secure_session()
require_once 'db_connect.php';

require_login(); // Allow any logged-in user to access this page initially

$term_id = filter_input(INPUT_GET, 'term_id', FILTER_VALIDATE_INT);
$context = strval($_GET['context'] ?? '');

$term_data = null;
$item_summary_text = 'Nenhum item associado ou erro ao carregar.';
$page_error_message = '';
$voltar_link = '/manage_terms.php'; // Simplified Voltar link

if (!$term_id || $term_id <= 0) {
    $_SESSION['manage_terms_page_message'] = 'ID do termo de doação inválido ou não fornecido.';
    $_SESSION['manage_terms_page_message_type'] = 'error';
    header('Location: ' . $voltar_link);
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

            if ($context === 'approval') {
                if ($term_data['status'] !== 'Aguardando Aprovação') {
                    $page_error_message = "Este termo (ID: " . htmlspecialchars($term_id) . ") não está 'Aguardando Aprovação'. Status Atual: " . htmlspecialchars($term_data['status']) . ". Ações de aprovação não são aplicáveis.";
                }
            } else {
                if (!in_array($term_data['status'], ['Doado', 'Reprovado', 'Aguardando Aprovação'])) {
                    $page_error_message = "Status do termo inválido para visualização pública: " . htmlspecialchars($term_data['status']);
                    $term_data = null;
                }
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
                $item_summary_text = "Nenhum item individual listado para este termo.";
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

$can_approve_decline = (isset($_SESSION['user_role']) && ($_SESSION['user_role'] === 'superAdmin' || $_SESSION['user_role'] === 'admin-aprovador'));

require_once 'templates/header.php';
?>

<div class="container view-term-container">
    <?php if (!empty($page_error_message)): ?>
        <h2>Erro ao Visualizar Termo</h2>
        <p class="error-message"><?php echo htmlspecialchars($page_error_message); ?></p>
        <p><a href="<?php echo $voltar_link; ?>" class="button-secondary">Voltar para Termos</a></p>
    <?php elseif ($term_data): ?>
        <h2>Termo de Doação - ID: <?php echo htmlspecialchars($term_data['term_id']); ?></h2>

        <div class="term-section">
            <h3>Dados da Doação</h3>
            <p><strong>ID do Termo:</strong> <?php echo htmlspecialchars($term_data['term_id']); ?></p>
            <p><strong>Data e Hora da Doação (Registro do Termo):</strong>
                <?php
                $created_datetime = DateTime::createFromFormat('Y-m-d H:i:s', $term_data['created_at']);
                echo htmlspecialchars($created_datetime ? $created_datetime->format('d/m/Y H:i:s') : 'Data Inválida');
                echo " (Evento em: ";
                $donation_datetime_str = $term_data['donation_date'] . ' ' . $term_data['donation_time'];
                if (strlen($term_data['donation_time']) == 5) $donation_datetime_str .= ':00';
                $donation_datetime = DateTime::createFromFormat('Y-m-d H:i:s', $donation_datetime_str);
                echo htmlspecialchars($donation_datetime ? $donation_datetime->format('d/m/Y H:i') : 'Data/Hora do Evento Inválida');
                echo ")";
                ?>
            </p>
            <p><strong>Responsável pela Doação (Sistema):</strong> <?php echo htmlspecialchars($term_data['responsible_donation']); ?></p>
            <p><strong>Registrado Por (Usuário):</strong> <?php echo htmlspecialchars($term_data['registered_by_username'] ?? 'N/A'); ?></p>
            <p><strong>Status do Termo:</strong> <span class="status-<?php echo strtolower(str_replace(' ', '-', htmlspecialchars($term_data['status']))); ?> status-tag"><?php echo htmlspecialchars($term_data['status']); ?></span></p>
        </div>

        <?php if ($term_data['status'] === 'Reprovado' && !empty(trim($term_data['reproval_reason']))): ?>
        <fieldset class="data-section-rounded term-section">
            <legend style="color: #721c24; font-weight:bold;">Motivo da Reprovação</legend>
            <p><?php echo nl2br(htmlspecialchars(trim($term_data['reproval_reason']))); ?></p>
        </fieldset>
        <?php endif; ?>

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
            <p><?php echo $item_summary_text; ?></p>
        </div>

        <div class="term-section">
            <h3>Assinatura do Recebedor</h3>
            <img src="/<?php echo htmlspecialchars($term_data['signature_image_path']); ?>" alt="Assinatura do Recebedor" class="signature-image">
        </div>

        <?php if ($context === 'approval' && isset($term_data['status']) && $term_data['status'] === 'Aguardando Aprovação' && $can_approve_decline): ?>
        <div class="term-actions approval-actions no-print" style="margin-top: 20px; padding-top:20px; border-top: 1px solid #eee; display:flex; gap:10px; justify-content:center;">
            <form action="/admin/process_donation_approval_handler.php" method="POST" style="display:inline;" onsubmit="return confirm('Tem certeza que deseja APROVAR este termo de doação?');">
                <input type="hidden" name="term_id" value="<?php echo htmlspecialchars($term_data['term_id']); ?>">
                <button type="submit" class="button-primary">Aprovar Termo</button>
            </form>
            <button type="button" id="openReprovalModalButton" class="button-delete">Reprovar Termo</button>
        </div>
        <?php endif; ?>

        <div class="term-actions no-print" style="margin-top: 30px;">
            <a href="<?php echo $voltar_link; ?>" class="button-secondary">Voltar para Termos</a>
            <button onclick="window.print();" class="button-primary">Imprimir Termo</button>
        </div>

    <?php else: ?>
        <?php
            if(empty($page_error_message)) {
                 $page_error_message = "Não foi possível carregar os dados do termo de doação ou o status atual não permite a visualização neste contexto.";
            }
        ?>
         <h2>Erro ao Visualizar Termo</h2>
        <p class="error-message"><?php echo htmlspecialchars($page_error_message); ?></p>
        <p><a href="<?php echo $voltar_link; ?>" class="button-secondary">Voltar para Termos</a></p>
    <?php endif; ?>
</div>

<!-- Reproval Reason Modal -->
<div id="reprovalReasonModal" class="modal" style="display: none;">
    <div class="modal-content">
        <span class="modal-close-button" id="closeReprovalModal">&times;</span>
        <h3>Reprovar Termo de Doação</h3>
        <form id="reprovalForm" action="/admin/process_donation_approval_handler.php" method="POST">
            <input type="hidden" name="action" value="decline">
            <input type="hidden" name="term_id" value="<?php echo htmlspecialchars($term_data['term_id'] ?? ''); ?>">
            <div>
                <label for="reproval_reason_text">Motivo da Reprovação (obrigatório):</label>
                <textarea id="reproval_reason_text" name="reproval_reason" rows="4" required style="width: 95%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;"></textarea>
            </div>
            <div class="form-action-buttons-group" style="margin-top:15px; justify-content: flex-end;">
                <button type="button" id="cancelReprovalButton" class="button-secondary">Cancelar</button>
                <button type="submit" class="button-delete">Confirmar Reprovação</button>
            </div>
        </form>
    </div>
</div>


<style>
    .view-term-container .term-section { margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
    .view-term-container .term-section h3 { margin-top: 0; color: #337ab7; }
    .view-term-container p { margin-bottom: 8px; }
    .signature-image { border: 1px solid #ddd; max-width: 400px; height: auto; }
    .approval-actions { background-color: #f9f9f9; padding: 20px; border-radius: 8px; margin-bottom:20px; }
    /* Modal styles are assumed to be in style.css, but basic display:none is here for the div */
    @media print {
        .no-print, header, footer, nav, .button-secondary, .button-primary, .approval-actions, #reprovalReasonModal { display: none !important; }
        .container, .view-term-container { width: 100% !important; margin: 0 !important; padding: 0 !important; border: none !important; box-shadow: none !important;}
        .term-section { border-bottom: none; page-break-inside: avoid; }
        .signature-image { max-width: 300px; }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const reprovalModal = document.getElementById('reprovalReasonModal');
    const openModalButton = document.getElementById('openReprovalModalButton');
    const closeModalButton = document.getElementById('closeReprovalModal');
    const cancelReprovalButton = document.getElementById('cancelReprovalButton');
    const reprovalForm = document.getElementById('reprovalForm');

    const currentTermIdForModal = '<?php echo htmlspecialchars($term_data['term_id'] ?? ''); ?>';
    const hiddenTermIdInput = reprovalForm ? reprovalForm.querySelector('input[name="term_id"]') : null;

    if (hiddenTermIdInput && currentTermIdForModal) {
        hiddenTermIdInput.value = currentTermIdForModal;
    }

    if (openModalButton && reprovalModal) {
        openModalButton.addEventListener('click', function() {
            if (hiddenTermIdInput && hiddenTermIdInput.value) {
                reprovalModal.style.display = 'block';
            } else {
                alert("Erro: ID do termo não definido para reprovação.");
            }
        });
    }
    if (closeModalButton && reprovalModal) {
        closeModalButton.addEventListener('click', function() {
            reprovalModal.style.display = 'none';
        });
    }
    if (cancelReprovalButton && reprovalModal) {
        cancelReprovalButton.addEventListener('click', function() {
            reprovalModal.style.display = 'none';
        });
    }

    window.addEventListener('click', function(event) {
        if (event.target == reprovalModal) {
            reprovalModal.style.display = 'none';
        }
    });

    if (reprovalForm) {
        reprovalForm.addEventListener('submit', function(event) {
            const reasonText = document.getElementById('reproval_reason_text');
            if (reasonText && reasonText.value.trim() === '') {
                alert('Por favor, forneça o motivo da reprovação.');
                event.preventDefault();
            }
        });
    }
});
</script>

<?php
require_once 'templates/footer.php';
?>
