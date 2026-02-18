<?php
define('ALLOWED_ACCESS', true);
require_once __DIR__ . '/../includes/paths.php';
require_once $project_root . 'includes/session.php';
require_once $project_root . 'languages/language.php';

$page_title = translate('nav_map', 'Server Map');
$page_class = 'map';

// Load header
require_once $project_root . 'includes/header.php';
?>

<style>
    /* Prevent left-column from being stretched by map content */
    .left-column {
        overflow: hidden;
        min-width: 0;
        /* Allow flex item to shrink below content size */
    }

    .map-embed-container {
        text-align: center;
        border: 1px solid #443322;
        padding: 10px;
        box-shadow: inset 0 0 20px rgba(0, 0, 0, 0.8);
        position: relative;
    }

    /* Scrollable Container Styles */
    .map-scroll-container {
        width: 100%;
        /* Default desktop height */
        height: 900px;
        /* Allow horizontal scroll, prevent internal vertical scroll */
        overflow-x: auto;
        overflow-y: hidden;
        -webkit-overflow-scrolling: touch;
        border: 1px solid #443322;
        background: #000;
        position: relative;
    }

    /* The Map Iframe */
    .map-scroll-container iframe {
        width: 966px;
        /* Content height */
        height: 900px;
        display: block;
        border: none;
        margin: 0;
        padding: 0;
    }

    /* Mobile Height Adjustment */
    /* Mobile Height Adjustment */
    @media (max-width: 991px) {
        .map-scroll-container {
            /* Increase height significantly to prevent any bottom clipping */
            height: 1800px;
        }

        .map-scroll-container iframe {
            /* Match container height and allow internal scrolling if needed */
            height: 1800px;
        }
    }

    /* Custom Scrollbars for Visibility */
    .map-scroll-container::-webkit-scrollbar {
        width: 12px;
        height: 12px;
    }

    .map-scroll-container::-webkit-scrollbar-track {
        background: #1a0f00;
    }

    .map-scroll-container::-webkit-scrollbar-thumb {
        background-color: #ecc05b;
        /* Visible Gold Color */
        border: 2px solid #1a0f00;
        border-radius: 6px;
    }

    .map-scroll-container::-webkit-scrollbar-corner {
        background: #1a0f00;
    }
</style>

<!-- Embedded Map Container -->
<div class="map-embed-container">
    <h2 style="
        color: #ecc05b; 
        font-family: 'Cinzel', serif; 
        border-bottom: 1px solid #443322; 
        padding-bottom: 15px; 
        margin-bottom: 20px; 
        text-transform: uppercase;
        text-shadow: 0 2px 4px rgba(0,0,0,0.8);">
        <?php echo translate('nav_map', 'World Map'); ?>
    </h2>

    <!-- Scrollable Map Wrapper -->
    <div class="map-scroll-container">
        <!-- Remove scrolling="no" to allow content to expand naturally or scroll if needed -->
        <iframe src="<?php echo $base_path; ?>playermap/index.php" style="overflow:hidden;"></iframe>
    </div>

    <div style="margin-top: 15px; font-size: 13px; color: #888; font-style: italic;">
        <?php echo translate('map_tip', 'Use scrollbars to view the full map.'); ?>
    </div>
</div>

<?php
// Load footer
require_once $project_root . 'includes/footer.php';
?>