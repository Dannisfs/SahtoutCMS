<?php
/**
 * Server Status & Top Players Widget
 * Adapted from WoWSimpleRegistration by MasterkinG32
 * Integrated into SahtoutCMS
 */
if (!defined('ALLOWED_ACCESS'))
    exit('Direct access not allowed.');

// Race & Class icon mappings for WotLK (3.3.5)
$race_names = [
    1 => 'Human',
    2 => 'Orc',
    3 => 'Dwarf',
    4 => 'Night Elf',
    5 => 'Undead',
    6 => 'Tauren',
    7 => 'Gnome',
    8 => 'Troll',
    10 => 'Blood Elf',
    11 => 'Draenei'
];
$class_names = [
    1 => 'Warrior',
    2 => 'Paladin',
    3 => 'Hunter',
    4 => 'Rogue',
    5 => 'Priest',
    6 => 'Death Knight',
    7 => 'Shaman',
    8 => 'Mage',
    9 => 'Warlock',
    11 => 'Druid'
];

// Helper: format play time
function format_playtime($seconds)
{
    $days = floor($seconds / 86400);
    $hours = floor(($seconds % 86400) / 3600);
    $mins = floor(($seconds % 3600) / 60);
    if ($days > 0)
        return "{$days}d {$hours}h";
    if ($hours > 0)
        return "{$hours}h {$mins}m";
    return "{$mins}m";
}

// Helper: format gold
function format_gold($copper)
{
    $gold = floor($copper / 10000);
    $silver = floor(($copper % 10000) / 100);
    return number_format($gold) . 'g ' . $silver . 's';
}

// Get online players (limit 50)
$online_query = $char_db->query("SELECT name, race, class, gender, level FROM characters WHERE online = 1 ORDER BY level DESC LIMIT 50");
$online_count_query = $char_db->query("SELECT COUNT(*) as cnt FROM characters WHERE online = 1");
$online_count = 0;
if ($online_count_query) {
    $row = $online_count_query->fetch_assoc();
    $online_count = (int) $row['cnt'];
}

// Get realm name
$realm_name_val = 'Realm 1';
$rn_query = $auth_db->query("SELECT name FROM realmlist WHERE id = 1 LIMIT 1");
if ($rn_query && $rn_query->num_rows > 0) {
    $rn_row = $rn_query->fetch_assoc();
    $realm_name_val = htmlspecialchars($rn_row['name']);
}

// Top Players queries
function get_top_playtime($db)
{
    return $db->query("SELECT name, race, class, gender, level, totaltime FROM characters ORDER BY totaltime DESC LIMIT 10");
}
function get_top_killers($db)
{
    return $db->query("SELECT name, race, class, gender, level, totalKills FROM characters ORDER BY totalKills DESC LIMIT 10");
}
function get_top_honor($db)
{
    return $db->query("SELECT name, race, class, gender, level, totalHonorPoints FROM characters ORDER BY totalHonorPoints DESC LIMIT 10");
}
function get_top_arena($db)
{
    return $db->query("SELECT name, race, class, gender, level, arenaPoints FROM characters ORDER BY arenaPoints DESC LIMIT 10");
}
function get_top_arena_teams($db)
{
    return $db->query("SELECT at.arenaTeamId, at.name, at.captainGuid, at.rating, c.name as captainName FROM arena_team at LEFT JOIN characters c ON at.captainGuid = c.guid ORDER BY at.rating DESC LIMIT 10");
}

$img_path = $base_path . 'img/';
?>

<!-- SERVER STATUS SECTION -->
<section class="server-status-widget" id="server-status-section">
    <div class="status-container">
        <h2 class="status-title">
            <i class="fas fa-server"></i>
            <?php echo translate('server_status', 'SERVER STATUS'); ?>
        </h2>
        <p class="status-subtitle">
            <?php echo translate('online_players', 'Online Players'); ?>:
        </p>
        <p class="realm-info">
            <span class="realm-name">
                <?php echo $realm_name_val; ?>
            </span>
            <span class="player-count">(
                <?php echo translate('limited_display', 'Limited to 50 players'); ?> ·
                <?php echo translate('online_players', 'Online Players'); ?>:
                <?php echo $online_count; ?>)
            </span>
        </p>

        <?php if ($online_count > 0 && $online_query && $online_query->num_rows > 0): ?>
            <div class="status-table-wrap">
                <table class="status-table" id="onlinePlayersTable">
                    <thead>
                        <tr>
                            <th>
                                <?php echo translate('name', 'Name'); ?>
                            </th>
                            <th>
                                <?php echo translate('race', 'Race'); ?>
                            </th>
                            <th>
                                <?php echo translate('class', 'Class'); ?>
                            </th>
                            <th>
                                <?php echo translate('level', 'Level'); ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($char = $online_query->fetch_assoc()): ?>
                            <tr>
                                <td class="player-name">
                                    <?php echo htmlspecialchars($char['name']); ?>
                                </td>
                                <td><img src="<?php echo $img_path; ?>race/<?php echo (int) $char['race']; ?>-<?php echo (int) $char['gender']; ?>.gif"
                                        alt="<?php echo $race_names[(int) $char['race']] ?? ''; ?>"
                                        title="<?php echo $race_names[(int) $char['race']] ?? ''; ?>"></td>
                                <td><img src="<?php echo $img_path; ?>class/<?php echo (int) $char['class']; ?>.gif"
                                        alt="<?php echo $class_names[(int) $char['class']] ?? ''; ?>"
                                        title="<?php echo $class_names[(int) $char['class']] ?? ''; ?>"></td>
                                <td class="player-level">
                                    <?php echo (int) $char['level']; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="no-players">
                <?php echo translate('no_players_online', 'No players are currently online.'); ?>
            </p>
        <?php endif; ?>
    </div>
</section>

<!-- TOP PLAYERS SECTION -->
<section class="top-players-widget" id="top-players-section">
    <div class="status-container">
        <h2 class="status-title">
            <i class="fas fa-trophy"></i>
            <?php echo translate('top_players', 'TOP PLAYERS'); ?>
        </h2>
        <p class="realm-info">
            <span class="realm-name">
                <?php echo $realm_name_val; ?>
            </span>
        </p>

        <div class="top-tabs">
            <button class="top-tab active" data-top="playtime"><i class="fas fa-clock"></i>
                <?php echo translate('play_time', 'Play Time'); ?>
            </button>
            <button class="top-tab" data-top="killers"><i class="fas fa-skull-crossbones"></i>
                <?php echo translate('killers', 'Killers'); ?>
            </button>
            <button class="top-tab" data-top="honor"><i class="fas fa-medal"></i>
                <?php echo translate('honor_points', 'Honor Points'); ?>
            </button>
            <button class="top-tab" data-top="arena"><i class="fas fa-fist-raised"></i>
                <?php echo translate('arena_points', 'Arena Points'); ?>
            </button>
            <button class="top-tab" data-top="teams"><i class="fas fa-users"></i>
                <?php echo translate('arena_teams', 'Arena Teams'); ?>
            </button>
        </div>

        <!-- Play Time -->
        <div class="top-panel active" id="top-playtime">
            <?php $res = get_top_playtime($char_db);
            if ($res && $res->num_rows > 0): ?>
                <table class="status-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>
                                <?php echo translate('name', 'Name'); ?>
                            </th>
                            <th>
                                <?php echo translate('race', 'Race'); ?>
                            </th>
                            <th>
                                <?php echo translate('class', 'Class'); ?>
                            </th>
                            <th>
                                <?php echo translate('level', 'Level'); ?>
                            </th>
                            <th>
                                <?php echo translate('play_time', 'Play Time'); ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rank = 1;
                        while ($r = $res->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?php echo $rank++; ?>
                                </td>
                                <td class="player-name">
                                    <?php echo htmlspecialchars($r['name']); ?>
                                </td>
                                <td><img src="<?php echo $img_path; ?>race/<?php echo (int) $r['race']; ?>-<?php echo (int) $r['gender']; ?>.gif"
                                        title="<?php echo $race_names[(int) $r['race']] ?? ''; ?>"></td>
                                <td><img src="<?php echo $img_path; ?>class/<?php echo (int) $r['class']; ?>.gif"
                                        title="<?php echo $class_names[(int) $r['class']] ?? ''; ?>"></td>
                                <td>
                                    <?php echo (int) $r['level']; ?>
                                </td>
                                <td>
                                    <?php echo format_playtime((int) $r['totaltime']); ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="no-players">
                    <?php echo translate('no_data', 'No data available.'); ?>
                </p>
            <?php endif; ?>
        </div>

        <!-- Killers -->
        <div class="top-panel" id="top-killers">
            <?php $res = get_top_killers($char_db);
            if ($res && $res->num_rows > 0): ?>
                <table class="status-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>
                                <?php echo translate('name', 'Name'); ?>
                            </th>
                            <th>
                                <?php echo translate('race', 'Race'); ?>
                            </th>
                            <th>
                                <?php echo translate('class', 'Class'); ?>
                            </th>
                            <th>
                                <?php echo translate('level', 'Level'); ?>
                            </th>
                            <th>
                                <?php echo translate('kills', 'Kills'); ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rank = 1;
                        while ($r = $res->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?php echo $rank++; ?>
                                </td>
                                <td class="player-name">
                                    <?php echo htmlspecialchars($r['name']); ?>
                                </td>
                                <td><img src="<?php echo $img_path; ?>race/<?php echo (int) $r['race']; ?>-<?php echo (int) $r['gender']; ?>.gif"
                                        title="<?php echo $race_names[(int) $r['race']] ?? ''; ?>"></td>
                                <td><img src="<?php echo $img_path; ?>class/<?php echo (int) $r['class']; ?>.gif"
                                        title="<?php echo $class_names[(int) $r['class']] ?? ''; ?>"></td>
                                <td>
                                    <?php echo (int) $r['level']; ?>
                                </td>
                                <td>
                                    <?php echo number_format((int) $r['totalKills']); ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="no-players">
                    <?php echo translate('no_data', 'No data available.'); ?>
                </p>
            <?php endif; ?>
        </div>

        <!-- Honor Points -->
        <div class="top-panel" id="top-honor">
            <?php $res = get_top_honor($char_db);
            if ($res && $res->num_rows > 0): ?>
                <table class="status-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>
                                <?php echo translate('name', 'Name'); ?>
                            </th>
                            <th>
                                <?php echo translate('race', 'Race'); ?>
                            </th>
                            <th>
                                <?php echo translate('class', 'Class'); ?>
                            </th>
                            <th>
                                <?php echo translate('level', 'Level'); ?>
                            </th>
                            <th>
                                <?php echo translate('honor_points', 'Honor Points'); ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rank = 1;
                        while ($r = $res->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?php echo $rank++; ?>
                                </td>
                                <td class="player-name">
                                    <?php echo htmlspecialchars($r['name']); ?>
                                </td>
                                <td><img src="<?php echo $img_path; ?>race/<?php echo (int) $r['race']; ?>-<?php echo (int) $r['gender']; ?>.gif"
                                        title="<?php echo $race_names[(int) $r['race']] ?? ''; ?>"></td>
                                <td><img src="<?php echo $img_path; ?>class/<?php echo (int) $r['class']; ?>.gif"
                                        title="<?php echo $class_names[(int) $r['class']] ?? ''; ?>"></td>
                                <td>
                                    <?php echo (int) $r['level']; ?>
                                </td>
                                <td>
                                    <?php echo number_format((int) $r['totalHonorPoints']); ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="no-players">
                    <?php echo translate('no_data', 'No data available.'); ?>
                </p>
            <?php endif; ?>
        </div>

        <!-- Arena Points -->
        <div class="top-panel" id="top-arena">
            <?php $res = get_top_arena($char_db);
            if ($res && $res->num_rows > 0): ?>
                <table class="status-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>
                                <?php echo translate('name', 'Name'); ?>
                            </th>
                            <th>
                                <?php echo translate('race', 'Race'); ?>
                            </th>
                            <th>
                                <?php echo translate('class', 'Class'); ?>
                            </th>
                            <th>
                                <?php echo translate('level', 'Level'); ?>
                            </th>
                            <th>
                                <?php echo translate('arena_points', 'Arena Points'); ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rank = 1;
                        while ($r = $res->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?php echo $rank++; ?>
                                </td>
                                <td class="player-name">
                                    <?php echo htmlspecialchars($r['name']); ?>
                                </td>
                                <td><img src="<?php echo $img_path; ?>race/<?php echo (int) $r['race']; ?>-<?php echo (int) $r['gender']; ?>.gif"
                                        title="<?php echo $race_names[(int) $r['race']] ?? ''; ?>"></td>
                                <td><img src="<?php echo $img_path; ?>class/<?php echo (int) $r['class']; ?>.gif"
                                        title="<?php echo $class_names[(int) $r['class']] ?? ''; ?>"></td>
                                <td>
                                    <?php echo (int) $r['level']; ?>
                                </td>
                                <td>
                                    <?php echo number_format((int) $r['arenaPoints']); ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="no-players">
                    <?php echo translate('no_data', 'No data available.'); ?>
                </p>
            <?php endif; ?>
        </div>

        <!-- Arena Teams -->
        <div class="top-panel" id="top-teams">
            <?php $res = get_top_arena_teams($char_db);
            if ($res && $res->num_rows > 0): ?>
                <table class="status-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>
                                <?php echo translate('team_name', 'Team Name'); ?>
                            </th>
                            <th>
                                <?php echo translate('rating', 'Rating'); ?>
                            </th>
                            <th>
                                <?php echo translate('captain', 'Captain'); ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rank = 1;
                        while ($r = $res->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?php echo $rank++; ?>
                                </td>
                                <td class="player-name">
                                    <?php echo htmlspecialchars($r['name']); ?>
                                </td>
                                <td>
                                    <?php echo number_format((int) $r['rating']); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($r['captainName'] ?? '-'); ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="no-players">
                    <?php echo translate('no_data', 'No data available.'); ?>
                </p>
            <?php endif; ?>
        </div>
    </div>
</section>

<script>
    // Top Players tab switching
    document.querySelectorAll('.top-tab').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.top-tab').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.top-panel').forEach(p => p.classList.remove('active'));
            this.classList.add('active');
            document.getElementById('top-' + this.dataset.top).classList.add('active');
        });
    });
</script>