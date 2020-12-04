<?php

/**
 * ECSHOP 管理中心腾讯云产品设置
 * ============================================================================
 * * 版权所有 2020-2040 腾讯云，并保留所有权利。
 * 网站地址: http://www.ecshop.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: tencent $
 * $Id: tencent_center.php 17217 2020-08-25 06:29:08Z jerry $
 */

define('IN_ECS', true);

/* 代码 */
require(dirname(__FILE__) . '/includes/init.php');

if ($GLOBALS['_CFG']['certificate_id'] == '') {
    $certi_id = 'error';
} else {
    $certi_id = $GLOBALS['_CFG']['certificate_id'];
}

$sess_id = $GLOBALS['sess']->get_session_id();
/* 检查权限 */
admin_priv('shop_config');
/*------------------------------------------------------ */
//-- 编辑 ?act=list_edit
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'list_edit') {
    $tms = isset($_CFG['tms_plugin']) ? json_decode($_CFG['tms_plugin'], true) : array();
    $smarty->assign('tms', $tms);
    $center  = json_decode($_CFG['tencent_center'], true);
    if (isset($center['global_secret']) && $center['global_secret'] === '1') {
        $smarty->assign('center',    $center);
    }

    $smarty->assign('ur_here',   $_LANG['tencent_tms']);
    $smarty->display('tencent_tms.htm');
}

/*------------------------------------------------------ */
//-- 提交   ?act=save_config
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'save_config') {
    $tms_options = isset($_CFG['tms_plugin']) ? json_decode($_CFG['tms_plugin'], true) : array();
    $switch = empty($_POST['switch']) ? '0' : $_POST['switch'];

    if ($switch === '1') {
        $tms_options['switch'] = trim($switch);
        $tms_options['custome_secret'] = empty($_POST['custome_secret']) ? '0' : trim($_POST['custome_secret']);
        $tms_options['secret_id'] = empty($_POST['secret_id']) ? '' : $_POST['secret_id'];
        $tms_options['secret_key'] = empty($_POST['secret_key']) ? '' : $_POST['secret_key'];
    } else {
        $tms_options['switch'] = trim($switch);
    }

    $tms_options = addslashes_deep($tms_options);
    $datastr = json_encode($tms_options);
    $tms_plugin = isset($_CFG['tms_plugin']) ? $_CFG['tms_plugin'] : array();
    if (empty($tms_plugin)) {
        $sql = "insert into " . $ecs->table('shop_config') . " (parent_id, code, type, value) values (6, 'tms_plugin', 'hidden', '$datastr')";
        $db->query($sql);
    } else {
        $sql = "UPDATE " . $ecs->table('shop_config') . " SET value= '" . $datastr . "' WHERE code='tms_plugin'";
        $db->query($sql);
    }
    clear_cache_files();
    require(dirname(__FILE__) . '/../includes/cls_tencentcloud_center.php');
    $tencent_center = new tencent_center();
    $upload_data = array(
        'action' => $switch === '1' ? 'activate' : 'deactivate',
        'plugin_type' => 'tms',
        'data' => array(
            'uin' => $tencent_center->getUserUinBySecret($tms_options['secret_id'], $tms_options['secret_key']),
            'cust_sec_on' => $data['custome_secret'] === '1' ? 1 : 2,
            'others' => json_encode(array())
        )
    );
    $tencent_center->sendUserExperienceInfo($upload_data);

    sys_msg($_LANG['save_ok'], 0, array(array('href' => 'tencent_tms.php?act=list_edit', 'text' => $_LANG['5tencent_tms'])));
}
