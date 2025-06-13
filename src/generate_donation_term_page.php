<?php
require_once 'auth.php'; // Includes start_secure_session()
require_once 'db_connect.php';

// Ensure session is started (start_secure_session might be called in auth.php)
// If not, uncomment: start_secure_session();

require_admin('../index.php?error=pleaselogin'); // require_login() might be more appropriate if any admin can access

// Line 7 (or around here): Fix for FILTER_SANITIZE_STRING
$item_ids_str = $_GET['item_ids'] ?? ''; // Default to empty string if not set

$page_error_message = $_SESSION['generate_donation_page_error_message'] ?? null;
unset($_SESSION['generate_donation_page_error_message']);

$item_ids = [];
$valid_item_ids_for_donation = [];
$item_summary_by_category = [];
$total_items_for_donation = 0;

if (empty($item_ids_str)) {
    $_SESSION['home_page_error_message'] = "Nenhum item selecionado para doação.";
    header('Location: /home.php');
    exit();
}

$item_ids_array = explode(',', $item_ids_str);
foreach ($item_ids_array as $id_str_loop) { // Renamed $id to $id_str_loop to avoid conflict if register_globals is on (though unlikely)
    $id_int = filter_var(trim($id_str_loop), FILTER_VALIDATE_INT);
    if ($id_int !== false && $id_int > 0) {
        $item_ids[] = $id_int;
    }
}

if (empty($item_ids)) {
    $_SESSION['home_page_error_message'] = "IDs de itens inválidos fornecidos.";
    header('Location: /home.php');
    exit();
}

// Fetch item details (name, category) for valid, 'Pendente' items
if ($conn && !empty($item_ids)) {
    $placeholders = implode(',', array_fill(0, count($item_ids), '?'));
    $sql_items = "SELECT i.id, i.name AS item_name, c.name AS category_name
                  FROM items i
                  JOIN categories c ON i.category_id = c.id
                  WHERE i.id IN ($placeholders) AND i.status = 'Pendente'";

    $stmt_items = $conn->prepare($sql_items);
    if ($stmt_items) {
        $types = str_repeat('i', count($item_ids));
        $stmt_items->bind_param($types, ...$item_ids);
        $stmt_items->execute();
        $result_items = $stmt_items->get_result();

        while ($row = $result_items->fetch_assoc()) {
            $valid_item_ids_for_donation[] = $row['id'];
            if (!isset($item_summary_by_category[$row['category_name']])) {
                $item_summary_by_category[$row['category_name']] = 0;
            }
            $item_summary_by_category[$row['category_name']]++;
            $total_items_for_donation++;
        }
        $stmt_items->close();
    } else {
        error_log("DB Prepare Error (fetch items for donation): " . $conn->error);
        $page_error_message = "Erro ao buscar detalhes dos itens. Tente novamente.";
    }
} else if (!$conn) {
     error_log("DB Connection failed on generate_donation_term_page.");
     $page_error_message = "Erro de conexão com o banco de dados.";
}


if ($total_items_for_donation === 0 && empty($page_error_message)) {
    if (count($item_ids) > 0) { // If some IDs were passed but none were valid/Pendente
        $_SESSION['home_page_error_message'] = "Nenhum dos itens selecionados está disponível para doação (podem não estar 'Pendentes' ou IDs são inválidos).";
    } else { // Should have been caught by earlier checks
         $_SESSION['home_page_error_message'] = "Nenhum item válido para doação.";
    }
    header('Location: /home.php');
    exit();
}

// Sort summary by category name for consistent display
ksort($item_summary_by_category);

$item_summary_display = [];
foreach ($item_summary_by_category as $category => $count) {
    $item_summary_display[] = htmlspecialchars($count) . " - " . htmlspecialchars($category);
}
$item_summary_str = implode(', ', $item_summary_display);


$current_user_name = $_SESSION['username'] ?? 'N/A';
$current_date = date('Y-m-d');
$current_time = date('H:i');

require_once 'templates/header.php';
?>

<div class="container register-item-container">
    <h2>Registrar Termo de Doação</h2>

    <?php if ($page_error_message): ?>
        <p class="error-message"><?php echo htmlspecialchars($page_error_message); ?></p>
    <?php endif; ?>

    <?php if ($total_items_for_donation > 0): ?>
        <div class="data-section-rounded">
            <h4>Itens para Doação</h4>
            <p><?php echo $item_summary_str; ?>.</p>
            <p><strong>Total de itens: <?php echo $total_items_for_donation; ?></strong></p>
        </div>

        <form action="submit_donation_handler.php" method="POST" id="donationForm" class="form-modern">
            <fieldset class="data-section-rounded">
                <legend>Dados da Doação</legend>
                <div class="form-group">
                    <label for="responsible_donation">Responsável pela Doação (Sistema):</label>
                    <input type="text" id="responsible_donation" name="responsible_donation" value="<?php echo htmlspecialchars($current_user_name); ?>" required readonly class="form-control-readonly">
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

            <fieldset class="data-section-rounded">
                <legend>Instituição Recebedora</legend>
                 <div class="form-row">
                    <div class="form-group_col_full">
                        <label for="institution_name">Nome da Instituição:</label>
                        <input type="text" id="institution_name" name="institution_name" required class="form-control">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group_col">
                        <label for="institution_cnpj">CNPJ:</label>
                        <input type="text" id="institution_cnpj" name="institution_cnpj" class="form-control cnpj-mask">
                    </div>
                    <div class="form-group_col">
                        <label for="institution_ie">IE (Inscrição Estadual):</label>
                        <input type="text" id="institution_ie" name="institution_ie" class="form-control">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group_col">
                        <label for="institution_responsible_name">Nome do Responsável (Instituição):</label>
                        <input type="text" id="institution_responsible_name" name="institution_responsible_name" required class="form-control">
                    </div>
                    <div class="form-group_col">
                        <label for="institution_phone">Telefone (Instituição):</label>
                        <input type="text" id="institution_phone" name="institution_phone" class="form-control phone-mask">
                    </div>
                </div>
                <div class="form-row">
                     <div class="form-group_col form-group_col-large">
                        <label for="institution_address_street">Endereço (Rua/Av.):</label>
                        <input type="text" id="institution_address_street" name="institution_address_street" class="form-control">
                    </div>
                    <div class="form-group_col form-group_col-small">
                        <label for="institution_address_number">Número:</label>
                        <input type="text" id="institution_address_number" name="institution_address_number" class="form-control number-mask">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group_col">
                        <label for="institution_address_bairro">Bairro:</label>
                        <input type="text" id="institution_address_bairro" name="institution_address_bairro" class="form-control">
                    </div>
                    <div class="form-group_col">
                        <label for="institution_address_cidade">Cidade:</label>
                        <input type="text" id="institution_address_cidade" name="institution_address_cidade" class="form-control">
                    </div>
                    <div class="form-group_col">
                        <label for="institution_address_estado">Estado (UF):</label>
                        <input type="text" id="institution_address_estado" name="institution_address_estado" class="form-control estado-mask" maxlength="2">
                    </div>
                     <div class="form-group_col">
                        <label for="institution_address_cep">CEP:</label>
                        <input type="text" id="institution_address_cep" name="institution_address_cep" class="form-control cep-mask">
                    </div>
                </div>
            </fieldset>

            <fieldset class="data-section-rounded">
                <legend>Assinatura do Responsável da Instituição</legend>
                <p>Por favor, o responsável pela instituição deve assinar no quadro abaixo:</p>
                <div id="signaturePadContainer" style="border: 1px solid #ccc; max-width:400px; min-height:150px; height:200px; margin-bottom:10px; position: relative; touch-action: none;">
                    <canvas id="signatureCanvas" style="width: 100%; height: 100%; touch-action: none;"></canvas>
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
    <?php else: ?>
        <p>Não há itens válidos para este termo de doação. Por favor, <a href="/home.php">volte</a> e selecione itens pendentes.</p>
    <?php endif; ?>
</div>

<!-- 1. Load SignaturePad library FIRST -->
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>

<!-- 2. THEN include your custom script that uses SignaturePad -->
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
    let signaturePad = null;

    function resizeCanvas() {
        if (!canvas || !signaturePadContainer) return;
        const ratio =  Math.max(window.devicePixelRatio || 1, 1);

        const containerWidth = signaturePadContainer.offsetWidth;
        if (containerWidth === 0) {
            return;
        }

        canvas.width = containerWidth * ratio;
        canvas.height = signaturePadContainer.offsetHeight * ratio;
        canvas.getContext("2d").scale(ratio, ratio);
        if (signaturePad) {
            signaturePad.clear();
        } else {
            const ctx = canvas.getContext("2d");
            ctx.clearRect(0, 0, canvas.width, canvas.height);
        }
    }

    if (canvas) {
        setTimeout(function() {
            // Check if SignaturePad is loaded
            if (typeof SignaturePad === 'undefined') {
                console.error('SignaturePad library not loaded. Check script path or CDN.');
                // Optionally, display an error message to the user on the page
                const padContainer = document.getElementById('signaturePadContainer');
                if(padContainer) padContainer.innerHTML = '<p class="error-message" style="padding:10px;">Erro ao carregar o campo de assinatura. A biblioteca SignaturePad não foi encontrada.</p>';
                return;
            }

            signaturePad = new SignaturePad(canvas, {
                backgroundColor: 'rgb(255, 255, 255)'
            });
            console.log('Signature Pad initialized:', signaturePad);
            resizeCanvas();

            let resizeTimeout;
            window.addEventListener("resize", function() {
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(resizeCanvas, 250);
            });
        }, 100);

    } else {
        console.error("Signature canvas element not found!");
    }

    const clearSignatureButton = document.getElementById('clearSignatureButton');
    if (clearSignatureButton) {
        clearSignatureButton.addEventListener('click', function() {
            if (signaturePad) {
                signaturePad.clear();
            } else {
                console.error("Attempted to clear non-existent signature pad.");
            }
        });
    }

    const donationForm = document.getElementById('donationForm');
    const signatureDataInput = document.getElementById('signatureDataInput');
    const submitButton = document.getElementById('submitDonationButton');

    if (donationForm && signatureDataInput) {
        donationForm.addEventListener('submit', function(event) {
            if (!signaturePad || signaturePad.isEmpty()) {
                alert("Por favor, forneça a assinatura do responsável da instituição.");
                event.preventDefault();
                if(submitButton) submitButton.disabled = false; // Re-enable button if submission is blocked
                return false;
            }
            // Ensure signature data is captured before form proceeds
            signatureDataInput.value = signaturePad.toDataURL('image/png');
        });
    }

    // More robust submit button disabling logic
    if(donationForm && submitButton) {
        donationForm.addEventListener('submit', function(event) {
            // If the signature check above (or any other client-side validation) fails and calls event.preventDefault(),
            // this part might not be strictly necessary for the disabling effect,
            // but it's a good practice to disable on successful submission start.
            if (!event.defaultPrevented) { // Only disable if submission is not already prevented
                 submitButton.disabled = true;
                 // Optional: Add a message like "Processando..."
                 // submitButton.textContent = 'Processando...';
            }
        });
    }
});
</script>

<?php require_once 'templates/footer.php'; ?>
