<?php
/*
 * Copyright (c) 2023. Ankio.  由CleanPHP4强力驱动。
 */
/**
 * Package: app\objects\config
 * Class ChannelConfig
 * Created By ankio.
 * Date : 2023/7/18
 * Time : 13:23
 * Description :
 */

namespace app\objects\config;

use app\utils\ImageUpload;
use cleanphp\base\Request;
use cleanphp\base\Response;
use library\verity\VerityException;
use library\verity\VerityObject;
use library\verity\VerityRule;

class ChannelConfig extends VerityObject
{

    public string $key = "";
    public int $timeout = 0;
    public int $conflict = 1;
    public string $image_alipay  = "";
    public string $image_wechat  = "";

    /**
     * @throws VerityException
     */
    public function onParseType(string $key, mixed &$val, mixed $demo): bool
    {
        if(str_starts_with($key,"image_") && !str_starts_with($val,Request::getAddress())){
            $val  = url("api", "image", "qrcode", [
                'url' => $val,
                'type'=>'.png'
            ]);
        }
        $this->onParseTypeCheck($key, $val, $demo);
        return parent::onParseType($key, $val, $demo);
    }

    public function onMerge($key, $raw, &$val): void
    {
        if(str_starts_with($key,"image_")){
            if ($raw!==$val) {
                (new ImageUpload("channel"))->delImage($raw);
            }

        }
    }

    /**
     * @inheritDoc
     */
    function getRules(): array
    {
        return [
            "key"=>new VerityRule("^\w{32}$","AppKey必须为32位字符串",false),
            "timeout"=>new VerityRule("^\d+$","时间必须为整数",false),
            "conflict"=>new VerityRule("^1|2$","重复订单处理方案错误",false),
            "image_alipay|image_wechat"=>new VerityRule("","图片不允许为空",false)
        ];
    }
}