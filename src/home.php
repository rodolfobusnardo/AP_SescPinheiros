<?php
require_once 'auth.php';
require_once 'db_connect.php';

require_login();

$filter_categories = [];
$sql_filter_cats = "SELECT id, name FROM categories ORDER BY name ASC";
$result_filter_cats = $conn->query($sql_filter_cats);
if ($result_filter_cats && $result_filter_cats->num_rows > 0) {
    while ($row_fc = $result_filter_cats->fetch_assoc()) {
        $filter_categories[] = $row_fc;
    }
}

$filter_locations = [];
$sql_filter_locs = "SELECT id, name FROM locations ORDER BY name ASC";
$result_filter_locs = $conn->query($sql_filter_locs);
if ($result_filter_locs && $result_filter_locs->num_rows > 0) {
    while ($row_fl = $result_filter_locs->fetch_assoc()) {
        $filter_locations[] = $row_fl;
    }
}

require_once 'get_items_handler.php';

$current_user_is_admin = is_admin();

require_once 'templates/header.php';
?>

<div class="container home-container">
    <h2>Itens Encontrados</h2>

    <?php
    // Display success/error messages
    if (isset($_GET['message'])) {
        $successMessage = '';
        if ($_GET['message'] == 'itemadded' && isset($_GET['barcode'])) {
            $successMessage = 'Item cadastrado com sucesso! Código de Barras: <strong>' . htmlspecialchars($_GET['barcode']) . '</strong>';
        } elseif ($_GET['message'] == 'itemupdated') {
            $successMessage = 'Item atualizado com sucesso!';
        } elseif ($_GET['message'] == 'itemdeleted') {
            $successMessage = 'Item excluído com sucesso!';
        }
        if (!empty($successMessage)) {
            echo '<p class="success-message">' . $successMessage . '</p>';
        }
    }

    if (isset($_GET['error'])) {
        $error_map = [
            'deletefailed' => 'Erro ao tentar excluir o item.',
        ];
        $error_key = $_GET['error'];
        $errorMessage = $error_map[$error_key] ?? 'Ocorreu um erro desconhecido (' . htmlspecialchars($error_key) . ').';
        echo '<p class="error-message">' . $errorMessage . '</p>';
    }
    ?>

    <form id="filterForm" class="form-filters">
        <div class="filter-row">
            <div>
                <label for="filter_item_name">Nome do Item (contém):</label>
                <input type="text" id="filter_item_name" name="filter_item_name" value="<?php echo htmlspecialchars($_GET['filter_item_name'] ?? ''); ?>" placeholder="Digite parte do nome...">
            </div>
            <div>
                <label for="filter_category_id">Categoria:</label>
                <select id="filter_category_id" name="filter_category_id">
                    <option value="">Todas as Categorias</option>
                    <?php foreach ($filter_categories as $category): ?>
                        <option value="<?php echo htmlspecialchars($category['id']); ?>" <?php echo (isset($_GET['filter_category_id']) && $_GET['filter_category_id'] == $category['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filter_status">Status do Item:</label>
                <select id="filter_status" name="filter_status">
                    <option value="" <?php echo (!isset($_GET['filter_status']) || $_GET['filter_status'] == '') ? 'selected' : ''; ?>>Todos os Status</option>
                    <option value="Pendente" <?php echo (isset($_GET['filter_status']) && $_GET['filter_status'] == 'Pendente') ? 'selected' : ''; ?>>Pendente</option>
                    <option value="Devolvido" <?php echo (isset($_GET['filter_status']) && $_GET['filter_status'] == 'Devolvido') ? 'selected' : ''; ?>>Devolvido</option>
                    <option value="Doado" <?php echo (isset($_GET['filter_status']) && $_GET['filter_status'] == 'Doado') ? 'selected' : ''; ?>>Doado (Em breve)</option>
                </select>
            </div>
        </div>
        <div class="filter-row">
            <div>
                <label for="filter_location_id">Local:</label>
                <select id="filter_location_id" name="filter_location_id">
                    <option value="">Todos os Locais</option>
                    <?php foreach ($filter_locations as $location): ?>
                        <option value="<?php echo htmlspecialchars($location['id']); ?>" <?php echo (isset($_GET['filter_location_id']) && $_GET['filter_location_id'] == $location['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($location['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filter_days_waiting">Tempo Aguardando:</label>
                <select id="filter_days_waiting" name="filter_days_waiting">
                    <option value="">Qualquer</option>
                    <option value="0-7" <?php echo (isset($_GET['filter_days_waiting']) && $_GET['filter_days_waiting'] == '0-7') ? 'selected' : ''; ?>>0-7 dias</option>
                    <option value="8-30" <?php echo (isset($_GET['filter_days_waiting']) && $_GET['filter_days_waiting'] == '8-30') ? 'selected' : ''; ?>>8-30 dias</option>
                    <option value="31-59" <?php echo (isset($_GET['filter_days_waiting']) && $_GET['filter_days_waiting'] == '31-59') ? 'selected' : ''; ?>>31-59 dias</option>
                    <option value="60-9999" <?php echo (isset($_GET['filter_days_waiting']) && $_GET['filter_days_waiting'] == '60-9999') ? 'selected' : ''; ?>>60+ dias</option>
                </select>
            </div>
        </div>
        <div class="filter-row">
            <div>
                <label for="filter_found_date_start">Achado de (data):</label>
                <input type="date" id="filter_found_date_start" name="filter_found_date_start" value="<?php echo htmlspecialchars($_GET['filter_found_date_start'] ?? ''); ?>">
            </div>
            <div>
                <label for="filter_found_date_end">Até (data):</label>
                <input type="date" id="filter_found_date_end" name="filter_found_date_end" value="<?php echo htmlspecialchars($_GET['filter_found_date_end'] ?? ''); ?>">
            </div>
        </div>

        <div class="filter-buttons">
            <button type="submit" class="button-filter">Aplicar Filtros</button>
            <button type="reset" id="clearFiltersButton" class="button-filter-clear">Limpar Filtros</button>
        </div>
    </form>
    <hr> <!-- Inline style removed, assuming general or .admin-container hr style applies -->

    <?php if ($current_user_is_admin): ?>
    <div class="action-bar">
        <input type="checkbox" id="selectAllCheckbox">
        <label for="selectAllCheckbox">Selecionar Todos Visíveis</label>
        <button id="devolverButton" class="button-primary" disabled>Devolver Selecionados</button>
        <button id="doarButton" class="button-secondary" disabled>Doar Selecionados</button>
    </div>
    <?php endif; ?>

    <div id="itemListContainer">
        <?php if (!empty($items)): ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th></th> <!-- For Checkboxes -->
                        <th>ID</th>
                        <th>Status</th>
                        <th>Nome</th>
                        <th>Cód. Barras</th>
                        <th>Imagem C.B.</th>
                        <th>Categoria</th>
                        <th>Local Encontrado</th>
                        <th>Data Achado</th>
                        <th>Dias Aguardando</th>
                        <th>Registrado por</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><input type="checkbox" class="item-checkbox" name="selected_items[]" value="<?php echo htmlspecialchars($item['id']); ?>"></td>
                            <td><?php echo htmlspecialchars($item['id']); ?></td>
                            <td>
                                <span class="item-status status-<?php echo strtolower(htmlspecialchars($item['status'])); ?>">
                                    <?php echo htmlspecialchars($item['status']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                            <td><?php echo htmlspecialchars($item['barcode'] ?? 'N/A'); ?></td>
                            <td>
                                <?php if (!empty($item['barcode'])): ?>
                                    <svg id="barcode-<?php echo htmlspecialchars($item['id']); ?>" class="barcode-image"></svg>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($item['category_name'] ?? 'N/A'); ?> (<?php echo htmlspecialchars($item['category_code'] ?? 'N/A'); ?>)</td>
                            <td><?php echo htmlspecialchars($item['location_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(date("d/m/Y", strtotime($item['found_date']))); ?></td>
                            <td><?php echo htmlspecialchars($item['days_waiting'] ?? '0'); ?> dias</td>
                            <td>
                                <?php echo htmlspecialchars($item['registered_by_username'] ?? 'Usuário Removido'); ?>
                                <?php if (isset($item['registered_at'])): ?>
                                    <br><small>em <?php echo htmlspecialchars(date("d/m/Y H:i", strtotime($item['registered_at']))); ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="actions-cell home-actions-cell">
                                <button type="button" class="button-details button-details-home" data-description="<?php echo htmlspecialchars($item['description'] ?? ''); ?>" data-itemid="<?php echo htmlspecialchars($item['id']); ?>" title="Ver Descrição">Ver Descrição</button>

                                <?php if ($item['status'] === 'Pendente' && $current_user_is_admin): ?>
                                    <div class="action-group-inline" style="margin-top:5px;">
                                        <a href="admin/edit_item_page.php?id=<?php echo htmlspecialchars($item['id']); ?>" class="button-edit" title="Editar" style="margin-right: 5px;">Editar</a>
                                        <form action="admin/delete_item_handler.php" method="POST" onsubmit="return confirm('Tem certeza que deseja excluir este item?');" style="display:inline-block;">
                                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($item['id']); ?>">
                                            <button type="submit" class="button-delete" title="Excluir">Excluir</button>
                                        </form>
                                    </div>
                                <?php elseif ($item['status'] === 'Devolvido' && !empty($item['devolution_document_id'])): ?>
                                    <div class="action-group-centered" style="margin-top:5px;">
                                         <a href="manage_devolutions.php?view_id=<?php echo htmlspecialchars($item['devolution_document_id']); ?>"
                                            class="button-secondary button-ver-termo"
                                            title="Visualizar Termo de Devolução">Ver Termo</a>
                                    </div>
                                <?php elseif ($item['status'] === 'Doado' && !empty($item['donation_document_id'])): ?>
                                    <div class="action-group-centered" style="margin-top:5px;">
                                        <a href="manage_donations.php?view_id=<?php echo htmlspecialchars($item['donation_document_id']); ?>"
                                           class="button-secondary button-ver-termo"
                                           title="Visualizar Termo de Doação">Ver Termo Doação</a>
                                    </div>
                                <?php endif; ?>
                                <?php // Fallback for items with status that don't have specific actions above and aren't Pendente for non-admin
                                    if ($item['status'] !== 'Pendente' && $item['status'] !== 'Devolvido' && ($item['status'] !== 'Doado' || empty($item['donation_document_id']))) {
                                        // Ensure non-admins viewing Pendente items don't see "---" if they only have "Ver Descrição"
                                        if (!($item['status'] === 'Pendente' && !$current_user_is_admin)) {
                                             // And for Devolvido items without a document_id by non-admins
                                            if(!($item['status'] === 'Devolvido' && empty($item['devolution_document_id']) && !$current_user_is_admin)){
                                                if($item['status'] === 'Devolvido' && empty($item['devolution_document_id'])){
                                                     echo '<div style="margin-top:5px; text-align:center;"><span style="color: #999; font-size:0.9em;">Termo Indisponível</span></div>';
                                                } else if ($item['status'] !== 'Pendente') { // Avoid "---" for non-admin on Pendente
                                                    // Check if any specific condition for Doado was met or not
                                                    $isDoadoWithAction = ($item['status'] === 'Doado' && !empty($item['donation_document_id']));
                                                    if (!$isDoadoWithAction && $item['status'] === 'Doado'){
                                                        // If it's 'Doado' but no specific action rendered (e.g. no document_id)
                                                        // You might want a specific message or button here like "Termo Doação Indisponível"
                                                        // For now, it will fall through if no other condition specific to 'Doado' is met
                                                    } else if ($item['status'] !== 'Doado'){ // Default placeholder if no other actions fit
                                                         echo '<div style="margin-top:5px; text-align:center;"><span style="color: #999; font-size:0.9em;">---</span></div>';
                                                    }
                                                }
                                            }
                                        }
                                    }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="info-message">Nenhum item encontrado com os filtros atuais.</p>
        <?php endif; ?>
    </div>
</div>

<script>
    const current_user_is_admin = <?php echo json_encode($current_user_is_admin); ?>;
    const initial_php_items = <?php echo json_encode($items); ?>;
</script>

<!-- Item Details Modal -->
<div id="itemDetailModal" class="modal" style="display: none;">
    <div class="modal-content">
        <span class="modal-close-button">&times;</span>
        <h3>Descrição do Item</h3>
        <p id="modalItemDescriptionTextHome"></p>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Modal JavaScript Logic for Home Page Description View
    const modalHome = document.getElementById('itemDetailModal'); // Existing modal ID
    const modalTextElementHome = document.getElementById('modalItemDescriptionTextHome'); // New P ID

    // Query for close button within the specific modalHome instance
    const closeButtonHome = modalHome.querySelector('.modal-close-button');

    if (!modalHome || !modalTextElementHome || !closeButtonHome) {
        console.error('Modal elements for home page description not found! Check IDs: itemDetailModal, modalItemDescriptionTextHome, .modal-close-button');
        return;
    }

    document.querySelectorAll('.button-details-home').forEach(button => {
        button.addEventListener('click', function() {
            const description = this.dataset.description;

            if (description && description.trim() !== '') {
                modalTextElementHome.textContent = description;
            } else {
                modalTextElementHome.textContent = 'Sem Detalhes de descrição.';
            }
            modalHome.style.display = 'block';
        });
    });

    closeButtonHome.onclick = function() {
        modalHome.style.display = 'none';
    }

    // Close modal if window is clicked outside of modal content
    window.addEventListener('click', function(event) {
        if (event.target == modalHome) {
            modalHome.style.display = 'none';
        }
    });

    // JsBarcode logic from manage_items, adapted for home.php if needed for items here
    // This was already present in home.php's footer via scripts.js, but if items are loaded dynamically,
    // ensure JsBarcode is called after items are in DOM.
    // The existing JsBarcode call in scripts.js should handle barcodes visible on initial load.
    // If filtering re-renders items and new barcodes appear, ensure JsBarcode is re-triggered.
    // For now, relying on existing JsBarcode in scripts.js for initially loaded items.
    // The original home.php already had JsBarcode logic in its main script block.
    // The items are loaded via PHP, so JsBarcode should be called once after DOM is ready.
    // The existing footer.php includes scripts.js which has its own DOMContentLoaded.
    // To avoid conflicts, it's better if all page-specific JS is self-contained or uses unique function names.
    // The current JsBarcode in home.php is:
    <?php if (!empty($items)): ?>
        <?php foreach ($items as $item): ?>
            <?php if (!empty($item['barcode'])): ?>
                try {
                    const barcodeElement = document.getElementById("barcode-<?php echo htmlspecialchars($item['id']); ?>");
                    if (barcodeElement) {
                        JsBarcode(barcodeElement, "<?php echo htmlspecialchars($item['barcode']); ?>", {
                            format: "CODE128",
                            lineColor: "#000",
                            width: 1.5,
                            height: 40,
                            displayValue: true,
                            fontSize: 10,
                            margin: 2
                        });
                    }
                } catch (e) {
                    console.error("Error generating barcode for item ID <?php echo htmlspecialchars($item['id']); ?>: ", e);
                }
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
});
</script>

<?php require_once 'templates/footer.php'; ?>
