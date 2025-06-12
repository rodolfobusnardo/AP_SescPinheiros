<?php
// File: src/admin/manage_locations.php
require_once '../auth.php';
require_once '../db_connect.php';

start_secure_session();
require_admin('../index.php');

// Fetch all locations for the list
$locations = [];
$sql_locations = "SELECT id, name FROM locations ORDER BY name ASC";
$result_locations = $conn->query($sql_locations);
if ($result_locations && $result_locations->num_rows > 0) {
    while ($row = $result_locations->fetch_assoc()) {
        $locations[] = $row;
    }
} elseif ($result_locations === false) {
    error_log("SQL Error (fetch_locations): " . $conn->error);
    $_SESSION['page_error_message'] = "Erro ao carregar lista de locais.";
}

require_once '../templates/header.php';
?>

<div class="container admin-container">
    <h2>Gerenciar Locais de Achados</h2>

    <?php
    // Display messages from redirects
    if (isset($_GET['success'])) {
        $success_messages = [
            'loc_added' => 'Novo local adicionado com sucesso!',
            'loc_updated' => 'Local atualizado com sucesso!',
        ];
        if (isset($success_messages[$_GET['success']])) {
            echo '<p class="success-message">' . htmlspecialchars($success_messages[$_GET['success']]) . '</p>';
        }
    }
    if (isset($_GET['error'])) {
        $error_messages = [
            'emptyfields_addloc' => 'Nome é obrigatório para adicionar local.',
            'loc_exists' => 'Um local com este Nome já existe.',
            'add_loc_failed' => 'Falha ao adicionar novo local.',
            'emptyfields_editloc' => 'Nome é obrigatório para editar local.',
            'loc_exists_edit' => 'Outro local com este Nome já existe.',
            'edit_loc_failed' => 'Falha ao atualizar local.',
            'invalid_action' => 'Ação inválida especificada.',
        ];
        $error_key = $_GET['error'];
        $display_message = $error_messages[$error_key] ?? 'Ocorreu um erro desconhecido.';
        echo '<p class="error-message">' . htmlspecialchars($display_message) . '</p>';
    }
    if (isset($_GET['message']) && $_GET['message'] == 'loc_nochange') {
        echo '<p class="info-message">Nenhuma alteração detectada no local.</p>';
    }
    if (isset($_SESSION['page_error_message'])) {
        echo '<p class="error-message">' . htmlspecialchars($_SESSION['page_error_message']) . '</p>';
        unset($_SESSION['page_error_message']);
    }
    ?>

    <!-- Add Location Form -->
    <div id="addLocationSection">
        <h3>Adicionar Novo Local</h3>
        <form action="location_handler.php" method="POST" class="form-admin">
            <input type="hidden" name="action" value="add_location">
            <div>
                <label for="name_add_loc">Nome do Local:</label>
                <input type="text" id="name_add_loc" name="name" required>
            </div>
            <div>
                <button type="submit">Adicionar Local</button>
            </div>
        </form>
    </div>

    <hr> <!-- Inline style removed -->

    <!-- Edit Location Form (Initially Hidden) -->
    <div id="editLocationSection" style="display:none;"> <!-- Inline styles for border, padding, bg removed -->
        <h3>Editar Local</h3>
        <form action="location_handler.php" method="POST" class="form-admin">
            <input type="hidden" name="action" value="edit_location">
            <input type="hidden" id="edit_location_id" name="id">
            <div>
                <label for="name_edit_loc">Nome do Local:</label>
                <input type="text" id="name_edit_loc" name="name" required>
            </div>
            <div class="form-action-buttons-group">
                <button type="button" class="button-secondary" onclick="hideEditForm('editLocationSection', 'addLocationSection')">Cancelar</button>
                <button type="submit" class="button-primary">Salvar Alterações</button>
            </div>
        </form>
    </div>

    <h3>Lista de Locais</h3>
    <?php if (!empty($locations)): ?>
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($locations as $location): ?>
            <tr>
                <td><?php echo htmlspecialchars($location['id']); ?></td>
                <td><?php echo htmlspecialchars($location['name']); ?></td>
                <td class="actions-cell">
                    <button type="button" class="button-edit" onclick="populateEditLocationForm(<?php echo htmlspecialchars($location['id']); ?>)">Editar</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p>Nenhum local encontrado.</p>
    <?php endif; ?>
</div>

<script>
function populateEditLocationForm(locationId) {
    fetch(`location_handler.php?action=get_location&id=${locationId}`)
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            document.getElementById('edit_location_id').value = data.data.id;
            document.getElementById('name_edit_loc').value = data.data.name;
            document.getElementById('editLocationSection').style.display = 'block';
            document.getElementById('addLocationSection').style.display = 'none'; // Optionally hide add form
            window.scrollTo(0, document.getElementById('editLocationSection').offsetTop - 20); // Scroll to form
        } else {
            alert('Erro ao buscar dados do local: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        alert('Ocorreu um erro de comunicação ao buscar dados do local.');
    });
}

function hideEditForm(formToHideId, formToShowId) {
    document.getElementById(formToHideId).style.display = 'none';
    if (formToShowId) {
        document.getElementById(formToShowId).style.display = 'block';
    }
}
</script>

<?php
if (isset($conn) && $conn instanceof mysqli) {
    // $conn->close();
}
require_once '../templates/footer.php';
?>
