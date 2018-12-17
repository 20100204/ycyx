<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/26
 * Time: 16:56
 * 统计
 */

namespace app\controllers;
use app\common\behavior\NoCsrs;
use app\models\Admin;
use app\models\Aftersales;
use app\models\Cancelorder;
use app\models\Order;
use yii\web\Controller;
use Yii;

class StaticticsController extends Controller
{
    public function behaviors()
    {
        return [
            'csrf' => [
                'class' => NoCsrs::className(),
                'controller' => $this,
                'actions' => [
                   "orderamount",
                    "moreca"

                ]
            ]
        ];
    }

    /*查询售后和取消订单信息*/
    public function actionMoreca(){
        Yii::$app->response->format = 'json';
        date_default_timezone_set("PRC");
        $time = Yii::$app->request->post('day');
        $area = Yii::$app->request->post('area');
        $today= strtotime($time);
        $tomorrow =$today+3600*24 ;
        if($area){
            $orderCancel = Order::find()->leftJoin('shop','order.shop_id=shop.id')->where('order.updated_at>='.$today.' and order.updated_at<'.$tomorrow.' and order.status in("CANCEL_BY_SYSTEM","CANCEL_BY_USER") and shop.area like "%'.$area.'%"')->asArray()->all();
        }else{
            $orderCancel = Order::find()->where('order.updated_at>='.$today.' and order.updated_at<'.$tomorrow.' and status in("CANCEL_BY_SYSTEM","CANCEL_BY_USER")')->asArray()->all();
        }

        $cancel=[
            'all_amount'=>0,
            'user_amount'=>0,
            'system_amount'=>0,
            'preorder_amount'=>0,
            'groupon_amount'=>0,
            'all_count'=>0,
            'user_count'=>0,
            'system_count'=>0,
            'preorder_count'=>0,
            'groupon_count'=>0,

        ];
        if($orderCancel){
            $cancel['all_count'] = count($orderCancel);
            foreach ($orderCancel as $ck=>$cv){
                $cancel['all_amount'] = bcadd( $cancel['all_amount'] ,$cv['payment'],2);
                if($cv['status']=='CANCEL_BY_USER'){
                    $cancel['user_amount'] = bcadd($cancel['user_amount'],$cv['payment'],2);
                    $cancel['user_count'] = $cancel['user_count']+1;
                }elseif ($cv['status']=='CANCEL_BY_SYSTEM'){
                    $cancel['system_amount'] = bcadd($cancel['system_amount'],$cv['payment'],2);
                    $cancel['system_count'] = $cancel['system_count']+1;
                }
                if($cv['promotion_type']=='preorder'){
                    $cancel['preorder_amount'] = bcadd($cancel['preorder_amount'],$cv['payment'],2);
                    $cancel['preorder_count'] = $cancel['preorder_count']+1;
                }elseif ($cv['promotion_type']=='groupon'){
                    $cancel['groupon_amount'] = bcadd($cancel['groupon_amount'],$cv['payment'],2);
                    $cancel['groupon_count'] = $cancel['groupon_count']+1;
                }
            }
        }
        $after =[
            'all_amount'=>0,
            'preorder_amount'=>0,
            'groupon_amount'=>0,
            'all_count'=>0,
            'preorder_count'=>0,
            'groupon_count'=>0,
        ];

if($area){
    $allAfter = Aftersales::find()->where('(aftersale_apply.status=1 or aftersale_apply.status=3) and aftersale_apply.updated_at>='.$today.' and aftersale_apply.updated_at<'.$tomorrow.' and shop.area like "%'.$area.'%"')->select('aftersale_apply.*,order.promotion_type,order.payment,order.refund_amount')->leftJoin('order','order.id=aftersale_apply.order_id') ->leftJoin('shop','shop.id=order.shop_id')->asArray()->all();

}else{
    $allAfter = Aftersales::find()->where('(aftersale_apply.status=1 or aftersale_apply.status=3) and aftersale_apply.updated_at>='.$today.' and aftersale_apply.updated_at<'.$tomorrow)->select('aftersale_apply.*,order.promotion_type,order.payment,order.refund_amount')->leftJoin('order','order.id=aftersale_apply.order_id')->asArray()->all();
}



        if($allAfter){
            $after['all_count'] = count($allAfter);
            foreach ($allAfter as $ak=>$av){
                $after['all_amount'] = bcsub(bcadd( $after['all_amount'],$av['payment'],2),$av['refund_amount'],2);
                if($av['promotion_type']=='preorder'){
                    $after['preorder_count'] =  $after['preorder_count'] +1;
                    $after['preorder_amount'] = bcsub(bcadd($after['preorder_amount'],$av['payment'],2),$av['refund_amount'],2);
                }elseif ($av['promotion_type']=='groupon'){
                    $after['groupon_count'] =  $after['groupon_count'] +1;
                    $after['groupon_amount'] = bcsub(bcadd($after['groupon_amount'],$av['payment'],2),$av['refund_amount'],2);
                }

            }
        }

        return ['cancel'=>$cancel,'after'=>$after];

    }


    /*某日*/
    public function actionOrderamount(){
        Yii::$app->response->format = 'json';
        date_default_timezone_set("PRC");
        $time="";
        $area="";
        $city = [];
        $isCity = false;
        if(Yii::$app->request->isPost){
            $time = Yii::$app->request->post('day');
            $area= Yii::$app->request->post('area');
        }
//        if(!$time){
//            $time ="2018-08-17";
//        }
        $today= $time?strtotime($time):strtotime(date("Y-m-d"));
        $tomorrow =$today+3600*24 ;
        if (Yii::$app->session->get('parent_admin_role_id') == '2') {
            //如果是市公司用户who_uploader in()市公司只能看到本市的;
            $citys[] = Yii::$app->session->get('role_area');
            $isCity = true;
            $area = Yii::$app->session->get('role_area');

        } else {
            $citys = Admin::find()->select('area,city')->leftJoin('admin_role', 'admin.admin_role_id=admin_role.id')->distinct()->where('admin.status=1 and admin_role.parent_id=2 ')->asArray()->all();
            array_unshift($citys, ['city' => '全国','area'=>'']);
        }

        //今日订单总额
        if($area){
            $allOrder = Order::find()->leftJoin("shop",'order.shop_id=shop.id')->select('order.*')->where('order.created_at>='.$today.' and order.created_at<'.$tomorrow.' and shop.area like "%'.$area.'%"' )->asArray()->orderBy('order.created_at')->all();
        } else{
            $allOrder = Order::find()->select('order.*')->where('order.created_at>='.$today.' and order.created_at<'.$tomorrow )->asArray()->orderBy('order.created_at')->all();
        }


        $order=[
            'all_count'=>0,
            'all_amount'=>0,
            'wechat_amount'=>0,
            'balance_amount'   => 0,
            'preorder_amount' => 0,
            'preorder_count'  => 0,
            'unpaid_preorder_count'   => 0,
            'unpaid_preorder_amount'  => 0,
            'groupon_count' => 0,
            'groupon_amount' => 0,
            'unpaid_groupon_amount'  => 0,
            'unpaid_groupon_count'  => 0,
        ];
        if($allOrder){
            $order['all_count'] = count($allOrder);
            //要减去团购的金额

            if($area){
                $wechat = Order::find()->leftJoin('payment_detail','order.id=payment_detail.order_id')->leftJoin("payment",'payment.id=payment_detail.payment_id')->leftJoin("shop",'order.shop_id=shop.id')->select('order.payment')->where('order.created_at>='.$today.' and order.created_at<'.$tomorrow.' and shop.area like "%'.$area.'%" and payment.method="wechat" and payment.status=1' )->asArray()->all();
                if($wechat){
                    $wechat = array_column($wechat,'payment');
                    if($wechat){
                        foreach ($wechat as $v){
                            $order['wechat_amount'] = bcadd($order['wechat_amount'],$v,2);
                        }

                    }

                }
                $balance = Order::find()->leftJoin('payment_detail','order.id=payment_detail.order_id')->leftJoin("payment",'payment.id=payment_detail.payment_id')->leftJoin("shop",'order.shop_id=shop.id')->select('order.payment')->where('order.created_at>='.$today.' and order.created_at<'.$tomorrow.' and shop.area like "%'.$area.'%" and payment.method="balance" and payment.status=1' )->asArray()->all();
                if($balance){
                    $balance = array_column($balance,'payment');
                    if($balance){
                        foreach ($balance as $v){
                            $order['balance_amount'] = bcadd($order['balance_amount'],$v,2);
                        }
                    }
                }
            } else{
                $wechat = Order::find()->leftJoin('payment_detail','order.id=payment_detail.order_id')->leftJoin("payment",'payment.id=payment_detail.payment_id')->select('order.payment')->where('order.created_at>='.$today.' and order.created_at<'.$tomorrow.' and payment.method="wechat" and payment.status=1' )->asArray()->all();
                if($wechat){
                    $wechat = array_column($wechat,'payment');
                    if($wechat){
                        foreach ($wechat as $v){
                            $order['wechat_amount'] = bcadd($order['wechat_amount'],$v,2);
                        }
                    }
                }
                $balance = Order::find()->leftJoin('payment_detail','order.id=payment_detail.order_id')->leftJoin("payment",'payment.id=payment_detail.payment_id')->select('order.payment')->where('order.created_at>='.$today.' and order.created_at<'.$tomorrow.' and payment.method="balance" and payment.status=1' )->asArray()->all();
                if($balance){
                    $balance = array_column($balance,'payment');
                    if($balance){
                        foreach ($balance as $v){
                            $order['balance_amount'] = bcadd($order['balance_amount'],$v,2);
                        }
                    }
                }

            }

            foreach ($allOrder as $ok=>$ov){
                $order['all_amount'] = bcadd( $order['all_amount'],$ov['payment'],2);
                if($ov['promotion_type']=='preorder'){
                    $order['preorder_amount'] = bcadd($order['preorder_amount'],$ov['payment'],2);
                    $order['preorder_count']  = $order['preorder_count'] + 1;
                    if(!$ov['paid_at']){
                        $order['unpaid_preorder_amount']= bcadd( $order['unpaid_preorder_amount'],$ov['payment'],2);
                        $order['unpaid_preorder_count'] = $order['unpaid_preorder_count']+1;
                    }
                }elseif ($ov['promotion_type']=='groupon'){
                    $order['groupon_amount'] = bcadd($order['groupon_amount'],$ov['payment'],2);
                    $order['groupon_count']  = $order['groupon_count']+1;
                    if(!$ov['paid_at']){
                        $order['unpaid_groupon_amount']= bcadd( $order['unpaid_groupon_amount'],$ov['payment'],2);
                        $order['unpaid_groupon_count'] =$order['unpaid_groupon_count']+ 1;
                    }
                }

            }

        }

        //取消订单金额
       // $allCancel = Cancelorder::find()->where('status=1 and created_at>='.$today.' and created_at<'.$tomorrow)->asArray()->all();

        //退货订单金额
       // $allAfter = Aftersales::find()->where('status=1 and created_at>='.$today.' and created_at<'.$tomorrow)->asArray()->all();
        $trend =  $this->ef($allOrder,'order',$today);
        return ['trend'=>$trend['trend'],'count'=>$trend['count'],'order'=>$order,'today'=>date("Y-m-d",$today),'isCity'=>$isCity,'city'=>$citys];
    }

    //24小时
    private function ef($data=[],$type="",$day=""){
        for($i=0;$i<24;$i++){
            $time[] = $day + $i*3600;
        }
        $rs = [];
        $count =[];
        foreach ($time as $k=>$v){
            @$rs[$k]=@$rs[$k]?@$rs[$k]:0;
            @$count[$k]=@$count[$k]?@$count[$k]:0;
            if(isset($time[$k+1])){
                foreach ($data as $ok=>$ov){
                    if($ov['created_at']>=$v&&$ov['created_at']<$time[$k+1]){
                        $rs[$k] = bcadd($ov['payment'],$rs[$k],2);
                        $count[$k] =   $count[$k]+1;

                    }
                }
            }
        }


        return ['trend'=>$rs,'count'=>$count];
    }




}
