<?php
/**
 * Liste administrative des comptes webmail - Réservée aux administrateurs
 * Permet de voir et gérer tous les comptes utilisateurs
 */

// Load Dolibarr environment
$res = 0;
$paths = [
    dirname(dirname(dirname(__DIR__))).'/main.inc.php',
    '../../../main.inc.php',
    '../../main.inc.php',
    '../main.inc.php'
];

foreach ($paths as $path) {
    if (file_exists($path)) {
        $res = @include $path;
        break;
    }
}

if (!$res) {
    die("Erreur : Impossible de charger main.inc.php.");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

// Load translation files
$langs->loadLangs(array("users", "mails", "admin"));

// Vérifier les droits d'administration
if (!$user->hasRight('roundcubemodule', 'admin', 'write')) {
    accessforbidden('Vous n\'avez pas les droits pour administrer les comptes webmail');
}

// Parameters
$action = GETPOST('action', 'aZ09');
$accountid = GETPOST('accountid', 'int');
$userid = GETPOST('userid', 'int');
$confirm = GETPOST('confirm', 'alpha');
$search_user = GETPOST('search_user', 'alpha');
$search_email = GETPOST('search_email', 'alpha');
$search_status = GETPOST('search_status', 'alpha');

// Actions administratives
if ($action == 'toggle_active' && $accountid) {
    $sql = "UPDATE ".MAIN_DB_PREFIX."mailboxmodule_webmail_accounts SET is_active = 1 - is_active WHERE rowid = ".$accountid;
    if ($db->query($sql)) {
        setEventMessages("Statut du compte modifié", null, 'mesgs');
    } else {
        setEventMessages("Erreur lors de la modification", null, 'errors');
    }
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

if ($action == 'delete' && $confirm == 'yes' && $accountid) {
    $sql = "DELETE FROM ".MAIN_DB_PREFIX."mailboxmodule_webmail_accounts WHERE rowid = ".$accountid;
    if ($db->query($sql)) {
        setEventMessages("Compte supprimé", null, 'mesgs');
    } else {
        setEventMessages("Erreur lors de la suppression", null, 'errors');
    }
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

if ($action == 'reset_errors' && $accountid) {
    $sql = "UPDATE ".MAIN_DB_PREFIX."mailboxmodule_webmail_accounts SET error_count = 0, last_error = NULL WHERE rowid = ".$accountid;
    if ($db->query($sql)) {
        setEventMessages("Erreurs réinitialisées", null, 'mesgs');
    } else {
        setEventMessages("Erreur lors de la réinitialisation", null, 'errors');
    }
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

/*
 * View
 */

$form = new Form($db);

llxHeader('', 'Administration des comptes webmail', '');

print '<h1>🛡️ Administration des comptes webmail</h1>';

// Filtres de recherche
print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<div class="div-table-responsive">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>Utilisateur</td>';
print '<td>Email</td>';
print '<td>Statut</td>';
print '<td></td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td><input type="text" name="search_user" value="'.$search_user.'" placeholder="Nom ou login..." class="flat"></td>';
print '<td><input type="text" name="search_email" value="'.$search_email.'" placeholder="Email..." class="flat"></td>';
print '<td>';
print '<select name="search_status" class="flat">';
print '<option value="">Tous</option>';
print '<option value="1"'.($search_status == '1' ? ' selected' : '').'>Actifs</option>';
print '<option value="0"'.($search_status == '0' ? ' selected' : '').'>Inactifs</option>';
print '<option value="error"'.($search_status == 'error' ? ' selected' : '').'>En erreur</option>';
print '</select>';
print '</td>';
print '<td>';
print '<input type="submit" value="Filtrer" class="button">';
print ' <a href="'.$_SERVER["PHP_SELF"].'" class="button">Reset</a>';
print '</td>';
print '</tr>';
print '</table>';
print '</div>';
print '</form>';

print '<br>';

// Construction de la requête avec filtres
$sql = "SELECT wa.*, u.login, u.firstname, u.lastname, u.email as user_email ";
$sql .= "FROM ".MAIN_DB_PREFIX."mailboxmodule_webmail_accounts wa ";
$sql .= "LEFT JOIN ".MAIN_DB_PREFIX."user u ON wa.fk_user = u.rowid ";
$sql .= "WHERE 1=1 ";

if (!empty($search_user)) {
    $sql .= "AND (u.login LIKE '%".$db->escape($search_user)."%' OR u.firstname LIKE '%".$db->escape($search_user)."%' OR u.lastname LIKE '%".$db->escape($search_user)."%') ";
}

if (!empty($search_email)) {
    $sql .= "AND wa.email LIKE '%".$db->escape($search_email)."%' ";
}

if ($search_status !== '') {
    if ($search_status == 'error') {
        $sql .= "AND wa.error_count > 0 ";
    } else {
        $sql .= "AND wa.is_active = ".$db->escape($search_status)." ";
    }
}

$sql .= "ORDER BY u.lastname ASC, u.firstname ASC, wa.is_default DESC";

$resql = $db->query($sql);

if ($resql) {
    $num = $db->num_rows($resql);
    
    print '<div class="div-table-responsive">';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<th>Utilisateur</th>';
    print '<th>Compte</th>';
    print '<th>Email</th>';
    print '<th>Serveur</th>';
    print '<th class="center">Statut</th>';
    print '<th class="center">Dernière connexion</th>';
    print '<th class="center">Erreurs</th>';
    print '<th class="center">Actions</th>';
    print '</tr>';
    
    if ($num == 0) {
        print '<tr><td colspan="8" class="center opacitymedium">Aucun compte trouvé</td></tr>';
    } else {
        $i = 0;
        while ($obj = $db->fetch_object($resql)) {
            $i++;
            
            print '<tr class="oddeven">';
            
            // Utilisateur
            print '<td>';
            $user_obj = new User($db);
            $user_obj->fetch($obj->fk_user);
            print $user_obj->getNomUrl(1);
            print '<br><small>'.$obj->login.'</small>';
            print '</td>';
            
            // Nom du compte
            print '<td>';
            print $obj->account_name ?: '<em>Sans nom</em>';
            if ($obj->is_default) {
                print ' <span class="badge badge-status1">Défaut</span>';
            }
            print '</td>';
            
            // Email
            print '<td>'.$obj->email.'</td>';
            
            // Serveur
            print '<td>';
            print $obj->imap_host.':'.$obj->imap_port;
            if ($obj->imap_encryption) {
                print ' ('.$obj->imap_encryption.')';
            }
            print '</td>';
            
            // Statut
            print '<td class="center">';
            if ($obj->is_active) {
                print '<span class="badge badge-status4">Actif</span>';
            } else {
                print '<span class="badge badge-status8">Inactif</span>';
            }
            print '</td>';
            
            // Dernière connexion
            print '<td class="center">';
            if ($obj->last_connection) {
                print dol_print_date($db->jdate($obj->last_connection), 'dayhour');
                print '<br><small>('.$obj->connection_count.' connexions)</small>';
            } else {
                print '<span class="opacitymedium">Jamais</span>';
            }
            print '</td>';
            
            // Erreurs
            print '<td class="center">';
            if ($obj->error_count > 0) {
                print '<span class="badge badge-status8">'.$obj->error_count.'</span>';
                if ($obj->last_error) {
                    print '<br><small title="'.$obj->last_error.'">'.dol_trunc($obj->last_error, 30).'</small>';
                }
            } else {
                print '<span class="badge badge-status4">0</span>';
            }
            print '</td>';
            
            // Actions
            print '<td class="center nowraponall">';
            
            // Test de connexion
            $roundcube_url = dol_buildpath('/custom/roundcubemodule/roundcube_auto_login.php?accountid='.$obj->rowid, 1);
            print '<a class="marginleftonly marginrightonly" href="'.$roundcube_url.'" target="_blank" title="Tester la connexion">';
            print img_picto('Tester', 'globe');
            print '</a>';
            
            // Activer/Désactiver
            print '<a class="marginleftonly marginrightonly" href="'.$_SERVER["PHP_SELF"].'?action=toggle_active&accountid='.$obj->rowid.'" title="'.($obj->is_active ? 'Désactiver' : 'Activer').'">';
            print img_picto($obj->is_active ? 'Désactiver' : 'Activer', $obj->is_active ? 'switch_off' : 'switch_on');
            print '</a>';
            
            // Réinitialiser les erreurs
            if ($obj->error_count > 0) {
                print '<a class="marginleftonly marginrightonly" href="'.$_SERVER["PHP_SELF"].'?action=reset_errors&accountid='.$obj->rowid.'" title="Réinitialiser les erreurs">';
                print img_picto('Reset erreurs', 'refresh');
                print '</a>';
            }
            
            // Éditer (redirige vers la fiche utilisateur)
            $edit_url = dol_buildpath('/user/card.php?id='.$obj->fk_user.'&tab=webmail&action=edit&accountid='.$obj->rowid, 1);
            print '<a class="marginleftonly marginrightonly" href="'.$edit_url.'" title="Modifier">';
            print img_edit();
            print '</a>';
            
            // Supprimer
            print '<a class="marginleftonly marginrightonly" href="'.$_SERVER["PHP_SELF"].'?action=delete&accountid='.$obj->rowid.'" title="Supprimer">';
            print img_delete();
            print '</a>';
            
            print '</td>';
            print '</tr>';
        }
    }
    
    print '</table>';
    print '</div>';
    
    // Statistiques
    print '<br>';
    print '<div class="info">';
    print '<strong>📊 Statistiques :</strong><br>';
    
    // Compter les comptes par statut
    $sql_stats = "SELECT 
        COUNT(*) as total,
        SUM(is_active) as actifs,
        SUM(CASE WHEN error_count > 0 THEN 1 ELSE 0 END) as erreurs,
        COUNT(DISTINCT fk_user) as utilisateurs
        FROM ".MAIN_DB_PREFIX."mailboxmodule_webmail_accounts";
    $result_stats = $db->query($sql_stats);
    
    if ($result_stats && $stats = $db->fetch_object($result_stats)) {
        print '• <strong>Total comptes :</strong> '.$stats->total.'<br>';
        print '• <strong>Comptes actifs :</strong> '.$stats->actifs.'<br>';
        print '• <strong>Comptes en erreur :</strong> '.$stats->erreurs.'<br>';
        print '• <strong>Utilisateurs avec comptes :</strong> '.$stats->utilisateurs;
    }
    
    print '</div>';
    
} else {
    print '<div class="error">Erreur lors de la récupération des comptes : '.$db->lasterror().'</div>';
}

// Confirm delete
if ($action == 'delete' && !$confirm) {
    print $form->formconfirm(
        $_SERVER["PHP_SELF"].'?accountid='.$accountid,
        'Supprimer le compte',
        'Êtes-vous sûr de vouloir supprimer ce compte webmail ?<br><strong>Cette action est irréversible.</strong>',
        'delete',
        '',
        0,
        1
    );
}

// Actions en bas de page
print '<div class="tabsAction">';
print '<a class="butAction" href="'.dol_buildpath('/user/list.php', 1).'">👥 Liste des utilisateurs</a>';
print '<a class="butAction" href="'.dol_buildpath('/custom/roundcubemodule/admin/roundcube_config.php', 1).'">⚙️ Configuration</a>';
print '</div>';

llxFooter();
$db->close();
?>