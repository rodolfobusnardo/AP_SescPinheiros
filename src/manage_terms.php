<?php
require_once 'auth.php';
require_once 'db_connect.php';

require_login(); // Allow all logged-in users

$page_title = "Gerenciar Termos";
$page_error_message = '';
$devolution_terms = [];
$donation_terms_list = [];

// Get filter status
$filter_status = $_GET['filter_status'] ?? '';

// Fetch Devolution Terms
if ($filter_status === '' || $filter_status === 'Devolvido') {
    $sql_dev_terms = "SELECT dd.id AS devolution_id, i.name AS item_name, dd.devolution_timestamp,
                             dd.owner_name, u.username AS processed_by_username
                      FROM devolution_documents dd
                      JOIN items i ON dd.item_id = i.id
                      JOIN users u ON dd.returned_by_user_id = u.id
                      ORDER BY dd.devolution_timestamp DESC";
    $result_dev_terms = $conn->query($sql_dev_terms);
    if ($result_dev_terms) {
        while ($row = $result_dev_terms->fetch_assoc()) {
            $devolution_terms[] = $row;
        }
    } else {
        error_log("Error fetching devolution terms (manage_terms.php): " . $conn->error);
        $page_error_message .= "Erro ao carregar termos de devolução. ";
    }
}

// Fetch Donation Terms
$sql_don_terms_base = "SELECT dt.term_id, dt.created_at AS term_creation_date, dt.institution_name,
                              dt.status, dt.reproval_reason, u.username AS registered_by_username
                       FROM donation_terms dt
                       LEFT JOIN users u ON dt.user_id = u.id";
$conditions_don = [];
$params_don = [];
$types_don = "";

if (in_array($filter_status, ['Aguardando Aprovação', 'Doado', 'Reprovado'])) {
    $conditions_don[] = "dt.status = ?";
    $params_don[] = $filter_status;
    $types_don .= "s";
} elseif ($filter_status === 'Devolvido') { // If filtering for "Devolvido", don't fetch donation terms
    $conditions_don[] = "1=0"; // This will make the query return no results for donations
}
// If $filter_status is empty, no status condition is added, fetching all donation terms.

$sql_don_terms = $sql_don_terms_base;
if (!empty($conditions_don)) {
    $sql_don_terms .= " WHERE " . implode(" AND ", $conditions_don);
}
$sql_don_terms .= " ORDER BY dt.created_at DESC";

$stmt_don_terms = $conn->prepare($sql_don_terms);

if ($stmt_don_terms) {
    if (!empty($types_don) && !empty($params_don)) {
        $stmt_don_terms->bind_param($types_don, ...$params_don);
    }
    if ($stmt_don_terms->execute()) {
        $result_don_terms = $stmt_don_terms->get_result();
        while ($term = $result_don_terms->fetch_assoc()) {
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
                $stmt_summary->bind_param("i", $term['term_id']);
                if ($stmt_summary->execute()) {
                    $result_summary_items = $stmt_summary->get_result();
                    while ($summary_item = $result_summary_items->fetch_assoc()) {
                        $item_summary_parts[] = htmlspecialchars($summary_item['category_name']) . ": " . htmlspecialchars($summary_item['item_count']);
                    }
                    $term['item_summary_text'] = empty($item_summary_parts) ? 'Nenhum item encontrado.' : implode(', ', $item_summary_parts);
                } else {
                    error_log("Error executing item summary for donation term ID " . $term['term_id'] . " (manage_terms.php): " . $stmt_summary->error);
                    $term['item_summary_text'] = 'Erro ao carregar resumo.';
                }
                $stmt_summary->close();
            } else {
                error_log("Error preparing item summary for donation term ID " . $term['term_id'] . " (manage_terms.php): " . $conn->error);
                $term['item_summary_text'] = 'Erro ao preparar resumo.';
            }
            $donation_terms_list[] = $term;
        }
    } else {
        error_log("Error executing donation terms query (manage_terms.php): " . $stmt_don_terms->error);
        $page_error_message .= "Erro ao carregar termos de doação. ";
    }
    $stmt_don_terms->close();
} else {
    error_log("Error preparing donation terms query (manage_terms.php): " . $conn->error);
    $page_error_message .= "Erro crítico ao preparar busca de termos de doação. ";
}


require_once 'templates/header.php';
?>

<div class="container admin-container">
    <h2><?php echo htmlspecialchars($page_title); ?></h2>

    <?php if (!empty($page_error_message)): ?>
        <p class="error-message"><?php echo htmlspecialchars(trim($page_error_message)); ?></p>
    <?php endif; ?>

    <form method="GET" action="manage_terms.php" class="form-filters" style="margin-bottom: 20px;">
        <div class="filter-row">
            <div>
                <label for="filter_status">Status do Termo:</label>
                <select id="filter_status" name="filter_status" onchange="this.form.submit()">
                    <option value="" <?php if (empty($filter_status)) echo 'selected'; ?>>Todos os Status</option>
                    <option value="Aguardando Aprovação" <?php if ($filter_status === 'Aguardando Aprovação') echo 'selected'; ?>>Aguardando Aprovação (Doação)</option>
                    <option value="Doado" <?php if ($filter_status === 'Doado') echo 'selected'; ?>>Doado (Doação)</option>
                    <option value="Reprovado" <?php if ($filter_status === 'Reprovado') echo 'selected'; ?>>Reprovado (Doação)</option>
                    <option value="Devolvido" <?php if ($filter_status === 'Devolvido') echo 'selected'; ?>>Devolvido (Devolução)</option>
                </select>
            </div>
            <div>
                 <button type="submit" class="button-filter" style="margin-top:25px;">Filtrar</button>
            </div>
        </div>
    </form>

    <?php if ($filter_status === '' || $filter_status === 'Devolvido'): ?>
    <h3>Termos de Devolução</h3>
    <?php if (!empty($devolution_terms)): ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome do Item</th>
                    <th>Data/Hora Devolução</th>
                    <th>Recebedor</th>
                    <th>Processado Por</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($devolution_terms as $term): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($term['devolution_id']); ?></td>
                        <td><?php echo htmlspecialchars($term['item_name']); ?></td>
                        <td><?php echo htmlspecialchars(date('d/m/Y H:i:s', strtotime($term['devolution_timestamp']))); ?></td>
                        <td><?php echo htmlspecialchars($term['owner_name']); ?></td>
                        <td><?php echo htmlspecialchars($term['processed_by_username']); ?></td>
                        <td class="actions-cell">
                            <a href="manage_devolutions.php?view_id=<?php echo htmlspecialchars($term['devolution_id']); ?>" class="button-secondary">Ver Termo</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>Nenhum termo de devolução encontrado<?php if (!empty($filter_status)) echo " para o status selecionado"; ?>.</p>
    <?php endif; ?>
    <?php endif; ?>


    <?php if ($filter_status === '' || in_array($filter_status, ['Aguardando Aprovação', 'Doado', 'Reprovado'])): ?>
    <?php if ($filter_status === ''): ?> <hr> <?php endif; // Add HR only if both sections might show ?>
    <h3>Termos de Doação</h3>
    <?php if (!empty($donation_terms_list)): ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID Termo</th>
                    <th>Data Criação</th>
                    <th>Instituição</th>
                    <th>Status</th>
                    <th>Registrado Por</th>
                    <th>Resumo dos Itens</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($donation_terms_list as $term): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($term['term_id']); ?></td>
                        <td><?php echo htmlspecialchars(date('d/m/Y H:i:s', strtotime($term['term_creation_date']))); ?></td>
                        <td><?php echo htmlspecialchars($term['institution_name']); ?></td>
                        <td>
                            <span class="status-<?php echo strtolower(str_replace(' ', '-', htmlspecialchars($term['status']))); ?> status-tag">
                                <?php echo htmlspecialchars($term['status']); ?>
                            </span>
                            <?php if ($term['status'] === 'Reprovado' && !empty(trim($term['reproval_reason']))): ?>
                                <small style="display:block; margin-top:5px; color: #721c24;" title="<?php echo htmlspecialchars(trim($term['reproval_reason'])); ?>">
                                    Motivo (passe o mouse)
                                </small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($term['registered_by_username'] ?? 'N/A'); ?></td>
                        <td><?php echo $term['item_summary_text']; ?></td>
                        <td class="actions-cell">
                             <a href="view_donation_term_page.php?term_id=<?php echo htmlspecialchars($term['term_id']); ?>" class="button-secondary">Ver Termo</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>Nenhum termo de doação encontrado<?php if (!empty($filter_status)) echo " para o status selecionado"; ?>.</p>
    <?php endif; ?>
    <?php endif; ?>

</div>

<?php
require_once 'templates/footer.php';
?>
