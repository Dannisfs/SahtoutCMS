<?php
// Close main content divs opened in header.php
?>
</div> <!-- End left-column -->

<div class="right-column">
    <!-- Sidebar -->
    <?php if (!isset($show_sidebar) || $show_sidebar !== false)
        include __DIR__ . '/sidebar.php'; ?>
</div>

</div> <!-- End warmane-wrapper -->

<!-- Footer -->
<footer>
    <div class="footer-inner" style="max-width: 1200px; margin: 0 auto;">
        <div class="footer-socials" style="margin-bottom: 20px;">
            <?php if (!empty($social_links['facebook'])): ?>
                <a href="<?php echo htmlspecialchars($social_links['facebook']); ?>" target="_blank"><i
                        class="fab fa-facebook-f"></i></a>
            <?php endif; ?>
            <?php if (!empty($social_links['discord'])): ?>
                <a href="<?php echo htmlspecialchars($social_links['discord']); ?>" target="_blank"><i
                        class="fab fa-discord"></i></a>
            <?php endif; ?>
            <?php if (!empty($social_links['youtube'])): ?>
                <a href="<?php echo htmlspecialchars($social_links['youtube']); ?>" target="_blank"><i
                        class="fab fa-youtube"></i></a>
            <?php endif; ?>
        </div>

        <div class="footer-nav">
            <a href="<?php echo $base_path; ?>">Home</a>
            <a href="<?php echo $base_path; ?>news">News</a>
            <a href="<?php echo $base_path; ?>how_to_play">Download</a>
            <a href="<?php echo $base_path; ?>register">Create Account</a>
            <a href="<?php echo $base_path; ?>shop">Store</a>
            <a href="<?php echo $base_path; ?>bugtracker"><?php echo translate('nav_bugtracker', 'BugTracker'); ?></a>
            <a href="<?php echo $base_path; ?>contact">Contact Us</a>
        </div>

        <p style="margin-top: 20px; color: #444;">&copy; <?php echo date("Y"); ?> <?php echo $site_title_name; ?>. World
            of Warcraft is a trademark of Blizzard Entertainment.</p>
    </div>
</footer>

<!-- Scripts -->
<script>
    // Simple script for tab switching if tabs are present
    $(document).ready(function () {
        $('.tab-link').click(function () {
            var tab_id = $(this).attr('data-tab');
            $('.tab-link').removeClass('current');
            $('.tab-content').removeClass('current');
            $(this).addClass('current');
            $("#" + tab_id).addClass('current');
        });
    });
</script>
</body>

</html>