document.addEventListener('DOMContentLoaded', function() {
    // Renderiza barcodes para os itens carregados inicialmente pelo PHP
    if (typeof initial_php_items !== 'undefined' && initial_php_items !== null && initial_php_items.length > 0) {
        initial_php_items.forEach(function(item) {
            if (item.barcode) {
                try {
                    const barcodeElement = document.getElementById("barcode-" + item.id);
                    if (barcodeElement) {
                        JsBarcode(barcodeElement, item.barcode, {
                            format: "CODE128",
                            lineColor: "#000",
                            width: 1.5,
                            height: 40,
                            displayValue: true,
                            fontSize: 12,
                            margin: 5
                        });
                    }
                } catch (e) {
                    console.error("Erro ao gerar barcode para o item inicial ID " + item.id + ": ", e);
                }
            }
        });
    }

    const filterForm = document.getElementById('filterForm');
    const itemListContainer = document.getElementById('itemListContainer');
    const clearFiltersButton = document.getElementById('clearFiltersButton');
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const devolverButton = document.getElementById('devolverButton');
    const doarButton = document.getElementById('doarButton');
    const itemNameInput = document.getElementById('filter_item_name');

    if (filterForm) {
        filterForm.addEventListener('submit', function(event) {
            event.preventDefault();
            fetchItemsAndUpdateList();
        });
    }

    if (clearFiltersButton) {
        clearFiltersButton.addEventListener('click', function(event) {
            event.preventDefault();
            if (filterForm) {
                filterForm.reset();
                const itemNameInput = document.getElementById('filter_item_name');
                if(itemNameInput) itemNameInput.value = '';
            }
            fetchItemsAndUpdateList(true);
        });
    }

    function fetchItemsAndUpdateList(fetchAll = false) {
        let params = new URLSearchParams();
        const currentFilterForm = document.getElementById('filterForm');

        if (!fetchAll && currentFilterForm) {
            const formData = new FormData(currentFilterForm);
            for (let pair of formData.entries()) {
                // pair[0] is the field name, pair[1] is its value
                if (pair[1] && pair[1].trim() !== '') { // Only append if value is not empty or just whitespace
                    if (pair[0] === 'filter_days_waiting') {
                        const parts = pair[1].split('-');
                        if (parts.length === 2) {
                            params.append('filter_days_waiting_min', parts[0].trim());
                            if (parts[1].trim() !== '9999') {
                                params.append('filter_days_waiting_max', parts[1].trim());
                            }
                            // Do not append filter_days_waiting itself
                        }
                    } else {
                        params.append(pair[0], pair[1].trim());
                    }
                }
            }
        }

        fetch('get_items_handler.php?' + params.toString(), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.statusText);
            }
            return response.json();
        })
        .then(data => {
            renderItems(data, itemListContainer, typeof current_user_is_admin !== 'undefined' && current_user_is_admin);
        })
        .catch(error => {
            console.error('Erro ao buscar itens via AJAX:', error);
            if (itemListContainer) {
                itemListContainer.innerHTML = '<p class="error-message">Erro ao carregar itens. Tente novamente mais tarde.</p>';
            }
        });
    }

    // Função renderItems CORRIGIDA
    function renderItems(itemsData, container, isAdmin) {
        if (!container) {
            console.error("Container para renderizar itens não encontrado.");
            return;
        }
        container.innerHTML = '';

        if (!itemsData || itemsData.length === 0) {
            container.innerHTML = '<p class="info-message">Nenhum item encontrado com os filtros atuais.</p>';
            return;
        }

        const table = document.createElement('table');
        table.className = 'admin-table';

        const thead = document.createElement('thead');
        let headerRowHtml = `
            <tr>
                <th></th> <!-- For Checkboxes -->
                <th>ID</th>
                <th>Status</th>
                <th>Nome</th>
                <th>Cód. Barras</th>
                <th>Imagem C.B.</th>
                <th>Categoria</th>
                <th>Local Encontrado</th>
                <th>Data Achado</th>
                <th>Dias Aguardando</th>
                <th>Registrado por</th>
                <th>Ações</th> <!-- Now always present -->
            </tr>`;
        thead.innerHTML = headerRowHtml;
        table.appendChild(thead);

        const tbody = document.createElement('tbody');
        itemsData.forEach(item => {
            const tr = document.createElement('tr');

            const formatDate = (dateStr) => {
                if (!dateStr) return 'N/A';
                const dateParts = dateStr.split('-');
                if (dateParts.length === 3) {
                    return `${dateParts[2]}/${dateParts[1]}/${dateParts[0]}`;
                }
                try {
                    const date = new Date(dateStr);
                    const correctedDate = new Date(date.valueOf() + date.getTimezoneOffset() * 60000 + (24*60*60000));
                    const day = String(correctedDate.getUTCDate()).padStart(2, '0');
                    const month = String(correctedDate.getUTCMonth() + 1).padStart(2, '0');
                    const year = correctedDate.getUTCFullYear();
                    return `${day}/${month}/${year}`;
                } catch (e) { return 'Data Inválida'; }
            };

            const formatDateTime = (dateTimeStr) => {
                if (!dateTimeStr) return 'N/A';
                 try {
                    const date = new Date(dateTimeStr);
                    const hours = String(date.getHours()).padStart(2, '0');
                    const minutes = String(date.getMinutes()).padStart(2, '0');
                    return `${formatDate(dateTimeStr.split(' ')[0])} ${hours}:${minutes}`;
                } catch (e) { return 'Data/Hora Inválida'; }
            };

            let statusCellContent = `
                <span class="item-status status-${escapeHTML(item.status).toLowerCase()}">
                    ${escapeHTML(item.status)}
                </span>`;

            let itemRowCellsHtml = `
                <td><input type="checkbox" class="item-checkbox" name="selected_items[]" value="${escapeHTML(item.id)}"></td>
                <td>${escapeHTML(item.id)}</td>
                <td>${statusCellContent}</td>
                <td>${escapeHTML(item.name)}</td>
                <td>${escapeHTML(item.barcode || 'N/A')}</td>
                <td>
                    ${item.barcode ? `<svg id="barcode-ajax-${escapeHTML(item.id)}" class="barcode-image"></svg>` : 'N/A'}
                </td>
                <td>${escapeHTML(item.category_name || 'N/A')} (${escapeHTML(item.category_code || 'N/A')})</td>
                <td>${escapeHTML(item.location_name || 'N/A')}</td>
                <td>${formatDate(item.found_date)}</td>
                <td>${escapeHTML(item.days_waiting === null || item.days_waiting === undefined ? '0' : item.days_waiting)} dias</td>
                <td>
                    ${escapeHTML(item.registered_by_username || 'Usuário Removido')}<br>
                    <small>em ${formatDateTime(item.registered_at)}</small>
                </td>
            `;

            // This replaces the old "if (isAdmin)" block for actions
            let actionsCellGeneratedHtml = '';
            if (item.status === 'Pendente') {
                actionsCellGeneratedHtml += `<button type="button" class="button-view-details button-secondary" data-itemid="${escapeHTML(item.id)}" title="Ver Detalhes do Item">Ver Detalhes</button>`;
                if (isAdmin) {
                    actionsCellGeneratedHtml += `
                        <a href="admin/edit_item_page.php?id=${escapeHTML(item.id)}" class="button-edit" title="Editar">Editar</a>
                        <form action="admin/delete_item_handler.php" method="POST" onsubmit="return confirm('Tem certeza que deseja excluir este item?');">
                            <input type="hidden" name="id" value="${escapeHTML(item.id)}">
                            <button type="submit" class="button-delete" title="Excluir">Excluir</button>
                        </form>`;
                }
            } else if (item.status === 'Devolvido') {
                if (item.devolution_document_id) {
                    actionsCellGeneratedHtml = `
                        <a href="manage_devolutions.php?view_id=${escapeHTML(item.devolution_document_id)}"
                           class="button-secondary"
                           title="Visualizar Termo de Devolução">Ver Termo</a>`;
                } else {
                    actionsCellGeneratedHtml = '<span style="color: #999; font-size:0.9em;">Termo Indisponível</span>';
                }
            } else if (item.status === 'Doado') {
                // Placeholder for "Ver Termo de Doação" - for now, an alert via JS onclick
                actionsCellGeneratedHtml = `<button type="button" class="button-secondary button-view-doacao-term" data-itemid="${escapeHTML(item.id)}" title="Visualizar Termo de Doação (Em Breve)">Ver Termo Doação</button>`;
            } else {
                actionsCellGeneratedHtml = '<span style="color: #999; font-size:0.9em;">---</span>';
            }
            itemRowCellsHtml += `<td class="actions-cell">${actionsCellGeneratedHtml}</td>`;
            tr.innerHTML = itemRowCellsHtml;
            tbody.appendChild(tr);
        });
        table.appendChild(tbody);
        container.appendChild(table);

        // Add event listeners to newly created checkboxes
        document.querySelectorAll('#itemListContainer .item-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', handleItemCheckboxChange);
        });
        updateActionButtonsState(); // Update button states after rendering
        if(selectAllCheckbox) selectAllCheckbox.checked = false; // Uncheck selectAll after re-render

        itemsData.forEach(item => {
            if (item.barcode) {
                try {
                    const barcodeElementAjax = document.getElementById("barcode-ajax-" + item.id);
                    if (barcodeElementAjax) {
                        JsBarcode(barcodeElementAjax, item.barcode, {
                            format: "CODE128",
                            lineColor: "#000",
                            width: 1.5,
                            height: 40,
                            displayValue: true,
                            fontSize: 12,
                            margin: 5
                        });
                    }
                } catch (e) {
                    console.error("Erro ao gerar barcode AJAX para o item ID " + item.id + ": ", e);
                }
            }
        });
    }

    function updateActionButtonsState() {
        if (!devolverButton || !doarButton) return; // Buttons might not exist on all pages
        const selectedCheckboxes = document.querySelectorAll('#itemListContainer .item-checkbox:checked');
        const anySelected = selectedCheckboxes.length > 0;

        devolverButton.disabled = !anySelected;
        doarButton.disabled = !anySelected;
    }

    function handleItemCheckboxChange() {
        updateActionButtonsState();
        if (selectAllCheckbox) {
            const allItemCheckboxes = document.querySelectorAll('#itemListContainer .item-checkbox');
            const allChecked = Array.from(allItemCheckboxes).every(cb => cb.checked);
            selectAllCheckbox.checked = allItemCheckboxes.length > 0 && allChecked;
        }
    }

    function handleSelectAllChange() {
        const itemCheckboxes = document.querySelectorAll('#itemListContainer .item-checkbox');
        itemCheckboxes.forEach(checkbox => {
            checkbox.checked = selectAllCheckbox.checked;
        });
        updateActionButtonsState();
    }

    function escapeHTML(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/[&<>"']/g, function (match) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            }[match];
        });
    }

    function debounce(func, delay) {
        let timeout;
        return function(...args) {
            const context = this;
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(context, args), delay);
        };
    }

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', handleSelectAllChange);
    }

    // Initial event listeners for checkboxes loaded by PHP
    document.querySelectorAll('#itemListContainer .item-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', handleItemCheckboxChange);
    });

    if (devolverButton) {
        devolverButton.addEventListener('click', function() {
            const selectedIds = Array.from(document.querySelectorAll('#itemListContainer .item-checkbox:checked'))
                                    .map(cb => cb.value);
            if (selectedIds.length > 0) {
                window.location.href = 'devolve_item_page.php?ids=' + selectedIds.join(',');
            } else {
                alert('Por favor, selecione ao menos um item para devolver.');
            }
        });
    }

    if (doarButton) {
        doarButton.addEventListener('click', function() {
            const selectedIds = Array.from(document.querySelectorAll('#itemListContainer .item-checkbox:checked'))
                                    .map(cb => cb.value);
            if (selectedIds.length > 0) {
                // current_user_is_admin is a global variable from PHP
                if (typeof current_user_is_admin !== 'undefined' && current_user_is_admin) {
                    alert('Funcionalidade "Doar" (para IDs: ' + selectedIds.join(',') + ') será implementada futuramente.');
                    // Later, this will call the actual donation handler
                } else {
                    alert('Você não tem permissão para executar esta ação.');
                }
            } else {
                alert('Por favor, selecione ao menos um item para doar.');
            }
        });
    }

    // Initial button state
    updateActionButtonsState();

    if (itemNameInput) {
        // Use false for fetchAll to ensure other filters are also applied
        itemNameInput.addEventListener('input', debounce(function() {
            fetchItemsAndUpdateList(false);
        }, 350)); // 350ms debounce delay
    }
});