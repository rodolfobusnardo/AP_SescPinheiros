<?php
require_once '../auth.php'; // Inclui start_secure_session() e funções de auth
require_once '../db_connect.php';


// start_secure_session(); // Chamado dentro de auth.php ou no início dele
require_admin('../index.php?error=unauthorized'); // Redireciona se não for admin

$item_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$item = null;
$categories = [];
$locations = [];
$error_message = ''; // Para erros no carregamento da página
$success_message = ''; // Para mensagens de sucesso de outras páginas (via GET)

// Pegar mensagens da sessão (se existirem, de um redirect anterior)
if (isset($_SESSION['admin_page_error_message'])) {
    $error_message = $_SESSION['admin_page_error_message'];
    unset($_SESSION['admin_page_error_message']);
}
if (isset($_SESSION['admin_page_success_message'])) {
    $success_message = $_SESSION['admin_page_success_message'];
    unset($_SESSION['admin_page_success_message']);
}

if (!$item_id) {
    $_SESSION['admin_page_error_message'] = "ID do item inválido ou não fornecido para edição.";
    header('Location: /home.php?error=invaliditemid_editpage');
    exit();
}

// Buscar dados do item
$sql_item = "SELECT name, category_id, location_id, found_date, description, status FROM items WHERE id = ?";
$stmt_item = $conn->prepare($sql_item);

if ($stmt_item) {
    $stmt_item->bind_param("i", $item_id);
    if ($stmt_item->execute()) {
        $result_item = $stmt_item->get_result();
        if ($result_item->num_rows === 1) {
            $item = $result_item->fetch_assoc();
        } else {
            $_SESSION['admin_page_error_message'] = "Item não encontrado com o ID: " . htmlspecialchars($item_id);
            header('Location: /home.php?error=itemnotfound_editpage');
            exit();
        }
    } else {
        error_log("SQL Execute Error (fetch_item_for_edit): " . $stmt_item->error);
        $error_message = "Erro ao executar busca do item.";
    }
    $stmt_item->close();
} else {
    error_log("SQL Prepare Error (fetch_item_for_edit): " . $conn->error);
    $error_message = "Erro ao preparar busca do item para edição.";
}

// Buscar categorias e locais
if (empty($error_message)) {
    $sql_cats = "SELECT id, name FROM categories ORDER BY name ASC";
    $result_cats = $conn->query($sql_cats);
    if ($result_cats) {
        while ($row = $result_cats->fetch_assoc()) {
            $categories[] = $row;
        }
    } else {
        error_log("SQL Error (fetch_categories_for_edit): " . $conn->error);
        $error_message .= " Erro ao carregar categorias.";
    }

    $sql_locs = "SELECT id, name FROM locations ORDER BY name ASC";
    $result_locs = $conn->query($sql_locs);
    if ($result_locs) {
        while ($row = $result_locs->fetch_assoc()) {
            $locations[] = $row;
        }
    } else {
        error_log("SQL Error (fetch_locations_for_edit): " . $conn->error);
        $error_message .= " Erro ao carregar locais.";
    }
}

// Mensagens de erro ou sucesso via GET
if (isset($_GET['error']) && empty($error_message)) {
    $error_map_get = [
        'emptyfields' => 'Nome, Categoria, Local e Data são obrigatórios.',
        'invaliditemid' => 'ID do item inválido fornecido para atualização.',
        'updatefailed' => 'Falha ao atualizar o item. Erro no banco de dados.',
        'itemnotfound_handler' => 'Item não encontrado pelo handler de atualização.',
        'nametoolong' => 'O nome do item é muito longo (máx 255 caracteres).',
        'nametooshort' => 'O nome do item é muito curto (mín 3 caracteres).',
        'descriptiontoolong' => 'A descrição é muito longa (máx 1000 caracteres).',
        'invaliddateformat' => 'Formato de data inválido. Use AAAA-MM-DD.'
    ];
    $error_key = $_GET['error'];
    $error_message = $error_map_get[$error_key] ?? 'Ocorreu uma falha ao tentar atualizar o item (cód: ' . htmlspecialchars($error_key) . ')';
}
if (isset($_GET['success']) && $_GET['success'] === 'itemupdated' && empty($success_message)) {
    $success_message = 'Item atualizado com sucesso!';
}

require_once '../templates/header.php';
?>

<div class="container admin-container">
    <h2>Editar Item ID: <?php echo htmlspecialchars($item_id); ?></h2>

    <?php if (!empty($success_message)): ?>
        <p class="success-message"><?php echo htmlspecialchars($success_message); ?></p>
    <?php endif; ?>
    <?php if (!empty($error_message)): ?>
        <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
    <?php endif; ?>

    <?php if ($item && empty($error_message)): ?>
    <form action="edit_item_handler.php" method="POST" class="form-admin">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($item_id); ?>">

        <div>
            <label for="name">Nome do item:</label>
            <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($item['name'] ?? ''); ?>">
        </div>

        <div>
            <label for="category_id">Categoria:</label>
            <select id="category_id" name="category_id" required>
                <option value="">Selecione uma categoria</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?php echo htmlspecialchars($category['id']); ?>"
                        <?php echo (isset($item['category_id']) && $item['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($category['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="location_id">Local onde foi encontrado:</label>
            <select id="location_id" name="location_id" required>
                <option value="">Selecione um local</option>
                <?php foreach ($locations as $location): ?>
                    <option value="<?php echo htmlspecialchars($location['id']); ?>"
                        <?php echo (isset($item['location_id']) && $item['location_id'] == $location['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($location['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="found_date">Data do achado:</label>
            <input type="date" id="found_date" name="found_date" required value="<?php echo htmlspecialchars($item['found_date'] ?? ''); ?>">
        </div>

        <div>
            <label for="description">Descrição (opcional):</label>
            <textarea id="description" name="description" rows="4"><?php echo htmlspecialchars($item['description'] ?? ''); ?></textarea>
        </div>

    <?php if (is_admin()): // is_admin() returns true for admin & superAdmin ?>
    <div>
        <label for="status">Status do Item:</label>
        <select id="status" name="status" required>
            <option value="Pendente" <?php echo (isset($item['status']) && $item['status'] == 'Pendente') ? 'selected' : ''; ?>>Pendente</option>
            <option value="Aguardando Aprovação" <?php echo (isset($item['status']) && $item['status'] == 'Aguardando Aprovação') ? 'selected' : ''; ?>>Aguardando Aprovação</option>
            <option value="Devolvido" <?php echo (isset($item['status']) && $item['status'] == 'Devolvido') ? 'selected' : ''; ?>>Devolvido</option>
            <option value="Doado" <?php echo (isset($item['status']) && $item['status'] == 'Doado') ? 'selected' : ''; ?>>Doado</option>
        </select>
    </div>
    <?php endif; ?>

        <div class="form-action-buttons-group">
            <a href="/home.php" class="button-secondary">Cancelar</a>
            <button type="submit" class="button-primary">Salvar Alterações</button>
        </div>
    </form>
    <?php elseif (empty($error_message)): ?>
        <p class="error-message">Não foi possível carregar os dados do item para edição. O item pode não existir ou houve um erro.</p>
    <?php endif; ?>
</div>

<?php require_once '../templates/footer.php'; ?>
