/**
 * JavaScript pour l'éditeur de configuration FAB-DIS
 */

(function() {
    'use strict';

    /**
     * Fonction helper pour accéder aux traductions
     */
    function t(category, key, params) {
        if (typeof pkoaiTranslations === 'undefined') {
            return key;
        }
        var translation = pkoaiTranslations[category] && pkoaiTranslations[category][key];
        if (!translation) {
            return key;
        }
        if (params !== undefined) {
            if (!Array.isArray(params)) {
                params = [params];
            }
            params.forEach(function(param) {
                translation = translation.replace('%s', param);
            });
        }
        return translation;
    }

    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    var currentConfig = null;
    var currentMappingIndex = null;
    var currentKeyValueCallback = null;
    var activePipeline = null; // HTMLElement of active sub-pipeline, or null for main
    var draggedRow = null;
    var dragPlaceholder = null;
    var autoScrollInterval = null;
    var lastMouseY = 0;
    var SCROLL_ZONE = 100; // pixels du bord pour déclencher le scroll
    var SCROLL_SPEED = 15; // pixels par tick

    /**
     * Initialisation
     */
    function init() {
        // Vérifier qu'on est sur la page de configuration
        if (typeof pkoaiConfigUrls === 'undefined' || typeof prestashopColumns === 'undefined') {
            return;
        }

        // Charger la config initiale si présente
        if (typeof initialConfig !== 'undefined' && initialConfig) {
            currentConfig = initialConfig;
            renderMappings();
            renderSheets();
            renderAiConfig();
        } else {
            // Nouvelle configuration
            initNewConfig();
        }

        // Initialiser l'éditeur JSON avec coloration
        initJsonEditor();

        // Event listeners
        bindEvents();

        // Vérifier les dépendances de modules
        checkModuleDependencies();
    }

    /**
     * Charge les valeurs de configuration IA
     */
    function renderAiConfig() {
        if (!currentConfig) return;

        var cacheCheckbox = document.getElementById('config-llm-cache');
        var contextTextarea = document.getElementById('config-llm-context');

        if (cacheCheckbox) {
            cacheCheckbox.checked = currentConfig.llm_cache_context || false;
        }
        if (contextTextarea) {
            var context = currentConfig.llm_global_context;
            if (context) {
                if (typeof context === 'object') {
                    contextTextarea.value = JSON.stringify(context, null, 2);
                } else {
                    contextTextarea.value = context;
                }
            } else {
                contextTextarea.value = '';
            }
        }
    }

    /**
     * Vérifie les dépendances de modules et affiche un avertissement si nécessaire
     */
    function checkModuleDependencies() {
        if (!pkoaiConfigUrls.moduleDependencies) {
            return;
        }

        fetch(pkoaiConfigUrls.moduleDependencies)
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (!data.success || !data.dependencies) {
                    return;
                }

                var missingModules = [];
                var deps = data.dependencies;

                // Vérifier chaque dépendance
                for (var key in deps) {
                    if (deps.hasOwnProperty(key) && !deps[key].installed) {
                        missingModules.push({
                            module: deps[key].module,
                            description: deps[key].description
                        });
                    }
                }

                // Afficher l'avertissement si des modules manquent
                if (missingModules.length > 0) {
                    var alertDiv = document.getElementById('module-dependencies-alert');
                    var listEl = document.getElementById('module-dependencies-list');

                    if (alertDiv && listEl) {
                        listEl.innerHTML = '';
                        missingModules.forEach(function(mod) {
                            var li = document.createElement('li');
                            li.innerHTML = '<strong>' + mod.module + '</strong> : ' + mod.description;
                            listEl.appendChild(li);
                        });
                        alertDiv.style.display = 'block';
                    }
                }
            })
            .catch(function(error) {
                console.error('Error checking dependencies:', error);
            });
    }

    /**
     * Initialisation de l'éditeur JSON avec coloration syntaxique
     */
    function initJsonEditor() {
        var editor = document.getElementById('config-json');
        var rawTextarea = document.getElementById('config-json-raw');

        if (!editor) return;

        // Charger le contenu initial depuis le textarea caché
        if (rawTextarea && rawTextarea.value) {
            editor.innerHTML = highlightJson(rawTextarea.value);
        }

        // Coloration à la perte de focus (pour ne pas perdre le curseur pendant l'édition)
        editor.addEventListener('blur', function() {
            var text = getEditorText();
            editor.innerHTML = highlightJson(text);
        });

        // Support du Tab pour l'indentation
        editor.addEventListener('keydown', function(e) {
            if (e.key === 'Tab') {
                e.preventDefault();
                document.execCommand('insertText', false, '  ');
            }
        });
    }

    /**
     * Récupération du texte brut de l'éditeur
     */
    function getEditorText() {
        var editor = document.getElementById('config-json');
        if (!editor) return '';

        // Créer un élément temporaire pour extraire le texte sans HTML
        var temp = document.createElement('div');
        temp.innerHTML = editor.innerHTML;

        // Remplacer les br par des retours à la ligne
        temp.querySelectorAll('br').forEach(function(br) {
            br.replaceWith('\n');
        });

        // Remplacer les div par des retours à la ligne (Chrome ajoute des div)
        temp.querySelectorAll('div').forEach(function(div) {
            div.before('\n');
            div.replaceWith(div.textContent);
        });

        return temp.textContent || temp.innerText || '';
    }

    /**
     * Définition du texte de l'éditeur avec coloration
     */
    function setEditorText(text) {
        var editor = document.getElementById('config-json');
        if (!editor) return;

        editor.innerHTML = highlightJson(text);
    }

    /**
     * Mise à jour de la coloration JSON
     */
    function updateJsonHighlighting() {
        var editor = document.getElementById('config-json');
        if (!editor) return;

        var text = getEditorText();
        editor.innerHTML = highlightJson(text);
    }

    /**
     * Coloration syntaxique JSON
     */
    function highlightJson(json) {
        if (!json) return '';

        // Échapper les caractères HTML
        var escaped = json
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');

        // Tokeniser et colorier
        var result = '';
        var i = 0;
        var inString = false;
        var stringStart = 0;

        while (i < escaped.length) {
            var char = escaped[i];

            // Début ou fin de string
            if (char === '"' && (i === 0 || escaped[i-1] !== '\\')) {
                if (!inString) {
                    inString = true;
                    stringStart = i;
                } else {
                    inString = false;
                    var str = escaped.substring(stringStart, i + 1);
                    // Vérifier si c'est une clé (suivie de :)
                    var rest = escaped.substring(i + 1);
                    if (/^\s*:/.test(rest)) {
                        result += '<span class="json-key">' + str + '</span>';
                    } else {
                        result += '<span class="json-string">' + str + '</span>';
                    }
                    i++;
                    continue;
                }
            }

            if (!inString) {
                // Nombres
                if (/[-\d]/.test(char)) {
                    var numMatch = escaped.substring(i).match(/^-?\d+\.?\d*([eE][+-]?\d+)?/);
                    if (numMatch) {
                        result += '<span class="json-number">' + numMatch[0] + '</span>';
                        i += numMatch[0].length;
                        continue;
                    }
                }
                // Booléens et null
                if (char === 't' && escaped.substring(i, i+4) === 'true') {
                    result += '<span class="json-boolean">true</span>';
                    i += 4;
                    continue;
                }
                if (char === 'f' && escaped.substring(i, i+5) === 'false') {
                    result += '<span class="json-boolean">false</span>';
                    i += 5;
                    continue;
                }
                if (char === 'n' && escaped.substring(i, i+4) === 'null') {
                    result += '<span class="json-null">null</span>';
                    i += 4;
                    continue;
                }
                // Brackets
                if (/[{}\[\]]/.test(char)) {
                    result += '<span class="json-bracket">' + char + '</span>';
                    i++;
                    continue;
                }
                // Autres caractères
                result += char;
            }

            i++;
        }

        return result;
    }

    /**
     * Bindage des événements
     */
    function bindEvents() {
        // Sauvegarde
        var saveBtn = document.getElementById('save-config-btn');
        if (saveBtn) saveBtn.addEventListener('click', saveConfig);

        // Suppression
        var deleteBtn = document.getElementById('delete-config-btn');
        if (deleteBtn) deleteBtn.addEventListener('click', deleteConfig);

        // Export
        var exportBtn = document.getElementById('export-config-btn');
        if (exportBtn) exportBtn.addEventListener('click', exportConfig);

        // Ajout de mapping
        var addMappingBtn = document.getElementById('add-mapping-btn');
        if (addMappingBtn) addMappingBtn.addEventListener('click', addMapping);

        // Configuration des feuilles
        var addSheetBtn = document.getElementById('add-sheet-btn');
        if (addSheetBtn) addSheetBtn.addEventListener('click', addSheet);

        // Primary sheet et join_key
        var primarySheetInput = document.getElementById('config-primary-sheet');
        var joinKeyInput = document.getElementById('config-join-key');
        if (primarySheetInput) primarySheetInput.addEventListener('change', updateSheetsConfig);
        if (joinKeyInput) joinKeyInput.addEventListener('change', updateSheetsConfig);

        // Toggle du panneau sheets
        var sheetsPanel = document.getElementById('sheets-config-panel');
        if (sheetsPanel) {
            sheetsPanel.addEventListener('show.bs.collapse', function() {
                var chevron = this.previousElementSibling.querySelector('.pull-right');
                if (chevron) chevron.style.transform = 'rotate(180deg)';
            });
            sheetsPanel.addEventListener('hide.bs.collapse', function() {
                var chevron = this.previousElementSibling.querySelector('.pull-right');
                if (chevron) chevron.style.transform = 'rotate(0deg)';
            });
        }

        // Sauvegarde d'action (pipeline)
        var saveActionBtn = document.getElementById('save-action-btn');
        if (saveActionBtn) saveActionBtn.addEventListener('click', saveAction);

        // Clear all actions
        var clearActionsBtn = document.getElementById('clear-actions-btn');
        if (clearActionsBtn) clearActionsBtn.addEventListener('click', function() {
            document.getElementById('pipeline-list').innerHTML = '';
            activePipeline = null;
            updatePipelineEmptyState();
        });

        // Condition toggle
        // When clicking an action grid button, add to active sub-pipeline or main pipeline

        // Gestion keyvalue
        var addKeyValueBtn = document.getElementById('add-keyvalue-btn');
        if (addKeyValueBtn) addKeyValueBtn.addEventListener('click', addKeyValueRow);
        var saveKeyValueBtn = document.getElementById('save-keyvalue-btn');
        if (saveKeyValueBtn) saveKeyValueBtn.addEventListener('click', saveKeyValue);

        // Synchronisation JSON <-> Visuel
        var tabJson = document.querySelector('a[href="#tab-json"]');
        var tabVisual = document.querySelector('a[href="#tab-visual"]');
        if (tabJson) tabJson.addEventListener('shown.bs.tab', syncToJson);
        if (tabVisual) tabVisual.addEventListener('shown.bs.tab', syncFromJson);

        // Changements dans les métadonnées
        var fournisseurInput = document.getElementById('config-fournisseur');
        var typeSelect = document.getElementById('config-type');
        if (fournisseurInput) fournisseurInput.addEventListener('change', updateConfigMeta);
        if (typeSelect) typeSelect.addEventListener('change', updateConfigMeta);

        // Recherche de colonne
        var columnSearch = document.getElementById('column-search');
        if (columnSearch) {
            columnSearch.addEventListener('input', filterColumns);
            columnSearch.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    this.value = '';
                    filterColumns();
                }
            });
        }

        // Bouton effacer recherche
        var clearSearchBtn = document.getElementById('clear-search');
        if (clearSearchBtn) {
            clearSearchBtn.addEventListener('click', function() {
                var searchInput = document.getElementById('column-search');
                if (searchInput) {
                    searchInput.value = '';
                    filterColumns();
                }
            });
        }

        // Masquer colonnes vides
        var hideEmptyCheckbox = document.getElementById('hide-empty-columns');
        if (hideEmptyCheckbox) {
            hideEmptyCheckbox.addEventListener('change', filterColumns);
        }

        // Modal ajout de colonne
        var addColumnSearch = document.getElementById('add-column-search');
        if (addColumnSearch) {
            addColumnSearch.addEventListener('input', filterAddColumnOptions);
            addColumnSearch.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    var select = document.getElementById('add-column-select');
                    if (select.options.length === 1) {
                        select.selectedIndex = 0;
                        document.getElementById('confirm-add-column-btn').disabled = false;
                        confirmAddColumn();
                    } else if (select.selectedIndex >= 0) {
                        confirmAddColumn();
                    }
                }
            });
        }

        var addColumnSelect = document.getElementById('add-column-select');
        if (addColumnSelect) {
            addColumnSelect.addEventListener('change', function() {
                var confirmBtn = document.getElementById('confirm-add-column-btn');
                confirmBtn.disabled = !this.value;
            });
            addColumnSelect.addEventListener('dblclick', function() {
                if (this.value) {
                    confirmAddColumn();
                }
            });
        }

        var confirmAddColumnBtn = document.getElementById('confirm-add-column-btn');
        if (confirmAddColumnBtn) {
            confirmAddColumnBtn.addEventListener('click', confirmAddColumn);
        }

        // Ajouter toutes les colonnes
        var addAllColumnsBtn = document.getElementById('add-all-columns-btn');
        if (addAllColumnsBtn) {
            addAllColumnsBtn.addEventListener('click', function(e) {
                e.preventDefault();
                addAllColumns();
            });
        }

        // Supprimer les colonnes vides
        var removeEmptyColumnsBtn = document.getElementById('remove-empty-columns-btn');
        if (removeEmptyColumnsBtn) {
            removeEmptyColumnsBtn.addEventListener('click', function(e) {
                e.preventDefault();
                removeEmptyColumns();
            });
        }

        // Configuration IA
        var llmCacheCheckbox = document.getElementById('config-llm-cache');
        if (llmCacheCheckbox) {
            llmCacheCheckbox.addEventListener('change', function() {
                if (currentConfig) {
                    currentConfig.llm_cache_context = this.checked;
                }
            });
        }

        var llmContextTextarea = document.getElementById('config-llm-context');
        if (llmContextTextarea) {
            llmContextTextarea.addEventListener('change', function() {
                if (currentConfig) {
                    var value = this.value.trim();
                    if (value) {
                        // Essayer de parser comme JSON, sinon garder comme string
                        try {
                            currentConfig.llm_global_context = JSON.parse(value);
                        } catch (e) {
                            currentConfig.llm_global_context = value;
                        }
                    } else {
                        delete currentConfig.llm_global_context;
                    }
                }
            });
        }
    }

    /**
     * Filtre les colonnes selon la recherche et l'option de masquage
     */
    function filterColumns() {
        var searchInput = document.getElementById('column-search');
        var hideEmptyCheckbox = document.getElementById('hide-empty-columns');
        var countBadge = document.getElementById('column-count');

        var search = (searchInput ? searchInput.value.toLowerCase() : '');
        var hideEmpty = (hideEmptyCheckbox ? hideEmptyCheckbox.checked : false);

        var rows = document.querySelectorAll('#mapping-tbody tr');
        var visibleCount = 0;
        var totalCount = rows.length;
        var firstMatch = null;

        rows.forEach(function(row) {
            var psColumnSelect = row.querySelector('select.ps-column-select');

            var colName = psColumnSelect ? psColumnSelect.value : '';

            // Check emptiness from the actual mapping data, not DOM inputs
            var mapping = (currentConfig && currentConfig.mapping && colName) ? currentConfig.mapping[colName] : null;
            var hasCol = mapping && mapping.col && mapping.col.trim() !== '';
            var hasDefault = mapping && mapping.default !== undefined && mapping.default !== '';
            var hasActions = mapping && ((mapping.actions && mapping.actions.length > 0) || (mapping.action && mapping.action.type));

            var isEmpty = !hasCol && !hasDefault && !hasActions;

            // Vérifier si correspond à la recherche
            var matchesSearch = !search || colName.toLowerCase().includes(search);

            // Décider de l'affichage
            var shouldShow = matchesSearch && (!hideEmpty || !isEmpty);

            row.style.display = shouldShow ? '' : 'none';

            if (shouldShow) {
                visibleCount++;
                if (!firstMatch && search && matchesSearch) {
                    firstMatch = row;
                }
            }
        });

        // Mettre à jour le compteur
        if (countBadge) {
            if (search || hideEmpty) {
                countBadge.textContent = visibleCount + '/' + totalCount;
                countBadge.style.display = '';
            } else {
                countBadge.style.display = 'none';
            }
        }

        // Scroll vers la première correspondance si recherche active
        if (firstMatch && search) {
            firstMatch.scrollIntoView({ behavior: 'smooth', block: 'center' });
            // Highlight temporaire
            firstMatch.style.backgroundColor = '#fff3cd';
            setTimeout(function() {
                firstMatch.style.backgroundColor = '';
            }, 1500);
        }
    }

    /**
     * Initialisation d'une nouvelle configuration (quand pas de config sélectionnée)
     */
    function initNewConfig() {
        if (!currentConfig) {
            currentConfig = {
                fournisseur: '',
                type: 'FAB-DIS',
                mapping: {},
                sheets: {}
            };
            setEditorText(JSON.stringify(currentConfig, null, 2));
        }
        // Rendre les feuilles configurées
        renderSheets();
    }

    /**
     * Rendu de la table des feuilles
     */
    function renderSheets() {
        var tbody = document.getElementById('sheets-tbody');
        if (!tbody) return;

        tbody.innerHTML = '';

        if (!currentConfig) return;

        // Initialiser sheets si absent
        if (!currentConfig.sheets) {
            currentConfig.sheets = {};
        }

        var sheetsKeys = Object.keys(currentConfig.sheets);

        // Mettre à jour le badge
        var badge = document.getElementById('sheets-count');
        if (badge) {
            badge.textContent = sheetsKeys.length;
        }

        // Mettre à jour les champs primary_sheet et join_key
        var primarySheetInput = document.getElementById('config-primary-sheet');
        var joinKeyInput = document.getElementById('config-join-key');
        if (primarySheetInput && currentConfig.primary_sheet) {
            primarySheetInput.value = currentConfig.primary_sheet;
        }
        if (joinKeyInput && currentConfig.join_key) {
            joinKeyInput.value = currentConfig.join_key;
        }

        sheetsKeys.forEach(function(sheetName) {
            var sheetConfig = currentConfig.sheets[sheetName];
            var row = createSheetRow(sheetName, sheetConfig);
            tbody.appendChild(row);
        });
    }

    /**
     * Création d'une ligne de feuille
     */
    function createSheetRow(sheetName, sheetConfig) {
        var tr = document.createElement('tr');
        tr.dataset.sheetName = sheetName;

        // Nom de la feuille
        var tdName = document.createElement('td');
        var inputName = document.createElement('input');
        inputName.type = 'text';
        inputName.className = 'form-control input-sm sheet-name';
        inputName.value = sheetName;
        inputName.placeholder = 'Ex: B03_MEDIA';
        inputName.addEventListener('change', function() {
            renameSheet(sheetName, this.value);
        });
        tdName.appendChild(inputName);
        tr.appendChild(tdName);

        // Relation (one/many)
        var tdRelation = document.createElement('td');
        var selectRelation = document.createElement('select');
        selectRelation.className = 'form-control input-sm sheet-relation';
        selectRelation.innerHTML = '<option value="one">one</option><option value="many">many</option>';
        selectRelation.value = sheetConfig.relation || 'one';
        selectRelation.addEventListener('change', function() {
            sheetConfig.relation = this.value;
        });
        tdRelation.appendChild(selectRelation);
        tr.appendChild(tdRelation);

        // Colonne de jointure
        var tdJoinCol = document.createElement('td');
        var inputJoinCol = document.createElement('input');
        inputJoinCol.type = 'text';
        inputJoinCol.className = 'form-control input-sm sheet-join-col';
        inputJoinCol.value = sheetConfig.join_col || '';
        inputJoinCol.placeholder = 'Ex: B';
        inputJoinCol.addEventListener('change', function() {
            sheetConfig.join_col = this.value || undefined;
        });
        tdJoinCol.appendChild(inputJoinCol);
        tr.appendChild(tdJoinCol);

        // Bouton suppression
        var tdDelete = document.createElement('td');
        var btnDelete = document.createElement('button');
        btnDelete.type = 'button';
        btnDelete.className = 'btn btn-danger btn-xs';
        btnDelete.innerHTML = '<i class="icon-trash"></i>';
        btnDelete.addEventListener('click', function() {
            deleteSheet(sheetName);
        });
        tdDelete.appendChild(btnDelete);
        tr.appendChild(tdDelete);

        return tr;
    }

    /**
     * Ajout d'une nouvelle feuille
     */
    function addSheet() {
        if (!currentConfig) return;
        if (!currentConfig.sheets) currentConfig.sheets = {};

        // Générer un nom unique
        var baseName = 'FEUILLE';
        var counter = 1;
        var newName = baseName;
        while (currentConfig.sheets[newName]) {
            newName = baseName + '_' + counter++;
        }

        currentConfig.sheets[newName] = {
            relation: 'many',
            join_col: 'B'
        };

        renderSheets();
    }

    /**
     * Renommage d'une feuille
     */
    function renameSheet(oldName, newName) {
        if (!currentConfig || !currentConfig.sheets) return;
        if (!newName || newName === oldName) return;

        // Vérifier si le nouveau nom existe déjà
        if (currentConfig.sheets[newName]) {
            alert(t('configEditor', 'sheetExists'));
            renderSheets();
            return;
        }

        // Renommer
        currentConfig.sheets[newName] = currentConfig.sheets[oldName];
        delete currentConfig.sheets[oldName];

        renderSheets();
    }

    /**
     * Suppression d'une feuille
     */
    function deleteSheet(sheetName) {
        if (!confirm(t('configEditor', 'confirmDeleteSheet', sheetName))) return;
        delete currentConfig.sheets[sheetName];
        renderSheets();
    }

    /**
     * Mise à jour des paramètres généraux des feuilles
     */
    function updateSheetsConfig() {
        if (!currentConfig) return;

        var primarySheetInput = document.getElementById('config-primary-sheet');
        var joinKeyInput = document.getElementById('config-join-key');

        if (primarySheetInput) {
            currentConfig.primary_sheet = primarySheetInput.value || undefined;
        }
        if (joinKeyInput) {
            currentConfig.join_key = joinKeyInput.value || undefined;
        }
    }

    /**
     * Mise à jour des métadonnées
     */
    function updateConfigMeta() {
        if (!currentConfig) return;
        currentConfig.fournisseur = document.getElementById('config-fournisseur').value;
        currentConfig.type = document.getElementById('config-type').value;
    }

    /**
     * Rendu de la table des mappings
     */
    function renderMappings() {
        // Nettoyer l'état du drag & drop avant de re-render
        cleanupDragState();

        var tbody = document.getElementById('mapping-tbody');
        tbody.innerHTML = '';

        if (!currentConfig || !currentConfig.mapping) return;

        var mappingKeys = Object.keys(currentConfig.mapping);

        mappingKeys.forEach(function(psColumn, index) {
            var mapping = currentConfig.mapping[psColumn];
            var row = createMappingRow(psColumn, mapping, index);
            tbody.appendChild(row);
        });
    }

    /**
     * Nettoie l'état du drag & drop
     */
    function cleanupDragState() {
        if (draggedRow) {
            draggedRow.classList.remove('dragging');
            draggedRow = null;
        }
        if (dragPlaceholder && dragPlaceholder.parentNode) {
            dragPlaceholder.parentNode.removeChild(dragPlaceholder);
        }
        dragPlaceholder = null;
        stopAutoScroll();
    }

    /**
     * Création d'une ligne de mapping
     */
    function createMappingRow(psColumn, mapping, index) {
        var tr = document.createElement('tr');
        tr.dataset.index = index;
        tr.dataset.psColumn = psColumn;

        // Colonne Ordre (poignée de drag)
        var tdOrder = document.createElement('td');
        tdOrder.style.textAlign = 'center';
        tdOrder.style.cursor = 'grab';
        tdOrder.className = 'drag-handle';
        tdOrder.innerHTML = '<i class="icon-bars" style="color: #999;"></i>';
        tdOrder.title = t('configEditor', 'dragToReorder');
        tr.appendChild(tdOrder);

        // Rendre la ligne draggable
        tr.draggable = true;
        tr.addEventListener('dragstart', handleDragStart);
        tr.addEventListener('dragend', handleDragEnd);
        tr.addEventListener('dragover', handleDragOver);
        tr.addEventListener('dragenter', handleDragEnter);
        tr.addEventListener('dragleave', handleDragLeave);
        tr.addEventListener('drop', handleDrop);

        // Colonne PrestaShop
        var tdPs = document.createElement('td');
        var selectPs = document.createElement('select');
        selectPs.className = 'form-control input-sm ps-column-select';
        selectPs.innerHTML = '<option value="">' + t('configEditor', 'selectColumn') + '</option>';
        Object.entries(prestashopColumns).forEach(function(entry) {
            var key = entry[0];
            var label = entry[1];
            var opt = document.createElement('option');
            opt.value = key;
            opt.textContent = label;
            if (key === psColumn) opt.selected = true;
            selectPs.appendChild(opt);
        });
        selectPs.addEventListener('change', function() {
            updateMappingKey(index, this.value);
        });
        tdPs.appendChild(selectPs);
        tr.appendChild(tdPs);

        // Configuration summary
        var tdConfig = document.createElement('td');
        var configDiv = document.createElement('div');
        configDiv.style.cssText = 'cursor: pointer;';
        configDiv.addEventListener('click', function() {
            editAction(psColumn, mapping);
        });
        configDiv.innerHTML = renderMappingSummaryHTML(mapping);
        tdConfig.appendChild(configDiv);
        tr.appendChild(tdConfig);

        // Actions column (Configurer + Supprimer)
        var tdActions = document.createElement('td');
        tdActions.style.cssText = 'white-space: nowrap; text-align: right; vertical-align: middle;';

        var btnConfigure = document.createElement('button');
        btnConfigure.type = 'button';
        btnConfigure.className = 'btn btn-xs btn-default';
        btnConfigure.style.marginRight = '4px';
        btnConfigure.innerHTML = '<i class="icon-cog"></i> ' + t('configEditor', 'configure');
        btnConfigure.addEventListener('click', function() {
            editAction(psColumn, mapping);
        });
        tdActions.appendChild(btnConfigure);

        var btnDelete = document.createElement('button');
        btnDelete.type = 'button';
        btnDelete.className = 'btn btn-danger btn-xs';
        btnDelete.innerHTML = '<i class="icon-trash"></i>';
        btnDelete.addEventListener('click', function() {
            deleteMapping(psColumn);
        });
        tdActions.appendChild(btnDelete);
        tr.appendChild(tdActions);

        return tr;
    }

    /**
     * Résumé de l'action pour affichage
     */
    /**
     * Summary for a single action
     */
    /**
     * Render a rich multi-line HTML summary for a mapping row
     */
    function renderMappingSummaryHTML(mapping) {
        var sz = 'font-size:11px;';
        var arrow = ' <span style="color:#5bc0de; font-weight: bold;">\u2192</span> ';

        // Source badges (inline)
        var src = '';
        if (mapping.sheet) src += '<span class="label label-default" style="font-size:10px;font-weight:400;">' + escapeHtml(mapping.sheet) + '</span> ';
        if (mapping.col) src += '<strong style="color:#555;">' + escapeHtml(mapping.col) + '</strong>';
        if (mapping.default) src += ' <span style="color:#999;">def: ' + escapeHtml(mapping.default) + '</span>';

        // Actions
        var actions = [];
        if (mapping.actions && Array.isArray(mapping.actions)) actions = mapping.actions;
        else if (mapping.action && mapping.action.type) actions = [mapping.action];

        if (actions.length === 0 && !src) {
            return '<span style="color:#ccc;' + sz + '">\u2014 ' + t('configEditor', 'noAction') + '</span>';
        }

        // Render in pipeline order: group consecutive simple actions on one line,
        // conditions on their own lines
        var html = '';
        var currentLine = [];
        if (src) currentLine.push(src);

        function flushLine() {
            if (currentLine.length > 0) {
                html += '<div style="' + sz + '">' + currentLine.join(arrow) + '</div>';
                currentLine = [];
            }
        }

        actions.forEach(function(a) {
            if (a.type === 'condition') {
                flushLine();
                html += renderConditionSummaryHTML(a);
            } else {
                currentLine.push(renderInlineAction(a));
            }
        });
        flushLine();


        return html;
    }

    /**
     * Render a single action as inline text with icon
     */
    function renderInlineAction(action) {
        var actionDef = actionTypes.find(function(a) { return a.type === action.type; });
        var icon = actionDef ? actionDef.icon : 'icon-cog';
        return '<i class="' + icon + '" style="color:#5bc0de;"></i> ' + escapeHtml(getSingleActionSummary(action));
    }

    /**
     * Render condition branches: each SI/SINON SI/SINON on its own line, actions inline with arrows
     */
    function renderConditionSummaryHTML(action) {
        var html = '';
        var sz = 'font-size:11px;';
        var arrow = ' <span style="color:#5bc0de; font-weight: bold;">\u2192</span> ';

        var branches = action.branches || [];
        if (branches.length === 0 && action.rules) {
            branches = [{ rules: action.rules, actions: action.then_actions || [] }];
        }

        branches.forEach(function(branch, idx) {
            var prefix = idx === 0 ? 'SI' : 'SINON SI';
            var ruleText = (branch.rules || []).map(function(r) {
                var f = r.field || 'val';
                if (f === 'col_value') f = 'val';
                return f + ' ' + (r.operator || '=') + ' ' + (r.value || '?');
            }).join(' & ');

            var actionsInline = (branch.actions || []).map(function(a) {
                return renderInlineAction(a);
            }).join(arrow);

            html += '<div style="' + sz + 'margin-top:2px;">' +
                '<i class="icon-code-fork" style="color:#ff9800;"></i> ' +
                '<strong style="color:#ff9800;">' + prefix + '</strong> ' +
                '<span style="color:#666;">' + escapeHtml(ruleText) + '</span>';
            if (actionsInline) html += arrow + '<span style="color:#27ae60;">' + actionsInline + '</span>';
            html += '</div>';
        });

        // SINON
        var elseActions = action.else_actions || [];
        var elseValue = action.else_value || '';
        if (elseActions.length > 0 || elseValue) {
            var elseInline = elseActions.map(function(a) { return renderInlineAction(a); }).join(arrow);
            html += '<div style="' + sz + 'margin-top:2px;">' +
                '<i class="icon-times" style="color:#e74c3c;"></i> ' +
                '<strong style="color:#e74c3c;">SINON</strong>';
            if (elseValue) html += ' <span style="color:#666;">"' + escapeHtml(elseValue) + '"</span>';
            if (elseInline) html += arrow + '<span style="color:#e74c3c;">' + elseInline + '</span>';
            html += '</div>';
        }

        return html;
    }

    function getSingleActionSummary(action) {
        if (!action || !action.type) return '?';

        var type = action.type;
        var actionDef = actionTypes.find(function(a) { return a.type === type; });
        var label = actionDef ? actionDef.label : type;

        switch (type) {
            case 'multiply':
                return label + ' \u00d7' + (action.value || '?');
            case 'divide':
                return label + ' \u00f7' + (action.value || '?');
            case 'add':
                return label + ' +' + (action.value || '?');
            case 'subtract':
                return label + ' -' + (action.value || '?');
            case 'round':
                return label + '(' + (action.decimals !== undefined ? action.decimals : '2') + ')';
            case 'map':
            case 'category_map':
                var count = action.values ? Object.keys(action.values).length : 0;
                return label + ' (' + count + ' val.)';
            case 'replace':
                return label + ' "' + (action.search || '') + '"';
            case 'regex_replace':
                return label + ' /' + (action.pattern || '') + '/';
            case 'truncate':
                return label + '(' + (action.length || 100) + ')';
            case 'prefix':
                return label + (action.text ? ' "' + action.text + '"' : '') + (action.source ? ' (' + action.source + ')' : '');
            case 'concat':
                return label + (action.separator ? ' [' + action.separator + ']' : '');
            case 'template':
                var tpl = action.template || '';
                return label + (tpl ? ' "' + (tpl.length > 20 ? tpl.substring(0, 20) + '...' : tpl) + '"' : '');
            case 'copy':
                return label + (action.col ? ' (' + action.col + ')' : '');
            case 'date_format':
                return label + (action.to ? ' \u2192 ' + action.to : '');
            case 'conditional':
                return label + (action.condition ? ' (' + action.condition + ')' : '');
            case 'llm_transform':
                var llmName = '';
                if (action.llm_config_id && typeof llmConfigs !== 'undefined') {
                    var cfg = llmConfigs.find(function(c) { return c.id_llm_config == action.llm_config_id; });
                    if (cfg) llmName = cfg.name;
                }
                return label + (llmName ? ' (' + llmName + ')' : '');
            case 'multiline_aggregate':
                var method = action.method || 'concat';
                var filterType = action.filter_type || '';
                return label + ' ' + method + (filterType ? '[' + filterType + ']' : '');
            case 'condition':
                return getConditionSummary(action);
            default:
                return label;
        }
    }

    /**
     * Build a readable summary for a condition action with branches
     */
    function getConditionSummary(action) {
        var parts = [];
        var branches = action.branches || [];

        // Legacy format
        if (branches.length === 0 && action.rules) {
            branches = [{ rules: action.rules, actions: action.then_actions || [] }];
        }

        branches.forEach(function(branch, idx) {
            var prefix = idx === 0 ? 'SI' : 'SINON SI';
            var rulesSummary = (branch.rules || []).map(function(r) {
                var field = r.field || 'val';
                // Shorten field display
                if (field.indexOf(':') !== -1) field = field.split(':')[1];
                if (field === 'col_value') field = 'val';
                return field + ' ' + (r.operator || '=') + ' ' + (r.value || '?');
            }).join(' & ');

            var actionsSummary = (branch.actions || []).map(function(a) {
                return getSingleActionSummary(a);
            }).join(' \u2192 ');

            parts.push(prefix + '(' + rulesSummary + ') \u2192 ' + (actionsSummary || '...'));
        });

        // SINON
        var elseActions = action.else_actions || [];
        var elseValue = action.else_value || '';
        if (elseActions.length > 0) {
            var elseSummary = elseActions.map(function(a) { return getSingleActionSummary(a); }).join(' \u2192 ');
            parts.push('SINON \u2192 ' + elseSummary);
        } else if (elseValue) {
            parts.push('SINON: "' + elseValue + '"');
        }

        return parts.join(' | ');
    }

    /**
     * Summary for a mapping's actions (pipeline)
     */
    function getActionSummary(mapping) {
        var actions = [];
        if (mapping.actions && Array.isArray(mapping.actions)) {
            actions = mapping.actions;
        } else if (mapping.action && mapping.action.type) {
            actions = [mapping.action];
        }

        if (actions.length === 0) {
            return t('configEditor', 'noAction');
        }

        return actions.map(function(a) { return getSingleActionSummary(a); }).join(' \u2192 ');
    }

    /**
     * Ajout d'un nouveau mapping
     */
    function addMapping() {
        if (!currentConfig) return;
        if (!currentConfig.mapping) currentConfig.mapping = {};

        // Ouvrir la modal de sélection de colonne
        openAddColumnModal();
    }

    /**
     * Ouvre la modal de sélection de colonne
     */
    function openAddColumnModal() {
        var modal = $('#add-column-modal');
        var select = document.getElementById('add-column-select');
        var searchInput = document.getElementById('add-column-search');
        var countSpan = document.getElementById('available-columns-count');
        var confirmBtn = document.getElementById('confirm-add-column-btn');

        // Récupérer les colonnes non utilisées
        var usedColumns = Object.keys(currentConfig.mapping || {});
        var availableKeys = Object.keys(prestashopColumns).filter(function(key) {
            return usedColumns.indexOf(key) === -1;
        });

        if (availableKeys.length === 0) {
            alert(t('configEditor', 'allColumnsMapped'));
            return;
        }

        // Remplir le select
        select.innerHTML = '';
        availableKeys.forEach(function(key) {
            var option = document.createElement('option');
            option.value = key;
            option.textContent = prestashopColumns[key];
            select.appendChild(option);
        });

        // Mettre à jour le compteur
        countSpan.textContent = t('configEditor', 'availableColumns', availableKeys.length);

        // Réinitialiser
        searchInput.value = '';
        confirmBtn.disabled = true;
        select.selectedIndex = -1;

        // Ouvrir la modal
        modal.modal('show');

        // Focus sur la recherche
        modal.on('shown.bs.modal', function() {
            searchInput.focus();
        });
    }

    /**
     * Filtre les options du select de colonnes
     */
    function filterAddColumnOptions() {
        var searchInput = document.getElementById('add-column-search');
        var select = document.getElementById('add-column-select');
        var search = searchInput.value.toLowerCase();

        var usedColumns = Object.keys(currentConfig.mapping || {});
        var availableKeys = Object.keys(prestashopColumns).filter(function(key) {
            return usedColumns.indexOf(key) === -1;
        });

        // Filtrer et re-remplir le select (recherche sur le label)
        select.innerHTML = '';
        var filteredKeys = availableKeys.filter(function(key) {
            var label = prestashopColumns[key];
            return label.toLowerCase().indexOf(search) !== -1 || key.toLowerCase().indexOf(search) !== -1;
        });

        filteredKeys.forEach(function(key) {
            var option = document.createElement('option');
            option.value = key;
            option.textContent = prestashopColumns[key];
            select.appendChild(option);
        });

        // Mettre à jour le compteur
        var countSpan = document.getElementById('available-columns-count');
        if (search) {
            countSpan.textContent = t('configEditor', 'availableColumnsFiltered', [filteredKeys.length, availableKeys.length]);
        } else {
            countSpan.textContent = t('configEditor', 'availableColumns', availableKeys.length);
        }
    }

    /**
     * Confirme l'ajout de la colonne sélectionnée
     */
    function confirmAddColumn() {
        var select = document.getElementById('add-column-select');
        var selectedColumn = select.value;

        if (!selectedColumn) {
            return;
        }

        // Ajouter la colonne au mapping
        currentConfig.mapping[selectedColumn] = {
            col: '',
            sheet: ''
        };

        // Fermer la modal
        $('#add-column-modal').modal('hide');

        // Re-render le mapping
        renderMappings();

        // Scroller vers la nouvelle ligne
        setTimeout(function() {
            var rows = document.querySelectorAll('#mapping-tbody tr');
            var lastRow = rows[rows.length - 1];
            if (lastRow) {
                lastRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
                lastRow.style.backgroundColor = '#d4edda';
                setTimeout(function() {
                    lastRow.style.backgroundColor = '';
                }, 1500);
            }
        }, 100);
    }

    /**
     * Ajoute toutes les colonnes non utilisées
     */
    function addAllColumns() {
        if (!currentConfig) return;
        if (!currentConfig.mapping) currentConfig.mapping = {};

        var usedColumns = Object.keys(currentConfig.mapping);
        var availableKeys = Object.keys(prestashopColumns).filter(function(key) {
            return usedColumns.indexOf(key) === -1;
        });

        if (availableKeys.length === 0) {
            alert(t('configEditor', 'allColumnsMapped'));
            return;
        }

        if (!confirm(t('configEditor', 'confirmAddAllColumns', availableKeys.length))) {
            return;
        }

        availableKeys.forEach(function(key) {
            currentConfig.mapping[key] = {
                col: '',
                sheet: ''
            };
        });

        renderMappings();
    }

    /**
     * Supprime les colonnes vides (sans source, sans défaut, sans action)
     */
    function removeEmptyColumns() {
        if (!currentConfig || !currentConfig.mapping) return;

        var emptyColumns = [];

        Object.keys(currentConfig.mapping).forEach(function(key) {
            var mapping = currentConfig.mapping[key];
            var hasCol = mapping.col && mapping.col.trim() !== '';
            var hasDefault = mapping.default !== undefined && mapping.default !== '';
            var hasActions = (mapping.actions && mapping.actions.length > 0) || (mapping.action && mapping.action.type);

            if (!hasCol && !hasDefault && !hasActions) {
                emptyColumns.push(key);
            }
        });

        if (emptyColumns.length === 0) {
            alert(t('configEditor', 'noEmptyColumns'));
            return;
        }

        if (!confirm(t('configEditor', 'confirmRemoveEmptyColumns', emptyColumns.length))) {
            return;
        }

        emptyColumns.forEach(function(key) {
            delete currentConfig.mapping[key];
        });

        renderMappings();
    }

    /**
     * Mise à jour de la clé d'un mapping (changement de colonne PS)
     */
    function updateMappingKey(index, newKey) {
        if (!currentConfig || !currentConfig.mapping) return;

        var keys = Object.keys(currentConfig.mapping);
        var oldKey = keys[index];

        if (oldKey === newKey) return;

        // Vérifier si la nouvelle clé existe déjà
        if (currentConfig.mapping[newKey]) {
            alert(t('configEditor', 'mappingExists'));
            renderMappings();
            return;
        }

        // Renommer la clé
        var value = currentConfig.mapping[oldKey];
        delete currentConfig.mapping[oldKey];
        currentConfig.mapping[newKey] = value;

        renderMappings();
    }

    /**
     * Suppression d'un mapping
     */
    function deleteMapping(psColumn) {
        if (!confirm(t('configEditor', 'confirmDeleteMapping'))) return;
        delete currentConfig.mapping[psColumn];
        renderMappings();
    }

    /**
     * Drag and Drop - Début du drag
     */
    function handleDragStart(e) {
        draggedRow = this;

        // Créer le placeholder
        dragPlaceholder = document.createElement('tr');
        dragPlaceholder.className = 'drag-placeholder';
        dragPlaceholder.innerHTML = '<td colspan="7"></td>';

        // Insérer le placeholder après la ligne actuelle
        var tbody = document.getElementById('mapping-tbody');
        tbody.insertBefore(dragPlaceholder, this.nextSibling);

        // Délai pour permettre au navigateur de créer l'image de drag
        setTimeout(function() {
            if (draggedRow) {
                draggedRow.classList.add('dragging');
            }
        }, 0);

        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', this.dataset.psColumn);

        // Démarrer l'auto-scroll continu
        startAutoScroll();
    }

    /**
     * Démarre l'auto-scroll continu
     */
    function startAutoScroll() {
        // Tracker la position de la souris
        document.addEventListener('dragover', trackMousePosition);

        // Démarrer l'interval de scroll
        autoScrollInterval = setInterval(function() {
            var windowHeight = window.innerHeight;

            if (lastMouseY < SCROLL_ZONE && lastMouseY > 0) {
                // Scroll vers le haut - plus on est proche du bord, plus c'est rapide
                var intensity = 1 - (lastMouseY / SCROLL_ZONE);
                var speed = Math.ceil(SCROLL_SPEED * intensity * 2);
                window.scrollBy(0, -speed);
            } else if (lastMouseY > windowHeight - SCROLL_ZONE && lastMouseY < windowHeight) {
                // Scroll vers le bas
                var intensity = 1 - ((windowHeight - lastMouseY) / SCROLL_ZONE);
                var speed = Math.ceil(SCROLL_SPEED * intensity * 2);
                window.scrollBy(0, speed);
            }
        }, 16); // ~60fps
    }

    /**
     * Track la position de la souris pendant le drag
     */
    function trackMousePosition(e) {
        lastMouseY = e.clientY;
    }

    /**
     * Arrête l'auto-scroll
     */
    function stopAutoScroll() {
        document.removeEventListener('dragover', trackMousePosition);
        if (autoScrollInterval) {
            clearInterval(autoScrollInterval);
            autoScrollInterval = null;
        }
    }

    /**
     * Drag and Drop - Fin du drag
     */
    function handleDragEnd(e) {
        // Déplacer la ligne à la position du placeholder
        if (draggedRow && dragPlaceholder && dragPlaceholder.parentNode) {
            var tbody = document.getElementById('mapping-tbody');
            tbody.insertBefore(draggedRow, dragPlaceholder);
            updateMappingOrder();
        }

        // Nettoyer
        if (draggedRow) {
            draggedRow.classList.remove('dragging');
        }

        if (dragPlaceholder && dragPlaceholder.parentNode) {
            dragPlaceholder.parentNode.removeChild(dragPlaceholder);
        }
        dragPlaceholder = null;
        draggedRow = null;

        // Arrêter l'auto-scroll
        stopAutoScroll();
    }

    /**
     * Drag and Drop - Survol d'une ligne
     */
    function handleDragOver(e) {
        e.preventDefault();
        if (!draggedRow || !dragPlaceholder) {
            return;
        }
        if (this === draggedRow || this === dragPlaceholder) {
            return;
        }

        e.dataTransfer.dropEffect = 'move';

        var tbody = document.getElementById('mapping-tbody');
        var rect = this.getBoundingClientRect();
        var midpoint = rect.top + rect.height / 2;

        // Positionner le placeholder avant ou après cette ligne
        if (e.clientY < midpoint) {
            if (dragPlaceholder.nextSibling !== this) {
                tbody.insertBefore(dragPlaceholder, this);
            }
        } else {
            if (dragPlaceholder !== this.nextSibling) {
                tbody.insertBefore(dragPlaceholder, this.nextSibling);
            }
        }
    }

    /**
     * Drag and Drop - Entrée dans une ligne
     */
    function handleDragEnter(e) {
        // Pas utilisé avec le nouveau système
    }

    /**
     * Drag and Drop - Sortie d'une ligne
     */
    function handleDragLeave(e) {
        // Pas utilisé avec le nouveau système
    }

    /**
     * Drag and Drop - Drop sur une ligne
     */
    function handleDrop(e) {
        e.preventDefault();
        e.stopPropagation();
        // Le déplacement est géré dans handleDragEnd
    }

    /**
     * Met à jour l'ordre du mapping selon l'ordre des lignes dans le DOM
     */
    function updateMappingOrder() {
        if (!currentConfig || !currentConfig.mapping) return;

        var tbody = document.getElementById('mapping-tbody');
        var rows = tbody.querySelectorAll('tr:not(.drag-placeholder)');
        var newMapping = {};

        rows.forEach(function(row) {
            var psColumn = row.dataset.psColumn;
            if (psColumn && currentConfig.mapping[psColumn]) {
                newMapping[psColumn] = currentConfig.mapping[psColumn];
            }
        });

        currentConfig.mapping = newMapping;
    }

    /**
     * Édition d'une action (pipeline mode)
     */
    function editAction(psColumn, mapping) {
        currentMappingIndex = psColumn;

        // Modal title
        var colLabel = (typeof prestashopColumns !== 'undefined' && prestashopColumns[psColumn]) ? prestashopColumns[psColumn] : psColumn;
        document.getElementById('action-modal-title').textContent = colLabel;

        // Source fields
        document.getElementById('modal-col-input').value = mapping.col || '';
        document.getElementById('modal-sheet-input').value = mapping.sheet || '';
        document.getElementById('modal-default-input').value = (mapping.default !== undefined) ? mapping.default : '';

        // Build action grid
        renderActionGrid();

        // Load existing conditions
        // Load existing pipeline (conditions are now part of the pipeline)
        loadPipeline(mapping);

        $('#action-modal').modal('show');
    }

    /**
     * Build action icon grid in right panel
     */
    function renderActionGrid() {
        var container = document.getElementById('action-grid');
        container.innerHTML = '';

        var groupOrder = Object.keys(actionGroups);

        groupOrder.forEach(function(groupKey) {
            var groupLabel = actionGroups[groupKey];
            var groupActions = actionTypes.filter(function(a) { return a.group === groupKey; });
            if (groupActions.length === 0) return;

            // Group header
            var header = document.createElement('div');
            header.style.cssText = 'font-size: 11px; font-weight: 600; color: #666; text-transform: uppercase; margin: 12px 0 6px 0; padding-bottom: 4px; border-bottom: 1px solid #ddd;';
            if (container.children.length === 0) header.style.marginTop = '0';
            header.textContent = groupLabel;
            container.appendChild(header);

            // Action buttons grid
            var grid = document.createElement('div');
            grid.style.cssText = 'display: flex; flex-wrap: wrap; gap: 4px;';

            groupActions.forEach(function(actionDef) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-default btn-xs';
                btn.style.cssText = 'display: flex; align-items: center; gap: 4px; padding: 4px 8px; font-size: 11px; white-space: nowrap;';
                btn.title = actionDef.description || actionDef.label;
                btn.innerHTML = '<i class="' + actionDef.icon + '"></i> ' + escapeHtml(actionDef.label);
                btn.addEventListener('click', function() {
                    // Add to active sub-pipeline (if a condition branch is focused) or main pipeline
                    // Conditions always go to main pipeline, other actions to active sub-pipeline
                    var targetList;
                    if (actionDef.type === 'condition') {
                        targetList = document.getElementById('pipeline-list');
                        activePipeline = null; // Reset active
                    } else {
                        targetList = (activePipeline instanceof HTMLElement) ? activePipeline : document.getElementById('pipeline-list');
                    }
                    addActionToPipeline(actionDef.type, null, targetList);
                });
                grid.appendChild(btn);
            });

            container.appendChild(grid);
        });
    }

    /**
     * Load conditions from mapping into UI
     */
    // (Old condition functions removed - conditions are now pipeline nodes)

    /**
     * Load existing pipeline actions into UI
     */
    function loadPipeline(mapping) {
        var listContainer = document.getElementById('pipeline-list');
        listContainer.innerHTML = '';

        // Migrate old format: condition + actions + else_actions → actions with condition node
        var actions = migrateToUnifiedPipeline(mapping);

        if (actions.length === 0) {
            document.getElementById('pipeline-empty').style.display = 'block';
            document.getElementById('clear-actions-btn').style.display = 'none';
        } else {
            document.getElementById('pipeline-empty').style.display = 'none';
            document.getElementById('clear-actions-btn').style.display = 'inline-block';

            // Find if there's a condition followed by more actions (= PUIS)
            var condIndex = -1;
            for (var i = 0; i < actions.length; i++) {
                if (actions[i].type === 'condition') { condIndex = i; break; }
            }

            actions.forEach(function(action, idx) {
                if (condIndex >= 0 && idx > condIndex && action.type !== 'condition') {
                    // Post-condition action → add to PUIS sub-pipeline
                    var puisList = listContainer.querySelector('.condition-puis-block .condition-sub-pipeline');
                    if (puisList) {
                        addActionToPipeline(action.type, action, puisList);
                        return;
                    }
                }
                addActionToPipeline(action.type, action, listContainer);
            });
        }
    }

    /**
     * Migrate old format (mapping.condition + mapping.else_actions) to unified pipeline
     */
    function migrateToUnifiedPipeline(mapping) {
        var actions = [];
        if (mapping.actions && Array.isArray(mapping.actions)) {
            actions = mapping.actions;
        } else if (mapping.action && mapping.action.type) {
            actions = [mapping.action];
        }

        // If old-style condition exists at mapping level, wrap in a condition action
        if (mapping.condition && mapping.condition.rules && mapping.condition.rules.length > 0) {
            var condAction = {
                type: 'condition',
                logic: mapping.condition.logic || 'AND',
                rules: mapping.condition.rules,
                then_actions: actions,
                else_actions: mapping.else_actions || [],
                else_value: mapping.condition_else || ''
            };
            return [condAction];
        }

        return actions;
    }

    /**
     * Add an action block to the pipeline
     */
    function addActionToPipeline(actionType, existingAction, targetContainer) {
        var actionDef = actionTypes.find(function(a) { return a.type === actionType; });
        if (!actionDef) return;

        // Target: either a passed container element or the main pipeline
        var listContainer = (targetContainer instanceof HTMLElement) ? targetContainer : document.getElementById('pipeline-list');
        var emptyEl = document.getElementById('pipeline-empty');
        emptyEl.style.display = 'none';
        document.getElementById('clear-actions-btn').style.display = 'inline-block';

        // Special rendering for condition nodes
        if (actionType === 'condition') {
            renderConditionNode(listContainer, existingAction || {});
            return;
        }

        var node = document.createElement('div');
        node.className = 'pipeline-node pipeline-action-block';
        node.dataset.actionType = actionType;

        // Node header (compact)
        var header = document.createElement('div');
        header.className = 'pipeline-node-header pipeline-drag-handle';

        var icon = document.createElement('i');
        icon.className = actionDef.icon + ' node-icon';

        var title = document.createElement('span');
        title.className = 'node-title';
        title.textContent = actionDef.label;

        // Inline summary of params
        var summary = document.createElement('span');
        summary.className = 'node-summary';
        summary.textContent = existingAction ? getSingleActionSummary(existingAction) : '';

        var actionsDiv = document.createElement('span');
        actionsDiv.className = 'node-actions';

        var dragIcon = document.createElement('i');
        dragIcon.className = 'icon-arrows';
        dragIcon.style.cssText = 'color: #aaa; cursor: move; font-size: 11px;';

        var deleteBtn = document.createElement('button');
        deleteBtn.type = 'button';
        deleteBtn.className = 'btn btn-danger btn-xs';
        deleteBtn.style.cssText = 'padding: 1px 5px; font-size: 10px;';
        deleteBtn.innerHTML = '<i class="icon-times"></i>';
        deleteBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            node.remove();
            updatePipelineEmptyState();
        });

        actionsDiv.appendChild(dragIcon);
        actionsDiv.appendChild(deleteBtn);

        header.appendChild(icon);
        header.appendChild(title);
        header.appendChild(summary);
        header.appendChild(actionsDiv);
        node.appendChild(header);

        // Params (collapsed by default, expanded on click)
        if (actionDef.params && Object.keys(actionDef.params).length > 0) {
            var paramsDiv = document.createElement('div');
            paramsDiv.className = 'pipeline-node-params pipeline-action-params';
            renderActionParamsInContainer(paramsDiv, actionDef, existingAction || {});
            node.appendChild(paramsDiv);

            // Start expanded if new action (no existing params)
            if (!existingAction) {
                node.classList.add('expanded');
            }

            // Toggle on header click
            header.addEventListener('click', function(e) {
                if (e.target.tagName === 'BUTTON' || e.target.closest('button') || e.target.closest('.node-actions')) return;
                node.classList.toggle('expanded');
            });
        }

        listContainer.appendChild(node);
        setupPipelineDragDrop();
    }

    /**
     * Build the field value from separate sheet + col inputs in a condition rule row
     */
    function buildConditionField(row) {
        var sheet = (row.querySelector('.condition-sheet') || {}).value || '';
        var col = (row.querySelector('.condition-col') || {}).value || '';
        sheet = sheet.trim();
        col = col.trim();
        if (!col) return 'col_value';
        if (sheet) return sheet + ':' + col;
        return col;
    }

    /**
     * Create a clickable drop zone for adding actions to a sub-pipeline
     */
    function createActionDropZone(targetList) {
        var zone = document.createElement('div');
        zone.className = 'action-drop-zone';
        zone.style.cssText = 'border: 2px dashed #ccc; border-radius: 4px; padding: 10px; margin-top: 6px; text-align: center; cursor: pointer; color: #999; font-size: 11px; transition: border-color 0.15s, color 0.15s; background: #fff;';
        zone.textContent = 'Cliquez ici puis ajoutez une action \u2192';
        zone.addEventListener('click', function() {
            activePipeline = targetList;
            // Visual feedback
            zone.style.borderColor = 'var(--pkoai-primary)';
            zone.style.color = 'var(--pkoai-primary)';
            zone.textContent = '\u2190 Choisissez une action dans le panneau';
            setTimeout(function() {
                zone.style.borderColor = '#ccc';
                zone.style.color = '#999';
                zone.textContent = 'Cliquez ici puis ajoutez une action \u2192';
            }, 3000);
        });
        return zone;
    }

    /**
     * Highlight the active pipeline target (visual feedback)
     */
    function highlightActivePipeline(targetEl) {
        // Remove highlight from all
        document.querySelectorAll('.pipeline-flow').forEach(function(el) {
            el.style.outline = '';
        });
        // Add highlight to target
        if (targetEl) {
            targetEl.style.outline = '2px solid var(--pkoai-primary)';
            targetEl.style.outlineOffset = '2px';
            targetEl.style.borderRadius = '4px';
            setTimeout(function() { targetEl.style.outline = ''; }, 2000);
        }
    }

    /**
     * Update pipeline empty state visibility
     */
    function updatePipelineEmptyState() {
        var list = document.getElementById('pipeline-list');
        var empty = document.getElementById('pipeline-empty');
        var clearBtn = document.getElementById('clear-actions-btn');
        if (list.children.length === 0) {
            empty.style.display = 'block';
            clearBtn.style.display = 'none';
        } else {
            empty.style.display = 'none';
            clearBtn.style.display = 'inline-block';
        }
    }

    /**
     * Render a condition as separate blocks on the main pipeline:
     * SI block (orange) → [+ SINON SI] → SINON block (red)
     * Each SI/SINON SI block contains rules + ALORS actions inside it.
     */
    function renderConditionNode(listContainer, existingAction) {
        // Wrapper div to group all condition blocks (for delete all + serialization)
        var condGroup = document.createElement('div');
        condGroup.className = 'pipeline-action-block condition-group';
        condGroup.dataset.actionType = 'condition';

        var branches = existingAction.branches || [];
        // Legacy
        if (branches.length === 0 && existingAction.rules) {
            branches = [{ rules: existingAction.rules, logic: existingAction.logic || 'AND', actions: existingAction.then_actions || [] }];
        }
        if (branches.length === 0) branches = [null]; // one empty SI

        // Render each SI / SINON SI block
        var branchesContainer = document.createElement('div');
        branchesContainer.className = 'condition-branches';
        condGroup.appendChild(branchesContainer);

        branches.forEach(function(branch, idx) {
            renderConditionBranchBlock(branchesContainer, branch, idx === 0, condGroup);
        });

        // "+ SINON SI" button
        var addElseIfBtn = document.createElement('button');
        addElseIfBtn.type = 'button';
        addElseIfBtn.className = 'btn btn-warning btn-xs';
        addElseIfBtn.style.cssText = 'margin: 6px 0; font-size: 11px;';
        addElseIfBtn.innerHTML = '<i class="icon-plus"></i> SINON SI';
        addElseIfBtn.addEventListener('click', function() {
            renderConditionBranchBlock(branchesContainer, null, false, condGroup);
        });
        condGroup.appendChild(addElseIfBtn);

        // SINON block (red)
        var sinon = document.createElement('div');
        sinon.className = 'pipeline-node condition-sinon-block';
        sinon.style.cssText = 'border-color: #e74c3c; border-width: 2px; background: rgba(231, 76, 60, 0.04);';

        var sinonHeader = document.createElement('div');
        sinonHeader.className = 'pipeline-node-header';
        sinonHeader.innerHTML = '<i class="icon-times node-icon" style="color: #e74c3c;"></i><span class="node-title" style="color: #e74c3c;">SINON</span>';
        sinon.appendChild(sinonHeader);

        var sinonContent = document.createElement('div');
        sinonContent.className = 'pipeline-node-params';
        sinonContent.style.display = 'block';

        var elseValueInput = document.createElement('input');
        elseValueInput.type = 'text';
        elseValueInput.className = 'form-control input-sm condition-else-val';
        elseValueInput.style.marginBottom = '6px';
        elseValueInput.placeholder = t('configEditor', 'fixedValueOrActions') || 'Valeur fixe (ou vide pour utiliser les actions)';
        elseValueInput.value = existingAction.else_value || '';
        sinonContent.appendChild(elseValueInput);

        var elseList = document.createElement('div');
        elseList.className = 'pipeline-flow condition-sub-pipeline';
        elseList.dataset.branch = 'else';
        sinonContent.appendChild(elseList);

        var elseDropZone = createActionDropZone(elseList);
        sinonContent.appendChild(elseDropZone);
        sinon.appendChild(sinonContent);
        condGroup.appendChild(sinon);

        // "PUIS (toujours)" block — inside condition group, same orange line
        var puisBlock = document.createElement('div');
        puisBlock.className = 'pipeline-node condition-puis-block';
        puisBlock.style.cssText = 'border-color: #5bc0de; border-width: 2px; background: rgba(91,192,222,0.04); margin-top: 10px;';

        var puisHeader = document.createElement('div');
        puisHeader.className = 'pipeline-node-header';
        puisHeader.innerHTML = '<i class="icon-arrow-down node-icon" style="color:#5bc0de;"></i> <span class="node-title" style="color:#5bc0de;">PUIS (toujours)</span>';
        puisBlock.appendChild(puisHeader);

        var puisContent = document.createElement('div');
        puisContent.className = 'pipeline-node-params';
        puisContent.style.display = 'block';
        var puisList = document.createElement('div');
        puisList.className = 'pipeline-flow condition-sub-pipeline';
        puisContent.appendChild(puisList);
        puisContent.appendChild(createActionDropZone(puisList));
        puisBlock.appendChild(puisContent);

        condGroup.appendChild(puisBlock);
        listContainer.appendChild(condGroup);

        // Load existing else actions
        var elseActions = existingAction.else_actions || [];
        elseActions.forEach(function(a) { addActionToPipeline(a.type, a, elseList); });

    }

    /**
     * Render a single SI or SINON SI block inside condition-branches
     */
    function renderConditionBranchBlock(branchesContainer, existingBranch, isFirst, condGroup) {
        var block = document.createElement('div');
        block.className = 'pipeline-node condition-branch-block';
        block.style.cssText = 'border-color: #ff9800; border-width: 2px; background: rgba(255, 152, 0, 0.04);';

        // Header: SI or SINON SI
        var labelText = isFirst ? 'SI' : 'SINON SI';
        var header = document.createElement('div');
        header.className = 'pipeline-node-header';
        header.innerHTML = '<i class="icon-code-fork node-icon" style="color: #ff9800;"></i>' +
            '<span class="node-title" style="color: #ff9800;">' + labelText + '</span>';

        // Delete button (for SINON SI only, or for SI if it deletes the whole condition)
        var deleteBtn = document.createElement('button');
        deleteBtn.type = 'button';
        deleteBtn.className = 'btn btn-danger btn-xs';
        deleteBtn.style.cssText = 'padding: 1px 5px; font-size: 10px; margin-left: auto;';
        deleteBtn.innerHTML = '<i class="icon-times"></i>';
        deleteBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (isFirst) {
                // Deleting SI removes the entire condition group
                condGroup.remove();
                updatePipelineEmptyState();
            } else {
                block.remove();
            }
        });
        var actionsSpan = document.createElement('span');
        actionsSpan.className = 'node-actions';
        actionsSpan.appendChild(deleteBtn);
        header.appendChild(actionsSpan);
        block.appendChild(header);

        // Content
        var content = document.createElement('div');
        content.className = 'pipeline-node-params';
        content.style.display = 'block';

        // Rules
        var rulesContainer = document.createElement('div');
        rulesContainer.className = 'condition-rules-list';
        content.appendChild(rulesContainer);

        var logicInput = document.createElement('input');
        logicInput.type = 'hidden';
        logicInput.className = 'condition-logic-value';
        logicInput.value = (existingBranch && existingBranch.logic) || 'AND';
        content.appendChild(logicInput);

        var rules = (existingBranch && existingBranch.rules) || [];
        if (rules.length === 0) {
            addConditionRuleToNode(rulesContainer, logicInput);
        } else {
            rules.forEach(function(rule, idx) {
                addConditionRuleToNode(rulesContainer, logicInput, rule, idx > 0);
            });
        }

        var addRuleBtn = document.createElement('button');
        addRuleBtn.type = 'button';
        addRuleBtn.className = 'btn btn-default btn-xs';
        addRuleBtn.style.cssText = 'margin-bottom: 8px; font-size: 10px;';
        addRuleBtn.innerHTML = '<i class="icon-plus"></i> ' + (t('configEditor', 'addCondition') || 'Add condition');
        addRuleBtn.addEventListener('click', function() {
            addConditionRuleToNode(rulesContainer, logicInput, null, true);
        });
        content.appendChild(addRuleBtn);

        // ALORS section (inside the same block)
        var alorsLabel = document.createElement('div');
        alorsLabel.style.cssText = 'font-size: 11px; font-weight: 600; color: #27ae60; margin: 4px 0 4px 0; text-transform: uppercase;';
        alorsLabel.innerHTML = '<i class="icon-check"></i> ALORS';
        content.appendChild(alorsLabel);

        var actionsList = document.createElement('div');
        actionsList.className = 'pipeline-flow condition-sub-pipeline';
        content.appendChild(actionsList);

        var branchDropZone = createActionDropZone(actionsList);
        content.appendChild(branchDropZone);

        block.appendChild(content);
        branchesContainer.appendChild(block);

        // Load existing actions
        if (existingBranch && existingBranch.actions) {
            existingBranch.actions.forEach(function(a) {
                addActionToPipeline(a.type, a, actionsList);
            });
        }
    }

    /**
     * Add a condition rule row inside a condition node
     */
    function addConditionRuleToNode(rulesContainer, logicInput, existingRule, showLogic) {
        // Logic toggle
        if (showLogic) {
            var logicDiv = document.createElement('div');
            logicDiv.style.cssText = 'text-align: center; margin: 4px 0;';
            var logicSelect = document.createElement('select');
            logicSelect.className = 'form-control input-sm';
            logicSelect.style.cssText = 'width: 80px; display: inline-block; font-size: 11px;';
            logicSelect.innerHTML = '<option value="AND"' + (logicInput.value === 'OR' ? '' : ' selected') + '>AND</option>' +
                '<option value="OR"' + (logicInput.value === 'OR' ? ' selected' : '') + '>OR</option>';
            logicSelect.addEventListener('change', function() { logicInput.value = this.value; });
            logicDiv.appendChild(logicSelect);
            rulesContainer.appendChild(logicDiv);
        }

        // Parse existing field: "SHEET:COL" → sheet + col, or "col_value"/empty → col only
        var existingSheet = '';
        var existingCol = '';
        if (existingRule && existingRule.field) {
            if (existingRule.field.indexOf(':') !== -1) {
                var parts = existingRule.field.split(':');
                existingSheet = parts[0];
                existingCol = parts[1];
            } else {
                existingCol = existingRule.field;
            }
        }

        var row = document.createElement('div');
        row.className = 'condition-rule';
        row.style.cssText = 'display: flex; gap: 4px; align-items: center; margin-bottom: 4px;';

        var sheetInput = document.createElement('input');
        sheetInput.type = 'text';
        sheetInput.className = 'form-control input-sm condition-sheet';
        sheetInput.style.cssText = 'width: 120px; height: 30px;';
        sheetInput.placeholder = 'Feuille';
        sheetInput.value = existingSheet;

        var colInput = document.createElement('input');
        colInput.type = 'text';
        colInput.className = 'form-control input-sm condition-col';
        colInput.style.cssText = 'width: 70px; height: 30px;';
        colInput.placeholder = 'Col';
        colInput.value = existingCol;

        var opSelect = document.createElement('select');
        opSelect.className = 'form-control input-sm condition-operator';
        opSelect.style.cssText = 'width: 130px; height: 30px;';
        Object.keys(conditionOperators).forEach(function(op) {
            var opt = document.createElement('option');
            opt.value = op;
            opt.textContent = conditionOperators[op];
            if (existingRule && existingRule.operator === op) opt.selected = true;
            opSelect.appendChild(opt);
        });

        var valueInput = document.createElement('input');
        valueInput.type = 'text';
        valueInput.className = 'form-control input-sm condition-value';
        valueInput.style.cssText = 'flex: 1; height: 30px;';
        valueInput.placeholder = 'valeur';
        valueInput.value = (existingRule && existingRule.value) ? existingRule.value : '';

        var delBtn = document.createElement('button');
        delBtn.type = 'button';
        delBtn.className = 'btn btn-danger btn-xs';
        delBtn.style.cssText = 'height: 30px; width: 30px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;';
        delBtn.innerHTML = '<i class="icon-times"></i>';
        delBtn.addEventListener('click', function() {
            if (row.previousElementSibling && row.previousElementSibling.tagName === 'DIV' && row.previousElementSibling.querySelector('select')) {
                row.previousElementSibling.remove();
            } else if (row.nextElementSibling && row.nextElementSibling.tagName === 'DIV' && row.nextElementSibling.querySelector('select')) {
                row.nextElementSibling.remove();
            }
            row.remove();
        });

        row.appendChild(sheetInput);
        row.appendChild(colInput);
        row.appendChild(opSelect);
        row.appendChild(valueInput);
        row.appendChild(delBtn);
        rulesContainer.appendChild(row);
    }

    /**
     * Renumber pipeline step badges
     */
    function renumberPipelineSteps() {
        var blocks = document.querySelectorAll('#pipeline-list .pipeline-action-block');
        blocks.forEach(function(block, i) {
            var num = block.querySelector('.pipeline-step-num');
            if (num) num.textContent = i + 1;
        });
    }

    /**
     * Setup drag & drop for pipeline blocks
     */
    function setupPipelineDragDrop() {
        var blocks = document.querySelectorAll('#pipeline-list .pipeline-action-block');
        blocks.forEach(function(block) {
            var handle = block.querySelector('.pipeline-drag-handle');
            if (!handle) return;
            handle.onmousedown = function(e) {
                if (e.target.tagName === 'BUTTON' || e.target.tagName === 'I' && e.target.parentElement.tagName === 'BUTTON') return;
                startPipelineDrag(e, block);
            };
        });
    }

    var pipelineDraggedBlock = null;

    function startPipelineDrag(e, block) {
        pipelineDraggedBlock = block;
        block.style.opacity = '0.5';

        var list = document.getElementById('pipeline-list');

        function onMouseMove(e) {
            var blocks = Array.from(list.querySelectorAll('.pipeline-action-block'));
            var afterElement = null;
            for (var i = 0; i < blocks.length; i++) {
                var box = blocks[i].getBoundingClientRect();
                var offset = e.clientY - box.top - box.height / 2;
                if (offset < 0 && blocks[i] !== pipelineDraggedBlock) {
                    afterElement = blocks[i];
                    break;
                }
            }
            if (afterElement) {
                list.insertBefore(pipelineDraggedBlock, afterElement);
            } else {
                list.appendChild(pipelineDraggedBlock);
            }
        }

        function onMouseUp() {
            pipelineDraggedBlock.style.opacity = '1';
            pipelineDraggedBlock = null;
            renumberPipelineSteps();
            document.removeEventListener('mousemove', onMouseMove);
            document.removeEventListener('mouseup', onMouseUp);
        }

        document.addEventListener('mousemove', onMouseMove);
        document.addEventListener('mouseup', onMouseUp);
    }

    /**
     * Render action params inside a given container (used by each pipeline block)
     */
    function renderActionParamsInContainer(container, actionDef, existingAction) {
        container.innerHTML = '';

        if (!actionDef || !actionDef.params) return;

        var actionType = actionDef.type;

        // Description
        if (actionDef.description) {
            var descDiv = document.createElement('div');
            descDiv.className = 'alert alert-info';
            descDiv.style.cssText = 'padding: 6px 10px; margin-bottom: 10px; font-size: 12px;';
            descDiv.innerHTML = '<i class="icon-info-circle"></i> ' + actionDef.description;
            container.appendChild(descDiv);
        }

        // JSON keys info for special columns
        if (actionType === 'multiline_aggregate' && currentMappingIndex) {
            var jsonKeysInfo = null;
            if (currentMappingIndex === 'Fichiers joints') {
                jsonKeysInfo = { titleKey: t('configEditor', 'jsonKeysAttachments'), keys: [
                    { name: 'type', desc: 'Type (NOTICE, BROCH, etc.)' }, { name: 'url', desc: 'URL' }, { name: 'name', desc: 'Name (optional)' }]};
            } else if (currentMappingIndex === 'Vidéos') {
                jsonKeysInfo = { titleKey: t('configEditor', 'jsonKeysVideos'), keys: [
                    { name: 'url', desc: 'URL (YouTube, Vimeo, etc.)' }, { name: 'title', desc: 'Title (optional)' }]};
            }
            if (jsonKeysInfo) {
                var infoDiv = document.createElement('div');
                infoDiv.id = 'json-keys-info';
                infoDiv.className = 'alert alert-warning';
                infoDiv.style.cssText = 'margin-bottom: 10px; font-size: 12px; display: none;';
                var html = '<i class="icon-lightbulb"></i> <strong>' + jsonKeysInfo.titleKey + '</strong><ul style="margin: 5px 0 0 0; padding-left: 20px;">';
                jsonKeysInfo.keys.forEach(function(key) { html += '<li><code>' + key.name + '</code> : ' + key.desc + '</li>'; });
                infoDiv.innerHTML = html + '</ul>';
                container.appendChild(infoDiv);
            }
        }

        var params = actionDef.params;
        var existingParams = existingAction || {};
        var conditionalElements = [];

        Object.keys(params).forEach(function(paramName) {
            var paramDef = params[paramName];
            var currentValue = existingParams[paramName];

            var div = document.createElement('div');
            div.className = 'form-group';
            div.style.marginBottom = '8px';
            div.dataset.paramName = paramName;

            if (paramDef.width) {
                div.style.display = 'inline-block';
                div.style.width = paramDef.width;
                div.style.paddingRight = '10px';
                div.style.verticalAlign = 'top';
                div.style.boxSizing = 'border-box';
            }

            var label = document.createElement('label');
            label.style.cssText = 'font-size: 12px; margin-bottom: 3px;';
            label.textContent = paramDef.label;
            div.appendChild(label);

            var inputElement = createParamInput(paramName, paramDef, currentValue, existingParams);
            if (inputElement) {
                div.appendChild(inputElement);
            }

            if (paramDef.placeholder && inputElement && (inputElement.tagName === 'INPUT' || inputElement.tagName === 'TEXTAREA')) {
                inputElement.placeholder = paramDef.placeholder;
            }

            if (paramDef.help) {
                var helpText = document.createElement('p');
                helpText.className = 'help-block';
                helpText.style.cssText = 'font-size: 11px; color: #737373; margin-top: 2px; margin-bottom: 0;';
                helpText.textContent = paramDef.help;
                div.appendChild(helpText);
            }

            if (paramDef.show_if) {
                conditionalElements.push({ element: div, condition: paramDef.show_if });
                div.style.display = 'none';
            }

            container.appendChild(div);
        });

        updateConditionalVisibility(container, existingParams, conditionalElements);
        updateJsonKeysInfoVisibility(container, existingParams);

        container.querySelectorAll('.action-param').forEach(function(input) {
            input.addEventListener('change', function() {
                var currentValues = getCurrentActionValues(container);
                updateConditionalVisibility(container, currentValues, conditionalElements);
                updateJsonKeysInfoVisibility(container, currentValues);
            });
        });
    }

    /**
     * Met à jour la visibilité de l'encart des clés JSON selon la méthode
     */
    function updateJsonKeysInfoVisibility(container, currentValues) {
        var infoBox = document.getElementById('json-keys-info');
        if (!infoBox) return;

        // Afficher uniquement si method = json_array
        var show = currentValues.method === 'json_array';
        infoBox.style.display = show ? '' : 'none';
    }

    /**
     * Crée l'élément input pour un paramètre
     */
    function createParamInput(paramName, paramDef, currentValue, existingParams) {
        var element;

        switch (paramDef.type) {
            case 'number':
                element = document.createElement('input');
                element.type = 'number';
                element.className = 'form-control action-param';
                element.dataset.param = paramName;
                element.step = paramDef.step || '1';
                element.value = currentValue !== undefined ? currentValue : (paramDef.default || '');
                break;

            case 'text':
                element = document.createElement('input');
                element.type = 'text';
                element.className = 'form-control action-param';
                element.dataset.param = paramName;
                element.value = currentValue !== undefined ? currentValue : (paramDef.default || '');
                break;

            case 'checkbox':
                element = document.createElement('input');
                element.type = 'checkbox';
                element.className = 'action-param';
                element.dataset.param = paramName;
                element.checked = currentValue !== undefined ? currentValue : (paramDef.default || false);
                break;

            case 'keyvalue':
                var wrapper = document.createElement('div');
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-default action-param-keyvalue';
                btn.dataset.param = paramName;
                var count = currentValue ? Object.keys(currentValue).length : 0;
                btn.innerHTML = '<i class="icon-list"></i> ' + t('configEditor', 'editValues', count);
                btn.addEventListener('click', function() {
                    openKeyValueEditor(paramName, currentValue || {});
                });
                wrapper.appendChild(btn);

                var hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.className = 'action-param';
                hidden.dataset.param = paramName;
                hidden.value = JSON.stringify(currentValue || {});
                wrapper.appendChild(hidden);
                element = wrapper;
                break;

            case 'columns_mapping':
                element = createColumnsMappingEditor(paramName, currentValue);
                break;

            case 'source_columns_mapping':
                element = createSourceColumnsMappingEditor(paramName, currentValue);
                break;

            case 'sources':
            case 'sourcesobject':
                element = document.createElement('textarea');
                element.className = 'form-control action-param';
                element.dataset.param = paramName;
                element.rows = paramDef.type === 'sourcesobject' ? 4 : 3;
                element.style.resize = 'vertical';
                element.style.minHeight = '80px';
                element.placeholder = paramDef.type === 'sourcesobject'
                    ? 'Format JSON: {"VAR": {"col": "A", "sheet": "Feuille"}}'
                    : 'Format JSON: [{"col": "A", "sheet": "Feuille"}]';
                element.value = currentValue ? JSON.stringify(currentValue, null, 2) : '';
                break;

            case 'llm_select':
                element = document.createElement('select');
                element.className = 'form-control action-param';
                element.dataset.param = paramName;

                var optEmpty = document.createElement('option');
                optEmpty.value = '';
                optEmpty.textContent = t('configEditor', 'selectLlmConfig');
                element.appendChild(optEmpty);

                if (typeof llmConfigs !== 'undefined' && llmConfigs) {
                    llmConfigs.forEach(function(config) {
                        var opt = document.createElement('option');
                        opt.value = config.id_llm_config;
                        opt.textContent = config.name + ' (' + config.provider + ' / ' + config.model + ')';
                        if (config.id_llm_config == currentValue) opt.selected = true;
                        element.appendChild(opt);
                    });
                }
                break;

            case 'textarea':
                element = document.createElement('textarea');
                element.className = 'form-control action-param';
                element.dataset.param = paramName;
                element.rows = paramDef.rows || 3;
                element.style.resize = 'vertical';
                element.style.minHeight = '80px';
                var textareaValue = currentValue;
                if (textareaValue !== undefined && typeof textareaValue === 'object') {
                    textareaValue = JSON.stringify(textareaValue, null, 2);
                }
                element.value = textareaValue !== undefined ? textareaValue : (paramDef.default || '');
                break;

            case 'select':
                element = document.createElement('select');
                element.className = 'form-control action-param';
                element.dataset.param = paramName;

                if (paramDef.options) {
                    Object.keys(paramDef.options).forEach(function(optKey) {
                        var opt = document.createElement('option');
                        opt.value = optKey;
                        opt.textContent = paramDef.options[optKey];
                        if (optKey === (currentValue || paramDef.default)) opt.selected = true;
                        element.appendChild(opt);
                    });
                }
                break;

            case 'columns_select':
                var wrapper = document.createElement('div');
                var container2 = document.createElement('div');
                container2.className = 'columns-select-container';
                container2.style.cssText = 'max-height: 200px; overflow-y: auto; border: 1px solid #ccc; padding: 10px; background: #f9f9f9;';

                var currentColumnsValue = currentValue || [];
                // S'assurer que c'est un tableau
                if (typeof currentColumnsValue === 'string') {
                    try {
                        currentColumnsValue = JSON.parse(currentColumnsValue);
                    } catch (e) {
                        currentColumnsValue = [];
                    }
                }
                if (!Array.isArray(currentColumnsValue)) {
                    currentColumnsValue = [];
                }
                Object.entries(prestashopColumns).forEach(function(entry) {
                    var key = entry[0];
                    var label = entry[1];
                    var checkDiv = document.createElement('div');
                    checkDiv.className = 'checkbox';
                    checkDiv.style.margin = '2px 0';

                    var checkLabel = document.createElement('label');
                    var checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.value = key;
                    checkbox.className = 'columns-select-checkbox';

                    var isChecked = currentColumnsValue.some(function(c) {
                        return (typeof c === 'string' && c === key) || (c.col === key) || (c.label === key);
                    });
                    checkbox.checked = isChecked;

                    checkLabel.appendChild(checkbox);
                    checkLabel.appendChild(document.createTextNode(' ' + label));
                    checkDiv.appendChild(checkLabel);
                    container2.appendChild(checkDiv);
                });

                wrapper.appendChild(container2);

                var hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.className = 'action-param';
                hidden.dataset.param = paramName;
                hidden.dataset.type = 'columns_select';
                hidden.value = JSON.stringify(currentColumnsValue);
                wrapper.appendChild(hidden);

                container2.addEventListener('change', function() {
                    var selected = [];
                    container2.querySelectorAll('.columns-select-checkbox:checked').forEach(function(cb) {
                        selected.push({ col: cb.value, label: prestashopColumns[cb.value] || cb.value });
                    });
                    hidden.value = JSON.stringify(selected);
                });
                element = wrapper;
                break;
        }

        return element;
    }

    /**
     * Crée un éditeur de mapping colonnes (clé JSON → colonne Excel)
     */
    function createColumnsMappingEditor(paramName, currentValue) {
        var wrapper = document.createElement('div');
        wrapper.className = 'columns-mapping-editor';
        wrapper.style.cssText = 'border: 1px solid #ddd; padding: 10px; border-radius: 4px; background: #f9f9f9;';

        var table = document.createElement('table');
        table.className = 'table table-condensed';
        table.style.marginBottom = '10px';
        table.innerHTML = '<thead><tr>' +
            '<th style="width: 45%;">' + t('configEditor', 'jsonKeyHeader') + '</th>' +
            '<th style="width: 45%;">' + t('configEditor', 'excelColumnHeader') + '</th>' +
            '<th style="width: 10%;"></th>' +
            '</tr></thead>';

        var tbody = document.createElement('tbody');
        tbody.className = 'columns-mapping-tbody';
        table.appendChild(tbody);
        wrapper.appendChild(table);

        // Champ caché pour stocker les valeurs
        var hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.className = 'action-param';
        hidden.dataset.param = paramName;
        hidden.dataset.type = 'columns_mapping';
        hidden.value = JSON.stringify(currentValue || {});
        wrapper.appendChild(hidden);

        // Fonction pour ajouter une ligne
        function addRow(key, value) {
            var tr = document.createElement('tr');

            var tdKey = document.createElement('td');
            var inputKey = document.createElement('input');
            inputKey.type = 'text';
            inputKey.className = 'form-control input-sm col-map-key';
            inputKey.value = key || '';
            inputKey.placeholder = 'url, alt, type...';
            inputKey.addEventListener('change', updateHidden);
            tdKey.appendChild(inputKey);
            tr.appendChild(tdKey);

            var tdValue = document.createElement('td');
            var inputValue = document.createElement('input');
            inputValue.type = 'text';
            inputValue.className = 'form-control input-sm col-map-value';
            inputValue.value = value || '';
            inputValue.placeholder = 'A, B, N...';
            inputValue.addEventListener('change', updateHidden);
            tdValue.appendChild(inputValue);
            tr.appendChild(tdValue);

            var tdDelete = document.createElement('td');
            var btnDelete = document.createElement('button');
            btnDelete.type = 'button';
            btnDelete.className = 'btn btn-danger btn-xs';
            btnDelete.innerHTML = '<i class="icon-trash"></i>';
            btnDelete.addEventListener('click', function() {
                tr.remove();
                updateHidden();
            });
            tdDelete.appendChild(btnDelete);
            tr.appendChild(tdDelete);

            tbody.appendChild(tr);
        }

        // Fonction pour mettre à jour le champ caché
        function updateHidden() {
            var values = {};
            tbody.querySelectorAll('tr').forEach(function(tr) {
                var key = tr.querySelector('.col-map-key').value.trim();
                var value = tr.querySelector('.col-map-value').value.trim();
                if (key && value) {
                    values[key] = value;
                }
            });
            hidden.value = JSON.stringify(values);
        }

        // Bouton ajouter
        var btnAdd = document.createElement('button');
        btnAdd.type = 'button';
        btnAdd.className = 'btn btn-success btn-sm';
        btnAdd.innerHTML = '<i class="icon-plus"></i> ' + t('configEditor', 'addColumn');
        btnAdd.addEventListener('click', function() {
            addRow('', '');
        });
        wrapper.appendChild(btnAdd);

        // Pré-remplir avec les valeurs existantes
        if (currentValue && typeof currentValue === 'object') {
            Object.keys(currentValue).forEach(function(key) {
                addRow(key, currentValue[key]);
            });
        }
        // Ajouter une ligne vide si aucune valeur
        if (!currentValue || Object.keys(currentValue).length === 0) {
            addRow('', '');
        }

        return wrapper;
    }

    /**
     * Crée un éditeur de mapping colonnes sources (nom pour IA → colonne Excel source)
     */
    function createSourceColumnsMappingEditor(paramName, currentValue) {
        var wrapper = document.createElement('div');
        wrapper.className = 'source-columns-mapping-editor';
        wrapper.style.cssText = 'border: 1px solid #ddd; padding: 10px; border-radius: 4px; background: #f9f9f9;';

        var table = document.createElement('table');
        table.className = 'table table-condensed';
        table.style.marginBottom = '10px';
        table.innerHTML = '<thead><tr>' +
            '<th style="width: 40%;">' + t('configEditor', 'paramNameHeader') + '</th>' +
            '<th style="width: 50%;">' + t('configEditor', 'sourceColumnHeader') + '</th>' +
            '<th style="width: 10%;"></th>' +
            '</tr></thead>';

        var tbody = document.createElement('tbody');
        tbody.className = 'source-columns-mapping-tbody';
        table.appendChild(tbody);
        wrapper.appendChild(table);

        // Champ caché pour stocker les valeurs
        var hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.className = 'action-param';
        hidden.dataset.param = paramName;
        hidden.dataset.type = 'source_columns_mapping';
        hidden.value = JSON.stringify(currentValue || {});
        wrapper.appendChild(hidden);

        // Fonction pour ajouter une ligne
        function addRow(key, value) {
            var tr = document.createElement('tr');

            var tdKey = document.createElement('td');
            var inputKey = document.createElement('input');
            inputKey.type = 'text';
            inputKey.className = 'form-control input-sm src-col-map-key';
            inputKey.value = key || '';
            inputKey.placeholder = 'ex: marque, categorie_fab...';
            inputKey.addEventListener('change', updateHidden);
            tdKey.appendChild(inputKey);
            tr.appendChild(tdKey);

            var tdValue = document.createElement('td');
            var inputValue = document.createElement('input');
            inputValue.type = 'text';
            inputValue.className = 'form-control input-sm src-col-map-value';
            inputValue.value = value || '';
            inputValue.placeholder = 'FEUILLE:G ou FEUILLE:[M] ou FEUILLE:[G=BROCH,M]';
            inputValue.title = 'Formats:\n• FEUILLE:G → valeur simple (1-1)\n• FEUILLE:[M] → multilignes concaténées (1-N)\n• FEUILLE:[G=BROCH,M] → lignes où colonne G = BROCH, récupère colonne M';
            inputValue.addEventListener('change', updateHidden);
            tdValue.appendChild(inputValue);
            tr.appendChild(tdValue);

            var tdDelete = document.createElement('td');
            var btnDelete = document.createElement('button');
            btnDelete.type = 'button';
            btnDelete.className = 'btn btn-danger btn-xs';
            btnDelete.innerHTML = '<i class="icon-trash"></i>';
            btnDelete.addEventListener('click', function() {
                tr.remove();
                updateHidden();
            });
            tdDelete.appendChild(btnDelete);
            tr.appendChild(tdDelete);

            tbody.appendChild(tr);
        }

        // Fonction pour mettre à jour le champ caché
        function updateHidden() {
            var values = {};
            tbody.querySelectorAll('tr').forEach(function(tr) {
                var key = tr.querySelector('.src-col-map-key').value.trim();
                var value = tr.querySelector('.src-col-map-value').value.trim();
                if (key && value) {
                    values[key] = value;
                }
            });
            hidden.value = JSON.stringify(values);
        }

        // Bouton ajouter
        var btnAdd = document.createElement('button');
        btnAdd.type = 'button';
        btnAdd.className = 'btn btn-success btn-sm';
        btnAdd.innerHTML = '<i class="icon-plus"></i> ' + t('configEditor', 'addSourceColumn');
        btnAdd.addEventListener('click', function() {
            addRow('', '');
        });
        wrapper.appendChild(btnAdd);

        // Pré-remplir avec les valeurs existantes
        if (currentValue && typeof currentValue === 'object') {
            Object.keys(currentValue).forEach(function(key) {
                addRow(key, currentValue[key]);
            });
        }

        return wrapper;
    }

    /**
     * Récupère les valeurs actuelles des paramètres
     */
    function getCurrentActionValues(container) {
        var values = {};
        container.querySelectorAll('.action-param').forEach(function(input) {
            var paramName = input.dataset.param;
            if (input.type === 'checkbox') {
                values[paramName] = input.checked;
            } else if (input.tagName === 'SELECT') {
                values[paramName] = input.value;
            } else {
                values[paramName] = input.value;
            }
        });
        return values;
    }

    /**
     * Met à jour la visibilité des éléments conditionnels
     */
    function updateConditionalVisibility(container, currentValues, conditionalElements) {
        conditionalElements.forEach(function(item) {
            var show = false;
            var condition = item.condition;

            Object.keys(condition).forEach(function(fieldName) {
                var expectedValues = condition[fieldName];
                var currentValue = currentValues[fieldName];

                if (Array.isArray(expectedValues)) {
                    show = expectedValues.includes(currentValue);
                } else {
                    show = currentValue === expectedValues;
                }
            });

            item.element.style.display = show ? '' : 'none';
        });
    }

    /**
     * Ouverture de l'éditeur clé/valeur
     */
    function openKeyValueEditor(paramName, values) {
        var tbody = document.getElementById('keyvalue-tbody');
        tbody.innerHTML = '';

        Object.keys(values).forEach(function(key) {
            addKeyValueRowWithData(key, values[key]);
        });

        // Au moins une ligne vide
        if (Object.keys(values).length === 0) {
            addKeyValueRowWithData('', '');
        }

        currentKeyValueCallback = function(newValues) {
            // Mettre à jour le champ caché
            var hidden = document.querySelector('.action-param[data-param="' + paramName + '"][type="hidden"]');
            if (hidden) {
                hidden.value = JSON.stringify(newValues);
            }

            // Mettre à jour le bouton
            var btn = document.querySelector('.action-param-keyvalue[data-param="' + paramName + '"]');
            if (btn) {
                var count = Object.keys(newValues).length;
                btn.innerHTML = '<i class="icon-list"></i> ' + t('configEditor', 'editValues', count);
            }
        };

        pkoToggleKvModal(true);
    }

    /**
     * Ajout d'une ligne clé/valeur vide
     */
    function addKeyValueRow() {
        addKeyValueRowWithData('', '');
    }

    /**
     * Ajout d'une ligne clé/valeur avec données
     */
    function addKeyValueRowWithData(key, value) {
        var tbody = document.getElementById('keyvalue-tbody');
        var tr = document.createElement('tr');

        var tdKey = document.createElement('td');
        var inputKey = document.createElement('input');
        inputKey.type = 'text';
        inputKey.className = 'form-control input-sm kv-key';
        inputKey.value = key;
        tdKey.appendChild(inputKey);
        tr.appendChild(tdKey);

        var tdValue = document.createElement('td');
        var inputValue = document.createElement('input');
        inputValue.type = 'text';
        inputValue.className = 'form-control input-sm kv-value';
        inputValue.value = value;
        tdValue.appendChild(inputValue);
        tr.appendChild(tdValue);

        var tdDelete = document.createElement('td');
        var btnDelete = document.createElement('button');
        btnDelete.type = 'button';
        btnDelete.className = 'btn btn-danger btn-xs';
        btnDelete.innerHTML = '<i class="icon-trash"></i>';
        btnDelete.addEventListener('click', function() {
            tr.remove();
        });
        tdDelete.appendChild(btnDelete);
        tr.appendChild(tdDelete);

        tbody.appendChild(tr);
    }

    /**
     * Sauvegarde des clé/valeur
     */
    function saveKeyValue() {
        var values = {};
        document.querySelectorAll('#keyvalue-tbody tr').forEach(function(tr) {
            var key = tr.querySelector('.kv-key').value.trim();
            var value = tr.querySelector('.kv-value').value;
            if (key) {
                values[key] = value;
            }
        });

        if (currentKeyValueCallback) {
            currentKeyValueCallback(values);
        }

        pkoToggleKvModal(false);
    }

    /**
     * Sauvegarde de l'action
     */
    /**
     * Collect params from a single pipeline block's inputs
     */
    /**
     * Collect actions from a pipeline container (recursive for conditions)
     */
    function collectActionsFromContainer(listEl) {
        var actions = [];
        // Only direct children (not nested sub-pipeline blocks)
        var children = listEl.children;
        for (var i = 0; i < children.length; i++) {
            var block = children[i];
            if (!block.classList.contains('pipeline-action-block')) continue;

            var actionType = block.dataset.actionType;

            if (actionType === 'condition') {
                var condAction = { type: 'condition', branches: [] };

                // Collect all SI / SINON SI branch blocks
                block.querySelectorAll('.condition-branches > .condition-branch-block').forEach(function(branchBlock) {
                    var branchData = {};
                    var logicVal = branchBlock.querySelector('.condition-logic-value');
                    branchData.logic = logicVal ? logicVal.value : 'AND';

                    branchData.rules = [];
                    branchBlock.querySelectorAll('.condition-rules-list .condition-rule').forEach(function(row) {
                        branchData.rules.push({
                            field: buildConditionField(row),
                            operator: row.querySelector('.condition-operator').value || '=',
                            value: row.querySelector('.condition-value').value || ''
                        });
                    });

                    var subPipeline = branchBlock.querySelector('.condition-sub-pipeline');
                    branchData.actions = subPipeline ? collectActionsFromContainer(subPipeline) : [];

                    condAction.branches.push(branchData);
                });

                // ELSE sub-pipeline (from .condition-sinon-block)
                var sinonBlock = block.querySelector('.condition-sinon-block .condition-sub-pipeline');
                condAction.else_actions = sinonBlock ? collectActionsFromContainer(sinonBlock) : [];

                // ELSE fixed value
                var elseValInput = block.querySelector('.condition-else-val');
                if (elseValInput && elseValInput.value) {
                    condAction.else_value = elseValInput.value;
                }

                actions.push(condAction);

                // PUIS (toujours) — flatten into main pipeline after condition
                var puisBlock = block.querySelector('.condition-puis-block .condition-sub-pipeline');
                if (puisBlock) {
                    var puisActions = collectActionsFromContainer(puisBlock);
                    puisActions.forEach(function(a) { actions.push(a); });
                }
            } else {
                var action = { type: actionType };
                var paramsContainer = block.querySelector('.pipeline-action-params');
                if (paramsContainer) {
                    var params = collectParamsFromContainer(paramsContainer);
                    Object.keys(params).forEach(function(key) {
                        action[key] = params[key];
                    });
                }
                actions.push(action);
            }
        }
        return actions;
    }

    function collectParamsFromContainer(container) {
        var params = {};
        container.querySelectorAll('.action-param').forEach(function(input) {
            var paramName = input.dataset.param;
            var value;

            if (input.type === 'checkbox') {
                value = input.checked;
            } else if (input.type === 'number') {
                value = parseFloat(input.value);
                if (isNaN(value)) value = undefined;
            } else if (input.type === 'hidden') {
                try {
                    value = JSON.parse(input.value);
                } catch (e) {
                    value = input.dataset.type === 'columns_select' ? [] : {};
                }
            } else if (input.tagName === 'TEXTAREA') {
                var rawValue = input.value.trim();
                if (rawValue.startsWith('[') || rawValue.startsWith('{')) {
                    try { value = JSON.parse(rawValue); } catch (e) { value = rawValue; }
                } else {
                    value = rawValue;
                }
            } else if (input.tagName === 'SELECT') {
                value = input.value;
            } else {
                value = input.value;
            }

            if (value !== undefined && value !== '') {
                params[paramName] = value;
            }
        });
        return params;
    }

    function saveAction() {
        var mapping = currentConfig.mapping[currentMappingIndex];

        // Save source fields from modal
        var colVal = document.getElementById('modal-col-input').value.trim();
        var sheetVal = document.getElementById('modal-sheet-input').value.trim();
        var defaultVal = document.getElementById('modal-default-input').value.trim();
        mapping.col = colVal || undefined;
        mapping.sheet = sheetVal || undefined;
        mapping.default = defaultVal || undefined;

        // Collect pipeline actions recursively
        var actions = collectActionsFromContainer(document.getElementById('pipeline-list'));

        // Clean up old format
        delete mapping.action;
        delete mapping.condition;
        delete mapping.condition_else;
        delete mapping.else_actions;

        // Save unified pipeline
        if (actions.length > 0) {
            mapping.actions = actions;
        } else {
            delete mapping.actions;
        }

        $('#action-modal').modal('hide');
        renderMappings();
    }

    /**
     * Synchronisation vers JSON
     */
    function syncToJson() {
        if (!currentConfig) return;
        setEditorText(JSON.stringify(currentConfig, null, 2));
    }

    /**
     * Synchronisation depuis JSON
     */
    function syncFromJson() {
        try {
            var json = getEditorText();
            currentConfig = JSON.parse(json);

            document.getElementById('config-fournisseur').value = currentConfig.fournisseur || '';
            document.getElementById('config-type').value = currentConfig.type || 'FAB-DIS';

            renderMappings();
            renderSheets();
            renderAiConfig();
        } catch (e) {
            alert(t('configEditor', 'jsonParseError', e.message));
        }
    }

    /**
     * Sauvegarde de la configuration
     */
    function saveConfig() {
        // Synchroniser les métadonnées
        updateConfigMeta();

        // Synchroniser les feuilles
        updateSheetsConfig();

        // Synchroniser depuis JSON si on est sur l'onglet JSON
        if (document.querySelector('#tab-json.active')) {
            try {
                currentConfig = JSON.parse(getEditorText());
            } catch (e) {
                alert(t('configEditor', 'jsonParseError', e.message));
                return;
            }
        }

        var name = document.getElementById('config-name').value;
        if (!name) {
            alert(t('configEditor', 'pleaseEnterConfigName'));
            return;
        }

        var idConfig = parseInt(document.getElementById('config-id').value) || 0;

        var formData = new FormData();
        formData.append('id_config', idConfig);
        formData.append('name', name);
        formData.append('config', JSON.stringify(currentConfig));

        fetch(pkoaiConfigUrls.save, {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                alert(t('configEditor', 'configSaved'));
                // Mettre à jour l'ID si c'était une nouvelle config
                if (data.id_config) {
                    document.getElementById('config-id').value = data.id_config;
                    currentConfigId = data.id_config;
                }
                location.reload();
            } else {
                alert(t('errors', 'unknownError') + ': ' + data.error);
            }
        });
    }

    /**
     * Suppression de la configuration
     */
    function deleteConfig() {
        var name = document.getElementById('config-name').value;
        var idConfig = parseInt(document.getElementById('config-id').value) || 0;

        if (!idConfig) {
            alert(t('configEditor', 'noConfigToDelete'));
            return;
        }

        if (!confirm(t('configEditor', 'confirmDeleteConfig', name))) {
            return;
        }

        var formData = new FormData();
        formData.append('id_config', idConfig);

        fetch(pkoaiConfigUrls.delete, {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                // Rediriger vers la page d'import
                if (pkoaiConfigUrls.importPage) {
                    window.location.href = pkoaiConfigUrls.importPage;
                } else {
                    location.reload();
                }
            } else {
                alert(t('errors', 'unknownError') + ': ' + data.error);
            }
        });
    }

    /**
     * Export de la configuration en JSON
     */
    function exportConfig() {
        var idConfig = parseInt(document.getElementById('config-id').value) || 0;

        if (!idConfig) {
            alert(t('configEditor', 'noConfigToExport'));
            return;
        }

        // Ouvrir l'URL d'export dans une nouvelle fenêtre pour télécharger
        window.location.href = pkoaiConfigUrls.export + '&id_config=' + idConfig;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Intégration Lunar / Filament (PKOS)
    //
    // Le moteur d'édition de pipeline ci-dessus est lifté verbatim du module
    // PrestaShop « Publiko AI Importer » (vue config-editor). On NE lance PAS son
    // init() full-page (table de mapping, feuilles, JSON, AJAX vers les
    // controllers PS) : seul le sous-système « modal pipeline » est réutilisé,
    // piloté par l'API exposée ci-dessous et monté dans une modal Filament.
    //
    // Globals attendus (injectés par la vue Blade avant le mount) :
    //   window.actionTypes, window.actionGroups, window.conditionOperators,
    //   window.llmConfigs, window.prestashopColumns, window.pkoaiTranslations
    // ─────────────────────────────────────────────────────────────────────────

    // Remplace la sous-modal Bootstrap clé/valeur (pas de jQuery dans Filament).
    function pkoToggleKvModal(show) {
        var m = document.getElementById('keyvalue-modal');
        if (m) {
            m.style.display = show ? 'flex' : 'none';
        }
    }

    var pkoOnChange = null;

    // Reconstruit l'état canonique {sheet, col, default, actions} depuis le DOM.
    function pkoCollect() {
        var get = function (id) {
            var el = document.getElementById(id);
            return el ? (el.value || '').trim() : '';
        };
        var list = document.getElementById('pipeline-list');
        return {
            sheet: get('modal-sheet-input') || undefined,
            col: get('modal-col-input') || undefined,
            'default': get('modal-default-input') || undefined,
            actions: list ? collectActionsFromContainer(list) : []
        };
    }

    function pkoEmitChange() {
        if (typeof pkoOnChange === 'function') {
            try {
                pkoOnChange(pkoCollect());
            } catch (e) { /* noop */ }
        }
    }

    function pkoClear() {
        var list = document.getElementById('pipeline-list');
        if (list) {
            list.innerHTML = '';
        }
        updatePipelineEmptyState();
        pkoEmitChange();
    }

    /**
     * Monte l'éditeur dans une modal Filament déjà rendue (DOM identique à la
     * .tpl PrestaShop). `opts` :
     *   - seed    : {sheet, col, default, actions[]} de la colonne à éditer
     *   - label   : libellé de la colonne (indices de params multi-lignes)
     *   - root    : élément racine de l'éditeur (écoute des changements)
     *   - onChange: callback(stateCanonique) à chaque modification
     */
    function pkoMount(opts) {
        opts = opts || {};
        var seed = opts.seed || {};

        // Neutralise tout couplage avec l'état full-page du module PS.
        currentConfig = { mapping: {} };
        currentMappingIndex = opts.label || null;
        activePipeline = null;
        currentKeyValueCallback = null;

        var setVal = function (id, v) {
            var el = document.getElementById(id);
            if (el) { el.value = (v === undefined || v === null) ? '' : v; }
        };
        setVal('modal-sheet-input', seed.sheet);
        setVal('modal-col-input', seed.col);
        setVal('modal-default-input', seed['default']);

        renderActionGrid();
        loadPipeline({ actions: Array.isArray(seed.actions) ? seed.actions : [] });

        pkoOnChange = opts.onChange || null;

        var root = opts.root || document;
        // Toute interaction (saisie, ajout/suppression d'action, drag) resynchronise
        // l'état vers le champ hôte. Capture pour attraper aussi les clics de boutons.
        root.addEventListener('input', pkoEmitChange, true);
        root.addEventListener('change', pkoEmitChange, true);
        root.addEventListener('click', function () { setTimeout(pkoEmitChange, 0); }, true);

        var addKv = document.getElementById('add-keyvalue-btn');
        if (addKv) { addKv.onclick = function () { addKeyValueRow(); }; }
        var saveKv = document.getElementById('save-keyvalue-btn');
        if (saveKv) { saveKv.onclick = function () { saveKeyValue(); pkoEmitChange(); }; }
        var clearBtn = document.getElementById('clear-actions-btn');
        if (clearBtn) { clearBtn.onclick = function () { pkoClear(); }; }

        pkoEmitChange();
    }

    window.PkoPipelineEditor = { mount: pkoMount, collect: pkoCollect, clear: pkoClear };
})();
