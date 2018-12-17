<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/1/17 0017
 * Time: 下午 2:49
 */

namespace app\controllers;

use app\common\behavior\NoCsrs;
use app\models\Admin;
use app\models\Category;
use app\models\Item;
use app\models\Itemstore;
use app\models\Sku;
use app\models\Skustore;
use Yii;
use app\models\UploadPic;
use yii\db\Exception;
use yii\web\Controller;
use yii\web\Response;
use yii\web\UploadedFile;

class ItemController extends Controller
{
    public static $cates;

    public function behaviors()
    {
        return [
            'csrf' => [
                'class' => NoCsrs::className(),
                'controller' => $this,
                'actions' => [
                    'uppic',
                    "add",
                    "edit",
                    "editsave",
                    "skus",
                    "storelist",
                    "addtosku",
                    "usesku"
                ]
            ]
        ];
    }

    public $enableCsrfValidation = false;

    /*获取所有的分类*/
    public function actionGetcategorys()
    {
        Yii::$app->response->format = 'json';
        $cates = Category::find()->where('is_disabled=0')->asArray()->all();
        return $this->categorys($cates);
    }

    private function categorys($cates, $level = 1)
    {
        if ($cates) {
            foreach ($cates as $ck => $cv) {
                if ($cv['level'] == $level) {
                    self::$cates[$ck] = ['value' => $cv['id'], 'label' => $cv['cat_name']];
                    foreach ($cates as $subkey => $subV) {
                        if ($subV['parent_id'] == $cv['id']) {
                            $son = ['value' => $subV['id'], 'label' => $subV['cat_name']];
                            foreach ($cates as $childKey => $childVal) {
                                if ($childVal['parent_id'] == $subV['id']) {
                                    $son['children'][] = ['value' => $childVal['id'], 'label' => $childVal['cat_name']];
                                }
                            }
                            self::$cates[$ck]['children'][] = $son;
                        }

                    }

                }
            }
        }
        sort(self::$cates);
        return self::$cates;
    }

    public function actionAddtosku()
    {
        //产品库都产品表
        Yii::$app->response->format = 'json';
        $post = Yii::$app->request->post();
        if (!$post) {
            return ['rs' => 'false', 'msg' => '参数缺失'];
        }
        $db = Yii::$app->db;
        $transaction = $db->beginTransaction();
        $skuMode = new Sku();
        // $skustoreMode = new Skustore();
        $time = time();
        try {
            foreach ($post as $k => $v) {
                $itemMode = Item::findOne($v['id']);
                $oldSkuModel = Sku::find()->where('item_id=' . $v['id'])->one();
                $skuMode = new Sku();
                $skuMode->title = $itemMode->title;
                $skuMode->item_id = $itemMode->id;
                $skuMode->bn = $this->bn($itemMode->id);
                $skuMode->description = $itemMode->title;
                $skuMode->packing_qty = 1;
                $skuMode->packing_unit = '个';
                $skuMode->is_whole = '0';
                $skuMode->moq = 1;
                $skuMode->price = $oldSkuModel->price;
                $skuMode->cat_id = $oldSkuModel->cat_id;
                $skuMode->detail = $oldSkuModel->detail;
                $skuMode->barcode = $this->bn($itemMode->id);
                $skuMode->specs = $oldSkuModel->specs;
                $skuMode->producing_area = $oldSkuModel->producing_area;
                $skuMode->who_uploader = Yii::$app->session->get('admin_id');
                $skuMode->pic = $itemMode->pic;
                $skuMode->pics = $itemMode->pics;
                $skuMode->created_at = $time;
                $skuMode->updated_at = $time;
                if (!$skuMode->save(false)) {
                    throw new Exception('添加到sku表失败');
                }
            }
            $transaction->commit();
            return ['rs' => "true"];
        } catch (Exception $e) {
            $transaction->rollBack();
            return ['rs' => "false", 'msg' => $e->getMessage()];
        }


    }

    public function actionUppic()
    {
        Yii::$app->response->format = 'json';
        $get = Yii::$app->request->get();
        if (@$_FILES['upload']['tmp_name']) {
            if ($_FILES['upload']['tmp_name']) {
                $savePath = Yii::$app->params['uploadImg'];
                $date = date("Ymd");
                $savePath = $savePath . $date . '/';
                if (!file_exists($savePath)) {
                    mkdir($savePath, 0777, true);
                }
                $baseName = $pathInfo = pathinfo('_' . $_FILES['upload']['name'], PATHINFO_FILENAME);
                $baseName = md5(date("YmdHis") . $baseName);
                $extname = strtolower(pathinfo($_FILES['upload']['name'], PATHINFO_EXTENSION));
                $filename = $baseName . '.' . $extname;
                $desPath = $savePath . $filename;
                if (move_uploaded_file($_FILES['upload']['tmp_name'], $desPath)) {
                    if (@$get['yu']) {
                        $this->thumb($desPath, $savePath.$baseName.'_t.'.$extname);
                    }
                    return '/upload/imgs/' . $date . '/' . $filename;
                }
            }
        }
    }

    /*生成缩略图*/
    private function thumb($org, $dst)
    {
        list($width, $height, $type) = getimagesize($org);
        $image_p = imagecreatetruecolor(110, 110);
        switch ($type) {
            case 1:
                $image = imagecreatefromgif($org);
                imagecopyresampled($image_p, $image, 0, 0, 0, 0, 110, 110, $width, $height);
                imagegif($image_p, $dst );
                break;

            case 2:
                $image = imagecreatefromjpeg($org);
                imagecopyresampled($image_p, $image, 0, 0, 0, 0, 110, 110, $width, $height);
                imagejpeg($image_p, $dst );
                break;

            case 3:
                $image = imagecreatefrompng($org);
                imagecopyresampled($image_p, $image, 0, 0, 0, 0, 110, 110, $width, $height);
                imagepng($image_p, $dst);
                break;

        }

    }

    //产品库
    public function actionStorelist()
    {
        Yii::$app->response->format = 'json';
        if (Yii::$app->request->isPost) {
            $search = Yii::$app->request->post();
            foreach ($search as $k => $v) {
                if ($k == 'curpage') {
                    $curPage = $v;
                    continue;
                }
                if ($search[$k]) {

                    @$where[] = $k . ' like "%' . $v . '%"';
                }
            }
            @$where = implode(' and ', @$where);
        }
        $pageSize = Yii::$app->params['pagesize'];
        $query = new \yii\db\Query();
        @$curPage = $curPage ? $curPage : 1;
        @$offset = $pageSize * ($curPage - 1);
        if (@$where) {
            $query = Item::find()->where($where);
            $count = $query->count();
            $rs = $query->offset($offset)->limit($pageSize)->orderBy('updated_at desc')->all();
        } else {
            $query = Item::find();
            $count = $query->count();
            $rs = $query->offset($offset)->limit($pageSize)->orderBy('updated_at desc')->all();
        }
        foreach ($rs as $k => $v) {
            $rs[$k]['updated_at'] = date("Y-m-d H:i:s", $v['updated_at']);
            $rs[$k]['is_self_build'] = $rs[$k]['is_self_build'] == 1 ? '是' : '否';
        }
        $enableTosku = false;
        if (Yii::$app->session->get('username') != 'admin') {
            $aulist = explode('|', Yii::$app->session->get('authlist'));

            if (!in_array('itemtosku', $aulist)) {
                $enableTosku = true;
            }

        }

        return ['rs' => $rs, 'totalPage' => $count, 'pageSize' => $pageSize, 'search' => @$search, 'currpage' => $curPage, 'auth' => ['enableTosku' => $enableTosku]];

    }

    private function foramtdate($item, $key)
    {
        if ($key == 'updated_at') {
            $item = date("Y-m-d H:i:s");
        }

    }

    /*可用商品列表*/
    public function actionUsesku()
    {

        Yii::$app->response->format = 'json';
        // return md5('yldk');
        if (Yii::$app->session->get('parent_admin_role_id') == '2') {
            //如果是市公司用户who_uploader in();
            //超级管理园创建的，总公司创建的，同一个是公司的用户创建的；
            $who_uploaderIds = Admin::find()->where('area="' . Yii::$app->session->get('role_area') . '" or admin_role_id in(11,1)')->asArray()->select('id')->all();
            $who_uploaderIds = array_column($who_uploaderIds, 'id');
            $where[] = 'item_sku.who_uploader in (' . implode(',', $who_uploaderIds) . ')';

        }
        //总公司能看到所有的
        $where[] = 'item_sku.status in (1,2)';
        $where[] = 'item_sku.cat_id !=1';
        if (Yii::$app->request->isPost) {
            $search = Yii::$app->request->post();
            foreach ($search as $k => $v) {
                if ($k == 'curpage') {
                    $curPage = $v;
                    continue;
                }
                if ($search[$k]) {
                    if ($k == 'cat_id') {
                        $catIdCount = count($v);
                        if ($catIdCount > 0) {
                            switch ($catIdCount) {
                                case 1:
                                    $where[] = '(f.id=' . $v[0] . ' or f.parent_id=' . $v[0] . ' or f.top_cat_id=' . $v[0] . ')';
                                    continue;
                                    break;
                                case 2:
                                    $where[] = '(f.id=' . $v[1] . ' or f.parent_id=' . $v[1] . ')';
                                    continue;
                                    $catIds = Category::find()->where('level=3 and parent_id=' . $v[1])->select('id')->asArray()->all();
                                    $catIds = array_column($catIds, 'id');
                                    if ($catIds) {
                                        $where[] = "item_sku.cat_id in (" . implode(',', $catIds) . ")";
                                    }
                                    continue;
                                    break;
                                case 3:
                                    $where[] = 'f.id=' . $v[2];
                                    continue;
                                    $where[] = "item_sku.cat_id=" . $v[2];
                                    continue;
                                    break;
                            }
                            continue;
                        }
                    }
                    $where[] = $k . ' like "%' . $v . '%"';
                }
            }
        }
        $where = implode(' and ', $where);
        $pageSize = Yii::$app->params['pagesize'];
        $query = new \yii\db\Query();
        $curPage = @$curPage ? $curPage : 1;
        $offset = $pageSize * ($curPage - 1);
        $query = $query->select('item_sku.*,store.quantity,item_sku_count.count,f.cat_name')
            ->from('item_sku')
            ->leftJoin('item_sku_store as store', 'item_sku.id=store.sku_id')
            ->leftJoin('item_sku_count', 'item_sku.id=item_sku_count.sku_id')
            ->leftJoin('category as f', 'item_sku.cat_id= f.id')
            ->orderBy('item_sku.id desc')
            ->where($where);
        $totalPage = $query->count();
        $rs = $query->offset($offset)->limit($pageSize)->all();
        $cates = Category::find()->where('is_disabled=0 and id!=1')->asArray()->all();
        return ['rs' => $rs, 'totalPage' => $totalPage, 'pageSize' => $pageSize, 'currpage' => $curPage, 'category' => $this->categorys($cates)];


    }

    public function actionSkus()
    {
        Yii::$app->response->format = 'json';
        // return md5('yldk');
        if (Yii::$app->session->get('parent_admin_role_id') == '2') {
            //如果是市公司用户who_uploader in();
            //超级管理园创建的，总公司创建的，同一个是公司的用户创建的；
            $who_uploaderIds = Admin::find()->where('area="' . Yii::$app->session->get('role_area') . '" or admin_role_id in(11,1)')->asArray()->select('id')->all();
            $who_uploaderIds = array_column($who_uploaderIds, 'id');
            $where[] = 'item_sku.who_uploader in (' . implode(',', $who_uploaderIds) . ')';

        }
        //总公司能看到所有的
        $where[] = 'item_sku.status in (1,2)';
        if (Yii::$app->request->isPost) {
            $search = Yii::$app->request->post();
            foreach ($search as $k => $v) {
                if ($k == 'curpage') {
                    $curPage = $v;
                    continue;
                }
                if ($search[$k]) {
                    if ($k == 'cat_id') {
                        $catIdCount = count($v);
                        if ($catIdCount > 0) {
                            switch ($catIdCount) {
                                case 1:
                                    $where[] = '(f.id=' . $v[0] . ' or f.parent_id=' . $v[0] . ' or f.top_cat_id=' . $v[0] . ')';
                                    continue;
                                    break;
                                case 2:
                                    $where[] = '(f.id=' . $v[1] . ' or f.parent_id=' . $v[1] . ')';
                                    continue;
                                    $catIds = Category::find()->where('level=3 and parent_id=' . $v[1])->select('id')->asArray()->all();
                                    $catIds = array_column($catIds, 'id');
                                    if ($catIds) {
                                        $where[] = "item_sku.cat_id in (" . implode(',', $catIds) . ")";
                                    }
                                    continue;
                                    break;
                                case 3:
                                    $where[] = 'f.id=' . $v[2];
                                    continue;
                                    $where[] = "item_sku.cat_id=" . $v[2];
                                    continue;
                                    break;
                            }
                            continue;
                        }
                    }
                    $where[] = $k . ' like "%' . $v . '%"';
                }
            }
        }
        $where = implode(' and ', $where);
        $pageSize = Yii::$app->params['pagesize'];
        $query = new \yii\db\Query();
        $curPage = @$curPage ? $curPage : 1;
        $offset = $pageSize * ($curPage - 1);
        $query = $query->select('item_sku.*,store.quantity,item_sku_count.count,f.cat_name')
            ->from('item_sku')
            ->leftJoin('item_sku_store as store', 'item_sku.id=store.sku_id')
            ->leftJoin('item_sku_count', 'item_sku.id=item_sku_count.sku_id')
            ->leftJoin('category as f', 'item_sku.cat_id= f.id')
            ->orderBy('item_sku.id desc')
            ->where($where);
        $totalPage = $query->count();
        $rs = $query->offset($offset)->limit($pageSize)->all();
        // return ;
        $enableEdit = false;
        $enableAdd = false;

        if (Yii::$app->session->get('username') != 'admin') {
            $aulist = explode('|', Yii::$app->session->get('authlist'));
            if (!in_array('goodsedit', $aulist)) {
                $enableEdit = true;
            }
            if (!in_array('goodsadd', $aulist)) {
                $enableAdd = true;
            }

        }
        $cates = Category::find()->where('is_disabled=0')->asArray()->all();
        return ['rs' => $rs, 'totalPage' => $totalPage, 'pageSize' => $pageSize, 'search' => @$search, 'currpage' => $curPage, 'auth' => ['enableEdit' => $enableEdit, 'enableAdd' => $enableAdd], 'category' => $this->categorys($cates)];
    }

    public function actionAdd()
    {
        Yii::$app->response->format = 'json';
        $post = Yii::$app->request->post();
        $cat_id = $post['skuItem']['cat_id'][2];
        if (!$cat_id) {
            return ['rs' => "false", 'msg' => "请选择三级分类"];
        }
        $post['skuItem']['cat_id'] = $cat_id;
        $pic = array_column($post['skuItem']['pics'], 'response');
        $pics = rtrim(implode('|', $pic), '|');
        $db = Yii::$app->db;
        $transaction = $db->beginTransaction();
        $skuMode = new Sku();
        $skustoreMode = new Skustore();
        $itemstoreModel = new Itemstore();
        $time = time();
        try {
            $itemMode = new Item();
            $itemMode->cat_id = $cat_id;
            $itemMode->created_at = $time;
            $itemMode->title = $post['skuItem']['title'];
            $rand = rand(1, 899);
            $itemMode->barcode = $this->bn($rand);
            // $itemMode->cat_id = 1;
            $itemMode->brand_id = 1;
            $itemMode->pic = $pic[0];
            $itemMode->pics = $pics;
            $itemMode->is_self_build = 1;
            $itemMode->description = $post['skuItem']['title'];
            $itemMode->updated_at = $time;
            if (!$itemMode->save(false)) {
                throw new Exception('添加到item表失败');
            }
            $itemstoreModel->item_id = $itemMode->id;
            $itemstoreModel->quantity = 0;
            if (!$itemstoreModel->save(false)) {
                throw new Exception('添加到item_store表失败');
            }
            $skuMode->title = $post['skuItem']['title'];;
            $skuMode->cat_id = $cat_id;
            //$skuMode->cat_id = 1;
            $skuMode->item_id = $itemMode->id;
            $skuMode->bn = $this->bn($itemMode->id);
            $skuMode->description = $post['skuItem']['title'];
            $skuMode->packing_qty = 1;
            $skuMode->packing_unit = '个';
            $skuMode->is_whole = '0';
            $skuMode->moq = 1;
            $skuMode->price = $post['skuItem']['price'];
            $skuMode->detail = $post['skuItem']['detail'];
            $skuMode->barcode = $this->bn($itemMode->id);
            $skuMode->specs = $post['skuItem']['specs'];
            $skuMode->producing_area = $post['skuItem']['producing_area'];
            $skuMode->who_uploader = Yii::$app->session->get('admin_id');
            $skuMode->pic = $pic[0];
            $skuMode->pics = $pics;
            $skuMode->created_at = $time;
            $skuMode->updated_at = $time;
            if (!$skuMode->save(false)) {
                throw new Exception('添加到sku表失败');
            }
            $skustoreMode->sku_id = $skuMode->id;
            $skustoreMode->storage_id = 1;
            $skustoreMode->item_id = $itemMode->id;

            $skustoreMode->quantity = 0;
            if (!$skustoreMode->save(false)) {
                throw new Exception('添加到sku_store表失败');
            }
            $transaction->commit();
            return ['rs' => "true"];
        } catch (Exception $e) {
            $transaction->rollBack();
            return ['rs' => "false", 'msg' => $e->getMessage() . __LINE__];
        }


    }

    private function bn($itemId)
    {
        return 'yp' . str_pad($itemId % 1000, 6, '0', STR_PAD_LEFT) . date("y") . date('m') . date('d') . date("H") . date("i") . date("s");
    }

    public function actionEdit()
    {
        Yii::$app->response->format = 'json';
        $id = Yii::$app->request->get('id');
        $rs = Sku::find()->where('id=' . $id)->asArray()->one();
        $selectCate = Category::findOne($rs['cat_id']);
        if ($selectCate) {
            $rs['cat_id'] = [strval($selectCate->top_cat_id), strval($selectCate->parent_id), $rs['cat_id']];
        } else {
            $rs['cat_id'] = [];
        }

        $rs['is_whole'] = !$rs['is_whole'] ? "是" : "否";
        $rs['pics'] = explode('|', $rs['pics']);
        foreach ($rs['pics'] as $k => $v) {
            $rs['pics'][] = [
                'name' => $v,
                'url' => $v,
                'status' => 'finished'
            ];
            unset($rs['pics'][$k]);
        }
        $cates = [];
        $cates = Category::find()->where('is_disabled=0')->asArray()->all();
        return ['rs' => $rs, 'category' => $this->categorys($cates)];
        // return ['rs'=>$rs];

    }

    public function actionEditsave()
    {
        Yii::$app->response->format = 'json';
        $post = Yii::$app->request->post();
        $cat_id = $post['sku']['cat_id'][2];
        $post['sku']['cat_id'] = $cat_id;
        sort($post['sku']['pics']);
        if ($post['sku']['pics']) {
            @$pic = $post['sku']['pics'][0]['url'];
            @$pics = trim(implode('|', array_column($post['sku']['pics'], url)), '|');
        }
        $model = Sku::findOne($post['sku']['id']);
        $model->load($post, 'sku', false);
        $model->updated_at = time();
        $model->pic = $pic;
        $model->pics = $pics;
        //item

        $itemModel = Item::findOne($model->item_id);
        $itemModel->cat_id = $cat_id;
        $itemModel->save(false);
        // return $model;
        if ($model->save(false)) {
            return ['rs' => "true"];
        } else {
            return ['rs' => "false", 'msg' => '编辑失败'];
        }
    }

    //下架
    public function actionDown()
    {
        Yii::$app->response->format = 'json';
        $post = Yii::$app->request->post();
        if (Sku::updateAll(['status' => 2], ['id' => $post])) {
            return ['rs' => "true"];
        } else {
            return ['rs' => "false", 'msg' => '下架失败'];
        }

    }

    //上架
    public function actionUp()
    {
        Yii::$app->response->format = 'json';
        $post = Yii::$app->request->post();
        if (Sku::updateAll(['status' => 1], ['id' => $post])) {
            return ['rs' => "true"];
        } else {
            return ['rs' => "false", 'msg' => '上架失败'];
        }
    }

    public function actionDel()
    {
        Yii::$app->response->format = 'json';
        $post = Yii::$app->request->post();
        if (Sku::updateAll(['status' => 3], ['id' => $post])) {
            return ['rs' => "true"];
        } else {
            return ['rs' => "false", 'msg' => 'del失败'];
        }

    }
}
