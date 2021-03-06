<?php
/**
 * Created by PhpStorm.
 * User: mac
 * Date: 2017/10/12
 * Time: 下午5:49
 */

namespace app\modules\admin\controllers;

use app\models\Conf;
use app\models\Coupon;
use app\models\CouponItem;
use app\models\Dish;
use app\models\WechatPromotion;
use Yii;
use yii\base\Exception;
use yii\data\ActiveDataProvider;
use yii\helpers\ArrayHelper;

class PromotionController extends N8Base {

    public $cMenu = [
        'default'=>[
            'promotion-index'=>['label'=>'促销简报','url'=>['/admin/promotion/index']],
            'promotion-wechat'=>['label'=>'微信端特惠','url'=>['/admin/promotion/wechat']],
            'promotion-coupon'=>['label'=>'优惠券','url'=>['/admin/promotion/coupon']],
            'promotion-upoff'=>['label'=>'满减','url'=>['/admin/promotion/upoff']],
        ]
    ];

    public function actionIndex(){

        $this->menus = $this->cMenu['default'];
        $this->initActiveMenu('promotion-index');

        return $this->render('index');
    }

    /**
     * 微信端特惠
     */
    public function actionWechat(){
        $model = new WechatPromotion();
        if(Yii::$app->request->isPost){
            $model->load(Yii::$app->request->post());
            if($model->save()){
                return $this->redirect(['/admin/promotion/wechat']);
            }
        }

        $query = WechatPromotion::find();

        $dataProvider = new ActiveDataProvider([
            'query' => $query
        ]);

        $this->menus = $this->cMenu['default'];
        $this->initActiveMenu('promotion-wechat');

        return $this->render('wechat',[
            'dataProvider'=>$dataProvider,
            'model'=>$model,
            'dishes'=>ArrayHelper::map(Dish::find()->all(),'id','title')
        ]);
    }

    public function actionUpdateWechat($id){
        $model = WechatPromotion::findOne($id);

        if(Yii::$app->request->isPost){
            $model->load(Yii::$app->request->post());
            if($model->save()){
                return $this->redirect(['/admin/promotion/wechat']);
            }
        }

        $this->menus = $this->cMenu['default'];
        $menu = ['label'=>'更新微信特惠','url'=>['/admin/promotion/update-wechat','id'=>$id]];
        $this->initActiveMenu('promotion-update-wechat',$menu);

        return $this->render('update-wechat',[
            'model'=>$model,
            'dishes'=>ArrayHelper::map(Dish::find()->all(),'id','title')
        ]);
    }

    public function actionDeleteWechat($id){
        Yii::$app->response->format = 'json';
        try {
            $model = WechatPromotion::findOne($id);
            $model->delete();

            return ['done'=>true,'data'=>'删除成功'];
        }catch(Exception $e){
            return ['done'=>false,'error'=>$e->getMessage()];
        }
    }


    public function actionCoupon(){
        $model = new Coupon();
        if(Yii::$app->request->isPost){
            $model->load(Yii::$app->request->post());

//            $model->begin_at = strtotime($model->begin_at);
            $model->end_at = empty($model->end_at) ? 0 : strtotime($model->end_at);

            if($model->save()){
                CouponItem::initCouponItems($model->id);
                return $this->redirect(['/admin/promotion/coupon']);
            }

        }

        $query = Coupon::find();
        $dataProvider = new ActiveDataProvider([
            'query' => $query
        ]);

        $this->menus = $this->cMenu['default'];
        $this->initActiveMenu('promotion-coupon');

        return $this->render('coupon',[
            'model'=>$model,
            'dataProvider'=>$dataProvider
        ]);
    }

    public function actionUpdateCoupon($id){
        $model = Coupon::findOne($id);

        if(Yii::$app->request->isPost){
            $model->load(Yii::$app->request->post());

            $model->begin_at = strtotime($model->begin_at);
            $model->end_at = empty($model->end_at) ? 0 : strtotime($model->end_at);

            if($model->save()){
                CouponItem::initCouponItems($model->id);
                return $this->redirect(['/admin/promotion/coupon']);
            }
        }

        $model->begin_at = date('Y-m-d H:i:s',$model->begin_at);
        $model->end_at = empty($model->end_at) ? '' : date('Y-m-d H:i:s',$model->end_at);

        $this->menus = $this->cMenu['default'];
        $menu = ['label'=>'更新优惠券活动信息','url'=>['/admin/promotion/update-coupon','id'=>$id]];
        $this->initActiveMenu('promotion-update-coupon',$menu);

        return $this->render('update-coupon',[
            'model'=>$model,
        ]);
    }

    public function actionDeleteCoupon($id){
        Yii::$app->response->format = 'json';
        try {
            $model = Coupon::findOne($id);

            $check = CouponItem::find()->where(['coupon_id'=>$id])->andWhere(['>','user_id',0])->count();
            if($check>0){
                throw new Exception("不允许删除，已经有人领取了某些优惠券");
            }

            CouponItem::deleteAll(['coupon_id'=>$id]);
            $model->delete();

            return ['done'=>true,'data'=>'删除成功'];
        }catch(Exception $e){
            return ['done'=>false,'error'=>$e->getMessage()];
        }
    }


    /**
     * 一个优惠券活动的券列表
     * @param $id
     * @return string
     */
    public function actionCouponItems($id){
        $query = CouponItem::find()->where(['coupon_id'=>$id]);

        $dataProvider = new ActiveDataProvider([
            'query' => $query
        ]);

        $this->menus = $this->cMenu['default'];
        $menu = ['label'=>'券列表','url'=>['/admin/promotion/coupon-items','id'=>$id]];
        $this->initActiveMenu('promotion-coupon-items',$menu);

        return $this->render('coupon-items',[
            'dataProvider'=>$dataProvider
        ]);
    }

    public function actionUpoff(){

        $conf = Conf::readConf('up_off');
        if(Yii::$app->request->isPost){
            Yii::$app->response->format = 'json';
            try {
                $state = Yii::$app->request->post('state');
                $upMoney = Yii::$app->request->post('up_money');
                $offMoney = Yii::$app->request->post('off_money');
                $beginTime = Yii::$app->request->post('begin_time');
                $endTime = Yii::$app->request->post('end_time');

                //todo

                $conf = [
                    'state'=>$state,
                    'up_money'=>$upMoney,
                    'off_money'=>$offMoney,
                    'begin_time'=>strtotime($beginTime),
                    'end_time'=>$endTime == 0 ? 0 : strtotime($endTime),
                ];

                Conf::writeConf('up_off',$conf);

                return ['done'=>true];
            }catch(Exception $e){
                return ['done'=>false,'error'=>$e->getMessage()];
            }
        }

        $this->menus = $this->cMenu['default'];
        $this->initActiveMenu('promotion-upoff');

        return $this->render('upoff',[
            'conf'=>$conf
        ]);
    }
}