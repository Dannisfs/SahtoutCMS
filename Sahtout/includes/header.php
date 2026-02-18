<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/paths.php';

if (!defined('ALLOWED_ACCESS')) {
    header('HTTP/1.1 403 Forbidden');
    exit('Direct access to this file is not allowed.');
}

require_once $project_root . 'includes/config.settings.php';
require_once $project_root . 'includes/session.php'; // Include session and DB config
require_once $project_root . 'languages/language.php';

// Language Switch Logic
if (isset($_GET['lang'])) {
    $lang_code = trim($_GET['lang']);
    $allowed_langs = ['en', 'zh', 'cn', 'de', 'es', 'fr', 'ru'];
    if (in_array($lang_code, $allowed_langs)) {
        if ($lang_code == 'cn')
            $lang_code = 'zh';
        $_SESSION['lang'] = $lang_code;
    }
}
$current_lang = $_SESSION['lang'] ?? 'en';

$page_class = isset($page_class) ? $page_class : 'default';

// User Data & Avatar Logic
$points = 0;
$tokens = 0;
$avatar = $base_path . 'img/accountimg/profile_pics/user.jpg';
$username = '';
$gmlevel = 0;
if (isset($_SESSION['user_id'])) {
    $username = $_SESSION['username'] ?? 'User';
    if (!empty($_SESSION['avatar']))
        $avatar = $base_path . 'img/accountimg/profile_pics/' . $_SESSION['avatar'];

    // Refresh currency if needed
    if (isset($site_db)) {
        $stmt_site = $site_db->prepare("SELECT points, tokens, avatar FROM user_currencies WHERE account_id = ?");
        if ($stmt_site) {
            $stmt_site->bind_param('i', $_SESSION['user_id']);
            $stmt_site->execute();
            $stmt_site->bind_result($points, $tokens, $db_avatar);
            if ($stmt_site->fetch()) {
                if (!empty($db_avatar))
                    $avatar = $base_path . 'img/accountimg/profile_pics/' . $db_avatar;
            }
            $stmt_site->close();
        }
        // GM Level check
        if (isset($auth_db)) {
            $stmt_gm = $auth_db->prepare("SELECT gmlevel FROM account_access WHERE id = ?");
            if ($stmt_gm) {
                $stmt_gm->bind_param('i', $_SESSION['user_id']);
                $stmt_gm->execute();
                $stmt_gm->bind_result($gmlevel);
                $stmt_gm->fetch();
                $stmt_gm->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if (isset($meta_description)): ?>
        <meta name="description" content="<?php echo htmlspecialchars($meta_description); ?>">
    <?php endif; ?>
    <title><?php echo isset($page_title) ? $page_title : (isset($site_title_name) ? $site_title_name : 'RebornWOW'); ?>
    </title>
    <base href="<?php echo $base_path; ?>">
    <link rel="icon" href="<?php echo $base_path . $site_logo; ?>" type="image/x-icon">

    <!-- Warmane Theme CSS -->
    <link rel="stylesheet" href="<?php echo $base_path; ?>assets/css/warmane.css?v=<?php echo time(); ?>">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <?php if (file_exists($project_root . "assets/css/{$page_class}.css")): ?>
        <!-- Optional Page Specific Overrides -->
        <link rel="stylesheet"
            href="<?php echo $base_path; ?>assets/css/<?php echo $page_class; ?>.css?v=<?php echo time(); ?>">
    <?php endif; ?>
</head>

<body class="<?php echo htmlspecialchars($page_class); ?>">

    <!-- Logo (RebornWOW) -->
    <!-- Simplified: No box, no bottom text, just the glowing icon and text -->
    <div class="warmane-logo-container" style="top: 0; left: 5%;">
        <a href="<?php echo $base_path; ?>" style="text-decoration:none;">
            <div style="text-align: left; padding: 15px 0;">
                <div
                    style="font-family: 'Cinzel', serif; font-size: 32px; color: #ffcb00; text-shadow: 0 2px 5px #000; font-weight: 700; letter-spacing: 1px; line-height: 1; display: flex; align-items: center;">
                    <i class="fas fa-dragon"
                        style="margin-right: 12px; font-size: 36px; filter: drop-shadow(0 0 5px #ffcb00);"></i>
                    <span>
                        Reborn<span style="color: #fff; text-shadow: 0 0 10px rgba(255,255,255,0.5);">WOW</span>
                    </span>
                </div>
            </div>
        </a>
    </div>

    <!-- Top Navigation Bar -->
    <nav class="warmane-nav">
        <!-- Added padding-left to push menu items away from the logo area -->
        <ul class="nav-links" style="padding-left: 230px; display: flex; align-items: center;">
            <li><a href="<?php echo $base_path; ?>"><?php echo translate('nav_home', 'Home'); ?></a></li>
            <li><a
                    href="<?php echo $base_path; ?>how_to_play"><?php echo translate('nav_how_to_play', 'Download'); ?></a>
            </li>
            <li><a href="<?php echo $base_path; ?>map"><?php echo translate('nav_map', 'Map'); ?></a></li>
            <li><a href="<?php echo $base_path; ?>armory/solo_pvp"><?php echo translate('nav_armory', 'Armory'); ?></a>
            </li>
            <li><a href="<?php echo $base_path; ?>shop"><?php echo translate('nav_shop', 'Shop'); ?></a></li>
            <li><a
                    href="<?php echo $base_path; ?>bugtracker"><?php echo translate('nav_bugtracker', 'BugTracker'); ?></a>
            </li>
            <li><a
                    href="<?php echo $base_path; ?>register"><?php echo translate('nav_register', 'Create Account'); ?></a>
            </li>

            <!-- Account Panel Link -->
            <li style="border-left: 1px solid #443322; padding-left: 20px; margin-left: 10px;">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="<?php echo $base_path; ?>account" style="color: #fff;"><i class="fas fa-user-circle"></i>
                        <?php echo translate('nav_account', 'ACCOUNT'); ?>
                    </a>
                <?php else: ?>
                    <a href="<?php echo $base_path; ?>login" style="color: #fff;"><i class="fas fa-sign-in-alt"></i>
                        <?php echo translate('nav_login', 'LOG IN'); ?>
                    </a>
                <?php endif; ?>
            </li>

            <!-- Language Selection -->
            <li style="margin-left: 10px; font-size: 13px; color: #666;">|</li>
            <li style="margin: 0 5px;">
                <a href="?lang=en"
                    style="color: <?php echo $current_lang == 'en' ? '#fff' : '#888'; ?>; font-size: 12px; padding: 5px;">EN</a>
            </li>
            <li style="margin: 0;">
                <a href="?lang=zh"
                    style="color: <?php echo ($current_lang == 'zh' || $current_lang == 'cn') ? '#fff' : '#888'; ?>; font-size: 12px; padding: 5px;">CN</a>
            </li>
        </ul>
    </nav>

    <!-- Main Content Wrapper -->
    <div class="warmane-wrapper<?php echo (isset($show_sidebar) && $show_sidebar === false) ? ' no-sidebar' : ''; ?>">
        <div class="left-column">
            <!-- Content Output Starts Here -->