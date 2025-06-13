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
                $valid_item_ids_for_donation[] = $row['id'];
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
     <?php if (isset($_SESSION['generate_donation_page_error_message'])): ?>
        <p class="error-message"><?php echo htmlspecialchars($_SESSION['generate_donation_page_error_message']); ?></p>
        <?php unset($_SESSION['generate_donation_page_error_message']); ?>
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
                    <input type="text" id="institution_cnpj" name="institution_cnpj" class="form-control">
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
                <input type="text" id="institution_phone" name="institution_phone" class="form-control">
            </div>
            <div class="form-group">
                <label for="institution_address_street">Endereço (Rua/Av.):</label>
                <input type="text" id="institution_address_street" name="institution_address_street" class="form-control">
            </div>
            <div class="form-row">
                <div class="form-group_col form-group_col-short">
                    <label for="institution_address_number">Número:</label>
                    <input type="text" id="institution_address_number" name="institution_address_number" class="form-control">
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
                    <input type="text" id="institution_address_estado" name="institution_address_estado" class="form-control" maxlength="2">
                </div>
                 <div class="form-group_col">
                    <label for="institution_address_cep">CEP:</label>
                    <input type="text" id="institution_address_cep" name="institution_address_cep" class="form-control">
                </div>
            </div>
        </fieldset>

        <fieldset>
            <legend>Assinatura do Responsável da Instituição</legend>
            <p>Por favor, o responsável pela instituição deve assinar no quadro abaixo:</p>
            <div id="signaturePadContainer" style="border: 1px solid #ccc; max-width:400px; height:200px; margin-bottom:10px; position: relative;">
                <canvas id="signatureCanvas" style="width: 100%; height: 100%;"></canvas>
            </div>
            <button type="button" id="clearSignatureButton" class="button-secondary">Limpar Assinatura</button>
            <input type="hidden" name="signature_data" id="signatureDataInput">
        </fieldset>

        <input type="hidden" name="item_ids_for_donation" value="<?php echo htmlspecialchars(implode(',', $valid_item_ids_for_donation)); ?>">

        <div class="form-action-buttons-group" style="margin-top: 20px;">
            <a href="/home.php" class="button-secondary">Cancelar</a>
            <button type="submit" class="button-primary" id="submitDonationButton">Enviar para Aprovação</button>
        </div>
    </form>
</div>

<!-- Assuming signature_pad library is in /js/signature_pad.umd.min.js -->
<!-- If you downloaded it to a different path, adjust accordingly. -->
<!-- Make sure this path is correct relative to your web root. -->
<script src="/js/signature_pad.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // --- Input Masking Logic ---
    const cnpjInput = document.getElementById('institution_cnpj');
    if (cnpjInput) {
        cnpjInput.addEventListener('input', function (e) {
            let v = e.target.value.replace(/\D/g, ''); // Remove non-digits
            if (v.length > 14) v = v.slice(0, 14);
            v = v.replace(/^(\d{2})(\d)/, '$1.$2');
            v = v.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
            v = v.replace(/\.(\d{3})(\d)/, '.$1/$2');
            v = v.replace(/(\d{4})(\d)/, '$1-$2');
            e.target.value = v;
        });
    }

    const phoneInput = document.getElementById('institution_phone');
    if (phoneInput) {
        phoneInput.addEventListener('input', function (e) {
            let v = e.target.value.replace(/\D/g, '');
            if (v.length > 11) v = v.slice(0, 11);
            if (v.length > 10) { // 11 digits (XX) XXXXX-XXXX
                v = v.replace(/^(\d{2})(\d{5})(\d{4})$/, '($1) $2-$3');
            } else if (v.length > 6) { // 10 digits (XX) XXXX-XXXX
                v = v.replace(/^(\d{2})(\d{4})(\d{0,4})$/, '($1) $2-$3');
            } else if (v.length > 2) { // (XX) XXXX
                v = v.replace(/^(\d{2})(\d*)$/, '($1) $2');
            } else if (v.length > 0) { // (X
                v = v.replace(/^(\d*)$/, '($1');
            }
            e.target.value = v;
        });
    }

    const cepInput = document.getElementById('institution_address_cep');
    if (cepInput) {
        cepInput.addEventListener('input', function (e) {
            let v = e.target.value.replace(/\D/g, '');
            if (v.length > 8) v = v.slice(0, 8);
            v = v.replace(/^(\d{5})(\d)/, '$1-$2');
            e.target.value = v;
        });
    }

    const estadoInput = document.getElementById('institution_address_estado');
    if (estadoInput) {
        estadoInput.addEventListener('input', function (e) {
            e.target.value = e.target.value.toUpperCase().replace(/[^A-Z]/g, '').substring(0, 2);
        });
    }

    const numeroInput = document.getElementById('institution_address_number');
    if (numeroInput) {
        numeroInput.addEventListener('input', function (e) {
            e.target.value = e.target.value.replace(/\D/g, '');
        });
    }

    // --- Signature Pad Integration ---
    const canvas = document.getElementById('signatureCanvas');
    const signaturePadContainer = document.getElementById('signaturePadContainer');
    let signaturePad;

    function resizeCanvas() {
        if (canvas && signaturePadContainer) {
            const ratio =  Math.max(window.devicePixelRatio || 1, 1);
            canvas.width = signaturePadContainer.offsetWidth * ratio;
            canvas.height = signaturePadContainer.offsetHeight * ratio;
            canvas.getContext("2d").scale(ratio, ratio);
            if (signaturePad) {
                signaturePad.clear(); // Clear after resize
            }
        }
    }

    if (canvas) {
        signaturePad = new SignaturePad(canvas, {
            backgroundColor: 'rgb(255, 255, 255)' // White background
        });

        // Call resizeCanvas initially and on window resize
        // Debounce resize function to avoid performance issues
        let resizeTimeout;
        window.addEventListener("resize", function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(resizeCanvas, 250);
        });
        resizeCanvas(); // Initial resize

    } else {
        console.error("Signature canvas not found!");
    }


    const clearSignatureButton = document.getElementById('clearSignatureButton');
    if (clearSignatureButton && signaturePad) {
        clearSignatureButton.addEventListener('click', function() {
            signaturePad.clear();
        });
    }

    const donationForm = document.getElementById('donationForm');
    const signatureDataInput = document.getElementById('signatureDataInput');
    const submitButton = document.getElementById('submitDonationButton');

    if (donationForm && signatureDataInput && signaturePad) {
        donationForm.addEventListener('submit', function(event) {
            if (signaturePad.isEmpty()) {
                alert("Por favor, forneça a assinatura do responsável da instituição.");
                event.preventDefault(); // Prevent form submission
                // Re-enable button if it was disabled
                if(submitButton) submitButton.disabled = false;
                return false;
            }
            signatureDataInput.value = signaturePad.toDataURL('image/png');
        });
    }

    // Disable button briefly on submit to prevent double-clicks
    if(submitButton) {
        submitButton.addEventListener('click', function() {
            // A small delay to allow the form submission event to capture signature
            // or for the empty signature alert to fire.
            setTimeout(() => {
                if (!signaturePad.isEmpty() || !donationForm.checkValidity()) { // check form validity as well
                     // if signature is not empty OR form is invalid (which will prevent submission anyway)
                    this.disabled = true;
                }
                // If signature is empty, the submit listener should preventDefault and re-enable.
                // If form is invalid, browser will handle it.
            }, 50);
        });
    }
});
</script>

<?php
require_once __DIR__ . '/templates/footer.php';
?>
