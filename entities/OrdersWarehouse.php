<?php

namespace app\domains\warehouse\entities;

use app\domains\orders\entities\Orders;
use app\domains\users\entities\Users;
use Yii;

/**
 * This is the model class for table "orders_warehouse".
 *
 * @property int $id
 * @property int $order_id
 * @property int $warehouse_id
 * @property string $comment
 * @property int $user_id
 * @property string $create_date
 *
 * @property Users $user
 * @property Orders $order
 * @property Warehouse $warehouse
 */
class OrdersWarehouse extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'orders_warehouse';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['order_id', 'warehouse_id', 'user_id'], 'required'],
            [['order_id', 'warehouse_id', 'user_id'], 'integer'],
            [['comment'], 'string'],
            [['create_date'], 'safe'],
            [['warehouse_id', 'order_id'], 'unique', 'targetAttribute' => ['warehouse_id', 'order_id']],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => Users::class, 'targetAttribute' => ['user_id' => 'id']],
            [['order_id'], 'exist', 'skipOnError' => true, 'targetClass' => Orders::class, 'targetAttribute' => ['order_id' => 'id']],
            [['warehouse_id'], 'exist', 'skipOnError' => true, 'targetClass' => Warehouse::class, 'targetAttribute' => ['warehouse_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'order_id' => 'Order ID',
            'warehouse_id' => 'Warehouse ID',
            'comment' => 'Comment',
            'user_id' => 'User ID',
            'create_date' => 'Create Date',
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
    public function getOrder()
    {
        return $this->hasOne(Orders::class, ['id' => 'order_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getWarehouse()
    {
        return $this->hasOne(Warehouse::class, ['id' => 'warehouse_id']);
    }
}
