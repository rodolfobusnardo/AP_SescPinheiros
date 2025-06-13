<?php
require_once 'auth.php'; // Includes start_secure_session()
require_once 'db_connect.php';

// Access Control: 'admin', 'admin-aprovador', or 'superAdmin'
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'admin-aprovador', 'superAdmin'])) {
    $_SESSION['home_page_error_message'] = 'Você não tem permissão para acessar esta página.';
    header('Location: home.php');
    exit();
}

$approved_terms = [];
$page_error_message = ''; // For errors specific to this page loading

// Fetch approved donation terms
$sql_terms = "SELECT dt.term_id, dt.donation_date, dt.donation_time, dt.institution_name,
                     dt.responsible_donation, u.username AS registered_by_username
              FROM donation_terms dt
              LEFT JOIN users u ON dt.user_id = u.id
              WHERE dt.status = 'Doado'
              ORDER BY dt.donation_date DESC, dt.donation_time DESC";

$result_terms = $conn->query($sql_terms);

if ($result_terms) {
    while ($term = $result_terms->fetch_assoc()) {
        // For each term, fetch item summary
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
                error_log("Error executing item summary statement for term ID " . $term['term_id'] . " (manage_donations): " . $stmt_summary->error);
                $term['item_summary_text'] = 'Erro ao carregar resumo.';
            }
            $stmt_summary->close();
        } else {
            error_log("Error preparing item summary statement for term ID " . $term['term_id'] . " (manage_donations): " . $conn->error);
            $term['item_summary_text'] = 'Erro ao preparar resumo.';
        }
        $approved_terms[] = $term;
    }
} else {
    error_log("Error fetching approved donation terms (manage_donations): " . $conn->error);
    $page_error_message = "Erro ao carregar os termos de doação aprovados. Tente novamente mais tarde.";
}

require_once 'templates/header.php';
?>

<div class="container">
    <h2>Termos de Doação Aprovados</h2>

    <?php if (!empty($page_error_message)): ?>
        <p class="error-message"><?php echo htmlspecialchars($page_error_message); ?></p>
    <?php endif; ?>

    <?php // Display any session messages (e.g., from other actions)
    if (isset($_SESSION['page_message'])): ?>
        <p class="<?php echo (isset($_SESSION['page_message_type']) && $_SESSION['page_message_type'] == 'success') ? 'success-message' : 'error-message'; ?>">
            <?php echo htmlspecialchars($_SESSION['page_message']); ?>
        </p>
        <?php
        unset($_SESSION['page_message']);
        unset($_SESSION['page_message_type']);
        ?>
    <?php endif; ?>

    <?php if (empty($approved_terms) && empty($page_error_message)): ?>
        <p>Nenhum termo de doação aprovado encontrado no momento.</p>
    <?php elseif (!empty($approved_terms)): ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID do Termo</th>
                    <th>Data da Doação</th>
                    <th>Instituição</th>
                    <th>Responsável (Termo)</th>
                    <th>Registrado Por (Usuário)</th>
                    <th>Resumo dos Itens</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($approved_terms as $term): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($term['term_id']); ?></td>
                        <td>
                            <?php
                            $donation_datetime_str = $term['donation_date'] . ' ' . $term['donation_time'];
                            // Ensure time part is correctly formatted if it's just H:i
                            if (strlen($term['donation_time']) == 5) {
                                $donation_datetime_str .= ':00'; // Append seconds if missing
                            }
                            $donation_datetime = DateTime::createFromFormat('Y-m-d H:i:s', $donation_datetime_str);
                            if ($donation_datetime) {
                                echo htmlspecialchars($donation_datetime->format('d/m/Y H:i'));
                            } else {
                                // Fallback if time is not perfect or missing, just show date
                                $donation_date_fallback = DateTime::createFromFormat('Y-m-d', $term['donation_date']);
                                if ($donation_date_fallback) {
                                    echo htmlspecialchars($donation_date_fallback->format('d/m/Y'));
                                } else {
                                    echo 'Data Inválida (' . htmlspecialchars($donation_datetime_str) . ')'; // Show problematic string for debug
                                }
                            }
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars($term['institution_name']); ?></td>
                        <td><?php echo htmlspecialchars($term['responsible_donation']); ?></td>
                        <td><?php echo htmlspecialchars($term['registered_by_username'] ?? 'N/A'); ?></td>
                        <td><?php echo $term['item_summary_text']; // Already htmlspecialchars'd during creation ?></td>
                        <td class="actions-cell">
                            <a href="view_donation_term_page.php?term_id=<?php echo $term['term_id']; ?>"
                               class="button-secondary">Ver Termo</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php
require_once 'templates/footer.php';
?>
