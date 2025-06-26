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
            // Wrap in setTimeout to allow DOM to update from reset before fetching/re-rendering results
            setTimeout(() => {
                fetchItemsAndUpdateList(true);
            }, 50); // 50ms delay, can be adjusted
        });
    }

    function fetchItemsAndUpdateList(fetchAll = false) {
        let params = new URLSearchParams();
        const currentFilterForm = document.getElementById('filterForm');

        if (!fetchAll && currentFilterForm) {
            const formData = new FormData(currentFilterForm);
            for (let pair of formData.entries()) {
                if (pair[1] && pair[1].trim() !== '') {
                    if (pair[0] === 'filter_days_waiting') {
                        const parts = pair[1].split('-');
                        if (parts.length === 2) {
                            params.append('filter_days_waiting_min', parts[0].trim());
                            if (parts[1].trim() !== '9999') {
                                params.append('filter_days_waiting_max', parts[1].trim());
                            }
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
            // A variável global 'current_user_is_admin' é definida em home.php
            renderItems(data, itemListContainer, typeof current_user_is_admin !== 'undefined' && current_user_is_admin);
        })
        .catch(error => {
            console.error('Erro ao buscar itens via AJAX:', error);
            if (itemListContainer) {
                itemListContainer.innerHTML = '<p class="error-message">Erro ao carregar itens. Tente novamente mais tarde.</p>';
            }
        });
    }

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

        const colgroup = document.createElement('colgroup');
        colgroup.innerHTML = `
            <col style="width: 30px;">
            <col style="width: 50px;">
            <col style="width: 120px;">
            <col style="width: 15%;">
            <col style="width: 12%;">
            <col style="width: 150px;">
            <col style="width: 10%;">
            <col style="width: 15%;">
            <col style="width: 100px;">
            <col style="width: 120px;">
            <col style="width: 12%;">
            <col style="width: 170px;">
        `;
        table.appendChild(colgroup);

        const thead = document.createElement('thead');
        thead.innerHTML = `
            <tr>
                <th class="checkbox-cell"></th>
                <th>ID</th>
                <th>Status</th>
                <th class="truncate-text">Nome</th>
                <th class="truncate-text">Cód. Barras</th>
                <th>Imagem C.B.</th>
                <th class="truncate-text">Categoria</th>
                <th class="truncate-text">Local Encontrado</th>
                <th>Data Achado</th>
                <th>Dias Aguardando</th>
                <th class="truncate-text">Registrado por</th>
                <th>Ações</th>
            </tr>`;
        table.appendChild(thead);

        const tbody = document.createElement('tbody');
        itemsData.forEach(item => {
            const tr = document.createElement('tr');

            const formatDate = (dateStr) => {
                if (!dateStr) return 'N/A';
                try {
                    const date = new Date(dateStr);
                    // Adicionar ajuste de fuso horário para corrigir datas que aparecem um dia antes
                    const correctedDate = new Date(date.valueOf() + date.getTimezoneOffset() * 60000);
                    const day = String(correctedDate.getUTCDate()).padStart(2, '0');
                    const month = String(correctedDate.getUTCMonth() + 1).padStart(2, '0');
                    const year = correctedDate.getUTCFullYear();
                    if (isNaN(year)) return 'Data Inválida';
                    return `${day}/${month}/${year}`;
                } catch (e) { return 'Data Inválida'; }
            };
            
            let statusClassNameFinal = 'desconhecido';
            if (item.status) {
                let classNameNormalized = String(item.status).toLowerCase();
                const charMapSimple = { 'á': 'a', 'à': 'a', 'â': 'a', 'ã': 'a', 'ä': 'a', 'å': 'a', 'é': 'e', 'è': 'e', 'ê': 'e', 'ë': 'e', 'í': 'i', 'ì': 'i', 'î': 'i', 'ï': 'i', 'ó': 'o', 'ò': 'o', 'ô': 'o', 'õ': 'o', 'ö': 'o', 'ú': 'u', 'ù': 'u', 'û': 'u', 'ü': 'u', 'ç': 'c', 'ñ': 'n' };
                classNameNormalized = classNameNormalized.replace(/[áàâãäåéèêëíìîïóòôõöúùûüçñ]/g, m => charMapSimple[m]);
                classNameNormalized = classNameNormalized.replace(/[^a-z0-9\s-]/g, '');
                classNameNormalized = classNameNormalized.replace(/[\s-]+/g, '-');
                statusClassNameFinal = classNameNormalized.replace(/^-+|-+$/g, '');
                if (!statusClassNameFinal) statusClassNameFinal = 'desconhecido';
            }
            const statusCellContent = `<span class="item-status status-${escapeHTML(statusClassNameFinal)}">${escapeHTML(item.status || 'N/A')}</span>`;

            const registeredByDisplayName = (item.registered_by_full_name && String(item.registered_by_full_name).trim() !== '') ? item.registered_by_full_name : item.registered_by_username;
            const registeredByText = `${escapeHTML(registeredByDisplayName || 'Usuário Removido')}`;

            let cellsHtml = `
                <td class="checkbox-cell"><input type="checkbox" class="item-checkbox" name="selected_items[]" value="${escapeHTML(item.id)}"></td>
                <td>${escapeHTML(item.id)}</td>
                <td class="status-cell">${statusCellContent}</td>
                <td class="truncate-text">${escapeHTML(item.name)}</td>
                <td class="truncate-text">${escapeHTML(item.barcode || 'N/A')}</td>
                <td>${item.barcode ? `<svg id="barcode-ajax-${escapeHTML(item.id)}" class="barcode-image"></svg>` : 'N/A'}</td>
                <td>${escapeHTML(item.category_name || 'N/A')} (${escapeHTML(item.category_code || 'N/A')})</td>
                <td>${escapeHTML(item.location_name || 'N/A')}</td>
                <td>${formatDate(item.found_date)}</td>
                <td>${escapeHTML(item.days_waiting === null || item.days_waiting === undefined ? '0' : item.days_waiting)} dias</td>
                <td class="truncate-text">${registeredByText}</td>
            `;

            let actionsContentHtml = '';
            actionsContentHtml += `<button type="button" class="button-details button-details-home" data-description="${escapeHTML(item.description || '')}" data-itemid="${escapeHTML(item.id)}" title="Ver Descrição">Ver Descrição</button>`;

            if (item.status === 'Pendente' && isAdmin) {
                actionsContentHtml += `<a href="admin/edit_item_page.php?id=${escapeHTML(item.id)}" class="button-edit" title="Editar">Editar</a>`;
                actionsContentHtml += `
                    <form action="admin/delete_item_handler.php" method="POST" onsubmit="return confirm('Tem certeza que deseja excluir este item?');" class="delete-form">
                        <input type="hidden" name="id" value="${escapeHTML(item.id)}">
                        <button type="submit" class="button-delete" title="Excluir">Excluir</button>
                    </form>`;
            } else if (item.status === 'Devolvido' && item.devolution_document_id) {
                actionsContentHtml += `<a href="manage_devolutions.php?view_id=${escapeHTML(item.devolution_document_id)}" class="button-secondary button-ver-termo" title="Visualizar Termo de Devolução">Ver Termo</a>`;
            } else if (item.status === 'Doado' && item.donation_document_id) {
                actionsContentHtml += `<a href="manage_donations.php?view_id=${escapeHTML(item.donation_document_id)}" class="button-secondary button-ver-termo" title="Visualizar Termo de Doação">Ver Termo Doação</a>`;
            } else {
                 if (item.status === 'Devolvido' && !item.devolution_document_id) {
                    actionsContentHtml += '<span style="color: #999; font-size:0.9em;">Termo Indisponível</span>';
                } else if (item.status === 'Doado' && !item.donation_document_id) {
                    actionsContentHtml += '<span style="color: #999; font-size:0.9em;">Termo Indisponível</span>';
                }
            }
            
            // CORREÇÃO: Adiciona o wrapper para garantir o layout correto dos botões.
            cellsHtml += `
                <td class="actions-cell home-actions-cell">
                    <div class="actions-wrapper">
                        ${actionsContentHtml}
                    </div>
                </td>`;

            tr.innerHTML = cellsHtml;
            tbody.appendChild(tr);
        });
        table.appendChild(tbody);
        container.appendChild(table);

        // Adiciona os event listeners aos elementos recém-criados
        document.querySelectorAll('#itemListContainer .item-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', handleItemCheckboxChange);
        });

        // Adiciona os listeners para os botões "Ver Descrição"
        document.querySelectorAll('.button-details-home').forEach(button => {
            button.addEventListener('click', function() {
                const modalHome = document.getElementById('itemDetailModal');
                const modalTextElementHome = document.getElementById('modalItemDescriptionTextHome');
                if (modalHome && modalTextElementHome) {
                    const description = this.dataset.description;
                    modalTextElementHome.textContent = (description && description.trim() !== '') ? description : 'Sem Detalhes de descrição.';
                    modalHome.style.display = 'block';
                }
            });
        });

        updateActionButtonsState();
        if(selectAllCheckbox) selectAllCheckbox.checked = false;

        // Gera os barcodes para os itens carregados via AJAX
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
        if (!devolverButton || !doarButton) return;
        const selectedCheckboxes = document.querySelectorAll('#itemListContainer .item-checkbox:checked');
        const anySelected = selectedCheckboxes.length > 0;
        devolverButton.disabled = !anySelected;
        doarButton.disabled = !anySelected;
    }

    function handleItemCheckboxChange() {
        updateActionButtonsState();
        if (selectAllCheckbox) {
            const allItemCheckboxes = document.querySelectorAll('#itemListContainer .item-checkbox');
            const allChecked = allItemCheckboxes.length > 0 && Array.from(allItemCheckboxes).every(cb => cb.checked);
            selectAllCheckbox.checked = allChecked;
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
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[match];
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

    // Event listeners para os checkboxes carregados inicialmente
    document.querySelectorAll('#itemListContainer .item-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', handleItemCheckboxChange);
    });

    if (devolverButton) {
        devolverButton.addEventListener('click', function() {
            const selectedIds = Array.from(document.querySelectorAll('#itemListContainer .item-checkbox:checked')).map(cb => cb.value);
            if (selectedIds.length > 0) {
                window.location.href = 'devolve_item_page.php?ids=' + selectedIds.join(',');
            } else {
                alert('Por favor, selecione ao menos um item para devolver.');
            }
        });
    }

    if (doarButton) {
        doarButton.addEventListener('click', function() {
            const selectedIds = Array.from(document.querySelectorAll('#itemListContainer .item-checkbox:checked')).map(cb => cb.value);
            if (selectedIds.length > 0) {
                if (typeof current_user_is_admin !== 'undefined' && current_user_is_admin) {
                    window.location.href = 'generate_donation_term_page.php?item_ids=' + selectedIds.join(',');
                } else {
                    alert('Você não tem permissão para executar esta ação.');
                }
            } else {
                alert('Por favor, selecione ao menos um item para doar.');
            }
        });
    }
    
    if (itemNameInput) {
        itemNameInput.addEventListener('input', debounce(function() {
            fetchItemsAndUpdateList(false);
        }, 350));
    }

    updateActionButtonsState();
});