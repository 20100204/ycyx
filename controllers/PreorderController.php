<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/1/25
 * Time: 16:56
 */

namespace app\controllers;

use app\models\Admin;
use app\models\Approve;
use app\models\Category;
use app\models\Coupon;
use app\models\Couponcash;
use app\models\Coupondiscount;
use app\models\Order;
use app\models\Orderdetail;
use app\models\Preorder;
use app\models\Preorderactivelog;
use app\models\Preorderbuy;
use app\models\Preorderchecklog;
use app\models\Preordersend;
use app\models\Promotion;
use app\models\Promotioncoupon;
use app\models\PromotionLabel;
use app\models\Purchase;
use app\models\Shop;
use app\models\Sku;
use app\models\Ycypuser;
use Yii;
use app\common\behavior\NoCsrs;
use yii\db\Exception;
use yii\web\Controller;

class PreorderController extends Controller
{
    public function behaviors()
    {
        return [
            'csrf' => [
                'class' => NoCsrs::className(),
                'controller' => $this,
                'actions' => [
                    'coupons',
                    'list',
                    "add",
                    'edit',
                    'editsave',
                    'down',
                    'caigouexport',
                    'sendgoods',
                    'sends',
                    'activerange',
                    'lookactive',
                    'cmplist',
                    'up',
                    'querycaigou',
                    'makecaigou',
                    'querycaitiao',
                    'nowcaiquery',
                    'newcaigou',
                    'buynocai',
                    'newmakecaigou',
                    'buyhistory',
                    'sendhistory',
                    'nosend',
                    'newsends',
                    'repeat',
                    'shops',
                    'purchase',
                    'excludeshops',
                ]
            ]
        ];
    }


    /*实际采购*/
    public function actionPurchase()
    {
        Yii::$app->response->format = 'json';
        if (Yii::$app->request->isPost) {
            $post = Yii::$app->request->post();
            if (!$post['amount'] || !$post['buyer'] || !$post['preorder_id'] || !$post['price'] || !$post['quantity'] || !$post['trade_no']) {
                return ['rs' => 'false', 'msg' => '参数缺失，无法插入'];
            }
            $time = time();
            $purchaseModel = new Purchase();
            $purchaseModel->active_type = 1;
            $purchaseModel->active_id = $post['preorder_id'];
            $purchaseModel->quantity = $post['quantity'];
            $purchaseModel->amount = $post['amount'];
            $purchaseModel->price = $post['price'];
            $purchaseModel->trade_no = $post['trade_no'];
            $purchaseModel->buyer = $post['buyer'];
            $purchaseModel->remark = $post['remark'];
            $purchaseModel->trande_type = 1;
            $purchaseModel->created_at = $time;
            if ($purchaseModel->save(false)) {
                $post['created_at'] = date("Y-m-d H:i:s", $time);
                return ['rs' => 'true', 'datas' => $post];
            }
            return ['rs' => 'false', 'msg' => '插入记录失败'];
            //添加记录
        } else {
            //获取列表
            $rs = Purchase::find()->where('active_type=1 and active_id=' . Yii::$app->request->get('id'))->all();
            if ($rs) {
                foreach ($rs as $k => $v) {
                    $rs[$k]['created_at'] = date("Y-m-d H:i:s", $v['created_at']);
                }
            }

            return $rs;
        }
    }

    /*排除的店东*/
    public function actionExcludeshops()
    {
        Yii::$app->response->format = 'json';
        $rs = [];
        $shopIds = Yii::$app->request->post('shopIds');
        return Shop::find()->select('id,shop_name,realname,mobile,city, shop_type ')->where('status=1 and id in (' . $shopIds . ')')->asArray()->all();
    }

    /*获取店东列表*/

    public function actionShops()
    {
        Yii::$app->response->format = 'json';
        $rs = [];
        $post = Yii::$app->request->post('active_id');
        $search = Yii::$app->request->post('search');
        if ($post) {
            $rs = Shop::find()->where('id in (' . implode(',', $post) . ')')->asArray()->all();
        }
        $where = '';
        if ($search) {

            foreach ($search as $k => $v) {
                //店铺类型
                if ($k == 'shop_type_selected') {
                    if (!$v) {
                        $where = $where . ' and shop_type=0';
                    }
                    if ($v == 1) {
                        $where = $where . ' and shop_type=1';
                    }
                    continue;
                }

                if (trim($v)) {
                    //店铺名称
                    if ($k == 'shop_name') {
                        $where .= ' and shop_name like "%' . $v . '%"';
                        continue;
                    }
                    //城市选择
                    if ($k == 'city_selected') {
                        if ($v == '全国') {
                            continue;
                        }
                        $city = explode(',', $v);
                        $where .= ' and city= "' . $city[1] . '"';
                        continue;
                    }
                    //店东联系人
                    if ($k == 'realname') {
                        $where .= ' and realname= "' . $v . '"';
                        continue;
                    }
                    //店东手机号
                    if ($k == 'mobile') {
                        $where .= ' and mobile= "' . $v . '"';
                        continue;
                    }

                }
            }
        }

        if (Yii::$app->session->get('parent_admin_role_id') == '2') {
            //如果是市公司用户who_uploader in()市公司只能看到本市的;
            $who_uploaderIds = Admin::find()->where('area="' . Yii::$app->session->get('role_area') . '"')->asArray()->select('id')->all();
            $who_uploaderIds = array_column($who_uploaderIds, 'id');
            $where .= ' and admin_id in (' . implode(',', $who_uploaderIds) . ')';
            $citys = Admin::find()->select('company_name')->where('area="' . Yii::$app->session->get('role_area') . '"')->distinct()->asArray()->all();
        } else {
            $citys = Admin::find()->select('company_name')->leftJoin('admin_role', 'admin.admin_role_id=admin_role.id')->distinct()->where('admin.status=1 and admin_role.parent_id=2 ')->asArray()->all();
            array_unshift($citys, ['company_name' => '全国']);
        }
        $all = [];
        $all = Shop::find()->select('id,shop_name,realname,mobile,city, shop_type ')->where('status=1 ' . $where)->asArray()->all();
        if ($all) {
            if ($rs) {
                foreach ($all as $k => $v) {
                    foreach ($rs as $rk => $rv) {
                        if ($rv['id'] == $v['id']) {
                            $all[$k]['_checked'] = true;
                        }
                    }
                    if ($v['shop_type'] == 1) {
                        $all[$k]['shop_type'] = "代理";
                    } else {
                        $all[$k]['shop_type'] = "店铺";
                    }
                }
            } else {
                foreach ($all as $k => $v) {
                    if ($v['shop_type'] == 1) {
                        $all[$k]['shop_type'] = "代理";
                    } else {
                        $all[$k]['shop_type'] = "店铺";
                    }
                }

            }
        }
        return ['shop' => $all, 'city' => $citys];
    }

    /*获取公司列表*/
    public function actionGetcompanys()
    {
        Yii::$app->response->format = 'json';

        if (Yii::$app->session->get('parent_admin_role_id') == '2') {
            // $citys = Admin::find()->select('id,company_name')->where('area="'.Yii::$app->session->get('role_area').'" and admin_role_id=10')->groupBy('company_name')->asArray()->all();

            return $citys = Admin::find()->select('id,company_name')->where('area="' . Yii::$app->session->get('role_area') . '" and admin_role_id=10')->distinct()->asArray()->all();
        } else {
            //   return $citys = Admin::find()->select('admin.id,admin.company_name')->leftJoin('admin_role', 'admin.admin_role_id=admin_role.id')->groupBy('admin.company_name')->where('admin.status=1 and admin_role.parent_id=2 and admin.admin_role_id=10')->asArray()->all();
            return $citys = Admin::find()->select('admin.id,admin.company_name')->leftJoin('admin_role', 'admin.admin_role_id=admin_role.id')->distinct()->where('admin.status=1 and admin_role.parent_id=2 and admin.admin_role_id=10')->asArray()->all();
        }


    }


    /*活动复用*/
    public function actionRepeat()
    {
        Yii::$app->response->format = 'json';
        $preorderIds = Yii::$app->request->post();
        if (!$preorderIds) {
            return ['rs' => 'false', 'msg' => '活动复用失败'];
        }
        $time = time();
        $db = Yii::$app->db;
        $transaction = $db->beginTransaction();
        try {
            foreach ($preorderIds as $k => $v) {
                $preorderModel = Preorder::findOne($v);
                $promotionModel = new Promotion();
                $promotionModel->promotion_type = 'preorder';
                $promotionModel->promotion_name = $preorderModel->description;
                $promotionModel->created_at = $time;
                $promotionModel->updated_at = $time;
                if (!$promotionModel->save(false)) {
                    throw new Exception("insert fail promotion");
                }
                $newPreorderModel = new Preorder();
                $newPreorderModel->active_id = $preorderModel->active_id;
                $newPreorderModel->active_type = $preorderModel->active_type;
                $newPreorderModel->area = $preorderModel->area;
                $newPreorderModel->fake_sold_out = $preorderModel->fake_sold_out;
                $newPreorderModel->begin_time = strtotime(date("Y-m-d"));
                $newPreorderModel->caigou_price = $preorderModel->caigou_price;
                $newPreorderModel->created_at = $time;
                $newPreorderModel->description = $preorderModel->description;
                $newPreorderModel->end_time = strtotime(date("Y-m-d"));
                $newPreorderModel->freeze_store = 0;
                $newPreorderModel->label_name = $preorderModel->label_name;
                $newPreorderModel->limit_num = $preorderModel->limit_num;
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
                $skuTitle = Sku::findOne($preorderModel->sku_id);
                $newPreorderModel->sku_title = $skuTitle->title;
                $newPreorderModel->sold_out = 0;
                $newPreorderModel->store = $preorderModel->limit_num;
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
            }
            $transaction->commit();
            return ['rs' => "true"];
        } catch (\Exception $e) {
            $transaction->rollBack();
            return ['rs' => "false", 'msg' => $e->getMessage()];
        }
    }

    /*历史发货*/
    public function actionSendhistory()
    {
        Yii::$app->response->format = 'json';
        $curPage = 1;
        $post = Yii::$app->request->post();
        $preorderId = $post['id'];
        if (!$preorderId) {
            return [];
        }
        $orderBy = 'send.send_time desc,shop.id,b.sku_id';
        $where = ' and  send.preorder_id in(' . implode(',', $preorderId) . ')';
        if (Yii::$app->session->get('parent_admin_role_id') == '2') {
            $cityArea = Yii::$app->session->get('role_area');
            $where .= ' and shop.area like "%' . $cityArea . '%"';
        }
        if (isset($post['search'])) {
            if (trim($post['search']['order_type']) == 1) {
                $orderBy = 'b.sku_id,shop.id';
            }
            if (trim($post['search']['page'])) {
                $curPage = trim($post['search']['page']);
            }
            if (trim($post['search']['sku_title'])) {
                $where .= ' and c.title like "%' . $post['search']['sku_title'] . '%"';
            }
            if (trim($post['search']['shop_name'])) {
                $where .= ' and shop.shop_name like "%' . $post['search']['shop_name'] . '%"';
            }
            if (trim($post['search']['mobile'])) {
                $where .= ' and d.mobile=' . $post['search']['mobile'];
            }
            if ($post['search']['shop_type'] == '0' || $post['search']['shop_type'] == 1) {

                if($post['search']['shop_type']==1){
                    $where .= ' and (shop.shop_type=1 or d.home_delivery=1)';

                }elseif ($post['search']['shop_type']=='0'){
                    //$where .= ' and shop.shop_type=0 and d.home_delivery=0 ';
                        $where .= ' and ((shop.shop_type=0 and d.home_delivery=0) or (shop.shop_type=0 and d.home_delivery is null)) ';

                }
              //  $where .= ' and (shop.shop_type=' . $post['search']['shop_type'] .' or d.home_delivery='.$post['search']['shop_type'].')';
            }
        }
        $query = new \yii\db\Query();
        $query = $query->select('c.specs,b.sku_title,b.id as preorder_id,b.description,d.quantity,shop.shop_name,d.receiver_name,d.address as order_address,d.mobile,d.mobile as mask_mobile,d.remark,shop.shop_type,shop.address,admin.username as sender,FROM_UNIXTIME(send.send_time) as send_time,d.home_delivery
         ')->from('preorder_send as send')
            ->leftJoin('promotion_preorder as b', 'send.preorder_id=b.id')
            ->leftJoin('order as d', 'd.id=send.order_id')
            ->leftJoin('order_detail as c', 'd.id=c.order_id')
            ->leftJoin('shop', 'd.shop_id=shop.id')
            ->leftJoin('admin', 'send.sender_id=admin.id')
            ->where('send.status=2 ' . $where)->orderBy($orderBy);
        $print = $query->all();
        $pageSize = 80;
        $offset = $pageSize * ($curPage - 1);
        $rs = $query->offset($offset)->limit($pageSize)->all();
        $count = count($print);
        if ($rs) {
            foreach ($print as $pk => $pv) {
                if ($pv['shop_type'] == 1||$pv['home_delivery']==1) {
                    $print[$pk]['address'] = $pv['order_address'];
                    $print[$pk]['men_address'] = $pv['order_address'];
                    @$print[$pk]['getgoods']="送货上门";
                } else {
                    $print[$pk]['men_address'] = "";
                    @$print[$pk]['getgoods']="到店自取";
                }
                $str_split = str_split($pv['mask_mobile'], 4);
                $str_split[1] = '****';
                $print[$pk]['mask_mobile'] = implode($str_split);
                //获取分类名称
                $skuId = Preorder::findOne($pv['preorder_id']);
                $skuInfo = Sku::findOne($skuId->sku_id);
                if ($skuInfo->cat_id) {
                    $catInfo = Category::findOne($skuInfo->cat_id);
                    if ($catInfo) {
                        if ($catInfo->level != 1) {
                            $catInfo = Category::findOne($catInfo->top_cat_id);
                        }
                        @$print[$pk]['cat_name'] = $catInfo->cat_name;
                    }

                }
            }

            foreach ($rs as $k => $v) {
                if ($v['shop_type'] == 1||$v['home_delivery']) {
                    $rs[$k]['address'] = $v['order_address'];
                    @$rs[$k]['getgoods']="送货上门";
                }else{
                    @$rs[$k]['getgoods']="到店自取";
                }
                @$rs[$k]['sn'] = $pageSize * ($curPage - 1) + $k + 1;

                $str_split = str_split($v['mask_mobile'], 4);
                $str_split[1] = '****';
                $rs[$k]['mask_mobile'] = implode($str_split);


                //获取分类名称
                $skuId = Preorder::findOne($v['preorder_id']);
                $skuInfo = Sku::findOne($skuId->sku_id);
                if ($skuInfo->cat_id) {
                    $catInfo = Category::findOne($skuInfo->cat_id);
                    if ($catInfo) {
                        if ($catInfo->level != 1) {
                            $catInfo = Category::findOne($catInfo->top_cat_id);
                        }
                        @$rs[$k]['cat_name'] = $catInfo->cat_name;
                    }

                }
            }
        }

        return ['rs' => $rs, 'print' => $print, 'count' => $count, 'pageSize' => $pageSize];

        // return $rs;

    }

    /*发货*/
    public function actionNewsends()
    {
        Yii::$app->response->format = 'json';
        $post = Yii::$app->request->post();
        if (!$post) {
            return ['rs' => 'false', 'msg' => '发货数据为空'];
        }
        $db = Yii::$app->db;
        $time = time();
        $transcation = $db->beginTransaction();
        try {
            //做日志
            $preorderIds = array_unique(array_column($post, 'preorder_id'));
            foreach ($preorderIds as $pk => $pv) {
                $preorderActiveModel = new Preorderactivelog();
                $preorderActiveModel->preorder_id = $pv;
                $preorderActiveModel->admin_id = Yii::$app->session->get('admin_id');
                $preorderActiveModel->created_at = date("Y-m-d H:i:s", $time);
                $preorderActiveModel->desc = '对该活动做了发货处理';
                if (!$preorderActiveModel->save(false)) {
                    throw new Exception('数据插入activelog表时出错' . __LINE__);
                }
            }
            foreach ($post as $k => $v) {
                $preorderSendModel = Preordersend::findOne($v['id']);
                $preorderSendModel->sender_id = Yii::$app->session->get('admin_id');
                $preorderSendModel->send_time = $time;
                $preorderSendModel->status = 2;
                if (!$preorderSendModel->save(false)) {
                    throw new Exception("发货失败" . __LINE__);
                }

                //订单发货
                $orderModel = Order::findOne($preorderSendModel->order_id);
                $orderModel->status = 'SHIPPED';
                $orderModel->shipped_at = $time;
                //$orderModel->updated_at=$time;
                if (!$orderModel->save(false)) {
                    throw new Exception("发货失败" . __LINE__);
                }
                //子订单发货
                $subOrderModel = Orderdetail::findOne($preorderSendModel->order_detail_id);
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

    /*代发货*/
    public function actionNosend()
    {
        Yii::$app->response->format = 'json';

        $post = Yii::$app->request->post();
        $curPage = 1;
        $preorderId = $post['id'];
        // $curPage = $post['page']?$post['page']:$curPage;
        if (!$preorderId) {
            return [];
        }
        $orderBy = 'shop.id,b.sku_id';
        $where = ' and  send.preorder_id in(' . implode(',', $preorderId) . ')';
        if (Yii::$app->session->get('parent_admin_role_id') == '2') {
            $cityArea = Yii::$app->session->get('role_area');
            $where .= ' and shop.area like "%' . $cityArea . '%"';
        }
        if (isset($post['search'])) {
            if (trim($post['search']['order_type']) == 1) {
                $orderBy = 'b.sku_id,shop.id';
            }
            if (trim($post['search']['page'])) {
                $curPage = trim($post['search']['page']);
            }
            if (trim($post['search']['sku_title'])) {
                $where .= ' and c.title like "%' . $post['search']['sku_title'] . '%"';
            }
            if (trim($post['search']['shop_name'])) {
                $where .= ' and shop.shop_name like "%' . $post['search']['shop_name'] . '%"';
            }
            if (trim($post['search']['mobile'])) {
                $where .= ' and d.mobile=' . $post['search']['mobile'];
            }
            if ($post['search']['shop_type'] === '0' || $post['search']['shop_type'] == 1) {
                if($post['search']['shop_type']==1){
                    $where .= ' and (shop.shop_type=1 or d.home_delivery=1)';

                }elseif ($post['search']['shop_type']=='0'){
                    $where .= ' and ((shop.shop_type=0 and d.home_delivery=0) or (shop.shop_type=0 and d.home_delivery is null)) ';
                }
              //  $where .= ' and d.home_delivery=' . $post['search']['shop_type'];
               // $where .= ' and (shop.shop_type=' . $post['search']['shop_type'] .' or d.home_delivery='.$post['search']['shop_type'].')';
            }
        }
        // ->where('(f.status in(0,2) or isnull(f.status) )
        $query = new \yii\db\Query();
        $query = $query->select('send.id,c.specs,b.sku_title,b.id as preorder_id,b.description,d.quantity,shop.shop_name,shop.shop_type,d.receiver_name,d.address as order_address,d.mobile,d.mobile as mask_mobile,d.remark,shop.address,e.status as cancelstatus,d.home_delivery')->from('preorder_send as send')
            ->leftJoin('promotion_preorder as b', 'send.preorder_id=b.id')
            ->leftJoin('order as d', 'd.id=send.order_id')
            ->leftJoin('order_detail as c', 'd.id=c.order_id')
            ->leftJoin('order_cancel as e', 'd.id=e.order_id')
            ->leftJoin('shop', 'd.shop_id=shop.id')
            ->where('d.order_type=0 and send.status=1 and ((e.status=2) or (isnull(e.status))) ' . $where)
            ->orderBy($orderBy);
        $print = $query->all();
        $pageSize = 80;
        $offset = $pageSize * ($curPage - 1);
        $rs = $query->offset($offset)->limit($pageSize)->all();
        $count = count($print);
        if ($rs) {

            foreach ($print as $pk => $pv) {
                if ($pv['shop_type'] == 1||$pv['home_delivery']==1) {
                    @$print[$pk]['getgoods']="送货上门";
                    $print[$pk]['address'] = $pv['order_address'];
                    $print[$pk]['men_address'] = $pv['order_address'];
                } else {
                    $print[$pk]['men_address'] = "";
                    @$print[$pk]['getgoods']="到店自取";
                }
                $str_split = str_split($pv['mask_mobile'], 4);
                $str_split[1] = '****';
                $print[$pk]['mask_mobile'] = implode($str_split);
                //获取分类名称
                $skuId = Preorder::findOne($pv['preorder_id']);
                $skuInfo = Sku::findOne($skuId->sku_id);
                if ($skuInfo->cat_id) {
                    $catInfo = Category::findOne($skuInfo->cat_id);
                    if ($catInfo) {
                        if ($catInfo->level != 1) {
                            $catInfo = Category::findOne($catInfo->parent_id);
                        }
                        @$print[$pk]['cat_name'] = $catInfo->cat_name;
                    }

                }

            }

            foreach ($rs as $k => $v) {
                @$rs[$k]['sn'] = $pageSize * ($curPage - 1) + $k + 1;
//                  if(in_array($v['cancelstatus'],['0','1','3'])){
//                      $rs[$k]['_disabled'] = true ;
//                      continue;
//                  }
                //str_split
                $str_split = str_split($v['mask_mobile'], 4);
                $str_split[1] = '****';
                $rs[$k]['mask_mobile'] = implode($str_split);
//                  if(in_array($v['cancelstatus'],['1','3'])){
//                      unset($rs[$k]);
//                      continue;
//                  }
                if ($v['shop_type'] == 1||$v['home_delivery']==1) {
                    $rs[$k]['address'] = $v['order_address'];
                    @$rs[$k]['getgoods']="送货上门";
                }else{
                    @$rs[$k]['getgoods']="到店自取";
                }

                //获取分类名称
                $skuId = Preorder::findOne($v['preorder_id']);
                $skuInfo = Sku::findOne($skuId->sku_id);
                if ($skuInfo->cat_id) {
                    $catInfo = Category::findOne($skuInfo->cat_id);
                    if ($catInfo) {
                        if ($catInfo->level != 1) {
                            $catInfo = Category::findOne($catInfo->parent_id);
                        }
                        @$rs[$k]['cat_name'] = $catInfo->cat_name;
                    }

                }

            }
            // sort($rs);
        }

        return ['rs' => $rs, 'print' => $print, 'count' => $count, 'pageSize' => $pageSize];

    }

    /*生成采购计划*/
    public function actionNewmakecaigou()
    {
        Yii::$app->response->format = 'json';
        $post = Yii::$app->request->post();
        if ($post) {
            $db = Yii::$app->db;
            $transcation = $db->beginTransaction();
            try {
                $time = time();
                foreach ($post as $k => $v) {
                    $preorderbuyModel = new Preorderbuy();
                    $preorderbuyModel->buyer_id = Yii::$app->session->get('admin_id');
                    $preorderbuyModel->preorder_id = $v['id'];
                    $buytime = explode('~', $v['ordertimerange']);
                    $preorderbuyModel->buyer_start_time = strtotime($buytime[0]);
                    $preorderbuyModel->buyer_end_time = strtotime($buytime[1]);
                    $preorderbuyModel->price = $v['price'];
                    $preorderbuyModel->caigou_price = $v['caigou_price'];
                    $preorderbuyModel->buy_count = $v['nocai'];
                    $preorderbuyModel->sold_out = $v['sold_out'];
                    $preorderbuyModel->created_at = $time;
                    if (!$preorderbuyModel->save(false)) {
                        throw new Exception('生成计划失败' . __LINE__);
                    }
                    $orderIds = explode(',', $v['order_id']);
                    foreach ($orderIds as $orderKey => $orderVal) {
                        $sendModel = new Preordersend();
                        $sendModel->caigou_id = $preorderbuyModel->id;
                        $sendModel->preorder_id = $preorderbuyModel->preorder_id;
                        $sendModel->promotion_id = $v['promotion_id'];
                        $sendModel->status = 1;
                        $orderInfo = explode('|', $orderVal);
                        $sendModel->order_id = $orderInfo[0];
                        $sendModel->order_detail_id = $orderInfo[1];
                        if (!$sendModel->save(false)) {
                            throw new Exception('生成采购计划失败' . __LINE__);
                        }
                    }
                }
                $transcation->commit();
                return ['rs' => 'true'];
            } catch (Exception $e) {
                $transcation->rollBack();
            }


        }

        return ['rs' => 'false', 'msg' => $e->getMessage()];


    }

    /*采购管理待采购商品*/
    public function actionBuynocai()
    {
        Yii::$app->response->format = 'json';
        $preorderIds = Yii::$app->request->post('id');
        if (!$preorderIds) {
            return [];
        }
        $endtime = Yii::$app->request->post('endtime');
        $time = $endtime ? strtotime($endtime) : time();
        $rs = [];
        $where = '';
        if (Yii::$app->session->get('parent_admin_role_id') == '2') {
            $cityArea = Yii::$app->session->get('role_area');
            $where = ' and shop.area like "%' . $cityArea . '%"';
        }
        foreach ($preorderIds as $k => $v) {
            $orderId = Preordersend::find()->where('preorder_id=' . $v)->select('order_id,promotion_id')->asArray()->all();

            $begin_time = '';
            if ($orderId) {
                $orderIds = implode('","', array_column($orderId, 'order_id'));
                $where .= '  and b.order_id not in ("' . $orderIds . '") ';
            }
            $lasttime = Preorderbuy::find()->where('preorder_id=' . $v)->orderBy('id desc')->one();
            if ($lasttime) {
                $begin_time = $lasttime->buyer_end_time;
                // $where .= '  and b.created_at >' . $begin_time . ' ';
            }
            // return $where;
            $query = new \yii\db\Query();
            $nocai = $query->select('a.id,a.promotion_id,b.order_id,b.id as order_detail_id,a.description, a.sku_title,b.specs,a.caigou_price,a.price,a.begin_time,a.sold_out,b.quantity as nocai,f.status,b.sku_id ')
                ->from('order_detail as b')
                ->leftJoin("shop", 'b.shop_id=shop.id')
                ->leftJoin('order_cancel as f', 'b.order_id=f.order_id')
                ->leftjoin('promotion_preorder as a', 'a.promotion_id=b.promotion_id')
                //->leftJoin('item_sku as c', 'a.sku_id=c.id')
                ->where('(f.status =2 or isnull(f.status) ) and b.order_type=0 and   b.status="PAID" and b.created_at<=' . $time . '  and  a.id=' . $v . $where)
                ->all();

            if ($nocai) {
                $shuliang = array_sum(array_column($nocai, 'nocai'));
                //
                $orders = '';

                foreach ($nocai as $nok => $noV) {
                    $orders = ltrim($orders . ',' . $noV['order_id'] . '|' . $noV['order_detail_id'], ',');
                }
                if ($begin_time) {
                    @$nocai[0]['ordertimerange'] = date("Y-m-d H:i:s", $begin_time) . '~' . date("Y-m-d H:i:s", $time);
                } else {
                    @$nocai[0]['ordertimerange'] = date("Y-m-d H:i:s", $nocai[0]['begin_time']) . '~' . date("Y-m-d H:i:s", $time);
                }
                $nocai[0]['nocai'] = $shuliang;
                @$nocai[0]['order_id'] = $orders;
                //分类名称
                $skuInfo = Sku::findOne($noV['sku_id']);
                if ($skuInfo) {
                    $catInfo = Category::findOne($skuInfo->cat_id);
                    if ($catInfo) {
                        if ($catInfo->level != 1) {
                            $catInfo = Category::findOne($catInfo->parent_id);
                        }
                        @$nocai[0]['cat_name'] = $catInfo->cat_name;
                    }
                }

                //求promotion_id
                $promotionIdInfo = Preorder::findOne($v);
                $nocai[0]['sold_out'] = Orderdetail::find()->leftJoin("order_cancel", "order_detail.order_id=order_cancel.id")->where("order_detail.promotion_id=" . $promotionIdInfo->promotion_id . ' and order_detail.status in("SHIPPED","FINISHED","IN_SHOP","PAID") and ((order_cancel.status=2) or (order_cancel.status is null))')->sum("order_detail.quantity");
                $rs[] = $nocai[0];
            }
        }
        return ['rs' => $rs, 'endtime' => date("Y-m-d H:i:s", $time)];

        return [];
        // return Yii::$app->request->post();
        $orderId = Preordersend::find()->where(['in', 'preorder_id', $preorderIds])->select('order_id')->asArray()->all();
        $orderIds = implode('","', array_column($orderId, 'order_id'));
        $query = new \yii\db\Query();
        $rs = $query->select('a.id,a.promotion_id,b.order_id,b.id as order_detail_id,a.description, a.sku_title,c.specs,a.caigou_price,a.price,a.begin_time,,a.sold_out,b.quantity as nocai')
            ->from('promotion_preorder as a')
            ->leftJoin('order_detail as b', 'a.promotion_id=b.promotion_id')
            ->leftJoin('item_sku as c', 'a.sku_id=c.id')
            ->where('b.status="PAID" and b.created_at<=' . $time . ' and b.order_id not in ("' . $orderIds . '") and a.id in (' . implode(',', $preorderIds) . ')')
            ->all();

        if ($rs) {
            // $daicai = [];
            foreach ($rs as $k => $v) {
                @$rs[$v['id']]['nocai'] = $rs[$v['id']]['nocai'] ? $rs[$v['id']]['nocai'] : 0;
                @$rs[$v['id']]['nocai'] = bcadd($v['nocai'], $rs[$v['id']]['nocai']);
                @$rs[$v['id']]['id'] = $v['id'];
                @$rs[$v['id']]['promotion_id'] = $v['promotion_id'];
                @$rs[$v['id']]['order_id'] = ltrim($rs[$v['id']]['order_id'] . ',' . $v['order_id'] . '|' . $v['order_detail_id'], ',');
                // @$rs[$v['id']]['order_detail_id'] =  ltrim($rs[$v['id']]['order_detail_id'].','.$v['order_detail_id'],',');
                @$rs[$v['id']]['description'] = $v['description'];
                @$rs[$v['id']]['sku_title'] = $v['sku_title'];
                @$rs[$v['id']]['specs'] = $v['specs'];
                @$rs[$v['id']]['caigou_price'] = $v['caigou_price'];
                @$rs[$v['id']]['price'] = $v['price'];
                @$rs[$v['id']]['begin_time'] = $v['begin_time'];
                @$rs[$v['id']]['sold_out'] = $v['sold_out'];
                //$rs[$k]['_checked'] = false;
                $lasttime = Preorderbuy::find()->where('preorder_id=' . $v['id'])->orderBy('id desc')->one();
                if ($lasttime) {
                    $v['begin_time'] = $lasttime->buyer_end_time;
                }
                @$rs[$v['id']]['ordertimerange'] = date("Y-m-d H:i:s", $v['begin_time']) . '~' . date("Y-m-d H:i:s", $time);
                unset($rs[$k]);
            }
            sort($rs);
        }

        return ['rs' => $rs, 'endtime' => date("Y-m-d H:i:s", $time)];

    }

    /*采购管理历史*/
    public function actionBuyhistory()
    {
        Yii::$app->response->format = 'json';
        $preorderIds = Yii::$app->request->post();
        $where = "";
        if (Yii::$app->session->get('parent_admin_role_id') == '2') {
            $cityArea = Yii::$app->session->get('role_area');
            $where = ' and b.area = "' . $cityArea . '"';
        }
        $query = new \yii\db\Query();
        $rs = $query->select('a.preorder_id ,c.description,c.sku_title,d.specs,a.caigou_price,a.price,a.buyer_start_time,a.buyer_end_time,a.buy_count as nocai,a.sold_out,a.created_at,b.username as buyer,d.cat_id')
            ->from('preorder_buy as a')
            ->leftJoin('admin as b', 'a.buyer_id=b.id')
            ->leftJoin('promotion_preorder as c', 'a.preorder_id=c.id')
            ->leftJoin('item_sku as d', 'c.sku_id=d.id')
            ->where('a.preorder_id in(' . implode(',', $preorderIds) . ')' . $where)
            ->orderBy('a.created_at desc,a.buyer_end_time desc')
            ->all();

        if ($rs) {
            foreach ($rs as $k => $v) {
                $rs[$k]['ordertimerange'] = date("Y-m-d H:i:s", $v['buyer_start_time']) . '~' . date("Y-m-d H:i:s", $v['buyer_end_time']);
                if ($v['created_at']) {
                    $rs[$k]['created_at'] = date("Y-m-d H:i:s", $v['created_at']);
                } else {
                    $rs[$k]['created_at'] = date("Y-m-d H:i:s", $v['buyer_end_time']);
                }
                //分类名称
                $catInfo = Category::findOne($v['cat_id']);
                if ($catInfo) {
                    if ($catInfo->level != 1) {
                        $catInfo = Category::findOne($catInfo->parent_id);
                    }
                    @$rs[$k]['cat_name'] = $catInfo->cat_name;
                }

            }
        }
        return $rs;

    }

    /*标签列表*/
    public function actionLabels()
    {
        Yii::$app->response->format = 'json';
        return PromotionLabel::find()->where('id>=1')->asArray()->all();
    }

    /*查询当前是否有待采购订单 Nowcaiquery*/
    public function actionNowcaiquery()
    {
        Yii::$app->response->format = 'json';
        $post = Yii::$app->request->post();
        $starttime = strtotime($post['starttime']);
        $endtime = strtotime($post['endtime']);
        //该时间段的销量
        $buy_count = Orderdetail::find()->asArray()->where('promotion_id=' . $post['promotion_id'] . ' and sku_id=' . $post['sku_id'] . ' and is_paid=1 and created_at>=' . $starttime . ' and created_at<=' . $endtime)->count();
        if (!$buy_count) {
            return ['datas' => [], 'startcaigoutime' => $post['starttime']];
        }

        //当时的总销量
        $soldout = Orderdetail::find()->asArray()->where('promotion_id=' . $post['promotion_id'] . ' and sku_id=' . $post['sku_id'] . ' and is_paid=1 and created_at<=' . $endtime)->count();
        $skuInfo = Sku::find()->where('id=' . $post['sku_id'])->asArray()->one();
        $preorder = Preorder::find()->where('id=' . $post['preorder_id'])->asArray()->one();
        $rs['sku_title'] = $skuInfo['title'];
        $rs['specs'] = $skuInfo['specs'];
        $rs['caigou_price'] = $preorder['caigou_price'];
        $rs['price'] = $preorder['price'];
        $rs['timerange'] = $post['starttime'] . '--' . $post['endtime'];
        $rs['buy_count'] = $buy_count;
        $rs['sold_out'] = $soldout;
        $rs['buyer'] = '';
        $rs['id'] = $post['preorder_id'];//预售活动id
        return ['datas' => [$rs], 'startcaigoutime' => $post['endtime']];


    }

    /*带条件历史的查询*/
    public function actionQuerycaitiao()
    {
        Yii::$app->response->format = 'json';
        $post = Yii::$app->request->post();
        $timeRange = explode('--', $post['selecttime']);
        $starttime = strtotime($timeRange[0]);
        $endtime = strtotime($timeRange[1]);
        $caigouLog = Preorderbuy::find()->where('buyer_start_time=' . $starttime . ' and buyer_end_time=' . $endtime . ' and preorder_id=' . $post['preorder_id'])->asArray()->one();
        $skuInfo = Sku::find()->where('id=' . $post['sku_id'])->asArray()->one();
        $buyer = Admin::find()->where('id=' . $caigouLog['buyer_id'])->asArray()->one();

        $rs['sku_title'] = $skuInfo['title'];
        $rs['specs'] = $skuInfo['specs'];
        $rs['caigou_price'] = $caigouLog['caigou_price'];
        $rs['price'] = $caigouLog['price'];
        $rs['timerange'] = $post['selecttime'];
        $rs['buy_count'] = $caigouLog['buy_count'];
        $rs['sold_out'] = $caigouLog['sold_out'];
        $rs['buyer'] = $buyer['username'];

        return ['datas' => [$rs]];
    }

    /*采购查询*/
    public function actionQuerycaigou()
    {
        Yii::$app->response->format = 'json';
        $post = Yii::$app->request->post();
        if (!$post['sold_out']) {
            //还没有订单产生
            return ['datas' => [], 'startcaigoutime' => $post['begin_time'], 'endcaigoutime' => date("Y-m-d H:i:s"), 'historytimes' => []];
        }
        $history = Preorderbuy::find()->where('preorder_id=' . $post['id'])->orderBy('buyer_end_time desc')->asArray()->all();

        $historytimes = [];
        if ($history) {

            foreach ($history as $v) {
                $historytimes[] = date("Y-m-d H:i:s", $v['buyer_start_time']) . '--' . date("Y-m-d H:i:s", $v['buyer_end_time']);
            }

            $datas = [];
            $orderInfo = Orderdetail::find()->asArray()->where('promotion_id=' . $post['promotion_id'] . ' and sku_id=' . $post['sku_id'] . ' and is_paid=1 and created_at>=' . $history[0]['buyer_end_time'])->all();
            if ($orderInfo) {
                $buycount = array_column($orderInfo, 'quantity');
                $buycount = array_sum($buycount);
                $datas['id'] = $post['id'];
                $datas['specs'] = Sku::findOne($post['sku_id'])->specs;;
                $datas['sku_title'] = $post['sku_title'];
                $datas['caigou_price'] = $post['caigou_price'];
                $datas['price'] = $post['price'];
                $datas['buy_count'] = $buycount;
                $datas['startcaigoutime'] = date("Y-m-d H:i:s", $history[0]['buyer_end_time']);
                $datas['endcaigoutime'] = date("Y-m-d H:i:s");
                $datas['timerange'] = $datas['startcaigoutime'] . '~' . $datas['endcaigoutime'];
                $datas['sold_out'] = $post['sold_out'];
                return ['datas' => [$datas], 'startcaigoutime' => date('Y-m-d H:i:s', $history[0]['buyer_end_time']), 'endcaigoutime' => date("Y-m-d H:i:s"), 'historytimes' => $historytimes];
            }
            return ['datas' => [], 'startcaigoutime' => date('Y-m-d H:i:s', $history[0]['buyer_end_time']), 'endcaigoutime' => date("Y-m-d H:i:s"), 'historytimes' => $historytimes];


        } else {
            $post['specs'] = Sku::findOne($post['sku_id'])->specs;
            $post['startcaigoutime'] = $post['begin_time'];
            $post['endcaigoutime'] = date("Y-m-d H:i:s");
            $post['timerange'] = $post['startcaigoutime'] . '~' . $post['endcaigoutime'];
            $post['buy_count'] = $post['sold_out'];
            return ['datas' => [$post], 'startcaigoutime' => $post['startcaigoutime'], 'endcaigoutime' => $post['endcaigoutime'], 'historytimes' => $historytimes];
        }
        //查询现在待采购的数量默认取出这个值
        /* return $orderInfo = Orderdetail::findAll(['promotion_id' => $post['promotion_id'], 'sku_id' => $post['sku_id'], 'is_paid' => 1]);
          if ($orderInfo) {
              $post['sku_count'] = 0;
              foreach ($orderInfo as $order) {
                  $post['sku_count'] = $post['sku_count'] + $order['quantity'];
              }
          }*/


    }

    /*生成采购计划*/
    public function actionMakecaigou()
    {
        Yii::$app->response->format = 'json';
        $post = Yii::$app->request->post();
        /* 检测已经改时间段是否有生成过以后*/
        $preorderbuyModel = new Preorderbuy();
        $preorderbuyModel->buyer_id = Yii::$app->session->get('admin_id');
        $preorderbuyModel->preorder_id = $post['datas'][0]['id'];
        $preorderbuyModel->buyer_start_time = strtotime($post['startcaigoutime']);
        $endtime = strtotime($post['endcaigoutime']);
        $time = time();
        if ($endtime >= $time) {
            $preorderbuyModel->buyer_end_time = $time;
        } else {
            $preorderbuyModel->buyer_end_time = $endtime;
        }
        $preorderbuyModel->price = $post['datas'][0]['price'];
        $preorderbuyModel->caigou_price = $post['datas'][0]['caigou_price'];
        $preorderbuyModel->buy_count = $post['datas'][0]['buy_count'];
        $preorderbuyModel->sold_out = $post['datas'][0]['sold_out'];

        $db = Yii::$app->db;
        $transcation = $db->beginTransaction();
        try {
            if (!$preorderbuyModel->save(false)) {
                throw new Exception('生成计划失败' . __LINE__);
            }

            $where = 'promotion_id=' . $post['promotion_id'] . ' and sku_id=' . $post['sku_id'] . ' and is_paid=1 and created_at>=' . $preorderbuyModel->buyer_start_time .
                ' and created_at<=' . $preorderbuyModel->buyer_end_time . ' and status !="SHIPPED"';
            $orderInfo = Orderdetail::find()->asArray()
                ->where($where)->all();

            if (!$orderInfo) {
                throw new Exception("生成计划失败，查不到订单信息" . __LINE__);
            }

            foreach ($orderInfo as $v) {
                $sendModel = new Preordersend();
                $sendModel->caigou_id = $preorderbuyModel->id;
                $sendModel->preorder_id = $preorderbuyModel->preorder_id;
                $sendModel->promotion_id = $post['promotion_id'];
                $sendModel->status = 1;
                $sendModel->order_id = $v['order_id'];
                $sendModel->order_detail_id = $v['id'];
                if (!$sendModel->save(false)) {
                    throw new Exception('生成计划失败' . __LINE__);
                }
            }
            $transcation->commit();
            return ['rs' => 'true'];
        } catch (Exception $e) {
            $transcation->rollBack();
        }

        return ['rs' => 'false', 'msg' => $e->getMessage()];

    }


    //查看活动
    public function actionLookactive()
    {
        Yii::$app->response->format = 'json';
        $post = Yii::$app->request->post();
        if (@$post['admin_id']) {
            $admin = Admin::findOne($post['admin_id']);
            $rs['username'] = $admin->username;
        } else {
            $rs['username'] = '';
        }
        $skuInfo = Sku::findOne($post['sku_id']);
        $rs['pic'] = $skuInfo->pic;
        $rs['specs'] = $skuInfo->specs;
        $rs['amount'] = 0;
        $query = new \yii\db\Query();
        $queryInfo = $query->select('order.quantity,order.payment')->from('order_detail  ')->leftJoin('order', 'order_detail.order_id=order.id')->where('order_detail.promotion_id=' . $post['promotion_id'] . ' and order_detail.sku_id=' . $post['sku_id'])->all();
        if ($queryInfo) {
            foreach ($queryInfo as $v) {
                $rs['amount'] = bcadd($rs['amount'], $v['payment'], 3);
            }
        }
        $preorderModel = Preorder::find()->where('promotion_id=' . $post['promotion_id'])->asArray()->one();
        $query = new \yii\db\Query();
        $rs['logs'] = $query->select('a.desc,a.created_at,b.username')->from('preorder_active_log as a ')->leftJoin('admin as b', 'a.admin_id=b.id')->where('a.preorder_id=' . $post['preorder_id'])->orderBy('a.id desc ')->all();
        $zbCheck = false;
        //分享得券信息
        $shareCoupon = Preorder::find()->leftJoin('coupon', 'promotion_preorder.coupon_id=coupon.id')->leftJoin('admin', 'admin.id=coupon.admin_id')->where('promotion_preorder.promotion_id=' . $post['promotion_id'])->select('coupon.id,coupon.title,coupon.coupon_type,admin.city')->asArray()->one();

        if ($shareCoupon['id']) {
            if ($shareCoupon['coupon_type'] == 'cash') {
                $cash = Couponcash::find()->where('coupon_id=' . $shareCoupon['id'])->one();
                if ($cash->discount_amount > 1) {
                    $zbCheck = true;
                }

            } elseif ($shareCoupon['coupon_type'] == "discount") {
                $cash = Coupondiscount::find()->where('coupon_id=' . $shareCoupon['id'])->one();
                if (bcdiv($cash->discount_amount, $cash->min_amount, 2) > 0.1) {
                    $zbCheck = true;
                }
            }
        } else {
            $shareCoupon = [];
        }
        //参与得券
        $takecoupon = Preorder::find()->leftJoin('coupon', 'promotion_preorder.buy_gift_coupon_id=coupon.id')->leftJoin('admin', 'admin.id=coupon.admin_id')->where('promotion_preorder.promotion_id=' . $post['promotion_id'])->select('coupon.id,coupon.title,coupon.coupon_type,admin.city')->asArray()->one();
        if ($takecoupon['id']) {
            if ($preorderModel['per_buy_gift_coupon_num']) {
                $takecoupon['msg'] = "每用户最多送" . $preorderModel['per_buy_gift_coupon_num'] . '张券';
            } else {
                $takecoupon['msg'] = "每用户送券张数不限";

            }
            if ($takecoupon['coupon_type'] == 'cash') {
                $cash = Couponcash::find()->where('coupon_id=' . $takecoupon['id'])->one();
                if ($cash->discount_amount > 1) {
                    $zbCheck = true;
                }

            } elseif ($takecoupon['coupon_type'] == "discount") {
                $cash = Coupondiscount::find()->where('coupon_id=' . $takecoupon['id'])->one();
                if (bcdiv($cash->discount_amount, $cash->min_amount, 2) > 0.1) {
                    $zbCheck = true;
                }
            }
        } else {
            $takecoupon = [];
        }

        //优惠券信息
        $coupons = Promotioncoupon::find()->where('promotion_id=' . $post['promotion_id'])->asArray()->select('coupon_id')->all();
        if ($coupons) {

            $couponids = array_column($coupons, 'coupon_id');
            if ($couponids) {
                $coupons = Coupon::find()->leftJoin("admin", "coupon.admin_id=admin.id")->select('coupon.title,coupon.coupon_type,admin.city,coupon.id')->where('coupon.id in(' . implode(',', $couponids) . ')')->asArray()->all();
                if ($coupons) {
                    foreach ($coupons as $cpk => $cpv) {
                        if ($cpv['coupon_type'] == 'cash') {
                            $cash = Couponcash::find()->where('coupon_id=' . $cpv['id'])->one();
                            $amount = bcsub(bcsub($preorderModel['price'], $preorderModel['caigou_price'], 2), $cash->discount_amount, 2);
                            $coupons[$cpk]['msg'] = "如仅购一份,公司毛利润为:" . $amount;
                        } elseif ($cpv['coupon_type'] == "discount") {
                            $cash = Coupondiscount::find()->where('coupon_id=' . $cpv['id'])->one();
                            $mll = bcmul(bcdiv(bcsub($preorderModel['price'], $preorderModel['caigou_price'], 2), $preorderModel['price'], 2), 100, 2);
                            $fd = bcmul(bcdiv($cash->discount_amount, $cash->min_amount, 2), 100, 2);
                            $coupons[$cpk]['msg'] = "未使用优惠券前毛利率为:" . $mll . '%,优惠幅度为:' . $fd . '%';
                        }
                    }
                }

                if (!$zbCheck && $coupons) {
                    foreach ($coupons as $ck => $cv) {
                        if ($cv['coupon_type'] == 'cash') {
                            $cash = Couponcash::find()->where('coupon_id=' . $cv['id'])->one();

                            if ($cash->discount_amount > 1) {
                                $zbCheck = true;
                                break;
                            }

                        } elseif ($cv['coupon_type'] == "discount") {
                            $cash = Coupondiscount::find()->where('coupon_id=' . $cv['id'])->one();

                            if (bcdiv($cash->discount_amount, $cash->min_amount, 2) > 0.1) {
                                $zbCheck = true;
                                break;
                            }
                        }
                    }

                }
            }
        }

        return ['datas' => $rs, 'rs' => 'true', 'sharecoupon' => $shareCoupon, 'coupons' => $coupons, 'zbCheck' => $zbCheck, 'takecoupon' => $takecoupon];

        //  $order = Orderdetail::find()->select('')->where()->asArray()->all();


    }

    //发货
    public function actionSends()
    {
        Yii::$app->response->format = 'json';
        $post = Yii::$app->request->post();
        $orderDetailIds = array_column($post, 'orderdetailid');
        $orderIds = array_column($post, 'order_id');
        $caigouIds = array_column($post, 'id');
        $db = Yii::$app->db;
        $time = time();
        $transcation = $db->beginTransaction();
        try {
            $preorderActiveModel = new Preorderactivelog();
            $preorderActiveModel->preorder_id = $post[0]['preorder_id'];
            $preorderActiveModel->admin_id = Yii::$app->session->get('admin_id');
            $preorderActiveModel->created_at = date("Y-m-d H:i:s");
            $preorderActiveModel->desc = '对该活动做了发货处理';
            if (!$preorderActiveModel->save(false)) {
                throw new Exception('数据插入activelog表时出错' . __LINE__);
            }

            if (!Preordersend::updateAll(['sender_id' => Yii::$app->session->get('admin_id'), 'send_time' => $time, 'status' => 2], ['id' => $caigouIds]) || !Order::updateAll(['status' => 'SHIPPED', 'updated_at' => $time], ['id' => $orderIds]) || !Orderdetail::updateAll(['status' => 'SHIPPED', 'updated_at' => $time], ['id' => $orderDetailIds])) {
                throw new Exception('发货失败');
            }

            $transcation->commit();
            return ['rs' => "true"];
        } catch (Exception $e) {
            $transcation->rollBack();
            return ['rs' => "false", 'msg' => $e->getMessage()];
        }
        //old

        $promotionIds = array_column($post, 'promotion_id');
        $preorderIds = Preorder::find()->select('id')->asArray()->where(['promotion_id' => array_unique($promotionIds)])->all();
        $preorderIds = array_unique($preorderIds);
        $skuIds = array_column($post, 'sku_id');
        $order = Orderdetail::find()->select('id,promotion_id,sku_id,status')->where(['sku_id' => $skuIds, 'promotion_id' => $promotionIds])->all();
        $sendgoods = ['SHIPPED'];
        $newoids = [];
        $newois = [];
        foreach ($orderDetailIds as $k => $v) {
            foreach ($v as $vv) {
                $newoids[] = $vv;
            }
        }
        foreach ($orderIds as $k => $v) {
            foreach ($v as $vv) {
                $newois[] = $vv;
            }
        }
        foreach ($order as $orderk => $orderVal) {
            if (!in_array($orderVal['status'], $sendgoods)) {
                $allorderdetailIds[] = $orderVal['id'];
            }
        }

        //部分发货
        /* if ($rs = array_diff($allorderdetailIds, $newoids)) {
             $preorder_status = 6;
         } else {
             $preorder_status = 4;
         }*/
        //部分发货和全部发货
        $db = Yii::$app->db;
        $transcation = $db->beginTransaction();
        try {

            foreach ($preorderIds as $v) {

                $preorderActiveModel = new Preorderactivelog();
                $preorderActiveModel->preorder_id = $v['id'];
                $preorderActiveModel->admin_id = Yii::$app->session->get('admin_id') ? Yii::$app->session->get('admin_id') : 1;
                $preorderActiveModel->created_at = date("Y-m-d H:i:s");
                $preorderActiveModel->desc = '对该活动做了发货处理';
                if (!$preorderActiveModel->save(false)) {
                    throw new Exception('数据插入activelog表时出错' . __LINE__);
                }
            }

            if (!Order::updateAll(['status' => 'SHIPPED'], ['id' => $newois]) || !Orderdetail::updateAll(['status' => 'SHIPPED'], ['id' => $newoids]) || !Preorder::updateAll(['updated_at' => time()], ['promotion_id' => $promotionIds])) {
                throw new Exception('发货失败');
            }

            $transcation->commit();
            return ['rs' => "true"];
        } catch (Exception $e) {
            $transcation->rollBack();
            return ['rs' => "false", 'msg' => $e->getMessage()];
        }
    }


    //分拣发货
    public function actionSendgoods()
    {
        Yii::$app->response->format = 'json';
        $post = Yii::$app->request->post();
        $query = new \yii\db\Query();
        //  $sendGoods = ['SHIPPED'];
        // $sendGoods = implode('","', $sendGoods);
        return $shopInfo = $query->select('item_sku.specs,order_detail.title as sku_title ,order_detail.id as orderdetailid,order_detail.order_id,
        shop.id as shoperid,order_detail.promotion_id,
        order_detail.quantity as sku_count,shop.shop_name as shopername,order.receiver_name,order.mobile,send.id,send.caigou_id,order.remark,send.preorder_id')
            ->from('preorder_send as send')
            ->leftJoin('order_detail', 'send.order_detail_id=order_detail.id')
            ->leftJoin('item_sku', 'order_detail.sku_id=item_sku.id')
            ->leftJoin('order', 'order.id=order_detail.order_id')
            ->leftJoin('shop', 'order.shop_id=shop.id')
            // ->leftJoin('user', 'shop.user_id=user.id')
            // ->leftJoin('user_addr', 'shop.user_id=user_addr.user_id')
            ->where(['order_detail.sku_id' => $post['sku_id'], 'send.promotion_id' => $post['promotion_id'],
                'send.status' => 1, 'order_detail.promotion_id' => $post['promotion_id'], 'order_detail.is_paid' => 1])->
            andWhere('order_detail.status="")')
            ->all();


        $shopId = [];
        foreach ($shopInfo as $sk => $sv) {
            if (in_array($sv['shoperid'], $shopId)) {
                $location = array_search($sv['shoperid'], $shopId);
                $shopInfo[$location]['sku_count'] = $shopInfo[$location]['sku_count'] + $shopInfo[$sk]['sku_count'];

                $shopInfo[$location]['orderdetailid'][] = $sv['orderdetailid'];
                $shopInfo[$location]['order_id'][] = $sv['order_id'];
                unset($shopInfo[$sk]);
                continue;
            }
            unset($shopInfo[$sk]['orderdetailid'], $shopInfo[$sk]['order_id']);
            $shopInfo[$sk]['orderdetailid'][] = $sv['orderdetailid'];
            $shopInfo[$sk]['order_id'][] = $sv['order_id'];
            $shopId[$sk] = $sv['shoperid'];

            if ($post['promotion_id'] == $sv['promotion_id'] && $post['sku_id'] == $sv['sku_id']) {
                $shopInfo[$sk]['shoperaddr'] = $sv['province'] . $sv['city'] . $sv['county'] . $sv['addrdetail'];
                $shopInfo[$sk]['pickup_time'] = $post['pickup_time'];
                $shopInfo[$sk]['sku_title'] = $post['sku_title'];
            }
        }
        sort($shopInfo);
        return $shopInfo;

    }

    //采购导出,已经结束的才会改变状态
    public function actionCaigouexport()
    {
        Yii::$app->response->format = 'json';
        $post = Yii::$app->request->post('preorder');
        //根据时间和状态来判断哪些活动要更新为采购中3,如果审核未通过或者下架的活动不用更新状态了
        $preorder_status = [2];

        $time = time();
        $ids = [];

        if ($post) {
            foreach ($post as $k => $v) {

                if (in_array($v['preorder_status'], $preorder_status) || ($time >= strtotime($v['end_time']) && $v['preorder_status'] == 1)) {
                    $ids[] = $v['id'];
                }

                $post[$k]['specs'] = Sku::findOne($post[$k]['sku_id'])->specs;
                $orderInfo = Orderdetail::findAll(['promotion_id' => $post[$k]['promotion_id'], 'sku_id' => $post[$k]['sku_id'], 'is_paid' => 1]);
                if ($orderInfo) {
                    $post[$k]['sku_count'] = 0;
                    foreach ($orderInfo as $order) {
                        $post[$k]['sku_count'] = $post[$k]['sku_count'] + $order['quantity'];
                    }
                }

            }

            if ($ids) {
                Preorder::updateAll(['preorder_status' => 3], ['id' => $ids]);
            }
        }
        return $post;


    }

    //上架
    public function actionUp()
    {
        Yii::$app->response->format = 'json';
        if (!$post = Yii::$app->request->post()) {
            return ['rs' => 'false', 'msg' => '参数错误'];
        }
        $db = Yii::$app->db;
        $transcation = $db->beginTransaction();
        try {
            if (!Preorder::updateAll(['preorder_status' => 8], ['id' => $post])) {
                throw new Exception('上架失败' . __LINE__);
            }

            $preorderActiveModel = new Preorderactivelog();
            foreach ($post as $v) {

                $preorderchecklogModel = new Preorderchecklog();
                $preorderchecklogModel->preorder_id = $v;
                $preorderchecklogModel->created_at = date("Y-m-d H:i:s");
                $preorderchecklogModel->check_status = 1;
                $preorderchecklogModel->admin_id = Yii::$app->session->get('admin_id');
                if (!$preorderchecklogModel->save('false')) {
                    throw new Exception('上架失败' . __LINE__);
                }
                $preorderActiveModel->preorder_id = $v;
                $preorderActiveModel->admin_id = Yii::$app->session->get('admin_id');
                $preorderActiveModel->created_at = date("Y-m-d H:i:s");
                $preorderActiveModel->desc = '对该活动做了上架处理';
                if (!$preorderActiveModel->save(false)) {
                    throw new Exception('数据插入activelog表时出错' . __LINE__);
                }
            }
            $transcation->commit();
            return ['rs' => "true"];
        } catch (Exception $e) {
            $transcation->rollBack();
            return ['rs' => "false", 'msg' => $e->getMessage()];
        }

    }

    //下架
    public function actionDown()
    {
        Yii::$app->response->format = 'json';
        if (!$post = Yii::$app->request->post()) {
            return ['rs' => 'false', 'msg' => '参数错误'];
        }
        $db = Yii::$app->db;
        $transcation = $db->beginTransaction();
        try {
            if (!Preorder::updateAll(['preorder_status' => 5], ['id' => $post])) {
                throw new Exception('下架失败');
            }
            $preorderActiveModel = new Preorderactivelog();
            foreach ($post as $v) {
                $preorderActiveModel->preorder_id = $v;
                $preorderActiveModel->admin_id = Yii::$app->session->get('admin_id') ? Yii::$app->session->get('admin_id') : 1;
                $preorderActiveModel->created_at = date("Y-m-d H:i:s");
                $preorderActiveModel->desc = '对该活动做了下架处理';
                if (!$preorderActiveModel->save(false)) {
                    throw new Exception('数据插入activelog表时出错' . __LINE__);
                }
            }
            $transcation->commit();
            return ['rs' => "true"];
        } catch (Exception $e) {
            $transcation->rollBack();
            return ['rs' => "false", 'msg' => $e->getMessage()];
        }

    }

    /*preorder_status为1时表示审核通过,检查优惠券是否要总部审核,现金券大于1，满减券大于10%*/
    private function zbCheck($promotionId, $couponId, $buy_gift_coupon_id)
    {

        if ($couponId) {
            $shareCoupon = Coupon::findOne($couponId);
            if ($shareCoupon->coupon_type == 'cash') {
                $cash = Couponcash::find()->where('coupon_id=' . $couponId)->one();
                if ($cash->discount_amount > 1) {

                    //要总部审核
                    $approveModel = new Approve();
                    $approveModel->approve_type = "preorder";
                    $approveModel->event_id = $promotionId;
                    $approveModel->title = "预售活动优惠券审批";
                    $approveModel->created_at = time();
                    $approveModel->creater_id = Yii::$app->session->get('admin_id');
                    if (!$approveModel->save(false)) {
                        throw new Exception("审核失败" . __LINE__);
                    }
                    return true;
                }

            } elseif ($shareCoupon->coupon_type == 'discount') {
                $discount = Coupondiscount::find()->where('coupon_id=' . $couponId)->one();
                if (bcdiv($discount->min_amount, $discount->discount_amount, 2) > 0.1) {
                    //要总部审核
                    $approveModel = new Approve();
                    $approveModel->approve_type = "preorder";
                    $approveModel->event_id = $promotionId;
                    $approveModel->title = "预售活动优惠券审批";
                    $approveModel->created_at = time();
                    $approveModel->creater_id = Yii::$app->session->get('admin_id');

                    if (!$approveModel->save(false)) {
                        throw new Exception("审核失败" . __LINE__);
                    }
                    return true;
                }
            }
        }

        //参与得券
        if ($buy_gift_coupon_id) {
            $takecoupon = Coupon::findOne($buy_gift_coupon_id);
            if ($takecoupon->coupon_type == 'cash') {
                $cash = Couponcash::find()->where('coupon_id=' . $buy_gift_coupon_id)->one();
                if ($cash->discount_amount > 1) {

                    //要总部审核
                    $approveModel = new Approve();
                    $approveModel->approve_type = "preorder";
                    $approveModel->event_id = $promotionId;
                    $approveModel->title = "预售活动优惠券审批";
                    $approveModel->created_at = time();
                    $approveModel->creater_id = Yii::$app->session->get('admin_id');
                    if (!$approveModel->save(false)) {
                        throw new Exception("审核失败" . __LINE__);
                    }
                    return true;
                }

            } elseif ($takecoupon->coupon_type == 'discount') {
                $discount = Coupondiscount::find()->where('coupon_id=' . $buy_gift_coupon_id)->one();
                if (bcdiv($discount->min_amount, $discount->discount_amount, 2) > 0.1) {
                    //要总部审核
                    $approveModel = new Approve();
                    $approveModel->approve_type = "preorder";
                    $approveModel->event_id = $promotionId;
                    $approveModel->title = "预售活动优惠券审批";
                    $approveModel->created_at = time();
                    $approveModel->creater_id = Yii::$app->session->get('admin_id');

                    if (!$approveModel->save(false)) {
                        throw new Exception("审核失败" . __LINE__);
                    }
                    return true;
                }
            }
        }

        //优惠券信息
        $coupons = Promotioncoupon::find()->where('promotion_id=' . $promotionId)->asArray()->select('coupon_id')->all();
        if ($coupons) {
            $couponids = array_column($coupons, 'coupon_id');
            if ($couponids) {
                $coupons = Coupon::find()->where('coupon.id in(' . implode(',', $couponids) . ')')->asArray()->all();
                if ($coupons) {
                    foreach ($coupons as $ck => $cv) {
                        if ($cv['coupon_type'] == 'cash') {
                            //查现金券表
                            $cash = Couponcash::find()->where('coupon_id=' . $cv['id'])->one();
                            if ($cash->discount_amount > 1) {
                                //要总部审核
                                $approveModel = new Approve();
                                $approveModel->approve_type = "preorder";
                                $approveModel->event_id = $promotionId;
                                $approveModel->title = "预售活动优惠券审批";
                                $approveModel->created_at = time();
                                $approveModel->creater_id = Yii::$app->session->get('admin_id');

                                if (!$approveModel->save(false)) {
                                    throw new Exception("审核失败" . __LINE__);
                                }
                                return true;
                            }

                        } elseif ($cv['coupon_type'] == 'discount') {
                            //查满减券表
                            $cash = Coupondiscount::find()->where('coupon_id=' . $cv['id'])->one();
                            if (bcdiv($cash->discount_amount, $cash->min_amount, 2) > 0.1) {
                                //要总部审核
                                $approveModel = new Approve();
                                $approveModel->approve_type = "preorder";
                                $approveModel->event_id = $promotionId;
                                $approveModel->title = "预售活动优惠券审批";
                                $approveModel->created_at = time();
                                $approveModel->creater_id = Yii::$app->session->get('admin_id');

                                if (!$approveModel->save(false)) {
                                    throw new Exception("审核失败" . __LINE__);
                                }
                                return true;
                            }
                        }
                    }
                }
            }
        }

        return false;

    }


    //审核
    public function actionCheck()
    {
        Yii::$app->response->format = 'json';
        $get = Yii::$app->request->get();
        $model = new Preorderchecklog();
        $db = Yii::$app->db;
        $zbCheck = false;
        $transcation = $db->beginTransaction();
        try {
            //更新预售表
            $preorderModel = Preorder::findOne($get['id']);
            $model->preorder_id = $get['id'];
            $check_status = '';
            switch ($get['preorder_status']) {
                case '1':
                    $zbCheck = $this->zbCheck($preorderModel->promotion_id, $preorderModel->coupon_id, $preorderModel->buy_gift_coupon_id);
                    if ($zbCheck) {
                        $check_status = '5';
                        $preorderModel->preorder_status = 11;
                    } else {
                        $check_status = '3';
                        $preorderModel->preorder_status = 1;
                    }
                    break;
                case '10':
                    $check_status = '2';
                    $preorderModel->preorder_status = 10;

                    break;
                case '9':
                    $check_status = '4';
                    $preorderModel->preorder_status = 9;
                    break;
            }
            $model->check_status = $check_status;
            $model->reject_reason = $get['rejectreason'];
            $model->created_at = date("Y-m-d H:i:s");
            $model->admin_id = Yii::$app->session->get('admin_id');
            if (!$model->save(false)) {
                throw new Exception('数据更新审核表时出错' . __LINE__);
            }
            $preorderModel->preorder_check_log_id = $model->id;
            //return $preorderModel;
            if (!$preorderModel->save(false)) {
                throw new Exception('数据更新预售表时出错' . __LINE__);
            }

            //更新活动记录表
            $preorderActiveModel = new Preorderactivelog();
            $preorderActiveModel->admin_id = Yii::$app->session->get('admin_id') ? Yii::$app->session->get('admin_id') : 1;
            $preorderActiveModel->created_at = date("Y-m-d H:i:s");
            $preorderActiveModel->preorder_id = $get['id'];
            // return $get['check_status'];
            //  return $get;
            switch ($get['preorder_status']) {
                case '10':
                    $desc = '对该活动做了审核退回处理';
                    break;
                case '1':
                    $desc = '对该活动做了审核同意处理';
                    break;
                case '9':
                    $desc = '对该活动做了审核关闭处理';
                    break;
            }

            $preorderActiveModel->desc = $desc;
            if (!$preorderActiveModel->save(false)) {
                throw new Exception('数据插入activelog表时出错' . __LINE__);
            }
            $transcation->commit();
            return ['rs' => "true"];
        } catch (Exception $e) {
            $transcation->rollBack();
            return ['rs' => "false", 'msg' => $e->getMessage()];
        }


    }


    //可用市公司列表
    public function actionCmplist()
    {
        Yii::$app->response->format = 'json';
        $post = Yii::$app->request->post('active_id');
        $adminIds = [];
        $where[] = ' status=1 ';
        if ($post) {

            $where[] = ' id in ( ' . implode(',', $post) . ')';
            $where = implode('and', $where);
            $rs = Admin::find()->where($where)->asArray()->all();
        }

        if (Yii::$app->session->get('parent_admin_role_id') == '2') {
            //如果是市公司用户who_uploader in()市公司只能看到本市的;
            $all = Admin::find()->where('area="' . Yii::$app->session->get('role_area') . '"   and  admin_role_id=10')->asArray()->all();
        } else {
            $query = new \yii\db\Query();
            //$all = $query->select('a.*')->from('admin as a')->leftJoin('admin_role as b', 'a.admin_role_id=b.id')->groupBy('company_name')->where('b.parent_id=2 and a.admin_role_id=10 ')->all();
            $all = $query->select('a.*')->from('admin as a')->leftJoin('admin_role as b', 'a.admin_role_id=b.id')->distinct()->where('b.parent_id=2 and a.admin_role_id=10 ')->all();
        }
        //$all = Admin::find()->where('status=1 and admin_role_id=2')->asArray()->all();

        if (@$rs) {
            foreach ($all as $ak => $av) {
                foreach ($rs as $rk => $rv) {
                    if ($rv['id'] == $av['id']) {
                        $all[$ak]['_checked'] = true;
                    }
                }
            }
        }
        return ['rs' => $all];
    }

    public function actionList()
    {
        Yii::$app->response->format = 'json';
        $curPage = 1;
        $where = [];
        $enableCheck = false;//审核权限
        $enableEdit = false;//编辑权限
        $enableAdd = false;//添加活动
        $enableDown = false;//下架;
        $enableUp = false;//上架
        $enableSend = false;//发货
        $enableCaigou = false;//采购
        $enableRepeat = false;//活动复用
        $enableAddpurchase = false;//添加采购记录
        //  return Yii::$app->session->get('username');
        if (Yii::$app->session->get('username') != 'admin') {
            $aulist = explode('|', Yii::$app->session->get('authlist'));
            if (!in_array('preorderedit', $aulist)) {
                $enableEdit = true;
            }
            if (!in_array('preorderadd', $aulist)) {
                $enableAdd = true;
            }
            if (!in_array('preordercheck', $aulist)) {
                $enableCheck = true;
            }
            if (!in_array('preorderdown', $aulist)) {
                $enableDown = true;
            }
            if (!in_array('preorderup', $aulist)) {
                $enableUp = true;
            }
            if (!in_array('preordersend', $aulist)) {
                $enableSend = true;
            }
            if (!in_array('preordercaigou', $aulist)) {
                $enableCaigou = true;
            }
            if (!in_array('preorderrepeat', $aulist)) {
                $enableRepeat = true;
            }
            if (!in_array('addpurchaserecord', $aulist)) {
                $enableAddpurchase = true;
            }
        }
        $count = 0;
        $pageSize = Yii::$app->params['pagesize'];
        //$pageSize = 1;
        $curPage = 1;
        $last = ['rs' => [], 'totalPage' => $count, 'pageSize' => $pageSize, 'currpage' => $curPage, 'auth' => ['enableCheck' => $enableCheck, 'enableEdit' => $enableEdit, 'enableAdd' => $enableAdd, 'enableDown' => $enableDown, 'enableUp' => $enableUp, 'enableSend' => $enableSend, 'enableCaigou' => $enableCaigou, 'enableRepeat' => $enableRepeat, 'enableAddpurchase' => $enableAddpurchase]];

        if (Yii::$app->session->get('parent_admin_role_id') == '2') {
            //如果是市公司用户who_uploader in()市公司只能看到本市的;
            $where[] = 'a.area like "%' . Yii::$app->session->get('role_area') . '%"';
        }
        $search = Yii::$app->request->post();
        $caisend = "";
        if ($search) {
            foreach ($search as $k => $v) {
                if ($k == 'curpage') {
                    $curPage = $v;
                    continue;
                }
                if (trim($v)) {
                    if ($k == 'caisend') {
                        if ($v != 'all' &&$v) {
                            $caisend = $v;
                        }
                        continue;
                    }
                    if ($search[$k]) {
                        $where[] = $k . ' like "%' . trim($v) . '%"';
                    }
                }
            }
        }

        $offset = $pageSize * ($curPage - 1);
        $query = new \yii\db\Query();
        $query = $query->select('a.*,b.reject_reason,
  b.created_at as check_time,admin.username as active_sender,b.admin_id as checker,q.from_active_type')
            ->from('promotion_preorder as a')
            ->leftJoin('preorder_check_log as b', 'a.preorder_check_log_id=b.id')
            ->leftJoin('admin', 'a.who_creater=admin.id')
            ->leftJoin('make_preorder as q', 'a.id=q.to_preorder_id');
        //caisend
        if ($caisend) {
            if ($caisend == 'send') {
                //取消订单的去掉
                $spromotionids = Yii::$app->db->createCommand(" select DISTINCT  a.promotion_id from   preorder_send as a left JOIN order_cancel as b on a.order_id=b.order_id where a.`status`=1  and ((b.status=2) or (b.`status` is null )) and a.promotion_id is not null")->queryAll();
                if ($spromotionids) {
                    $spromotionids = array_column($spromotionids, 'promotion_id');
                    $where[] = "a.promotion_id in (" . implode(',', $spromotionids) . ")";
                } else {
                    return $last;
                }

            } elseif ($caisend == 'cai') {
                //把活动id取出来,取消订单的也要去掉
                $allpromotion_id = Yii::$app->db->createCommand(" select DISTINCT  a.promotion_id from `order` as a LEFT JOIN preorder_send as b on a.id=b.order_id left join order_cancel as c on a.id=c.order_id where a.order_type=0 and  a.`status`='PAID'  and promotion_type='preorder' and b.caigou_id is null and ((c.status=2) or (c.status is null) ) and a.promotion_id is not null")->queryAll();

                // return $allpromotion_id;
                if ($allpromotion_id) {
                    $allpromotion_id = array_column($allpromotion_id, 'promotion_id');
                    $where[] = "a.promotion_id in (" . implode(',', $allpromotion_id) . ")";
                } else {
                    return $last;
                }
            }
        }

        if ($where) {
            $where = implode(' and ', $where);
            $query = $query->where($where);
        }
        $rs = $query->offset($offset)->limit($pageSize)->orderBy('updated_at desc')->all();
        $count = $query->count();
        foreach ($rs as $k => $v) {
            if ($v['active_type'] == 2) {
                $city = Admin::find()->where('id in (' . $v['active_id'] . ')')->select('city')->distinct()->column();
                $rs[$k]['city'] = implode(',', $city);
            } elseif ($v['active_type'] == 3) {
                $city = Shop::find()->where('id in (' . $v['active_id'] . ')')->select('city')->distinct()->column();
                $rs[$k]['city'] = implode(',', $city);

            } else {
                $rs[$k]['city'] = '全国';
            }
            $rs[$k]['user_created_at'] = $rs[$k]['user_created_at'] ? date("Y-m-d H:i:s", $v['user_created_at']) : '';
            $rs[$k]['updated_at'] = $v['updated_at'] ? date("Y-m-d H:i:s", $v['updated_at']) : "";
            $rs[$k]['begin_time'] = $v['begin_time'] ? date("Y-m-d H:i:s", $v['begin_time']) : "";
            $rs[$k]['end_time'] = $v['end_time'] ? date("Y-m-d H:i:s", $v['end_time']) : "";
            $rs[$k]['notice_time'] = $v['notice_time'] ? date("Y-m-d H:i:s", $v['notice_time']) : 0;
            $rs[$k]['pickup_time'] = $v['pickup_time'] ? date("Y-m-d H:i:s", $v['pickup_time']) : 0;
            $rs[$k]['created_at'] = date("Y-m-d H:i:s", $v['created_at']);
            $rs[$k]['pickup_end_time'] = $v['pickup_end_time'] ? date("Y-m-d H:i:s", $v['pickup_end_time']) : 0;

            if ($v['from_active_type'] == '1') {
                $rs[$k]['from_active_type'] = "退货";
            } elseif ($v['from_active_type'] == '2') {
                $rs[$k]['from_active_type'] = "取消订单";
            } else {
                $rs[$k]['from_active_type'] = "正常添加";
            }

        }


        return ['rs' => $rs, 'totalPage' => $count, 'pageSize' => $pageSize, 'search' => @$search, 'currpage' => $curPage, 'auth' => ['enableCheck' => $enableCheck, 'enableEdit' => $enableEdit, 'enableAdd' => $enableAdd, 'enableDown' => $enableDown, 'enableUp' => $enableUp, 'enableSend' => $enableSend, 'enableCaigou' => $enableCaigou, 'enableRepeat' => $enableRepeat, 'enableAddpurchase' => $enableAddpurchase]];
    }

    /*返回优惠券信息*/
    private function coupon($range, $select)
    {

        $rs = [];
        if ($range == 2) {
            //市公司
            if (count($select) > 1) {
                $rs = Coupon::find()->where("(scope=0 and status=1 )")->select("id,coupon_type,title")->asArray()->all();
            } else {
                $shopInfo = Admin::findOne($select[0]);
//                $area = explode(":",$shopInfo->area);
//                $area = $area[0].":".$area[1];
                $rs = Coupon::find()->where("(scope=0 and status=1 ) or(scope=1 and status=1  and scope_info like '%" . $shopInfo->area . "%' )")->select("id,coupon_type,title")->asArray()->all();
            }

        }
        if ($range == 3) {
            //店铺
            if (!$select) {
                return [];
            }
            $userIds = implode(",", $select);
            $us = Shop::find()->where('id in (' . $userIds . ')')->select('id,area')->asArray()->all();
            $diff = false;
            $area1 = "";
            $area2 = "";
            foreach ($us as $k => $v) {
                if (@$us[$k + 1]) {
                    $area1 = explode(":", $v['area']);
                    $area1 = $area1[0] . ":" . $area1[1];
                    $area2 = explode(":", $us[$k + 1]['area']);
                    $area2 = $area2[0] . ":" . $area1[2];
                    if ($area1 != $area2) {
                        $diff = true;
                    }

                } else {
                    $area1 = explode(":", $us[0]['area']);
                    $area1 = $area1[0] . ":" . $area1[1];
                }
            }
            if ($diff) {
                $rs = Coupon::find()->where("(scope=0 and status=1) ")->select("id,coupon_type,title")->asArray()->all();
            } else {
                $rs = Coupon::find()->where("(scope=0 and status=1 ) or (scope=1 and status=1  and scope_info like '%" . $area1 . "%' )")->select("id,coupon_type,title")->asArray()->all();
            }

        }
        if ($rs) {
            foreach ($rs as $rk => $rv) {
                if ($rv['coupon_type'] == "cash") {
                    $rs[$rk]['title'] = "现金券--" . $rv['title'];
                } elseif ($rv['coupon_type'] == 'discount') {
                    $rs[$rk]['title'] = "满减券--" . $rv['title'];
                }
            }
        }
        // array_unshift($rs,['title'=>"请选择","id"=>"全国"]);
        return $rs;

    }

    /*优惠券,如果多个市的话，只选全国的优惠券,多个用户如果在不同的城市则只选全国的券*/
    public function actionCoupons()
    {
        Yii::$app->response->format = 'json';
        $post = YII::$app->request->post();
        if (!$post['scope'] || !$post['select']) {
            return [];
        }
        $rs = [];
        $rs = $this->coupon($post['scope'], $post['select']);
        return $rs;

    }



    //添加活动
    public function actionAdd()
    {
        Yii::$app->response->format = 'json';
        if (Yii::$app->request->isGet) {
            $adminId = Yii::$app->session->get('admin_id');
            $roleId = Yii::$app->session->get('parent_admin_role_id');
            $fake_sold_out = rand(10,20) ;
            if ($roleId == 2) {
                return ['roleId' => $roleId, 'admin_id' => [$adminId],'fake_sold_out'=>$fake_sold_out];
            }
            return ['fake_sold_out'=>$fake_sold_out];
        }
        $post = Yii::$app->request->post();
        if (!$post['preorder']['description']) {
            return ['rs' => 'false', 'msg' => '请输入活动名称'];
        }
        if (count($post['preorder']['active_id']) < 1 && $post['preorder']['active_type'] != 1) {
            return ['rs' => 'false', 'msg' => '请选择销售范围'];
        }

        $model = new Preorder();
        $promotionModel = new Promotion();
        $time = time();
        $db = Yii::$app->db;
        $transaction = $db->beginTransaction();
        try {
            $promotionModel->promotion_type = 'preorder';
            $promotionModel->promotion_name = $post['preorder']['description'];
            $promotionModel->created_at = $time;
            $promotionModel->updated_at = $time;
            if (!$promotionModel->save(false)) {
                throw new Exception("insert fail promotion");
            }
            $couponIds = $post['preorder']['coupon_ids'];
            unset($post['preorder']['coupon_ids']);
            if ($couponIds) {
                //插入可用优惠券表
                //return $couponIds;
                foreach ($couponIds as $ck => $cv) {
                    $couponModel = new Promotioncoupon();
                    $couponModel->promotion_id = $promotionModel->id;
                    $couponModel->coupon_id = $cv;
                    $couponModel->created_at = $time;
                    if (!$couponModel->save(false)) {
                        throw new Exception("insert fail promotion");
                    }

                }
            }

            if ($post['preorder']['exclude_shops']) {
                $post['preorder']['exclude_shops'] = implode(',', $post['preorder']['exclude_shops']);
            } else {
                $post['preorder']['exclude_shops'] = null;
            }
            if(!$post['preorder']['fake_sold_out']){
                $post['preorder']['fake_sold_out'] = 0;
             }
            $model->load($post, 'preorder', false);

            if ($model->active_type == 2) {
                $model->active_id = trim(implode(',', $model->active_id), ',');

                $adminIds = explode(',', $model->active_id);
                $area = Admin::find()->asArray()->select('area')->where(['id' => $adminIds])->all();
                $model->area = implode(',', array_unique(array_column($area, 'area')));

            } elseif ($model->active_type == 3) {
                $area = Shop::find()->asArray()->select('area')->where(['id' => $model->active_id])->distinct()->all();
                $cityArea = [];
                foreach ($area as $k => $v) {
                    $newArea = explode(':', $v['area']);
                    $cityArea [] = $newArea[0] . ':' . $newArea[1];
                }
                $model->area = implode(',', $cityArea);
                $model->active_id = trim(implode(',', $model->active_id), ',');

            } else {
                unset($model->active_id);
            }
            if ($model->user_created_at) {
                $model->user_created_at = strtotime($model->user_created_at);
            }
            $model->begin_time = strtotime($model->begin_time);
            $model->end_time = strtotime($model->end_time);
            $model->pickup_end_time = strtotime($model->pickup_end_time);
            $model->pickup_time = strtotime($model->pickup_time);
            $model->notice_time = strtotime($model->notice_time);
            $model->sku_title = Sku::findOne($model->sku_id)->title;
            $model->created_at = $time;
            $model->updated_at = $time;
            $model->promotion_id = $promotionModel->id;
            $model->who_creater = Yii::$app->session->get('admin_id');
            $model->store = $model->limit_num;
            $model->freeze_store = 0;
            $model->preorder_status = 8;
            if (!$model->area) {
                throw new Exception('请选择销售范围' . __LINE__);
            }

            // return $model;
            if (!$model->save(false)) {
                throw new Exception("insert fail preorder");
            }
            //插入审核记录表
            $preorderCheckModel = new Preorderchecklog();
            $preorderCheckModel->preorder_id = $model->id;
            $preorderCheckModel->created_at = date("Y-m-d H:i:s");
            $preorderCheckModel->admin_id = Yii::$app->session->get('admin_id');
            if (!$preorderCheckModel->save(false)) {
                throw new Exception('数据更新审核表时出错' . __LINE__);
            }
            $model->preorder_check_log_id = $preorderCheckModel->id;
            if (!$model->save(false)) {
                throw new Exception("insert fail preorder" . __LINE__);
            }
            //更新活动记录表
            $preorderActiveModel = new Preorderactivelog();
            $preorderActiveModel->admin_id = Yii::$app->session->get('admin_id') ? Yii::$app->session->get('admin_id') : 1;
            $preorderActiveModel->created_at = date("Y-m-d H:i:s");
            $preorderActiveModel->preorder_id = $model->id;
            $desc = "对该活动进行了创建操作";
            $preorderActiveModel->desc = $desc;
            if (!$preorderActiveModel->save(false)) {
                throw new Exception('数据插入activelog表时出错' . __LINE__);
            }
            $transaction->commit();
            return ['rs' => "true"];
        } catch (\Exception $e) {
            $transaction->rollBack();
            return ['rs' => "false", 'msg' => $e->getMessage()];
        }


        // return $model;

        $model->created_at = time();
        $model->updated_at = time();
        if ($model->save(false)) {
            return ['rs' => "true"];
        } else {
            return ['rs' => "false", 'msg' => '添加失败'];
        }
        return $model;

    }

    /*活动范围*/
    public function actionActiverange()
    {
        Yii::$app->response->format = 'json';
        $post = Yii::$app->request->post();
        $rs = [];
        if (!$post) {
            return $rs;
        }
        switch ($post['active_type']) {
            case "2":
                $rs = Admin::findAll(explode(',', $post['active_id']));
                break;
            case "3":
                $rs = shop::findAll(explode(',', $post['active_id']));
                if ($rs) {
                    foreach ($rs as $k => $v) {
                        if ($v['shop_type'] == 1) {
                            $rs[$k]['shop_type'] = "代理";
                        } else {
                            $rs[$k]['shop_type'] = "店东";
                        }
                    }
                }
                break;

        }
        return $rs;


    }

    //编辑保存
    public function actionEditsave()
    {
        Yii::$app->response->format = 'json';
        $post = Yii::$app->request->post();
        $time = time();
        $preorderModel = Preorder::findOne($post['preorder']['id']);
        $couponIds = $post['preorder']['coupon_ids'];// 可用优惠券
        unset($post['preorder']['coupon_ids']);
        if ($post['preorder']['exclude_shops']) {
            $post['preorder']['exclude_shops'] = implode(',', $post['preorder']['exclude_shops']);
        } else {
            $post['preorder']['exclude_shops'] = null;
        }
        if(!$post['preorder']['fake_sold_out']){
            $post['preorder']['fake_sold_out'] = 0;
        }
        $preorderModel->load($post, 'preorder', false);
        if (!isset($post['preorder']['buy_gift_coupon_id']) || !$post['preorder']['buy_gift_coupon_id']) {
            $preorderModel->buy_gift_coupon_id = "";
        }
        if (!isset($post['preorder']['coupon_id']) || !$post['preorder']['coupon_id']) {
            $preorderModel->coupon_id = "";
        }
        $promotionModel = Promotion::findOne($preorderModel->promotion_id);
        $promotionModel->promotion_name = $preorderModel->description;
        $promotionModel->updated_at = time();

        if ($preorderModel->user_created_at) {
            $preorderModel->user_created_at = strtotime($preorderModel->user_created_at);
        }
        $preorderModel->updated_at = $time;
        $preorderModel->notice_time = strtotime($preorderModel->notice_time);
        $preorderModel->begin_time = strtotime($preorderModel->begin_time);
        $preorderModel->end_time = strtotime($preorderModel->end_time);
        $preorderModel->pickup_time = strtotime($preorderModel->pickup_time);
        $preorderModel->pickup_end_time = strtotime($preorderModel->pickup_end_time);
        $preorderModel->store = $preorderModel->limit_num;
        $skuTitle = Sku::findOne($preorderModel->sku_id);
        $preorderModel->sku_title = $skuTitle->title;

        if ($preorderModel->active_type == 2) {
            if (is_array($preorderModel->active_id)) {
                $area = Admin::find()->asArray()->select('area')->where(['id' => $preorderModel->active_id])->all();
                $preorderModel->area = implode(',', array_unique(array_column($area, 'area')));
                $preorderModel->active_id = trim(implode(',', $preorderModel->active_id), ',');
            }

        } elseif ($preorderModel->active_type == 3) {
            $area = Shop::find()->asArray()->select('area')->where(['id' => $preorderModel->active_id])->distinct()->all();
            $cityArea = [];
            foreach ($area as $k => $v) {
                $newArea = explode(':', $v['area']);
                $cityArea [] = $newArea[0] . ':' . $newArea[1];
            }
            $preorderModel->area = implode(',', $cityArea);
            $preorderModel->active_id = trim(implode(',', $preorderModel->active_id), ',');
        } else {
            unset($preorderModel->active_id);
        }


        $db = Yii::$app->db;
        $transaction = $db->beginTransaction();

        try {

            if ($couponIds) {
                //插入可用优惠券表
                if (Promotioncoupon::find()->where("promotion_id=" . $preorderModel->promotion_id)->one()) {
                    if (!Promotioncoupon::deleteAll("promotion_id=" . $preorderModel->promotion_id)) {
                        throw new Exception("insert fail promotion" . __LINE__);
                    }
                }

                //return $couponIds;
                foreach ($couponIds as $ck => $cv) {
                    $couponModel = new Promotioncoupon();
                    $couponModel->promotion_id = $promotionModel->id;
                    $couponModel->coupon_id = $cv;
                    $couponModel->created_at = $time;
                    if (!$couponModel->save(false)) {
                        throw new Exception("insert fail promotion");
                    }
                }


            } else {
                //空的话，删除原来的可用券
                if (Promotioncoupon::find()->where("promotion_id=" . $preorderModel->promotion_id)->one()) {
                    if (!Promotioncoupon::deleteAll("promotion_id=" . $preorderModel->promotion_id)) {
                        throw new Exception("insert fail promotion" . __LINE__);
                    }
                }
            }

            if (!$promotionModel->save(false)) {
                throw new Exception('数据更新promotion表时出错' . __LINE__);
            }
            $checker = '';
            /*如果活动状态是被退回，编辑后改为待审核*/
            if ($preorderModel->preorder_status == '10') {
                $preorderModel->preorder_status = 8;
                $preorderCheckLogModel = new Preorderchecklog();
                $preorderCheckLogModel->preorder_id = $post['preorder']['id'];
                $preorderCheckLogModel->created_at = date("Y-m-d H:i:s");
                $preorderCheckLogModel->admin_id = Yii::$app->session->get('admin_id');
                $preorderCheckLogModel->check_status = 1;
                if (!$preorderCheckLogModel->save(false)) {
                    throw new Exception('数据更新preordercheck表时出错' . __LINE__);
                }
                $checker = '和下架后的，请求审核通过操作';
            }

            if (!$preorderModel->area) {
                throw new Exception('请选择销售范围' . __LINE__);
            }
            //return $preorderModel;
            if (!$preorderModel->save(false)) {
                throw new Exception('数据更新preorder表时出错' . __LINE__);
            }
            //更新活动记录表
            $preorderActiveModel = new Preorderactivelog();
            $preorderActiveModel->admin_id = Yii::$app->session->get('admin_id');
            $preorderActiveModel->created_at = date("Y-m-d H:i:s");
            $preorderActiveModel->preorder_id = $post['preorder']['id'];
            $desc = "对该活动进行了编辑操作" . $checker;
            $preorderActiveModel->desc = $desc;
            if (!$preorderActiveModel->save(false)) {
                throw new Exception('数据插入activelog表时出错' . __LINE__);
            }
            $transaction->commit();
            return ['rs' => 'true'];
        } catch (Exception $e) {
            $transaction->rollBack();
            return ['rs' => 'false', 'msg' => $e->getMessage()];
        }

    }

    //编辑预售
    public function actionEdit()
    {
        Yii::$app->response->format = 'json';
        $preorderId = Yii::$app->request->get('id');
        $preorder = Preorder::findOne($preorderId);
        if ($preorder['user_created_at']) {
            $preorder['user_created_at'] = date("Y-m-d H:i:s", $preorder['user_created_at']);
        }
        $preorder['exclude_shops'] = $preorder['exclude_shops'] ? explode(',', $preorder['exclude_shops']) : [];
        $preorder['notice_time'] = date("Y-m-d H:i:s", $preorder['notice_time']);
        $preorder['begin_time'] = date("Y-m-d H:i:s", $preorder['begin_time']);
        $preorder['end_time'] = date("Y-m-d H:i:s", $preorder['end_time']);
        $preorder['pickup_time'] = date("Y-m-d H:i:s", $preorder['pickup_time']);
        $preorder['pickup_end_time'] = date("Y-m-d H:i:s", $preorder['pickup_end_time']);
        $skuInfo = Sku::findOne($preorder['sku_id']);
        $preorder['active_id'] = explode(',', $preorder['active_id']);
        $lableInfo = [];
        if ($preorder['label_name']) {
            $lableInfo = PromotionLabel::find()->where('label_name="' . $preorder['label_name'] . '"')->asArray()->one();
        }
        $coupons = [];
        if ($preorder['active_type'] == 2 || $preorder['active_type'] == 3) {

            $coupons = $this->coupon($preorder['active_type'], $preorder['active_id']);

        }

        $promotionCoupon = Promotioncoupon::find()->where("promotion_id=" . $preorder['promotion_id'])->asArray()->select("coupon_id")->all();
        $promotionCoupon = $promotionCoupon ? array_column($promotionCoupon, 'coupon_id') : [];


        return ['preorder' => $preorder, 'sku' => $skuInfo, 'admin_role_id' => Yii::$app->session->get('parent_admin_role_id'), 'labelInfo' => $lableInfo, 'admin_id' => Yii::$app->session->get('admin_id'), "promotioncouponids" => $promotionCoupon, 'conpons' => $coupons, 'coupon_id' => $preorder['coupon_id'], 'buy_gift_coupon_id' => $preorder['buy_gift_coupon_id']];
    }


}
