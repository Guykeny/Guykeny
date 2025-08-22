/**
 * Bandeau JavaScript Manager - VERSION STABLE SANS RÉINITIALISATION
 * Gestion des interactions du bandeau de classement Roundcube
 * 
 * Emplacement: custom/roundcubemodule/components/bandeau/js/bandeau.js
 */

console.log('🔧 Chargement Bandeau JavaScript Manager (Version Stable Sans Réinitialisation)...');

// Variables globales
let currentMailData = null;
let currentMailUID = null;
let currentMailId = null;
let selectedEntities = { societe: null, contact: null, projet: null };
let searchTimeout = null;
let lastProcessedMailUID = null;
let isFormDisplayed = false; // Pour tracker si le formulaire est affiché

/**
 * Gestionnaire principal des messages Roundcube
 * NE RÉINITIALISE PAS LE FORMULAIRE SI DÉJÀ AFFICHÉ
 */
window.handleRoundcubeMessage = function(e) {
    if (e.data && typeof e.data === 'object') {
        if (e.data.type && e.data.type === 'roundcube_mail_selected' && e.data.data) {
            const newUID = e.data.data.uid;
            
            // Si c'est le même mail, on ne fait rien
            if (newUID && newUID === currentMailUID) {
                console.log('📧 Même mail, pas de mise à jour:', newUID);
                return;
            }
            
            console.log('📨 Nouveau mail détecté:', newUID);
            
            // Mettre à jour les données du mail SANS toucher au formulaire
            currentMailData = e.data.data;
            currentMailUID = newUID;
            currentMailId = e.data.data.message_id;
            
            // Mettre à jour uniquement l'affichage des infos du mail
            updateMailInfo(e.data.data);
        }
    }
};

/**
 * Mettre à jour UNIQUEMENT les informations du mail sans toucher aux champs de recherche
 */
function updateMailInfo(mailData) {
    // Si le formulaire n'est pas encore affiché, on l'affiche
    if (!isFormDisplayed) {
        showClassificationForm(mailData);
        return;
    }
    
    // Sinon, on met à jour UNIQUEMENT la zone d'info du mail
    const mailInfoContainer = document.querySelector('.mail-data-container');
    if (mailInfoContainer) {
        console.log('📋 Mise à jour des infos du mail uniquement');
        mailInfoContainer.innerHTML = `
            <p style="margin: 5px 0;"><strong>Sujet:</strong> ${mailData.subject || 'N/A'}</p>
            <p style="margin: 5px 0;"><strong>De:</strong> ${mailData.from || mailData.from_email || 'N/A'}</p>
            <p style="margin: 5px 0;"><strong>UID:</strong> ${mailData.uid || 'N/A'}</p>
            ${mailData.date ? `<p style="margin: 5px 0;"><strong>Date:</strong> ${new Date(mailData.date).toLocaleString('fr-FR')}</p>` : ''}
        `;
    }
}

/**
 * Afficher le formulaire de classement UNIQUEMENT la première fois
 */
function showClassificationForm(mailData) {
    const container = document.getElementById('classification-form');
    const noSelection = document.getElementById('classification-no-selection');
    
    if (!container || !noSelection) {
        console.error('❌ Conteneurs de classement non trouvés');
        return;
    }
    
    console.log('📋 Affichage initial du formulaire pour le mail:', mailData.uid);
    
    noSelection.style.display = 'none';
    container.style.display = 'block';
    container.innerHTML = generateClassificationFormHTML(mailData);
    
    isFormDisplayed = true;
    
    // Restaurer les sélections si elles existent
    restoreSelections();
}

/**
 * Générer le HTML du formulaire de classement
 */
function generateClassificationFormHTML(mailData) {
    return `
        <div class="classification-form-container">
            <h4 style="color: white; margin-bottom: 15px;">📧 Mail sélectionné :</h4>
            <div class="mail-data-container" style="background: rgba(255,255,255,0.1); padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                <p style="margin: 5px 0;"><strong>Sujet:</strong> ${mailData.subject || 'N/A'}</p>
                <p style="margin: 5px 0;"><strong>De:</strong> ${mailData.from || mailData.from_email || 'N/A'}</p>
                <p style="margin: 5px 0;"><strong>UID:</strong> ${mailData.uid || 'N/A'}</p>
                ${mailData.date ? `<p style="margin: 5px 0;"><strong>Date:</strong> ${new Date(mailData.date).toLocaleString('fr-FR')}</p>` : ''}
            </div>
            
            <h5 style="color: white; margin-bottom: 15px;">📁 Classement :</h5>
            
            <div class="classification-field">
                <label>🏢 Tiers:</label>
                <input type="text" 
                       id="search-societe" 
                       placeholder="Tapez pour rechercher un tiers..." 
                       autocomplete="off">
                <div id="suggestions-societe" class="suggestions-container"></div>
                <div id="selected-societe" class="selected-entity" style="display:none;"></div>
            </div>
            
            <div class="classification-field">
                <label>👤 Contact:</label>
                <input type="text" 
                       id="search-contact" 
                       placeholder="Tapez pour rechercher un contact..." 
                       autocomplete="off">
                <div id="suggestions-contact" class="suggestions-container"></div>
                <div id="selected-contact" class="selected-entity" style="display:none;"></div>
            </div>
            
            <div class="classification-field">
                <label>📋 Projet:</label>
                <input type="text" 
                       id="search-projet" 
                       placeholder="Tapez pour rechercher un projet..." 
                       autocomplete="off">
                <div id="suggestions-projet" class="suggestions-container"></div>
                <div id="selected-projet" class="selected-entity" style="display:none;"></div>
            </div>
            
            <div class="classification-actions" style="margin-top: 20px;">
                <button class="btn btn-primary" onclick="classifyAndSaveMail()" style="background: #28a745;">
                    📁 Classer ce mail
                </button>
                <button class="btn" onclick="saveMailWithoutLinks()" style="background: #6c757d;">
                    💾 Sauvegarder sans lien
                </button>
                <button class="btn" onclick="clearAllSelections()" style="background: #dc3545;">
                    🔄 Réinitialiser
                </button>
            </div>
            
            <div id="classification-status" style="margin-top: 15px; display: none;">
                <!-- Zone pour afficher le statut de sauvegarde -->
            </div>
        </div>
    `;
}

/**
 * Initialiser les événements de recherche APRÈS création du formulaire
 */
function initSearchEvents() {
    ['societe', 'contact', 'projet'].forEach(type => {
        const input = document.getElementById(`search-${type}`);
        if (input) {
            // Retirer l'ancien event handler s'il existe
            input.onkeyup = null;
            
            // Ajouter le nouveau avec debounce
            input.addEventListener('keyup', function(e) {
                const value = e.target.value;
                handleSearchInput(type, value);
            });
            
            // Empêcher la soumission du formulaire sur Enter
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                }
            });
        }
    });
}

/**
 * Gérer la recherche avec debounce
 */
function handleSearchInput(type, query) {
    // Annuler la recherche précédente
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }
    
    // Si la recherche est trop courte
    if (query.length < 2) {
        hideSearchResults(type);
        return;
    }
    
    // Afficher "Recherche..."
    const suggestionsContainer = document.getElementById(`suggestions-${type}`);
    if (suggestionsContainer) {
        suggestionsContainer.innerHTML = '<div class="suggestion-item">Recherche...</div>';
        suggestionsContainer.style.display = 'block';
    }
    
    // Lancer la recherche après 500ms
    searchTimeout = setTimeout(() => {
        performSearch(type, query);
    }, 500);
}

/**
 * Effectuer la recherche
 */
function performSearch(type, query) {
    const typeMap = { 
        'societe': 'thirdparty', 
        'contact': 'contact', 
        'projet': 'project'
    };
    
    const apiType = typeMap[type] || type;
    const url = `${CONFIG.API_URL}?action=search_entities&type=${apiType}&query=${encodeURIComponent(query)}`;
    
    console.log('🔎 Recherche', type, ':', query);
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.results) {
                showSearchResults(type, data.results);
            } else {
                showSearchResults(type, []);
            }
        })
        .catch(error => {
            console.error('❌ Erreur recherche', type, ':', error);
            showSearchResults(type, []);
        });
}

// Rendre la fonction globale pour compatibilité
window.searchEntity = function(type, query) {
    handleSearchInput(type, query);
};

/**
 * Afficher les résultats de recherche
 */
function showSearchResults(type, results) {
    const container = document.getElementById(`suggestions-${type}`);
    if (!container) return;
    
    container.innerHTML = '';
    
    if (results.length === 0) {
        container.innerHTML = '<div class="suggestion-item" style="font-style: italic; color: #999;">Aucun résultat trouvé</div>';
    } else {
        results.forEach(result => {
            const item = document.createElement('div');
            item.className = 'suggestion-item';
            item.style.cssText = 'padding: 8px; cursor: pointer; border-bottom: 1px solid rgba(255,255,255,0.1);';
            item.innerHTML = `
                <strong>${result.label || result.name}</strong>
                <small style="display: block; color: #999;">ID: ${result.id}</small>
            `;
            
            item.onclick = function() {
                selectEntity(type, result);
            };
            
            item.onmouseenter = function() {
                this.style.background = 'rgba(255,255,255,0.1)';
            };
            
            item.onmouseleave = function() {
                this.style.background = 'transparent';
            };
            
            container.appendChild(item);
        });
    }
    
    container.style.display = 'block';
}

/**
 * Sélectionner une entité
 */
function selectEntity(type, entity) {
    const input = document.getElementById(`search-${type}`);
    const selectedDiv = document.getElementById(`selected-${type}`);
    
    if (input) {
        input.value = entity.label || entity.name;
        input.classList.add('field-selected');
        input.style.background = 'rgba(40, 167, 69, 0.2)';
        input.disabled = true; // Désactiver le champ une fois sélectionné
    }
    
    if (selectedDiv) {
        selectedDiv.innerHTML = `
            <span style="color: #28a745;">✅ ${entity.label || entity.name}</span>
            <button onclick="clearSelection('${type}')" style="margin-left: 10px; padding: 2px 8px; background: #dc3545; color: white; border: none; border-radius: 3px; cursor: pointer;">✖</button>
        `;
        selectedDiv.style.display = 'block';
    }
    
    selectedEntities[type] = entity;
    hideSearchResults(type);
    
    console.log(`✅ ${type} sélectionné:`, entity);
    showNotification(`✅ ${type} sélectionné: ${entity.label || entity.name}`, 'success');
}

/**
 * Effacer une sélection spécifique
 */
function clearSelection(type) {
    const input = document.getElementById(`search-${type}`);
    const selectedDiv = document.getElementById(`selected-${type}`);
    
    if (input) {
        input.value = '';
        input.classList.remove('field-selected');
        input.style.background = 'transparent';
        input.disabled = false; // Réactiver le champ
    }
    
    if (selectedDiv) {
        selectedDiv.style.display = 'none';
    }
    
    selectedEntities[type] = null;
    console.log(`❌ Sélection ${type} effacée`);
}

/**
 * Effacer toutes les sélections
 */
function clearAllSelections() {
    ['societe', 'contact', 'projet'].forEach(type => {
        clearSelection(type);
    });
    showNotification('🔄 Toutes les sélections effacées', 'info');
}

/**
 * Restaurer les sélections après mise à jour du formulaire
 */
function restoreSelections() {
    Object.keys(selectedEntities).forEach(type => {
        if (selectedEntities[type]) {
            const entity = selectedEntities[type];
            const input = document.getElementById(`search-${type}`);
            const selectedDiv = document.getElementById(`selected-${type}`);
            
            if (input) {
                input.value = entity.label || entity.name;
                input.classList.add('field-selected');
                input.style.background = 'rgba(40, 167, 69, 0.2)';
                input.disabled = true;
            }
            
            if (selectedDiv) {
                selectedDiv.innerHTML = `
                    <span style="color: #28a745;">✅ ${entity.label || entity.name}</span>
                    <button onclick="clearSelection('${type}')" style="margin-left: 10px; padding: 2px 8px; background: #dc3545; color: white; border: none; border-radius: 3px; cursor: pointer;">✖</button>
                `;
                selectedDiv.style.display = 'block';
            }
        }
    });
    
    // Réinitialiser les événements
    initSearchEvents();
}

/**
 * Masquer les résultats de recherche
 */
function hideSearchResults(type) {
    const container = document.getElementById(`suggestions-${type}`);
    if (container) {
        container.style.display = 'none';
    }
}

/**
 * FONCTION PRINCIPALE : Classer et sauvegarder le mail
 */
async function classifyAndSaveMail() {
    console.log('📁 Début du classement et sauvegarde du mail...');
    console.log('Current mail data:', currentMailData);
    console.log('Selected entities:', selectedEntities);
    
    // Vérifications
    if (!currentMailData) {
        showNotification('❌ Aucun mail sélectionné', 'error');
        return;
    }
    
    const hasSelection = Object.values(selectedEntities).some(entity => entity !== null);
    if (!hasSelection) {
        showNotification('⚠️ Veuillez sélectionner au moins un élément pour le classement', 'warning');
        return;
    }
    
    updateClassificationStatus('Préparation de la sauvegarde...', 'loading');
    
    try {
        // Préparer les données pour save_mails.php
        const saveData = {
            uid: String(currentMailData.uid || ''),
            mbox: currentMailData.folder || currentMailData.mailbox || 'INBOX',
            message_id: currentMailData.message_id || `<${Date.now()}@roundcube>`,
            subject: currentMailData.subject || 'Sans sujet',
            from: currentMailData.from || currentMailData.from_email || 'unknown@example.com',
            raw_email: currentMailData.subject || 'Contenu du mail',
            date: currentMailData.date || new Date().toISOString(),
            links: []
        };
        
        // Ajouter les liens de classement
        if (selectedEntities.societe) {
            saveData.links.push({
                type: 'societe',
                id: parseInt(selectedEntities.societe.id),
                name: selectedEntities.societe.label || selectedEntities.societe.name || ''
            });
        }
        
        if (selectedEntities.contact) {
            saveData.links.push({
                type: 'contact',
                id: parseInt(selectedEntities.contact.id),
                name: selectedEntities.contact.label || selectedEntities.contact.name || ''
            });
        }
        
        if (selectedEntities.projet) {
            saveData.links.push({
                type: 'projet',
                id: parseInt(selectedEntities.projet.id),
                name: selectedEntities.projet.label || selectedEntities.projet.name || ''
            });
        }
        
        console.log('📤 Données à envoyer:', JSON.stringify(saveData, null, 2));
        
        const saveUrl = CONFIG.SAVE_URL || '/custom/roundcubemodule/scripts/save_mails.php';
        console.log('URL de sauvegarde:', saveUrl);
        
        updateClassificationStatus('Envoi au serveur...', 'loading');
        
        // Appeler save_mails.php
        const response = await fetch(saveUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(saveData)
        });
        
        console.log('Response status:', response.status);
        
        const responseText = await response.text();
        console.log('Response text:', responseText);
        
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (e) {
            console.error('Erreur parsing JSON:', e);
            console.error('Réponse brute:', responseText);
            
            if (responseText.includes('Fatal error') || responseText.includes('Warning')) {
                showNotification('❌ Erreur PHP dans save_mails.php - Voir console', 'error');
                updateClassificationStatus('❌ Erreur serveur PHP', 'error');
                return;
            }
            
            throw new Error('Réponse invalide du serveur');
        }
        
        console.log('📥 Réponse parsée:', result);
        
        // Gérer la réponse
        if (result.status === 'OK') {
            updateClassificationStatus(`✅ Mail classé et sauvegardé! (ID: ${result.mail_id})`, 'success');
            showNotification('✅ Mail classé et sauvegardé avec succès!', 'success');
            
            // Effacer les sélections après succès
            setTimeout(() => {
                clearAllSelections();
            }, 2000);
            
        } else if (result.status === 'ALREADY_CLASSIFIED') {
            updateClassificationStatus('⚠️ Ce mail est déjà classé', 'warning');
            showNotification('⚠️ Ce mail est déjà classé', 'warning');
            
        } else if (result.status === 'DIFFERENT_LINKS') {
            handleDifferentLinks(result);
            
        } else if (result.status === 'ERROR') {
            updateClassificationStatus(`❌ Erreur: ${result.message}`, 'error');
            showNotification(`❌ Erreur: ${result.message}`, 'error');
            
        } else {
            updateClassificationStatus('❌ Réponse inattendue', 'error');
            showNotification('❌ Erreur lors de la sauvegarde', 'error');
        }
        
    } catch (error) {
        console.error('❌ Erreur lors du classement:', error);
        updateClassificationStatus(`❌ Erreur: ${error.message}`, 'error');
        showNotification(`❌ Erreur: ${error.message}`, 'error');
    }
}

/**
 * Sauvegarder le mail sans liens de classement
 */
async function saveMailWithoutLinks() {
    console.log('💾 Sauvegarde du mail sans classement...');
    
    if (!currentMailData) {
        showNotification('❌ Aucun mail sélectionné', 'error');
        return;
    }
    
    updateClassificationStatus('Sauvegarde sans classement...', 'loading');
    
    try {
        const saveData = {
            uid: String(currentMailData.uid || ''),
            mbox: currentMailData.folder || currentMailData.mailbox || 'INBOX',
            message_id: currentMailData.message_id || `<${Date.now()}@roundcube>`,
            subject: currentMailData.subject || 'Sans sujet',
            from: currentMailData.from || currentMailData.from_email || 'unknown@example.com',
            raw_email: currentMailData.subject || 'Contenu du mail',
            date: currentMailData.date || new Date().toISOString(),
            links: [] // Pas de liens
        };
        
        const saveUrl = CONFIG.SAVE_URL || '/custom/roundcubemodule/scripts/save_mails.php';
        
        const response = await fetch(saveUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(saveData)
        });
        
        const responseText = await response.text();
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (e) {
            console.error('Erreur parsing:', e);
            throw new Error('Réponse invalide');
        }
        
        if (result.status === 'OK' || result.status === 'ALREADY_CLASSIFIED') {
            updateClassificationStatus('✅ Mail sauvegardé sans classement', 'success');
            showNotification('✅ Mail sauvegardé sans classement', 'success');
            
        } else {
            updateClassificationStatus(`❌ Erreur: ${result.message}`, 'error');
            showNotification(`❌ Erreur: ${result.message}`, 'error');
        }
        
    } catch (error) {
        console.error('❌ Erreur:', error);
        updateClassificationStatus(`❌ Erreur: ${error.message}`, 'error');
        showNotification(`❌ Erreur: ${error.message}`, 'error');
    }
}

/**
 * Gérer le cas où le mail a déjà des liens différents
 */
function handleDifferentLinks(result) {
    const statusDiv = document.getElementById('classification-status');
    if (!statusDiv) return;
    
    let html = '<div style="background: rgba(255,193,7,0.2); padding: 10px; border-radius: 5px;">';
    html += '<h5 style="color: #ffc107;">⚠️ Ce mail est déjà classé différemment</h5>';
    
    if (result.existing && result.existing.length > 0) {
        html += '<p><strong>Classement actuel:</strong></p><ul>';
        result.existing.forEach(link => {
            html += `<li>${link.target_name || link.name} (${link.target_type || link.type})</li>`;
        });
        html += '</ul>';
    }
    
    html += '<div style="margin-top: 10px;">';
    html += `<button onclick="reclassifyMail('sync_links')" class="btn" style="background: #28a745;">Remplacer</button> `;
    html += `<button onclick="reclassifyMail('add_links')" class="btn" style="background: #007bff;">Ajouter</button> `;
    html += `<button onclick="clearAllSelections()" class="btn" style="background: #6c757d;">Annuler</button>`;
    html += '</div></div>';
    
    statusDiv.innerHTML = html;
    statusDiv.style.display = 'block';
}

/**
 * Reclasser le mail
 */
async function reclassifyMail(action) {
    console.log(`📁 Reclassement avec action: ${action}`);
    
    updateClassificationStatus('Mise à jour...', 'loading');
    
    try {
        const saveData = {
            uid: String(currentMailData.uid || ''),
            mbox: currentMailData.folder || currentMailData.mailbox || 'INBOX',
            message_id: currentMailData.message_id || '',
            subject: currentMailData.subject || 'Sans sujet',
            from: currentMailData.from || currentMailData.from_email || '',
            raw_email: currentMailData.subject || 'Contenu',
            links: [],
            action: action
        };
        
        // Ajouter les liens
        if (selectedEntities.societe) {
            saveData.links.push({
                type: 'societe',
                id: parseInt(selectedEntities.societe.id),
                name: selectedEntities.societe.label || ''
            });
        }
        
        if (selectedEntities.contact) {
            saveData.links.push({
                type: 'contact',
                id: parseInt(selectedEntities.contact.id),
                name: selectedEntities.contact.label || ''
            });
        }
        
        if (selectedEntities.projet) {
            saveData.links.push({
                type: 'projet',
                id: parseInt(selectedEntities.projet.id),
                name: selectedEntities.projet.label || ''
            });
        }
        
        const saveUrl = CONFIG.SAVE_URL || '/custom/roundcubemodule/scripts/save_mails.php';
        
        const response = await fetch(saveUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(saveData)
        });
        
        const result = await response.json();
        
        if (result.status === 'UPDATED') {
            updateClassificationStatus('✅ Classement mis à jour!', 'success');
            showNotification('✅ Classement mis à jour!', 'success');
            
            setTimeout(() => {
                clearAllSelections();
            }, 2000);
            
        } else {
            updateClassificationStatus(`❌ Erreur: ${result.message}`, 'error');
        }
        
    } catch (error) {
        console.error('❌ Erreur:', error);
        updateClassificationStatus(`❌ Erreur: ${error.message}`, 'error');
    }
}

/**
 * Mettre à jour le statut
 */
function updateClassificationStatus(message, type) {
    const statusDiv = document.getElementById('classification-status');
    if (!statusDiv) return;
    
    const colors = {
        loading: '#007bff',
        success: '#28a745',
        warning: '#ffc107',
        error: '#dc3545'
    };
    
    statusDiv.innerHTML = `
        <div style="background: rgba(255,255,255,0.1); padding: 10px; border-radius: 5px; border-left: 4px solid ${colors[type]};">
            ${type === 'loading' ? '⏳' : ''} ${message}
        </div>
    `;
    statusDiv.style.display = 'block';
    
    if (type === 'success') {
        setTimeout(() => {
            statusDiv.style.display = 'none';
        }, 5000);
    }
}

/**
 * Réinitialiser COMPLÈTEMENT le formulaire
 */
function resetClassificationForm() {
    console.log('🔄 Réinitialisation complète du formulaire...');
    
    selectedEntities = { societe: null, contact: null, projet: null };
    currentMailData = null;
    currentMailUID = null;
    currentMailId = null;
    lastProcessedMailUID = null;
    isFormDisplayed = false;
    
    const container = document.getElementById('classification-form');
    const noSelection = document.getElementById('classification-no-selection');
    
    if (container) {
        container.style.display = 'none';
        container.innerHTML = '';
    }
    
    if (noSelection) {
        noSelection.style.display = 'block';
    }
}

/**
 * Fonction de notification
 */
function showNotification(message, type = 'info') {
    const notification = document.getElementById('notification');
    if (!notification) return;
    
    notification.className = 'show ' + type;
    notification.textContent = message;
    
    setTimeout(() => {
        notification.className = '';
    }, 4000);
}

/**
 * Test manuel
 */
function testMail() {
    console.log('🧪 Test manuel...');
    
    // Réinitialiser pour forcer un nouveau mail
    currentMailUID = null;
    
    const testData = {
        type: 'roundcube_mail_selected',
        data: {
            uid: 'test_' + Date.now(),
            message_id: '<test.' + Date.now() + '@example.com>',
            subject: 'Mail de test - ' + new Date().toLocaleTimeString(),
            from: 'Test User <test@example.com>',
            from_email: 'test@example.com',
            date: new Date().toISOString(),
            folder: 'INBOX'
        }
    };
    
    window.handleRoundcubeMessage({ data: testData });
    showNotification('📧 Mail de test chargé', 'info');
}

/**
 * Initialisation
 */
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Bandeau JavaScript - Initialisation...');
    
    // Écouter les messages
    window.addEventListener('message', handleRoundcubeMessage);
    
    // Vérifier la configuration
    if (typeof CONFIG !== 'undefined') {
        console.log('✅ Configuration chargée:', {
            API_URL: CONFIG.API_URL,
            SAVE_URL: CONFIG.SAVE_URL,
            USER_ID: CONFIG.USER_ID
        });
    } else {
        console.error('❌ CONFIG non défini!');
    }
    
    // Initialiser les événements après un court délai
    setTimeout(() => {
        initSearchEvents();
    }, 500);
    
    console.log('✅ Bandeau initialisé');
});

// Export des fonctions
window.classifyAndSaveMail = classifyAndSaveMail;
window.saveMailWithoutLinks = saveMailWithoutLinks;
window.reclassifyMail = reclassifyMail;
window.searchEntity = searchEntity;
window.selectEntity = selectEntity;
window.clearSelection = clearSelection;
window.clearAllSelections = clearAllSelections;
window.resetClassificationForm = resetClassificationForm;
window.testMail = testMail;

console.log('✅ Bandeau JavaScript chargé - Version Stable Sans Réinitialisation');