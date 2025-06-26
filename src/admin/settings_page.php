<?php
// Bloco único de PHP para toda a lógica antes do HTML
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db_connect.php';

// 1. Inicia a sessão e verifica a permissão
start_secure_session();
require_super_admin(); // Redireciona se não for super admin. O auth.php corrigido cuida do caminho.

// 2. Lógica da página (buscar dados do banco)
function get_all_settings($conn) {
    $stmt = $conn->prepare("SELECT * FROM settings WHERE config_id = 1");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    // Retorna um array vazio se não houver configurações
    return [
        'unidade_nome' => '', 'cnpj' => '', 'endereco_rua' => '',
        'endereco_numero' => '', 'endereco_bairro' => '', 'endereco_cidade' => '',
        'endereco_estado' => '', 'endereco_cep' => ''
    ];
}

$current_settings = get_all_settings($conn);

// Aplica máscaras para exibição, se os dados existirem
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

// 3. Somente agora, após toda a lógica, o header é chamado para começar a "desenhar" a página
require_once __DIR__ . '/../templates/header.php';
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

    <form action="settings_handler.php" method="POST" class="form-admin form-modern">
        <h3>Dados da Unidade</h3>
        <div class="form-row">
            <div class="form-group_col">
                <label for="unidade_nome">Nome da Unidade:</label>
                <input type="text" id="unidade_nome" name="unidade_nome" class="form-control" value="<?php echo htmlspecialchars($current_settings['unidade_nome'] ?? ''); ?>" maxlength="255">
            </div>
            <div class="form-group_col">
                <label for="cnpj">CNPJ:</label>
                <input type="text" id="cnpj" name="cnpj" class="form-control" value="<?php echo htmlspecialchars($current_settings['cnpj'] ?? ''); ?>" maxlength="18" required>
            </div>
        </div>

        <h3>Endereço Completo</h3>
        <div class="form-row">
            <div class="form-group_col">
                <label for="endereco_rua">Rua:</label>
                <input type="text" id="endereco_rua" name="endereco_rua" class="form-control" value="<?php echo htmlspecialchars($current_settings['endereco_rua'] ?? ''); ?>" maxlength="255">
            </div>
            <div class="form-group_col">
                <label for="endereco_numero">Número:</label>
                <input type="text" id="endereco_numero" name="endereco_numero" class="form-control" value="<?php echo htmlspecialchars($current_settings['endereco_numero'] ?? ''); ?>" maxlength="10">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group_col">
                <label for="endereco_bairro">Bairro:</label>
                <input type="text" id="endereco_bairro" name="endereco_bairro" class="form-control" value="<?php echo htmlspecialchars($current_settings['endereco_bairro'] ?? ''); ?>" maxlength="100">
            </div>
            <div class="form-group_col">
                <label for="endereco_cidade">Cidade:</label>
                <input type="text" id="endereco_cidade" name="endereco_cidade" class="form-control" value="<?php echo htmlspecialchars($current_settings['endereco_cidade'] ?? ''); ?>" maxlength="100">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group_col">
                <label for="endereco_estado">Estado:</label>
                <input type="text" id="endereco_estado" name="endereco_estado" class="form-control" value="<?php echo htmlspecialchars($current_settings['endereco_estado'] ?? ''); ?>" maxlength="2" pattern="[A-Za-z]{2}" title="Use a sigla com duas letras, ex: SP">
            </div>
            <div class="form-group_col">
                <label for="endereco_cep">CEP:</label>
                <input type="text" id="endereco_cep" name="endereco_cep" class="form-control" value="<?php echo htmlspecialchars($current_settings['endereco_cep'] ?? ''); ?>" maxlength="9">
            </div>
        </div>

        <div class="form-action-buttons-group" style="margin-top: 25px;">
            <button type="submit" class="button-primary">Salvar Configurações</button>
        </div>
    </form>
</div>

<script>
// Scripts de máscara
document.getElementById('cnpj').addEventListener('input', function (e) {
    let v = e.target.value.replace(/\D/g, '');
    if (v.length > 14) v = v.slice(0, 14);
    v = v.replace(/^(\d{2})(\d)/, '$1.$2');
    v = v.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
    v = v.replace(/\.(\d{3})(\d)/, '.$1/$2');
    v = v.replace(/(\d{4})(\d)/, '$1-$2');
    e.target.value = v;
});

document.getElementById('endereco_cep').addEventListener('input', function (e) {
    let v = e.target.value.replace(/\D/g, '');
    if (v.length > 8) v = v.slice(0, 8);
    v = v.replace(/^(\d{5})(\d)/, '$1-$2');
    e.target.value = v;
});

document.getElementById('endereco_numero').addEventListener('input', function (e) {
    e.target.value = e.target.value.replace(/\D/g, '');
});

document.getElementById('endereco_estado').addEventListener('input', function (e) {
    e.target.value = e.target.value.toUpperCase().replace(/[^A-Z]/g, '').substring(0, 2);
});
</script>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>