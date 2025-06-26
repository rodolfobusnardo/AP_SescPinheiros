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
            $successMessage = 'Item cadastrado com sucesso!
Código de Barras: <strong>' . htmlspecialchars($_GET['barcode']) . '</strong>';
        } elseif ($_GET['message'] == 'itemupdated') {
            $successMessage = 'Item atualizado com sucesso!';
        } elseif ($_GET['message'] == 'itemdeleted') {
            $successMessage = 'Item excluído com sucesso!';
        }
        if (!empty($successMessage)) {
            echo '<p class="success-message">' .
$successMessage . '</p>';
        }
    }

    if (isset($_GET['error'])) {
        $error_map = [
            'deletefailed' => 'Erro ao tentar excluir o item.',
        ];
        $error_key = $_GET['error'];
        $errorMessage = $error_map[$error_key] ?? 'Ocorreu um erro desconhecido (' . htmlspecialchars($error_key) . ').';
        echo '<p class="error-message">' .
$errorMessage . '</p>';
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
          
                        <option value="<?php echo htmlspecialchars($category['id']); ?>" <?php echo (isset($_GET['filter_category_id']) && $_GET['filter_category_id'] == $category['id']) ?
'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['name']);
?>
                        </option>
                    <?php endforeach;
?>
                </select>
            </div>
            <div>
                <label for="filter_status">Status do Item:</label>
                <select id="filter_status" name="filter_status">
                    <option value="" <?php 
echo (!isset($_GET['filter_status']) || $_GET['filter_status'] == '') ? 'selected' : '';
?>>Todos os Status</option>
                    <option value="Pendente" <?php echo (isset($_GET['filter_status']) && $_GET['filter_status'] == 'Pendente') ?
'selected' : ''; ?>>Pendente</option>
                    <option value="Devolvido" <?php echo (isset($_GET['filter_status']) && $_GET['filter_status'] == 'Devolvido') ?
'selected' : ''; ?>>Devolvido</option>
                    <option value="Doado" <?php echo (isset($_GET['filter_status']) && $_GET['filter_status'] == 'Doado') ?
'selected' : ''; ?>>Doado (Em breve)</option>
                </select>
            </div>
        </div>
        <div class="filter-row">
            <div>
                <label for="filter_location_id">Local:</label>
                <select id="filter_location_id" name="filter_location_id">
   
                    <option value="">Todos os Locais</option>
                    <?php foreach ($filter_locations as $location): ?>
                        <option value="<?php echo htmlspecialchars($location['id']); ?>" <?php echo (isset($_GET['filter_location_id']) && $_GET['filter_location_id'] == $location['id']) ?
'selected' : ''; ?>>
                            <?php echo htmlspecialchars($location['name']);
?>
                        </option>
                    <?php endforeach;
?>
                </select>
            </div>
            <div>
                <label for="filter_days_waiting">Tempo Aguardando:</label>
                <select id="filter_days_waiting" name="filter_days_waiting">
                    <option value="">Qualquer</option>
   
                    <option value="0-7" <?php echo (isset($_GET['filter_days_waiting']) && $_GET['filter_days_waiting'] == '0-7') ?
'selected' : ''; ?>>0-7 dias</option>
                    <option value="8-30" <?php echo (isset($_GET['filter_days_waiting']) && $_GET['filter_days_waiting'] == '8-30') ?
'selected' : ''; ?>>8-30 dias</option>
                    <option value="31-59" <?php echo (isset($_GET['filter_days_waiting']) && $_GET['filter_days_waiting'] == '31-59') ?
'selected' : ''; ?>>31-59 dias</option>
                    <option value="60-9999" <?php echo (isset($_GET['filter_days_waiting']) && $_GET['filter_days_waiting'] == '60-9999') ?
'selected' : ''; ?>>60+ dias</option>
                </select>
            </div>
        </div>
        <div class="filter-row">
            <div>
                <label for="filter_found_date_start">Achado de (data):</label>
                <input type="date" id="filter_found_date_start" name="filter_found_date_start" 
value="<?php echo htmlspecialchars($_GET['filter_found_date_start'] ?? ''); ?>">
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
    <hr> <?php if ($current_user_is_admin): ?>
    <div class="action-bar">
        <input type="checkbox" id="selectAllCheckbox">
        <label for="selectAllCheckbox">Selecionar Todos Visíveis</label>
       
        <button id="devolverButton" class="button-secondary" disabled>Devolver Selecionados</button>
        <button id="doarButton" class="button-secondary" disabled>Doar Selecionados</button>
    </div>
    <?php endif;
?>

    <div id="itemListContainer">
        <?php if (!empty($items)): ?>
            <table class="admin-table">
                <colgroup>
                    <col style="width: 30px;">   <col style="width: 50px;">   <col style="width: 120px;">  <col style="width: 15%;">   <col style="width: 12%; ">   <col style="width: 150px;">  <col style="width: 10%;">   <col style="width: 15%;">   <col style="width: 100px;">  <col style="width: 120px;">  <col style="width: 12%;">   <col style="width: 170px;">  </colgroup>
                <thead>
                    <tr>
                        <th class="checkbox-cell"></th> <th>ID</th>
                        <th>Status</th>
                        <th class="truncate-text">Nome</th>
                        <th class="truncate-text">Cód. Barras</th>
                        <th>Imagem C.B.</th>
                        <th class="truncate-text">Categoria</th>
                        <th class="truncate-text">Local Encontrado</th>
                        
                        <th>Data Achado</th>
                        <th>Dias Aguardando</th> <th class="truncate-text">Registrado por</th>
                        <th>Ações</th>
                    
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                   
                            <td class="checkbox-cell"><input type="checkbox" class="item-checkbox" name="selected_items[]" value="<?php echo htmlspecialchars($item['id']); ?>"></td>
                            <td><?php echo htmlspecialchars($item['id']);
?></td>
                            <td class="status-cell">
                                <?php
                                $raw_status = $item['status'];
                                $status_text_display = htmlspecialchars($raw_status);

                                $class_name_normalized = $raw_status;
                                $class_name_normalized = strtolower($class_name_normalized);
                                $char_map_simple = [
                                    'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
    
                                    'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
                                    'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
          
                                    'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
                                    'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
             
                                    'ç' => 'c', 
                                    'ñ' => 'n',
                                
                                ];
                                $class_name_normalized = strtr($class_name_normalized, $char_map_simple);
                                $class_name_normalized = preg_replace('/[^a-z0-9\s-]/', '', $class_name_normalized);
                                $class_name_normalized = preg_replace('/[\s-]+/', '-', $class_name_normalized);
                                $status_class_name_final = trim($class_name_normalized, '-');
?>
                                <span class="item-status status-<?php echo $status_class_name_final; ?>">
                                    <?php echo $status_text_display;
?>
                                </span>
                            </td>
                            <td class="truncate-text"><?php echo htmlspecialchars($item['name']);
?></td>
                            <td class="truncate-text"><?php echo htmlspecialchars($item['barcode'] ?? 'N/A');
?></td>
                            <td> <?php if (!empty($item['barcode'])): ?>
                                 
                                    <svg id="barcode-<?php echo htmlspecialchars($item['id']); ?>" class="barcode-image"></svg>
                                <?php else: ?>
                                    N/A
                      
                                <?php endif;
?>
                            </td>
                            <td><?php echo htmlspecialchars($item['category_name'] ?? 'N/A');
?> (<?php echo htmlspecialchars($item['category_code'] ?? 'N/A'); ?>)</td>
                            <td><?php echo htmlspecialchars($item['location_name'] ?? 'N/A');
?></td>
                            <td><?php echo htmlspecialchars(date("d/m/Y", strtotime($item['found_date'])));
?></td>
                            <td><?php echo htmlspecialchars($item['days_waiting'] ?? '0');
?> dias</td>
                            <td class="truncate-text">
                                <?php
                                $display_name = !empty(trim($item['registered_by_full_name'] ?? ''))
  
                                                ?
$item['registered_by_full_name']
                                                : $item['registered_by_username'];
echo htmlspecialchars($display_name ?? 'Usuário Removido');
                                ?>
                            </td>
                            <td class="actions-cell home-actions-cell">
                                <div class="actions-wrapper">
                                    <button type="button" class="button-details button-details-home" data-description="<?php 
echo htmlspecialchars($item['description'] ?? ''); ?>" data-itemid="<?php echo htmlspecialchars($item['id']); ?>" title="Ver Descrição">Ver Descrição</button>

                                    <?php if ($item['status'] === 'Pendente' && $current_user_is_admin): ?>
                                        <a href="admin/edit_item_page.php?id=<?php echo htmlspecialchars($item['id']); ?>" class="button-edit" title="Editar">Editar</a>
        
                                        <form action="admin/delete_item_handler.php" method="POST" onsubmit="return confirm('Tem certeza que deseja excluir este item?');" class="delete-form">
                                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($item['id']); ?>">
                                            <button type="submit" class="button-delete" title="Excluir">Excluir</button>
                                        </form>
                                    <?php elseif ($item['status'] === 'Devolvido' && !empty($item['devolution_document_id'])): ?>
                                        
                                        <a href="manage_devolutions.php?view_id=<?php echo htmlspecialchars($item['devolution_document_id']); ?>" class="button-secondary button-ver-termo" title="Visualizar Termo de Devolução">Ver Termo</a>
          
                                    <?php elseif ($item['status'] === 'Doado' && !empty($item['donation_document_id'])): ?>
                                        <a href="manage_donations.php?view_id=<?php echo htmlspecialchars($item['donation_document_id']);
?>" class="button-secondary button-ver-termo" title="Visualizar Termo de Doação">Ver Termo Doação</a>
                
                                    <?php else: ?>
                                        <?php
                                        $canShowPlaceholder = true;
                                        if ($item['status'] === 'Pendente' && !$current_user_is_admin) {
                                            $canShowPlaceholder = false;
                                        } else if ($item['status'] === 'Devolvido' && empty($item['devolution_document_id'])) {
 
                                            echo '<span style="color: #999; font-size:0.9em;">Termo Indisponível</span>';
                                            $canShowPlaceholder = false;
                                        } else if ($item['status'] === 'Doado' && empty($item['donation_document_id'])) {
             
                                            echo '<span style="color: #999; font-size:0.9em;">Termo Indisponível</span>';
                                             $canShowPlaceholder = false;
                                        }

                    
                                        if ($canShowPlaceholder && $item['status'] !== 'Pendente' && $item['status'] !== 'Devolvido' && $item['status'] !== 'Doado') {
                
                                            echo '<span style="color: #999; font-size:0.9em;">---</span>';
                                        }
                                        ?>
                            
                                    <?php endif; ?>
                                </div>
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
    const modalHome = document.getElementById('itemDetailModal');
    const modalTextElementHome = document.getElementById('modalItemDescriptionTextHome');

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
                modalTextElementHome.textContent = 
'Sem Detalhes de descrição.';
            }
            modalHome.style.display = 'block';
        });
    });
    closeButtonHome.onclick = function() {
        modalHome.style.display = 'none';
    }

    window.addEventListener('click', function(event) {
        if (event.target == modalHome) {
            modalHome.style.display = 'none';
        }
    });

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
            <?php endif;
?>
        <?php endforeach; ?>
    <?php endif; ?>
});
</script>

<?php require_once 'templates/footer.php';
?>