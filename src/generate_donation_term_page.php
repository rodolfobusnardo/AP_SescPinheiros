<?php
require_once __DIR__ . '/auth.php'; // Includes start_secure_session() and auth functions
require_once __DIR__ . '/db_connect.php';

require_admin('/index.php?error=unauthorized_donation_page'); // Ensure only admins can access

$item_ids_str = filter_input(INPUT_GET, 'item_ids', FILTER_SANITIZE_STRING);
$item_ids_array_raw = [];
$valid_item_ids_for_donation = [];
$items_for_donation_details = [];
$category_summary = [];
$error_message = '';
$summary_text = 'Nenhum item válido selecionado para doação.';

if (empty($item_ids_str)) {
    $_SESSION['home_page_error_message'] = 'Nenhum ID de item fornecido para doação.';
    header('Location: /home.php');
    exit();
}

$item_ids_array_raw = explode(',', $item_ids_str);
$item_ids_array_int = [];
foreach ($item_ids_array_raw as $id_str) {
    $id_int = filter_var(trim($id_str), FILTER_VALIDATE_INT);
    if ($id_int !== false && $id_int > 0) {
        $item_ids_array_int[] = $id_int;
    }
}

if (empty($item_ids_array_int)) {
    $_SESSION['home_page_error_message'] = 'IDs de item fornecidos são inválidos.';
    header('Location: /home.php');
    exit();
}

// Fetch item details for valid and 'Pendente' items
if (!empty($item_ids_array_int)) {
    $placeholders = implode(',', array_fill(0, count($item_ids_array_int), '?'));
    $sql = "SELECT i.id, i.name, i.status, c.name AS category_name
            FROM items i
            JOIN categories c ON i.category_id = c.id
            WHERE i.id IN ($placeholders) AND i.status = 'Pendente'";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        // Dynamically bind parameters
        $types = str_repeat('i', count($item_ids_array_int));
        $stmt->bind_param($types, ...$item_ids_array_int);

        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $items_for_donation_details[] = $row;
                $valid_item_ids_for_donation[] = $row['id']; // Store only IDs of actually donatable items
                if (!isset($category_summary[$row['category_name']])) {
                    $category_summary[$row['category_name']] = 0;
                }
                $category_summary[$row['category_name']]++;
            }
        } else {
            error_log("Error executing statement to fetch items for donation: " . $stmt->error);
            $error_message = "Erro ao buscar detalhes dos itens. Tente novamente.";
        }
        $stmt->close();
    } else {
        error_log("Error preparing statement to fetch items for donation: " . $conn->error);
        $error_message = "Erro ao preparar busca de itens. Tente novamente.";
    }
}

if (empty($valid_item_ids_for_donation)) {
    $_SESSION['home_page_error_message'] = 'Nenhum dos itens selecionados está disponível para doação (status "Pendente") ou os IDs são inválidos.';
    header('Location: /home.php');
    exit();
}

// Generate summary text
if (!empty($category_summary)) {
    $summary_parts = [];
    foreach ($category_summary as $category => $count) {
        $summary_parts[] = htmlspecialchars($count) . " - " . htmlspecialchars($category);
    }
    $summary_text = "Itens para Doação: " . implode(', ', $summary_parts) . ".";
}

// Default values
$current_date = date('Y-m-d');
$current_time = date('H:i');
$responsible_user = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : '';

require_once __DIR__ . '/templates/header.php';
?>

<div class="container">
    <h2>Registrar Termo de Doação</h2>

    <?php if (!empty($error_message)): ?>
        <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
    <?php endif; ?>

    <p><?php echo $summary_text; ?></p>
    <p>Total de itens: <?php echo count($valid_item_ids_for_donation); ?></p>

    <form action="submit_donation_handler.php" method="POST" id="donationForm" class="form-modern">
        <fieldset>
            <legend>Dados da Doação</legend>
            <div class="form-group">
                <label for="responsible_donation">Responsável pela Doação (Sistema):</label>
                <input type="text" id="responsible_donation" name="responsible_donation" value="<?php echo $responsible_user; ?>" required readonly class="form-control-readonly">
            </div>
            <div class="form-row">
                <div class="form-group_col">
                    <label for="donation_date">Data da Doação:</label>
                    <input type="date" id="donation_date" name="donation_date" value="<?php echo $current_date; ?>" required class="form-control">
                </div>
                <div class="form-group_col">
                    <label for="donation_time">Hora da Doação:</label>
                    <input type="time" id="donation_time" name="donation_time" value="<?php echo $current_time; ?>" required class="form-control">
                </div>
            </div>
        </fieldset>

        <fieldset>
            <legend>Instituição Recebedora</legend>
            <div class="form-group">
                <label for="institution_name">Nome da Instituição:</label>
                <input type="text" id="institution_name" name="institution_name" required class="form-control">
            </div>
            <div class="form-row">
                <div class="form-group_col">
                    <label for="institution_cnpj">CNPJ:</label>
                    <input type="text" id="institution_cnpj" name="institution_cnpj" class="form-control cnpj-mask"> <!-- JS mask class -->
                </div>
                <div class="form-group_col">
                    <label for="institution_ie">IE (Inscrição Estadual):</label>
                    <input type="text" id="institution_ie" name="institution_ie" class="form-control">
                </div>
            </div>
            <div class="form-group">
                <label for="institution_responsible_name">Nome do Responsável (Instituição):</label>
                <input type="text" id="institution_responsible_name" name="institution_responsible_name" required class="form-control">
            </div>
            <div class="form-group">
                <label for="institution_phone">Telefone (Instituição):</label>
                <input type="text" id="institution_phone" name="institution_phone" class="form-control phone-mask"> <!-- JS mask class -->
            </div>
            <div class="form-group">
                <label for="institution_address_street">Endereço (Rua/Av.):</label>
                <input type="text" id="institution_address_street" name="institution_address_street" class="form-control">
            </div>
            <div class="form-row">
                <div class="form-group_col form-group_col-short">
                    <label for="institution_address_number">Número:</label>
                    <input type="text" id="institution_address_number" name="institution_address_number" class="form-control number-mask"> <!-- JS mask class -->
                </div>
                <div class="form-group_col">
                    <label for="institution_address_bairro">Bairro:</label>
                    <input type="text" id="institution_address_bairro" name="institution_address_bairro" class="form-control">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group_col">
                    <label for="institution_address_cidade">Cidade:</label>
                    <input type="text" id="institution_address_cidade" name="institution_address_cidade" class="form-control">
                </div>
                <div class="form-group_col form-group_col-short">
                    <label for="institution_address_estado">Estado (UF):</label>
                    <input type="text" id="institution_address_estado" name="institution_address_estado" class="form-control state-mask" maxlength="2"> <!-- JS mask class -->
                </div>
                 <div class="form-group_col">
                    <label for="institution_address_cep">CEP:</label>
                    <input type="text" id="institution_address_cep" name="institution_address_cep" class="form-control cep-mask"> <!-- JS mask class -->
                </div>
            </div>
        </fieldset>

        <fieldset>
            <legend>Assinatura do Responsável da Instituição</legend>
            <p>Por favor, o responsável pela instituição deve assinar no quadro abaixo:</p>
            <div id="signaturePadContainer" style="border: 1px solid #ccc; max-width:400px; height:200px; margin-bottom:10px;">
                <canvas id="signatureCanvas" width="400" height="200"></canvas> <!-- JS will initialize this -->
            </div>
            <button type="button" id="clearSignatureButton" class="button-secondary">Limpar Assinatura</button>
            <input type="hidden" name="signature_data" id="signatureDataInput">
             <!-- Note: JavaScript for signature pad (e.g., SignaturePad by szimek) will be needed here or in script.js -->
        </fieldset>

        <input type="hidden" name="item_ids_for_donation" value="<?php echo htmlspecialchars(implode(',', $valid_item_ids_for_donation)); ?>">

        <div class="form-action-buttons-group" style="margin-top: 20px;">
            <a href="/home.php" class="button-secondary">Cancelar</a>
            <button type="submit" class="button-primary" id="submitDonationButton">Enviar para Aprovação</button>
        </div>
         <!-- JS for form validation (e.g., ensure signature is not empty) and submitting signature data will be needed -->
    </form>
</div>

<?php
// Placeholder for inline JS or link to specific JS file for this page
// e.g. <script src="js/donation_form.js"></script>
// For now, general script.js might handle some aspects like masks if configured globally.
require_once __DIR__ . '/templates/footer.php';
?>
