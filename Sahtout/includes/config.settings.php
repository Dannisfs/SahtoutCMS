<?php
if (!defined('ALLOWED_ACCESS')) {
    header('HTTP/1.1 403 Forbidden');
    exit('Direct access not allowed.');
}

// Site Title (Editable from Admin Panel)
$site_title_name = 'rebornWOW';

// Logo
$site_logo = 'img/logo.png';

// BugTracker URL
$bugtracker_url = 'https://github.com/wowshub/AzerothCore-wotlk-with-PlayerBots-NPCBots/issues';

// Server Status Info
$server_realmlist = 'yangyanghub.com:8088';
$server_version = '3.3.5a';
$server_status_text = 'Stable';

// Social links
$social_links = [
    'facebook' => 'https://facebook.com/blodyiheb',
    'twitter' => 'https://x.com/blodyiheb',
    'tiktok' => 'https://tiktok.com/blodyiheb',
    'youtube' => 'https://www.youtube.com/@Blodyone',
    'discord' => 'https://discord.gg/chxXTXXQ6M',
    'twitch' => 'https://twitch.tv',
    'kick' => 'https://kick.com/blodyiheb',
    'instagram' => 'https://instagram.com',
    'github' => 'https://github.com/blodyiheb/SahtoutCMS',
    'linkedin' => 'https://linkedin.com',
];
