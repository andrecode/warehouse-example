<?php

namespace app\domains\warehouse\entities;

use app\domains\users\entities\Users;
use Yii;

/**
 * This is the model class for table "warehouse_log".
 *
 * @property int $id
 * @property int $warehouse_id
 * @property int $user_id
 * @property string $action
 * @property string $create_date
 *
 * @property Warehouse $warehouse
 * @property Users $user
 */
class WarehouseLog extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'warehouse_log';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['warehouse_id', 'user_id', 'action', 'create_date'], 'required'],
            [['warehouse_id', 'user_id'], 'integer'],
            [['action'], 'string'],
            [['create_date', 'warehouse_id', 'action', 'user_id'], 'safe'],
            [['warehouse_id'], 'exist', 'skipOnError' => true, 'targetClass' => Warehouse::class, 'targetAttribute' => ['warehouse_id' => 'id']],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => Users::class, 'targetAttribute' => ['user_id' => 'id']],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'warehouse_id' => 'Warehouse ID',
            'user_id' => 'User ID',
            'action' => 'Action',
            'create_date' => 'Create Date',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getWarehouse()
    {
        return $this->hasOne(Warehouse::class, ['id' => 'warehouse_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(Users::class, ['id' => 'user_id']);
    }
}
