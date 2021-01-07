<?php

if (!defined('IN_ECS')) {
    die('Hacking attempt');
}

require_once 'tencent_cloud/DebugerLog.php';
require_once 'tencent_cloud/tms/autoload.php';
require_once 'cls_tencentcloud_center.php';

use TencentCloud\Common\Credential;
use TencentCloud\Common\Profile\ClientProfile;
use TencentCloud\Common\Exception\TencentCloudSDKException;
use TencentCloud\Cms\V20190321\CmsClient;
use TencentCloud\Cms\V20190321\Models\TextModerationRequest;
use TencentCloud\Cms\V20190321\Models\TextModerationResponse;
use TencentDiscuzTMS\TMSOptions;

class tencent_tms
{
    const  MAX_STRING_LEN = 5000;
    private $secret_id;
    private $secret_key;
    private $whitelist;
    private $table_name = 'tencentcloud_keywords';
    private $user_info;

    /**
     * tencent_cos constructor.
     * @param $options
     */
    public function __construct($options)
    {
        /* 由于要包含init.php，所以这两个对象一定是存在的，因此直接赋值 */
        $this->db = $GLOBALS['db'];
        $this->ecs = $GLOBALS['ecs'];
        $this->user_info = $GLOBALS['_SESSION'];
        $this->secret_id = isset($options['secret_id']) ? $options['secret_id'] : '';
        $this->secret_key = isset($options['secret_key']) ? $options['secret_key'] : '';
        $this->whitelist = isset($options['whitelist']) ? $options['whitelist'] : '';
    }

    /**
     * 返回Client对象
     *
     * @return bool|CmsClient
     * @throws Exception
     */
    private function getClient()
    {
        if (empty($this->secret_id) || empty($this->secret_key)) {
            DebugLog::writeDebugLog('debug', "secret params error");
            return false;
        }
        $cred = new Credential($this->secret_id, $this->secret_key);
        $clientProfile = new ClientProfile();
        return new CmsClient($cred, "ap-shanghai", $clientProfile);
    }

    /**
     * 调用腾讯云文本检测接口
     *
     * @param $text
     * @return bool
     * @throws Exception
     */
    public function textModeration($text)
    {
        $text = strlen($text) < self::MAX_STRING_LEN ? $text : substr($text, 0, self::MAX_STRING_LEN);
        if (isset($this->whitelist) && is_array($this->whitelist) && !empty($this->whitelist)) {
            $text = str_replace($this->whitelist, '', $text);
        }
        // 文本内容经过敏感词过滤后为空，可以正常发布
        if ($text === '') {
            return true;
        }
        try {
            $client = $this->getClient();
            if (!($client instanceof CmsClient)) {
                DebugLog::writeDebugLog('debug', "create CmsClient failed");
                return false;
            }
            $req = new TextModerationRequest();
            $params['Content'] = base64_encode($text);
            $req->fromJsonString(json_encode($params, JSON_UNESCAPED_UNICODE));
            $response = $client->TextModeration($req);

            //检测接口异常不影响用户发帖回帖
            if (!($response instanceof TextModerationResponse)) {
                DebugLog::writeDebugLog('debug', "interface error");
                return false;
            }

            //检测通过
            if ($response->getData()->getEvilLabel() === 'Normal' && $response->getData()->getEvilFlag() === 0) {
                DebugLog::writeDebugLog('debug', "content is legal");
                return true;
            }

            if (isset($this->user_info['admin_name']) && $this->user_info['admin_name'] !== '') {
                $user_name = $this->user_info['admin_name'];
                $uid = $this->user_info['admin_id'];
            } else {
                $user_name = $this->user_info['user_name'] === '' ? 'unlogin' : $this->user_info['user_name'];
                $uid = $this->user_info['user_id'];
            }

            // 获取敏感词命中数据
            $data = array(
                'uid' => $uid,
                'username' => $user_name,
                'keyword' => implode(', ', $response->getData()->getKeywords()),
                'examine_text' => $text,
                'evil_label' => $response->getData()->getEvilLabel(),
                'examine_date' => time(),
            );

            $this->saveKeywords($this->db, $data, $this->ecs->prefix . $this->table_name);

            DebugLog::writeDebugLog('debug', $text . "content is illegal");
            return false;
        } catch (TencentCloudSDKException $e) {
            DebugLog::writeDebugLog('debug', "exception error");
            return false;
        }
    }

    /**
     * 保存敏感词命中记录
     *
     * @param $db
     * @param $data
     * @param $table
     */
    private function saveKeywords($db, $data, $table)
    {
        $data = addslashes_deep($data);
        $sql = "INSERT INTO $table(uid, username, keyword, examine_text, evil_label, examine_date)
            VALUES ('" . $data['uid'] . "', '" . $data['username'] . "', '" . $data['keyword'] .
            "', '" . $data['examine_text'] . "', '" . $data['evil_label'] . "', '" . $data['examine_date'] . "')";
        $db->query($sql);
    }
}
