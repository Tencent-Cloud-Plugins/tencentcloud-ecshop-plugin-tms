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
define('TENCENTCLOUD_KEYWORD_TABLE', 'tencentcloud_keywords');

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
//Normal：正常，Polity：涉政，Porn：色情，Illegal：违法，Abuse：谩骂，Terror：暴恐，Ad：广告，Custom：自定义关键词
$evil_label_map = array(
    'Normal' => '正常',
    'Polity' => '涉政',
    'Porn' => '色情',
    'Illegal' => '违法',
    'Abuse' => '谩骂',
    'Terror' => '暴恐',
    'Ad' => '广告',
    'Custom' => '自定义关键词',
);
/*------------------------------------------------------ */
//-- 编辑 ?act=list_edit
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'list_edit') {
    $tms = isset($_CFG['tms_plugin']) ? json_decode($_CFG['tms_plugin'], true) : array();
    if (isset($tms['whitelist']) && is_array($tms['whitelist'])) {
        $tms['whitelist'] = implode(";", $tms['whitelist']);
    }

    $smarty->assign('tms', $tms);
    $center = json_decode($_CFG['tencent_center'], true);
    if (isset($center['global_secret']) && $center['global_secret'] === '1') {
        $smarty->assign('center', $center);
    }

    $smarty->assign('ur_here', $_LANG['tencent_tms']);
    $smarty->display('tencent_tms.htm');
}

/*------------------------------------------------------ */
//-- 提交   ?act=save_config
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'save_config') {
    $tms_options = isset($_CFG['tms_plugin']) ? json_decode($_CFG['tms_plugin'], true) : array();
    $switch = empty($_POST['switch']) ? '0' : $_POST['switch'];
    if ($switch === '1') {
        if (empty($tms_options) || $tms_options['switch'] === '0') {
            create_keywords_table($db, $ecs->prefix . TENCENTCLOUD_KEYWORD_TABLE);
        }
        $tms_options['switch'] = trim($switch);
        $tms_options['custome_secret'] = empty($_POST['custome_secret']) ? '0' : trim($_POST['custome_secret']);
        $tms_options['secret_id'] = empty($_POST['secret_id']) ? '' : $_POST['secret_id'];
        $tms_options['secret_key'] = empty($_POST['secret_key']) ? '' : $_POST['secret_key'];
    } else {
        $tms_options['switch'] = trim($switch);
    }
    if (isset($_POST['whitelist']) && !empty($_POST['whitelist'])) {
        $tms_options['whitelist'] = preg_split('/;|；/', $_POST['whitelist']);
    } else {
        $tms_options['whitelist'] = "";
    }

    $tms_options = addslashes_deep($tms_options);
    $datastr = json_encode($tms_options);

    // 解决向mysql数据库插入带有反斜杠”\”的字符时，数据库中保存的时候反斜杠”\”会丢失
    $datastr = str_replace('\\', '\\\\', $datastr);
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
/*------------------------------------------------------ */
//-- 获取命中记录   ?act=get_record
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'get_record') {
    $tms = isset($_CFG['tms_plugin']) ? json_decode($_CFG['tms_plugin'], true) : array();
    if (isset($tms['whitelist']) && is_array($tms['whitelist'])) {
        $tms['whitelist'] = implode(";", $tms['whitelist']);
    }
    $smarty->assign('tms', $tms);
    $center = json_decode($_CFG['tencent_center'], true);
    if (isset($center['global_secret']) && $center['global_secret'] === '1') {
        $smarty->assign('center', $center);
    }

    $sql = "SELECT * FROM " . $ecs->table(TENCENTCLOUD_KEYWORD_TABLE) . " where status = 1";
    $username = empty($_REQUEST['username']) ? '' : trim($_REQUEST['username']);
    $keyword = empty($_REQUEST['keyword']) ? '' : trim($_REQUEST['keyword']);
    $evillable = empty($_REQUEST['evillable']) ? '' : trim($_REQUEST['evillable']);

    if ($username !== '') {
        $filter['username'] = $username;
        $sql .= " and username like '%" . $username . "%'";
    }

    if ($keyword !== '') {
        $filter['keyword'] = $keyword;
        $sql .= " and keyword like '%" . $keyword . "%'";
    }

    if ($evillable !== '') {
        $filter['evillable'] = $evillable;
        $sql .= " and evil_label = '" . $evillable . "'";
    }
    $sql .= ' order by examine_date desc';

    $page_count = empty($_REQUEST['page_count']) ? 1 : intval($_REQUEST['page_count']);
    $page_size = empty($_REQUEST['page_size']) ? 10 : intval($_REQUEST['page_size']);
    $filter['page_count'] = $page_count;
    $filter['page_size'] = $page_size;
    $page_limit = ($page_count - 1) * $page_size;
    $sql .= " limit $page_limit, $page_size";
    $keywords = $db->getAll($sql);
    $keywords = format_keyword($keywords);
    $smarty->assign('keywords', $keywords);
    $smarty->assign('filter', $filter);
    $smarty->assign('evil_label_map', $evil_label_map);
    $smarty->assign('act', 'get_record');
    $smarty->assign('ur_here', $_LANG['tencent_tms']);
    $smarty->display('tencent_tms.htm');
}

/**
 * 创建敏感词表
 *
 * @param $db
 * @param $table
 */
function create_keywords_table($db, $table)
{
    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `$table` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uid` int(10) unsigned NOT NULL DEFAULT 0,
  `username` varchar(255) NOT NULL DEFAULT '' ,
  `keyword` varchar(255) NOT NULL DEFAULT '' ,
  `evil_label` varchar(16)  NOT NULL DEFAULT '' ,
  `type` tinyint(2) unsigned NOT NULL  DEFAULT 1 ,
  `examine_text` text NOT NULL ,
  `status` tinyint(2) unsigned  NOT NULL DEFAULT 1 ,
  `examine_date` bigint unsigned NOT NULL DEFAULT 0 ,
  PRIMARY KEY (`id`),
  KEY date_label_idx(`examine_date`,`evil_label`)
) ENGINE=MyISAM;
SQL;
    $db->query($sql);
}

/**
 * 格式化敏感词命中数据
 *
 * @param $kerywords
 * @return array
 */
function format_keyword($kerywords)
{
    global $evil_label_map;
    if (empty($kerywords)) {
        return array();
    }
    foreach ($kerywords as $k => $v) {
        if ($v['uid'] === '0') {
            $v['uid'] = '-';
        }
        $v['evil_label'] = isset($evil_label_map[$v['evil_label']]) ? $evil_label_map[$v['evil_label']] : $v['evil_label'];
        $v['examine_date'] = date('Y-m-d h:i:s', $v['examine_date']);
        $kerywords[$k] = $v;
    }
    return $kerywords;
}

