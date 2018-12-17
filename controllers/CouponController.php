<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/21
 * Time: 17:08
 */

namespace app\controllers;


use app\common\behavior\NoCsrs;
use app\models\Admin;
use app\models\Approve;
use app\models\Coupon;
use app\models\Couponcash;
use app\models\Coupondiscount;
use app\models\Couponsendlog;
use app\models\Orderdetail;
use app\models\Shop;
use app\models\Ycypuser;
use app\queue\CouponJob;
use yii\db\Exception;
use yii\web\Controller;
use Yii;

class CouponController extends Controller
{

    public function behaviors()
    {
        return [
            'csrf' => [
                'class' => NoCsrs::className(),
                'controller' => $this,
                'actions' => [
                    'typechange',
                    'add',
                    'check',
                    'edit',
                    "send",
                    "cityshop",
                    "used"
                ]
            ]
        ];
    }

    /*市公司变化时，社区的变化*/
    public function actionCityshop()
    {
        Yii::$app->response->format = 'json';
        $post = Yii::$app->request->post();
        if (!$post || !$post['area']) {
            return ['rs' => 'false', 'msg' => '参数不全'];
        }
        $shops = Shop::find()->where('status!=2 and area like "%' . $post['area'] . '%"')->select('id,shop_name')->asArray()->all();
        return ['rs' => 'true', 'datas' => $shops];
    }

    /*订单详情*/
    public function actionOrderinfo(){
        Yii::$app->response->format = 'json';
        if(!$orderId = Yii::$app->request->get("order_id")){
            return [];
        }
        $orderInfos = Orderdetail::find()->where('order_id='.$orderId)->asArray()->all();
        if($orderInfos){
            foreach ($orderInfos as $k=>$v){
                if($v['promotion_type']=='groupon'){
                    $orderInfos[$k]['promotion_type'] ='团购';
                }elseif($v['promotion_type']=='preorder'){
                    $orderInfos[$k]['promotion_type'] ='预售';
                }
                switch ($v['status']){
                    case 'WAIT_PAY':
                        $orderInfos[$k]['status'] = '待付款';
                        break;
                    case 'PAID':
                        $orderInfos[$k]['status'] = '已付款';
                        break;
                    case 'SHIPPED':
                        $orderInfos[$k]['status'] = '已发货';
                        break;
                    case 'IN_SHOP':
                        $orderInfos[$k]['status'] = '商品到店';
                        break;
                    case 'FINISHED':
                        $orderInfos[$k]['status'] = '订单完成';
                        break;
                    case 'CANCEL_BY_SYSTEM':
                        $orderInfos[$k]['status'] = '系统取消订单';
                        break;
                    case 'CANCEL_BY_USER':
                        $orderInfos[$k]['status'] = '用户取消订单';
                        break;
                    default:
                        $orderInfos[$k]['status'] = '未知状态';
                        break;
                }
            }
        }
        return $orderInfos;

    }

    /*优惠券使用记录*/
    public function actionUsed()
    {
        Yii::$app->response->format = 'json';
        $isCity = false;
        $city = [];
        $search = [];
        //$where[] = "g.is_checked=1";
        $where = [];
        $curPage = 1;
        if (Yii::$app->session->get('parent_admin_role_id') == '2') {
            $isCity = true;
            $where[] = 'f.area like "%' . Yii::$app->session->get("role_area") . '%"';
        } else {
            $city = Admin::find()->select('admin.area,admin.city')->leftJoin('admin_role', 'admin.admin_role_id=admin_role.id')->distinct()->where('admin.status=1 and admin_role.parent_id=2 and admin.admin_role_id=10')->asArray()->all();
            array_unshift($city, ['city' => '全国', 'area' => "全国"]);
        }
        if (Yii::$app->request->isPost) {
            $search = Yii::$app->request->post();
            if ($search) {
                foreach ($search as $searchKey => $searchVal) {
                    if ($searchKey == "coupon_id" && $searchVal) {
                        $where[] = " a.coupon_id=" . $searchVal;
                        continue;
                    }
                    if ($searchKey == "city" && $searchVal != "全国" && $searchVal) {
                        $where[] = ' f.area like "%' . $searchVal . '%"';
                        continue;
                    }
                    if ($searchKey == "mobile" && $searchVal) {
                        $where[] = " f.mobile=" . $searchVal;
                        continue;
                    }
                    if ($searchKey == "page") {
                        $curPage = $searchVal;
                        continue;
                    }
                    if ($searchKey == "shop_name" && $searchVal) {
                        $where[] = ' d.shop_name like "%' . $searchVal . '%"';
                        continue;
                    }
                    if ($searchKey == "title" && $searchVal) {
                        $where[] = ' e.title like "%' . $searchVal . '%"';
                        continue;
                    }
                    if ($searchKey == "coupon_type" && $searchVal && $searchVal != "all") {
                        $where[] = ' e.coupon_type="' . $searchVal . '"';
                        continue;
                    }
                    if ($searchKey == "used_at" && $searchVal && $searchVal[0] && $searchVal[1]) {
                        $start = strtotime($searchVal[0]);
                        $end = strtotime($searchVal[1]);
                        $where[] = '  a.used_at>=' . $start . ' and a.used_at<=' . $end;
                        continue;
                    }
                }
            }
        }
        $query = new \yii\db\Query();
        $query = $query->select("a.id,a.coupon_id,e.title,e.coupon_type,c.discount,f.mobile,d.city,d.shop_name,a.created_at,a.used_at,a.expired_at,a.source,e.auditor_id as coupon_amount,c.paid_at,b.order_id,c.amount,c.payment")
            ->from("user_coupon as a")
            ->leftJoin("order_detail_coupon as b", 'a.id=b.user_coupon_id')
            ->leftJoin("coupon as e", "a.coupon_id=e.id")
            ->leftJoin("user as f", 'a.user_id=f.id')
            ->leftJoin("order as c", 'b.order_id=c.id')
            ->leftJoin("shop as d", 'c.shop_id=d.id');
        $where[] = "c.paid_at is not null";
        $where = implode(" and ", $where);
        $query = $query->where($where);
        $usedAmont = 0;
        $all = $query->all();
//        /* 优惠券总金额*/
        if ($all) {
            foreach ($all as $allKey => $allVal) {
                $usedAmont = bcadd($usedAmont, $allVal['discount'], 2);
            }
        }
        $totalRecord = $query->count();
        $pageSize = Yii::$app->params['pagesize'];
        $pageSize = 50;
        $offset = $pageSize * ($curPage - 1);
        $rs = $query->limit($pageSize)->offset($offset)->orderBy("c.paid_at desc,a.id desc")->all();

        if ($rs) {
            foreach ($rs as $k => $v) {
                $rs[$k]['created_at'] = date("Y-m-d H:i:s", $v['created_at']);
                $rs[$k]['expired_at'] = date("Y-m-d H:i:s", $v['expired_at']);
                $rs[$k]['used_at'] = date("Y-m-d H:i:s", $v['used_at']);
                switch ($v['source']) {
                    case "SHARE":
                        $rs[$k]['source'] = "分享得券";
                        break;
                    case "CHECK_IN":
                        $rs[$k]['source'] = "签到得券";
                        break;
                    case "SYSTEM":
                        $rs[$k]['source'] = "系统直发";
                        break;
                    case "BUY_GIFT":
                        $rs[$k]['source'] = "购买得券";
                        break;
                }
                if ($v['coupon_type'] == 'cash') {
                    $rs[$k]['coupon_type'] = "现金券";
                    $cashModel = Couponcash::find()->where('coupon_id=' . $v['coupon_id'])->one();
                    $rs[$k]['coupon_amount'] = $cashModel->discount_amount . "元";
                } elseif ($v['coupon_type'] == 'discount') {
                    $rs[$k]['coupon_type'] = "满减券";
                    $discountModel = Coupondiscount::find()->where('coupon_id=' . $v['coupon_id'])->one();
                    $rs[$k]['coupon_amount'] = "满" . $discountModel->min_amount . "减" . $discountModel->discount_amount . "元";
                }

            }

        }

        return ['isCity' => $isCity, 'usedAmont' => $usedAmont, 'currPage' => $curPage, 'pageSize' => $pageSize, 'totalPage' => $totalRecord, 'rs' => $rs, 'city' => $city];


    }

    /* 送券*/
    public function actionSend()
    {
        Yii::$app->response->format = 'json';
        $post = Yii::$app->request->post();
        if (!$post['scope'] || !$post['couponId']) {
            return ['rs' => 'false', 'msg' => '参数缺失' . __LINE__];
        }
        $adminId = Yii::$app->session->get("admin_id");
        if ($post['scope'] == "个人" || $post['scope'] == "市公司" || $post['scope'] == "社区" || !$adminId) {
            if (!$post['select']) {
                return ['rs' => 'false', 'msg' => '参数缺失' . __LINE__];
            }
        }
        //判断是不是市公司登录和是平台的用户
        if ($post['scope'] == '个人') {
            $userInfo = Ycypuser::find()->where('mobile=' . $post['select'])->asArray()->one();
            if (!$userInfo) {
                return ['rs' => 'false', 'msg' => '不是公司的粉丝'];
            }
            if (Yii::$app->session->get('parent_admin_role_id') == '2') {
                $area = Yii::$app->session->get('role_area');
                $userarea = explode(":", $userInfo['area']);
                $userarea = $userarea[0] . ':' . $userarea[1];
                if ($userarea != $area) {
                    return ['rs' => 'false', 'msg' => "不是分公司粉丝"];
                }

            }
        }
        $db = Yii::$app->db;
        $transcation = $db->beginTransaction();
        try {
            $time = time();
            $msg = $post['scope'] . '|' . $post['couponId'] . '|' . $post['select'];
            $logModel = new Couponsendlog();
            $logModel->admin_id = $adminId;
            $logModel->coupon_id = $post['couponId'];
            $logModel->created_at = $time;
            $logModel->detail = $msg;
            $logModel->status = 3; //表示状态待定;
            if (!$logModel->save(false)) {
                throw new Exception("发送失败");
            }
            $coupon = Coupon::findOne($post['couponId']);
            //根据送的优惠券信息来确定是否要审核
            $zbCheck = false;
            if ($coupon->coupon_type == 'cash') {
                $cash = Couponcash::find()->where('coupon_id=' . $post['couponId'])->one();
                if ($cash->discount_amount > 1) {

                    $this->zbCheck($logModel->id, $cash->title);
                    $zbCheck = true;
                }

            } elseif ($coupon->coupon_type == "discount") {
                $cash = Coupondiscount::find()->where('coupon_id=' . $post['couponId'])->one();
                if (bcdiv($cash->discount_amount, $cash->min_amount, 2) > 0.1) {
                    $this->zbCheck($logModel->id, $cash->title);
                    $zbCheck = true;
                }
            }
            if (!$zbCheck) {
                Yii::$app->queue->push(new CouponJob([
                    'range' => $post['scope'],
                    'sendLogId' => $logModel->id,
                    'couponId' => $post['couponId'],
                    'expired' => $coupon->expired,
                    'title' => $coupon->title,
                    'select' => $post['select']
                ]));
            }
//            } else {
//                //
//                $logModel->checkstatus = 2;
//                if (!$logModel->save(false)) {
//                    throw new Exception("发送失败");
//                }
//            }
            $transcation->commit();
            return ["rs" => "true"];
        } catch (Exception $e) {
            $transcation->rollBack();
            return ['rs' => 'false', 'msg' => $e->getMessage()];
        }

    }

    /*插入待审批事项*/
    public function zbCheck($sendLogId, $title)
    {
        $approveModel = new Approve();
        $approveModel->approve_type = "sendcoupon";
        $approveModel->event_id = $sendLogId;
        $approveModel->title = "系统送券" . $title;
        $approveModel->created_at = time();
        $approveModel->status = 1;
        $approveModel->creater_id = Yii::$app->session->get('admin_id');
        if (!$approveModel->save(false)) {
            throw new Exception("审核失败" . __LINE__);
        }
    }

    /*送券首页*/
    public function actionIndex()
    {
        Yii::$app->response->format = 'json';
        //获取城市列表
        $city = [];
        $isCity = false;
        if (Yii::$app->session->get('parent_admin_role_id') == '2') {
            $isCity = true;
            $city = [Yii::$app->session->get('role_area')];
        } else {
            $city = Admin::find()->select('admin.area,admin.city')->leftJoin('admin_role', 'admin.admin_role_id=admin_role.id')->distinct()->where('admin.status=1 and admin_role.parent_id=2 and admin.admin_role_id=10')->asArray()->all();

        }
        $enableSend = false;

        if (Yii::$app->session->get('username') != 'admin') {
            $aulist = explode('|', Yii::$app->session->get('authlist'));

            if (!in_array('couponsend', $aulist)) {
                $enableSend = true;

            }

        }
        return ['isCity' => $isCity, 'city' => $city, 'enableSend' => $enableSend];
    }

    /*优惠券类别发生变化时*/
    public function actionTypechange()
    {
        Yii::$app->response->format = 'json';
        $post = Yii::$app->request->post();
        if (!$post['scope'] || !$post['type']) {
            return ["rs" => "false", "msg" => "参数缺失" . __LINE__];
        }
        if ($post['scope'] == "市公司" || $post['scope'] == "社区" || $post['scope'] == "个人") {
            if (!$post['select']) {
                return ["rs" => "false", "msg" => "参数缺失" . __LINE__];
            }
        }
        if ($post['scope'] == '个人') {
            $userInfo = Ycypuser::find()->where('mobile=' . $post['select'])->asArray()->one();
            if (!$userInfo) {
                return ['rs' => 'false', 'msg' => '不是公司的粉丝'];
            }
            if (Yii::$app->session->get('parent_admin_role_id') == '2') {
                $area = Yii::$app->session->get('role_area');
                $userarea = explode(":", $userInfo['area']);
                $userarea = $userarea[0] . ':' . $userarea[1];
                if ($userarea != $area) {
                    return ['rs' => 'false', 'msg' => "不是分公司粉丝"];
                }

            }
        }


        return $rs = $this->usecoupon($post['scope'], $post['type'], $post['select']);


    }

    /*根据条件返回可用券列表*/
    private function usecoupon($range, $type, $select)
    {

        $rs = [];
        $type = $type == 1 ? "cash" : "discount";
        switch ($range) {
            case "全国":
                $rs = Coupon::find()->where("scope=0 and status=1 and coupon_type='" . $type . "'")->select("id,title")->asArray()->all();
                break;
            case "市公司":
                $rs = Coupon::find()->where("(scope=0 and status=1 and coupon_type='" . $type . "') or(scope=1 and status=1 and coupon_type='" . $type . "' and scope_info like '%" . $select . "%' )")->select("id,title")->asArray()->all();
                break;
            case "社区":
                $shop = Shop::findOne($select);
                $shoparea = explode(":", $shop->area);
                $shoparea = $shoparea[0] . ':' . $shoparea[1];
                $rs = Coupon::find()->where("(scope=0 and status=1 and coupon_type='" . $type . "') or(scope=1 and status=1 and coupon_type='" . $type . "' and scope_info like '%" . $shoparea . "%' )")->select("id,title")->asArray()->all();
                break;
            case "个人":
                $userModel = Ycypuser::find()->where("mobile=" . $select)->asArray()->one();
                if ($userModel) {
                    $area = explode(":", $userModel['area']);
                    $area = $area[0] . ':' . $area[1];
                    $rs = Coupon::find()->where("(scope=0 and status=1 and coupon_type='" . $type . "') or(scope=1 and status=1 and coupon_type='" . $type . "' and scope_info like '%" . $area . "%' )")->select("id,title")->asArray()->all();
                }

                break;
        }

        return $rs;
    }
}


