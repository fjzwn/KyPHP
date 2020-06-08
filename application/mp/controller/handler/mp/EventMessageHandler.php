<?php
/**
 * Created by PhpStorm.
 * Script Name: EventMessageHandler.php
 * Create: 2020/4/15 10:48
 * Description: 公众号事件消息处理器
 * Author: Jason<dcq@kuryun.cn>
 */
namespace app\mp\controller\handler\mp;

use app\common\controller\WechatMp;
use EasyWeChat\Kernel\Contracts\EventHandlerInterface;
use think\facade\Log;

class EventMessageHandler extends WechatMp implements EventHandlerInterface
{
    /**
     * @var \think\Model
     */
    private $mpFollowM;
    private $redis;

    /**
     * 初始化
     * @author Jason<dcq@kuryun.cn>
     */
    public function __construct() {
        parent::__construct();
        $this->mpFollowM = model('mpFollow');
    }

    /**
     * 处理器
     * @param null $payload
     * @return mixed
     * @author Jason<dcq@kuryun.cn>
     */
    public function handle($payload = null) {
        $method = camel_case('event_' . $payload['Event']);
        if(method_exists($this, $method)) {
            return call_user_func_array([$this, $method], [$payload]);
        }
        Log::write('无此事件处理方法:' . $method);
    }

    /**
     * 关注事件
     * @param $event
     * @return bool|mixed
     * @author: fudaoji<fdj@kuryun.cn>
     */
    public function eventSubscribe($event) {
        //消息订阅
        if($this->mpInfo['verify_type_info'] == -1){
            $data = [
                'mpid'          => $this->mpInfo['id'],
                'openid'        => $event['FromUserName'],
                'subscribe'     => 1,
                'subscribe_time' => time(),
            ];
        }else{
            $wx_user = $this->mpApp->user->get($event['FromUserName']);
            $data = [
                'mpid'     => $this->mpInfo['id'],
                'openid'        => $wx_user['openid'],
                'nickname'      => $wx_user['nickname'],
                'subscribe'     => $wx_user['subscribe'],
                'headimgurl'    => $wx_user['headimgurl'],
                'country'       => $wx_user['country'],
                'province'      => $wx_user['province'],
                'city'          => $wx_user['city'],
                'sex'           => $wx_user['sex'],
                'remark'        => $wx_user['remark'],
                'language'      => $wx_user['language'],
                'groupid'       => $wx_user['groupid'],
                'subscribe_time' => $wx_user['subscribe_time'],
                'tagid_list'    => json_encode($wx_user['tagid_list']),
                'subscribe_scene' => $wx_user['subscribe_scene'],
                'unionid' => isset($wx_user['unionid']) ?: '',
                'qr_scene' => isset($wx_user['qr_scene']) ?: '',
                'qr_scene_str' => isset($wx_user['qr_scene_str']) ?: '',
            ];
        }

        $follow = $this->mpFollowM->getOneByMap(['mpid' => $this->mpInfo['id'], 'openid' => $event['FromUserName']], true, 1);
        if($follow) {
            $data['id'] = $follow['id'];
            $this->mpFollowM->updateOne($data);
        }else {
            $this->mpFollowM->addOne($data);
        }
        //扫描带参数二维码事件
        if(strpos($event['EventKey'], 'qrscene_') !== false) {
            $event_key = str_replace('qrscene_', '', $event['EventKey']);
            $key_arr = explode('@', $event_key);
            if(!empty($key_arr) && count($key_arr) < 2) {
                Log::write('约定二维码场景值应由处理方法名@场景值构成');
                return false;
            }
            $method = camel_case($key_arr[0]);
            if(method_exists($this, $method)) {
                return call_user_func_array([$this, $method], [$event]);
            }
            Log::write('无此事件处理方法:' . $method);
        }
    }

    /**
     * 取消订阅
     * @param $event
     * @author Jason<dcq@kuryun.cn>
     */
    public function eventUnsubscribe($event) {
        $follow = $this->mpFollowM->getOneByMap(['openid' => $event['FromUserName'], 'mpid' => $this->mpInfo['id']], true, 1);
        if($follow){
            $this->mpFollowM->updateOne([
                'id'        => $follow['id'],
                'mpid'      => $this->mpInfo['id'],
                'subscribe' => 0,
                'unsubscribe_time' => time()
            ]);
        }
    }

    /**
     * 扫描带参二维码事件
     * @param $event
     * @return bool|mixed
     * @author: fudaoji<fdj@kuryun.cn>
     */
    public function eventSCAN($event) {
        $key_arr = explode('@', $event['EventKey']);
        if(!empty($key_arr) && count($key_arr) < 2) {
            Log::write('约定二维码场景值应由处理方法名@场景值构成');
            return false;
        }
        $method = camel_case($key_arr[0]);
        if(method_exists($this, $method)) {
            return call_user_func_array([$this, $method], [$event]);
        }
        Log::write('无此事件处理方法:' . $method);
    }

}