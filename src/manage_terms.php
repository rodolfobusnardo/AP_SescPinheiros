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
                             dd.owner_name, u.username AS processed_by_username, u.full_name AS processed_by_full_name
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
                              dt.status, dt.reproval_reason, u.username AS registered_by_username, u.full_name AS registered_by_full_name
                       FROM donation_terms dt
                       LEFT JOIN users u ON dt.user_id = u.id";
$conditions_don = [];
$params_don = [];
$types_don = "";

if (in_array($filter_status, ['Aguardando Aprovação', 'Doado', 'Reprovado'])) {
    $conditions_don[] = "dt.status = ?";
    $params_don[] = $filter_status;
    $types_don .= "s";
} elseif ($filter_status === 'Devolvido') {
    $conditions_don[] = "1=0";
}

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

            $stmt_summary_inner = $conn->prepare($sql_summary); // Use different var name for inner stmt
            if ($stmt_summary_inner) {
                $stmt_summary_inner->bind_param("i", $term['term_id']);
                if ($stmt_summary_inner->execute()) {
                    $result_summary_items = $stmt_summary_inner->get_result();
                    while ($summary_item = $result_summary_items->fetch_assoc()) {
                        $item_summary_parts[] = htmlspecialchars($summary_item['category_name']) . ": " . htmlspecialchars($summary_item['item_count']);
                    }
                    $term['item_summary_text'] = empty($item_summary_parts) ? 'Nenhum item encontrado.' : implode(', ', $item_summary_parts);
                } else {
                    error_log("Error executing item summary for donation term ID " . $term['term_id'] . " (manage_terms.php): " . $stmt_summary_inner->error);
                    $term['item_summary_text'] = 'Erro ao carregar resumo.';
                }
                $stmt_summary_inner->close();
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
                        <td>
                            <?php
                            $dev_display_name = !empty(trim($term['processed_by_full_name'] ?? ''))
                                             ? $term['processed_by_full_name']
                                             : $term['processed_by_username'];
                            echo htmlspecialchars($dev_display_name ?? 'N/A');
                            ?>
                        </td>
                        <td class="actions-cell">
                            <a href="manage_devolutions.php?view_id=<?php echo htmlspecialchars($term['devolution_id']); ?>" class="button-secondary">Ver Termo</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>Nenhum termo de devolução encontrado<?php if (!empty($filter_status) && $filter_status === 'Devolvido') echo " para o status selecionado"; elseif(empty($filter_status)) echo "."; else echo "."; ?>.</p>
    <?php endif; ?>
    <?php endif; ?>


    <?php if ($filter_status === '' || in_array($filter_status, ['Aguardando Aprovação', 'Doado', 'Reprovado'])): ?>
    <?php if ($filter_status === ''): ?> <hr> <?php endif; ?>
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
                    <?php
                        $view_term_link = "view_donation_term_page.php?term_id=" . htmlspecialchars($term['term_id']);
                        $button_text = "Ver Termo";
                        if ($term['status'] === 'Aguardando Aprovação') {
                            $view_term_link .= "&context=approval";
                            // For users who can approve, the button text could be "Analisar"
                            if (isset($_SESSION['user_role']) && ($_SESSION['user_role'] === 'superAdmin' || $_SESSION['user_role'] === 'admin-aprovador')) {
                                $button_text = "Analisar Pendência";
                            }
                        }
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($term['term_id']); ?></td>
                        <td><?php echo htmlspecialchars(date('d/m/Y H:i:s', strtotime($term['term_creation_date']))); ?></td>
                        <td><?php echo htmlspecialchars($term['institution_name']); ?></td>
                        <td>
                            <?php
                            $raw_status_term = $term['status'];
                            $status_text_display_term = htmlspecialchars($raw_status_term);
                            $class_name_normalized_term = $raw_status_term;
                            $class_name_normalized_term = strtolower($class_name_normalized_term);
                            $char_map_simple_term = [
                                'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
                                'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
                                'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
                                'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
                                'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
                                'ç' => 'c', 'ñ' => 'n',
                            ];
                            $class_name_normalized_term = strtr($class_name_normalized_term, $char_map_simple_term);
                            $class_name_normalized_term = preg_replace('/[^a-z0-9\s-]/', '', $class_name_normalized_term);
                            $class_name_normalized_term = preg_replace('/[\s-]+/', '-', $class_name_normalized_term);
                            $status_class_name_final_term = trim($class_name_normalized_term, '-');
                            ?>
                            <span class="status-<?php echo $status_class_name_final_term; ?> status-tag">
                                <?php echo $status_text_display_term; ?>
                            </span>
                            <?php if ($term['status'] === 'Reprovado' && !empty(trim($term['reproval_reason']))): ?>
                                <small style="display:block; margin-top:5px; color: #721c24;" title="<?php echo htmlspecialchars(trim($term['reproval_reason'])); ?>">
                                    Motivo (passe o mouse)
                                </small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $don_display_name = !empty(trim($term['registered_by_full_name'] ?? ''))
                                             ? $term['registered_by_full_name']
                                             : $term['registered_by_username'];
                            echo htmlspecialchars($don_display_name ?? 'N/A');
                            ?>
                        </td>
                        <td><?php echo $term['item_summary_text']; ?></td>
                        <td class="actions-cell">
                             <a href="<?php echo $view_term_link; ?>" class="button-secondary"><?php echo $button_text; ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>Nenhum termo de doação encontrado<?php if (!empty($filter_status) && in_array($filter_status, ['Aguardando Aprovação', 'Doado', 'Reprovado'])) echo " para o status selecionado"; elseif(empty($filter_status)) echo "."; else echo ".";?>.</p>
    <?php endif; ?>
    <?php endif; ?>

</div>

<?php
require_once 'templates/footer.php';
?>
