<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/2/15
 * Time: 10:13
 */

namespace app\controllers;

use app\common\behavior\NoCsrs;
use app\models\Admin;
use app\models\Aftersales;
use app\models\Aftersalesrefund;
use app\models\Order;
use app\models\Orderdetail;
use app\models\Shop;
use app\models\Shoporderprofitrate;
use app\models\Userrelshop;
use yii\web\Controller;
use Yii;

class ReportController extends Controller
{
    public function behaviors()
    {
        return [
            'csrf' => [
                'class' => NoCsrs::className(),
                'controller' => $this,
                'actions' => [
                    "orders",
                    "profit"

                ]
            ]
        ];
    }


    /*店东分润排名*/
    public function actionProfit(){
        Yii::$app->response->format = 'json';
        $curPage = 1;
        $where = [];
        $search = Yii::$app->request->post();
        if ($search) {
            foreach ($search as $k => $v) {
                if ($k == 'curpage') {
                    $curPage = $v;
                    continue;
                }

                if (trim($v)) {
                    if($k=='city'){
                        if($v=='全国'){
                            continue;
                        }
                        $val = explode(',',$v)[1];
                        $where[] = 'c.'.$k .'="' .   $val . '"';
                        continue;
                    }
                    $where[] = 'c.'.$k . 'like "%' . trim($v) . '%"';
                }
            }

        }
        //  return $where;
        if (Yii::$app->session->get('parent_admin_role_id') == '2') {
            //如果是市公司用户who_uploader in()市公司只能看到本市的;
            $who_uploaderIds = Admin::find()->where('area="' . Yii::$app->session->get('role_area') . '"')->asArray()->select('id')->all();
            $who_uploaderIds = array_column($who_uploaderIds, 'id');
            $where[] = 'admin_id in (' . implode(',', $who_uploaderIds) . ')';
            $citys = Admin::find()->select('admin.company_name,area') ->where('area="'.Yii::$app->session->get('role_area').'"')->distinct()->asArray()->all();
        }else{
            $citys = Admin::find()->select('admin.area,admin.company_name')->leftJoin('admin_role', 'admin.admin_role_id=admin_role.id')->distinct()->where('admin.status=1 and admin_role.parent_id=2 ')->asArray()->all();
            array_unshift($citys, ['label' => '全国', 'company_name' => '全国']);
        }
        $where[] = 'a.status !=2';
        if ($where) {
            $where = implode(' and ', $where);
        }
        $pageSize = Yii::$app->session->get('pagesize');
        $offset = $pageSize * ($curPage - 1);
        $query = new \yii\db\Query();
        $query->select("sum(a.profit_rate_amount) as amount ,c.* ")
            ->from('shop_order_profit_rate as a')
            ->leftJoin('shop as c', 'a.shop_id=c.id');



        if ($where) {
            $query = $query->where($where);
        }
        $rs = $query->groupBy('a.shop_id');
        $count = $rs->count();
        $rs = $rs->offset($offset)->limit($pageSize)->orderBy('amount desc')->all();
      // $totalPage = $query->count();
        return ['rs' => $rs, 'totalPage' => $count, 'pageSize' => $pageSize, 'search' => @$search, 'currpage' => $curPage, 'citys'=>$citys];


    }


    public function actionSuborders()
    {
        Yii::$app->response->format = 'json';
        $id = Yii::$app->request->get('id');
        $query = new \yii\db\Query();
        $rs = $query->select('a.*,b.pic')->from('order_detail as a')->leftJoin('item_sku as b', 'a.sku_id=b.id')->where('a.order_id=' . $id)->all();
        if ($rs) {
            foreach ($rs as $k => $v) {
                $rs[$k]['profit_rate'] = $v['profit_rate'] . '%';
            }
        }
        return $rs;


        // return Orderdetail::find()->where('order_id='.$id)->all();

    }

    public function getcityinfo($area){
        $userCount =0;
        $orderAmount = 0;
        $salesAmount = 0;
        $profitAmount = 0;
        $amount = 0;
        $useShop = 0;
        if($area=='all'){
            $shopInfo=  Shop::find()->where('city not in ("澳门特别行政区","新乡市","深圳市","花王堂区")')->asArray()->select('id,status')->all();
            $shopCount = count($shopInfo);
            //粉丝数量
            $shopIds = implode(',',array_column($shopInfo,'id'));
        }else{
            $shopInfo=  Shop::find()->where('area like "%'.$area.'%"')->asArray()->select('id,status')->all();
            $shopCount = count($shopInfo);
            //粉丝数量
            $shopIds = implode(',',array_column($shopInfo,'id'));

        }
        if($shopIds){
            foreach ($shopInfo as $k=>$v){
                if($v['status']!=2){
                    $useShop++;
                }
            }
            $userCount = Userrelshop::find()->where('is_checked=1 and shop_id in ('.$shopIds.')')->count();
            //订单成交金额
            $orderAmount = Order::find()->where('status not in ("WAIT_PAY","CANCEL_BY_USER","CANCEL_BY_SYSTEM") and shop_id in ('.$shopIds.')')->sum('payment');
            $salesAmount = Aftersales::find()->where('status in (0,1) and shop_id in('.$shopIds.')')->sum('amount');
            $orderAmount = $orderAmount?$orderAmount:0;
            $salesAmount= $salesAmount?$salesAmount:0;
            $amount = bcsub($orderAmount,$salesAmount,2);
            //分润金额
            $profitAmount = Shoporderprofitrate::find()->where('status=1 and shop_id in ('.$shopIds.')')->sum('profit_rate_amount');
            $profitAmount= $profitAmount?$profitAmount:0;
        }

      return  $rs = ['shopCount'=>$shopCount,'useShop'=>$useShop,'profitAmount'=>$profitAmount.'元','userCount'=>$userCount,'amount'=>$amount.'元'];


    }
    /*城市信息*/
    public function actionCityinfo(){
        Yii::$app->response->format = 'json';
        $city = Yii::$app->request->get('city');
       $area='';
        switch ($city){
            case "wx":
                $area = "320000:320200";
                break;
            case "qd":
                $area = "370000:370200";
                break;
            case "ws":
                $area = "650000:650100";
                break;
            case "sz":
                $area = "all";
                break;
        }



       return $this->getcityinfo($area);

    }
    /*查询店东信息*/
    public function actionShopinfo(){
        Yii::$app->response->format = 'json';
        $shopId = Yii::$app->request->get('id');
        $created = Yii::$app->request->get('created');
        $orderAmount = Order::find()->where('status not in ("WAIT_PAY","CANCEL_BY_USER","CANCEL_BY_SYSTEM") and shop_id='.$shopId)->sum('payment');
        $salesAmount = Aftersales::find()->where('status in (0,1) and shop_id='.$shopId )->sum('amount');
        $orderAmount = $orderAmount?$orderAmount:0;
        $salesAmount= $salesAmount?$salesAmount:0;
        $amount = bcsub($orderAmount,$salesAmount,2);

        //分润金额
        $profitAmount = Shoporderprofitrate::find()->where('status=1 and shop_id='.$shopId)->sum('profit_rate_amount');
        $profitAmount = $profitAmount?$profitAmount:0;
        //粉丝数量
        $userCount = Userrelshop::find()->where('is_checked=1 and shop_id='.$shopId)->count();
        $userCount = $userCount?$userCount:0;
        return ['amount'=>$amount.'元','created'=>date("Y-m-d H:i:s",$created),'profitAmount'=>$profitAmount.'元','usercount'=>$userCount];

    }

    /*根据area查询城市下所有的店东*/
    public function actionShopbyarea(){
        Yii::$app->response->format = 'json';
        $city = Yii::$app->request->get('city');
        $rs =[];
        switch ($city){
            case "wx":
                  $rs=  Shop::find()->where('area like "%'."320000:320200".'%"')->asArray()->all();
                break;
            case "qd":
                $rs=  Shop::find()->where('area like "%'."370000:370200".'%"')->asArray()->all();
                break;
            case "ws":
                $rs=  Shop::find()->where('area like "%'."650000:650100".'%"')->asArray()->all();
                break;
            case "sz":
                $rs=  Shop::find()->where('city not in ("澳门特别行政区","新乡市","深圳市","花王堂区") and id!=55')->asArray()->all();
                break;
        }
        return $rs;
    }

    private function getshopsbyarea($area){

    }

    //订单
    public function actionOrders()
    {
        Yii::$app->response->format = 'json';
        $where = [];
        $pageSize = Yii::$app->params['pagesize'];
        $curPage = 1;
        if (Yii::$app->request->isPost) {
            $search = Yii::$app->request->post();
            foreach ($search as $k => $v) {
                if ($k == 'curpage') {
                    $curPage = $v;
                    continue;
                }
                if ($search[$k]) {
                    //return $v;
                    if($k=='start_time'){
                        if($v[0]&&$v[1]){
                            $where[] = 'a.created_at>='.strtotime($v[0]);
                            $where[] = 'a.created_at<='.strtotime($v[1]);
                        }
                        continue;
                    }
                    if ($k == 'ordertitle') {
                        $where[] = ' a.title like "%' . trim($v) . '%"';
                        continue;
                    }
                    if ($k == 'id') {
                        $where[] = ' a.id=' . trim($v);
                        continue;
                    }
                    if ($k == 'shop_name') {
                        $where[] = ' c.shop_name like "%' . trim($v) . '%"';
                        continue;
                    }
                    if ($k == 'receiver_name') {
                        $where[] = ' a.receiver_name like "%' . trim($v) . '%"';
                        continue;
                    }
                    if ($k == 'status') {
                        $where[] = ' a.status="' . trim($v) . '"';
                        continue;
                    }
                    if($k=='pagesize'){
                        $pageSize = $v;
                        continue;
                    }
                }
            }
        }
        if (Yii::$app->session->get('parent_admin_role_id') == '2') {
            //如果是市公司用户who_uploader in()市公司只能看到本市的;
            $who_uploaderIds = Admin::find()->where('area="' . Yii::$app->session->get('role_area') . '"')->asArray()->select('id')->all();
            $who_uploaderIds = array_column($who_uploaderIds, 'id');
            $where[] = 'c.admin_id in (' . implode(',', $who_uploaderIds) . ')';

        }
        $where = $where ? implode(' and ', $where) : [];

        $query = new \yii\db\Query();

        $offset = $pageSize * ($curPage - 1);
        $query = $query->select("c.city ,a.id ,a.payment ,a.title ,a.quantity ,c.shop_name , a.status,b.profit_rate_amount ,a.created_at,b.status as orderCheck,a.receiver_name,a.mobile ")
            ->from('order as a')
            ->leftJoin('shop as c', 'a.shop_id=c.id')
            ->leftJoin('shop_order_profit_rate as b', 'a.id=b.order_id');

        if ($where) {
            $query = $query->where($where);
        }
        $totalPage = $query->count();
        $rs = $query->offset($offset)->limit($pageSize)->orderBy('a.created_at desc,a.id desc')->all();
        foreach ($rs as $k => $v) {
            $rs[$k]['created_at'] = date("Y-m-d H:i:s", $v['created_at']);
            switch ($v['orderCheck']) {
                case '0':
                    $rs[$k]['orderCheck'] = '待审核订单';
                    break;
                case '1':
                    $rs[$k]['orderCheck'] = '已审核订单';
                    break;

            }
            switch ($v['status']) {
                case 'WAIT_PAY':
                    $rs[$k]['status'] = '待付款';
                    break;
                case 'PAID':
                    $rs[$k]['status'] = '已付款';
                    break;
                case 'SHIPPED':
                    $rs[$k]['status'] = '已发货';
                    break;
                case 'IN_SHOP':
                    $rs[$k]['status'] = '商品到店';
                    break;
                case 'FINISHED':
                    $rs[$k]['status'] = '订单完成';
                    break;
                case 'CANCEL_BY_SYSTEM':
                    $rs[$k]['status'] = '系统取消订单';
                    break;
                case 'CANCEL_BY_USER':
                    $rs[$k]['status'] = '用户取消订单';
                    break;
                default:
                    $rs[$k]['status'] = '未知状态';
                    break;
            }
        }
        return ['rs' => $rs, 'totalPage' => $totalPage, 'pageSize' => $pageSize, 'search' => @$search, 'currpage' => $curPage];


    }
}
