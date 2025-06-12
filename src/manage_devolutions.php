<?php
require_once 'auth.php';
require_once 'db_connect.php';

start_secure_session();
require_admin('home.php');

// Fetch Unidade Name from settings
$unidade_nome_setting = 'N/A'; // Default value
if (isset($conn)) { // Check if $conn is already set
    $stmt_settings = $conn->prepare("SELECT unidade_nome FROM settings WHERE config_id = 1");
    if ($stmt_settings) {
        $stmt_settings->execute();
        $result_settings = $stmt_settings->get_result();
        if ($result_settings->num_rows > 0) {
            $setting_row = $result_settings->fetch_assoc();
            if (!empty($setting_row['unidade_nome'])) {
                $unidade_nome_setting = $setting_row['unidade_nome'];
            }
        }
        $stmt_settings->close();
    } else {
        error_log("In manage_devolutions.php: Failed to prepare statement to fetch unidade_nome from settings: " . $conn->error);
    }
} else {
    error_log("In manage_devolutions.php: Database connection \$conn is not available for fetching settings.");
}

$view_id = null;
$detailed_document = null;
$detailed_view_error = '';
$is_detail_view_mode = false;
$devolution_documents = []; // Initialize list
$system_users = []; // Initialize for filters

if (isset($_GET['view_id'])) {
    $is_detail_view_mode = true;
    $view_id = filter_var($_GET['view_id'], FILTER_VALIDATE_INT);
    if ($view_id) {
        // SQL to fetch detailed document information
        $sql_detail = "SELECT
                            dd.*,
                            i.name as item_name,
                            i.barcode as item_barcode,
                            i.description as item_description,
                            i.found_date as item_found_date,
                            cat.name as item_category_name,
                            loc.name as item_location_name,
                            reg_user.username as item_registered_by_username, /* User who registered the item */
                            resp_user.username as responsible_user_name /* User who processed devolution */
                       FROM devolution_documents dd
                       JOIN items i ON dd.item_id = i.id
                       JOIN users resp_user ON dd.returned_by_user_id = resp_user.id
                       LEFT JOIN categories cat ON i.category_id = cat.id
                       LEFT JOIN locations loc ON i.location_id = loc.id
                       LEFT JOIN users reg_user ON i.user_id = reg_user.id /* If you want to show who originally registered item */
                       WHERE dd.id = ?";
        $stmt_detail = $conn->prepare($sql_detail);
        if ($stmt_detail) {
            $stmt_detail->bind_param("i", $view_id);
            $stmt_detail->execute();
            $result_detail = $stmt_detail->get_result();
            if ($result_detail && $result_detail->num_rows > 0) {
                $detailed_document = $result_detail->fetch_assoc();
            } else {
                $detailed_view_error = "Termo de devolução não encontrado (ID: " . htmlspecialchars($view_id) . ").";
            }
            $stmt_detail->close();
        } else {
            error_log("Manage Devolutions (Detail View): Prepare failed: " . $conn->error);
            $detailed_view_error = "Erro ao preparar a busca pelo termo de devolução.";
        }
    } else {
        $detailed_view_error = "ID do termo de devolução inválido.";
    }
} else { // Not in detail view mode, so fetch list and filter users
    // Fetch system_users for filters
    $sql_users_list = "SELECT id, username FROM users ORDER BY username ASC";
    $result_sys_users = $conn->query($sql_users_list);
    if ($result_sys_users) {
        while ($row_su = $result_sys_users->fetch_assoc()) {
            $system_users[] = $row_su;
        }
    }

    // Base SQL to fetch devolution documents list
    $sql_base = "SELECT
                    dd.id as devolution_id,
                    dd.devolution_timestamp,
                    dd.owner_name,
                    dd.signature_image_path,
                    i.name as item_name,
                    i.barcode as item_barcode,
                    u.username as responsible_user_name
                 FROM devolution_documents dd
                 JOIN items i ON dd.item_id = i.id
                 JOIN users u ON dd.returned_by_user_id = u.id"; // ORDER BY will be added after conditions

    $sql_conditions = [];
    $sql_params_types = "";
    $sql_params_values = [];

    // Item Name Filter
    if (!empty($_GET['filter_item_name'])) {
        $sql_conditions[] = "i.name LIKE ?";
        $sql_params_types .= "s";
        $sql_params_values[] = "%" . trim($_GET['filter_item_name']) . "%";
    }
    // Owner Name Filter
    if (!empty($_GET['filter_owner_name'])) {
        $sql_conditions[] = "dd.owner_name LIKE ?";
        $sql_params_types .= "s";
        $sql_params_values[] = "%" . trim($_GET['filter_owner_name']) . "%";
    }
    // User ID Filter
    if (!empty($_GET['filter_user_id'])) {
        $user_id_filter_val = filter_var($_GET['filter_user_id'], FILTER_VALIDATE_INT);
        if ($user_id_filter_val) {
            $sql_conditions[] = "dd.returned_by_user_id = ?";
            $sql_params_types .= "i";
            $sql_params_values[] = $user_id_filter_val;
        }
    }
    // Date Start Filter
    if (!empty($_GET['filter_date_start'])) {
        if (preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $_GET['filter_date_start'])) {
            $sql_conditions[] = "DATE(dd.devolution_timestamp) >= ?";
            $sql_params_types .= "s";
            $sql_params_values[] = $_GET['filter_date_start'];
        }
    }
    // Date End Filter
    if (!empty($_GET['filter_date_end'])) {
        if (preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $_GET['filter_date_end'])) {
            $sql_conditions[] = "DATE(dd.devolution_timestamp) <= ?";
            $sql_params_types .= "s";
            $sql_params_values[] = $_GET['filter_date_end'];
        }
    }

    $sql_query_final = $sql_base;
    if (!empty($sql_conditions)) {
        $sql_query_final .= " WHERE " . implode(" AND ", $sql_conditions);
    }
    $sql_query_final .= " ORDER BY dd.devolution_timestamp DESC";

    $stmt_list = $conn->prepare($sql_query_final);
    if ($stmt_list) {
        if (!empty($sql_params_values)) {
            $stmt_list->bind_param($sql_params_types, ...$sql_params_values);
        }
        $stmt_list->execute();
        $result_list = $stmt_list->get_result();
        if ($result_list) {
            while ($row = $result_list->fetch_assoc()) {
                $devolution_documents[] = $row;
            }
        } else {
            error_log("Manage Devolutions (List View): Execute failed: " . $stmt_list->error);
            $_SESSION['page_error_message'] = "Erro ao executar a busca por termos de devolução.";
        }
        $stmt_list->close();
    } else {
        error_log("Manage Devolutions (List View): Prepare failed: " . $conn->error);
        $_SESSION['page_error_message'] = "Erro ao preparar a busca por termos de devolução.";
    }
}

require_once 'templates/header.php';
?>

<div class="container admin-container">
    <?php if (!$is_detail_view_mode): ?>
        <h2>Termos de Devolução Registrados</h2>

        <?php
        if (isset($_SESSION['page_error_message'])) {
            echo '<p class="error-message">' . htmlspecialchars($_SESSION['page_error_message']) . '</p>';
            unset($_SESSION['page_error_message']);
        }
        if (isset($_GET['success_message'])) { // For potential future use
            echo '<p class="success-message">' . htmlspecialchars($_GET['success_message']) . '</p>';
        }
        ?>

        <!-- Filter Form -->
        <form method="GET" action="manage_devolutions.php" class="form-filters">
            <h4>Filtros</h4>
            <div class="filter-row">
                <div>
                    <label for="filter_item_name">Nome do Item (contém):</label>
                    <input type="text" id="filter_item_name" name="filter_item_name" value="<?php echo htmlspecialchars($_GET['filter_item_name'] ?? ''); ?>">
                </div>
                <div>
                    <label for="filter_owner_name">Nome do Proprietário (contém):</label>
                    <input type="text" id="filter_owner_name" name="filter_owner_name" value="<?php echo htmlspecialchars($_GET['filter_owner_name'] ?? ''); ?>">
                </div>
            </div>
            <div class="filter-row">
                <div>
                    <label for="filter_user_id">Responsável (Usuário):</label>
                    <select id="filter_user_id" name="filter_user_id">
                        <option value="">Todos</option>
                        <?php foreach ($system_users as $user_filter): ?>
                            <option value="<?php echo htmlspecialchars($user_filter['id']); ?>" <?php echo (isset($_GET['filter_user_id']) && $_GET['filter_user_id'] == $user_filter['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user_filter['username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="filter-row">
                <div>
                    <label for="filter_date_start">Data Devolução (De):</label>
                    <input type="date" id="filter_date_start" name="filter_date_start" value="<?php echo htmlspecialchars($_GET['filter_date_start'] ?? ''); ?>">
                </div>
                <div>
                    <label for="filter_date_end">Data Devolução (Até):</label>
                    <input type="date" id="filter_date_end" name="filter_date_end" value="<?php echo htmlspecialchars($_GET['filter_date_end'] ?? ''); ?>">
                </div>
            </div>
            <div class="filter-buttons">
                <button type="submit" class="button-filter">Aplicar Filtros</button>
                <a href="manage_devolutions.php" class="button-filter-clear" style="margin-left:10px;">Limpar Filtros</a>
            </div>
        </form>

        <?php if (!empty($devolution_documents)): ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID Devol.</th>
                    <th>Data/Hora Devol.</th>
                    <th>Item Nome</th>
                    <th>Item Cód. Barras</th>
                    <th>Proprietário</th>
                    <th>Responsável (Usuário)</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($devolution_documents as $doc): ?>
                <tr>
                    <td><?php echo htmlspecialchars($doc['devolution_id']); ?></td>
                    <td><?php echo htmlspecialchars(date("d/m/Y H:i", strtotime($doc['devolution_timestamp']))); ?></td>
                    <td><?php echo htmlspecialchars($doc['item_name']); ?></td>
                    <td><?php echo htmlspecialchars($doc['item_barcode'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($doc['owner_name']); ?></td>
                    <td><?php echo htmlspecialchars($doc['responsible_user_name']); ?></td>
                    <td>
                        <a href="manage_devolutions.php?view_id=<?php echo htmlspecialchars($doc['devolution_id']); ?>" class="button-secondary">Ver Termo</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php elseif (empty($_SESSION['page_error_message'])): // Only show "no terms found" if there wasn't a page error ?>
        <p class="info-message">Nenhum termo de devolução encontrado.</p>
        <?php endif; ?>

    <?php else: // We are in detail view mode ($is_detail_view_mode is true) ?>
        <div id="detailedViewSection" class="detailed-view-section">
            <h3>Detalhes do Termo de Devolução #<?php echo htmlspecialchars($view_id ?? ''); ?></h3>

            <?php if (!empty($detailed_view_error)): ?>
                <p class="error-message"><?php echo htmlspecialchars($detailed_view_error); ?></p>
                <p><a href="manage_devolutions.php" class="button-secondary">Voltar à Lista</a></p>
            <?php elseif ($detailed_document): ?>
                <h4>Dados do Item Devolvido</h4>
                <p class="compact-data-line">
                    <span class="term-data-group"><strong class="term-label">Item:</strong> <span class="term-value"><?php echo htmlspecialchars($detailed_document['item_name']); ?></span></span>
                    <span class="term-separator"> | </span>
                    <span class="term-data-group"><strong class="term-label">Categoria:</strong> <span class="term-value"><?php echo htmlspecialchars($detailed_document['item_category_name'] ?? 'N/A'); ?></span></span>
                    <span class="term-separator"> | </span>
                    <span class="term-data-group"><strong class="term-label">Local Encontrado:</strong> <span class="term-value"><?php echo htmlspecialchars($detailed_document['item_location_name'] ?? 'N/A'); ?></span></span>
                </p>
                <p><strong>Data que foi Achado:</strong> <?php echo htmlspecialchars(date("d/m/Y", strtotime($detailed_document['item_found_date']))); ?></p>
                <p><strong>Descrição Original:</strong> <?php echo nl2br(htmlspecialchars($detailed_document['item_description'] ?? 'N/A')); ?></p>
                <p><strong>Registrado Originalmente por:</strong> <?php echo htmlspecialchars($detailed_document['item_registered_by_username'] ?? 'N/A'); ?></p>

                <h4>Dados da Devolução</h4>
                <p class="compact-data-line">
                    <span class="term-data-group"><strong class="term-label">Responsável:</strong> <span class="term-value"><?php echo htmlspecialchars($detailed_document['responsible_user_name']); ?></span></span>
                    <span class="term-separator"> | </span>
                    <span class="term-data-group"><strong class="term-label">Data/Hora:</strong> <span class="term-value"><?php echo htmlspecialchars(date("d/m/Y H:i:s", strtotime($detailed_document['devolution_timestamp']))); ?></span></span>
                </p>

                <h4>Dados do Proprietário</h4>
                <p class="compact-data-line">
                    <span class="term-data-group"><strong class="term-label">Nome:</strong> <span class="term-value"><?php echo htmlspecialchars($detailed_document['owner_name']); ?></span></span>
                    <span class="term-separator"> | </span>
                    <span class="term-data-group"><strong class="term-label">Telefone:</strong> <span class="term-value"><?php echo htmlspecialchars($detailed_document['owner_phone'] ?? 'N/A'); ?></span></span>
                    <span class="term-separator"> | </span>
                    <span class="term-data-group"><strong class="term-label">Credencial/Doc:</strong> <span class="term-value"><?php echo htmlspecialchars($detailed_document['owner_credential_number'] ?? 'N/A'); ?></span></span>
                </p>
                <p><strong class="term-label">Endereço:</strong> <span class="term-value"><?php echo nl2br(htmlspecialchars($detailed_document['owner_address'] ?? 'N/A')); ?></span></p>

                <div class="devolution-declaration" style="margin-top: 20px; margin-bottom: 20px; padding: 15px; border: 1px solid #eee; background-color: #f9f9f9; text-align: justify;">
                    <h5 style="text-align: center; font-weight: bold; margin-bottom: 10px;">Declaração de Reconhecimento de Propriedade</h5>
                    <p style="font-size: 0.9em; line-height: 1.5; margin-bottom: 10px;">
                        Declaro, para os devidos fins, que reconheço o item descrito neste termo como de minha propriedade e que o mesmo me foi devolvido pelo setor de Achados e Perdidos - Sesc <?php echo htmlspecialchars($unidade_nome_setting); ?>, após conferência e identificação.
                    </p>
                </div>

                <h4>Assinatura</h4>
                <?php if (!empty($detailed_document['signature_image_path']) && file_exists($detailed_document['signature_image_path'])): ?>
                    <img src="<?php echo htmlspecialchars($detailed_document['signature_image_path']); ?>" class="signature-image" alt="Assinatura do Proprietário">
                <?php elseif (!empty($detailed_document['signature_image_path'])): ?>
                    <p class="error-message">Imagem da assinatura não encontrada em: <?php echo htmlspecialchars($detailed_document['signature_image_path']); ?></p>
                <?php else: ?>
                    <p><em>Nenhuma assinatura registrada para este termo.</em></p>
                <?php endif; ?>

                <div style="margin-top: 20px;">
                    <button onclick="window.print();" class="button-primary">Imprimir Termo</button>
                    <a href="manage_devolutions.php" class="button-secondary" style="margin-left:10px;">Voltar à Lista</a>
                </div>
            <?php else: ?>
                <p class="info-message">Não foi possível carregar os detalhes do termo de devolução.</p>
                <p><a href="manage_devolutions.php" class="button-secondary">Voltar à Lista</a></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php
require_once 'templates/footer.php';
?>
