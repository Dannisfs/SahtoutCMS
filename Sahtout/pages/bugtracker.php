<?php
define('ALLOWED_ACCESS', true);
require_once __DIR__ . '/../includes/paths.php';
require_once $project_root . 'includes/session.php';
require_once $project_root . 'languages/language.php';
require_once $project_root . 'includes/config.settings.php';

$page_class = 'bugtracker';
$page_title = $site_title_name . ' - ' . translate('nav_bugtracker', 'BugTracker');

include $project_root . 'includes/header.php';
?>

<div class="main-content">
    <div class="warmane-wrapper">
        <div class="warmane-content" style="padding: 40px; text-align: center; color: #fff; width: 100%;">
            <h1
                style="color: #ecc05b; font-family: 'Cinzel', serif; font-size: 36px; margin-bottom: 20px; text-transform: uppercase; text-shadow: 2px 2px 4px #000;">
                <?php echo translate('nav_bugtracker', 'BugTracker'); ?>
            </h1>

            <div
                style="background: rgba(0, 0, 0, 0.6); border: 2px solid #5c3a16; padding: 40px; border-radius: 8px; max-width: 800px; margin: 0 auto;">
                <i class="fas fa-bug" style="font-size: 64px; color: #ecc05b; margin-bottom: 20px;"></i>
                <p style="font-size: 18px; line-height: 1.6; margin-bottom: 30px;">
                    <?php echo translate('bugtracker_content', 'View and report issues with the server to help us improve your experience.'); ?>
                </p>

                <div style="display: flex; justify-content: center; gap: 20px;">
                    <?php if (!empty($bugtracker_url)): ?>
                        <a href="<?php echo htmlspecialchars($bugtracker_url); ?>" target="_blank" class="warmane-btn"
                            style="background: linear-gradient(to bottom, #a90329 0%, #8f0222 44%, #6d0019 100%); color: #fff; padding: 12px 30px; text-decoration: none; border-radius: 4px; font-weight: bold; border: 1px solid #5c3a16; text-transform: uppercase;">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo translate('btn_report_issue', 'Report Issue'); ?>
                        </a>
                    <?php else: ?>
                        <div style="color: #888; font-style: italic;">
                            <?php echo translate('msg_no_bugtracker_configured', 'Bug tracker URL not configured yet.'); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div style="margin-top: 40px; text-align: left; max-width: 800px; margin-left: auto; margin-right: auto;">
                <h3 style="color: #ecc05b; border-bottom: 1px solid #443322; padding-bottom: 10px;">
                    <?php echo translate('bugtracker_guidelines', 'Reporting Guidelines'); ?>
                </h3>
                <ul style="list-style-type: disc; padding-left: 20px; color: #ccc; line-height: 1.8;">
                    <li>Please search for existing issues before reporting a new one.</li>
                    <li>Provide as much detail as possible (screenshots, steps to reproduce).</li>
                    <li>Categorize your report correctly (Quest, Spell, Loot, etc.).</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php
include $project_root . 'includes/footer.php';
?>