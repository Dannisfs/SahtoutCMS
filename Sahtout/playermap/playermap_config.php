<?php
// Playermap configuration loader

$conf_path = "/etc/playermap/playermap.conf";
if (!file_exists($conf_path)) {
    $conf_path = __DIR__ . "/playermap.conf";
}

if (!file_exists($conf_path)) {
    die("playermap config file missing: $conf_path");
}

$_playermap_conf = parse_ini_file($conf_path, true, INI_SCANNER_TYPED);

if (!$_playermap_conf) {
    die("Unable to parse config file: $conf_path");
}

$gen = $_playermap_conf['general'] ?? [];

$language      = $gen['language'] ?? 'en';
$site_encoding = $gen['site_encoding'] ?? 'utf-8';
$db_type       = $gen['db_type'] ?? 'MySQL';
$server_type   = $gen['server_type'] ?? 1;

$gm_online                         = $gen['gm_online'] ?? true;
$gm_online_count                   = $gen['gm_online_count'] ?? 100;
$map_gm_show_online_only_gmoff     = $gen['map_gm_show_online_only_gmoff'] ?? 1;
$map_gm_show_online_only_gmvisible = $gen['map_gm_show_online_only_gmvisible'] ?? 1;
$map_gm_add_suffix                 = $gen['map_gm_add_suffix'] ?? 1;
$map_status_gm_include_all         = $gen['map_status_gm_include_all'] ?? 0;
$map_show_status                   = $gen['map_show_status'] ?? 1;
$map_show_time                     = $gen['map_show_time'] ?? 1;
$map_time                          = $gen['map_time'] ?? 5;
$map_time_to_show_uptime           = $gen['map_time_to_show_uptime'] ?? 3000;
$map_time_to_show_maxonline        = $gen['map_time_to_show_maxonline'] ?? 3000;
$map_time_to_show_gmonline         = $gen['map_time_to_show_gmonline'] ?? 3000;
$developer_test_mode               = $gen['developer_test_mode'] ?? false;
$multi_realm_mode                  = $gen['multi_realm_mode'] ?? true;

$realm_id                          = $gen['realm_id'] ?? 1;

$realm_db = [
    'addr'     => $gen['realm_db_addr']     ?? '127.0.0.1:3306',
    'user'     => $gen['realm_db_user']     ?? 'acore',
    'pass'     => $gen['realm_db_pass']     ?? 'acore',
    'name'     => $gen['realm_db_name']     ?? 'acore_auth',
    'encoding' => $gen['realm_db_encoding'] ?? 'utf8',
];

$world_db = [];
$characters_db = [];
$server = [];

// Find all [realm_X] sections
foreach ($_playermap_conf as $section => $arr) {
    if (preg_match('/^realm_(\d+)$/', $section, $m)) {
        $rid = (int)$m[1];
        $world_db[$rid] = [
            'addr'     => $arr['world_db_addr']     ?? '127.0.0.1:3306',
            'user'     => $arr['world_db_user']     ?? 'acore',
            'pass'     => $arr['world_db_pass']     ?? 'acore',
            'name'     => $arr['world_db_name']     ?? 'acore_world',
            'encoding' => $arr['world_db_encoding'] ?? 'utf8',
        ];
        $characters_db[$rid] = [
            'addr'     => $arr['characters_db_addr']     ?? '127.0.0.1:3306',
            'user'     => $arr['characters_db_user']     ?? 'acore',
            'pass'     => $arr['characters_db_pass']     ?? 'acore',
            'name'     => $arr['characters_db_name']     ?? 'acore_characters',
            'encoding' => $arr['characters_db_encoding'] ?? 'utf8',
        ];
        $server[$rid] = [
            'addr'          => $arr['server_addr']       ?? '127.0.0.1',
            'addr_wan'      => $arr['server_addr_wan']   ?? '127.0.0.1',
            'game_port'     => $arr['server_game_port']  ?? 8085,
            'rev'           => $arr['server_rev']        ?? '',
            'both_factions' => $arr['server_both_factions'] ?? true,
        ];
    }
}

?>
