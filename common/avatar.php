<?php
function huli_avatar_url($qq = '')
{
    $uin = !empty($qq) && preg_match('/^\d+$/', $qq) ? $qq : '10001';
    return 'https://q2.qlogo.cn/headimg_dl?dst_uin=' . $uin . '&spec=640';
}
