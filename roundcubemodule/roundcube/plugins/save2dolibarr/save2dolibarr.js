$(document).ready(function () {
    console.log('Plugin save2dolibarr chargé');
    
    rcmail.register_command('plugin.save2dolibarr', function () {
        console.log('Commande save2dolibarr déclenchée');
        
        var uid = null;
        var mbox = rcmail.env.mailbox;
        
        // Récupérer l'UID selon le contexte
        if (rcmail.env.uid) {
            uid = rcmail.env.uid;
        } else if (rcmail.message_list && rcmail.message_list.get_selection) {
            var selection = rcmail.message_list.get_selection();
            if (selection && selection.length > 0) {
                uid = selection[0];
            }
        }

        console.log('UID trouvé:', uid, 'MBOX:', mbox);

        if (!uid || !mbox) {
            rcmail.display_message("Sélectionner un mail à classer.", 'error');
            return;
        }

        console.log('Ouverture du modal pour UID:', uid, 'MBOX:', mbox);

        window.save2dolibarr_current_uid = uid;
        window.save2dolibarr_current_mbox = mbox;
        
        // Afficher le modal
        $('#save2dolibarr_overlay').show();
        $('#save2dolibarr_modal').show();
        $('body').css('overflow', 'hidden');

        // Réinitialiser le contenu
        $('#sender_info').html('Recherche en cours...');
        $('#active_modules_checkbox_container').html('<div style="text-align: center; padding: 20px;"><span class="loading-spinner"></span> Chargement des modules...</div>');

        // Charger les modules actifs
        loadActiveModules(uid, mbox);

    }, true);

    // S'assurer que la commande est bien enregistrée et activée
    console.log('Enregistrement de la commande plugin.save2dolibarr');
    rcmail.enable_command('plugin.save2dolibarr', true);
    
    // Écouter les changements d'état pour maintenir le bouton actif
    rcmail.addEventListener('listupdate', function() {
        rcmail.enable_command('plugin.save2dolibarr', true);
    });
    
    rcmail.addEventListener('selectfolder', function() {
        rcmail.enable_command('plugin.save2dolibarr', true);
    });

    function loadActiveModules(uid, mbox) {
        console.log('Chargement des modules actifs...');
        
        $.ajax({
            url: './?_task=mail&_action=plugin.get_active_dolibarr_modules',
            type: 'GET',
            dataType: 'json',
            timeout: 15000,
            success: function (response) {
                console.log('Réponse get_active_dolibarr_modules:', response);
                var container = $('#active_modules_checkbox_container');
                container.empty();

                // Vérifier s'il y a une erreur
                if (response.error) {
                    var errorMsg = '❌ ' + response.error;
                    if (response.details) errorMsg += '<br><small>Détails: ' + response.details + '</small>';
                    if (response.url) errorMsg += '<br><small>URL: ' + response.url + '</small>';
                    if (response.http_code) errorMsg += '<br><small>Code HTTP: ' + response.http_code + '</small>';
                    
                    container.html('<div style="padding:10px; color:#d32f2f;">' + errorMsg + '</div>');
                    getSenderEmailAndCheckDolibarr(uid, mbox);
                    return;
                }

                // Déterminer la structure des modules
                var activeModules = response;
                if (response.modules && Array.isArray(response.modules)) {
                    activeModules = response.modules;
                }

                if (Array.isArray(activeModules) && activeModules.length > 0) {
                    activeModules.forEach(function (module) {
                        var moduleValue = module.value || module.code;
                        var moduleLabel = module.label;
                        
                        var html = `
                        <div class="module-row" data-module="${moduleValue}" style="display: flex; align-items: stretch; gap: 15px; padding: 15px; border: 1px solid #ddd; border-radius: 6px; background: white; transition: all 0.3s;">
                            <div style="flex: 0 0 200px; display: flex; align-items: center;">
                                <label style="font-weight: 500; color: #333; margin: 0; font-size: 14px;">
                                    ${moduleLabel}
                                </label>
                            </div>
                            <div style="flex: 1; position: relative;">
                                <input type="text" id="search_${moduleValue}" data-module="${moduleValue}" 
                                       style="width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; 
                                              background: white; transition: all 0.2s;" 
                                       placeholder="Rechercher un ${moduleLabel.replace(/🏢|👤|📋|📄|🛒|💰|🎫|📋/g, '').trim()}..."
                                       autocomplete="off">
                                <div id="suggestions_${moduleValue}" style="position: absolute; top: 100%; left: 0; right: 0; 
                                     background: white; border: 1px solid #ddd; border-top: none; border-radius: 0 0 4px 4px; 
                                     max-height: 200px; overflow-y: auto; z-index: 1000; display: none; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                                </div>
                            </div>
                            <div style="flex: 0 0 120px; display: flex; align-items: center; justify-content: center;">
                                <span id="status_${moduleValue}" style="font-size: 12px; color: #666; text-align: center;">
                                    Prêt à rechercher
                                </span>
                            </div>
                        </div>
                        `;
                        container.append(html);
                    });
                    
                    // Attacher les événements de recherche directement
                    attachSearchEvents();

                    getSenderEmailAndCheckDolibarr(uid, mbox);

                } else {
                    container.html('<div style="padding:10px; color:#d32f2f;">Aucun module actif trouvé ou erreur de configuration Dolibarr.</div>');
                    getSenderEmailAndCheckDolibarr(uid, mbox);
                }
            },
            error: function (xhr, status, error) {
                console.error('Erreur AJAX get_active_dolibarr_modules:', status, error);
                console.error('Response text:', xhr.responseText);
                var errorMsg = 'Erreur lors du chargement des modules actifs.<br><small>' + error + '</small>';
                if (xhr.responseText) {
                    errorMsg += '<br><small>Réponse: ' + xhr.responseText.substring(0, 200) + '</small>';
                }
                $('#active_modules_checkbox_container').html('<div style="padding:10px; color:#d32f2f;">' + errorMsg + '</div>');
                getSenderEmailAndCheckDolibarr(uid, mbox);
            }
        });
    }

    // Gestion des événements de fermeture du modal
    $('#save2dolibarr_close, #save2dolibarr_overlay').click(function () {
        closeModal();
    });

    function closeModal() {
        $('#save2dolibarr_overlay').hide();
        $('#save2dolibarr_modal').hide();
        $('body').css('overflow', '');
        
        // Nettoyer les suggestions ouvertes
        hideSearchResults();
    }
    
    $(document).keyup(function(e) {
        if (e.key === "Escape" && $('#save2dolibarr_modal').is(':visible')) {
            closeModal();
        }
    });

    let checkSenderUrl = rcmail.env.save2dolibarr_check_sender_url;

    // Fonction pour attacher les événements de recherche
    function attachSearchEvents() {
        var searchTimeout;
        
        $(document).off('input', '[id^="search_"]').on('input', '[id^="search_"]', function() {
            var $input = $(this);
            var query = $input.val().trim();
            var module = $input.data('module');
            var $moduleRow = $input.closest('.module-row');
            
            console.log('Saisie détectée dans', module, '- Valeur:', query);
            
            clearTimeout(searchTimeout);
            
            // Réinitialiser l'état si le champ est vidé
            if (query.length === 0) {
                $input.removeData('selected-id');
                $input.css('border-color', '#ddd');
                $('#status_' + module).text('Prêt à rechercher');
                $moduleRow.css({
                    'background': 'white',
                    'border-color': '#ddd'
                });
                hideSearchResults(module);
                return;
            }
            
            if (query.length < 2) {
                $('#status_' + module).text('Tapez au moins 2 caractères');
                $input.removeData('selected-id');
                $input.css('border-color', '#ff9800');
                $moduleRow.css({
                    'background': '#fff8e1',
                    'border-color': '#ff9800'
                });
                hideSearchResults(module);
                return;
            }
            
            // État de recherche
            $('#status_' + module).html('<span class="loading-spinner"></span> Recherche...');
            $input.css('border-color', '#2196f3');
            $moduleRow.css({
                'background': '#e3f2fd',
                'border-color': '#2196f3'
            });
            
            searchTimeout = setTimeout(function() {
                console.log('Lancement de la recherche pour', module, 'avec:', query);
                performSearch(module, query, $input);
            }, 300);
        });

        // Événement pour la gestion de l'auto-remplissage des tiers
        $(document).off('input', '[id^="search_thirdparty"]').on('input', '[id^="search_thirdparty"]', function() {
            var $input = $(this);
            var query = $input.val().trim();
            
            // Si c'est un tiers et qu'on a les infos de l'expéditeur
            if (query.length === 0) {
                setTimeout(function() {
                    autoFillThirdpartyIfAvailable($input);
                }, 100);
            }
        });
    }

    // Fonction d'auto-remplissage pour les tiers
    function autoFillThirdpartyIfAvailable($input) {
        var senderInfo = $('#sender_info');
        if (senderInfo.find('.found').length > 0) {
            var thirdpartyText = senderInfo.text();
            var thirdpartyMatch = thirdpartyText.match(/Tiers trouvé\s*:\s*(.+?)(?:\s*\(|$)/);
            if (thirdpartyMatch && thirdpartyMatch[1]) {
                var thirdpartyName = thirdpartyMatch[1].trim();
                $input.val(thirdpartyName);
                $input.data('selected-id', 'auto-detected');
                $input.css('border-color', '#4caf50');
                $('#status_thirdparty').html('✅ Auto-détecté');
                $input.closest('.module-row').css({
                    'background': '#e8f5e9',
                    'border-color': '#4caf50'
                });
            }
        }
    }

    // Fonction pour valider qu'un champ a une sélection
    function isFieldValid(module) {
        var $input = $('#search_' + module);
        var value = $input.val().trim();
        var selectedId = $input.data('selected-id');
        return value.length > 0 && selectedId;
    }

    // Fonction pour marquer un champ comme valide
    function markFieldAsValid(module) {
        var $input = $('#search_' + module);
        var $moduleRow = $input.closest('.module-row');
        
        $input.css('border-color', '#4caf50');
        $('#status_' + module).html('✅ Sélectionné');
        $moduleRow.css({
            'background': '#e8f5e9',
            'border-color': '#4caf50'
        });
    }

    // Fonction pour marquer un champ comme invalide
    function markFieldAsInvalid(module) {
        var $input = $('#search_' + module);
        var $moduleRow = $input.closest('.module-row');
        
        $input.css('border-color', '#f44336');
        $('#status_' + module).text('❌ Sélection requise');
        $moduleRow.css({
            'background': '#ffebee',
            'border-color': '#f44336'
        });
    }

    function getSenderEmailAndCheckDolibarr(uid, mbox) {
        $.ajax({
            url: './?_task=mail&_action=plugin.get_sender_email',
            type: 'POST',
            data: { _uid: uid, _mbox: mbox },
            dataType: 'json',
            success: function (response) {
                console.log('Réponse get_sender_email:', response);
                
                if (response.email) {
                    $.ajax({
                        url: checkSenderUrl,
                        type: 'POST',
                        data: { email: response.email },
                        dataType: 'json',
                        success: function (sender_response) {
                            console.log('Réponse check_sender:', sender_response);
                            
                            if (sender_response.found) {
                                var senderName = sender_response.name || sender_response.societe?.nom || 'Tiers trouvé';
                                $('#sender_info').html('<span style="background:#e8f5e9; color:#2e7d32; padding:4px 8px; border-radius:4px;">✅ <strong>Tiers trouvé :</strong> ' + senderName + '</span>');
                                
                                // Auto-remplir le champ tiers si disponible
                                setTimeout(function() {
                                    var $thirdpartyInput = $('#search_thirdparty');
                                    if ($thirdpartyInput.length && $thirdpartyInput.val().trim() === '') {
                                        autoFillThirdpartyIfAvailable($thirdpartyInput);
                                    }
                                }, 500);
                            } else {
                                $('#sender_info').html('<span style="background:#fff3e0; color:#e65100; padding:4px 8px; border-radius:4px;">⚠️ <strong>Expéditeur inconnu :</strong> ' + response.email + '</span>');
                            }
                        },
                        error: function () {
                            $('#sender_info').html('<span style="color:#d32f2f;">Erreur lors de la vérification du tiers</span>');
                        }
                    });
                } else {
                    $('#sender_info').html('<span style="color:#d32f2f;">Email expéditeur non trouvé</span>');
                }
            },
            error: function () {
                $('#sender_info').html('<span style="color:#d32f2f;">Erreur lors de la récupération des infos du mail</span>');
            }
        });
    }

    // Fonction de recherche
    function performSearch(module, query, $input) {
        $.ajax({
            url: './?_task=mail&_action=plugin.save2dolibarr_search_targets',
            method: 'GET',
            data: { q: query, type: module },
            dataType: 'json',
            timeout: 10000,
            success: function (data) {
                var $status = $('#status_' + module);
                
                console.log('Résultats recherche pour', module, ':', data);
                
                if (Array.isArray(data)) {
                    if (data.length > 0) {
                        $status.text(data.length + ' résultat(s)');
                        showSearchResults(data, $input, module);
                    } else {
                        $status.text('Aucun résultat');
                        showSearchResults([], $input, module); // Afficher "Aucun résultat"
                    }
                } else if (data && data.error) {
                    $status.text('Erreur: ' + data.error);
                    hideSearchResults(module);
                } else {
                    $status.text('Erreur de format');
                    hideSearchResults(module);
                }
            },
            error: function (xhr, status, error) {
                console.error('Erreur search_targets:', status, error);
                $('#status_' + module).text('Erreur réseau');
                hideSearchResults(module);
            }
        });
    }

    // Afficher les résultats de recherche
    function showSearchResults(results, $input, module) {
        var $suggestions = $('#suggestions_' + module);
        $suggestions.empty();
        
        console.log('Affichage des suggestions pour', module, '- Nombre:', results.length);
        
        if (results.length === 0) {
            $suggestions.html('<div style="padding: 8px 12px; color: #666; font-style: italic; background: #f9f9f9;">Aucun résultat trouvé</div>');
        } else {
            results.forEach(function(item, index) {
                var $suggestion = $('<div>')
                    .html('<div style="font-weight: 500;">' + item.label + '</div><small style="color: #666;">ID: ' + item.id + '</small>')
                    .css({ 
                        cursor: 'pointer', 
                        padding: '8px 12px',
                        borderBottom: index < results.length - 1 ? '1px solid #eee' : 'none',
                        transition: 'background 0.2s',
                        fontSize: '14px',
                        background: 'white'
                    })
                    .hover(
                        function() { $(this).css('background', '#f0f7ff'); },
                        function() { $(this).css('background', 'white'); }
                    )
                    .on('click', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        console.log('Suggestion cliquée:', item.label);
                        
                        $input.val(item.label);
                        $input.data('selected-id', item.id);
                        markFieldAsValid(module);
                        hideSearchResults(module);
                    });
                $suggestions.append($suggestion);
            });
        }
        
        $suggestions.show();
        console.log('Liste déroulante affichée pour', module);
    }

    // Masquer les résultats pour un module spécifique
    function hideSearchResults(module) {
        if (module) {
            $('#suggestions_' + module).hide();
            console.log('Liste déroulante masquée pour', module);
        } else {
            // Masquer toutes les suggestions
            $('[id^="suggestions_"]').hide();
            console.log('Toutes les listes déroulantes masquées');
        }
    }

    // Masquer les suggestions si on clique ailleurs
    $(document).on('click', function(e) {
        // Ne pas masquer si on clique sur un input de recherche ou une suggestion
        if (!$(e.target).closest('[id^="search_"], [id^="suggestions_"]').length) {
            hideSearchResults();
        }
    });

    // Masquer les suggestions quand on tape Escape
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            hideSearchResults();
        }
    });

    $('#save2dolibarr_submit').click(function () {
        var targets = [];
        var hasErrors = false;
        var hasSelections = false;

        // Vérifier tous les champs de recherche qui ont une valeur
        $('[id^="search_"]').each(function() {
            var $input = $(this);
            var module = $input.data('module');
            var value = $input.val().trim();
            
            if (value.length > 0) {
                hasSelections = true;
                var selectedId = $input.data('selected-id');
                
                if (!selectedId) {
                    markFieldAsInvalid(module);
                    hasErrors = true;
                } else {
                    markFieldAsValid(module);
                    targets.push({ type: module, id: selectedId });
                }
            }
        });

        if (!hasSelections) {
            rcmail.display_message("Veuillez sélectionner au moins un module et effectuer une recherche.", 'warning');
            return;
        }

        if (hasErrors) {
            rcmail.display_message("Veuillez sélectionner des éléments valides dans les listes déroulantes.", 'error');
            return;
        }

        closeModal();
        
        rcmail.display_message('Classement du mail en cours...', 'loading');
        
        rcmail.http_post('plugin.save2dolibarr', {
            _uid: window.save2dolibarr_current_uid,
            _mbox: window.save2dolibarr_current_mbox,
            _links: JSON.stringify(targets)
        }, { 
            timeout: 60000
        });
    });

    $('#save2dolibarr_submit_only').click(function () {
        closeModal();
        
        rcmail.display_message('Sauvegarde du mail en cours...', 'loading');
        
        rcmail.http_post('plugin.save2dolibarr', {
            _uid: window.save2dolibarr_current_uid,
            _mbox: window.save2dolibarr_current_mbox,
            _links: JSON.stringify([])
        }, { 
            timeout: 60000
        });
    });
});