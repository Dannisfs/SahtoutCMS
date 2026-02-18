<?php
// Warmane-style Sidebar (Simplified)
?>
<aside id="sidebar-warmane">

    <!-- ACCOUNT PANEL -->
    <section class="sidebox">
        <div class="sidebox-header">
            <h3>ACCOUNT PANEL</h3>
        </div>
        <div class="sidebox-body user-panel-mini">
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="user-avatar-mini" style="text-align: center;">
                    <img src="<?php echo $avatar; ?>" width="80" height="80" alt="Avatar"
                        style="border-radius: 4px; box-shadow: 0 0 10px #000;">
                </div>
                <p style="color: #ecc05b; margin: 15px 0; font-family: 'Cinzel', serif; text-align: center;">Welcome,
                    <?php echo htmlspecialchars($username); ?>
                </p>
                <div class="user-stats"
                    style="border-top: 1px solid #333; border-bottom: 1px solid #333; padding: 10px 0; margin-bottom: 15px; text-align: center;">
                    <span style="color: #ffcb00; font-weight: bold;"><i class="fas fa-coins"></i> <?php echo $points; ?>
                        VP</span> &nbsp;|&nbsp;
                    <span style="color: #00eaff; font-weight: bold;"><i class="fas fa-gem"></i> <?php echo $tokens; ?>
                        DP</span>
                </div>

                <a href="<?php echo $base_path; ?>account" class="nice_button"
                    style="display: block; margin-bottom: 10px; text-align: center;">ACCOUNT SETTINGS</a>

                <?php if ($gmlevel > 0): ?>
                    <a href="<?php echo $base_path; ?>admin/dashboard" class="nice_button"
                        style="display: block; margin-bottom: 10px; text-align: center; color: #ff5555; border-color: #aa3333;">ADMIN
                        PANEL</a>
                <?php endif; ?>

                <a href="<?php echo $base_path; ?>logout" class="nice_button"
                    style="display: block; text-align: center; background: #222; border-color: #444;">LOG OUT</a>

            <?php else: ?>
                <p style="font-size: 13px; color: #aaa; text-align: center;">Log in to manage your account.</p>
                <form action="<?php echo $base_path; ?>login" method="post">
                    <input type="text" name="username" class="input_field" placeholder="Username"
                        style="width: 100%; margin-bottom: 10px; box-sizing: border-box;">
                    <input type="password" name="password" class="input_field" placeholder="Password"
                        style="width: 100%; margin-bottom: 10px; box-sizing: border-box;">
                    <input type="submit" value="LOG IN" class="nice_button" style="width: 100%;">
                </form>
                <div style="margin-top: 15px; font-size: 12px; text-align: center;">
                    <a href="<?php echo $base_path; ?>forgot_password" style="color: #888; text-decoration: none;">Forgot
                        password?</a> Or <a href="<?php echo $base_path; ?>register"
                        style="color: #ecc05b; text-decoration: none;">Create Account</a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- SERVER STATUS -->
    <section class="sidebox">
        <div class="sidebox-header">
            <h3>SERVER STATUS</h3>
        </div>
        <div class="sidebox-body">
            <div class="realm-status-list">
                <?php
                // Initialize $realmlistIP with a default
                $realmlistIP = '127.0.0.1';

                // Check if $realmsFile is defined and exists, then include it
                if (isset($realmsFile) && file_exists($realmsFile)) {
                    include $realmsFile; // This file is expected to define $realmlist
                }

                // Priority for realmlist IP: Admin Config > Realm Config > Default
                if (isset($server_realmlist) && !empty($server_realmlist)) {
                    $realmlistIP = $server_realmlist;
                } elseif (isset($realmlist) && is_array($realmlist) && !empty($realmlist[0]['address'])) {
                    $realmlistIP = $realmlist[0]['address'];
                }
                ?>
                <!-- Main Realm -->
                <div class="realm-row"
                    style="display: flex; align-items: center; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.05);">
                    <div class="realm-name-cell" style="display: flex; align-items: center;">
                        <i class="fas fa-dragon" style="color: #ecc05b; font-size: 24px; margin-right: 10px;"></i>
                        <span class="realm-name"
                            style="color: #ecc05b; font-family: 'Cinzel', serif; font-weight: bold;"><?php echo htmlspecialchars($server_name ?? 'RebornWOW'); ?></span>
                    </div>
                    <div class="realm-info" style="text-align: right;">
                        <div class="status-online" style="color: #4caf50; font-weight: bold;">Online</div>
                        <div class="realm-pop" style="color: #888; font-size: 12px;">High Pop</div>
                    </div>
                </div>

                <div
                    style="margin-top: 20px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 15px; text-align: center; font-size: 13px; line-height: 1.6; color: #aaa;">
                    Status: <span
                        style="color: #4caf50;"><?php echo htmlspecialchars($server_status_text ?? 'Stable'); ?></span><br>
                    Uptime: 2 Days 4 Hours<br>
                    Version: <?php echo htmlspecialchars($server_version ?? '3.3.5a'); ?><br>
                    <strong>set realmlist <?php echo htmlspecialchars($server_realmlist ?? '127.0.0.1'); ?></strong>
                </div>

                <div style="margin-top: 15px; text-align: center;">
                    <a href="<?php echo $base_path; ?>how_to_play"
                        style="color: #ecc05b; font-size: 12px; text-transform: uppercase; text-decoration: none;">Connection
                        Guide &raquo;</a>
                </div>
            </div>
        </div>
    </section>

</aside>