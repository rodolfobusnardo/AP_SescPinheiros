<?php
require_once '../auth.php';
require_once '../db_connect.php'; // Provides $conn

start_secure_session();
require_super_admin('../home.php'); // Redirect if not super admin

function get_all_settings($conn) {
    $stmt = $conn->prepare("SELECT * FROM settings WHERE config_id = 1");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return [
        'unidade_nome' => '', 'cnpj' => '', 'endereco_rua' => '',
        'endereco_numero' => '', 'endereco_bairro' => '', 'endereco_cidade' => '',
        'endereco_estado' => '', 'endereco_cep' => ''
    ];
}

$current_settings = get_all_settings($conn);

// Apply masks for display if data exists (e.g. for CNPJ and CEP)
if (!empty($current_settings['cnpj'])) {
    $v = preg_replace('/\D/', '', $current_settings['cnpj']);
    if (strlen($v) == 14) {
        $v = substr($v, 0, 2) . '.' . substr($v, 2, 3) . '.' . substr($v, 5, 3) . '/' . substr($v, 8, 4) . '-' . substr($v, 12, 2);
        $current_settings['cnpj'] = $v;
    }
}

if (!empty($current_settings['endereco_cep'])) {
    $v = preg_replace('/\D/', '', $current_settings['endereco_cep']);
    if (strlen($v) == 8) {
        $v = substr($v, 0, 5) . '-' . substr($v, 5, 3);
        $current_settings['endereco_cep'] = $v;
    }
}

require_once '../templates/header.php';
?>

<div class="container admin-container">
    <h2>Configurações do Sistema</h2>

    <?php
    if (isset($_GET['success'])) {
        echo '<p class="success-message">Configurações salvas com sucesso!</p>';
    }
    if (isset($_GET['error'])) {
        $error_message = 'Ocorreu um erro ao salvar as configurações.';
        if (!empty($_SESSION['settings_error_message'])) {
            $error_message = htmlspecialchars($_SESSION['settings_error_message']);
            unset($_SESSION['settings_error_message']);
        } elseif ($_GET['error'] === 'validation') {
            $error_message = 'Erro de validação. Verifique os campos.';
        }
        echo '<p class="error-message">' . $error_message . '</p>';
    }
    ?>

    <form action="settings_handler.php" method="POST" class="form-admin">
        <h3>Dados da Unidade</h3>
        <div>
            <label for="unidade_nome">Nome da Unidade:</label>
            <input type="text" id="unidade_nome" name="unidade_nome" value="<?php echo htmlspecialchars($current_settings['unidade_nome'] ?? ''); ?>" maxlength="255">
        </div>
        <div>
            <label for="cnpj">CNPJ:</label>
            <input type="text" id="cnpj" name="cnpj" value="<?php echo htmlspecialchars($current_settings['cnpj'] ?? ''); ?>" maxlength="18">
        </div>

        <h3>Endereço Completo</h3>
        <div>
            <label for="endereco_rua">Rua:</label>
            <input type="text" id="endereco_rua" name="endereco_rua" value="<?php echo htmlspecialchars($current_settings['endereco_rua'] ?? ''); ?>" maxlength="255">
        </div>
        <div>
            <label for="endereco_numero">Número:</label>
            <input type="text" id="endereco_numero" name="endereco_numero" value="<?php echo htmlspecialchars($current_settings['endereco_numero'] ?? ''); ?>" maxlength="10">
        </div>
        <div>
            <label for="endereco_bairro">Bairro:</label>
            <input type="text" id="endereco_bairro" name="endereco_bairro" value="<?php echo htmlspecialchars($current_settings['endereco_bairro'] ?? ''); ?>" maxlength="100">
        </div>
        <div>
            <label for="endereco_cidade">Cidade:</label>
            <input type="text" id="endereco_cidade" name="endereco_cidade" value="<?php echo htmlspecialchars($current_settings['endereco_cidade'] ?? ''); ?>" maxlength="100">
        </div>
        <div>
            <label for="endereco_estado">Estado:</label>
            <input type="text" id="endereco_estado" name="endereco_estado" value="<?php echo htmlspecialchars($current_settings['endereco_estado'] ?? ''); ?>" maxlength="2" pattern="[A-Za-z]{2}" title="Use a sigla com duas letras, ex: SP">
        </div>
        <div>
            <label for="endereco_cep">CEP:</label>
            <input type="text" id="endereco_cep" name="endereco_cep" value="<?php echo htmlspecialchars($current_settings['endereco_cep'] ?? ''); ?>" maxlength="9">
        </div>

        <div class="form-action-buttons-group">
            <button type="submit" class="button-primary">Salvar Configurações</button>
        </div>
    </form>
</div>

<script>
// User's provided script for masks
document.getElementById('cnpj').addEventListener('input', function (e) {
    let v = e.target.value.replace(/\D/g, ''); // Keep \D for regex literal in JS
    if (v.length > 14) v = v.slice(0, 14);
    v = v.replace(/^(\d{2})(\d)/, '$1.$2');
    v = v.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
    v = v.replace(/\.(\d{3})(\d)/, '.$1/$2');
    v = v.replace(/(\d{4})(\d)/, '$1-$2');
    e.target.value = v;
});

document.getElementById('endereco_cep').addEventListener('input', function (e) {
    let v = e.target.value.replace(/\D/g, ''); // Keep \D for regex literal in JS
    if (v.length > 8) v = v.slice(0, 8);
    v = v.replace(/^(\d{5})(\d)/, '$1-$2');
    e.target.value = v;
});

document.getElementById('endereco_numero').addEventListener('input', function (e) {
    e.target.value = e.target.value.replace(/\D/g, ''); // Keep \D for regex literal in JS
});

document.getElementById('endereco_estado').addEventListener('input', function (e) {
    e.target.value = e.target.value.toUpperCase().replace(/[^A-Z]/g, '').substring(0, 2);
});
</script>

<?php
require_once '../templates/footer.php';
?>
