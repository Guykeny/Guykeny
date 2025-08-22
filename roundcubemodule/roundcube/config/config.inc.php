<?php

/* Local configuration for Roundcube Webmail */

$config = array();

// Base de donnees
$config['db_dsnw'] = 'mysql://root:@localhost/roundcubemail';

// Serveurs mail
$config['imap_host'] = 'ssl://sucralose.o2switch.net:993';
$config['default_host'] = ['ssl://mail.%s:993', 'localhost'];
$config['default_port'] = 143;

$config['smtp_host'] = 'tls://sucralose.o2switch.net:587';
$config['smtp_server'] = 'tls://mail.%s:587';
$config['smtp_port'] = 25;
$config['smtp_user'] = '%u';
$config['smtp_pass'] = '%p';
$config['smtp_auth_type'] = 'PLAIN';

// Securite
$config['des_key'] = 'rcmail-!24ByteDESkey*Str';
$config['support_url'] = '';

// Plugins
$config['plugins'] = ['archive', 'password', 'userinfo', 'save2dolibarr', 'autologon', 'sentlogger'];

// Configuration generale
$config['product_name'] = 'Roundcube Webmail pour Dolibarr';
$config['auto_create_user'] = true;
$config['login_autocomplete'] = 2;
$config['session_lifetime'] = 60;
$config['session_auth_name'] = 'roundcube_dolibarr';
$config['language'] = 'fr_FR';

// Debug
$config['debug_level'] = 4095;
$config['log_driver'] = 'file';
$config['log_dir'] = __DIR__ . '/logs/';

// SSL Options
$config['imap_debug'] = true;
$config['smtp_debug'] = true;
$config['imap_conn_options'] = [
  'ssl' => [
    'verify_peer'       => false,
    'verify_peer_name'  => false,
    'allow_self_signed' => true,
  ],
];
$config['smtp_conn_options'] = [
  'ssl' => [
    'verify_peer'       => false,
    'verify_peer_name'  => false,
    'allow_self_signed' => true,
  ],
];

// Tables Roundcube standard (sans prefixe)
$config['db_table_users'] = 'users';
$config['db_table_identities'] = 'identities';
$config['db_table_contacts'] = 'contacts';
$config['db_table_contactgroups'] = 'contactgroups';
$config['db_table_contactgroupmembers'] = 'contactgroupmembers';
$config['db_table_session'] = 'session';
$config['db_table_cache'] = 'cache';
$config['db_table_cache_shared'] = 'cache_shared';
$config['db_table_cache_messages'] = 'cache_messages';
$config['db_table_cache_thread'] = 'cache_thread';
$config['db_table_cache_index'] = 'cache_index';
$config['db_table_messages'] = 'messages';
$config['db_table_headers'] = 'headers';

// Autologin Dolibarr
$config['autologin_db_dsn'] = 'mysql://root:@localhost/roundcubemail';
$config['autologin_db_table'] = 'llx_user';
$config['autologin_db_userid_field'] = 'login';
$config['autologin_db_passwd_field'] = 'pass_crypted';
$config['autologin_db_email_field'] = 'email';

$config['autologon_db_dsn'] = 'mysql://root:@localhost/roundcubemail';
$config['autologon_db_table'] = 'llx_user';
$config['autologon_db_userid_field'] = 'login';
$config['autologon_db_passwd_field'] = 'pass_crypted';
$config['autologon_db_email_field'] = 'email';
