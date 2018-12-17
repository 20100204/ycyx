<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/17
 * Time: 18:43
 */

namespace app\queue;

use app\models\Couponsendlog;
use app\models\Usercoupon;
use app\models\Usercouponlog;
use app\models\Userrelshop;
use app\models\Ycypuser;
use yii\base\Component;
use yii\db\Exception;
use yii\queue\JobInterface;
use Yii;

class CouponJob extends Component implements JobInterface
{
    public $range;
    public $sendLogId;
    public $select;
    public $couponId;
    public $expired;//有效期
    public $title;

    public function execute($queue)
    {
        $users = [];
        $sucessUsers = [];
        switch ($this->range) {
            case "全国":
                $users = Ycypuser::find()->where('id>=1 and status=1')->leftJoin('user_rel_wechat_app', 'user_rel_wechat_app.user_id=user.id')->select('user_rel_wechat_app.nickname,user.id')->asArray()->all();
                break;
            case "市公司":
                $users = Ycypuser::find()->where('user.id>=1 and status=1 and user.area like "%' . $this->select . '%"')->leftJoin('user_rel_wechat_app', 'user_rel_wechat_app.user_id=user.id')->select('user_rel_wechat_app.nickname,user.id')->asArray()->all();

                break;
            case "社区":
                $users = Userrelshop::find()->leftJoin('user_rel_wechat_app', 'user_rel_wechat_app.user_id=user_rel_shop.user_id')->select('user_rel_wechat_app.nickname,user_rel_shop.user_id  as  id')->where("user_rel_shop.is_checked=1 and shop_id=" . $this->select)->asArray()->all();
                //  print_r($users);
                break;
            case "个人":
                $users = Ycypuser::find()->where('user.id>=1 and status=1 and user.mobile=' . $this->select)->leftJoin('user_rel_wechat_app', 'user_rel_wechat_app.user_id=user.id')->select('user_rel_wechat_app.nickname,user.id')->asArray()->all();
                break;
        }
        $time = time();
        $db = Yii::$app->db;
        $transcation = $db->beginTransaction();
        try {
            $rsModel = Couponsendlog::findOne($this->sendLogId);
            $rsModel->status = 2;
            if (!$rsModel->save(false)) {
                throw new Exception("系统异常" . __LINE__);
            }
            foreach ($users as $k => $v) {
                $userModel = Ycypuser::findOne($v['id']);
                $userModel->coupon_msg_count = $userModel->coupon_msg_count+1;
                $userModel->save(false);
                $cModel = new Usercoupon();
                $cModel->coupon_id = $this->couponId;
                $cModel->user_id = $v['id'];
                $cModel->source = "SYSTEM";
                $cModel->created_at = $time;
                $cModel->send_id = $this->sendLogId;
                $cModel->expired_at = $this->expired * (24 * 3600) + $time;
//                $sucessUsers[] = [
//                    "user_id" => $v['id'],
//                    "nickname" => $v['nickname'],
//                    "title" => $this->title,
//                    "num" => 1,
//                    "key" => "vi3aeve7ieB4fa6y",
//                    'expired' => $cModel->expired_at
//                ];
                if (!$cModel->save(false)) {
                    throw new Exception("fail");
                }
                $uModel = new Usercouponlog();
                $uModel->coupon_id = $this->couponId;
                $uModel->user_id = $v['id'];
                $uModel->title = $this->title;
                $uModel->type = "SYSTEM";
                $uModel->send_id = $this->sendLogId;
                $uModel->created_at = $time;
                if (!$uModel->save(false)) {
                    throw new Exception("fail");
                }
            }
            $transcation->commit();
        } catch (Exception $e) {
            $sucessUsers = [];
            Yii::error("ycypsendcoupon" . $e->getMessage());
            $transcation->rollback();
            // $status = 1;
        }
          $sucessUsers=[];
//        if ($sucessUsers) {
//            foreach ($sucessUsers as $sk => $sv) {
//                if(!$sv['user_id']){
//                    continue;
//                }
//                if(!$sv['nickname']){
//                    continue;
//                }
//                if(!$sv['expired']){
//                    continue;
//                }
//                $curl = curl_init();
//                //  curl_setopt($curl, CURLOPT_URL, 'https://user.ycypsz.com/api/v1/wechat/tplmsg/new_coupon'); //测试环境地
//                curl_setopt($curl, CURLOPT_URL, 'https://api.ycypsz.com/api/v1/wechat/tplmsg/new_coupon');//正式环境地
//                curl_setopt($curl, CURLOPT_HEADER, false);
//                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
//                curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
//                curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
//                curl_setopt($curl, CURLOPT_POST, 1);
//                $post_data = array(
//                    "user_id" => $sv['user_id'],
//                    "nickname" => $sv['nickname'],
//                    "title" => $this->title,
//                    "num" => 1,
//                    "key" => "vi3aeve7ieB4fa6y",
//                    'expired' => $sv['expired']
//                );
//                curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);
//                if(curl_exec($curl)===false){
//                    Yii::error("ycypsendcoupon".curl_error($curl));
//                }
//                curl_close($curl);
//            }
//        }

//                    $rs = Couponsendlog::find()->where("coupon_id=".$this->couponId)->one();
//                    $rs->status =  $status;
//                    $rs->save(false);
    }
}
