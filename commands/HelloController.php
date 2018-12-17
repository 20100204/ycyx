<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace app\commands;

use app\models\Orderstatistics;
use yii\console\Controller;

/**
 * This command echoes the first argument that you have entered.
 *
 * This command is provided as an example for you to learn how to create console commands.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class HelloController extends Controller
{
    /**
     * This command echoes what you have entered as the message.
     * @param string $message the message to be echoed.
     */
//    public function actionIndex($message = 'hello world')
//    {
//        echo $message . "\n";
//    }

      public function actionIndex(){

          $model = new Orderstatistics();
          $model->order_created_time = strtotime(date("Y-m-d"));
          $model->wechat_amount = 1000;
          $model->all_amount = 1000;
          $model->balance_amount = 1000;
          $model->system_cancle_amount = 1000;
          $model->save(false);


      }


}
