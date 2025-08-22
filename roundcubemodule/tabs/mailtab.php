<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

$socid = GETPOST('socid', 'int');
$soc = new Societe($db);
$soc->fetch($socid);
$email_client = $soc->email;

llxHeader('', 'Boîte mail du client : '.$soc->name);

print load_fiche_titre("Mails reçus de : ".$email_client);

// Connexion IMAP
$hostname = '{imap.gmail.com:993/imap/ssl}INBOX';
$username = 'guykeny.ndayizeye8@gmail.com';
$password = 'yrtfzngztljfmiov';

$inbox = @imap_open($hostname, $username, $password);

if (!$inbox) {
    print '<div class="error">Erreur connexion IMAP : '.imap_last_error().'</div>';
} else {
    $emails = imap_search($inbox, 'FROM "'.$email_client.'"', SE_UID);
    if ($emails) {
        rsort($emails); // Mails les plus récents d’abord
        echo "<ul>";
        foreach (array_slice($emails, 0, 10) as $uid) {
            $header = imap_headerinfo($inbox, imap_msgno($inbox, $uid));
            $subject = isset($header->subject) ? htmlspecialchars($header->subject) : '(Sans sujet)';
            echo "<li><strong>".dol_print_date(strtotime($header->date), 'dayhour')."</strong> - ".$subject."</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>Aucun mail trouvé pour ce client.</p>";
    }
    imap_close($inbox);
}

llxFooter();
