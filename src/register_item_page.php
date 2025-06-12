<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'auth.php';
require_once 'db_connect.php'; // <<<--- ADICIONADO E CORRIGIDO

require_login('index.php?error=pleaselogin');

$categories = [];
$sql_cats = "SELECT id, name FROM categories ORDER BY name ASC";
$result_cats = null;
if ($conn) {
    $result_cats = $conn->query($sql_cats);
    if ($result_cats && $result_cats->num_rows > 0) {
        while ($row = $result_cats->fetch_assoc()) {
            $categories[] = $row;
        }
    } elseif ($result_cats === false) {
        error_log("Erro ao buscar categorias: " . $conn->error);
    }
} else {
    error_log("Conexão com DB falhou, não foi possível buscar categorias.");
}

$locations = [];
$sql_locs = "SELECT id, name FROM locations ORDER BY name ASC";
$result_locs = null;
if ($conn) {
    $result_locs = $conn->query($sql_locs);
    if ($result_locs && $result_locs->num_rows > 0) {
        while ($row = $result_locs->fetch_assoc()) {
            $locations[] = $row;
        }
    } elseif ($result_locs === false) {
        error_log("Erro ao buscar locais: " . $conn->error);
    }
} else {
    error_log("Conexão com DB falhou, não foi possível buscar locais.");
}

require_once 'templates/header.php';
?>

<div class="container register-item-container">
    <h2>Cadastrar Novo Item Encontrado</h2>

    <?php
    if (isset($_GET['error'])) {
        $errorMessage = 'Erro desconhecido ao adicionar item.';
        $error_map = [
            'emptyitemfields' => 'Todos os campos obrigatórios (Nome, Categoria, Local, Data) devem ser preenchidos.',
            'invaliddateformat' => 'Formato de data inválido. Use AAAA-MM-DD.',
            'sqlerror_item' => 'Erro de banco de dados ao tentar salvar o item.',
            'itemaddfailed' => 'Falha ao adicionar o item. Tente novamente.',
            'barcodeuniqueerror' => 'Erro ao gerar código de barras único. Tente novamente.',
            'categoryfetcherror' => 'Erro ao buscar lista de categorias. Verifique a conexão ou contate o suporte.',
            'locationfetcherror' => 'Erro ao buscar lista de locais. Verifique a conexão ou contate o suporte.'
        ];
        $error_key = $_GET['error'];
        if (isset($error_map[$error_key])) {
            $errorMessage = $error_map[$error_key];
        }
        echo '<p class="error-message">' . htmlspecialchars($errorMessage) . '</p>';
    }
    if (isset($_GET['success']) && $_GET['success'] == 'itemadded') {
        echo '<p class="success-message">Item "' . htmlspecialchars($_GET['item_name'] ?? '') . '" cadastrado com sucesso! Código de Barras: <strong>' . htmlspecialchars($_GET['barcode'] ?? '') . '</strong></p>';
    }
    ?>

    <form action="add_item_handler.php" method="POST">
        <div>
            <label for="name">Nome do item:</label>
            <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($_GET['name_preset'] ?? ''); ?>">
        </div>
        <div>
            <label for="category_id">Categoria:</label>
            <select id="category_id" name="category_id" required>
                <option value="">Selecione uma categoria</option>
                <?php if (empty($categories) && $result_cats === false && $conn): ?>
                    <option value="" disabled>Erro ao carregar categorias do banco.</option>
                <?php elseif (empty($categories) && !$conn): ?>
                     <option value="" disabled>Falha na conexão com DB para categorias.</option>
                <?php endif; ?>
                <?php foreach ($categories as $category): ?>
                    <option value="<?php echo htmlspecialchars($category['id']); ?>" <?php echo (isset($_GET['category_id_preset']) && $_GET['category_id_preset'] == $category['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($category['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="location_id">Local onde foi encontrado:</label>
            <select id="location_id" name="location_id" required>
                <option value="">Selecione um local</option>
                 <?php if (empty($locations) && $result_locs === false && $conn): ?>
                    <option value="" disabled>Erro ao carregar locais do banco.</option>
                <?php elseif (empty($locations) && !$conn): ?>
                     <option value="" disabled>Falha na conexão com DB para locais.</option>
                <?php endif; ?>
                <?php foreach ($locations as $location): ?>
                    <option value="<?php echo htmlspecialchars($location['id']); ?>" <?php echo (isset($_GET['location_id_preset']) && $_GET['location_id_preset'] == $location['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($location['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="found_date">Data do achado:</label>
            <input type="date" id="found_date" name="found_date" required value="<?php echo htmlspecialchars($_GET['found_date_preset'] ?? date('Y-m-d')); ?>">
        </div>
        <div>
            <label for="description">Descrição (opcional):</label>
            <textarea id="description" name="description" rows="4"><?php echo htmlspecialchars($_GET['description_preset'] ?? ''); ?></textarea>
        </div>
        <div>
            <button type="submit">Cadastrar Item</button>
        </div>
    </form>
</div>

<?php require_once 'templates/footer.php'; ?>