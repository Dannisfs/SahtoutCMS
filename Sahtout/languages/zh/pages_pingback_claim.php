<?php
return [
    // Success Messages
    'msg_reward_claimed' => '奖励已成功领取给 %s。+%d 积分已添加到您的账户。',

    // Error Messages
    'err_invalid_csrf' => '无效的CSRF令牌。',
    'err_invalid_user_id' => '无效的用户ID。',
    'err_user_not_found' => '在user_currencies中未找到用户ID。',
    'err_site_not_found' => '在vote_sites中未找到站点ID：%s。',
    'err_no_unclaimed_votes' => '用户 %s 没有未领取的投票奖励。',
    'err_database_generic' => '数据库错误：%s',
    'err_db_connection_failed' => '数据库连接失败。',
];
?>