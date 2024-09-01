<?php

namespace App\Payments;

use App\Exceptions\ApiException;
use \Curl\Curl;
use Illuminate\Support\Facades\Log;

/**
 * 西瓜支付 - 默认阿里
 */
class XiGuaPay
{
    protected $config;
    public function __construct($config)
    {
        $this->config = $config;
    }

    public function form()
    {
        return [
            'url' => [
                'label' => 'URL',
                'description' => '',
                'type' => 'input',
            ],
            'uid' => [
                'label' => 'UID',
                'description' => '商户接入 ID',
                'type' => 'input',
            ],
            'key' => [
                'label' => 'KEY',
                'description' => '',
                'type' => 'input',
            ],
            'type' => [
                'label' => 'TYPE',
                'description' => '支付类型  alipay, weixin',
                'type' => 'input',
            ],

        ];
    }

    public function pay($order)
    {
        //支付信息
        $total_fee = number_format($order['total_amount'] / 100, 2, '.', '');
        $sign = md5('version=1.0&customerid='.$this->config['uid'].'&total_fee='.$total_fee.'&sdorderno='.$order['trade_no'].'&notifyurl='.$order['notify_url'].'&returnurl='.$order['return_url'].'&'.$this->config['key']);
        $params = [
            'get_code' => 1,
            'version' => '1.0',
            'customerid' => $this->config['uid'],
            'paytype' => $this->config['type'],
            'total_fee' => $total_fee,
            'notifyurl' => $order['notify_url'],
            'returnurl' => $order['return_url'],
            'sdorderno' => $order['trade_no'],
            'sign' => $sign,
        ];
        //日志
//        Log::channel('daily')->error($params);
        $curl = new Curl();
        //超时10秒
        $curl->setOpt(CURLOPT_TIMEOUT, 10);
        $curl->setHeader('Content-Type', 'application/x-www-form-urlencoded');
        $curl->post($this->config['url'].'/apisubmit', $params);
        $result = $curl->response;
        if (!$result) {
            throw new ApiException('网络异常,请稍后再试');
        }
        //$result json转对象
        $result = json_decode($result);
        if ($result->status != 1) {
            if($result->status == 205){
                throw new ApiException('如已完成支付，请勿重复支付联系客服，如果未完成支付，请取消后重新下单');
            }
            if (isset($result->msg)) {
                throw new ApiException($result->msg);
            }
            throw new ApiException('未知错误');
        }
        $curl->close();
        return [
            'type' => 0, // 0:qrcode 1:url
            'data' => $result->code_url
        ];

    }

    public function notify($params)
    {
//        Log::channel('daily')->error($params);
        $mysign = md5('customerid='.$params['customerid'].'&status='.$params['status'].'&sdpayno='.$params['sdpayno'].'&sdorderno='.$params['sdorderno'].'&total_fee='.$params['total_fee'].'&paytype='.$params['paytype'].'&'.$this->config['key']);
        if ($params['sign'] == $mysign) {
            if ($params['status'] == 1) {
                return [
                    'trade_no' => $params['sdorderno'],
                    'callback_no' => $params['sdpayno']
                ];
            }
        }
        return false;
    }
}
