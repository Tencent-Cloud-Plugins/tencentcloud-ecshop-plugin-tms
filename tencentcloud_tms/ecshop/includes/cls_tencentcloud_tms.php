<?php

if (!defined('IN_ECS'))
{
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

class tencent_tms
{
    const  MAX_STRING_LEN = 5000;
    private $secret_id;
    private $secret_key;

    /**
     * tencent_cos constructor.
     * @param $options
     */
    public function __construct($options)
    {
        $this->secret_id = isset($options['secret_id']) ? $options['secret_id'] : '';
        $this->secret_key = isset($options['secret_key']) ? $options['secret_key'] : '';
    }

    /**
     * 返回Client对象
     * @param array $options 用户自定义插件参数
     * @return \Qcloud\Cos\Client
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
     * @param TMSOptions $TMSOptions
     * @param $text
     * @return Exception|TextModerationResponse|TencentCloudSDKException
     * @throws Exception
     */
    public function textModeration($text)
    {
        $text = strlen($text) < self::MAX_STRING_LEN ? $text : substr($text, 0, self::MAX_STRING_LEN);
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
            DebugLog::writeDebugLog('debug', $text . "content is illegal");
            return false;
        } catch (TencentCloudSDKException $e) {
            DebugLog::writeDebugLog('debug', "exception error");
            return false;
        }
    }
}
