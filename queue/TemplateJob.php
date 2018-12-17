<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/17
 * Time: 18:43
 */
namespace app\queue;
use app\models\Usercoupon;
use app\models\Ycypuser;
use yii\base\Component;
use yii\queue\JobInterface;

class TemplateJob extends Component implements JobInterface
{
                public $userId;
                public function execute($queue)
                {
                    $userModel = Ycypuser::findOne($this->userId);
                    $user = Ycypuser::find()->select('id')->asArray()->where('id>1')->all();
                    if($user){
                        foreach ($user as $v){
                           $modle = new Usercoupon();
                            $modle->coupon_id = 1;
                            $modle->user_id = $v['id'];
                            $modle->source = "SYSTEM";
                            $modle->created_at = time();
                            $modle->expired_at = time();
                            $modle->save(false);

                        }
                    }
                    //$userModel->password=time();
                   // $userModel->save(false);
                }
}
