<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/23
 * Time: 17:17
 */

namespace app\controllers;

use app\common\behavior\NoCsrs;
use app\models\Admin;
use app\models\Approve;
use app\models\Coupon;
use app\models\Couponcash;
use app\models\Coupondiscount;
use app\models\Couponsendlog;
use app\models\Preorder;
use app\models\Preorderactivelog;
use app\models\Preorderchecklog;
use app\models\Promotioncoupon;
use app\models\Shop;
use app\models\Userrelwechatapp;
use app\models\Ycypuser;
use app\queue\CouponJob;
use yii\db\Exception;
use yii\web\Controller;
use Yii;
class ApproveController extends Controller
{
    public function behaviors()
    {
        return [
            'csrf' => [
                'class' => NoCsrs::className(),
                'controller' => $this,
                'actions' => [
                    "lists",
                    "preorder",
                    'sendcouponcheck'
                ]
            ]
        ];
    }
    public function actionLists()
    {
        Yii::$app->response->format = 'json';
        $where=[];
        $curPage = 1;
        $search = [];
        if (Yii::$app->request->isPost) {
          $search = Yii::$app->request->post();
            foreach ($search as $k => $v) {
                if (!trim($v)) {
                    continue;
                }
                if ($k == 'page') {
                    $curPage = $v;
                    continue;
                }

                if ($k == 'title' && !empty($v)) {
                    $where[] = ' a.title like "%' . trim($v) . '%"';
                    continue;
                }
                if ($k == 'city' && !empty($v) && ($v != "全国")) {

                    $where[] = ' b.area="' . trim($v) . '"';
                    continue;
                }


                if ($k == 'check' && ($v != 3) && isset($v)) {
                    $where[] = ' a.status="' . trim($v) . '"';
                    continue;
                }
            }
        }
        $citys=[];
        if (Yii::$app->session->get('parent_admin_role_id') == '2') {
            $where[] = ' b.area="' .Yii::$app->session->get('role_area') . '"';

        } else {
            $citys = Admin::find()->select('admin.area,admin.city')->leftJoin('admin_role', 'admin.admin_role_id=admin_role.id')->distinct()->where(' admin_role.parent_id=2 ')->asArray()->all();
            array_unshift($citys, ['city' => '全国','area'=>'全国']);
        }
        $where = $where ? implode(' and ', $where) : [];
        $pageSize = Yii::$app->params['pagesize'];
       // $pageSize = 2;
        $offset = $pageSize * ($curPage - 1);
        $query = new \yii\db\Query();
        $query = $query->select('a.*,FROM_UNIXTIME(a.created_at) as applytime,FROM_UNIXTIME(a.approve_at) as approvetime,b.username as creater,c.username as approver')
            ->from('approve as a')
            ->leftJoin('admin as b', 'b.id=a.creater_id')
            ->leftJoin('admin as c', 'c.id=a.admin_id');
        if ($where) {
            $query = $query->where($where);
        }
        $totalPage = $query->count();
        $rs = $query->offset($offset)->limit($pageSize)->orderBy('a.id desc ')->all();
        $enableCheck=false;//审核
        if (Yii::$app->session->get('username') != 'admin') {
            $aulist = explode('|', Yii::$app->session->get('authlist'));
            if (!in_array('enablePreorderCheck', $aulist)) {
                $enableCheck = true;
            }
        }
        $enableCheck=false;//审核
        return ['rs' => $rs, 'totalPage' => $totalPage, 'pageSize' => $pageSize,  'currpage' => $curPage, 'citys' => $citys,'enableCheck'=>$enableCheck];

    }

    /*审核送券*/
    public function actionSendcouponcheck(){
        Yii::$app->response->format = 'json';
         $get = Yii::$app->request->get();
        $adminId = Yii::$app->session->get('admin_id');
        if(!$get||!$get['log_id']||!$get['status']||!in_array($get['status'],["通过","拒绝"])||!$adminId){
            return ['rs'=>'false','msg'=>'参数缺失'];
        }
        $checkstatus="";
        $time = time();
        if($get['status']=="拒绝"){
            $checkstatus = 4;
        }elseif ($get['status']=="通过"){
            $checkstatus = 3;
        }
        $db = Yii::$app->db;
        $transcation = $db->beginTransaction();
        try {
            //通过时，改变状态approve,coupon_send_log,加入队列

            //带班事项表
            $approveModel =  Approve::find()->where('event_id='.$get['log_id'].' and approve_type="sendcoupon"')->one();
            if(!$approveModel||$approveModel->status==2){
                throw new Exception("数据异常".__LINE__);
            }
            $approveModel->approve_at = $time;
            $approveModel->status = 2;
            $approveModel->admin_id = $adminId;//审核人
            if(!$approveModel->save(false)){
                throw new Exception("审核失败".__LINE__);
            }
            $sendLog = Couponsendlog::findOne($get['log_id']);
            $sendLog->checkstatus = $checkstatus;
            $sendLog->reject_reason = $get['reason'];
            if(!$sendLog->save(false)){
                throw new Exception("审核失败".__LINE__);
            }
            if($checkstatus==3){
                //加入队列
               $detail = explode('|',$sendLog->detail);
               $coupon = Coupon::findOne($sendLog->coupon_id);
                Yii::$app->queue->push(new CouponJob([
                    'range'=>$detail[0],
                    'sendLogId'=>$sendLog->id,
                    'couponId'=>$sendLog->coupon_id,
                    'expired'=>$coupon->expired,
                    'title'=>$coupon->title,
                    'select'=>$detail[2]
                ]));
            }


            $transcation->commit();
            return ['rs'=>'true'];
        }catch (Exception $e){
            $transcation->rollBack();
            return ['rs'=>'false','msg'=>$e->getMessage()];
        }

    }

    /*审核,只审核一次*/
    public function actionCheck(){
        Yii::$app->response->format = 'json';
        $get = Yii::$app->request->get();
        $adminId = Yii::$app->session->get('admin_id');
        if(!$get||!$get['preorder_id']||!$get['preorder_status']||!in_array($get['preorder_status'],["通过","拒绝"])||!$adminId){
            return ['rs'=>'false','msg'=>'参数缺失'];
        }
        $db = Yii::$app->db;
        $transcation = $db->beginTransaction();
        try {
            //预售活动表
             $preorderModel = Preorder::findOne($get['preorder_id']);
             if(!$preorderModel){
                 throw new Exception("数据异常".__LINE__);
             }
             $time = time();
              $check_status = "";
             if($get['preorder_status']=="拒绝"){
                 $preorderModel->preorder_status=12;//总部拒绝
                 $check_status = 6;
                 $msg = '对该活动做了审核拒绝处理';
             }elseif ($get['preorder_status']=="通过"){
                 $preorderModel->preorder_status=1;//总部拒绝
                 $check_status = 3;
                 $msg = '对该活动做了审核同意处理';
             }
             //active_log
             $preorderActiveLog = new Preorderactivelog();
             $preorderActiveLog->admin_id = $adminId;
             $preorderActiveLog->preorder_id = $get['preorder_id'];
             $preorderActiveLog->desc = $msg;
             $preorderActiveLog->created_at = date("Y-m-d H:i:s",$time);
             if(!$preorderActiveLog->save(false)){
                 throw new Exception("审核失败".__LINE__);
             }
             $preorderModel->updated_at=$time;//总部拒绝
            if(!$preorderModel->save(false)){
                throw new Exception("审核失败".__LINE__);
            }
            //预售活动审核日志表
             $preorderCheckLog = new Preorderchecklog();
             $preorderCheckLog->preorder_id = $get['preorder_id'];
             $preorderCheckLog->created_at = date("Y-m-d H:i:s",$time);
             $preorderCheckLog->admin_id = $adminId;
             $preorderCheckLog->check_status = $check_status;
             $preorderCheckLog->reject_reason = $get['reason'];
             if(!$preorderCheckLog->save(false)){
                 throw new Exception("审核失败".__LINE__);
             }

            //带班事项表
             $approveModel =  Approve::find()->where('event_id='.$preorderModel->promotion_id.' and approve_type="preorder" ')->one();
             if(!$approveModel){
                 throw new Exception("数据异常".__LINE__);
             }
             $approveModel->approve_at = $time;
             $approveModel->status = 2;
             $approveModel->admin_id = $adminId;//审核人
             if(!$approveModel->save(false)){
                 throw new Exception("审核失败".__LINE__);
             }
//            //开启队列，如果同意了，十点送，这里不送
//            if($check_status==3){
//                 //根据活动的范围，发送指定的优惠券信息,有可能有多个城市或店东
//                if($preorderModel['buy_gift_coupon_id']){
//                    //有送券的
//                    $coupon = Coupon::findOne($preorderModel['buy_gift_coupon_id']);
//                    if($preorderModel['active_type']==2){
//                        $logModel = new Couponsendlog();
//                        $logModel->admin_id = $adminId;
//                        $logModel->coupon_id = $preorderModel['buy_gift_coupon_id'];
//                        $logModel->created_at = $time;
//                        $cityMsg="市公司|".$preorderModel['buy_gift_coupon_id'].'|'.$preorderModel['area'].'|'.$preorderModel['promotion_id'];//最后放promotion_id
//                        $logModel->detail = $cityMsg;
//                        $logModel->checkstatus = 3; //表示状态待定;
//                        $logModel->status = 3; //表示状态待定;
//                        if(!$logModel->save(false)){
//                            throw new Exception("发送失败");
//                        }
//                        //市公司
//                        $cityareas = explode(",",$preorderModel['area']);
//                        foreach ($cityareas as $cityKey=>$cityV){
//                            Yii::$app->queue->push(new CouponJob([
//                                'range'=>"市公司",
//                                'sendLogId'=>$logModel->id,
//                                'couponId'=>$preorderModel['buy_gift_coupon_id'],
//                                'expired'=>$coupon->expired,
//                                'title'=>$coupon->title,
//                                'select'=>$cityV
//                            ]));
//                        }
//
//                    }elseif($preorderModel['active_type']==3){
//                        //店东
//                        $logModel = new Couponsendlog();
//                        $logModel->admin_id = $adminId;
//                        $logModel->coupon_id = $preorderModel['buy_gift_coupon_id'];
//                        $logModel->created_at = $time;
//                        $cityMsg="市公司|".$preorderModel['buy_gift_coupon_id'].'|'.$preorderModel['area'].'|'.$preorderModel['promotion_id'];//最后放promotion_id
//                        $logModel->detail = $cityMsg;
//                        $logModel->checkstatus = 3; //表示状态待定;
//                        $logModel->status = 3; //表示状态待定;
//                        if(!$logModel->save(false)){
//                            throw new Exception("发送失败");
//                        }
//
//                    }
//                }
//            }
            $transcation->commit();
            return ['rs'=>'true'];
        }catch (Exception $e){
            $transcation->rollBack();
            return ['rs'=>'false','msg'=>$e->getMessage()];
        }

    }
    /*查看送券信息*/
    public function actionSendcoupon(){
        Yii::$app->response->format = 'json';
        $logId = Yii::$app->request->get("event_id");
          $logInfo = Couponsendlog::findOne($logId);
        $appler = Admin::findOne($logInfo->admin_id);
        $couponInfo = Coupon::findOne($logInfo->coupon_id);
        $rs['appler']= $appler->username;
        $rs['appletime']= date("Y-m-d H:i:s",$appler->created_at);
        $range = explode("|",$logInfo->detail);
        $rs['range'] = $range[0];

        switch ($range[0]){
            case "社区":
                $shop = Shop::findOne($range[2]);
                $rs['rangedetail'] = $shop->shop_name;
                break;
            case "全国":
                $rs['rangedetail'] = "全国";
                break;
            case "个人":

                $userInfo = Ycypuser::find()->where('user.mobile='.$range[2])->one();
                $userInfo = Userrelwechatapp::find()->where('user_id='.$userInfo->id)->one();

                $rs['rangedetail'] = $userInfo->nickname;
                break;
            case "市公司":
                $cityInfo = Admin::find()->where("area='".$range[2]."'")->one();
                $rs['rangedetail'] = $cityInfo->city;
                break;

        }
        $rs['coupon_type'] = $couponInfo->coupon_type=="cash"?"现金券":"满减券";
        $rs['coupon_title']=$couponInfo->title;
        $rs['checkstatus'] = $logInfo->checkstatus=="3"?"通过":($logInfo->checkstatus=="4"?"拒绝":$logInfo->checkstatus);
        $enableCheck = false;//审核权限
        if (Yii::$app->session->get('username') != 'admin') {
            $aulist = explode('|', Yii::$app->session->get('authlist'));

            if (!in_array('enablePreorderCheck', $aulist)) {
                $enableCheck = true;
            }

        }
        $rs['enableCheck']=$enableCheck;
        return $rs;

    }

    /*查看预售活动*/
    public function actionPreorder(){
        Yii::$app->response->format = 'json';
        $promotionId = Yii::$app->request->get("event_id");
        if(!$promotionId){
            return ['rs'=>'false','msg'=>'参数不全'.__LINE__];
        }
        $preorderModel = Preorder::find()->where('promotion_id='.$promotionId)->asArray()->one();
        if(!$preorderModel){
            return ['rs'=>'false','msg'=>'参数不全'.__LINE__];
        }
        $preorderModel['pickup_end_time'] =  $preorderModel['pickup_end_time'] ?date("Y-m-d H:i:s", $preorderModel['pickup_end_time'] ):"";
        $preorderModel['pickup_time'] =  $preorderModel['pickup_time'] ?date("Y-m-d H:i:s", $preorderModel['pickup_time'] ):"";
        $preorderModel['end_time'] =  $preorderModel['end_time'] ?date("Y-m-d H:i:s", $preorderModel['end_time'] ):"";
        $preorderModel['begin_time'] =  $preorderModel['begin_time'] ?date("Y-m-d H:i:s", $preorderModel['begin_time'] ):"";
        $preorderModel['created_at'] =  $preorderModel['created_at'] ?date("Y-m-d H:i:s", $preorderModel['created_at'] ):"";
        $preorderModel['notice_time'] =  $preorderModel['notice_time'] ?date("Y-m-d H:i:s", $preorderModel['notice_time'] ):"";
        $preorderModel['user_created_at'] =  $preorderModel['user_created_at'] ?date("Y-m-d H:i:s", $preorderModel['user_created_at'] ):"";
        //获取创建人信息
   $preorderModel['active_sender'] = Admin::findOne($preorderModel['who_creater']);
  $preorderModel['active_sender'] = $preorderModel['active_sender']->username ;
        //获取市公司审核人信息
         $checker =    Preorderchecklog::find()->leftJoin("admin","admin.id=preorder_check_log.admin_id")->where('preorder_check_log.preorder_id='.$preorderModel["id"].' and preorder_check_log.check_status=5  ')->orderBy("preorder_check_log.id desc")->select("admin.username as checker, preorder_check_log.created_at  as check_time")->asArray()->one();
        $preorderModel['checker'] =$checker["checker"];
        $preorderModel['check_time'] =$checker["check_time"];
        //获取总公司拒绝理由
        $reason = Preorderchecklog::find()->where('preorder_id='.$preorderModel['id'].' and check_status=6')->one();
        $preorderModel['reason'] =$reason?$reason->reject_reason:"";
        $shareCoupon =[];
        $coupons =[];
        $takecoupon=[];
        if($preorderModel['coupon_id']){
            //分享得券信息
            $shareCoupon = Coupon::find()->leftJoin('admin','admin.id=coupon.admin_id')->where('coupon.id='.$preorderModel['coupon_id'])->select('coupon.id,coupon.title,coupon.coupon_type,admin.city')->one();
            if(!$shareCoupon['id']){
                $shareCoupon=[];

            }
        }
        if($preorderModel['buy_gift_coupon_id']){
            //参与得券信息
            $takecoupon = Coupon::find()->leftJoin('admin','admin.id=coupon.admin_id')->where('coupon.id='.$preorderModel['buy_gift_coupon_id'])->select('coupon.id,coupon.title,coupon.coupon_type,admin.city')->asArray()->one();
            if(!$takecoupon['id']){
                $takecoupon=[];
            }else{
                if($preorderModel['per_buy_gift_coupon_num']){
                    $takecoupon['msg']="每用户最多送".$preorderModel['per_buy_gift_coupon_num'].'张券';
                }else{
                    $takecoupon['msg']="每用户送券张数不限";
                }
            }
        }

        //优惠券信息
        $coupons = Promotioncoupon::find()->where('promotion_id='.$promotionId)->asArray()->select('coupon_id')->all();
        if($coupons){
            $couponids = array_column($coupons,'coupon_id');
            if($couponids){
                $coupons= Coupon::find()->leftJoin("admin","coupon.admin_id=admin.id")->select('coupon.title,coupon.coupon_type,admin.city,coupon.id')->where('coupon.id in('.implode(',',$couponids).')')->asArray()->all();
                // return $preorderModel;
                if($coupons){
                    foreach ($coupons as $cpk=>$cpv){
                        if($cpv['coupon_type']=='cash'){
                            $cash = Couponcash::find()->where('coupon_id='.$cpv['id'])->one();
                            $amount = bcsub(bcsub($preorderModel['price'],$preorderModel['caigou_price'],2),$cash->discount_amount,2);
                            $coupons[$cpk]['msg']="如仅购一份,公司毛利润为:".$amount;
                        }elseif ($cpv['coupon_type']=="discount"){
                            $cash = Coupondiscount::find()->where('coupon_id='.$cpv['id'])->one();
                            $mll = bcmul(bcdiv(bcsub($preorderModel['price'],$preorderModel['caigou_price'],2),$preorderModel['price'],2),100,2);
                            $fd = bcmul(bcdiv($cash->discount_amount,$cash->min_amount,2),100,2);
                            $coupons[$cpk]['msg']="未使用优惠券前毛利率为:".$mll.'%,优惠幅度为:'.$fd.'%';
                        }
                    }
                }
            }
        }
        $preorderModel['coupons'] = $coupons;
        $preorderModel['shareCoupon'] = $shareCoupon;
        $preorderModel['takecoupon'] = $takecoupon;
        if($preorderModel['active_type']==1){
            $preorderModel['active_type']='全国';
        }elseif ($preorderModel['active_type']==2){
            //市公司
            $citys =  Admin::find()->where('id in ('.$preorderModel['active_id'].')')->select('city')->asArray()->all();
             $citys = $citys?implode("、",array_column($citys,'city')):"";
             $preorderModel['active_type'] = $citys;
        }elseif ($preorderModel['active_type']==3){
            //店东
            $citys =  Shop::find()->where('id in ('.$preorderModel['active_id'].')')->select(' city,shop_name ')->asArray()->all();
            $ct="";
            if($citys){

                foreach ($citys as $ck=>$cv){
                   $ct .= $cv['city'].'-'.$cv['shop_name'];
                }
            }
            $preorderModel['active_type'] = $ct;
        }

        $enableCheck = false;//审核权限
        if (Yii::$app->session->get('username') != 'admin') {
            $aulist = explode('|', Yii::$app->session->get('authlist'));

            if (!in_array('enablePreorderCheck', $aulist)) {
                $enableCheck = true;
            }

        }

        return ['rs'=>$preorderModel,'enableCheck'=>$enableCheck];


    }


}
