<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/6/15
 * Time: 14:59
 */

namespace app\controllers;

use app\common\behavior\NoCsrs;
use app\models\Admin;
use app\models\Category;
use app\models\Coupon;
use app\models\Groupbuy;
use app\models\Groupbuylog;
use app\models\Groupbuystatus;
use app\models\Order;
use app\models\Orderdetail;
use app\models\Preordersend;
use app\models\Promotioncoupon;
use app\models\Sku;
use yii\db\Exception;
use yii\web\Controller;
use Yii;

class GroupbuyController extends Controller
{
    public function behaviors()
    {
        return [
            'csrf' => [
                'class' => NoCsrs::className(),
                'controller' => $this,
                'actions' => [
                    'lists',
                    "price",
                    "profit",
                    "addprice",
                    "citylist",
                    "oprice",
                    "nosend",
                    "send",
                    "sendhistory",
                    "coupons",
                    "usecoupon",
                    "sharecoupon",
                    "takecoupon"

                ]
            ]
        ];
    }
    /*获得可用优惠券列表*/
    public function actionCoupons(){
        Yii::$app->response->format = 'json';
         $post = Yii::$app->request->post();
        if(!$post['active_type']||!$post['promotion_id']){
            return [];
        }
        $select = $post['area']?explode(",",$post['area']):"";
        if($post['active_type']==1){
            //全国
                $rs = Coupon::find()->where("(scope=0 and status=1 )" )->select("id,coupon_type,title")->asArray()->all();

        }
        if($post['active_type']==2){
            //市公司
            if(!$post['area']){
                return [];
            }
            if(count($select)>1){
                $rs =  Coupon::find()->where("(scope=0 and status=1 )" )->select("id,coupon_type,title")->asArray()->all();
            }else{
                $area = explode(":",$select[0]);
                $area = $area[0].":".$area[1];
                $rs = Coupon::find()->where("(scope=0 and status=1 ) or(scope=1 and status=1  and scope_info like '%".$area."%' )")->select("id,coupon_type,title")->asArray()->all();
            }

        }
        if($rs){
            foreach ($rs as $rk=>$rv){
                if($rv['coupon_type']=="cash"){
                    $rs[$rk]['title']="现金券--".$rv['title'];
                }elseif($rv['coupon_type']=='discount'){
                    $rs[$rk]['title']="满减券--".$rv['title'];
                }
            }
        }
        $couponIds = [];
        $promotionCouponModel = Promotioncoupon::find()->where("promotion_id=".$post['promotion_id'])->select('coupon_id')->asArray()->all();
         $couponIds = array_column($promotionCouponModel,'coupon_id');
        return ['rs'=>$rs,'couponIds'=>$couponIds];

    }

    /*活动使用优惠券*/
    public function actionUsecoupon(){
        Yii::$app->response->format = 'json';
        $post = Yii::$app->request->post();
        if(!$post['promotion_id']){
            return ['rs'=>'false','msg'=>'参数缺失'.__LINE__];
        }
        $time =time();
        $db = Yii::$app->db;
        $transaction = $db->beginTransaction();

        try {

            //插入可用优惠券表
            if(Promotioncoupon::find()->where("promotion_id=".$post['promotion_id'])->one()){
                if(!Promotioncoupon::deleteAll("promotion_id=".$post['promotion_id'])){
                    throw new Exception("insert fail promotion".__LINE__);
                }
            }
            if($post['coupon_ids']){
                foreach ($post['coupon_ids'] as $ck=>$cv){
                    $couponModel = new Promotioncoupon();
                    $couponModel->promotion_id = $post['promotion_id'];
                    $couponModel->coupon_id = $cv;
                    $couponModel->created_at = $time;
                    if (!$couponModel->save(false)) {
                        throw new Exception("insert fail promotion");
                    }
                }
            }
            $transaction->commit();
            return ['rs'=>'true'];
        }catch (Exception $e){
            $transaction->rollBack();
            return ['rs'=>'false','msg'=>$e->getMessage()];
        }
    }


    /*参与活动得优惠券*/
    public function actionTakecoupon(){
        Yii::$app->response->format = 'json';
        $post = Yii::$app->request->post();
        if(!$post['promotion_id']){
            return ['rs'=>'false','msg'=>'参数缺失'.__LINE__];
        }

        $groupModel = Groupbuy::find()->where('promotion_id='.$post['promotion_id'])->one();
        $couponId = isset($post['buy_gift_coupon_id'])?$post['buy_gift_coupon_id']:null;
        $groupModel->buy_gift_coupon_id  = $couponId;
        $groupModel->per_buy_gift_coupon_num  = $post['per_buy_gift_coupon_num'];
        if(!$groupModel->save(false)){
            return ['rs'=>'false','msg'=>"设置失败".__LINE__];
        }
        return ['rs'=>'true'];

    }

    /*活动使用分享优惠券*/
    public function actionSharecoupon(){
        Yii::$app->response->format = 'json';
        $post = Yii::$app->request->post();
        if(!$post['promotion_id']){
            return ['rs'=>'false','msg'=>'参数缺失'.__LINE__];
        }

        $groupModel = Groupbuy::find()->where('promotion_id='.$post['promotion_id'])->one();
        $couponId = isset($post['coupon_id'])?$post['coupon_id']:null;
        $groupModel->coupon_id  = $couponId;
        if(!$groupModel->save(false)){
            return ['rs'=>'false','msg'=>"设置失败".__LINE__];
        }
        return ['rs'=>'true'];

    }

    /*详情*/
    public function actionInfo(){
        Yii::$app->response->format = 'json';
         $id = Yii::$app->request->get('id');
        $pro = [];
        $active =[];
        if(!$id){
            return ['pro'=>$pro,'active'=>$active];
        }
        $buyModel = Groupbuy::findOne($id) ;
        $buyModel->notice_time = $buyModel->notice_time?date("Y-m-d H:i:s",$buyModel->notice_time):'---';
        $buyModel->end_time = $buyModel->end_time?date("Y-m-d H:i:s",$buyModel->end_time):'---';
        $buyModel->begin_time = $buyModel->begin_time?date("Y-m-d H:i:s",$buyModel->begin_time):'---';
        $skuInfo = Sku::findOne($buyModel->sku_id);
        $buyModel->rank = $skuInfo->detail;
        $buyModel->sku_id = explode('|',$skuInfo->pics);

         $purchasePrice = json_decode($buyModel->purchase_tiered_prices,true);
        $firstPrice = ['price'=>$buyModel->purchase_price ,'groupon_num'=>$buyModel->groupon_num];
         if($purchasePrice){
             array_unshift($purchasePrice,$firstPrice);
         }else{
             $purchasePrice []= $firstPrice;
         }
         $purchaseInfo = '';
         foreach ($purchasePrice as $k=>$v){
             $purchaseInfo.='数量达到'.$v['groupon_num'].',价格为:'.$v['price'].';';
         }
         $buyModel->purchase_price = $purchaseInfo;

         $statusModel = Groupbuystatus::findAll(['groupon_id'=>$id]);
         if($statusModel){
             foreach ($statusModel as $sk=>$sv){
                 //统计销量
                 $query = new \yii\db\Query();
                 $statusModel[$sk]->deleted_at =$query->from('order_detail as a')
                     ->leftJoin("shop as b",'b.id=a.shop_id')
                     ->leftJoin('admin as d','d.id=b.admin_id')
                     ->where('d.area="'.$sv->area.'" and a.promotion_id='.$buyModel->promotion_id.' and a.status in ("PAID","SHIPPED","IN_SHOP","FINISHED")')->sum('a.quantity');

                 $adminModel = Admin::find()->select('city')->where('area="'.$sv->area.'"')->one();
                 $statusModel[$sk]->area = $adminModel->city;

                 $tiered_prices = json_decode($sv->tiered_prices,true);
                 $firstPrice = ['price'=>$sv->price ,'groupon_num'=>$buyModel->groupon_num];
                 if($tiered_prices){
                     array_unshift($tiered_prices,$firstPrice);
                 }else{
                     $tiered_prices []= $firstPrice;
                 }
                 $statusModel[$sk]->tiered_prices = $tiered_prices;
                 $priceList = [];
                 foreach ($tiered_prices as $tk=>$tv){
                      if($buyModel->sold_out>=$tv['groupon_num']){
                          $priceList[] =  $tv['price'];
                      }
                 }
                 sort($priceList);
                                 $statusModel[$sk]->reason =$priceList? $priceList[0]:'';

                // $statusModel[$sk]->reason = $priceList[0];
                 if($sv->status=='1'){
                     $statusModel[$sk]->status = '已通过';
                 }elseif ($sv->status=='2'){
                     $statusModel[$sk]->status = '已拒绝';

                 }elseif ($sv->status=='3'){
                     $statusModel[$sk]->status = '总部已通过';

                 }elseif ($sv->status=='4'){
                     $statusModel[$sk]->status = '总部已拒绝';

                 }else{
                     $statusModel[$sk]->status = '待审核';

                 }

             }

         }
         $buyModel->exclude_shops = $statusModel;




        return ['active'=>$buyModel];

    }

    /*发货*/
    public function actionSend()
    {
        Yii::$app->response->format = 'json';
        $post = Yii::$app->request->post();
        if (!$post['send']) {
            return ['rs' => 'false', 'msg' => '发货数据为空'];
        }
        $db = Yii::$app->db;
        $time = time();
        $transcation = $db->beginTransaction();
        try {
            //做日志
            foreach ($post['send'] as $k => $v) {
                $preorderSendModel = new Preordersend();
                $preorderSendModel->sender_id = Yii::$app->session->get('admin_id');
                $preorderSendModel->send_time = $time;
                $preorderSendModel->status = 2;
                $preorderSendModel->preorder_id = $v['id'];
                $preorderSendModel->order_detail_id = $v['order_detail_id'];
                $preorderSendModel->order_id = $v['order_id'];
                $preorderSendModel->promotion_id = $v['promotion_id'];
                $preorderSendModel->active_type = 'groupon';
                if (!$preorderSendModel->save(false)) {
                    throw new Exception("发货失败" . __LINE__);
                }

                //订单发货
                $orderModel = Order::findOne($v['order_id']);
                $orderModel->status = 'SHIPPED';
                $orderModel->shipped_at = $time;
                //$orderModel->updated_at=$time;
                if (!$orderModel->save(false)) {
                    throw new Exception("发货失败" . __LINE__);
                }
                //子订单发货
                $subOrderModel = Orderdetail::findOne($v['order_detail_id']);
                $subOrderModel->status = 'SHIPPED';
                //$subOrderModel->updated_at=$time;
                if (!$subOrderModel->save(false)) {
                    throw new Exception("发货失败" . __LINE__);
                }
            }
            $transcation->commit();
            return ['rs' => "true"];
        } catch (Exception $e) {
            $transcation->rollBack();
            return ['rs' => "false", 'msg' => $e->getMessage()];
        }

    }


    /*历史发货*/
    public function actionSendhistory()
    {
        Yii::$app->response->format = 'json';
        $post = Yii::$app->request->post();
        $preorderId = $post['id'];
        if (!$preorderId) {
            return [];
        }
        $orderBy='shop.id,b.sku_id';
        $where = ' and d.order_type=0 and  send.active_type="groupon" and   send.promotion_id in(' . implode(',', $preorderId) . ')';
        if(isset($post['search'])){
            if(trim($post['search']['order_type'])==1){
                $orderBy='b.sku_id,shop.id';
            }
            if(trim($post['search']['sku_title'])){
                $where.=' and c.title like "%'.$post['search']['sku_title'].'%"';
            }
            if(trim($post['search']['shop_name'])){
                $where.=' and shop.shop_name like "%'.$post['search']['shop_name'].'%"';
            }
            if(trim($post['search']['mobile'])){
                $where.=' and d.mobile='.$post['search']['mobile'];
            }
            if($post['search']['shop_type']=='0'||$post['search']['shop_type']==1){
              //  $where.=' and shop.shop_type='.$post['search']['shop_type'];
                if($post['search']['shop_type']==1){
                    $where .= ' and (shop.shop_type=1 or d.home_delivery=1)';

                }elseif ($post['search']['shop_type']=='0'){
                  //  $where .= ' and shop.shop_type=0 and d.home_delivery=0 ';
                    $where .= ' and ((shop.shop_type=0 and d.home_delivery=0) or (shop.shop_type=0 and d.home_delivery is null)) ';

                }
               // $where .= ' and (shop.shop_type=' . $post['search']['shop_type'] .' or d.home_delivery='.$post['search']['shop_type'].')';
            }
        }
        if (Yii::$app->session->get('parent_admin_role_id') == '2') {
            $area = Yii::$app->session->get('role_area');
            $where .=' and shop.area like "%'.$area.'%"';

        }
        $query = new \yii\db\Query();
        $rs= $query->select('send.preorder_id,c.specs,b.sku_title,b.id ,b.description,d.quantity,shop.shop_name,d.receiver_name,d.address as order_address,d.mobile,d.mobile as mask_mobile,d.remark,shop.shop_type,shop.address,admin.username as sender,FROM_UNIXTIME(send.send_time) as send_time,d.order_type,d.home_delivery,cate.cat_name,c.cat_id 
         ')->from('preorder_send as send')
            ->leftJoin('promotion_groupon as b', 'send.preorder_id=b.id')
            ->leftJoin('item_sku as c', 'b.sku_id=c.id')
            ->leftJoin("category as cate","c.cat_id=cate.id")
            ->leftJoin('order as d', 'd.id=send.order_id')
            ->leftJoin('shop', 'd.shop_id=shop.id')
            ->leftJoin('admin', 'send.sender_id=admin.id')
            ->where('b.sold_out>0 and send.status=1 '.$where)->orderBy($orderBy)
            ->all();
        if($rs){
            foreach ($rs as $k=>$v){
                if($v['shop_type']==1||$v['home_delivery']==1){
                    $rs[$k]['address'] = $v['order_address'];
                    $rs[$k]['getgoods']="送货上门";
                }else{
                    $rs[$k]['getgoods']="门店自取";
                }
                $catInfo = Category::findOne($v['cat_id']);
                if ($catInfo) {
                    if ($catInfo->level != 1) {
                        $catInfo = Category::findOne($catInfo->top_cat_id);
                    }
                    $rs[$k]['cat_name'] = $catInfo->cat_name;
                }
                $str_split = str_split($v['mask_mobile'],4);
                $str_split[1]='****';
                $rs[$k]['mask_mobile'] = implode($str_split);
            }
        }

        return $rs;

    }

    /*代发货*/
    public function actionNosend()
    {
        Yii::$app->response->format = 'json';
        $post = Yii::$app->request->post();
        $preorderId = $post['id'];
        if (!$preorderId) {
            return [];
        }
        $orderBy='shop.id,send.sku_id';
        $where = ' and  send.promotion_id in(' . implode(',', $preorderId) . ')';
        if(isset($post['search'])){
            if(trim($post['search']['order_type'])==1){
                $orderBy='send.sku_id,shop.id';
            }
            if(trim($post['search']['sku_title'])){
                $where.=' and send.sku_title like "%'.$post['search']['sku_title'].'%"';
            }
            if(trim($post['search']['shop_name'])){
                $where.=' and shop.shop_name like "%'.$post['search']['shop_name'].'%"';
            }
            if(trim($post['search']['mobile'])){
                $where.=' and d.mobile='.$post['search']['mobile'];
            }
            if($post['search']['shop_type']=='0'||$post['search']['shop_type']==1){
                //$where.=' and shop.shop_type='.$post['search']['shop_type'];
                if($post['search']['shop_type']==1){
                    $where .= ' and (shop.shop_type=1 or d.home_delivery=1)';

                }elseif ($post['search']['shop_type']=='0'){
                    $where .= ' and ((shop.shop_type=0 and d.home_delivery=0) or (shop.shop_type=0 and d.home_delivery is null)) ';
                }
            }
        }
        if (Yii::$app->session->get('parent_admin_role_id') == '2') {
            $area = Yii::$app->session->get('role_area');
            $where .=' and shop.area like "%'.$area.'%"';

        }

        $query = new \yii\db\Query();
        $rs = $query->select('send.id,send.groupon_status,send.promotion_id,c.specs,send.sku_title,f.order_id ,send.description,d.quantity,shop.shop_name,shop.shop_type,d.receiver_name,d.address as order_address,d.mobile,d.mobile as mask_mobile,d.remark,shop.address, f.id as order_detail_id ,send.end_time,d.home_delivery,d.order_type,cate.cat_name,c.cat_id ')
            ->from('promotion_groupon as send')
            ->leftJoin('item_sku as c', 'send.sku_id=c.id')
            ->leftJoin("category as cate","c.cat_id=cate.id")
            ->leftJoin('order_detail as f','send.promotion_id=f.promotion_id')
            ->leftJoin('order as d', 'd.id=f.order_id')
          //  ->leftJoin('order_cancel as e','d.id=e.order_id')
            ->leftJoin('shop', 'd.shop_id=shop.id')
            ->where('send.sold_out>0 and d.status="PAID" and d.order_type=0 and f.promotion_type="groupon" and send.groupon_status=1  '.$where)
            ->orderBy($orderBy)
            ->all();

        if($rs){
            foreach ($rs as $k=>$v){
                if( $v['groupon_status']!='1'){
                    $rs[$k]['_disabled'] = true ;
                    continue;
                }
                $catInfo = Category::findOne($v['cat_id']);
                if ($catInfo) {
                    if ($catInfo->level != 1) {
                        $catInfo = Category::findOne($catInfo->top_cat_id);
                    }
                    $rs[$k]['cat_name'] = $catInfo->cat_name;
                }
                $str_split = str_split($v['mask_mobile'],4);
                $str_split[1]='****';
                $rs[$k]['mask_mobile'] = implode($str_split);
//                if(in_array($v['cancelstatus'],['0','1','3'])){
//                    $rs[$k]['_disabled'] = true ;
//                    continue;
//                }
//                  if(in_array($v['cancelstatus'],['1','3'])){
//                      unset($rs[$k]);
//                      continue;
//                  }
                if($v['shop_type']==1||$v['home_delivery']==1){
                    $rs[$k]['address'] = $v['order_address'];
                    $rs[$k]['getgoods']="送货上门";
                }else{
                    $rs[$k]['getgoods']="门店自取";
                }
            }
            // sort($rs);
        }

        return $rs;

    }

    /*活动上下架时的条件判断*/
    private function check($model,$dostatus){
       if(in_array($dostatus,[1,3])){
           if($model->origin_price<=0){
               return ['rs'=>false,"msg"=>"请填写活动产品原价".__LINE__];
           }
           /*是否有填写售价，乌阶梯价时*/
           if($model->price<=0){
               return ['rs'=>false,"msg"=>"请填写活动产品售价".__LINE__];
           }
           /*有阶梯价时，是否填写了阶梯价和售价*/
           $gModel = Groupbuy::findOne($model->groupon_id);
           $purchasePrice = json_decode($gModel->purchase_tiered_prices,true);
           if($purchasePrice){
               $salesPrice = json_decode($model->tiered_prices,true);
               if(!$salesPrice){
                   return ['rs'=>false,'msg'=>'请填写活动的产品的阶梯售价'.__LINE__];
               }
               $p = array_column($purchasePrice,'groupon_num');
               $s = array_column($salesPrice,'groupon_num');
               $r = array_diff($p,$s);
               if($r){
                   return ['rs'=>false,'msg'=>'请填写活动的产品的阶梯售价'.__LINE__];
               }

               foreach ($salesPrice as $sk=>$sv){
                   if( $sv['price']<0){
                       return ['rs'=>false,'msg'=>'请填写活动的产品的阶梯售价'.__LINE__];
                   }
               }

           }
       }



        return ['rs'=>true];
    }

    /* 活动上架*/
    public function actionUp()
    {
        Yii::$app->response->format = 'json';
        $id = Yii::$app->request->get('id');
        $status = Yii::$app->request->get('status');
        $status = intval($status);
        if(!in_array($status,[1,3])){
            return ['rs'=>'false','msg'=>"数据缺失".__LINE__];
        }
        $db = Yii::$app->db;
        $adminId = Yii::$app->session->get('admin_id');
        if(Yii::$app->session->get('parent_admin_role_id') == '2'){
            $area = Yii::$app->session->get('role_area');
            $statusModel = Groupbuystatus::find()->where('groupon_id='.$id.' and area="'.$area.'"')->one();
        }else{
            $statusModel = Groupbuystatus::findOne($id);
        }

        if(!$statusModel||!$adminId){
            return ['rs'=>'false','msg'=>"数据缺失".__LINE__];
        }
        $tiaojian = $this->check($statusModel,$status);
        if(!$tiaojian['rs']){
            return ['rs'=>'false','msg'=>$tiaojian['msg']];
        }
        $time = time();
        $transcation = $db->beginTransaction();
        try{
            //$statusModel->c_status = $statusModel->status;
            $statusModel->status =$status;
//            if($status==3||$status==1){
//                //如果审核通过，修改总表状态为已成团
//                $grouponModel = Groupbuy::findOne($statusModel->groupon_id);
//                $grouponModel->groupon_status = 1;
//                if(!$grouponModel->save(false)){
//                    throw new Exception("操作失败".__LINE__);
//                }
//            }
            $statusModel->updated_at = $time;
            $statusModel->admin_id = $adminId;
            $statusModel->oper_time = $time;
            if(!$statusModel->save(false)) {
               throw new Exception("操作失败".__LINE__);
            }
            $logModel = new Groupbuylog();
            $logModel->group_id = $statusModel->groupon_id;
            $logModel->admin_id = $adminId;
            $logModel->create_at = $time;
            $logModel->check_id = $statusModel->id;
            $note = "把该活动状态改为:".$statusModel->status;
            $logModel->note = $note;
            if(!$logModel->save(false)){
                throw new Exception("操作失败".__LINE__);
            }
            $transcation->commit();
            return ['rs'=>'true'];
        }catch (Exception $e){
            $msg = $e->getMessage();
            $transcation->rollBack();
            return ['rs'=>'false','msg'=>$msg];
        }

    }
/*活动下架*/
    public function actionDown()
    {
        Yii::$app->response->format = 'json';
        $id = Yii::$app->request->get('id');
        $status = Yii::$app->request->get('status');
        $reason = Yii::$app->request->get('reason');
        $status = intval($status);
        if(!in_array($status,[2,4])||!$reason){
            return ['rs'=>'false','msg'=>"数据缺失".__LINE__];
        }
        $db = Yii::$app->db;
        $adminId = Yii::$app->session->get('admin_id');
        if(Yii::$app->session->get('parent_admin_role_id') == '2'){
            $area = Yii::$app->session->get('role_area');
            $statusModel = Groupbuystatus::find()->where('groupon_id='.$id.' and area="'.$area.'"')->one();
        }else{
            $statusModel = Groupbuystatus::findOne($id);
        }
        if(!$statusModel||!$adminId){
            return ['rs'=>'false','msg'=>"数据缺失".__LINE__];
        }
        $tiaojian = $this->check($statusModel,$status);
        if(!$tiaojian['rs']){
            return ['rs'=>'false','msg'=>$tiaojian['msg']];
        }
        $time = time();
        $transcation = $db->beginTransaction();
        try{
           // $statusModel->c_status = $statusModel->status;
            $statusModel->status = $status;
            $statusModel->updated_at = $time;
            $statusModel->admin_id = $adminId;
            $statusModel->oper_time = $time;
            $statusModel->reason = $reason;
            if(!$statusModel->save(false)) {
                throw new Exception("操作失败".__LINE__);
            }
            $logModel = new Groupbuylog();
            $logModel->group_id = $statusModel->groupon_id;
            $logModel->admin_id = $adminId;
            $logModel->create_at = $time;
            $logModel->check_id = $statusModel->id;
            $note = "把该活动状态改为:".$statusModel->status;
            $logModel->note = $note;
            if(!$logModel->save(false)){
                throw new Exception("操作失败".__LINE__);
            }
            $transcation->commit();
            return ['rs'=>'true'];
        }catch (Exception $e){
            $msg = $e->getMessage();
            $transcation->rollBack();
            return ['rs'=>'false','msg'=>$msg];
        }

    }

//审核时，确保填了售价和原价


    public function actionCitylist()
    {
        Yii::$app->response->format = 'json';
        $id = Yii::$app->request->get('id');
        return Groupbuystatus::find()->leftJoin("admin", "admin.area=promotion_groupon_status.area")->select("admin.company_name")->where('promotion_groupon_status.groupon_id=' . $id)->asArray()->distinct()->all();

    }

    public function actionAddprice()
    {
        Yii::$app->response->format = 'json';
        $post = Yii::$app->request->post();
        if (!$post['id'] || !$post['price'] || !$post['price']['price'] || !$post['price']['groupnum']||!$post['area']) {
            return ['rs' => 'false', 'msg' => '参数缺失' . __LINE__];
        }
        $model = Groupbuy::findOne($post['id']);
          $statusModel =Groupbuystatus::find()->where('area="'.$post['area'].'" and groupon_id=' . $post['id'])->one();
        if (!$model||!$statusModel) {
            return ['rs' => 'false', 'msg' => "数据有误，请联系管理员" . __LINE__];
        }

        $pprice = $purchasePrice = json_decode($model->purchase_tiered_prices, true);
        $hasPurchase = $purchasePrice ? true : false;
        $salesPrice = json_decode($statusModel->tiered_prices, true);
        $purchasePrice[] = ['price' => $model->purchase_price, 'groupon_num' => $model->groupon_num];

        foreach ($purchasePrice as $k => $v) {
            if ($v['groupon_num'] == $post['price']['groupnum']) {
                //售价大于采购价
                if ($post['price']['price'] <= $v['price']) {
                    return ['rs' => 'fasle', 'msg' => "售价要大于采购价line" . __LINE__];
                }

                //公司要有利润
                $c_price = bcdiv(bcmul(bcsub(100, $statusModel->profit_rate, 2), $post['price']['price'], 2), 100, 2);
                $profitRate = bcsub($c_price, $v['price'], 2);
                if ($profitRate < 0) {
                    return ['rs' => 'false', 'msg' => '公司盈利为负值line' . __LINE__];
                }

            }
        }


        //售价比较,量越大价格越低；
        if ($salesPrice) {
            foreach ($salesPrice as $sk => $sv) {
                if ($post['price']['groupnum'] < $model->groupon_num) {
                    if ($post['price']['price'] <= $sv['price']) {
                        return ['rs' => 'false', 'msg' => "数量越小价格应该越高" . __LINE__];
                    }
                }
                if (($post['price']['groupnum'] > $sv['groupon_num']) && ($post['price']['price'] >= $sv['price'])) {
                    return ['rs' => 'false', 'msg' => "数量越大价格应该越低" . __LINE__];
                }

                if (($post['price']['groupnum'] < $sv['groupon_num']) && ($post['price']['price'] <= $sv['price'])) {
                    return ['rs' => 'false', 'msg' => "数量越小价格应该越高" . __LINE__];
                }
            }

        } else {
            if ($hasPurchase) {
                if (($model->groupon_num != $post['price']['groupnum']) && ($statusModel->price > 0)) {
                    if ($post['price']['price'] >= $statusModel->price) {
                        return ['rs' => 'fasle', 'msg' => "数量越大价格应该越低" . __LINE__];
                    }
                }
            }
        }
        $adminId = Yii::$app->session->get("admin_id");
        if (!$adminId) {
            return ['rs' => 'false', 'msg' => '非法操作' . __LINE__];
        };

        $db = Yii::$app->db;
        $transcation = $db->beginTransaction();
        $time = time();
        try {
            //是否有价格阶梯
            if ($hasPurchase) {
                $notired = false;
                if ($salesPrice) {
                    $groupNum = array_column($salesPrice, 'groupon_num');
                    if (in_array($post['price']['groupnum'], $groupNum)) {
                        //有在更新
                        foreach ($salesPrice as $k => $v) {
                            if ($v['groupon_num'] == $post['price']['groupnum']) {
                                $salesPrice[$k]['price'] = $post['price']['price'];
                            }
                        }
                        usort($salesPrice,function ($a,$b){
                            if($a['groupon_num']==$b['groupon_num']){
                                return 0;
                            }
                            return ($a['groupon_num']>$b['groupon_num'])?1:-1;
                        });
                        $statusModel->tiered_prices = json_encode($salesPrice);

                    } else {
                        //没有在添加,一种最低成团，一种有阶梯的情况
                        if ($post['price']['groupnum'] == $model->groupon_num) {
                            $notired = true;
                            $statusModel->price = $post['price']['price'];
                        } else {
                            $salesPrice[] = ['groupon_num' => $post['price']['groupnum'], 'price' => $post['price']['price']];
                            usort($salesPrice,function ($a,$b){
                                if($a['groupon_num']==$b['groupon_num']){
                                    return 0;
                                }
                                return ($a['groupon_num']>$b['groupon_num'])?1:-1;
                            });

                            $statusModel->tiered_prices = json_encode($salesPrice);
                        }
                    }
                } else {
                    $opgm = array_column($pprice, 'groupon_num');
                    if (in_array($post['price']['groupnum'], $opgm)) {
                        $salesPrice[] = ['groupon_num' => $post['price']['groupnum'], 'price' => $post['price']['price']];

                        usort($salesPrice,function ($a,$b){
                            if($a['groupon_num']==$b['groupon_num']){
                                return 0;
                            }
                            return ($a['groupon_num']>$b['groupon_num'])?1:-1;
                        });

                        $statusModel->tiered_prices = json_encode($salesPrice);
                    } else {
                        if ($post['price']['groupnum'] == $model->groupon_num) {
                            $notired = true;
                            $statusModel->price = $post['price']['price'];
                        }
                    }
                }

            } else {
                $statusModel->price = $post['price']['price'];
            }
            $statusModel->updated_at = $time;
            if (!$statusModel->save(false)) {
                throw new Exception("系统错误，请联系管理员" . __LINE__);
            }
            $logModel = new Groupbuylog();
            $logModel->group_id = $post['id'];
            $logModel->admin_id = $adminId;
            $logModel->create_at = $time;
           //$logModel->check_id = $statusModel->id;

            $note = "把团购id为：" . $post['id'] . "数量为:" . $post['price']['groupnum'] . "城市area为：".$post['area']."的售价设为：" . $post['price']['price'];
            $logModel->note = $note;
            if (!$logModel->save(false)) {
                throw new Exception("系统错误，请联系管理员" . __LINE__);
            }
            $transcation->commit();
            return ['rs' => 'true'];
        } catch (Exception $e) {
            $msg = $e->getMessage();
            $transcation->rollBack();
            return ['rs' => 'false', 'msg' => $msg];
        }
    }


    /* 设置原价*/
    public function actionOprice(){
        Yii::$app->response->format = 'json';
        $post = Yii::$app->request->post();
        if (!Yii::$app->session->get('admin_id') || !$post['id'] || !isset($post['origin_price'])||!$post['area']) {
            return ['rs' => 'false', 'msg' => '参数缺失' . __LINE__];
        }
        if(floatval($post['origin_price'])<=0){
            return ['rs'=>'false','msg'=>'原价不能小于0'];
        }
      //  $model = Groupbuy::findOne($post['id']);
        $statusModel =Groupbuystatus::find()->where('area="'.$post['area'].'" and groupon_id=' . $post['id'])->one();
        if (!$statusModel) {
            return ['rs' => 'false', 'msg' => '参数缺失' . __LINE__];
        }
        $statusModel->origin_price = $post['origin_price'];
        if(!$statusModel->save(false)){
                return ['rs'=>'false','msg'=>'设置失败'.__LINE__];
        }
        return ['rs'=>'true'];
    }


    /* 设置店东分润*/
    public function actionProfit()
    {
        Yii::$app->response->format = 'json';
        $post = Yii::$app->request->post();
        if (!Yii::$app->session->get('admin_id') || !$post['id'] || $post['profit_rate']<0||!$post['area']) {
            return ['rs' => 'false', 'msg' => '参数缺失' . __LINE__];
        }
         $gmodel = Groupbuy::findOne($post['id']);
        $model =Groupbuystatus::find()->where('area="'.$post['area'].'" and groupon_id=' . $post['id'])->one();
        if (!$model) {
            return ['rs' => 'false', 'msg' => '参数缺失' . __LINE__];
        }

        if (bcmul($model->price, $post['profit_rate'], 2) > (bcsub($model->price, $gmodel->purchase_price, 2) * 100)) {
            return ['rs' => 'false', 'msg' => '店东分润设为' . $post['profit_rate'] . '公司将处于亏损状态' . __LINE__];
        }
        //if()
        $purchasePrice = json_decode($gmodel->purchase_tiered_prices, true);
        $salePrice = json_decode($model->tiered_prices, true);
        if ($purchasePrice && $salePrice) {
            foreach ($purchasePrice as $k => $v) {
                foreach ($salePrice as $sk => $sv) {
                    if ($sv['groupon_num'] == $v['groupon_num']) {
                        if (bcmul($sv['price'], $post['profit_rate'], 2) > (bcsub($sv['price'], $v['price'], 2) * 100)) {
                            return ['rs' => 'false', 'msg' => '店东分润设为' . $post['profit_rate'] . '公司将处于亏损状态' . __LINE__];
                        }
                    }
                }
            }
        }
        $oldProfit = $model->profit_rate;
        $model->profit_rate = $post['profit_rate'];
        $time = time();
        $model->updated_at = $time;
        $db = Yii::$app->db;
        $transcation = $db->beginTransaction();
        try {
            if (!$model->save(false)) {
                throw new Exception("设置失败" . __LINE__);
            }
            $logmodel = new Groupbuylog();
            $logmodel->admin_id = Yii::$app->session->get('admin_id');
            $logmodel->create_at = $time;
            $msg = $logmodel->admin_id . "把groupid为：" . $post['id'] . "的area为：".$post['area']."profit_rate从" . $oldProfit . '改为:' . $model->profit_rate;
            $logmodel->note = $msg;
            $logmodel->group_id = $post['id'];
            if (!$logmodel->save(false)) {
                throw new Exception("设置失败" . __LINE__);
            }
            $transcation->commit();
            return ['rs' => 'true'];
        } catch (Exception $e) {
            $msg = $e->getMessage();
            $transcation->rollBack();
            return ['rs' => 'false', 'msg' => $msg];

        }

    }


    /* price info*/
    public function actionPrice()
    {
        Yii::$app->response->format = 'json';
        if (Yii::$app->request->isGet) {
            $id = Yii::$app->request->get('id');
            $area = Yii::$app->request->get('area');
            //采购价格和阶梯价从groupbuy表中取
            $rs = Groupbuy::findOne($id);
            $price = [];
            $status = true;
            $isCity = false;
            if ($rs) {
                $citys = [];
                if (Yii::$app->session->get('parent_admin_role_id') != '2') {

                    $citys = Groupbuystatus::find()->leftJoin('admin', 'admin.area=promotion_groupon_status.area')->select('admin.city,admin.area')->distinct()->where('promotion_groupon_status.groupon_id='.$id)->asArray()->all();


                }else{
                    //市公司
                    $citys = Admin::find()->select('city,area')->where('area="'.Yii::$app->session->get('role_area').'"')->distinct()->all();
                    $isCity = true;

                }
                $area=$area?$area:$citys[0]['area'];
                $statusModel =Groupbuystatus::find()->where('area="'.$area.'" and groupon_id=' . $id)->one();
                if ($statusModel->profit_rate && $statusModel->price > 0) {
                     $cprice = bcsub(bcdiv(bcmul(bcsub(100, $statusModel->profit_rate, 2), $statusModel->price, 2), 100, 2), $rs->purchase_price, 2);
                    $sprice = bcdiv(bcmul($statusModel->price, $statusModel->profit_rate, 2), 100, 2);
                    $price[] = ['groupon_num' => $rs->groupon_num, 'price' => $statusModel->price, 'origin_price' => $statusModel->origin_price, 'purchase_price' => $rs->purchase_price, 'c_profit' => $cprice, 's_profit' => $sprice];
                } else {
                    $price[] = ['groupon_num' => $rs->groupon_num, 'price' => $statusModel->price > 0 ? $statusModel->price : '', 'origin_price' => $statusModel->origin_price, 'purchase_price' => $rs->purchase_price, 'c_profit' => '', 's_profit' => ''];
                }
                //有阶梯采购价
                if ($rs->purchase_tiered_prices) {
                    $purchasePrice = json_decode($rs->purchase_tiered_prices, true);
                    $salePrice = json_decode($statusModel->tiered_prices, true);
                    //有售的阶梯价
                    if ($salePrice) {
                        $purchasePG = array_column($purchasePrice, 'groupon_num');
                        $sg = array_column($salePrice, 'groupon_num');
                        $df = array_diff($purchasePG, $sg);
                    }
                    foreach ($purchasePrice as $k => $v) {

                        if ($salePrice) {
                            if (in_array($v['groupon_num'], $df)) {
                                $price[] = ['groupon_num' => $v['groupon_num'], 'price' => '', 'origin_price' => $statusModel->origin_price, 'purchase_price' => $v['price'], 'c_profit' => '', 's_profit' => ''];
                            } else {
                                foreach ($salePrice as $sk => $sv) {
                                    if ($sv['groupon_num'] == $v['groupon_num']) {
                                        if ($statusModel->profit_rate && $sv['price']) {
                                            $cprice = bcsub(bcdiv(bcmul($sv['price'], bcsub(100, $statusModel->profit_rate, 2), 2), 100, 2), $v['price'],2);
                                            $sprice = bcdiv(bcmul($sv['price'], $statusModel->profit_rate, 2), 100, 2);
                                            $price[] = ['groupon_num' => $v['groupon_num'], 'price' => $sv['price'], 'origin_price' => $statusModel->origin_price, 'purchase_price' => $v['price'], 'c_profit' => $cprice, 's_profit' => $sprice];
                                        } else {
                                            $price[] = ['groupon_num' => $v['groupon_num'], 'price' => $sv['price'], 'origin_price' => $statusModel->origin_price, 'purchase_price' => $v['price'], 'c_profit' => '', 's_profit' => ''];
                                        }
                                    }
                                }
                            }

//
                        } else {
                            //无售后阶梯价
                            $price[] = ['groupon_num' => $v['groupon_num'], 'price' => '', 'origin_price' => $statusModel->origin_price, 'purchase_price' => $v['price'], 'c_profit' => '', 's_profit' => ''];
                        }
                    }
                }

            }

            $nopriceset = false;
            if (Yii::$app->session->get('username') != 'admin') {
                $aulist = explode('|', Yii::$app->session->get('authlist'));

                if (!in_array('groupbuyprice', $aulist)) {
                    $nopriceset = true;
                }

            }
            if(!$isCity){
                if($statusModel->status==2 ||$statusModel->status==4){
                    $orderInfo = Orderdetail::find()->leftJoin("shop",'order_detail.shop_id=shop.id')->where("order_detail.promotion_id=".$rs->promotion_id.' and order_detail.status not in ("WAIT_PAY","CANCEL_BY_SYSTEM","CANCEL_BY_USER") and shop.area like "%'.$statusModel->area.'%"')->one();
                    if(!$orderInfo){
                        $status = false;
                    }
                }elseif (!$statusModel->status){
                    $status = false;
                }
            }else{
                //分公司
                if(!$statusModel->status){
                    $status = false;
                }
            }






            return ['area'=>$area,'citys'=>$citys,'rs' => $price, 'status' => $status,'nopriceset'=>$nopriceset,'priceInfo'=>['oPrice'=>$statusModel->origin_price,'profit_rate'=>$statusModel->profit_rate,'price'=>$statusModel->price]];
        }
    }

    /*获得状态信息*/
    public function actionStatus()
    {
        Yii::$app->response->format = 'json';
        $groupId = Yii::$app->request->get('id');
        if (!$groupId) {
            return [];
        }
        $rs = Groupbuystatus::find()->leftJoin('admin', 'admin.area=promotion_groupon_status.area')->leftJoin('admin b',"b.id=promotion_groupon_status.admin_id")->where('admin.admin_role_id=9 and groupon_id=' . $groupId)->select('promotion_groupon_status.*,admin.city,b.username as admin_name')->asArray()->all();
        if ($rs) {
            foreach ($rs as $k => $v) {
//                switch ($v['status']) {
//                    case '0':
//                        $rs[$k]['status'] = "待审核";
//                        break;
//                    case '1':
//                        $rs[$k]['status'] = "已审核";
//                        break;
//                    case '2':
//                        $rs[$k]['status'] = "已拒绝";
//
//                        break;
//                    case '3':
//                        $rs[$k]['status'] = "已审核";
//
//                        break;
//                    case '4':
//                        $rs[$k]['status'] = "已拒绝";
//
//                        break;
//
//                }
                $rs[$k]['oper_time'] = $rs[$k]['oper_time'] ? date("Y-m-d H:i:s", $v['oper_time']) : null;

            }
        }
        return $rs;


    }

    public function actionLists()
    {
        Yii::$app->response->format = 'json';
        $where = [];
        $city = [];
        $isCity = false;
        $curPage = 1;
        $pageSize = Yii::$app->params['pagesize'];
        $rs = [];
       //  $pageSize = 2;

        $offset = $pageSize * ($curPage - 1);
        $query = Groupbuy::find()->leftJoin("supplier", 'supplier.id=promotion_groupon.supplier_id');
        if (Yii::$app->session->get('parent_admin_role_id') == '2') {
            $area = Yii::$app->session->get('role_area');
            $where[] = 'promotion_groupon_status.area="' .$area. '"';
            $isCity = true;
            $query = $query->leftJoin('promotion_groupon_status','promotion_groupon_status.groupon_id=promotion_groupon.id')->select("promotion_groupon.*,supplier.supplier_name,promotion_groupon_status.status as city_check_status,promotion_groupon_status.sold_out as company_sold_out,promotion_groupon_status.reason");

         }  else {
//            $city = Admin::find()->select('company_name as label')->leftJoin('admin_role', 'admin.admin_role_id=admin_role.id')->distinct()->where(' admin_role.parent_id=2 ')->asArray()->all();
//            array_unshift($city, ['label' => '全国', 'value' => 0]);
            $query = $query ->select("promotion_groupon.*,supplier.supplier_name");
            }
        if (Yii::$app->request->isPost) {
            $post = Yii::$app->request->post();
            $curPage = isset($post['curPage'])?$post['curPage']:1;
            $where = $this->search($post, $where);
        }
        $query = $where ?$query->where(implode(' and ', $where)) : $query;
        $offset = $pageSize*($curPage-1);
        $totalPage = $query->count();
        $rs = $query->asArray()->limit($pageSize)->offset($offset)->orderby('promotion_groupon.id desc')->all();
        if ($rs) {
            foreach ($rs as $k => $v) {
                $rs[$k]['notice_time'] = date("Y-m-d H:i:s", $v['notice_time']);
                $rs[$k]['begin_time'] = date("Y-m-d H:i:s", $v['begin_time']);
                $rs[$k]['end_time'] = date("Y-m-d H:i:s", $v['end_time']);
                // $rs[$k]['supplier_type'] = $v['supplier_type'] ? "自营" : "他营";
                //  $rs[$k]['settllment_type'] = $v['settllment_type'] == 1 ? "月结" : "现结";
                // $rs[$k]['status'] = $v['status'] == 1 ? "通过" : ($v['status'] == 2 ? "禁用" : "未审核");
            }






        }
        $groupbuycheck=false;
        $enableSend = false;
        $enableCoupon = false;

        if (Yii::$app->session->get('username') != 'admin') {
            $aulist = explode('|', Yii::$app->session->get('authlist'));

            if (!in_array('groupbuycheck', $aulist)) {
                $groupbuycheck = true;

            }
            if (!in_array('groupbuysend', $aulist)) {
                $enableSend = true;
            }
            if (!in_array('grouponsetcoupon', $aulist)) {
                $enableCoupon = true;

            }


        }
        $supplier_type = [['label' => '全部', 'value' => '3'], ['label' => '自营', 'value' => '1'], ['label' => '他营', 'value' => '0']];

        return ['isCity' => $isCity, 'curPage' => $curPage, 'pageSize' => $pageSize, 'totalPage' => $totalPage, 'rs' => $rs, 'city' => $city, 'curstatus' => $supplier_type,'groupbuycheck'=>$groupbuycheck,'enableSend'=>$enableSend,'enableCoupon'=>$enableCoupon];


    }


    //search
    private function search($search, $where)
    {
        foreach ($search as $k => $v) {
            if ($k == 'curpage') {
                continue;
            }
//            if ($k == 'curstatus') {
//                    $where[] = ' supplier.supplier_type=' . $v;
//                    continue;
//            }
            if (trim($search[$k])) {
                if ($k == 'description') {
                    $where[] = 'promotion_groupon.description like "%' . trim($v) . '%"';
                }
                if ($k == 'sku_title') {
                    $where[] = 'promotion_groupon.sku_title like "%' . trim($v) . '%"';
                }
                if ($k == 'supplier_name') {
                    $where[] = ' supplier.supplier_name like "%' . trim($v) . '%"';
                }
//                if ($k == 'city') {
//                    if ($v == '全国' || !$v) {
//                        continue;
//                    }
//                    $citys = explode(',', $v);
//                    $where[] = ' supplier.city ="' . $citys[1] . '"';
//                }

            }
        }
        return $where;
    }

}
