<?php

namespace app\domains\warehouse\entities;

use app\domains\companies\entities\Companies;
use app\domains\orders\entities\Orders;
use app\domains\references\entities\MaterialStatuses;
use app\domains\references\entities\Models;
use app\domains\references\entities\Stocks;
use app\domains\users\entities\Users;
use Yii;

/**
 * This is the model class for table "warehouse".
 *
 * @property int $id
 * @property int $company_id
 * @property int $shipper_id
 * @property int $model_id
 * @property int $stock_id
 * @property string $serial
 * @property int $amount
 * @property int $status_id
 * @property int $responsible
 * @property string $pin_code
 * @property string $comment
 * @property int $user_id
 * @property string $create_date
 * @property int $parent_id
 *
 * @property Users $user
 * @property Companies $company
 * @property Models $model
 * @property Users $responsibleUser
 * @property MaterialStatuses $status
 * @property Stocks $stock
 * @property WarehouseLog[] $warehouseLogs
 * @property OrdersWarehouse[] $ordersWarehouses
 * @property Warehouse[] $childs
 * @property Warehouse $parent
 * @property WarehouseComments[] $comments
 * @property Companies $shipper
 */
class Warehouse extends \yii\db\ActiveRecord
{
    /**
     * @var
     */
    public $vendor;

    /**
     * @var
     */
    public $type;

    /**
     * @var
     */
    public $order_id;
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'warehouse';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['company_id', 'model_id', 'stock_id', 'status_id', 'user_id'], 'required'],
            [['company_id', 'model_id', 'stock_id', 'amount', 'status_id', 'responsible', 'user_id', 'parent_id'], 'integer'],
            [['serial', 'comment'], 'string'],
            [['create_date', 'status_id', 'order_id'], 'safe'],
            [['pin_code'], 'string', 'max' => 255],
            [
                ['user_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => Users::class,
                'targetAttribute' => ['user_id' => 'id']
            ],
            [
                ['company_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => Companies::class,
                'targetAttribute' => ['company_id' => 'id']
            ],
            [
                ['shipper_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => Companies::class,
                'targetAttribute' => ['shipper_id' => 'id']
            ],
            [
                ['model_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => Models::class,
                'targetAttribute' => ['model_id' => 'id']
            ],
            [
                ['responsibleUser'],
                'exist',
                'skipOnError' => true,
                'targetClass' => Users::class,
                'targetAttribute' => ['responsible' => 'id']
            ],
            [
                ['status_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => MaterialStatuses::class,
                'targetAttribute' => ['status_id' => 'id']
            ],
            [
                ['stock_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => Stocks::class,
                'targetAttribute' => ['stock_id' => 'id']
            ],

            [
                ['parent_id'],
                'exist',
                'skipOnError' => true,
                'targetClass' => self::class,
                'targetAttribute' => ['parent_id' => 'id']
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'Код',
            'company_id' => 'Собственник',
            'model_id' => 'Модель',
            'stock_id' => 'Склад',
            'serial' => 'Серийный номер',
            'amount' => 'Кол-во',
            'status_id' => 'Статус',
            'responsible' => 'Ответственный',
            'pin_code' => 'Pin-Код',
            'comment' => 'Комментарии',
            'user_id' => 'Добавил',
            'create_date' => 'Дата создания',
            'vendor' => 'Производитель',
            'type' => 'Тип',
            'order_id' => 'Код заявки',
            'parent_id' => 'Родительская заявка',
            'shipper_id' => 'Поставщик'
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(Users::class, ['id' => 'user_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCompany()
    {
        return $this->hasOne(Companies::class, ['id' => 'company_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getShipper()
    {
        return $this->hasOne(Companies::class, ['id' => 'shipper_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getModel()
    {
        return $this->hasOne(Models::class, ['id' => 'model_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getResponsibleUser()
    {
        return $this->hasOne(Users::class, ['id' => 'responsible']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getStatus()
    {
        return $this->hasOne(MaterialStatuses::class, ['id' => 'status_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getStock()
    {
        return $this->hasOne(Stocks::class, ['id' => 'stock_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getChilds()
    {
        return $this->hasMany(Warehouse::class, ['parent_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getComments()
    {
        return $this->hasMany(WarehouseComments::class, ['warehouse_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getParent()
    {
        return $this->hasOne(self::class, ['id' => 'parent_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getWarehouseLogs()
    {
        return $this->hasMany(WarehouseLog::class, ['warehouse_id' => 'id']);
    }

    /**
     *
     */
    public function afterFind()
    {
        parent::afterFind();

        $this->vendor = $this->model->vendor_id;
        $this->type = $this->model->type_id;
        $orderWarehouse = OrdersWarehouse::find()->where(['warehouse_id' => $this->id])->one();
        if (isset($orderWarehouse)) {
            $this->order_id = $orderWarehouse->order_id;
        }
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getOrdersWarehouses()
    {
        return $this->hasmany(OrdersWarehouse::class, ['warehouse_id' => 'id']);
    }
}
