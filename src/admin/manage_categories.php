<?php
// File: src/admin/manage_categories.php
require_once '../auth.php';
require_once '../db_connect.php';

start_secure_session();
require_admin('../index.php');

// Fetch all categories for the list
$categories = [];
$sql_categories = "SELECT id, name, code FROM categories ORDER BY name ASC";
$result_categories = $conn->query($sql_categories);
if ($result_categories && $result_categories->num_rows > 0) {
    while ($row = $result_categories->fetch_assoc()) {
        $categories[] = $row;
    }
} elseif ($result_categories === false) {
    error_log("SQL Error (fetch_categories): " . $conn->error);
    $_SESSION['page_error_message'] = "Erro ao carregar lista de categorias.";
}

require_once '../templates/header.php';
?>

<div class="container admin-container">
    <h2>Gerenciar Categorias de Itens</h2>

    <?php
    // Display messages from redirects
    if (isset($_GET['success'])) {
        $success_messages = [
            'cat_added' => 'Nova categoria adicionada com sucesso!',
            'cat_updated' => 'Categoria atualizada com sucesso!',
            'cat_deleted' => 'Categoria excluída com sucesso!',
        ];
        if (isset($success_messages[$_GET['success']])) {
            echo '<p class="success-message">' . htmlspecialchars($success_messages[$_GET['success']]) . '</p>';
        }
    }
    if (isset($_GET['error'])) {
        $error_messages = [
            'emptyfields_addcat' => 'Nome e Código são obrigatórios para adicionar categoria.',
            'code_too_long' => 'O código da categoria não pode ter mais que 10 caracteres.',
            'cat_exists' => 'Uma categoria com este Nome ou Código já existe.',
            'add_cat_failed' => 'Falha ao adicionar nova categoria.',
            'emptyfields_editcat' => 'Nome e Código são obrigatórios para editar categoria.',
            'code_too_long_edit' => 'O código da categoria não pode ter mais que 10 caracteres (ao editar).',
            'cat_exists_edit' => 'Outra categoria com este Nome ou Código já existe.',
            'edit_cat_failed' => 'Falha ao atualizar categoria.',
            'invalid_action' => 'Ação inválida especificada.',
            'invalid_id_delete' => 'ID inválido para exclusão.',
            'cat_in_use' => 'Esta categoria está em uso e não pode ser excluída.',
            'cat_not_found_delete' => 'Categoria não encontrada para exclusão.',
            'delete_cat_failed' => 'Falha ao excluir categoria.',
        ];
        $error_key = $_GET['error'];
        $display_message = $error_messages[$error_key] ?? 'Ocorreu um erro desconhecido.';
        echo '<p class="error-message">' . htmlspecialchars($display_message) . '</p>';
    }
     if (isset($_GET['message']) && $_GET['message'] == 'cat_nochange') {
        echo '<p class="info-message">Nenhuma alteração detectada na categoria.</p>';
    }
    if (isset($_SESSION['page_error_message'])) {
        echo '<p class="error-message">' . htmlspecialchars($_SESSION['page_error_message']) . '</p>';
        unset($_SESSION['page_error_message']);
    }
    ?>

    <!-- Add Category Form -->
    <div id="addCategorySection">
        <h3>Adicionar Nova Categoria</h3>
        <form action="category_handler.php" method="POST" class="form-admin">
            <input type="hidden" name="action" value="add_category">
            <div>
                <label for="name_add">Nome da Categoria:</label>
                <input type="text" id="name_add" name="name" required>
            </div>
            <div>
                <label for="code_add">Código (ex: ROP, ELE, max 10 chars):</label>
                <input type="text" id="code_add" name="code" required maxlength="10" pattern="[A-Za-z0-9_]+" title="Use letras, números ou underscore.">
            </div>
            <div>
                <button type="submit">Adicionar Categoria</button>
            </div>
        </form>
    </div>

    <hr> <!-- Inline style removed -->

    <!-- Edit Category Form (Initially Hidden) -->
    <div id="editCategorySection" style="display:none;"> <!-- Inline styles for border, padding, bg removed -->
        <h3>Editar Categoria</h3>
        <form action="category_handler.php" method="POST" class="form-admin">
            <input type="hidden" name="action" value="edit_category">
            <input type="hidden" id="edit_category_id" name="id">
            <div>
                <label for="name_edit">Nome da Categoria:</label>
                <input type="text" id="name_edit" name="name" required>
            </div>
            <div>
                <label for="code_edit">Código (ex: ROP, ELE, max 10 chars):</label>
                <input type="text" id="code_edit" name="code" required maxlength="10" pattern="[A-Za-z0-9_]+" title="Use letras, números ou underscore.">
            </div>
            <div class="form-action-buttons-group">
                <button type="button" class="button-secondary" onclick="hideEditForm('editCategorySection')">Cancelar</button>
                <button type="submit" class="button-primary">Salvar Alterações</button>
            </div>
        </form>
    </div>


    <h3>Lista de Categorias</h3>
    <?php if (!empty($categories)): ?>
    <table class="admin-table">
        <colgroup>
            <col style="width: 50px;">  <!-- ID -->
            <col style="width: auto;">  <!-- Nome -->
            <col style="width: 120px;"> <!-- Código -->
            <col style="width: 180px;"> <!-- Ações -->
        </colgroup>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>Código</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($categories as $category): ?>
            <tr>
                <td><?php echo htmlspecialchars($category['id']); ?></td>
                <td><?php echo htmlspecialchars($category['name']); ?></td>
                <td><?php echo htmlspecialchars($category['code']); ?></td>
                <td class="actions-cell category-actions-cell-override">
                    <button type="button" class="button-edit" onclick="populateEditCategoryForm(<?php echo htmlspecialchars($category['id']); ?>)">Editar</button>
                    <button type="button" class="button-delete" onclick="confirmDeleteCategory(<?php echo htmlspecialchars($category['id']); ?>)">Excluir</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p>Nenhuma categoria encontrada.</p>
    <?php endif; ?>
</div>

<script>
function populateEditCategoryForm(categoryId) {
    fetch(`category_handler.php?action=get_category&id=${categoryId}`)
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            document.getElementById('edit_category_id').value = data.data.id;
            document.getElementById('name_edit').value = data.data.name;
            document.getElementById('code_edit').value = data.data.code;
            document.getElementById('editCategorySection').style.display = 'block';
            document.getElementById('addCategorySection').style.display = 'none'; // Optionally hide add form
            window.scrollTo(0, document.getElementById('editCategorySection').offsetTop - 20); // Scroll to form
        } else {
            alert('Erro ao buscar dados da categoria: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        alert('Ocorreu um erro de comunicação ao buscar dados da categoria.');
    });
}

function hideEditForm(formId) {
    document.getElementById(formId).style.display = 'none';
    document.getElementById('addCategorySection').style.display = 'block'; // Show add form again
}

function confirmDeleteCategory(categoryId) {
    if (confirm('Tem certeza que deseja excluir esta categoria? Esta ação não pode ser desfeita.')) {
        // Redirect to a handler or use fetch to delete
        window.location.href = `category_handler.php?action=delete_category&id=${categoryId}`;
    }
}
</script>

<?php
if (isset($conn) && $conn instanceof mysqli) {
    // $conn->close(); // Usually closed by PHP or footer
}
require_once '../templates/footer.php';
?>
