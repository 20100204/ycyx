<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/2/9
 * Time: 09:26
 */

namespace app\controllers;


use app\common\behavior\NoCsrs;
use app\models\Admin;
use app\models\Settlement;
use app\models\Settlementlog;
use app\models\Shop;
use app\models\Shoporderprofitrate;
use app\models\Shopprofitdetail;
use app\models\Userbanlancelog;
use app\models\Userrelshop;
use app\models\Userrelwechatapp;
use app\models\Userwithdrawalapply;
use app\models\Userwithdrawalapply_log;
use app\models\Ycypuser;
use yii\db\Exception;
use yii\web\Controller;
use Yii;

class SettlementController extends Controller
{
    public function behaviors()
    {
        return [
            'csrf' => [
                'class' => NoCsrs::className(),
                'controller' => $this,
                'actions' => [
                    'orderlist',
                    "settlementlist",
                    "ordercheck",
                    "dealcheck",
                    'lookprofitdetail',
                    "skus",
                    "storelist",
                    "newsettlementlist"
                ]
            ]
        ];
    }

    //查看分润详情
    public function actionLookprofitdetail()
    {
        Yii::$app->response->format = 'json';
        $id = Yii::$app->request->get('id');
        $query = new \yii\db\Query();
       return $query->select('a.*,b.title,b.payment,b.price,c.pic')->from('shop_order_detail_profit_rate as a ')->leftJoin('order_detail as b','a.order_detail_id=b.id')
           ->leftJoin('item_sku as c','a.sku_id=c.id')->where('a.parent_profit_rate_id='.$id)->all();

    }

    //分成到零钱
    public function actionDealcheck()
    {
        Yii::$app->response->format = 'json';

        $post = Yii::$app->request->post();
        if (!$post) {
            return ['rs' => 'false', 'msg' => '待审核订单信息不全，请联系客服人员' . __LINE__];
        }
        $orderCheckList = Shoporderprofitrate::find()->where('id in (' . implode(',', $post) . ')')->asArray()->all();
        if (!$orderCheckList) {
            return ['rs' => 'false', 'msg' => '待审核订单信息不全，请联系客服人员' . __LINE__];
        }
        $shopids = array_unique(array_column($orderCheckList, 'shop_id'));
        if (!$shopids) {
            return ['rs' => 'false', 'msg' => '待审核订单信息不全，请联系客服人员' . __LINE__];
        }
        $shopCheckAmount = [];
        $orderIds = [];
        foreach ($orderCheckList as $k => $v) {
            if ($v['status'] || $v['who_checker'] || $v['checker_time']) {
                return ['rs' => 'false', 'msg' => '待审核订单信息不全，请联系客服人员'.__LINE__];
            }
            foreach ($shopids as $shopidItem) {
                if ($v['shop_id'] == $shopidItem) {
                    @$shopCheckAmount[$shopidItem] = bcadd($shopCheckAmount[$shopidItem], $v ['profit_rate_amount'], 2);
                    $orderIds[$shopidItem][] = $v['order_id'];
                    break;
                }
            }
        }


        if (!$shopCheckAmount) {
            return ['rs' => 'false', 'msg' => '待审核订单信息不全，请联系客服人员' . __LINE__];
        }

        //判断shop表中的profit_rate是否够减去
        $shopProfitRates = Shop::find()->select('id,profit_rate_amount,shop_name')->asArray()->where('id in (' . implode(',', $shopids) . ')')->all();
        if (!$shopProfitRates) {
            return ['rs' => 'false', 'msg' => '待审核订单信息不全，请联系客服人员' . __LINE__];
        }
        //判断shopid如果不相等，则返回失败
        $shoptableIds = array_column($shopProfitRates, 'id');
        if (array_diff($shopids, $shoptableIds)) {
            return ['rs' => 'false', 'msg' => '待审核订单信息不全，请联系客服人员' . __LINE__];
        }

        foreach ($shopProfitRates as $shopProfitRateItem) {
            foreach ($shopCheckAmount as $k => $v) {
                if ($k == $shopProfitRateItem['id']) {
                    if ($shopProfitRateItem['profit_rate_amount'] < $v) {
                        return ['rs' => 'false', 'msg' => '该' . $shopProfitRateItem['shop_name'] . '的可提成余额不足，可提成余额为：' . $shopProfitRateItem['profit_rate_amount'] . ',待提成余额为：' . $v];
                    }
                    break;
                }
            }
        }

        //批量更新,减去金额，怎加日志
        $db = Yii::$app->db;
        $transaction = $db->beginTransaction();
        $time = time();
        try {
            $whoChecker = Yii::$app->session->get('admin_id');
            if (!Shoporderprofitrate::updateAll(['who_checker' => $whoChecker, 'checker_time' => $time, 'status' => 1], [
                'in', 'id', $post
            ])) {
                throw new Exception('更新shop_order_profit_rate表失败' . __LINE__);
            }
            if (!Shopprofitdetail::updateAll(['status' => 1], ['in', 'parent_profit_rate_id', $post])) {
                throw new Exception('更新shop_order_profit_rate表失败' . __LINE__);
            }
            foreach ($shopProfitRates as $shopProfitRateItem) {
                foreach ($shopCheckAmount as $k => $v) {
                    if ($k == $shopProfitRateItem['id']) {
                        if ($shopProfitRateItem['profit_rate_amount'] < $v) {
                            throw new Exception('该' . $shopProfitRateItem['shop_name'] . '的可提成余额不足，可提成余额为：' . $shopProfitRateItem['profit_rate_amount'] . ',待提成余额为：' . $v);
                        }

                        $shopModel = Shop::find()->select('profit_rate_amount,user_id,id')->where('id=' . $k)->one();
                        if (!$shopModel) {
                            throw new Exception("店东信息不全" . __LINE__);
                        }
                        $shopModel->profit_rate_amount = bcsub($shopProfitRateItem['profit_rate_amount'], $v, 2);
                        $shopModel->updated_at = $time;

                        $userModel = Ycypuser::find()->select('balance,id')->where('id=' . $shopModel->user_id)->one();
                        if (!$userModel) {
                            throw new Exception("店东信息不全" . __LINE__);
                        }
                        $userModel->balance = bcadd($userModel->balance, $v, 2);
                        $userModel->balance_msg_count =$userModel->balance_msg_count+1;
                        $userBanlanceModel = new Userbanlancelog();
                        $userBanlanceModel->user_id = $shopModel->user_id;
                        $userBanlanceModel->amount = $v;
                        $userBanlanceModel->balance =  $userModel->balance;
                        $userBanlanceModel->operation_type = 'ORDER_PROFIT_RATE';
                        $userBanlanceModel->created_at = $time;
                        $userBanlanceModel->who_checker = Yii::$app->session->get('admin_id');
                        $orderId='';
                        foreach ($orderIds as $orderKey=>$orderVal){
                            if($orderKey==$k){
                                $orderId= implode('|',$orderVal);
                            }
                        }
                        $userBanlanceModel->msg = $userBanlanceModel->who_checker . '审核了订单，给该店东分润' . $v.',包含的订单是：'.$orderId;
                        if (!$shopModel->save(false) || !$userBanlanceModel->save(false) || !$userModel->save(false)) {
                            throw new Exception("审核失败，请联系客服人员!" . __LINE__);
                        }
                        break;
                    }
                }
            }
            $transaction->commit();
            return ['rs' => 'true'];
        } catch (Exception $e) {
            $transaction->rollBack();
            return ['rs' => 'false', 'msg' => $e->getMessage() . '，请联系客服人员'];
        }


        //shop表中减去profit_rate,昨日只，判断够不够减，不要合并一个一个的来，出错信息详细的显示出来


    }

    //订单审核
    public function actionOrdercheck()
    {
        Yii::$app->response->format = 'json';
        $where = [];
        $curPage = 1;
        if (Yii::$app->request->isPost) {
            $search = Yii::$app->request->post();
            foreach ($search as $k => $v) {
                if ($k == 'curpage') {
                    $curPage = $v;
                    continue;
                }
                if ($k == 'status') {
                    if ($v == 3) {
                        continue;
                    }elseif ($v === '0') {
                        $where[] = ' a.status=0';
                    } elseif ($v == 1) {
                        $where[] = 'a.status=1';
                    }elseif ($v=='2'){
                        $where[] = 'a.status=2';
                    }

                }
                if ($search[$k]) {
                    if ($k == 'city' && $v && ($v != "全国")) {
                        $searchCity = explode(',', $v);
                        $where[] = ' shop.city="' . trim($searchCity[1]) . '"';
                        continue;
                    }
                    if ($k == 'order_id') {
                        $where[] = ' a.order_id=' . trim($v);
                    }
                    if ($k == 'shop_name') {
                        $where[] = ' shop.shop_name like "%' . trim($v) . '%"';
                    }


                }
            }

        }
        if (Yii::$app->session->get('parent_admin_role_id') == '2') {
            //如果是市公司用户who_uploader in()市公司只能看到本市的;
            $who_uploaderIds = Admin::find()->where('area="' . Yii::$app->session->get('role_area') . '"')->asArray()->select('id')->all();
            $who_uploaderIds = array_column($who_uploaderIds, 'id');
            $where[] = 'shop.admin_id in (' . implode(',', $who_uploaderIds) . ')';
            $citys = Admin::find()->select('company_name')->where('area="' . Yii::$app->session->get('role_area') . '"')->distinct()->asArray()->all();

        }else{
            $citys = Admin::find()->select('company_name')->leftJoin('admin_role', 'admin.admin_role_id=admin_role.id')->distinct()->where(' admin_role.parent_id=2 ')->asArray()->all();
            array_unshift($citys, ['company_name' => '全国']);
        }
        $where = $where ? implode(' and ', $where) : [];
        // return $where;
        $pageSize = Yii::$app->params['pagesize'];
         $curPage = $curPage ? $curPage : 1;
         $offset = $pageSize * ($curPage - 1);
        $query = new \yii\db\Query();
        $query = $query->select('a.*,shop.city,shop.shop_name,shop.area,admin.realname as checker,order.pickup_at,order.payment,aa.status as afterstatus')
            ->from('shop_order_profit_rate as a')
            ->leftJoin('order', 'a.order_id=order.id')
            ->leftJoin('shop', 'a.shop_id=shop.id')
            ->leftJoin('admin', 'a.who_checker=admin.id')
             ->leftJoin('aftersale_apply as aa','a.order_id = aa.order_id');
        if ($where) {
            $query = $query->where($where);
        }
        $totalPage = $query->count();
        $rs = $query->offset($offset)->limit($pageSize)->orderBy('a.id desc')->all();
        if ($rs) {
            foreach ($rs as $k => $v) {

                $rs[$k]['checker'] = $v['checker'] ? $v['checker'] : '--';
                $rs[$k]['pickup_at'] = date("Y-m-d H:i:s", $v['pickup_at']);
                if ($v['status']==='0') {
                    $rs[$k]['status'] = '未审核';
                } elseif ($v['status'] == 1) {
                    $rs[$k]['_disabled'] = true;
                    $rs[$k]['status'] = '已审核';
                }elseif ($v['status'] == 2){
                    $rs[$k]['_disabled'] = true;
                    $rs[$k]['status'] = '订单已申请退货';
                }
                if((time()-$v['created_at']-48*3600)<0){
                    $rs[$k]['_disabled'] = true;
                }
                //待审核的退货订单和已经审核通过的不能审核
                if($v['afterstatus']=='0'||$v['afterstatus']=='1'||$v['afterstatus']=='3'){

                    $rs[$k]['_disabled'] = true;
                }



            }
        }
        return ['rs' => $rs, 'totalPage' => $totalPage, 'pageSize' => $pageSize, 'search' => @$search, 'currpage' => $curPage,'citys' => $citys];


    }

    /*新的提现审核*/
    public function actionNewsettlementcheck(){
        Yii::$app->response->format = 'json';
        $get= Yii::$app->request->get();
        $model = Userwithdrawalapply::findOne($get['id']);
        $adminId = Yii::$app->session->get('admin_id');
        if(!$model||!$adminId){
            return ['rs'=>'false','msg'=>'参数缺失'];
        }
        if (!$model || ($model['status'] !==0) || !in_array($get['status'], [1, 2])||$model->admin_id||$model->check_time) {
            return ['rs' => 'false', 'msg' => '提现信息有误，请联系客服人员1'];
        }
        $db = Yii::$app->db;
        $transaction = $db->beginTransaction();
        $time = time();
        try {

            $model->status = $get['status'];
            $model->admin_id =$adminId;
            $model->updated_at =$time;
            $model->check_time =$time;
            if (!$model->save(false)) {
                throw new Exception('操作失败' . __LINE__);
            }
            $logModel =new Userwithdrawalapply_log();
            $logModel->settlement_id =$get['id'] ;
            $logModel->created_at = $time;
            $logModel->admin_id =$adminId ;
            if ($get['status'] == 1) {
                $op = '同意提现的操作，提现金额为：' . $model->amount;
                if (!$userInfo = Ycypuser::findOne($model->user_id)) {
                    throw new Exception("查不到用户零钱信息");
                }
                //判断用户余额是否够提
                $userInfo->balance = bcsub($userInfo->balance, $model->amount, 2);
                if ($userInfo->balance < 0) {
                    throw new Exception('用户零钱不足提现');
                }
                $userInfo->updated_at = $time;
                $userInfo->freeze_balance = bcsub($userInfo->freeze_balance,$model->amount,2); //冻结金额直接为0；
                $userInfo->balance_msg_count =$userInfo->balance_msg_count+1;
                if (!$userInfo->save(false)) {
                    throw new Exception('扣减零钱失败');
                }

                $userBanlanceModel = new Userbanlancelog();
                $userBanlanceModel->user_id = $userInfo->id;
                $userBanlanceModel->amount =  -$model->amount;
                $userBanlanceModel->balance =   $userInfo->balance;
                $userBanlanceModel->operation_type = 'TAKE_CASH';
                $userBanlanceModel->msg = "提现金额为:".$model->amount;
                $userBanlanceModel->created_at = $time;
                $userBanlanceModel->who_checker = Yii::$app->session->get('admin_id');
                if(!$userBanlanceModel->save(false)){
                    throw new \Exception("提现失败");
                }


            }
            if ($get['status'] == 2) {
                $op = '拒绝提现的操作';
            }
            $note ='后台id为:' . $adminId . '的用户对提现id为:' . $get['id'] . '，用户id为:' . $model->user_id . '的提现请求进行了' . $op;
            $logModel->note = $note;
            if (!$logModel->save(false)) {
                throw new Exception('日志操作异常');
            }
            $transaction->commit();
            return ['rs' => 'true'];
        } catch (Exception $e) {
            $transaction->rollBack();
            return ['rs' => 'false', 'msg' => $e->getMessage() . '，请联系客服人员'];
        }






    }

    //提现审核
    public function actionSettlementcheck()
    {
        Yii::$app->response->format = 'json';
        $get = Yii::$app->request->get();
        $settlementInfo = Settlement::findOne($get['id']);
        if (!$settlementInfo || ($settlementInfo['status'] != 1) || !in_array($get['status'], [2, 4])) {
            return ['rs' => 'false', 'msg' => '提现信息有误，请联系客服人员1'];
        }
        if (isset($get['enter_amount'])) {
            if ($get['enter_amount'] <= 0 && ($get['status'] == 2)) {
                return ['rs' => 'false', 'msg' => '提现信息有误，请联系客服人员2'];
            }
        }
        if (!$get['note'] && ($get['status'] == 4)) {
            return ['rs' => 'false', 'msg' => '提现信息请填写拒绝原因'];
        }
        $applyMount = $settlementInfo['apply_amount'];
        unset($settlementInfo['apply_time'], $settlementInfo['apply_amount']);
        //status=2同意 4拒绝 ,
        $db = Yii::$app->db;
        $transaction = $db->beginTransaction();
        $time = time();
        try {
            //改变状态
            $settlementInfo->status = $get['status'];
            $settlementInfo->enter_time = $time;
            $settlementInfo->who_check_admin_id = Yii::$app->session->get('admin_id') ? Yii::$app->session->get('admin_id') : 1;
            $settlementInfo->note = $get['note'] ? $get['note'] : '';
            $settlementInfo->enter_amount = @$get['enter_amount'] ? @$get['enter_amount'] : '';

            if (!$settlementInfo->save(false)) {
                throw new Exception('操作失败' . __LINE__);
            }
            //添加日志
            $logModel = new Settlementlog();
            $logModel->settlement_id = $get['id'];
            $logModel->created_at = date("Y-m-d H:i:s", $time);
            $logModel->admin_id = Yii::$app->session->get('admin_id');
            if ($get['status'] == 2) {
                $op = '同意提现的操作，提现金额为：' . $get['enter_amount'];
            }
            if ($get['status'] == 4) {
                $op = '拒绝提现的操作';
            }
            $logModel->note = '后台id为:' . $logModel->admin_id . '的用户对提现id为:' . $logModel->settlement_id . '，店东shop_id为:' . $settlementInfo->shop_id . '的提现请求进行了' . $op;
            if (!$logModel->save(false)) {
                throw new Exception('日志操作异常');
            }

            if ($get['status'] == 2) {
                //零钱减少
                if (!$shopInfo = Shop::findOne($settlementInfo->shop_id)) {
                    throw new Exception('查不到社区信息');
                }
                if (!$userInfo = Ycypuser::findOne($shopInfo->user_id)) {
                    throw new Exception("查不到用户零钱信息");
                }

                $userInfo->balance = bcsub($userInfo->balance, $get['enter_amount'], 2);
                if ($userInfo->balance < 0) {
                    throw new Exception('用户零钱不足提现');
                }
                unset($userInfo['username'], $userInfo['password'], $userInfo['realname'], $userInfo['area'], $userInfo['gender'], $userInfo['mobile'], $userInfo['status']);
                $userInfo->updated_at = $time;
                $userInfo->freeze_balance = bcsub($userInfo->freeze_balance,$applyMount,2); //冻结金额直接为0；

                if (!$userInfo->save(false)) {
                    throw new Exception('扣减零钱失败');
                }

            }


            $transaction->commit();
            return ['rs' => 'true'];
        } catch (Exception $e) {
            $transaction->rollBack();
            return ['rs' => 'false', 'msg' => $e->getMessage() . '，请联系客服人员'];
        }


    }

    /*新提现*/
public function actionNewsettlementlist(){
    Yii::$app->response->format = 'json';
    $curPage = 1;
    $where = [];
    if (Yii::$app->request->isPost) {
        $where = [];
        $search = Yii::$app->request->post();
        foreach ($search as $k => $v) {
            if ($k == 'curpage') {
                $curPage = $v;
                continue;
            }
            if ($k == 'status') {
                if ($v == 3) {
                    continue;
                }
                if(!$v){
                    if($v=='0'){
                        $where[] = ' a.status =0';
                    }

                }else{
                    $where[] = ' a.status =' .$v;
                }

            }
            if ($search[$k]) {
                if ($k == 'mobile') {
                    $where[] = ' b.mobile like "%' . trim($v) . '%"';
                }

            }
        }
        $where = $where ? implode(' and ', $where) : [];
    }
    $pageSize = Yii::$app->params['pagesize'];
    $offset = $pageSize * ($curPage - 1);
    $query = new \yii\db\Query();
    $query = $query->select("a.*,b.mobile,c.openid,c.nickname,d.username as checker,b.type as user_type")
             ->from("user_withdrawal_apply as a")
             ->leftJoin('user as b','a.user_id=b.id')
             // ->leftJoin('user_rel_wechat_app as c','a.user_id=c.user_id')
             ->leftJoin('user_rel_miniprogram as c','a.user_id=c.user_id')
             ->leftJoin('admin as d','d.id=a.admin_id')
             ->orderBy('a.id desc');
    if ($where) {
        $query = $query->where($where);
    }
    $totalPage = $query->count();
    $rs = $query->offset($offset)->limit($pageSize)->all();
    if ($rs) {
        foreach ($rs as $k => $v) {

            //如果小程序没有再从公众号取
            if($v['openid']){
                $rs[$k]['from'] = "小程序";
            }else{

                $userInfo = Userrelwechatapp::find()->where('user_id='.$v['user_id'])->asArray()->one();
                if($userInfo){
                    $rs[$k]['openid'] = $userInfo['openid'];
                    $rs[$k]['nickname'] = $userInfo['nickname'];
                    $rs[$k]['from'] = "公众号";
                }
            }
            if($v['user_type']=='guest'){
                $query = new \yii\db\Query();
                $city =  $query->select('shop.city')->from('user_rel_shop')->leftJoin("shop",'user_rel_shop.shop_id=shop.id')->where("user_rel_shop.is_checked=1 and user_rel_shop.user_id=".$v['user_id'])->select('shop.city')->one();

            }else{
                $city =  Shop::find()->where('user_id='.$v['user_id'])->asArray()->one();
            }
            if($city){
                $rs[$k]['city'] = $city['city'];
            }else{
                $rs[$k]['city'] = '';
            }
            $rs[$k]['created_at'] = date("Y-m-d H:i:s", $v['created_at']);
            $rs[$k]['check_time'] = $rs[$k]['check_time'] ? date("Y-m-d H:i:s", $v['check_time']) : '--';
            if ($v['status'] == 1) {
                $rs[$k]['status'] = '已审批';
            } elseif ($v['status'] == 2) {
                $rs[$k]['status'] = '已拒绝';
            } else{
                $rs[$k]['status'] = '待审批';
            }
        }
    }
    return ['rs' => $rs, 'totalPage' => $totalPage, 'pageSize' => $pageSize, 'search' => @$search, 'currpage' => $curPage];


}

    //提现列表
    public function actionSettlementlist()
    {
        Yii::$app->response->format = 'json';
        if (Yii::$app->request->isPost) {
            $where = [];
            $search = Yii::$app->request->post();
            foreach ($search as $k => $v) {
                if ($k == 'curpage') {
                    $curPage = $v;
                    continue;
                }
                if ($search[$k]) {
                    if ($k == 'shop_name') {
                        $where[] = ' shop.shop_name like "%' . trim($v) . '%"';
                    }
                    if ($k == 'status') {
                        if ($v == 3) {
                            continue;
                        }
                        $where[] = ' a.status =' . trim($v);
                    }

                }
            }
            $where = $where ? implode(' and ', $where) : [];
        }

        $pageSize = Yii::$app->params['pagesize'];
        @$curPage = $curPage ? $curPage : 1;
        @$offset = $pageSize * ($curPage - 1);
        $query = new \yii\db\Query();
        $query = $query->select('a.*,shop.shop_name,shop.city,shop.realname,shop.mobile,shop.area,admin.realname as checker,c.openid')
            ->from('shop_settlement as a')
            ->leftJoin('shop', 'a.shop_id=shop.id')
            ->leftJoin('admin', 'a.who_check_admin_id=admin.id')
            ->leftJoin('user_rel_wechat_app as c','shop.user_id=c.user_id')
            ->orderBy('a.enter_time desc,a.id desc');
        if (@$where) {
            $query = $query->where($where);
        }
        $totalPage = $query->count();
        $rs = $query->offset($offset)->limit($pageSize)->all();
        if ($rs) {
            foreach ($rs as $k => $v) {
                $rs[$k]['enter_amount'] = $v['enter_amount'] ? $v['enter_amount'] : '--';
                $rs[$k]['checker'] = $v['checker'] ? $v['checker'] : '--';
                $rs[$k]['apply_time'] = date("Y-m-d H:i:s", $v['apply_time']);
                $rs[$k]['enter_time'] = $rs[$k]['enter_time'] ? date("Y-m-d H:i:s", $v['enter_time']) : '--';
                if ($v['status'] == 1) {
                    $rs[$k]['status'] = '待审批';
                } elseif ($v['status'] == 2) {
                    $rs[$k]['status'] = '已审批';
                } elseif ($v['status'] == 4) {
                    $rs[$k]['status'] = '已拒绝';
                }
            }
        }
        return ['rs' => $rs, 'totalPage' => $totalPage, 'pageSize' => $pageSize, 'search' => @$search, 'currpage' => $curPage];

    }
}
