<?php
// +----------------------------------------------------------------------
// | [KyPHP System] Copyright (c) 2020 http://www.kuryun.com/
// +----------------------------------------------------------------------
// | [KyPHP] 并不是自由软件,你可免费使用,未经许可不能去掉KyPHP相关版权
// +----------------------------------------------------------------------
// | Author: fudaoji <fdj@kuryun.cn>
// +----------------------------------------------------------------------
/**
 * Created by PhpStorm.
 * Script Name: Orderaddon.php
 * Create: 2020/7/21 17:03
 * Description: 应用订单
 * Author: fudaoji<fdj@kuryun.cn>
 */

namespace app\system\controller;

use ky\Csv;

class Orderaddon extends Base
{
    /**
     * @var \app\common\model\OrderAddon
     */
    protected $model = null;
    public function initialize()
    {
        parent::initialize(); // TODO: Change the autogenerated stub
        $this->model = model('orderAddon');
    }

    /**
     * 导出订单
     * @throws \think\exception\DbException
     * Author: fudaoji<fdj@kuryun.cn>
     */
    function export(){
        $params = cookie('export_params');
        $data_list = $this->model->getAllJoin($params);
        $title = ['订单号','应用','会员','总金额','实付金额', '下单时间', '支付状态','支付时间'];
        $paids = $this->model->paids();
        $data = [];
        foreach ($data_list as $v){
            $data[] = [
                'order_no' => $v['order_no'],
                'addon' => $v['name'],
                'username' => $v['username'].'('.$v['mobile'].')',
                'total' => number_format($v['total']/100, 2, '.', ''),
                'amount' => number_format($v['amount']/100, 2, '.', ''),
                'create_time' => date('Y-m-d H:i:s', $v['create_time']),
                'paid' => $paids[$v['paid']],
                'pay_time' => $v['pay_time'] ? date('Y-m-d H:i:s', $v['pay_time']) : ''
            ];
        }
        if(! count($data)){
            $this->error('无数据可导');
        }
        Csv::getInstance()->setFileName('应用订单'.time())->putCsv($data, $title);
    }

    /**
     * 订单列表
     * @return mixed
     * Author: fudaoji<fdj@kuryun.cn>
     * @throws \think\exception\DbException
     */
    public function index(){
        $search_key = input('search_key', '');
        $paid = input('paid', 1);
        $from_date = input('from_date', strtotime(date('Y-m-d')), 'strtotime');
        $to_date = input('to_date', time(), 'strtotime');
        $where = [
            'o.paid' => $paid,
            'o.uid' => $this->adminId,
            'o.create_time' => ['between', [$from_date, $to_date]]
        ];
        $search_key && $where['o.order_no|o.username|o.mobile|a.name'] = ['like', '%'.$search_key.'%'];

        $params = [
            'alias' => 'o',
            'join' => [
                ['addons a', 'a.addon = o.addon'],
                ['admin_addon aa', 'aa.addon = o.addon and aa.uid=o.uid']
            ],
            'page_size' => $this->pageSize,
            'where' => $where,
            'field' => 'o.*,a.name,aa.deadline'
        ];
        $data_list = $this->model->pageJoin($params);
        cookie('export_params', $params);

        $page = $data_list->appends([
            'search_key' => $search_key, 'paid' => $paid,
            'from_date' => date('Y-m-d H:i:s', $from_date),
            'to_date' => date('Y-m-d H:i:s', $to_date),
        ])->render();
        $assign = [
            'data_list' => $data_list,
            'page' => $page,
            'paid' => $paid,
            'search_key' => $search_key,
            'paids' => $this->model->paids(),
            'from_date' => date('Y-m-d H:i:s', $from_date),
            'to_date' => date('Y-m-d H:i:s', $to_date),
        ];
        return $this->show($assign);
    }
}