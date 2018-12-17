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
use app\models\Aftersales;
use app\models\Aftersalesrefund;
use app\models\Cancelorder;
use app\models\Cancelorderrefund;
use app\models\Groupbuy;
use app\models\Makepreorder;
use app\models\Order;
use app\models\Orderdetail;
use app\models\Payment;
use app\models\Paymentdetail;
use app\models\Preorder;
use app\models\Preorderactivelog;
use app\models\Preorderbuy;
use app\models\Preorderchecklog;
use app\models\Preordersend;
use app\models\Promotion;
use app\models\Shareordergiftmoney;
use app\models\Shop;
use app\models\Shoporderprofitrate;
use app\models\Shopprofitdetail;
use app\models\Userrelminiprogram;
use app\models\Userrelwechatapp;
use app\models\Wechatapp;
use yii\db\Exception;
use yii\web\Controller;
use Yii;

class AftersalesController extends Controller
{
    public function behaviors()
    {
        return [
            'csrf' => [
                'class' => NoCsrs::className(),
                'controller' => $this,
                'actions' => [
                    'applylist',
                    'check',
                    'aftersalesrefund',
                    'refundcheck',
                    'cancelorderlist',
                    'cancelordercheck',
                    'cancelorderrefund',
                    'cancelorderrefundcheck',
                    'getpaymentid'
                ]
            ]
        ];
    }

    /*取得商户交易号*/
    public function actionGetpaymentid()
    {
        Yii::$app->response->format = 'json';
        $post = Yii::$app->request->post();
        if (!$post['user_id'] || !$post['order_id']) {
            return ['rs' => 'false', 'msg' => '数据异常，请联系系统管理员'];
        }
        $rs = Payment::find()->select('payment.order_no,payment.id')->leftJoin('payment_detail', 'payment_detail.payment_id=payment.id')->where('payment_detail.status=1 and payment_detail.user_id=' . $post['user_id'] . ' and payment_detail.order_id=' . $post['order_id'] . ' and payment.status=1')->one();
        if ($rs) {
            return ['rs' => 'true', 'datas' => $rs];
        } else {
            return ['rs' => 'false', 'msg' => "数据异常，请联系管理员"];
        }


    }

    /*openid*/
    public function actionOpenid()
    {
        Yii::$app->response->format = 'json';
        $userId = Yii::$app->request->get('user_id');
        $rs = Userrelminiprogram::find()->where('user_id=' . $userId)->select('openid')->one();
        if (!$rs) {
            $rs = Wechatapp::find()->where('user_id=' . $userId)->select('openid')->one();
        }
        if ($rs) {
            return ['rs' => 'true', 'openid' => $rs];
        } else {
            return ['rs' => 'false', 'msg' => '用户信息不全'];
        }

    }

    /*取消订单退款审核*/
    public function actionCancelorderrefundcheck()
    {
        Yii::$app->response->format = 'json';
        $post = Yii::$app->request->post();
        if (!$post['status'] || !$post['id']) {
            return ['rs' => 'false', 'msg' => '参数缺失，审核失败'];
        }

        if ($post['status'] == 2 && !$post['remark']) {
            return ['rs' => 'false', 'msg' => '参数缺失，审核失败'];
        }

        $db = Yii::$app->db;
        $transcation = $db->beginTransaction();
        try {
            if (!$model = Cancelorderrefund::findOne($post['id'])) {
                throw new Exception("参数异常，审核失败" . __LINE__);
            }
            if ($model->status == 1 || $model->status == 2 || $model->admin_id || $model->audited_at) {
                throw new Exception("审核过的单子，不能重复审核" . __LINE__);
            }

            if (!in_array($post['status'], [1, 2])) {
                throw new Exception("参数异常，审核失败" . __LINE__);
            }
            //如果是运费订单，不需要检查，也不需要更新表申请
            $orderInfo = Order::findOne($model->order_id);
            if ($orderInfo->order_type != 1) {
                //普通订单
                if (!$aftersalesModel = Cancelorder::findOne($model->order_cancel_id)) {
                    throw new Exception("数据异常,操作失败" . __LINE__);
                }
                if ($aftersalesModel->status != 1) {
                    throw new Exception("数据异常,操作失败" . __LINE__);
                }
                if ($post['status'] == 1) {
                    $aftersalesModel->status = 3;
                } else {
                    $aftersalesModel->status = 2;
                }
                $aftersalesModel->updated_at = time();
                if (!$aftersalesModel->save(false)) {
                    throw new Exception("操作数据库失败" . __LINE__);
                }
            }
            if ($post['status'] == 1) {
                //改订单状态和子订单状态为CANCEL_BY_USER
                if (!Order::updateAll(['status' => 'CANCEL_BY_USER', 'updated_at' => time()], ['id' => $model->order_id])) {
                    throw new Exception('数据异常，审核失败');
                }
                //改订单状态和子订单状态为CANCEL_BY_USER
                if (!Orderdetail::updateAll(['status' => 'CANCEL_BY_USER', 'updated_at' => time()], ['order_id' => $model->order_id])) {
                    throw new Exception('数据异常，审核失败');
                }

            }
            $model->updated_at = time();
            $model->admin_id = Yii::$app->session->get('admin_id');
            $model->audited_at = time();
            $model->status = $post['status'];
            $model->remark = @$post['remark'];
            if (!$model->admin_id) {
                throw new Exception('参数缺失，审核失败' . __LINE__);
            }

            if (!$model->save(false)) {
                throw new Exception("操作数据库失败" . __LINE__);
            }

            $transcation->commit();
            return ['rs' => 'true'];
        } catch (Exception $e) {
            $transcation->rollBack();
            return ['rs' => 'false', 'msg' => $e->getMessage()];
        }
    }

    /*取消订单退款列表*/
    public function actionCancelorderrefund()
    {
        Yii::$app->response->format = 'json';
        $where[] = 'p.status=1';
        $curPage = 1;
        $search = [];
        if (Yii::$app->request->isPost) {
            $search = Yii::$app->request->post();
            foreach ($search as $k => $v) {

                if ($k == 'curpage') {
                    $curPage = $v;
                    continue;
                }

                if ($k == 'shop_name' && !empty($v)) {
                    $where[] = ' shop.shop_name like "%' . trim($v) . '%"';
                    continue;
                }
                if ($k == 'city' && !empty($v) && ($v != "全国")) {
                    $searchCity = explode(',', $v);
                    $where[] = ' shop.city="' . trim($searchCity[1]) . '"';
                    continue;
                }
                if ($k == 'mobile' && !empty($v)) {
                    $where[] = ' user.mobile="' . trim($v) . '"';
                    continue;
                }
                if ($k == 'order_id' && trim($v)) {
                    $where[] = ' b.order_id="' . trim($v) . '"';
                    continue;
                }
                if ($k == 'status' && ($v != 3)) {
                    $where[] = ' b.status="' . trim($v) . '"';
                    continue;
                }
            }
        }


        if (Yii::$app->session->get('parent_admin_role_id') == '2') {
            //如果是市公司用户who_uploader in()市公司只能看到本市的;
            $who_uploaderIds = Admin::find()->where('area="' . Yii::$app->session->get('role_area') . '"')->asArray()->select('id')->all();
            $who_uploaderIds = array_column($who_uploaderIds, 'id');
            $where[] = ' shop. admin_id in (' . implode(',', $who_uploaderIds) . ')';
            $citys = Admin::find()->select('company_name')->where('area="' . Yii::$app->session->get('role_area') . '"')->distinct()->asArray()->all();
        } else {
            $citys = Admin::find()->select('company_name')->leftJoin('admin_role', 'admin.admin_role_id=admin_role.id')->distinct()->where(' admin_role.parent_id=2 ')->asArray()->all();
            array_unshift($citys, ['company_name' => '全国']);
        }
        $where = $where ? implode(' and ', $where) : [];
        $pageSize = Yii::$app->params['pagesize'];
        $offset = $pageSize * ($curPage - 1);
        $query = new \yii\db\Query();
        $query = $query->select('b.*,a.user_id,a.reason,shop.shop_name,shop.city,user.mobile,admin.username,p.payment_id,e.order_no')
            ->from('order_cancel_refund as b')
            ->leftJoin('order_cancel as a', 'b.order_cancel_id=a.id')
            ->leftJoin('shop', 'a.shop_id=shop.id')
            ->leftJoin('user', 'a.user_id=user.id')
            ->leftJoin('payment_detail as p', 'b.order_id=p.order_id')
            ->leftJoin('payment as e', 'p.payment_id=e.id')
            ->leftJoin('admin', 'b.admin_id=admin.id');

        if ($where) {
            $query = $query->where($where);
        }
        $totalPage = $query->count();
        $rs = $query->offset($offset)->limit($pageSize)->orderBy('a.updated_at desc,a.id desc ')->all();

        if ($rs) {
            foreach ($rs as $k => $v) {
                $rs[$k]['created_at'] = date("Y-m-d H:i:s", $v['created_at']);
                $rs[$k]['audited_at'] = $v['audited_at'] ? date("Y-m-d H:i:s", $v['audited_at']) : '';
                switch ($v['status']) {
                    case 0:
                        $rs[$k]['status'] = '未退款';
                        break;
                    case 1:
                        $rs[$k]['status'] = '已退款';
                        break;
                    case 2:
                        $rs[$k]['status'] = '已拒绝';
                        break;

                }

            }
        }
        $enableCheck = false;//审核
        //  return Yii::$app->session->get('username');
        if (Yii::$app->session->get('username') != 'admin') {
            $aulist = explode('|', Yii::$app->session->get('authlist'));
            if (!in_array('cancelorderrefundcheck', $aulist)) {
                $enableCheck = true;
            }

        }

        return ['rs' => $rs, 'totalPage' => $totalPage, 'pageSize' => $pageSize, 'search' => $search, 'currpage' => $curPage, 'citys' => $citys, 'enableCheck' => $enableCheck];


    }

    /*取消订单审核,运费订单也要退，也要改变状态*/
    public function actionCancelordercheck()
    {
        Yii::$app->response->format = 'json';
        $post = Yii::$app->request->post();
        if (!$post['id'] || !$post['status']) {
            return ['rs' => 'false', 'msg' => '参数缺失'];
        }
        $model = Cancelorder::findOne($post['id']);
        if ($model->order_type != 'preorder') {
            return ['rs' => 'false', 'msg' => '数据异常'];
        }
        if ($model->status || $model->audited_at || $model->admin_id) {
            return ['rs' => 'false', 'msg' => '数据异常'];
        }
        if ($post['status'] == 2) {
            $model->status = 2;
            $model->admin_id = Yii::$app->session->get("admin_id");
            $model->audited_at = time();
            $model->updated_at = time();
            if (!$model->admin_id) {
                return ['rs' => 'false', 'msg' => '参数缺失'];
            }
            if ($model->save(false)) {
                return ['rs' => 'true'];
            } else {
                return ['rs' => 'false', 'msg' => '审核失败，请联系管理员'];
            }

        }

        if ($post['status'] == 1) {
            //同意
            $insertData = [];
            $db = Yii::$app->db;
            $transcation = $db->beginTransaction();
            try {
                if (Cancelorderrefund::find()->where('order_cancel_id=' . $model->id)->one()) {
                    throw new Exception('数据异常，该数据已经申请过退款');
                }

                //检查是否有运费订单并生成退款,如果有任意一个已发货则不生成退运费的单，根据总订单查出所有的订单，看是否都在取消订单中，如果都在，并且都审核通过，则生成运费退款单,只有送货上门的订单才这样
                $orderInfo = Order::findOne($model->order_id);
                $sumOrderInfo = Order::find()->where("order_origin_id=" . $orderInfo->order_origin_id)->all();
                $sumCount = count($sumOrderInfo);
                $hasSendGoods = false;
                $hasOrderstatus = true;
                $sendOrderId = "";
                if ($sumCount > 1) {
                    foreach ($sumOrderInfo as $sk => $sv) {
                        if (in_array($sv['status'], ["FINISHED", "IN_SHOP", "SHIPPED"])) {
                            $hasOrderstatus = false;
                            break;
                        }
                        if ($sv['order_type'] == 1) {
                            $hasSendGoods = true;
                            $sendOrderId = $sv['id'];
                            break;
                        }

                    }

                    if ($hasOrderstatus && $hasSendGoods) {
                        $sendGoodsAfter = true;
                        //检查所有的订单是否都审核通过了
                        foreach ($sumOrderInfo as $ssk => $ssv) {
                            if ($ssv['id'] != $model->order_id && $ssv['order_type'] != 1) {
                                $otherCancelOrderInfo = Cancelorder::find()->where("order_id=" . $ssv['id'])->select("status")->asArray()->one();
                                if (!$otherCancelOrderInfo || !in_array($otherCancelOrderInfo['status'], [1, 3])) {
                                    $sendGoodsAfter = false;
                                    break;
                                }
                            }
                        }
                        if ($sendGoodsAfter) {

                            //生成送货上门运费退款记录信息
                            $sendGoodsOrderInfo = Order::findOne($sendOrderId);
                            $insertData[] = [
                                'order_cancel_id' => $sendGoodsOrderInfo->id,
                                'order_id' => $sendGoodsOrderInfo->id,
                                'amount' => $sendGoodsOrderInfo->payment,
                                'created_at' => time(),
                                'updated_at' => time(),
                            ];


                        }

                    }
                }


//                //改订单状态和子订单状态为CANCEL_BY_USER
//                if (!Order::updateAll(['status' => 'CANCEL_BY_USER', 'updated_at' => time()], ['id' => $model->order_id])) {
//                    throw new Exception('数据异常，审核失败');
//                }
//                //改订单状态和子订单状态为CANCEL_BY_USER
//                if (!Orderdetail::updateAll(['status' => 'CANCEL_BY_USER', 'updated_at' => time()], ['order_id' => $model->order_id])) {
//                    throw new Exception('数据异常，审核失败');
//                }

                $model->status = 1;
                $model->admin_id = Yii::$app->session->get("admin_id");
                $model->audited_at = time();
                $model->updated_at = time();
                if (!$model->admin_id) {
                    throw new Exception('参数缺失');
                }
                if (!$model->save(false)) {
                    throw new Exception('审核失败');
                }


                //用批量插入的试下
                $insertData[] = [
                    'order_cancel_id' => $model->id,
                    'order_id' => $model->order_id,
                    'amount' => $model->amount,
                    'created_at' => time(),
                    'updated_at' => time(),
                ];
                $sql = Yii::$app->db->queryBuilder->batchInsert(Cancelorderrefund::tableName(), ["order_cancel_id", "order_id", "amount", 'created_at', "updated_at"], $insertData);
                if (!Yii::$app->db->createCommand($sql)->execute()) {
                    throw new Exception('审核失败');
                }

                /* 只有待审核的才生成新的预售*/
                $orderDetailInfo = Orderdetail::find()->where('order_id=' . $model->order_id)->one();

                $oldPreorderModel = Preorder::find()->where('promotion_id=' . $orderDetailInfo->promotion_id)->one();
                $preorderModel = Preorder::find()->where('from_preorder_id=' . $oldPreorderModel->id)->orderBy('id desc')->one();
                if (@$preorderModel->preorder_status == 8) {
                    //更新销量和库存,只有待审核的才会更新
                    @$preorderModel->limit_num += $orderDetailInfo->quantity;;
                    @$preorderModel->store += $orderDetailInfo->quantity;;
                    if (!$preorderModel->save(false)) {
                        throw new Exception("审核失败" . __LINE__);
                    }
                    $to_preorder_id = $preorderModel->id;
                } else {
                    //重新生成一条待审核的预售，针对退货或取消订单的店东
                    $to_preorder_id = $this->makepreorder($oldPreorderModel, $model, $orderDetailInfo->quantity);
                }
                $makepreorderModel = new Makepreorder();
                $makepreorderModel->from_active_type = 2;
                $makepreorderModel->from_active_id = $model->id;
                $makepreorderModel->from_preorder_id = $oldPreorderModel->id;
                $makepreorderModel->to_preorder_id = $to_preorder_id;
                $makepreorderModel->quantity = $orderDetailInfo->quantity;
                if (!$makepreorderModel->save(false)) {
                    throw new Exception("审核失败" . __LINE__);
                }


                $transcation->commit();
                return ['rs' => 'true'];
            } catch (Exception $e) {
                $transcation->rollBack();
                return ['rs' => 'false', 'msg' => $e->getMessage()];
            }
        }
    }

    /*取消订单列表*/
    public function actionCancelorderlist()
    {
        Yii::$app->response->format = 'json';
        $where = [];
        $curPage = 1;
        $search = [];
        if (Yii::$app->request->isPost) {
            $search = Yii::$app->request->post();
            foreach ($search as $k => $v) {
                if ($k == 'curpage') {
                    $curPage = $v;
                    continue;
                }

                if ($k == 'shop_name' && trim($v)) {
                    $where[] = ' shop.shop_name like "%' . trim($v) . '%"';
                    continue;
                }
                if ($k == 'city' && $v && ($v != "全国")) {
                    $searchCity = explode(',', $v);
                    $where[] = ' shop.city="' . trim($searchCity[1]) . '"';
                    continue;
                }
                if ($k == 'mobile' && trim($v)) {
                    $where[] = ' user.mobile="' . trim($v) . '"';
                    continue;
                }
                if ($k == 'order_id' && trim($v)) {
                    $where[] = ' a.order_id="' . trim($v) . '"';
                    continue;
                }
                if ($k == 'status' && ($v != 4) && isset($v)) {
                    $where[] = ' a.status="' . trim($v) . '"';
                    continue;
                }
                if ($k == 'title' && trim($v)) {
                    $v = explode(" ", trim($v));
                    $where[] = ' a.title like "%' . (trim($v[0])) . '%"  ';
                    continue;
                }
            }
        }
        if (Yii::$app->session->get('parent_admin_role_id') == '2') {
            //如果是市公司用户who_uploader in()市公司只能看到本市的;
            $who_uploaderIds = Admin::find()->where('area="' . Yii::$app->session->get('role_area') . '"')->asArray()->select('id')->all();
            $who_uploaderIds = array_column($who_uploaderIds, 'id');
            $where[] = ' shop. admin_id in (' . implode(',', $who_uploaderIds) . ')';
            $citys = Admin::find()->select('company_name')->where('area="' . Yii::$app->session->get('role_area') . '"')->distinct()->asArray()->all();
        } else {
            $citys = Admin::find()->select('company_name')->leftJoin('admin_role', 'admin.admin_role_id=admin_role.id')->distinct()->where(' admin_role.parent_id=2 ')->asArray()->all();
            array_unshift($citys, ['company_name' => '全国']);
        }
        $where = $where ? implode(' and ', $where) : [];
        $pageSize = Yii::$app->params['pagesize'];
        $offset = $pageSize * ($curPage - 1);
        $query = new \yii\db\Query();
        $query = $query->select('a.*,shop.shop_name,shop.city,user.mobile,admin.username,user_rel_miniprogram.nickname,order.status as orderstatus')
            ->from('order_cancel as a')
            ->leftJoin('shop', 'a.shop_id=shop.id')
            ->leftJoin('user', 'a.user_id=user.id')
            ->leftJoin("order", "a.order_id = order.id")
            ->leftJoin("user_rel_miniprogram", "user.id=user_rel_miniprogram.user_id")
            ->leftJoin('admin', 'a.admin_id=admin.id');

        if ($where) {
            $query = $query->where($where);
        }
        $totalPage = $query->count();
        $rs = $query->offset($offset)->limit($pageSize)->orderBy('a.updated_at desc,a.id desc ')->all();

        if ($rs) {
            foreach ($rs as $k => $v) {

                if ($v['orderstatus'] == 'PAID') {
                    $caigouInfo = Preordersend::find()->where("order_id=" . $v['order_id'])->select("order_id")->one();
                    if ($caigouInfo) {
                        // $rs[$k]['orderstatus']="PAIDBUY";
                        $v['orderstatus'] = "PAIDBUY";
                    } else {
                        // $rs[$k]['orderstatus']="PAIDNOBUY";
                        $v['orderstatus'] = "PAIDNOBUY";
                    }
                }
                switch ($v['orderstatus']) {
                    case 'WAIT_PAY':
                        $rs[$k]['orderstatus'] = '待付款';
                        break;
                    case 'PAIDBUY':
                        $rs[$k]['orderstatus'] = '已采购待发货';
                        break;
                    case 'PAIDNOBUY':
                        $rs[$k]['orderstatus'] = '已付款待采购';
                        break;
                    case 'SHIPPED':
                        $rs[$k]['orderstatus'] = '已发货';
                        break;
                    case 'IN_SHOP':
                        $rs[$k]['orderstatus'] = '商品到店';
                        break;
                    case 'FINISHED':
                        $rs[$k]['orderstatus'] = '订单完成';
                        break;
                    case 'CANCEL_BY_SYSTEM':
                        $rs[$k]['orderstatus'] = '系统取消订单';
                        break;
                    case 'CANCEL_BY_USER':
                        $rs[$k]['orderstatus'] = '用户取消订单';
                        break;
                    default:
                        $rs[$k]['status'] = '未知状态';
                        break;
                }

                if (!$v['nickname']) {
                    $nickInfo = Userrelwechatapp::find()->where("user_id=" . $v['user_id'])->select('nickname')->one();
                    if ($nickInfo) {
                        $rs[$k]['nickname'] = $nickInfo->nickname;
                    }
                }
                $rs[$k]['created_at'] = date("Y-m-d H:i:s", $v['created_at']);
                $rs[$k]['audited_at'] = $v['audited_at'] ? date("Y-m-d H:i:s", $v['audited_at']) : '';

                switch ($v['status']) {
                    case 0:
                        $rs[$k]['status'] = '待审核';
                        break;
                    case 1:
                        $rs[$k]['status'] = '审核通过,待退款';
                        break;
                    case 2:
                        $rs[$k]['status'] = '未通过';
                        break;
                    case 3:
                        $rs[$k]['status'] = '审核通过已退款';
                        break;
                }

            }
        }
        $enableCheck = false;//审核
        //  return Yii::$app->session->get('username');
        if (Yii::$app->session->get('username') != 'admin') {
            $aulist = explode('|', Yii::$app->session->get('authlist'));
            if (!in_array('cancelorderapplycheck', $aulist)) {
                $enableCheck = true;
            }

        }

        return ['rs' => $rs, 'totalPage' => $totalPage, 'pageSize' => $pageSize, 'search' => $search, 'currpage' => $curPage, 'citys' => $citys, 'enableCheck' => $enableCheck];


    }

    /*退款审核*/
    public function actionRefundcheck()
    {
        Yii::$app->response->format = 'json';
        $post = Yii::$app->request->post();
        if (!$post['status'] || !$post['id']) {
            return ['rs' => 'false', 'msg' => '参数缺失，审核失败'];
        }
        if ($post['status'] == 1 && !$post['actual_amount']) {
            return ['rs' => 'false', 'msg' => '参数缺失，审核失败'];
        }
        if ($post['status'] == 2 && !$post['remark']) {
            return ['rs' => 'false', 'msg' => '参数缺失，审核失败'];
        }

        $db = Yii::$app->db;
        $transcation = $db->beginTransaction();
        try {
            if (!$model = Aftersalesrefund::findOne($post['id'])) {
                throw new Exception("参数异常，审核失败" . __LINE__);
            }
            if ($model->status == 1 || $model->status == 2 || $model->admin_id || $model->audited_at) {
                throw new Exception("审核过的单子，不能重复审核" . __LINE__);
            }
            if (!$aftersalesModel = Aftersales::findOne($model->aftersale_apply_id)) {
                throw new Exception("数据异常,操作失败" . __LINE__);
            }
            if ($aftersalesModel->status != 1) {
                throw new Exception("数据异常,操作失败" . __LINE__);
            }
            $model->updated_at = time();
            $model->admin_id = Yii::$app->session->get('admin_id');
            $model->audited_at = time();
            $model->status = $post['status'];
            if (!$model->admin_id) {
                throw new Exception('参数缺失，审核失败' . __LINE__);
            }
            if ($post['status'] == 1) {
                $aftersalesModel->status = 3;
                $model->actual_amount = $post['actual_amount'];
                //分享得现金，的状态改变
                if ($aftersalesModel->order_type == 'groupon') {
                    $shareMoney = Shareordergiftmoney::find()->select("amount,order_id,status")->where('order_id=' . $model->order_id)->one();
                    if ($shareMoney) {
                        if ($shareMoney->status == 1) {
                            $shareMoney->status = 2;
                            $shareMoney->updated_at = time();
                            if (!$shareMoney->save(false)) {
                                throw new Exception('数据错误，无法退款' . __LINE__);
                            }
                        } else {
                            throw new Exception('数据错误，无法退款' . __LINE__);
                        }
                    }
                }


            } elseif ($post['status'] == 2) {
                $aftersalesModel->status = 2;
                $model->remark = $post['remark'];
            } else {
                throw new Exception("参数异常，审核失败" . __LINE__);
            }
            if (!$model->save(false)) {
                throw new Exception("操作数据库失败" . __LINE__);
            }

            $aftersalesModel->updated_at = time();
            if (!$aftersalesModel->save(false)) {
                throw new Exception("操作数据库失败" . __LINE__);
            }

            $transcation->commit();
            return ['rs' => 'true'];
        } catch (Exception $e) {
            $transcation->rollBack();
            return ['rs' => 'false', 'msg' => $e->getMessage()];
        }

    }

    /*售后退款*/
    public function actionAftersalesrefund()
    {
        Yii::$app->response->format = 'json';
        $where = [];
        $where[] = 'p.status=1';
        $curPage = 1;
        $search = [];
        if (Yii::$app->request->isPost) {
            $search = Yii::$app->request->post();
            foreach ($search as $k => $v) {
                if ($k == 'curpage') {
                    $curPage = $v;
                    continue;
                }

                if ($k == 'shop_name' && $v) {
                    $where[] = ' shop.shop_name like "%' . trim($v) . '%"';
                    continue;
                }
                if ($k == 'city' && $v && ($v != "全国")) {
                    $searchCity = explode(',', $v);
                    $where[] = ' shop.city="' . trim($searchCity[1]) . '"';
                    continue;
                }
                if ($k == 'mobile' && $v) {
                    $where[] = ' user.mobile="' . trim($v) . '"';
                    continue;
                }
                if ($k == 'order_detail_id' && $v) {
                    $where[] = ' b.order_detail_id="' . trim($v) . '"';
                    continue;
                }
                if ($k == 'status' && ($v != 3) && isset($v)) {
                    $where[] = ' b.status="' . trim($v) . '"';
                    continue;
                }
            }
        }
        if (Yii::$app->session->get('parent_admin_role_id') == '2') {
            //如果是市公司用户who_uploader in()市公司只能看到本市的;
            $who_uploaderIds = Admin::find()->where('area="' . Yii::$app->session->get('role_area') . '"')->asArray()->select('id')->all();
            $who_uploaderIds = array_column($who_uploaderIds, 'id');
            $where[] = ' shop. admin_id in (' . implode(',', $who_uploaderIds) . ')';
            $citys = Admin::find()->select('company_name')->where('area="' . Yii::$app->session->get('role_area') . '"')->distinct()->asArray()->all();
        } else {
            $citys = Admin::find()->select('company_name')->leftJoin('admin_role', 'admin.admin_role_id=admin_role.id')->distinct()->where('admin.status=1 and admin_role.parent_id=2 ')->asArray()->all();
            array_unshift($citys, ['company_name' => '全国']);
        }
        $where = $where ? implode(' and ', $where) : [];
        $pageSize = Yii::$app->params['pagesize'];
        $offset = $pageSize * ($curPage - 1);
        $query = new \yii\db\Query();
        $query = $query->select('b.*,a.user_id,a.reason,shop.shop_name,shop.city,user.mobile,admin.username,p.payment_id,e.order_no')
            ->from('aftersale_refund as b')
            ->leftJoin('aftersale_apply as a', 'b.aftersale_apply_id=a.id')
            ->leftJoin('shop', 'a.shop_id=shop.id')
            ->leftJoin('user', 'a.user_id=user.id')
            ->leftJoin('payment_detail as p', 'b.order_id=p.order_id')
            ->leftJoin('payment as e', 'p.payment_id=e.id')
            ->leftJoin('admin', 'a.admin_id=admin.id');

        if ($where) {
            $query = $query->where($where);
        }
        $totalPage = $query->count();
        $rs = $query->offset($offset)->limit($pageSize)->orderBy('a.updated_at desc,a.id desc ')->all();

        if ($rs) {
            foreach ($rs as $k => $v) {
                $rs[$k]['created_at'] = date("Y-m-d H:i:s", $v['created_at']);
                $rs[$k]['audited_at'] = $v['audited_at'] ? date("Y-m-d H:i:s", $v['audited_at']) : '';
                switch ($v['status']) {
                    case 0:
                        $rs[$k]['status'] = '未退款';
                        break;
                    case 1:
                        $rs[$k]['status'] = '已退款';
                        break;
                    case 2:
                        $rs[$k]['status'] = '已拒绝';
                        break;

                }

            }
        }

        $enableCheck = false;//审核
        //  return Yii::$app->session->get('username');
        if (Yii::$app->session->get('username') != 'admin') {
            $aulist = explode('|', Yii::$app->session->get('authlist'));
            if (!in_array('aftersalesrefundcheck', $aulist)) {
                $enableCheck = true;
            }

        }
        return ['rs' => $rs, 'totalPage' => $totalPage, 'pageSize' => $pageSize, 'search' => $search, 'currpage' => $curPage, 'citys' => $citys, 'enableCheck' => $enableCheck];
    }

    /*审核退货*/
    public function actionCheck()
    {
        Yii::$app->response->format = 'json';
        $post = Yii::$app->request->post();
        if (!$post['id'] || !$post['status']) {
            return ['rs' => 'false', 'msg' => '参数缺失'];
        }
        $model = Aftersales::findOne($post['id']);
        if ($model->status || $model->audited_at || $model->admin_id) {
            return ['rs' => 'false', 'msg' => '数据异常'];
        }
        if ($post['status'] == 2) {
            $model->status = 2;
            $model->admin_id = Yii::$app->session->get("admin_id");
            $model->audited_at = time();
            $model->updated_at = time();
            if (!$model->admin_id) {
                return ['rs' => 'false', 'msg' => '参数缺失'];
            }

            $db = Yii::$app->db;
            $transcation = $db->beginTransaction();
            try {
                if (!$model->save(false)) {
                    throw new Exception('审核失败，请联系管理员' . __LINE__);
                }
                //这里要详细
                if (!Shoporderprofitrate::updateAll(['status' => 0], ['order_id' => $model->order_id, 'shop_id' => $model->shop_id])) {
                    throw new Exception('审核失败，请联系管理员' . __LINE__);
                }
                if (!Shopprofitdetail::updateAll(['status' => 0], ['order_id' => $model->order_id, 'shop_id' => $model->shop_id])) {
                    throw new Exception('审核失败，请联系管理员' . __LINE__);
                }
                $transcation->commit();
                return ['rs' => 'true'];
            } catch (Exception $e) {
                $transcation->rollBack();
                return ['rs' => 'false', 'msg' => $e->getMessage()];
            }

        }

        if ($post['status'] == 1) {

            //同意退货（都不减库存，销量和限量,如果是团购要减去销量）
            if ($model->is_damaged || (!$model->is_damaged && $model->order_type == 'groupon')) {
                //货物有损毁不会生成新的预售活动，只生成退款记录，
                $db = Yii::$app->db;
                $transcation = $db->beginTransaction();
                try {
                    if (Aftersalesrefund::find()->where('aftersale_apply_id=' . $model->id)->one()) {
                        throw new Exception('数据异常，该数据已经申请过退款');
                    }
                    $model->status = 1;
                    $model->admin_id = Yii::$app->session->get("admin_id");
                    $model->audited_at = time();
                    $model->updated_at = time();
                    if (!$model->admin_id) {
                        throw new Exception('参数缺失');
                    }
                    if (!$model->save(false)) {
                        throw new Exception('审核失败');
                    }

                    $refundModel = new Aftersalesrefund();
                    $refundModel->aftersale_apply_id = $model->id;
                    $refundModel->order_id = $model->order_id;
                    $refundModel->order_detail_id = $model->order_detail_id;
                    $refundModel->amount = $model->amount;
                    if ($model->order_type == 'groupon') {
                        $orderDetailModel = Orderdetail::findOne($model->order_detail_id);
                        $refundModel->amount = bcsub($model->amount, $orderDetailModel->refund_amount, 2);
                        $shareMoney = Shareordergiftmoney::find()->select("amount,order_id,status")->where('order_id=' . $model->order_id)->one();
                        if ($shareMoney) {
                            if ($shareMoney->status == 1) {
                                $refundModel->amount = bcsub($refundModel->amount, $shareMoney->amount, 2);
                            } else {
                                throw new Exception('数据错误，无法退款' . __LINE__);
                            }
                        }

                        //要减去销量
//                        $groupbuyModel = Groupbuy::find()->where('promotion_id='.$orderDetailModel->promotion_id)->one();
//                        $groupbuyModel->sold_out = bcsub($groupbuyModel->sold_out,$model->quantity,2);
//                        if(!$groupbuyModel->save(false)){
//                            throw new Exception('审核失败'.__LINE__);
//                        }
                    }

                    $refundModel->created_at = time();
                    $refundModel->updated_at = time();
                    if (!$refundModel->save(false)) {
                        throw new Exception('审核失败');
                    }
                    $transcation->commit();
                    return ['rs' => 'true'];
                } catch (Exception $e) {
                    $transcation->rollBack();
                    return ['rs' => 'false', 'msg' => $e->getMessage()];
                }

            } else {

                if ($model->order_type == 'preorder') {
                    //货物没损坏，生成退款记录，检测该预售活动的退货是否有生成新的预售，生成的新的预售是否结束（日期，和销售数量），结束生成新的预售活动，没结束加上库存，加上预售限量，
                    $promotionId = Orderdetail::findOne($model->order_detail_id);
                    $preorderId = Preorder::find()->where('promotion_id=' . $promotionId->promotion_id)->one();
                    $preorderModel = Preorder::find()->where('from_preorder_id=' . $preorderId->id)->orderBy('id desc')->one();

                    $db = Yii::$app->db;
                    $transcation = $db->beginTransaction();
                    try {

                        if (@$preorderModel->preorder_status == 8) {
                            //更新销量和库存,只有待审核的才会更新
                            @$preorderModel->limit_num += $model->quantity;
                            @$preorderModel->store += $model->quantity;
                            if (!$preorderModel->save(false)) {
                                throw new Exception("审核失败" . __LINE__);
                            }
                            $to_preorder_id = $preorderModel->id;
                        } else {
                            //重新生成一条待审核的预售，针对退货或取消订单的店东
                            $to_preorder_id = $this->makepreorder($preorderId, $model, $model->quantity);

                        }

                        $makepreorderModel = new Makepreorder();
                        $makepreorderModel->from_active_type = 1;
                        $makepreorderModel->from_active_id = $model->id;
                        $makepreorderModel->from_preorder_id = $preorderId->id;
                        $makepreorderModel->to_preorder_id = $to_preorder_id;
                        $makepreorderModel->quantity = $model->quantity;
                        if (!$makepreorderModel->save(false)) {
                            throw new Exception("审核失败" . __LINE__);
                        }

                        //改状态，生成退款
                        if (Aftersalesrefund::find()->where('aftersale_apply_id=' . $model->id)->one()) {
                            throw new Exception('数据异常，该数据已经申请过退款');
                        }
                        $model->status = 1;
                        $model->admin_id = Yii::$app->session->get("admin_id");
                        $model->audited_at = time();
                        $model->updated_at = time();
                        if (!$model->admin_id) {
                            throw new Exception('参数缺失');
                        }
                        if (!$model->save(false)) {
                            throw new Exception('审核失败');
                        }

                        $refundModel = new Aftersalesrefund();
                        $refundModel->aftersale_apply_id = $model->id;
                        $refundModel->order_id = $model->order_id;
                        $refundModel->order_detail_id = $model->order_detail_id;
                        $refundModel->amount = $model->amount;
                        $refundModel->created_at = time();
                        $refundModel->updated_at = time();
                        if (!$refundModel->save(false)) {
                            throw new Exception('审核失败');
                        }

                        $transcation->commit();
                        return ['rs' => 'true'];
                    } catch (Exception $e) {
                        $transcation->rollBack();
                        return ['rs' => 'false', 'msg' => $e->getMessage()];
                    }
                }


            }


        }


    }

    /*生成预售*/
    public function makepreorder($preorderModel, $model, $quantity = 0)
    {
        $time = time();
        $promotionModel = new Promotion();
        $promotionModel->promotion_type = 'preorder';
        $promotionModel->promotion_name = $preorderModel->description;
        $promotionModel->created_at = $time;
        $promotionModel->updated_at = $time;
        if (!$promotionModel->save(false)) {
            throw new Exception("insert fail promotion");
        }
        $newPreorderModel = new Preorder();
        $newPreorderModel->from_preorder_id = $preorderModel->id;
        $newPreorderModel->active_id = $model->shop_id;
        $newPreorderModel->active_type = 3;
        $newPreorderModel->area = $preorderModel->area;
        $newPreorderModel->begin_time = strtotime(date("Y-m-d"));
        $newPreorderModel->caigou_price = $preorderModel->caigou_price;
        $newPreorderModel->created_at = $time;
        $newPreorderModel->description = $preorderModel->description;
        $newPreorderModel->end_time = strtotime(date("Y-m-d"));
        $newPreorderModel->freeze_store = 0;
        $newPreorderModel->label_name = $preorderModel->label_name;
        $newPreorderModel->limit_num = $quantity ? $quantity : $model->quantity;
        $newPreorderModel->notice_time = strtotime(date("Y-m-d"));
        $newPreorderModel->origin_price = $preorderModel->origin_price;
        $newPreorderModel->parentcompany_profit_rate = $preorderModel->parentcompany_profit_rate;
        $newPreorderModel->per_limit_count = $preorderModel->per_limit_count;
        $newPreorderModel->per_limit_num = $preorderModel->per_limit_num;
        $newPreorderModel->pickup_end_time = strtotime(date("Y-m-d"));
        $newPreorderModel->pickup_time = strtotime(date("Y-m-d"));
        //$newPreorderModel->preorder_check_log_id = $preorderModel->pickup_time;  //这个要做两次
        $newPreorderModel->preorder_pic = $preorderModel->preorder_pic;
        $newPreorderModel->preorder_status = 8;
        $newPreorderModel->price = $preorderModel->price;
        $newPreorderModel->profit_rate = $preorderModel->profit_rate;
        $newPreorderModel->promotion_id = $promotionModel->id;
        $newPreorderModel->rank = $preorderModel->rank;
        $newPreorderModel->sku_id = $preorderModel->sku_id;
        $newPreorderModel->sku_title = $preorderModel->sku_title;
        $newPreorderModel->sold_out = 0;
        $newPreorderModel->store = $quantity ? $quantity : $model->quantity;
        $newPreorderModel->updated_at = $time;
        $newPreorderModel->user_created_at = $preorderModel->user_created_at;
        $newPreorderModel->who_creater = Yii::$app->session->get('admin_id');
        if (!$newPreorderModel->save(false)) {
            throw new Exception("insert fail preorder");
        }
        $preorderCheckModel = new Preorderchecklog();
        $preorderCheckModel->preorder_id = $newPreorderModel->id;
        $preorderCheckModel->created_at = date("Y-m-d H:i:s");
        $preorderCheckModel->admin_id = Yii::$app->session->get('admin_id');
        if (!$preorderCheckModel->save(false)) {
            throw new Exception('数据更新审核表时出错' . __LINE__);
        }
        $newPreorderModel->preorder_check_log_id = $preorderCheckModel->id;
        if (!$newPreorderModel->save(false)) {
            throw new Exception("insert fail preorder" . __LINE__);
        }
        //更新活动记录表
        $preorderActiveModel = new Preorderactivelog();
        $preorderActiveModel->admin_id = Yii::$app->session->get('admin_id');
        $preorderActiveModel->created_at = date("Y-m-d H:i:s");
        $preorderActiveModel->preorder_id = $newPreorderModel->id;
        $desc = "对该活动进行了创建操作";
        $preorderActiveModel->desc = $desc;
        if (!$preorderActiveModel->save(false)) {
            throw new Exception('数据插入activelog表时出错' . __LINE__);
        }
        return $newPreorderModel->id;

    }


    /*售后申请列表*/
    public function actionApplylist()
    {
        Yii::$app->response->format = 'json';
        $where = [];
        $curPage = 1;
        $search = [];
        if (Yii::$app->request->isPost) {
            $search = Yii::$app->request->post();
            foreach ($search as $k => $v) {
                if ($k == 'curpage') {
                    $curPage = $v;
                    continue;
                }

                if ($k == 'shop_name' && trim($v)) {
                    $where[] = ' shop.shop_name like "%' . trim($v) . '%"';
                    continue;
                }
                if ($k == 'city' && $v && ($v != "全国")) {
                    $searchCity = explode(',', $v);
                    $where[] = ' shop.city="' . trim($searchCity[1]) . '"';
                    continue;
                }
                if ($k == 'mobile' && trim($v)) {
                    $where[] = ' user.mobile="' . trim($v) . '"';
                    continue;
                }
                if ($k == 'order_detail_id' && $v) {
                    $where[] = ' a.order_detail_id="' . trim($v) . '"';
                    continue;
                }
                if ($k == 'status' && ($v != 4) && isset($v)) {
                    $where[] = ' a.status="' . trim($v) . '"';
                    continue;
                }
                if ($k == 'title' && $v) {
                    $v = explode(" ", trim($v));
                    $where[] = ' a.title like "%' . (trim($v[0])) . '%"  ';
                    continue;
                }
            }
        }

        if (Yii::$app->session->get('parent_admin_role_id') == '2') {
            //如果是市公司用户who_uploader in()市公司只能看到本市的;
            $who_uploaderIds = Admin::find()->where('area="' . Yii::$app->session->get('role_area') . '"')->asArray()->select('id')->all();
            $who_uploaderIds = array_column($who_uploaderIds, 'id');
            $where[] = ' shop. admin_id in (' . implode(',', $who_uploaderIds) . ')';
            $citys = Admin::find()->select('company_name')->where('area="' . Yii::$app->session->get('role_area') . '"')->distinct()->asArray()->all();
        } else {
            $citys = Admin::find()->select('company_name')->leftJoin('admin_role', 'admin.admin_role_id=admin_role.id')->distinct()->where(' admin_role.parent_id=2 ')->asArray()->all();
            array_unshift($citys, ['company_name' => '全国']);
        }
        $where = $where ? implode(' and ', $where) : [];
        $pageSize = Yii::$app->params['pagesize'];
        $offset = $pageSize * ($curPage - 1);
        $query = new \yii\db\Query();
        $query = $query->select('a.*,shop.shop_name,shop.city,user.mobile,admin.username,user_rel_miniprogram.nickname')
            ->from('aftersale_apply as a')
            ->leftJoin('shop', 'a.shop_id=shop.id')
            ->leftJoin('user', 'a.user_id=user.id')
            ->leftJoin("user_rel_miniprogram", "user.id=user_rel_miniprogram.user_id")
            ->leftJoin('admin', 'a.admin_id=admin.id');

        if ($where) {
            $query = $query->where($where);
        }
        $totalPage = $query->count();
        $rs = $query->offset($offset)->limit($pageSize)->orderBy('a.updated_at desc,a.id desc ')->all();

        if ($rs) {
            foreach ($rs as $k => $v) {
                if ($v['pics']) {
                    $pics = explode('|', $v['pics']);
                    $rs[$k]['pic'] = $pics[0];
                    $rs[$k]['pics'] = $pics;
                }
                $rs[$k]['created_at'] = date("Y-m-d H:i:s", $v['created_at']);
                $rs[$k]['audited_at'] = $v['audited_at'] ? date("Y-m-d H:i:s", $v['audited_at']) : null;
                if ($v['aftersale_type'] == 1) {
                    $rs[$k]['aftersale_type'] = '仅退款';
                } else {
                    $rs[$k]['aftersale_type'] = '退货退款';
                }
                if ($v['is_signed'] == 1) {
                    $rs[$k]['is_signed'] = '已收货';
                } else {
                    $rs[$k]['is_signed'] = '未收货';
                }
                if ($v['is_damaged'] == 1) {
                    $rs[$k]['is_damaged'] = '是';
                } else {
                    $rs[$k]['is_damaged'] = '否';
                }
                switch ($v['status']) {
                    case 0:
                        $rs[$k]['status'] = '待审核';
                        break;
                    case 1:
                        $rs[$k]['status'] = '审核通过,待退款';
                        break;
                    case 2:
                        $rs[$k]['status'] = '未通过';
                        break;
                    case 3:
                        $rs[$k]['status'] = '审核通过已退款';
                        break;
                }

                /*是否改变退款金额，如果是团购检测*/
                if ($v['order_type'] == 'groupon') {
                    $orderDetailModel = Orderdetail::findOne($v['order_detail_id']);
                    $rs[$k]['amount'] = bcsub($v['amount'], $orderDetailModel->refund_amount, 2);
                }

            }
        }


        $enableCheck = false;//审核
        //  return Yii::$app->session->get('username');
        if (Yii::$app->session->get('username') != 'admin') {
            $aulist = explode('|', Yii::$app->session->get('authlist'));
            if (!in_array('aftersalesapplycheck', $aulist)) {
                $enableCheck = true;
            }

        }


        return ['rs' => $rs, 'totalPage' => $totalPage, 'pageSize' => $pageSize, 'search' => $search, 'currpage' => $curPage, 'citys' => $citys, 'auth' => ['enableCheck' => $enableCheck]];


    }


}
