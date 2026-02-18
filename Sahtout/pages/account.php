<?php
ob_start(); // Start output buffering to catch any unexpected output
define('ALLOWED_ACCESS', true);
require_once __DIR__ . '/../includes/paths.php'; // Include paths.php
require_once $project_root . 'includes/session.php';
require_once $project_root . 'includes/srp6.php';
require_once $project_root . 'languages/language.php'; // Include language file for translations

// Early session validation
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header("Location: {$base_path}login?error=invalid_session");
    exit();
}

// AJAX Handler for Avatars (Performance Optimization)
if (isset($_GET['action']) && $_GET['action'] === 'get_avatars') {
    header('Content-Type: application/json');
    $avatars = [];
    if (!$site_db->connect_error) {
        $stmt = $site_db->prepare("SELECT filename, display_name FROM profile_avatars WHERE active = 1");
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $avatars[] = [
                'filename' => $row['filename'],
                'display_name' => translate('avatar_' . str_replace('.', '_', $row['filename']), $row['filename'])
            ];
        }
        $stmt->close();
    }
    echo json_encode($avatars);
    exit;
}

// Initialize variables
$accountInfo = [];
$banInfo = [];
$message = '';
$error = '';
$characters = [];
$activityLog = [];
$teleport_cooldowns = [];
$currencies = ['points' => 0, 'tokens' => 0, 'avatar' => NULL];
$available_avatars = [];
$gmlevel = $_SESSION['gmlevel'] ?? 0;
$role = $_SESSION['role'] ?? 'player';
$debug_errors = $_SESSION['debug_errors'] ?? [];

// Retrieve and clear session messages
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}
if (isset($_SESSION['debug_errors'])) {
    $debug_errors = $_SESSION['debug_errors'];
    unset($_SESSION['debug_errors']);
}

// Handle form submissions before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($auth_db->connect_error || $char_db->connect_error || $site_db->connect_error) {
        $_SESSION['error'] = translate('error_database_connection', 'Database connection failed');
        header("Location: {$base_path}account");
        exit();
    }

    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['error'] = translate('error_invalid_form_submission', 'Invalid form submission');
        header("Location: {$base_path}account");
        exit();
    }

    // Handle email change
    if (isset($_POST['change_email'])) {
        $new_email = filter_var($_POST['new_email'], FILTER_SANITIZE_EMAIL);
        $current_password = $_POST['current_password'];

        try {
            if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception(translate('error_invalid_email_format', 'Invalid email format'));
            }

            // Fetch current email to check if it's the same
            /** @var \mysqli_stmt|false $stmt_current */
            $stmt_current = $auth_db->prepare("SELECT email FROM account WHERE id = ?");
            $stmt_current->bind_param('i', $_SESSION['user_id']);
            $stmt_current->execute();
            $result_current = $stmt_current->get_result();
            $current_email = $result_current->num_rows === 1 ? $result_current->fetch_assoc()['email'] : '';
            $stmt_current->close();

            // If new email is the same as current, allow update (no-op)
            if ($new_email !== $current_email) {
                // Check if email is used by another account
                /** @var \mysqli_stmt|false $stmt_check_email */
                $stmt_check_email = $auth_db->prepare("SELECT id FROM account WHERE email = ? AND id != ?");
                $stmt_check_email->bind_param('si', $new_email, $_SESSION['user_id']);
                $stmt_check_email->execute();
                $result = $stmt_check_email->get_result();
                if ($result->num_rows > 0) {
                    throw new Exception(translate('error_email_in_use', 'Email already in use by another account'));
                }
                $stmt_check_email->close();
            }

            // Verify current password
            /** @var \mysqli_stmt|false $stmt_verify */
            $stmt_verify = $auth_db->prepare("SELECT salt, verifier FROM account WHERE id = ?");
            $stmt_verify->bind_param('i', $_SESSION['user_id']);
            $stmt_verify->execute();
            $result = $stmt_verify->get_result();

            if ($result->num_rows !== 1) {
                throw new Exception(translate('error_account_not_found', 'Account not found'));
            }

            $row = $result->fetch_assoc();
            if (!SRP6::VerifyPassword($_SESSION['username'], $current_password, $row['salt'], $row['verifier'])) {
                throw new Exception(translate('error_incorrect_password', 'Incorrect current password'));
            }
            $stmt_verify->close();

            // Update email
            /** @var \mysqli_stmt|false $stmt_update */
            $stmt_update = $auth_db->prepare("UPDATE account SET email = ?, reg_mail = ? WHERE id = ?");
            $stmt_update->bind_param('ssi', $new_email, $new_email, $_SESSION['user_id']);
            if (!$stmt_update->execute()) {
                throw new Exception(translate('error_updating_email', 'Error updating email'));
            }
            $stmt_update->close();

            // Log action
            /** @var \mysqli_stmt|false $stmt_log */
            $stmt_log = $site_db->prepare("INSERT INTO website_activity_log (account_id, character_name, action, timestamp, details) VALUES (?, NULL, ?, UNIX_TIMESTAMP(), ?)");
            $action = translate('action_email_changed', 'Email Changed');
            $stmt_log->bind_param('iss', $_SESSION['user_id'], $action, $new_email);
            $stmt_log->execute();
            $stmt_log->close();

            $_SESSION['message'] = translate('message_email_updated', 'Email updated successfully!');
            header("Location: {$base_path}account");
            exit();
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header("Location: {$base_path}account");
            exit();
        }
    }

    // Handle password change
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        try {
            if ($new_password !== $confirm_password) {
                throw new Exception(translate('error_passwords_dont_match', 'New passwords don\'t match'));
            }
            if (strlen($new_password) < 6) {
                throw new Exception(translate('error_password_too_short', 'Password must be at least 6 characters'));
            }

            /** @var \mysqli_stmt|false $stmt */
            $stmt = $auth_db->prepare("SELECT salt, verifier FROM account WHERE id = ?");
            $stmt->bind_param('i', $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows !== 1) {
                throw new Exception(translate('error_account_not_found', 'Account not found'));
            }

            $row = $result->fetch_assoc();
            if (!SRP6::VerifyPassword($_SESSION['username'], $current_password, $row['salt'], $row['verifier'])) {
                throw new Exception(translate('error_incorrect_password', 'Current password is incorrect'));
            }
            $stmt->close();

            $new_salt = SRP6::GenerateSalt();
            $new_verifier = SRP6::CalculateVerifier($_SESSION['username'], $new_password, $new_salt);

            /** @var \mysqli_stmt|false $update */
            $update = $auth_db->prepare("UPDATE account SET salt = ?, verifier = ? WHERE id = ?");
            $update->bind_param('ssi', $new_salt, $new_verifier, $_SESSION['user_id']);
            if (!$update->execute()) {
                throw new Exception(translate('error_updating_password', 'Error updating password'));
            }
            $update->close();

            // Log action
            /** @var \mysqli_stmt|false $stmt_log */
            $stmt_log = $site_db->prepare("INSERT INTO website_activity_log (account_id, character_name, action, timestamp) VALUES (?, NULL, ?, UNIX_TIMESTAMP())");
            $action = translate('action_password_changed', 'Password Changed');
            $stmt_log->bind_param('is', $_SESSION['user_id'], $action);
            $stmt_log->execute();
            $stmt_log->close();

            $_SESSION['message'] = translate('message_password_changed', 'Password changed successfully!');
            header("Location: {$base_path}account");
            exit();
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header("Location: {$base_path}account");
            exit();
        }
    }

    // Handle character teleport
    if (isset($_POST['teleport_character'])) {
        $guid = filter_var($_POST['guid'], FILTER_VALIDATE_INT);
        $destination = filter_var($_POST['destination']);

        try {
            if (!$guid) {
                throw new Exception(translate('error_invalid_character_id', 'Invalid character ID'));
            }

            // Prevent rapid resubmissions
            if (isset($_SESSION['last_teleport_attempt']) && (time() - $_SESSION['last_teleport_attempt']) < 5) {
                throw new Exception(translate('error_rapid_submission', 'Please wait a few seconds before trying again'));
            }
            $_SESSION['last_teleport_attempt'] = time();

            // Check session-based cooldown
            if (isset($_SESSION['teleport_cooldowns'][$guid]) && ($_SESSION['teleport_cooldowns'][$guid] + 900) > time()) {
                $minutes = ceil(($_SESSION['teleport_cooldowns'][$guid] + 900 - time()) / 60);
                throw new Exception(sprintf(translate('error_teleport_cooldown', 'Teleport on cooldown. Please wait %s minute%s'), $minutes, $minutes > 1 ? 's' : ''));
            }

            // Fetch character name and online status
            /** @var \mysqli_stmt|false $stmt_check */
            $stmt_check = $char_db->prepare("SELECT online, name FROM characters WHERE guid = ? AND account = ?");
            $stmt_check->bind_param('ii', $guid, $_SESSION['user_id']);
            $stmt_check->execute();
            $result = $stmt_check->get_result();
            if ($result->num_rows !== 1) {
                throw new Exception(translate('error_character_not_found', 'Character not found'));
            }

            $char = $result->fetch_assoc();
            $character_name = $char['name'];
            if ($char['online'] == 1) {
                throw new Exception(translate('error_character_online', 'Character must be offline to teleport'));
            }
            $stmt_check->close();

            // Fetch teleport cooldown from database
            /** @var \mysqli_stmt|false $stmt_cooldown */
            $stmt_cooldown = $site_db->prepare("SELECT teleport_timestamp FROM character_teleport_log WHERE character_guid = ?");
            $stmt_cooldown->bind_param('i', $guid);
            $stmt_cooldown->execute();
            $result_cooldown = $stmt_cooldown->get_result();
            $last_teleport = $result_cooldown->num_rows > 0 ? $result_cooldown->fetch_assoc()['teleport_timestamp'] : 0;
            $stmt_cooldown->close();

            // Validate timestamp
            if (!is_numeric($last_teleport) || $last_teleport < 0) {
                $last_teleport = 0;
            }

            $current_time = time();
            $cooldown_duration = 900; // 15 minutes in seconds
            $cooldown_remaining = ($last_teleport + $cooldown_duration) - $current_time;
            if ($cooldown_remaining > 0) {
                $minutes = ceil($cooldown_remaining / 60);
                throw new Exception(sprintf(translate('error_teleport_cooldown', 'Teleport on cooldown. Please wait %s minute%s'), $minutes, $minutes > 1 ? 's' : ''));
            }

            $teleportData = [
                'shattrath' => ['map' => 530, 'x' => -1832.9, 'y' => 5370.1, 'z' => -12.4, 'o' => 2.0],
                'dalaran' => ['map' => 571, 'x' => 5804.2, 'y' => 624.8, 'z' => 647.8, 'o' => 3.1]
            ];

            if (!isset($teleportData[$destination])) {
                throw new Exception(translate('error_invalid_destination', 'Invalid teleport destination'));
            }

            $data = $teleportData[$destination];
            /** @var \mysqli_stmt|false $stmt_teleport */
            $stmt_teleport = $char_db->prepare("UPDATE characters SET map = ?, position_x = ?, position_y = ?, position_z = ?, orientation = ? WHERE guid = ?");
            $stmt_teleport->bind_param('iddddi', $data['map'], $data['x'], $data['y'], $data['z'], $data['o'], $guid);
            if (!$stmt_teleport->execute()) {
                throw new Exception(translate('error_teleporting_character', 'Error teleporting character'));
            }
            $stmt_teleport->close();

            // Log teleport in sahtout_site.character_teleport_log
            /** @var \mysqli_stmt|false $stmt_cooldown */
            $stmt_cooldown = $site_db->prepare("INSERT INTO character_teleport_log (account_id, character_guid, character_name, teleport_timestamp) VALUES (?, ?, ?, UNIX_TIMESTAMP()) ON DUPLICATE KEY UPDATE teleport_timestamp = UNIX_TIMESTAMP(), character_name = ?");
            $stmt_cooldown->bind_param('iiss', $_SESSION['user_id'], $guid, $character_name, $character_name);
            if (!$stmt_cooldown->execute()) {
                throw new Exception(translate('error_logging_teleport', 'Error logging teleport'));
            }
            $stmt_cooldown->close();

            // Update session cooldown
            $_SESSION['teleport_cooldowns'][$guid] = $current_time;

            // Log action in sahtout_site.website_activity_log
            /** @var \mysqli_stmt|false $stmt_log */
            $stmt_log = $site_db->prepare("INSERT INTO website_activity_log (account_id, character_name, action, timestamp, details) VALUES (?, ?, ?, UNIX_TIMESTAMP(), ?)");
            $action = translate('action_teleport', 'Teleport');
            $details = sprintf(translate('teleport_details', 'To %s'), ucfirst($destination));
            $stmt_log->bind_param('isss', $_SESSION['user_id'], $character_name, $action, $details);
            $stmt_log->execute();
            $stmt_log->close();

            $_SESSION['message'] = sprintf(translate('message_character_teleported', 'Character teleported to %s!'), ucfirst($destination));
            header("Location: {$base_path}account");
            exit();
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header("Location: {$base_path}account");
            exit();
        }
    }

    // Handle avatar change
    if (isset($_POST['change_avatar'])) {
        $avatar = $_POST['avatar'] !== '' ? $_POST['avatar'] : NULL;

        try {
            // Validate avatar
            /** @var \mysqli_stmt|false $stmt */
            $stmt = $site_db->prepare("SELECT filename FROM profile_avatars WHERE active = 1");
            $stmt->execute();
            $result = $stmt->get_result();
            $valid_avatars = [];
            while ($row = $result->fetch_assoc()) {
                $valid_avatars[] = $row['filename'];
            }
            $stmt->close();

            $valid_avatar = $avatar === NULL || in_array($avatar, $valid_avatars);
            if (!$valid_avatar) {
                throw new Exception(translate('error_invalid_avatar', 'Invalid avatar selected'));
            }

            /** @var \mysqli_stmt|false $stmt */
            $stmt = $site_db->prepare("UPDATE user_currencies SET avatar = ? WHERE account_id = ?");
            $stmt->bind_param('si', $avatar, $_SESSION['user_id']);
            if (!$stmt->execute()) {
                throw new Exception(translate('error_updating_avatar', 'Error updating avatar'));
            }
            $stmt->close();

            // Update session avatar for header.php
            $_SESSION['avatar'] = $avatar;

            // Log action
            /** @var \mysqli_stmt|false $stmt_log */
            $stmt_log = $site_db->prepare("INSERT INTO website_activity_log (account_id, character_name, action, timestamp, details) VALUES (?, NULL, ?, UNIX_TIMESTAMP(), ?)");
            $action = translate('action_avatar_changed', 'Avatar Changed');
            $details = $avatar !== NULL ? $avatar : translate('avatar_default', 'Default avatar');
            $stmt_log->bind_param('iss', $_SESSION['user_id'], $action, $details);
            $stmt_log->execute();
            $stmt_log->close();

            $_SESSION['message'] = translate('message_avatar_updated', 'Avatar updated successfully!');
            header("Location: {$base_path}account");
            exit();
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header("Location: {$base_path}account");
            exit();
        }
    }
}

// Now proceed with page rendering
$page_class = 'account';
include_once $project_root . 'includes/header.php';

// Database queries for page content
if ($auth_db->connect_error || $char_db->connect_error || $site_db->connect_error) {
    $error = translate('error_database_connection', 'Database connection failed');
} else {
    // Get account info
    /** @var \mysqli_stmt|false $stmt */
    $stmt = $auth_db->prepare("SELECT id, username, email, joindate, last_login, locked, online, expansion FROM account WHERE id = ?");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $accountInfo = $result->fetch_assoc();
    }
    $stmt->close();

    // Check ban status
    /** @var \mysqli_stmt|false $stmt */
    $stmt = $auth_db->prepare("SELECT bandate, unbandate, banreason FROM account_banned WHERE id = ? AND active = 1");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $banInfo = $result->fetch_assoc();
    }
    $stmt->close();

    // Get characters
    if (!empty($accountInfo)) {
        /** @var \mysqli_stmt|false $stmt */
        $stmt = $char_db->prepare("SELECT guid, name, race, class, gender, level, money, online FROM characters WHERE account = ?");
        $stmt->bind_param('i', $_SESSION['user_id']);
        $stmt->execute();
        $characters = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

    // Get teleport cooldowns
    if (!empty($characters)) {
        $guids = array_column($characters, 'guid');
        $placeholders = implode(',', array_fill(0, count($guids), '?'));
        /** @var \mysqli_stmt|false $stmt */
        $stmt = $site_db->prepare("SELECT character_guid, teleport_timestamp FROM character_teleport_log WHERE character_guid IN ($placeholders)");
        $stmt->bind_param(str_repeat('i', count($guids)), ...$guids);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $teleport_cooldowns[$row['character_guid']] = $row['teleport_timestamp'];
        }
        $stmt->close();
    }

    // Get activity log
    /** @var \mysqli_stmt|false $stmt */
    $stmt = $site_db->prepare("SELECT action, timestamp, details, character_name FROM website_activity_log WHERE account_id = ? ORDER BY timestamp DESC LIMIT 10");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $activityLog = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Get Points, Tokens, and Avatar
    /** @var \mysqli_stmt|false $stmt */
    $stmt = $site_db->prepare("SELECT points, tokens, avatar FROM user_currencies WHERE account_id = ?");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $currencies = $result->fetch_assoc();
    }
    $stmt->close();

    // Pre-load avatars for the security tab to avoid AJAX delay
    $available_avatars = [];
    if (!$site_db->connect_error) {
        $stmt = $site_db->prepare("SELECT filename, display_name FROM profile_avatars WHERE active = 1");
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $available_avatars[] = [
                'filename' => $row['filename'],
                'display_name' => translate('avatar_' . str_replace('.', '_', $row['filename']), $row['filename'])
            ];
        }
        $stmt->close();
    }

    // Vote System Logic
    $voteSites = [];
    try {
        $stmt = $site_db->prepare("SELECT id, callback_file_name, site_name, siteid, url_format, button_image_url, cooldown_hours, reward_points, uses_callback FROM vote_sites");
        $stmt->execute();
        $voteSites = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $unclaimed_rewards = [];
        $last_votes = [];
        $expiration = time() - (24 * 3600);

        $stmt = $site_db->prepare("SELECT site_id, COUNT(*) as c FROM vote_log WHERE user_id = ? AND reward_status = 0 AND vote_timestamp >= ? GROUP BY site_id");
        $stmt->bind_param("ii", $_SESSION['user_id'], $expiration);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc())
            $unclaimed_rewards[$r['site_id']] = $r['c'] > 0;
        $stmt->close();

        $stmt = $site_db->prepare("SELECT site_id, MAX(vote_timestamp) as last FROM (SELECT site_id, vote_timestamp FROM vote_log WHERE user_id = ? UNION SELECT site_id, vote_timestamp FROM vote_log_history WHERE user_id = ?) combined GROUP BY site_id");
        $stmt->bind_param("ii", $_SESSION['user_id'], $_SESSION['user_id']);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc())
            $last_votes[$r['site_id']] = (int) $r['last'];
        $stmt->close();

        foreach ($voteSites as &$site) {
            $site['has_unclaimed_rewards'] = $unclaimed_rewards[$site['id']] ?? false;
            $site['is_on_cooldown'] = false;
            $site['remaining_cooldown'] = 0;
            if (isset($last_votes[$site['id']])) {
                $diff = time() - $last_votes[$site['id']];
                $cd = $site['cooldown_hours'] * 3600;
                if ($diff < $cd) {
                    $site['is_on_cooldown'] = true;
                    $site['remaining_cooldown'] = $cd - $diff;
                }
            }
        }
        unset($site);
    } catch (Exception $e) { /* Ignore errors */
    }
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$auth_db->close();
$char_db->close();
$site_db->close();

// Helper functions
function getAccountStatus($locked, $banInfo)
{
    if (!empty($banInfo)) {
        $reason = htmlspecialchars($banInfo['banreason'] ?? translate('ban_no_reason', 'No reason provided'));
        $unbanDate = $banInfo['unbandate'] ? date('Y-m-d H:i:s', $banInfo['unbandate']) : translate('ban_permanent', 'Permanent');
        return sprintf('<span class="text-danger">%s (Reason: %s, Until: %s)</span>', translate('status_banned', 'Banned'), $reason, $unbanDate);
    }
    switch ($locked) {
        case 1:
            return sprintf('<span class="text-danger">%s</span>', translate('status_banned', 'Banned'));
        case 2:
            return sprintf('<span class="text-info">%s</span>', translate('status_frozen', 'Frozen'));
        default:
            return sprintf('<span class="text-success">%s</span>', translate('status_active', 'Active'));
    }
}

function getGMStatus($gmlevel, $role)
{
    global $base_path;
    $icon = ($gmlevel > 0 || $role !== 'player') ? 'gm_icon.gif' : 'player_icon.jpg';
    $color = ($gmlevel > 0 || $role !== 'player') ? '#f0a500' : '#aaa';

    if ($gmlevel > 0) {
        $suffix = '';
        if ($role === 'admin') {
            $suffix = translate('gm_suffix_admin', ' (S)');
        } elseif ($role === 'moderator') {
            $suffix = ($gmlevel == 1) ? translate('gm_suffix_moderator', ' (M)') : translate('gm_suffix_administrator', ' (A)');
        }
        $rank = sprintf(translate('gm_rank_gm', 'Game Master Level %s%s'), $gmlevel, $suffix);
    } elseif ($role === 'admin') {
        $rank = translate('gm_rank_admin', 'Admin');
    } elseif ($role === 'moderator') {
        $rank = translate('gm_rank_moderator', 'Moderator');
    } else {
        $rank = translate('gm_rank_player', 'Player');
    }

    return sprintf('<img src="%simg/accountimg/%s" alt="%s" class="account-icon"> <span style="color: %s">%s</span>', $base_path, $icon, translate('status_icon', 'Status Icon'), $color, $rank);
}

function getOnlineStatus($online)
{
    return $online ? sprintf('<span class="text-success">%s</span>', translate('status_online', 'Online')) : sprintf('<span class="text-danger">%s</span>', translate('status_offline', 'Offline'));
}

function getRaceIcon($race, $gender)
{
    global $base_path;
    $races = [
        1 => 'human',
        2 => 'orc',
        3 => 'dwarf',
        4 => 'nightelf',
        5 => 'undead',
        6 => 'tauren',
        7 => 'gnome',
        8 => 'troll',
        10 => 'bloodelf',
        11 => 'draenei'
    ];
    $gender_folder = ($gender == 1) ? 'female' : 'male';
    $race_name = $races[$race] ?? 'default';
    $image = $race_name . '.png';
    return sprintf('<img src="%simg/accountimg/race/%s/%s" alt="%s" class="account-icon">', $base_path, $gender_folder, $image, translate('race_icon', 'Race Icon'));
}

function getClassIcon($class)
{
    global $base_path;
    $icons = [
        1 => 'warrior.webp',
        2 => 'paladin.webp',
        3 => 'hunter.webp',
        4 => 'rogue.webp',
        5 => 'priest.webp',
        6 => 'deathknight.webp',
        7 => 'shaman.webp',
        8 => 'mage.webp',
        9 => 'warlock.webp',
        11 => 'druid.webp'
    ];
    return sprintf('<img src="%simg/accountimg/class/%s" alt="%s" class="account-icon">', $base_path, ($icons[$class] ?? 'default.jpg'), translate('class_icon', 'Class Icon'));
}

function getFactionIcon($race)
{
    global $base_path;
    $allianceRaces = [1, 3, 4, 7, 11]; // Human, Dwarf, Night Elf, Gnome, Draenei
    $faction = in_array($race, $allianceRaces) ? 'alliance.png' : 'horde.png';
    return sprintf('<img src="%simg/accountimg/faction/%s" alt="%s" class="account-icon">', $base_path, $faction, translate('faction_icon', 'Faction Icon'));
}

// Helper function to get avatar display name translation
function getAvatarDisplayName($filename)
{
    return translate('avatar_' . str_replace('.', '_', $filename), $filename);
}

// ---------------------------------------------------------
// Page Setup for Header
// ---------------------------------------------------------
$page_title = $site_title_name . " " . sprintf(translate('page_title', 'Account - %s'), htmlspecialchars($accountInfo['username'] ?? ''));

// We need Bootstrap 5 for the account panel, but header.php might not include it.
// We can inject it or just include it here. Since header.php outputs <head>...</head> and <body>...
// we actually can't easily inject into <head> without refactoring header.php to accept $extra_head_content.
// However, placing <link> in the <body> is supported by browsers (though not ideal spec-wise), or we can hope header.php allows injection.
// Looking at header.php, it doesn't seem to have a variable for extra head content.
// But wait, account.php included header.php inside its body previously.
// NOW, we will include header.php FIRST.
require_once $project_root . 'includes/header.php';
?>

<!-- Bootstrap 5 CSS (Loaded here to ensure it's available) -->
<link href="https://lib.baomitu.com/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">

<style>
    :root {
        --bg-account: url('<?php echo $base_path; ?>img/backgrounds/bg-account.jpg');
        --hover-wow-gif: url('<?php echo $base_path; ?>img/hover_wow.gif');
    }

    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.85);
        z-index: 9999;
        display: none;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(5px);
    }

    .modal-overlay.show {
        display: flex;
    }

    .modal-content {
        background: #1a1a1a;
        border: 2px solid #ffd700;
        padding: 30px;
        text-align: center;
        max-width: 500px;
        width: 90%;
        color: #fff;
        border-radius: 10px;
        box-shadow: 0 0 20px rgba(255, 215, 0, 0.3);
    }

    .vote-site-image img {
        max-width: 100%;
        height: 50px;
        object-fit: contain;
    }

    .cooldown-timer {
        font-size: 0.9rem;
        color: #f0a500;
        font-weight: bold;
        margin-top: 10px;
    }
</style>

    <main>
        <div class="account-container">
            <h1 class="account-title mb-4"><?php echo translate('dashboard_title', 'Account Dashboard'); ?></h1>

            <?php if ($message): ?>
                <div class="alert alert-success account-message"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger account-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (!empty($debug_errors) && ($role === 'admin' || $gmlevel > 0)): ?>
                <div class="alert alert-warning account-message">
                    <strong><?php echo translate('debug_warnings', 'Debug Warnings'); ?>:</strong><br>
                    <?php echo htmlspecialchars(implode('<br>', array_unique($debug_errors))); ?>
                </div>
            <?php endif; ?>

            <ul class="nav nav-tabs account-tabs mb-4 justify-content-center" id="accountTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview"
                        type="button" role="tab"><?php echo translate('tab_overview', 'Overview'); ?></button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="characters-tab" data-bs-toggle="tab" data-bs-target="#characters"
                        type="button" role="tab"><?php echo translate('tab_characters', 'Characters'); ?></button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="activity-tab" data-bs-toggle="tab" data-bs-target="#activity"
                        type="button" role="tab"><?php echo translate('tab_activity', 'Activity'); ?></button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="vote-tab" data-bs-toggle="tab" data-bs-target="#vote" type="button"
                        role="tab"><?php echo translate('tab_vote', 'Vote'); ?></button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security"
                        type="button" role="tab"><?php echo translate('tab_security', 'Security'); ?></button>
                </li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane show active" id="overview" role="tabpanel">
                    <div class="mb-4">
                        <h2 class="h3 text-warning">
                            <?php echo translate('section_account_info', 'Account Information'); ?>
                        </h2>
                        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                            <div class="col">
                                <div class="card account-card h-100">
                                    <div class="card-body text-center">
                                        <h3 class="card-title"><?php echo translate('card_basic_info', 'Basic Info'); ?>
                                        </h3>
                                        <?php
                                        $avatar_display = !empty($currencies['avatar']) ? $currencies['avatar'] : 'user.jpg';
                                        ?>
                                        <img src="<?php echo $base_path; ?>img/accountimg/profile_pics/<?php echo htmlspecialchars($avatar_display); ?>"
                                            alt="<?php echo translate('avatar_alt', 'Avatar'); ?>"
                                            class="account-profile-pic mb-3">
                                        <p><strong><?php echo translate('label_username', 'Username'); ?>:</strong>
                                            <?php echo htmlspecialchars($accountInfo['username'] ?? 'N/A'); ?></p>
                                        <p><strong><?php echo translate('label_account_id', 'Account ID'); ?>:</strong>
                                            <?php echo $accountInfo['id'] ?? 'N/A'; ?></p>
                                        <p><strong><?php echo translate('label_status', 'Status'); ?>:</strong>
                                            <?php echo getAccountStatus($accountInfo['locked'] ?? 0, $banInfo); ?></p>
                                        <p><strong><?php echo translate('label_rank', 'Rank'); ?>:</strong>
                                            <?php echo getGMStatus($gmlevel, $role); ?></p>
                                        <p><strong><?php echo translate('label_online', 'Online'); ?>:</strong>
                                            <?php echo getOnlineStatus($accountInfo['online'] ?? 0); ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col">
                                <div class="card account-card h-100">
                                    <div class="card-body text-center">
                                        <h3 class="card-title"><?php echo translate('card_contact', 'Contact'); ?></h3>
                                        <p><strong><?php echo translate('label_email', 'Email'); ?>:</strong>
                                            <?php echo htmlspecialchars($accountInfo['email'] ?? translate('email_not_set', 'Not set')); ?>
                                        </p>
                                        <p><strong
                                                class="text-warning"><?php echo translate('label_expansion', 'Expansion'); ?>:</strong>
                                            <?php echo translate('expansion_' . ($accountInfo['expansion'] ?? 2), ($accountInfo['expansion'] ?? 2) == 2 ? 'Wrath of the Lich King' : ($accountInfo['expansion'] == 1 ? 'The Burning Crusade' : 'Classic')); ?>
                                        </p>
                                        <?php if ($role === 'admin' || $role === 'moderator' || $gmlevel > 0): ?>
                                            <a href="<?php echo $base_path; ?>admin/dashboard"
                                                class="btn-account"><?php echo translate('button_admin_panel', 'Admin Panel'); ?></a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col">
                                <div class="card account-card h-100">
                                    <div class="card-body text-center">
                                        <h3 class="card-title"><?php echo translate('card_activity', 'Activity'); ?>
                                        </h3>
                                        <p><strong><?php echo translate('label_join_date', 'Join Date'); ?>:</strong>
                                            <?php echo $accountInfo['joindate'] ?? 'N/A'; ?></p>
                                        <p><strong><?php echo translate('label_last_login', 'Last Login'); ?>:</strong>
                                            <?php echo $accountInfo['last_login'] ?? translate('never', 'Never'); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div>
                        <h2 class="h3 text-warning"><?php echo translate('section_quick_stats', 'Quick Stats'); ?></h2>
                        <div class="row row-cols-1 row-cols-md-2 g-4">
                            <div class="col">
                                <div class="card account-card h-100">
                                    <div class="card-body text-center">
                                        <h3 class="card-title"><?php echo translate('card_characters', 'Characters'); ?>
                                        </h3>
                                        <p><strong><?php echo translate('label_total_characters', 'Total'); ?>:</strong>
                                            <?php echo count($characters); ?></p>
                                        <p><strong><?php echo translate('label_highest_level', 'Highest Level'); ?>:</strong>
                                            <?php
                                            $maxLevel = 0;
                                            foreach ($characters as $char) {
                                                if ($char['level'] > $maxLevel)
                                                    $maxLevel = $char['level'];
                                            }
                                            echo $maxLevel;
                                            ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col">
                                <div class="card account-card h-100">
                                    <div class="card-body text-center">
                                        <h3 class="card-title"><?php echo translate('card_wealth', 'Wealth'); ?></h3>
                                        <p><strong><?php echo translate('label_total_gold', 'Total Gold'); ?>:</strong>
                                            <?php
                                            $totalGold = 0;
                                            foreach ($characters as $char) {
                                                $totalGold += $char['money'];
                                            }
                                            echo sprintf('<span class="account-gold">%.2fg</span>', number_format($totalGold / 10000, 2));
                                            ?>
                                            <img src="<?php echo $base_path; ?>img/accountimg/gold_coin.png"
                                                alt="<?php echo translate('gold_icon', 'Gold Icon'); ?>"
                                                class="account-icon">
                                        </p>
                                        <p><strong><?php echo translate('label_points', 'Points'); ?>:</strong>
                                            <?php echo $currencies['points']; ?> P</p>
                                        <p><strong><?php echo translate('label_tokens', 'Tokens'); ?>:</strong>
                                            <?php echo $currencies['tokens']; ?> T</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane" id="characters" role="tabpanel">
                    <h2 class="h3 text-warning mb-4">
                        <?php echo translate('section_your_characters', 'Your Characters'); ?>
                    </h2>
                    <?php if (!empty($characters)): ?>
                        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                            <?php foreach ($characters as $char): ?>
                                <div class="col">
                                    <div class="card account-card h-100">
                                        <div class="card-body text-center">
                                            <div class="d-flex justify-content-center align-items-center gap-2 mb-3 flex-wrap">
                                                <span><?php echo getFactionIcon($char['race']); ?></span>
                                                <span><?php echo getRaceIcon($char['race'], $char['gender']); ?></span>
                                                <span
                                                    class="fw-bold text-warning"><?php echo htmlspecialchars($char['name']); ?></span>
                                            </div>
                                            <p><?php echo getClassIcon($char['class']); ?>
                                                <?php echo translate('label_level', 'Level'); ?>         <?php echo $char['level']; ?>
                                            </p>
                                            <p><?php echo translate('label_gold', 'Gold'); ?>: <span
                                                    class="account-gold"><?php echo number_format($char['money'] / 10000, 2); ?>g</span>
                                            </p>
                                            <p><?php echo translate('label_status', 'Status'); ?>:
                                                <?php echo getOnlineStatus($char['online']); ?>
                                            </p>
                                            <?php
                                            $cooldown_remaining = max(
                                                isset($teleport_cooldowns[$char['guid']]) ? ($teleport_cooldowns[$char['guid']] + 900 - time()) : 0,
                                                isset($_SESSION['teleport_cooldowns'][$char['guid']]) ? ($_SESSION['teleport_cooldowns'][$char['guid']] + 900 - time()) : 0
                                            );
                                            $is_on_cooldown = $cooldown_remaining > 0;
                                            $minutes = ceil($cooldown_remaining / 60);
                                            ?>
                                            <form method="post" class="mt-3"
                                                onsubmit="return confirm('<?php echo translate('confirm_teleport', 'Teleport this character?'); ?>');">
                                                <input type="hidden" name="csrf_token"
                                                    value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                <input type="hidden" name="guid" value="<?php echo $char['guid']; ?>">
                                                <div class="mb-3 form-group">
                                                    <label class="form-label"
                                                        for="destination-<?php echo $char['guid']; ?>"><?php echo translate('label_select_city', 'Select a city'); ?></label>
                                                    <select class="form-select" id="destination-<?php echo $char['guid']; ?>"
                                                        name="destination" required>
                                                        <option style="color: #000;" value="" selected>
                                                            <?php echo translate('select_city_placeholder', 'Select a city'); ?>
                                                        </option>
                                                        <option style="color: #000;" value="shattrath">
                                                            <?php echo translate('city_shattrath', 'Shattrath'); ?>
                                                        </option>
                                                        <option style="color: #000;" value="dalaran">
                                                            <?php echo translate('city_dalaran', 'Dalaran'); ?>
                                                        </option>
                                                    </select>
                                                </div>
                                                <button class="btn-account" type="submit" name="teleport_character" <?php echo $is_on_cooldown ? 'disabled' : ''; ?>><?php echo translate('button_teleport', 'Teleport'); ?></button>
                                                <?php if ($is_on_cooldown): ?>
                                                    <p class="mt-2 teleport-cooldown"
                                                        data-cooldown="<?php echo $cooldown_remaining; ?>">
                                                        <?php echo sprintf(translate('teleport_cooldown', 'Teleport Cooldown: %s minute%s'), $minutes, $minutes > 1 ? 's' : ''); ?>
                                                    </p>
                                                <?php endif; ?>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-center"><?php echo translate('no_characters', 'You have no characters yet.'); ?></p>
                    <?php endif; ?>
                </div>

                <div class="tab-pane" id="activity" role="tabpanel">
                    <h2 class="h3 text-warning mb-4">
                        <?php echo translate('section_account_activity', 'Account Activity'); ?>
                    </h2>
                    <?php if (!empty($activityLog)): ?>
                        <div class="table-responsive">
                            <table class="table account-table">
                                <thead>
                                    <tr>
                                        <th><?php echo translate('table_action', 'Action'); ?></th>
                                        <th><?php echo translate('table_character', 'Character'); ?></th>
                                        <th><?php echo translate('table_timestamp', 'Timestamp'); ?></th>
                                        <th><?php echo translate('table_details', 'Details'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($activityLog as $log): ?>
                                        <tr>
                                            <td data-label="<?php echo translate('table_action', 'Action'); ?>">
                                                <?php echo htmlspecialchars($log['action']); ?>
                                            </td>
                                            <td data-label="<?php echo translate('table_character', 'Character'); ?>">
                                                <?php echo htmlspecialchars($log['character_name'] ?? translate('none', 'N/A')); ?>
                                            </td>
                                            <td data-label="<?php echo translate('table_timestamp', 'Timestamp'); ?>">
                                                <?php echo date('Y-m-d H:i:s', $log['timestamp']); ?>
                                            </td>
                                            <td data-label="<?php echo translate('table_details', 'Details'); ?>">
                                                <?php echo htmlspecialchars($log['details'] ?? translate('none', 'None')); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-center"><?php echo translate('no_activity', 'No recent activity.'); ?></p>
                    <?php endif; ?>
                </div>

                <div class="tab-pane" id="vote" role="tabpanel">
                    <h2 class="h3 text-warning mb-4"><?php echo translate('section_vote', 'Vote for Rewards'); ?></h2>
                    <div class="alert alert-info message-box" style="display:none;" id="voteMessage"></div>

                    <?php if (empty($voteSites)): ?>
                        <p class="text-center"><?php echo translate('vote_no_sites', 'No vote sites available.'); ?></p>
                    <?php else: ?>
                        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                            <?php foreach ($voteSites as $site): ?>
                                <div class="col">
                                    <div class="card account-card h-100 vote-site">
                                        <div class="card-body text-center d-flex flex-column">
                                            <h3 class="card-title text-warning" style="font-size:1.1rem;">
                                                <?php echo htmlspecialchars($site['site_name']); ?>
                                            </h3>
                                            <div
                                                class="flex-grow-1 d-flex flex-column align-items-center justify-content-center my-2">
                                                <div class="vote-site-image mb-2">
                                                    <img src="<?php echo htmlspecialchars($site['button_image_url'] ?? $base_path . 'img/default.png'); ?>"
                                                        alt="<?php echo htmlspecialchars($site['site_name']); ?>">
                                                </div>
                                                <p class="mb-1 text-white"><?php echo $site['reward_points']; ?> VP</p>
                                                <p class="mb-2 text-muted small"><?php echo $site['cooldown_hours']; ?>h
                                                    Cooldown</p>

                                                <?php
                                                $vote_url = $site['url_format'] ? htmlspecialchars($site['url_format'], ENT_QUOTES, 'UTF-8') : '#';
                                                $username_clean = $_SESSION['username'];
                                                if ($username_clean && $site['url_format']) {
                                                    $vote_url = str_replace(
                                                        ['{siteid}', '{userid}', '{username}'],
                                                        [urlencode($site['siteid']), urlencode($_SESSION['user_id']), urlencode($username_clean)],
                                                        htmlspecialchars($site['url_format'], ENT_QUOTES, 'UTF-8')
                                                    );
                                                } elseif ($site['uses_callback'] && $username_clean) {
                                                    $vote_url .= (parse_url($vote_url, PHP_URL_QUERY) ? '&' : '?') . 'vote=1&pingUsername=' . urlencode($username_clean);
                                                }
                                                ?>

                                                <div class="mt-auto d-flex justify-content-center gap-2 flex-wrap mb-2">
                                                    <a href="<?php echo $vote_url; ?>" class="btn-account vote-btn"
                                                        target="_blank"
                                                        data-site-name="<?php echo htmlspecialchars($site['site_name']); ?>"
                                                        <?php echo $site['is_on_cooldown'] ? 'disabled' : ''; ?>
                                                        style="min-width: 80px;">
                                                        <?php echo translate('vote_button', 'Vote'); ?>
                                                    </a>
                                                    <?php if ($_SESSION['user_id'] > 0): ?>
                                                        <button class="btn-account claim-btn"
                                                            style="filter: hue-rotate(90deg); min-width: 80px;"
                                                            onclick="claimRewards(<?php echo (int) $_SESSION['user_id']; ?>, '<?php echo htmlspecialchars($site['callback_file_name']); ?>', '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>')"
                                                            <?php echo $site['has_unclaimed_rewards'] ? '' : 'disabled'; ?>>
                                                            <?php echo translate('claim_button', 'Claim'); ?>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>

                                                <p class="cooldown-timer" data-site-id="<?php echo (int) $site['id']; ?>"
                                                    data-remaining-seconds="<?php echo (int) $site['remaining_cooldown']; ?>"
                                                    data-cooldown-hours="<?php echo (int) $site['cooldown_hours']; ?>">
                                                    <?php echo $site['is_on_cooldown'] ? 'Cooldown Active' : 'Ready to Vote'; ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <div class="mt-5">
                        <h3 class="h4 text-warning mb-4">
                            <?php echo translate('vote_rewards_title', 'Voting Rewards'); ?>
                        </h3>
                        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4">
                            <div class="col">
                                <div class="card account-card h-100">
                                    <div class="card-body text-center">
                                        <div class="mb-3 text-warning" style="font-size: 2rem;"><i
                                                class="fas fa-coins"></i></div>
                                        <h4 class="h5 text-white"><?php echo translate('vote_reward_gold', 'Gold'); ?>
                                        </h4>
                                        <p class="small text-muted">
                                            <?php echo translate('vote_reward_gold_desc', 'Receive up to 40 gold per vote to boost your in-game wealth.'); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col">
                                <div class="card account-card h-100">
                                    <div class="card-body text-center">
                                        <div class="mb-3 text-warning" style="font-size: 2rem;"><i
                                                class="fas fa-hat-wizard"></i></div>
                                        <h4 class="h5 text-white">
                                            <?php echo translate('vote_reward_enchants', 'Enchants'); ?>
                                        </h4>
                                        <p class="small text-muted">
                                            <?php echo translate('vote_reward_enchants_desc', 'Unlock powerful weapon and armor enchants for your characters.'); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col">
                                <div class="card account-card h-100">
                                    <div class="card-body text-center">
                                        <div class="mb-3 text-warning" style="font-size: 2rem;"><i
                                                class="fas fa-dragon"></i></div>
                                        <h4 class="h5 text-white">
                                            <?php echo translate('vote_reward_mounts', 'Mounts'); ?>
                                        </h4>
                                        <p class="small text-muted">
                                            <?php echo translate('vote_reward_mounts_desc', 'Gain access to exclusive mounts only available through voting.'); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col">
                                <div class="card account-card h-100">
                                    <div class="card-body text-center">
                                        <div class="mb-3 text-warning" style="font-size: 2rem;"><i
                                                class="fas fa-gem"></i></div>
                                        <h4 class="h5 text-white">
                                            <?php echo translate('vote_reward_vip_points', 'VIP Points'); ?>
                                        </h4>
                                        <p class="small text-muted">
                                            <?php echo translate('vote_reward_vip_points_desc', 'Earn points to redeem for special items and perks.'); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-overlay">
                        <div class="modal-content">
                            <h2 class="text-warning mb-3">Voting...</h2>
                            <p class="mb-4">Redirecting to <span class="site-name text-warning fw-bold"></span>.</p>
                            <button class="btn-account" onclick="closeModal()">Close</button>
                        </div>
                    </div>
                </div>

                <div class="tab-pane" id="security" role="tabpanel">
                    <div class="mb-4">
                        <h3 class="h4 text-warning"><?php echo translate('section_change_email', 'Change Email'); ?>
                        </h3>
                        <form method="post" class="row g-3 justify-content-center">
                            <input type="hidden" name="csrf_token"
                                value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <div class="col-12 col-md-6 form-group">
                                <label class="form-label"
                                    for="current-password-email"><?php echo translate('label_current_password', 'Current Password'); ?></label>
                                <input class="form-control" type="password" id="current-password-email"
                                    name="current_password" required
                                    placeholder="<?php echo translate('placeholder_current_password', 'Enter current password'); ?>">
                            </div>
                            <div class="col-12 col-md-6 form-group">
                                <label class="form-label"
                                    for="new-email"><?php echo translate('label_new_email', 'New Email'); ?></label>
                                <input class="form-control" type="email" id="new-email" name="new_email" required
                                    minlength="3" maxlength="36"
                                    value="<?php echo htmlspecialchars($accountInfo['email'] ?? ''); ?>"
                                    placeholder="<?php echo translate('placeholder_new_email', 'Enter new email'); ?>">
                            </div>
                            <div class="col-12 text-center">
                                <button class="btn-account" type="submit"
                                    name="change_email"><?php echo translate('button_update_email', 'Update Email'); ?></button>
                            </div>
                        </form>
                    </div>

                    <div class="mb-4">
                        <h3 class="h4 text-warning">
                            <?php echo translate('section_change_password', 'Change Password'); ?>
                        </h3>
                        <form method="post" class="row g-3 justify-content-center">
                            <input type="hidden" name="csrf_token"
                                value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <div class="col-12 col-md-6 form-group">
                                <label class="form-label"
                                    for="current-password"><?php echo translate('label_current_password', 'Current Password'); ?></label>
                                <input class="form-control" type="password" id="current-password"
                                    name="current_password" required
                                    placeholder="<?php echo translate('placeholder_current_password', 'Enter current password'); ?>">
                            </div>
                            <div class="col-12 col-md-6 form-group">
                                <label class="form-label"
                                    for="new-password"><?php echo translate('label_new_password', 'New Password'); ?></label>
                                <input class="form-control" type="password" id="new-password" name="new_password"
                                    required minlength="6" maxlength="32"
                                    placeholder="<?php echo translate('placeholder_new_password', 'Enter new password'); ?>">
                            </div>
                            <div class="col-12 col-md-6 form-group">
                                <label class="form-label"
                                    for="confirm-password"><?php echo translate('label_confirm_password', 'Confirm New Password'); ?></label>
                                <input class="form-control" type="password" id="confirm-password"
                                    name="confirm_password" required minlength="6" maxlength="32"
                                    placeholder="<?php echo translate('placeholder_confirm_password', 'Confirm new password'); ?>">
                            </div>
                            <div class="col-12 text-center">
                                <button class="btn-account" type="submit"
                                    name="change_password"><?php echo translate('button_change_password', 'Change Password'); ?></button>
                            </div>
                        </form>
                    </div>

                    <div class="mb-4">
                        <h3 class="h4 text-warning"><?php echo translate('section_change_avatar', 'Change Avatar'); ?>
                        </h3>
                        <form method="post" class="row g-3 justify-content-center">
                            <input type="hidden" name="csrf_token"
                                value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <div class="col-12">
                                <label
                                    class="form-label"><?php echo translate('label_select_avatar', 'Select Avatar'); ?></label>

                                <div id="avatar-loading-spinner" class="text-center py-4" style="display:none;">
                                    <div class="spinner-border text-warning" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="text-muted mt-2">Loading avatars...</p>
                                </div>

                                <div class="row row-cols-2 row-cols-md-4 row-cols-lg-6 g-2 account-gallery"
                                    id="avatar-grid-container"
                                    data-current="<?php echo htmlspecialchars($currencies['avatar'] ?? ''); ?>">
                                    <!-- Avatars will be injected here via JS -->
                                </div>

                                <input type="hidden" name="avatar" id="avatar"
                                    value="<?php echo htmlspecialchars($currencies['avatar'] ?? ''); ?>">
                            </div>
                            <div class="col-12 text-center">
                                <button class="btn-account" type="submit"
                                    name="change_avatar"><?php echo translate('button_update_avatar', 'Update Avatar'); ?></button>
                            </div>
                        </form>
                    </div>

                    <div>
                        <h3 class="h4 text-warning">
                            <?php echo translate('section_account_actions', 'Account Actions'); ?>
                        </h3>
                        <p class="text-center">
                            <a href="<?php echo $base_path; ?>logout"
                                class="text-warning"><?php echo translate('action_logout', 'Logout'); ?></a> |
                            <a href="#"
                                class="text-danger"><?php echo translate('action_request_deletion', 'Request Account Deletion'); ?></a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <?php include_once $project_root . 'includes/footer.php'; ?>
    <!-- Bootstrap 5 JS -->
    <script src="https://lib.baomitu.com/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectAvatar(filename) {
            document.getElementById('avatar').value = filename;
            document.querySelectorAll('.account-gallery img').forEach(img => {
                img.classList.remove('selected');
            });
            const selectedImg = document.querySelector(`.account-gallery img[onclick="selectAvatar('${filename}')"]`);
            if (selectedImg) {
                selectedImg.classList.add('selected');
            }
        }

        // Client-side countdown timer for teleport cooldown
        document.querySelectorAll('.teleport-cooldown').forEach(function (element) {
            let seconds = parseInt(element.dataset.cooldown);
            if (seconds > 0) {
                let timer = setInterval(function () {
                    seconds--;
                    let minutes = Math.ceil(seconds / 60);
                    let plural = minutes > 1 ? 's' : '';
                    element.textContent = '<?php echo translate('teleport_cooldown', 'Teleport Cooldown: %s minute%s'); ?>'.replace('%s', minutes).replace('%s', plural);
                    if (seconds <= 0) {
                        clearInterval(timer);
                        element.remove();
                        element.closest('form').querySelector('button').disabled = false;
                    }
                }, 1000);
            }
        });

        // Vote JS
        const basePath = '<?php echo addslashes($base_path); ?>';

        window.closeModal = function () {
            document.querySelector('.modal-overlay').classList.remove('show');
        }

        // Vote Buttons
        document.querySelectorAll('.vote-btn').forEach(button => {
            button.addEventListener('click', function (e) {
                if (this.hasAttribute('disabled')) {
                    e.preventDefault();
                    showMessage('Cannot vote while on cooldown.', 'danger');
                    return;
                }
                const overlay = document.querySelector('.modal-overlay');
                const siteName = this.getAttribute('data-site-name');
                const url = this.getAttribute('href');

                overlay.querySelector('.site-name').textContent = siteName;
                overlay.classList.add('show');

                // Allow default link behavior to open new tab
                setTimeout(() => {
                    closeModal();
                }, 3000);
            });
        });

        // Cooldown Timers
        document.querySelectorAll('.cooldown-timer').forEach(timer => {
            let remaining = parseInt(timer.getAttribute('data-remaining-seconds'));
            if (remaining > 0) {
                const update = () => {
                    if (remaining <= 0) {
                        timer.textContent = 'Ready to Vote';
                        const btn = timer.closest('.vote-site').querySelector('.vote-btn');
                        if (btn) btn.removeAttribute('disabled');
                        return;
                    }
                    const h = Math.floor(remaining / 3600);
                    const m = Math.floor((remaining % 3600) / 60);
                    const s = remaining % 60;
                    timer.textContent = `${h}h ${m}m ${s}s`;
                    remaining--;
                    setTimeout(update, 1000);
                };
                update();
            }
        });

        // Claim Rewards
        window.claimRewards = function (userId, siteId, csrfToken) {
            fetch(`${basePath}pages/pingback/claim.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `user_id=${encodeURIComponent(userId)}&site_id=${encodeURIComponent(siteId)}&csrf_token=${encodeURIComponent(csrfToken)}`
            })
                .then(r => r.json().catch(() => ({ status: r.ok ? 'success' : 'error', message: 'Unknown response' })))
                .then(data => {
                    showMessage(data.message, data.status === 'success' ? 'success' : 'danger');
                    if (data.status === 'success') {
                        // Refresh button state without reload if possible, but reload is safer for syncing points
                        setTimeout(() => location.reload(), 1500);
                    }
                })
                .catch(e => showMessage('Error: ' + e.message, 'danger'));
        }

        function showMessage(msg, type) {
            const box = document.getElementById('voteMessage');
            if (box) {
                box.className = `alert alert-${type} message-box`;
                box.textContent = msg;
                box.style.display = 'block';
                setTimeout(() => box.style.display = 'none', 5000);
            } else {
                alert(msg);
            }
        }

        // Optimized Avatar Loading with Local Data
        const securityTab = document.getElementById('security-tab');
        const availableAvatars = <?php echo json_encode($available_avatars); ?>;
        let avatarsLoaded = false;

        if (securityTab) {
            securityTab.addEventListener('shown.bs.tab', function (event) {
                if (avatarsLoaded) return;

                const container = document.getElementById('avatar-grid-container');
                const spinner = document.getElementById('avatar-loading-spinner');
                const currentAvatar = container.getAttribute('data-current');

                if (spinner) spinner.style.display = 'block';

                // Use setTimeout to allow the UI to update/spinner to show before heavy DOM manipulation (if any)
                setTimeout(() => {
                    let html = '';

                    // Add returned avatars
                    availableAvatars.forEach(av => {
                        const isSelected = currentAvatar === av.filename ? 'selected' : '';
                        html += `
                                <div class="col text-center">
                                    <img src="<?php echo $base_path; ?>img/accountimg/profile_pics/${av.filename}"
                                         class="${isSelected}"
                                         onclick="selectAvatar('${av.filename}')"
                                         alt="${av.display_name}"
                                         loading="lazy">
                                    <span>${av.display_name}</span>
                                </div>`;
                    });

                    // Add default user option
                    const isDefaultSelected = !currentAvatar ? 'selected' : '';
                    html += `
                            <div class="col text-center">
                                <img src="<?php echo $base_path; ?>img/accountimg/profile_pics/user.jpg"
                                     class="${isDefaultSelected}"
                                     onclick="selectAvatar('')"
                                     alt="<?php echo translate('avatar_default', 'Default Avatar'); ?>">
                                <span><?php echo translate('avatar_default', 'Default Avatar'); ?></span>
                            </div>`;

                    container.innerHTML = html;
                    if (spinner) spinner.style.display = 'none';
                    avatarsLoaded = true;
                }, 10);
            });
        }
    </script>
    </script>
<?php
ob_end_flush(); // Flush the output buffer
?>