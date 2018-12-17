<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/1/25 0025
 * Time: 下午 12:16
 */

namespace app\controllers;

use app\common\behavior\NoCsrs;
use app\models\Ad;
use app\models\Admin;
use app\models\Order;
use app\models\Orderdetail;
use app\models\Role;
use app\models\Shop;
use app\models\Shoporderprofitrate;
use app\models\Ycypuser;
use yii\web\Controller;
use Yii;

class CitycmpController extends Controller
{

    public function behaviors()
    {
        return [
            'csrf' => [
                'class' => NoCsrs::className(),
                'controller' => $this,
                'actions' => [
                    'list',
                    'add',
                    'repasswd',
                    'headadd',
                    'head',
                    'edit',
                    'editsave',
                    'newhome',
                    'selectcity',
                    'searchcitydate',
                    'curtoday',
                    'searchtodayorderamount',
                    'todayamountdetail'

                ]
            ]
        ];
    }

    /*日订单金额详情*/
    public function actionTodayamountdetail(){
        Yii::$app->response->format = 'json';
        if(Yii::$app->request->isPost){
            $post = Yii::$app->request->post();
            if(!$post['today']){
                return [];
            }
            $searchTime='';
            foreach ($post as $k=>$v){
                if($k=='city'&&$v=='全国'){
                    continue;
                }
                if($v){
                    if($k=='city'){
                        $where[] = 'shop.area like "%'.$v.'%"';
                        continue;
                    }
                    if($k=='today'){
                        $searchTime = $v;
                        continue;
                    }
                }
            }
            $day = strtotime($searchTime);
            $tomorrow =strtotime(date("Y-m-d",$day+3600*24));
            $where[] = ' a.created_at>='.$day;
            $where[] = ' a.created_at<'.$tomorrow;
            $where = implode(' and ',$where);

            $time = [];
            $query = new \yii\db\Query();

                $order =  $query->select('a.id as order_id,a.receiver_name,shop.shop_name,a.mobile,shop.city,a.amount,a.payment,a.discount,a.title,a.promotion_type,a.refund_amount,a.status,a.created_at,a.paid_at')
                    ->from('order as a')
                    ->leftJoin('payment_detail as b','a.id=b.order_id')
                    ->leftJoin('shop','shop.id=a.shop_id')
                    ->where($where)
                    ->all();
                if($order){
                    foreach ($order as $k=>$v){
                        $order[$k]['created_at'] = date("Y-m-d H:i:s",$v['created_at']);
                        $order[$k]['paid_at'] = $v['paid_at']?date("Y-m-d H:i:s",$v['paid_at']):'';
                        switch ($v['promotion_type']){
                            case "preorder":
                                $order[$k]['promotion_type'] ="预售";
                                break;
                            case "groupon":
                                $order[$k]['promotion_type'] ="团购";
                                break;
                        }
                        switch ($v['status']) {
                            case 'WAIT_PAY':
                                $order[$k]['status'] = '待付款';
                                break;
                            case 'PAID':
                                $order[$k]['status'] = '已付款';
                                break;
                            case 'SHIPPED':
                                $order[$k]['status'] = '已发货';
                                break;
                            case 'IN_SHOP':
                                $order[$k]['status'] = '商品到店';
                                break;
                            case 'FINISHED':
                                $order[$k]['status'] = '订单完成';
                                break;
                            case 'CANCEL_BY_SYSTEM':
                                $order[$k]['status'] = '系统取消订单';
                                break;
                            case 'CANCEL_BY_USER':
                                $order[$k]['status'] = '用户取消订单';
                                break;
                            default:
                                $order[$k]['status'] = '未知状态';
                                break;
                        }
                    }
                }
            return $order;
        }
    }

    /*日订单金额搜索*/
    public function actionSearchtodayorderamount(){
        Yii::$app->response->format = 'json';
        set_time_limit(60);
        if(Yii::$app->request->isPost){
            $search = Yii::$app->request->post();
            if(!$search){
                return ['rs'=>[],'todayInfo'=>''];
            }
            $searchArea = '';
            $searchTime='';
            foreach ($search as $k=>$v){
                if($k=='city'&&$v=='全国'){
                    continue;
                }
                if($v){
                    if($k=='city'){
                        $searchArea = $v;
                        continue;
                    }
                    if($k=='today'){
                        $searchTime = $v;
                        continue;
                    }
                }
            }
            $day = strtotime($searchTime);
            $tomorrow =strtotime(date("Y-m-d",$day+3600*24));
            $time = [];
            $query = new \yii\db\Query();
            if(Yii::$app->session->get('parent_admin_role_id')==2){
                //如果是市公司
                $area = Yii::$app->session->get('role_area');
            }else{
                if($searchArea){
                    $order  =  $query->select('a.created_at,a.payment')->from('order as a')->leftJoin('payment_detail as b','a.id=b.order_id')->leftJoin('shop','shop.id=a.shop_id')->where('a.created_at>='.$day.' and a.created_at<'.$tomorrow.' and shop.area like "%'.$searchArea.'%"')->all();
                }else{
                    //24小时统计
                    $order  =  $query->select('a.created_at,a.payment')->from('order as a')->leftJoin('payment_detail as b','a.id=b.order_id')->where('a.created_at>='.$day.' and a.created_at<'.$tomorrow)->all();
                }
            }

            $rs = [];
            if($order){
                for($i=0;$i<25;$i++){
                    $time[] = $day + $i*3600-1;
                }
                foreach ($time as $k=>$v){
                    if(@$time[$k+1]){
                        foreach ($order as $ok=>$ov){
                            if($ov['created_at']>$v&&$ov['created_at']<$time[$k+1]){
                                @$rs[$k] = @$rs[$k]?@$rs[$k]:0;
                                @$rs[$k] = bcadd($ov['payment'],$rs[$k],2);
                            }else{
                                @$rs[$k] = @$rs[$k]?@$rs[$k]:0;
                            }
                        }
                    }
                }

            }
            $todayInfo = array_sum($rs);
            return ['rs'=>$rs,'todayInfo'=>$todayInfo,'curToday'=>date('Y-m-d',$day)];

        }



    }

    /* 今日数据统计*/
    public function actionCurtoday(){
        Yii::$app->response->format = 'json';
        set_time_limit(60);
        $searchArea = '';
        $searchTime ='';
        if(Yii::$app->request->isPost){
             $search = Yii::$app->request->post();
             foreach ($search as $k=>$v){
                 if($k=='city'&&$v=='全国'){
                     continue;
                 }
                 if($v){
                    if($k=='city'){
                        $searchArea = $v;
                        continue;
                    }
                    if($k=='today'){
                        $searchTime = $v;
                        continue;
                    }
                 }
             }
        }

        $day = $searchTime?strtotime($searchTime):strtotime(date("Y-m-d"));
        $tomorrow =strtotime(date("Y-m-d",$day+3600*24));

        $month  = strtotime(date("Y-m"));
        $monthDays =  date("t",$month);
        $nextMonth = strtotime(date("Y-m",strtotime("next month")));

        $time = [];
        $citys = [];
        $isCity = false;

        $query = new \yii\db\Query();
        $area ='';

            if(Yii::$app->session->get('parent_admin_role_id')==2){
                //如果是市公司
                $isCity = true;
                $area = Yii::$app->session->get('role_area');
                $citys = Admin::find()->select('city,area') ->where('area="'.$area.'"')->distinct()->asArray()->all();
            }else{
                $citys = Admin::find()->select('admin.area,admin.city')->leftJoin('admin_role', 'admin.admin_role_id=admin_role.id')->distinct()->where('admin.status=1 and admin_role.parent_id=2 ')->asArray()->all();
                array_unshift($citys, ['city' => '全国', 'area' => '全国']);
                $area = '全国';
              if($searchArea){
                  $order  =  $query->select('a.created_at,a.payment')->from('order as a')->leftJoin('payment_detail as b','a.id=b.order_id')->leftJoin('shop','shop.id=a.shop_id')->where('a.created_at>='.$day.' and a.created_at<'.$tomorrow.' and shop.area like "%'.$searchArea.'%"')->all();
              }else{
                  //24小时统计
                  $order  =  $query->select('a.created_at,a.payment')->from('order as a')->leftJoin('payment_detail as b','a.id=b.order_id')->where('a.created_at>='.$day.' and a.created_at<'.$tomorrow)->all();
              }

              //月统计
                $query = new \yii\db\Query();
                $monthOrder='';
               // $monthOrder  =  $query->select('a.created_at,a.payment')->from('order as a')->leftJoin('payment_detail as b','a.id=b.order_id')->where('a.created_at>='.$month.' and a.created_at<'.$nextMonth)->all();

            }
            $rs = [];
            if($order){
                for($i=0;$i<25;$i++){
                    $time[] = $day + $i*3600-1;
                }
                  foreach ($time as $k=>$v){
                      if(@$time[$k+1]){
                          foreach ($order as $ok=>$ov){
                              if($ov['created_at']>$v&&$ov['created_at']<$time[$k+1]){
                                  @$rs[$k] = @$rs[$k]?@$rs[$k]:0;
                                  @$rs[$k] = bcadd($ov['payment'],$rs[$k],2);
                              }else{
                                  @$rs[$k] = @$rs[$k]?@$rs[$k]:0;
                              }
                          }
                      }
                  }

            }
            $mo =[];
            if($monthOrder){
                $months = [];

               for($i=0;$i<$monthDays;$i++){
                   $months[] = $month+$i*3600*24-1;
               }
               foreach ($months as $k=>$v){
                  // if(@$months[$k+1]){
                       foreach ($monthOrder as $ok=>$ov){
                           if($ov['created_at']>$v&&$ov['created_at']<$months[$k+1]){
                               @$mo[$k] = @$mo[$k]?@$mo[$k]:0;
                               @$mo[$k] = bcadd($ov['payment'], @$mo[$k],2);
                           }else{
                               @$mo[$k] = @$mo[$k]?@$mo[$k]:0;
                           }
                       }
                  // }

               }


            }
            $todayInfo =array_sum($rs);
            return ['rs'=>$rs,'todayInfo'=>$todayInfo,'citys'=>$citys,'isCity'=>$isCity,'area'=>$area,'curToday'=>date('Y-m-d',$day),'monthOrder'=>$mo,'curMonth'=>date("Y-m",$month)];

    }


    /*总公司用户添加*/
    public function actionHeadadd()
    {
        Yii::$app->response->format = 'json';
        if (Yii::$app->request->isGet) {
            return Role::find()->asArray()->where('parent_id=1')->all();
        }
        if (Yii::$app->request->isPost) {
            $post = Yii::$app->request->post();
            $model = new Admin();
            $model->load($post, 'admin', false);
            $model->password = hash('sha256', $model->password . Yii::$app->params['passwordkey'], false);
            $model->company_name = '总部';
            $model->area = '';
            $model->created_at = time();
            $model->updated_at = time();
            if ($model->save(false)) {
                return ['rs' => "true"];
            } else {
                return ['rs' => "false", 'msg' => '添加失败'];
            }


        }

    }

    public function actionAdd()
    {
        Yii::$app->response->format = 'json';
        $post = Yii::$app->request->post();
        $model = new Admin();
        $model->load($post, 'admin', false);

        $model->password = hash('sha256', $model->password . Yii::$app->params['passwordkey'], false);
        $districtCode = $model->company_name;

        $model->company_name = implode(',', array_column($model->company_name, 'name'));
        $city = explode(',',$model->company_name);
        $model->city = $city[1];
        $model->area = implode(':', array_column($districtCode, 'code'));
        // $model->admin_role_id=2;
        $model->created_at = time();
        $model->updated_at = time();
        if ($model->save(false)) {
            return ['rs' => "true"];
        } else {
            return ['rs' => "false", 'msg' => '添加失败'];
        }
        return $model;

    }

    private function weekday($time)
    {
        $n = date("N", $time);
        $now = time();
        if ($n == 1) {
            return ['start' => $time, 'end' => $now];
        } else {
            $n = -($n - 1);
            return ['start' => strtotime(" $n day") - ($now - $time), 'end' => $now];
        }

    }


    private function month($time)
    {
        $days = date("t", $time);
        $today = date("j", $time);
        $now = time();
        if ($today == 1) {
            return ['start' => $time, 'end' => $now];
        } else {
            $n = -($today - 1);
            return ['start' => strtotime(" $n day") - ($now - $time), 'end' => $now];
        }


    }


    /*后台首页根据城市和日期进行搜索*/
    public function actionSearchcitydate()
    {
        Yii::$app->response->format = 'json';
        $post = Yii::$app->request->post();
        if ( !$post['times'][0]) {
            return ['rs' => 'false', 'msg' => '查询参数不能为空'];
        }

        if (!@$post['times'][0]) {
            unset($post['times']);
        }
        $area = @$post['city']?@$post['city']:"";
        $timerange = @$post['times'] ? @$post['times'] : '';
        //订单数量
        $orderCount = 0;
        //订单金额
        $orderAmount = 0;
        //新增买家
        $guests = 0;
        //店东分润
        $profit = 0;
        //新增店东
        $shopCount = 0;
        //商品top5
        $goods = [];

        if ($area && $timerange) {
            $orderCount = Order::find()->where('order.created_at>=' . strtotime($timerange[0]) . ' and order.created_at<' . strtotime($timerange[1]) . ' and shop.area like "%' . $area . '%"')->leftJoin('shop', 'shop.id=order.shop_id ')->count();

            $orderAmount = Order::find()->where('payment_detail.status=1 and order.created_at>=' . strtotime($timerange[0]) . ' and order.created_at<' . strtotime($timerange[1]) . ' and shop.area like "%' . $area . '%"')->leftJoin("payment_detail",'payment_detail.order_id=order.id')->leftJoin('shop', 'shop.id=order.shop_id ')->sum('order.payment');
            $guests = Ycypuser::find()->where('type="guest" and created_at>=' . strtotime($timerange[0]) . ' and created_at<' . strtotime($timerange[1]) . ' and area like "%' . $area . '%"')->count();
            $profit = Shoporderprofitrate::find()->where('shop_order_profit_rate.created_at>=' . strtotime($timerange[0]) . ' and shop_order_profit_rate.created_at<' . strtotime($timerange[1]) . ' and  shop.area like "%' . $area . '%"')->leftJoin("shop", 'shop.id=shop_order_profit_rate.shop_id')->sum('shop_order_profit_rate.profit_rate_amount');
            $shopCount = Shop::find()->where('created_at>=' . strtotime($timerange[0]) . ' and created_at< ' . strtotime($timerange[1]) . ' and area like "%' . $area . '%"')->count();
            $goods = Yii::$app->db->createCommand('select  sum(a.quantity) as num,b.title from shop left join  `order` as a on a.shop_id=shop.id left join order_detail as b on a.id=b.order_id    where a.status in ("FINISHED","IN_SHOP","PAID","SHIPPED") and a.created_at>=' . strtotime($timerange[0]) . ' and a.created_at< ' . strtotime($timerange[1]) . ' and shop.area like "%' . $area . '%" group by b.sku_id order by num desc limit 5 ')->queryAll();

        } elseif (!$area && $timerange) {
            $orderCount = Order::find()->where('order.created_at>=' . strtotime($timerange[0]) . ' and order.created_at<' . strtotime($timerange[1]))->leftJoin('shop', 'shop.id=order.shop_id ')->count();

            $orderAmount = Order::find()->where('payment_detail.status=1 and order.created_at>=' . strtotime($timerange[0]) . ' and order.created_at<' . strtotime($timerange[1]))->leftJoin('shop', 'shop.id=order.shop_id ')->leftJoin("payment_detail",'payment_detail.order_id=order.id')->sum('order.payment');
            $guests = Ycypuser::find()->where('type="guest" and created_at>=' . strtotime($timerange[0]) . ' and created_at<' . strtotime($timerange[1]))->count();
            $profit = Shoporderprofitrate::find()->where('shop_order_profit_rate.created_at>=' . strtotime($timerange[0]) . ' and shop_order_profit_rate.created_at<=' . strtotime($timerange[1]))->leftJoin("shop", 'shop.id=shop_order_profit_rate.shop_id')->sum('shop_order_profit_rate.profit_rate_amount');
            $shopCount = Shop::find()->where('created_at>=' . strtotime($timerange[0]) . ' and created_at< ' . strtotime($timerange[1]))->count();

            $goods = Yii::$app->db->createCommand('select  sum(a.quantity) as num,b.title from shop left join  `order` as a on a.shop_id=shop.id left join order_detail as b on a.id=b.order_id    where a.status in ("FINISHED","IN_SHOP","PAID","SHIPPED") and a.created_at>=' . strtotime($timerange[0]) . ' and a.created_at< ' . strtotime($timerange[1]) . '  group by b.sku_id order by num desc limit 5 ')->queryAll();

        } elseif ($area && !$timerange) {
            $orderCount = Order::find()->where(' shop.area like "%' . $area . '%"')->leftJoin('shop', 'shop.id=order.shop_id ')->count();

            $orderAmount = Order::find()->where('payment_detail.status=1 and shop.area like "%' . $area . '%"')->leftJoin('shop', 'shop.id=order.shop_id ')->leftJoin("payment_detail",'payment_detail.order_id=order.id')->sum('order.payment');
            $guests = Ycypuser::find()->where('type="guest" and area like "%' . $area . '%"')->count();
            $profit = Shoporderprofitrate::find()->where('  shop.area like "%' . $area . '%"')->leftJoin("shop", 'shop.id=shop_order_profit_rate.shop_id')->sum('shop_order_profit_rate.profit_rate_amount');
            $shopCount = Shop::find()->where(' area like "%' . $area . '%"')->count();

            $goods = Yii::$app->db->createCommand('select  sum(a.quantity) as num,b.title from shop left join  `order` as a on a.shop_id=shop.id left join order_detail as b on a.id=b.order_id    where  a.status in ("FINISHED","IN_SHOP","PAID","SHIPPED") and shop.area like "%' . $area . '%" group by b.sku_id order by num desc limit 5 ')->queryAll();

        }


        return [
            'rs' => 'true',
            'shu' => [
                ['type' => '订单数量', 'nums' => $orderCount?$orderCount:0, 'shop' => @$goods[0]['title'], 'title' => intval(@$goods[0]['num'])],
                ['type' => '交易金额', 'nums' => $orderAmount?$orderAmount:0, 'shop' => @$goods[1]['title'], 'title' => intval(@$goods[1]['num'])],
                ['type' => '新增買家', 'nums' => $guests?$guests:0, 'shop' => @$goods[2]['title'], 'title' => intval(@$goods[2]['num'])],
                ['type' => '店东分润', 'nums' => $profit?$profit:0, 'shop' => @$goods[3]['title'], 'title' => intval(@$goods[3]['num'])],
                ['type' => '新增店東', 'nums' => $shopCount?$shopCount:0, 'shop' => @$goods[4]['title'], 'title' => intval(@$goods[4]['num'])]
            ]

        ];


    }

    /* 城市变换时返回的统计数据 type1 guest,2shoper,3order,4profit,5goods*/
    public function actionSelectcity()
    {
        Yii::$app->response->format = 'json';
        $get = Yii::$app->request->post();
        $type = $get['type'];
        $area = $get['area'];//areacode

        $time = strtotime(date("Y-m-d"));
        $weektime = $this->weekday($time);
        $monthtime = $this->month($time);
        $rs = [];
        switch ($type) {
            case 1:
                if ($area) {
                    $rs['todayGuest'] = Ycypuser::find()->where('type="guest" and created_at>=' . $time . ' and area like "%' . $area . '%"')->count();
                    //本周新增买家
                    $rs['weekGuest'] = Ycypuser::find()->where('type="guest" and created_at>=' . $weektime['start'] . ' and created_at<=' . $weektime['end'] . ' and area like "%' . $area . '%"')->count();
                    //本月新增买家
                    $rs['monthGuest'] = Ycypuser::find()->where('type="guest" and created_at>=' . $monthtime['start'] . ' and created_at<=' . $monthtime['end'] . ' and area like "%' . $area . '%"')->count();
                    //该市买家总数
                    $rs['allGuest'] = Ycypuser::find()->where('type="guest" and   area like "%' . $area . '%"')->count();
                } else {
                    //今日新增买家
                    $rs['todayGuest'] = Ycypuser::find()->where('type="guest" and created_at>=' . $time)->count();
                    //本周新增买家
                    $rs['weekGuest'] = Ycypuser::find()->where('type="guest" and created_at>=' . $weektime['start'] . ' and created_at<=' . $weektime['end'])->count();
                    //本月新增买家
                    $rs['monthGuest'] = Ycypuser::find()->where('type="guest" and created_at>=' . $monthtime['start'] . ' and created_at<=' . $monthtime['end'])->count();
                    //买家总数
                    $rs['allGuest'] = Ycypuser::find()->where('type="guest" ')->count();

                }

                $rs['allGuest'] = $rs['allGuest'] ? $rs['allGuest'] : 0;
                $rs['monthGuest'] = $rs['monthGuest'] ? $rs['monthGuest'] : 0;
                $rs['weekGuest'] = $rs['weekGuest'] ? $rs['weekGuest'] : 0;
                $rs['todayGuest'] = $rs['todayGuest'] ? $rs['todayGuest'] : 0;
                break;
            case 2:
                if ($area) {
                    $rs['todayShoper'] = Shop::find()->where('created_at>=' . $time . ' and area like "%' . $area . '%"')->count();
                    //本周新增店东
                    $rs['weekShoper'] = Shop::find()->where('created_at>=' . $weektime['start'] . ' and created_at<= ' . $weektime['end'] . ' and area like "%' . $area . '%"')->count();
                    //本月新增店东
                    $rs['monthShoper'] = Shop::find()->where('created_at>=' . $monthtime['start'] . ' and created_at<= ' . $monthtime['end'] . ' and area like "%' . $area . '%"')->count();
                    //所有店东
                    $rs['allShoper'] = Shop::find()->where('  area like "%' . $area . '%"')->count();
                } else {

                    //今日新增店东
                    $rs['todayShoper'] = Shop::find()->where('created_at>=' . $time)->count();

                    //本周新增店东
                    $rs['weekShoper'] = Shop::find()->where('created_at>=' . $weektime['start'] . ' and created_at<= ' . $weektime['end'])->count();

                    //本月新增店东
                    $rs['monthShoper'] = Shop::find()->where('created_at>=' . $monthtime['start'] . ' and created_at<= ' . $monthtime['end'])->count();
                    //所有店东
                    $rs['allShoper'] = Shop::find()->where('id>=1')->count();

                }

                $rs['allShoper'] = $rs['allShoper'] ? $rs['allShoper'] : 0;
                $rs['monthShoper'] = $rs['monthShoper'] ? $rs['monthShoper'] : 0;
                $rs['weekShoper'] = $rs['weekShoper'] ? $rs['weekShoper'] : 0;
                $rs['todayShoper'] = $rs['todayShoper'] ? $rs['todayShoper'] : 0;
                break;
            case 3:
                if ($area) {
                    //今日订单数量
                    $rs['todayOrder'] = Order::find()->where('order.created_at>=' . $time . ' and shop.area like "%' . $area . '%"')->leftJoin('shop', 'shop.id=order.shop_id ')->count();
                    //本周订单数量
                    $rs['weekOrder'] = Order::find()->where('order.created_at>=' . $weektime['start'] . ' and  order.created_at<=' . $weektime['end'] . ' and shop.area like "%' . $area . '%"')->leftJoin('shop', 'shop.id=order.shop_id ')->count();
                    //本月订单数量
                    $rs['monthOrder'] = Order::find()->where('order.created_at>=' . $monthtime['start'] . ' and order.created_at<=' . $monthtime['end'] . ' and shop.area like "%' . $area . '%"')->leftJoin('shop', 'shop.id=order.shop_id ')->count();
                    //所有订单数量
                    $rs['allOrder'] = Order::find()->where('  shop.area like "%' . $area . '%"')->leftJoin('shop', 'shop.id=order.shop_id ')->count();
                } else {

                    //今日订单数量
                    $rs['todayOrder'] = Order::find()->where('order.created_at>=' . $time)->leftJoin('shop', 'shop.id=order.shop_id ')->count();
                    //本周订单数量
                    $rs['weekOrder'] = Order::find()->where('order.created_at>=' . $weektime['start'] . ' and  order.created_at<=' . $weektime['end'])->leftJoin('shop', 'shop.id=order.shop_id ')->count();
                    //本月订单数量
                    $rs['monthOrder'] = Order::find()->where('order.created_at>=' . $monthtime['start'] . ' and order.created_at<=' . $monthtime['end'])->leftJoin('shop', 'shop.id=order.shop_id ')->count();
                    //所有订单数量
                    $rs['allOrder'] = Order::find()->where('order.id>=1')->leftJoin('shop', 'shop.id=order.shop_id ')->count();
                }

                $rs['allOrder'] = $rs['allOrder'] ? $rs['allOrder'] : 0;
                $rs['monthOrder'] = $rs['monthOrder'] ? $rs['monthOrder'] : 0;
                $rs['weekOrder'] = $rs['weekOrder'] ? $rs['weekOrder'] : 0;
                $rs['todayOrder'] = $rs['todayOrder'] ? $rs['todayOrder'] : 0;
                break;
            case 4:
                if ($area) {
                    //今日店东分润
                    $rs['todayProfitrate'] = Shoporderprofitrate::find()->where(' shop_order_profit_rate.created_at>=' . $time . ' and  shop.area like "%' . $area . '%"')->leftJoin("shop", 'shop.id=shop_order_profit_rate.shop_id')->sum('shop_order_profit_rate.profit_rate_amount');

                    //本周店东分润
                    $rs['weekProfitrate'] = Shoporderprofitrate::find()->where('shop_order_profit_rate.created_at>=' . $weektime['start'] . ' and shop_order_profit_rate.created_at<=' . $weektime['end'] . ' and  shop.area like "%' . $area . '%"')->leftJoin("shop", 'shop.id=shop_order_profit_rate.shop_id')->sum('shop_order_profit_rate.profit_rate_amount');

                    //本月店东分润
                    $rs['monthProfitrate'] = Shoporderprofitrate::find()->where(' shop_order_profit_rate.created_at>=' . $monthtime['start'] . ' and shop_order_profit_rate.created_at<=' . $monthtime['end'] . ' and shop.area like "%' . $area . '%"')->leftJoin("shop", 'shop.id=shop_order_profit_rate.shop_id')->sum('shop_order_profit_rate.profit_rate_amount');

                    //所有店东分润
                    $rs['allProfitrate'] = Shoporderprofitrate::find()->where(' shop.area like "%' . $area . '%"')->leftJoin("shop", 'shop.id=shop_order_profit_rate.shop_id')->sum('shop_order_profit_rate.profit_rate_amount');
                } else {
                    //今日店东分润
                    $rs['todayProfitrate'] = Shoporderprofitrate::find()->where(' shop_order_profit_rate.created_at>=' . $time)->leftJoin("shop", 'shop.id=shop_order_profit_rate.shop_id')->sum('shop_order_profit_rate.profit_rate_amount');

                    //本周店东分润
                    $rs['weekProfitrate'] = Shoporderprofitrate::find()->where('shop_order_profit_rate.created_at>=' . $weektime['start'] . ' and shop_order_profit_rate.created_at<=' . $weektime['end'])->leftJoin("shop", 'shop.id=shop_order_profit_rate.shop_id')->sum('shop_order_profit_rate.profit_rate_amount');

                    //本月店东分润
                    $rs['monthProfitrate'] = Shoporderprofitrate::find()->where(' shop_order_profit_rate.created_at>=' . $monthtime['start'] . ' and shop_order_profit_rate.created_at<=' . $monthtime['end'])->leftJoin("shop", 'shop.id=shop_order_profit_rate.shop_id')->sum('shop_order_profit_rate.profit_rate_amount');

                    //所有店东分润
                    $rs['allProfitrate'] = Shoporderprofitrate::find()->where('shop_order_profit_rate.id>=1')->leftJoin("shop", 'shop.id=shop_order_profit_rate.shop_id')->sum('shop_order_profit_rate.profit_rate_amount');
                }
                $rs['todayProfitrate'] = $rs['todayProfitrate'] ? $rs['todayProfitrate'] : 0;
                $rs['weekProfitrate'] = $rs['weekProfitrate'] ? $rs['weekProfitrate'] : 0;
                $rs['monthProfitrate'] = $rs['monthProfitrate'] ? $rs['monthProfitrate'] : 0;
                $rs['allProfitrate'] = $rs['allProfitrate'] ? $rs['allProfitrate'] : 0;
                break;

            case 5://商品

                if ($area) {
                    $rs['todayTop'] = Yii::$app->db->createCommand('select  sum(a.quantity) as num,b.title from shop left join `order` as a  on a.shop_id=shop.id   left join order_detail as b on a.id=b.order_id    where a.created_at>=' . $time . ' and shop.area like "%' . $area . '%" group by b.sku_id order by num desc limit 5 ')->queryAll();

                    //本周
                    $rs['weekTop'] = Yii::$app->db->createCommand('select  sum(a.quantity) as num,b.title from shop left join  `order` as a on a.shop_id=shop.id left join order_detail as b on a.id=b.order_id    where a.created_at>=' . $weektime['start'] . ' and a.created_at<= ' . $weektime['end'] . ' and shop.area like "%' . $area . '%" group by b.sku_id order by num desc limit 5 ')->queryAll();
                    //本月
                    $rs['monthTop'] = Yii::$app->db->createCommand('select  sum(a.quantity) as num,b.title from shop left join  `order` as a   on a.shop_id=shop.id left join order_detail as b on a.id=b.order_id  where a.created_at>=' . $monthtime['start'] . ' and a.created_at<= ' . $monthtime['end'] . ' and shop.area like "%' . $area . '%" group by b.sku_id order by num desc limit 5 ')->queryAll();

                    //全部
                    $rs['all'] = Yii::$app->db->createCommand('select  sum(a.quantity) as num,b.title from shop left join  `order` as a   on a.shop_id=shop.id  left join order_detail as b on a.id=b.order_id where shop.area like "%' . $area . '%" group by b.sku_id order by num desc limit 5 ')->queryAll();
                } else {

                    $rs['todayTop'] = Yii::$app->db->createCommand('select  sum(a.quantity) as num,b.title from `order` as a left join order_detail as b on a.id=b.order_id  where a.created_at>=' . $time . ' group by b.sku_id order by num desc limit 5 ')->queryAll();
                    //本周
                    $rs['weekTop'] = Yii::$app->db->createCommand('select  sum(a.quantity) as num,b.title from `order` as a left join order_detail as b on a.id=b.order_id  where a.created_at>=' . $weektime['start'] . ' and a.created_at<= ' . $weektime['end'] . ' group by b.sku_id order by num desc limit 5 ')->queryAll();
                    //本月
                    $rs['monthTop'] = Yii::$app->db->createCommand('select  sum(a.quantity) as num,b.title from `order` as a left join order_detail as b on a.id=b.order_id  where a.created_at>=' . $monthtime['start'] . ' and a.created_at<= ' . $monthtime['end'] . ' group by b.sku_id order by num desc limit 5 ')->queryAll();

                    //全部
                    $rs['all'] = Yii::$app->db->createCommand('select  sum(a.quantity) as num,b.title from `order` as a left join order_detail as b on a.id=b.order_id   group by b.sku_id order by num desc limit 5 ')->queryAll();

                }

                $rs['all'] = $rs['all'] ? $rs['all'] : [['num' => 0, 'title' => ''], ['num' => 0, 'title' => ''], ['num' => 0, 'title' => ''], ['num' => 0, 'title' => ''], ['num' => 0, 'title' => ''], ['num' => 0, 'title' => '']];

                $rs['monthTop'] = $rs['monthTop'] ? $rs['monthTop'] : [['num' => 0, 'title' => ''], ['num' => 0, 'title' => ''], ['num' => 0, 'title' => ''], ['num' => 0, 'title' => ''], ['num' => 0, 'title' => ''], ['num' => 0, 'title' => '']];;
                $rs['weekTop'] = $rs['weekTop'] ? $rs['weekTop'] : [['num' => 0, 'title' => ''], ['num' => 0, 'title' => ''], ['num' => 0, 'title' => ''], ['num' => 0, 'title' => ''], ['num' => 0, 'title' => ''], ['num' => 0, 'title' => '']];;
                $rs['todayTop'] = $rs['todayTop'] ? $rs['todayTop'] : [['num' => 0, 'title' => ''], ['num' => 0, 'title' => ''], ['num' => 0, 'title' => ''], ['num' => 0, 'title' => ''], ['num' => 0, 'title' => ''], ['num' => 0, 'title' => '']];

                foreach ($rs as $k => $v) {
                    $count = count($rs[$k]);
                    switch ($count) {
                        case 0:
                            $rs[$k][0] = ['num' => 0, 'title' => ''];
                            $rs[$k][1] = ['num' => 0, 'title' => ''];
                            $rs[$k][2] = ['num' => 0, 'title' => ''];
                            $rs[$k][3] = ['num' => 0, 'title' => ''];
                            $rs[$k][4] = ['num' => 0, 'title' => ''];
                            break;
                        case 1:
                            $rs[$k][1] = ['num' => 0, 'title' => ''];
                            $rs[$k][2] = ['num' => 0, 'title' => ''];
                            $rs[$k][3] = ['num' => 0, 'title' => ''];
                            $rs[$k][4] = ['num' => 0, 'title' => ''];
                            break;
                        case 2:
                            $rs[$k][2] = ['num' => 0, 'title' => ''];
                            $rs[$k][3] = ['num' => 0, 'title' => ''];
                            $rs[$k][4] = ['num' => 0, 'title' => ''];
                            break;
                        case 3:
                            $rs[$k][3] = ['num' => 0, 'title' => ''];
                            $rs[$k][4] = ['num' => 0, 'title' => ''];
                            break;
                        case 4:
                            $rs[$k][4] = ['num' => 0, 'title' => ''];
                            break;
                    }

                }

                break;
        }
        return ['rs' => $rs];

    }


    /*今日新增用户*/
    public function actionNewhome()
    {
        Yii::$app->response->format = 'json';
        $time = strtotime(date("Y-m-d"));
        $weektime = $this->weekday($time);
        $monthtime = $this->month($time);
        $citys = [];
        $guest = [];
        $shoper = [];
        $order = [];
        $profit = [];
        $goods = [];
        $amount = [];
        $roleId = Yii::$app->session->get('parent_admin_role_id');
        if ($roleId == '2') {
            //如果是市公司用户who_uploader in()市公司只能看到本市的;
            $area = Yii::$app->session->get('role_area');

            //今日交易额
            $amount['todayAmount'] = Order::find()->where('payment_detail.status=1 and order.created_at>=' .$time. ' and shop.area like "%' . $area . '%"')->leftJoin('shop', 'shop.id=order.shop_id ')->leftJoin("payment_detail",'payment_detail.order_id=order.id')->sum('order.payment');
            //本周交易额
          /*  $amount['weekAmount'] =  Order::find()->where('order.created_at>=' . $weektime['start'] . ' and order.created_at<=' . $weektime['end'] . ' and shop.area like "%' . $area . '%"')->leftJoin('shop', 'shop.id=order.shop_id ')->sum('order.payment');*/

            //本月交易额
          /*  $amount['monthAmount'] = Order::find()->where('order.created_at>=' . $monthtime['start'] . ' and order.created_at<=' . $monthtime['end'] . ' and shop.area like "%' . $area . '%"')->leftJoin('shop', 'shop.id=order.shop_id ')->sum('order.payment');*/
            //总金额
          /*  $amount['allAmount'] =  Order::find()->where( ' shop.area like "%' . $area . '%"')->leftJoin('shop', 'shop.id=order.shop_id ')->sum('order.payment');*/


            //今日新增买家
            $guest['todayGuest'] = Ycypuser::find()->where('type="guest" and created_at>=' . $time . ' and area like "%' . $area . '%"')->count();
            //本周新增买家
          /*  $guest['weekGuest'] = Ycypuser::find()->where('type="guest" and created_at>=' . $weektime['start'] . ' and created_at<=' . $weektime['end'] . ' and area like "%' . $area . '%"')->count();*/
            //本月新增买家
           /* $guest['monthGuest'] = Ycypuser::find()->where('type="guest" and created_at>=' . $monthtime['start'] . ' and created_at<=' . $monthtime['end'] . ' and area like "%' . $area . '%"')->count();*/
            //该市买家总数
          /*  $guest['allGuest'] = Ycypuser::find()->where('type="guest" and   area like "%' . $area . '%"')->count();*/

            //今日新增店东
          /*  $shoper['todayShoper'] = Shop::find()->where('created_at>=' . $time . ' and area like "%' .
                $area . '%"')->count();*/
            //本周新增店东
           /* $shoper['weekShoper'] = Shop::find()->where('created_at>=' . $weektime['start'] . ' and created_at<= ' . $weektime['end'] . ' and area like "%' . $area . '%"')->count();*/
            //本月新增店东
          /*  $shoper['monthShoper'] = Shop::find()->where('created_at>=' . $monthtime['start'] . ' and created_at<= ' . $monthtime['end'] . ' and area like "%' . $area . '%"')->count();*/
            //所有店东
          /*  $shoper['allShoper'] = Shop::find()->where('  area like "%' . $area . '%"')->count();*/

            //今日订单数量
            $order['todayOrder'] = Order::find()->where('order.created_at>=' . $time . ' and shop.area like "%' . $area . '%"')->leftJoin('shop', 'shop.id=order.shop_id ')->count();
            //本周订单数量
          /*  $order['weekOrder'] = Order::find()->where('order.created_at>=' . $weektime['start'] . ' and  order.created_at<=' . $weektime['end'] . ' and shop.area like "%' . $area . '%"')->leftJoin('shop', 'shop.id=order.shop_id ')->count();*/
            //本月订单数量
            /*$order['monthOrder'] = Order::find()->where('order.created_at>=' . $monthtime['start'] . ' and order.created_at<=' . $monthtime['end'] . ' and shop.area like "%' . $area . '%"')->leftJoin('shop', 'shop.id=order.shop_id ')->count();*/
            //所有订单数量
          /*  $order['allOrder'] = Order::find()->where('  shop.area like "%' . $area . '%"')->leftJoin('shop', 'shop.id=order.shop_id ')->count();*/

            //今日店东分润
            $profit['todayProfitrate'] = Shoporderprofitrate::find()->where(' shop_order_profit_rate.created_at>=' . $time . ' and  shop.area like "%' . $area . '%"')->leftJoin("shop", 'shop.id=shop_order_profit_rate.shop_id')->sum('shop_order_profit_rate.profit_rate_amount');

            //本周店东分润
           /* $profit['weekProfitrate'] = Shoporderprofitrate::find()->where('shop_order_profit_rate.created_at>=' . $weektime['start'] . ' and shop_order_profit_rate.created_at<=' . $weektime['end'] . ' and  shop.area like "%' . $area . '%"')->leftJoin("shop", 'shop.id=shop_order_profit_rate.shop_id')->sum('shop_order_profit_rate.profit_rate_amount');*/

            //本月店东分润
           /* $profit['monthProfitrate'] = Shoporderprofitrate::find()->where(' shop_order_profit_rate.created_at>=' . $monthtime['start'] . ' and shop_order_profit_rate.created_at<=' . $monthtime['end'] . ' and shop.area like "%' . $area . '%"')->leftJoin("shop", 'shop.id=shop_order_profit_rate.shop_id')->sum('shop_order_profit_rate.profit_rate_amount');*/

            //所有店东分润
           /* $profit['allProfitrate'] = Shoporderprofitrate::find()->where(' shop.area like "%' . $area . '%"')->leftJoin("shop", 'shop.id=shop_order_profit_rate.shop_id')->sum('shop_order_profit_rate.profit_rate_amount');*/


            $goods['todayTop'] = Yii::$app->db->createCommand('select  sum(a.quantity) as num,b.title from shop left join `order` as a  on a.shop_id=shop.id   left join order_detail as b on a.id=b.order_id    where a.status in ("FINISHED","IN_SHOP","PAID","SHIPPED") and  a.created_at>=' . $time . ' and shop.area like "%' . $area . '%" group by b.sku_id order by num desc limit 5 ')->queryAll();

            //本周
           /* $goods['weekTop'] = Yii::$app->db->createCommand('select  sum(a.quantity) as num,b.title from shop left join  `order` as a on a.shop_id=shop.id left join order_detail as b on a.id=b.order_id    where a.created_at>=' . $weektime['start'] . ' and a.created_at<= ' . $weektime['end'] . ' and shop.area like "%' . $area . '%" group by b.sku_id order by num desc limit 5 ')->queryAll();*/
            //本月
           /* $goods['monthTop'] = Yii::$app->db->createCommand('select  sum(a.quantity) as num,b.title from shop left join  `order` as a   on a.shop_id=shop.id left join order_detail as b on a.id=b.order_id  where a.created_at>=' . $monthtime['start'] . ' and a.created_at<= ' . $monthtime['end'] . ' and shop.area like "%' . $area . '%" group by b.sku_id order by num desc limit 5 ')->queryAll();*/

            //全部
           /* $goods['all'] = Yii::$app->db->createCommand('select  sum(a.quantity) as num,b.title from shop left join  `order` as a   on a.shop_id=shop.id  left join order_detail as b on a.id=b.order_id where shop.area like "%' . $area . '%" group by b.sku_id order by num desc limit 5 ')->queryAll();*/


        } else {

            //今日交易额
            $amount['todayAmount'] = Order::find()->leftJoin("payment_detail",'payment_detail.order_id=order.id')->where('payment_detail.status=1 and order.created_at>=' .$time)->sum('order.payment');
            //本周交易额
            /*$amount['weekAmount'] =  Order::find()->where('order.created_at>=' . $weektime['start'] . ' and order.created_at<=' . $weektime['end'] )->leftJoin('shop', 'shop.id=order.shop_id ')->sum('order.payment');*/

            //本月交易额
           /* $amount['monthAmount'] = Order::find()->where('order.created_at>=' . $monthtime['start'] . ' and order.created_at<=' . $monthtime['end'] )->leftJoin('shop', 'shop.id=order.shop_id ')->sum('order.payment');*/
            //总金额
         /*   $amount['allAmount'] =  Order::find()->where('order.id>1')->leftJoin('shop', 'shop.id=order.shop_id ')->sum('order.payment');*/


            //今日新增买家
            $guest['todayGuest'] = Ycypuser::find()->where('type="guest" and created_at>=' . $time)->count();
            //本周新增买家
          /*  $guest['weekGuest'] = Ycypuser::find()->where('type="guest" and created_at>=' . $weektime['start'] . ' and created_at<=' . $weektime['end'])->count();*/
            //本月新增买家
           /* $guest['monthGuest'] = Ycypuser::find()->where('type="guest" and created_at>=' . $monthtime['start'] . ' and created_at<=' . $monthtime['end'])->count();*/
            //买家总数
           /* $guest['allGuest'] = Ycypuser::find()->where('type="guest" ')->count();*/


            //今日新增店东
            /*$shoper['todayShoper'] = Shop::find()->where('created_at>=' . $time)->count();*/

            //本周新增店东
         /*   $shoper['weekShoper'] = Shop::find()->where('created_at>=' . $weektime['start'] . ' and created_at<= ' . $weektime['end'])->count();*/

            //本月新增店东
          /*  $shoper['monthShoper'] = Shop::find()->where('created_at>=' . $monthtime['start'] . ' and created_at<= ' . $monthtime['end'])->count();*/
            //所有店东
          /*  $shoper['allShoper'] = Shop::find()->where('id>=1')->count();*/

            //今日订单数量
            $order['todayOrder'] = Order::find()->where('order.created_at>=' . $time)->count();
            //本周订单数量
           /* $order['weekOrder'] = Order::find()->where('order.created_at>=' . $weektime['start'] . ' and  order.created_at<=' . $weektime['end'])->leftJoin('shop', 'shop.id=order.shop_id ')->count();*/
            //本月订单数量
           /* $order['monthOrder'] = Order::find()->where('order.created_at>=' . $monthtime['start'] . ' and order.created_at<=' . $monthtime['end'])->leftJoin('shop', 'shop.id=order.shop_id ')->count();*/
            //所有订单数量
           /* $order['allOrder'] = Order::find()->where('order.id>=1')->leftJoin('shop', 'shop.id=order.shop_id ')->count();*/

            //今日店东分润
            $profit['todayProfitrate'] = Shoporderprofitrate::find()->where(' shop_order_profit_rate.created_at>=' . $time)->sum('shop_order_profit_rate.profit_rate_amount');

            //本周店东分润
           /* $profit['weekProfitrate'] = Shoporderprofitrate::find()->where('shop_order_profit_rate.created_at>=' . $weektime['start'] . ' and shop_order_profit_rate.created_at<=' . $weektime['end'])->leftJoin("shop", 'shop.id=shop_order_profit_rate.shop_id')->sum('shop_order_profit_rate.profit_rate_amount');*/

            //本月店东分润
           /* $profit['monthProfitrate'] = Shoporderprofitrate::find()->where(' shop_order_profit_rate.created_at>=' . $monthtime['start'] . ' and shop_order_profit_rate.created_at<=' . $monthtime['end'])->leftJoin("shop", 'shop.id=shop_order_profit_rate.shop_id')->sum('shop_order_profit_rate.profit_rate_amount');*/

            //所有店东分润
           /* $profit['allProfitrate'] = Shoporderprofitrate::find()->where('shop_order_profit_rate.id>=1')->leftJoin("shop", 'shop.id=shop_order_profit_rate.shop_id')->sum('shop_order_profit_rate.profit_rate_amount');*/


            //商品今日top5,下单的不管有没有支付和取消

            $goods['todayTop'] = Yii::$app->db->createCommand('select  sum(a.quantity) as num,b.title from `order` as a left join order_detail as b on a.id=b.order_id  where a.status in ("FINISHED","IN_SHOP","PAID","SHIPPED") and a.created_at>=' . $time . ' group by b.sku_id order by num desc limit 5 ')->queryAll();
            //本周
         /*   $goods['weekTop'] = Yii::$app->db->createCommand('select  sum(a.quantity) as num,b.title from `order` as a left join order_detail as b on a.id=b.order_id  where a.created_at>=' . $weektime['start'] . ' and a.created_at<= ' . $weektime['end'] . ' group by b.sku_id order by num desc limit 5 ')->queryAll();*/
            //本月
         /*   $goods['monthTop'] = Yii::$app->db->createCommand('select  sum(a.quantity) as num,b.title from `order` as a left join order_detail as b on a.id=b.order_id  where a.created_at>=' . $monthtime['start'] . ' and a.created_at<= ' . $monthtime['end'] . ' group by b.sku_id order by num desc limit 5 ')->queryAll();*/

            //全部
         /*   $goods['all'] = Yii::$app->db->createCommand('select  sum(a.quantity) as num,b.title from `order` as a left join order_detail as b on a.id=b.order_id   group by b.sku_id order by num desc limit 5 ')->queryAll();*/



        }
        $profit['todayProfitrate'] = $profit['todayProfitrate'] ? $profit['todayProfitrate'] : 0;
        //$profit['weekProfitrate'] = $profit['weekProfitrate'] ? $profit['weekProfitrate'] : 0;
       // $profit['monthProfitrate'] = $profit['monthProfitrate'] ? $profit['monthProfitrate'] : 0;
        //$profit['allProfitrate'] = $profit['allProfitrate'] ? $profit['allProfitrate'] : 0;

       // $goods['all'] = $goods['all'] ? $goods['all'] : [['num' => 0, 'title' => ''], ['num' => 0, 'title' => ''], ['num' => 0, 'title' => ''], ['num' => 0, 'title' => ''], ['num' => 0, 'title' => ''], ['num' => 0, 'title' => '']];

       // $goods['monthTop'] = $goods['monthTop'] ? $goods['monthTop'] : [['num' => 0, 'title' => ''], ['num' => 0, 'title' => ''], ['num' => 0, 'title' => ''], ['num' => 0, 'title' => ''], ['num' => 0, 'title' => ''], ['num' => 0, 'title' => '']];;
       // $goods['weekTop'] = $goods['weekTop'] ? $goods['weekTop'] : [['num' => 0, 'title' => ''], ['num' => 0, 'title' => ''], ['num' => 0, 'title' => ''], ['num' => 0, 'title' => ''], ['num' => 0, 'title' => ''], ['num' => 0, 'title' => '']];;
        $goods['todayTop'] = $goods['todayTop'] ? $goods['todayTop'] : [['num' => 0, 'title' => ''], ['num' => 0, 'title' => ''], ['num' => 0, 'title' => ''], ['num' => 0, 'title' => ''], ['num' => 0, 'title' => ''], ['num' => 0, 'title' => '']];

     //   $order['allOrder'] = $order['allOrder'] ? $order['allOrder'] : 0;
       // $order['monthOrder'] = $order['monthOrder'] ? $order['monthOrder'] : 0;
      //  $order['weekOrder'] = $order['weekOrder'] ? $order['weekOrder'] : 0;
        $order['todayOrder'] = $order['todayOrder'] ? $order['todayOrder'] : 0;

        $amount['todayAmount'] =   $amount['todayAmount'] ?   $amount['todayAmount'] : 0;
      //  $amount['weekAmount'] = $amount['weekAmount']  ?  $amount['weekAmount']  : 0;
      //  $amount['monthAmount'] =  $amount['monthAmount'] ?  $amount['monthAmount'] : 0;
      //  $amount['allAmount'] = $amount['allAmount'] ?$amount['allAmount'] : 0;

       // $shoper['allShoper'] = $shoper['allShoper'] ? $shoper['allShoper'] : 0;
      //  $shoper['monthShoper'] = $shoper['monthShoper'] ? $shoper['monthShoper'] : 0;
      //  $shoper['weekShoper'] = $shoper['weekShoper'] ? $shoper['weekShoper'] : 0;
      //  $shoper['todayShoper'] = $shoper['todayShoper'] ? $shoper['todayShoper'] : 0;

       // $guest['allGuest'] = $guest['allGuest'] ? $guest['allGuest'] : 0;
      //  $guest['monthGuest'] = $guest['monthGuest'] ? $guest['monthGuest'] : 0;
       // $guest['weekGuest'] = $guest['weekGuest'] ? $guest['weekGuest'] : 0;
        $guest['todayGuest'] = $guest['todayGuest'] ? $guest['todayGuest'] : 0;

        foreach ($goods as $k => $v) {
            $count = count($goods[$k]);
            switch ($count) {
                case 0:
                    $goods[$k][0] = ['num' => 0, 'title' => ''];
                    $goods[$k][1] = ['num' => 0, 'title' => ''];
                    $goods[$k][2] = ['num' => 0, 'title' => ''];
                    $goods[$k][3] = ['num' => 0, 'title' => ''];
                    $goods[$k][4] = ['num' => 0, 'title' => ''];
                    break;
                case 1:
                    $goods[$k][1] = ['num' => 0, 'title' => ''];
                    $goods[$k][2] = ['num' => 0, 'title' => ''];
                    $goods[$k][3] = ['num' => 0, 'title' => ''];
                    $goods[$k][4] = ['num' => 0, 'title' => ''];
                    break;
                case 2:
                    $goods[$k][2] = ['num' => 0, 'title' => ''];
                    $goods[$k][3] = ['num' => 0, 'title' => ''];
                    $goods[$k][4] = ['num' => 0, 'title' => ''];
                    break;
                case 3:
                    $goods[$k][3] = ['num' => 0, 'title' => ''];
                    $goods[$k][4] = ['num' => 0, 'title' => ''];
                    break;
                case 4:
                    $goods[$k][4] = ['num' => 0, 'title' => ''];
                    break;
            }

        }
     //   $citys = Admin::find()->select('admin.area as value,admin.company_name as label ')->leftJoin('admin_role', 'admin.admin_role_id=admin_role.id')->groupBy('admin.company_name')->where('admin.status=1 and admin_role.parent_id=2 ')->asArray()->all();
        $citys = Admin::find()->select('admin.area as value,admin.city as label ')->where('status=1 and admin_role_id=9 ')->asArray()->all();
        array_unshift($citys, ['label' => '全国', 'value' => '']);
        return ['guest' => $guest, 'shoper' => $shoper, 'order' => $order, 'profit' => $profit, 'roleId' => $roleId, 'goods' => $goods, 'citys' => $citys,'area'=>Yii::$app->session->get('role_area'),'amount'=>$amount];


    }

    public function actionHeadedit()
    {
        Yii::$app->response->format = 'json';
        $roles = Role::find()->asArray()->where('parent_id=1')->all();
        $id = Yii::$app->request->get('id');
        $rs = Admin::findOne($id);
        return ['rs' => $rs, 'roles' => $roles];
    }

    /*编辑*/
    public function actionEdit()
    {
        Yii::$app->response->format = 'json';
        $roles = Role::find()->asArray()->where('parent_id=2')->all();
        $id = Yii::$app->request->get('id');
        $rs = Admin::findOne($id);
        $city = explode(',', $rs['company_name']);

        return ['rs' => $rs, 'city' => $city, 'roles' => $roles, 'adminroleid' => $rs->admin_role_id];
    }

    /*编辑保存*/
    public function actionEditsave()
    {
        Yii::$app->response->format = 'json';
        $post = Yii::$app->request->post();

        $adminModel = Admin::findOne($post['admin']['id']);
        $adminModel->updated_at = time();
        unset($adminModel->company_name, $adminModel->area, $adminModel->password);
        if (!($adminModel->load($post, 'admin', false) && $adminModel->save(false))) {
            return ['rs' => 'false', 'msg' => '更新失败'];
        }

        return ['rs' => 'true'];
    }

    public function actionRoles()
    {
        Yii::$app->response->format = 'json';
        return Role::find()->asArray()->where('parent_id=2')->all();

    }

    /*获取后台登录用户信息*/
    public function actionUserinfo()
    {
        Yii::$app->response->format = 'json';
        $userid = Yii::$app->session->get('admin_id');
        $passwd = Yii::$app->session->get('passwd');
        return ['userId' => $userid, 'passwd' => $passwd];
    }


    /*修改密码*/

    public function actionRepasswd()
    {
        Yii::$app->response->format = 'json';
        $post = Yii::$app->request->post();
        $adminModel = Admin::findOne($post['id']);
        $adminModel->password = hash('sha256', $post['passwd'] . Yii::$app->params['passwordkey'], false);
        $adminModel->updated_at = time();
        if (!$adminModel->save(false)) {
            return ['rs' => 'false'];
        }
        return ['rs' => 'true'];
    }

    /*总公司*/
    public function actionHead()
    {
        Yii::$app->response->format = 'json';
        $where[] = 'a.status in (1,2)';
        $where[] = 'b.parent_id =1';
        $curPage = 1;
        if (Yii::$app->session->get('parent_admin_role_id') != '1' && Yii::$app->session->get('username') != 'admin') {
            return ['rs' => [], 'totalPage' => 1, 'pageSize' => 1, 'search' => [], 'currpage' => $curPage];
        }
        if (Yii::$app->request->isPost) {
            $search = Yii::$app->request->post();
            foreach ($search as $k => $v) {
                if ($k == 'curpage') {
                    $curPage = $v;
                    continue;
                }
                if ($search[$k]) {
                    $where[] = $k . ' like "%' . trim($v) . '%"';
                }
            }
        }
        $where = implode(' and ', $where);
        $pageSize = Yii::$app->params['pagesize'];
        $offset = $pageSize * ($curPage - 1);
        $query = new \yii\db\Query();
        $query = $query->select('a.*,b.role_name')->from('admin as a')
            ->leftJoin('admin_role as b', ' a.admin_role_id=b.id')
            ->where($where);
        $totalPage = $query->count();
        $rs = $query->limit($pageSize)->offset($offset)->all();
        if($rs){
            foreach ($rs as $k=>$v){
                $rs[$k]['created_at'] = date("Y-m-d H:i:s",$v['created_at']);
                $rs[$k]['updated_at'] = date("Y-m-d H:i:s",$v['updated_at']);
            }
        }
        $enableAdd = false;
        $enableEdit = false;
        if (Yii::$app->session->get('username') != 'admin') {
            $aulist = explode('|', Yii::$app->session->get('authlist'));

            if (!in_array('headquarteradd', $aulist)) {
                $enableAdd = true;
            }
            if (!in_array('headquarteredit', $aulist)) {
                $enableEdit = true;
            }
        }
        return ['rs' => $rs, 'totalPage' => $totalPage, 'pageSize' => $pageSize, 'search' => @$search, 'currpage' => $curPage, 'auth' => ['enableAdd' => $enableAdd, 'enableEdit' => $enableEdit]];
    }

    public function actionList()
    {
        Yii::$app->response->format = 'json';
        $where[] = 'a.status in (1,2)';
        $where[] = 'b.parent_id =2';
        $curPage = 1;
        if (Yii::$app->session->get('parent_admin_role_id') == '2') {
            //如果是市公司用户who_uploader in()市公司只能看到本市的;
            $who_uploaderIds = Admin::find()->where('area="' . Yii::$app->session->get('role_area') . '"')->asArray()->select('id')->all();
            $who_uploaderIds = array_column($who_uploaderIds, 'id');
            $where[] = 'a.id in (' . implode(',', $who_uploaderIds) . ')';

        }
        if (Yii::$app->request->isPost) {
            $search = Yii::$app->request->post();
            foreach ($search as $k => $v) {
                if ($k == 'curpage') {
                    $curPage = $v;
                    continue;
                }
                if ($search[$k]) {
                    $where[] = $k . ' like "%' . trim($v) . '%"';
                }
            }
        }
        $pageSize = Yii::$app->params['pagesize'];
        $offset = $pageSize * ($curPage - 1);
        $where = implode(' and ', $where);
        $query = new \yii\db\Query();
        $query = $query->select('a.*,b.role_name')->from('admin as a')
            ->leftJoin('admin_role as b', ' a.admin_role_id=b.id')
            ->where($where);
        $totalPage = $query->count();
        $rs = $query->limit($pageSize)->offset($offset)->all();
        if($rs){
            foreach ($rs as $k=>$v){
                $rs[$k]['created_at'] = date("Y-m-d H:i:s",$v['created_at']);
                $rs[$k]['updated_at'] = date("Y-m-d H:i:s",$v['updated_at']);
            }
        }
        $enableAdd = false;//添加市公司权限
        $enableEdit = false;//编辑市公司权限
        $enablePasswd = false;
        if (Yii::$app->session->get('username') != 'admin') {
            $enablePasswd = true;
            $aulist = explode('|', Yii::$app->session->get('authlist'));

            if (!in_array('citycmpuseradd', $aulist)) {
                $enableAdd = true;
            }

            if (!in_array('citycmpuseredit', $aulist)) {
                $enableEdit = true;
            }
        }

        return ['rs' => $rs, 'totalPage' => $totalPage, 'pageSize' => $pageSize, 'search' => @$search, 'currpage' => $curPage, 'auth' => ['enableAdd' => $enableAdd, 'enableEdit' => $enableEdit, 'enablePasswd' => $enablePasswd]];

    }
}
