<?php

if (!defined('IN_ECS'))
{
    die('Hacking attempt');
}

require_once 'tencent_cloud/DebugerLog.php';
require_once 'tencent_cloud/ims/autoload.php';
require_once 'cls_tencentcloud_center.php';

use TencentCloud\Cms\V20190321\Models\ImageModerationResponse;
use TencentCloud\Common\Credential;
use TencentCloud\Common\Profile\ClientProfile;
use TencentCloud\Common\Exception\TencentCloudSDKException;
use TencentCloud\Cms\V20190321\CmsClient;
use TencentCloud\Cms\V20190321\Models\ImageModerationRequest;

class tencent_ims
{
    private $secret_id;
    private $secret_key;

    /**
     * 图片类型
     * @var string
     */
    private $image_type = [
        'image/png',
        'image/jpeg',
        'image/jpg',
        'image/gif',
        'image/bmp',
        'application/octet-stream',
    ];


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
     * 返回cos对象
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
     * 检测在媒体库上传的图片
     * @param $file
     * @return bool
     * @throws Exception
     */
    public function AuditImages($root_path, $images)
    {
        if (empty($images) || !is_array($images)) {
            DebugLog::writeDebugLog('debug', 'image not exist');
            return true;
        }
        foreach ($images as $image) {
            if ($image === "" || !file_exists($root_path . $image)) {
                DebugLog::writeDebugLog('debug', $image . 'image missing');
                continue;
            }

            if (!$this->imageModeration($root_path, $image)) {
                DebugLog::writeDebugLog('debug', $image . "tencentcloud image audit failed");
                return false;
            } else {
                continue;
            }
        }
        return true;
    }

    /**
     * 腾讯云图片检测
     * @param $IMSOptions
     * @param string $imgContent 图片内容
     * @return Exception|ImageModerationResponse|TencentCloudSDKException
     * @throws Exception
     */
    private function imageModeration($root_path, $image)
    {
        try {
            $client = $this->getClient();
            if (!($client instanceof CmsClient)) {
                DebugLog::writeDebugLog('debug', "create Client failed");
                return false;
            }
            $img_content = file_get_contents($root_path . $image);
            if (!$img_content && strlen($img_content) === 0) {
                DebugLog::writeDebugLog('debug', "image missing");
                return false;
            }
            $req = new ImageModerationRequest();
            $params['FileContent'] = base64_encode($img_content);

            $req->fromJsonString(json_encode($params, JSON_UNESCAPED_UNICODE));
            $response = $client->ImageModeration($req);
            //腾讯云图片内容安全检测接口返回异常，配置参数错误或服务异常
            if (!($response instanceof ImageModerationResponse)) {
                DebugLog::writeDebugLog('debug', "interface error");
                return false;
            }

            if ($response->getData()->EvilFlag === 0 || $response->getData()->EvilType === 100) {
                DebugLog::writeDebugLog('debug', "tencentcloud image audit pass through");
                return true;
            }
            return false;
        } catch (TencentCloudSDKException $e) {
            DebugLog::writeDebugLog('debug', "terminated by exception");
            return false;
        }
    }
}
