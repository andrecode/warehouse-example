<?php

namespace app\domains\warehouse\entities;

use app\domains\references\entities\MaterialStatuses;
use app\domains\users\entities\Users;
use Yii;

/**
 * This is the model class for table "warehouse_comments".
 *
 * @property int $id
 * @property int $warehouse_id
 * @property int $user_id
 * @property string $comment
 * @property string $create_date
 * @property int $status_id
 *
 * @property Users $user
 * @property Warehouse $warehouse
 * @property MaterialStatuses $status
 */
class WarehouseComments extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'warehouse_comments';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['warehouse_id', 'user_id', 'status_id'], 'required'],
            [['warehouse_id', 'user_id', 'status_id'], 'integer'],
            [['comment'], 'string'],
            [['create_date'], 'safe'],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => Users::class, 'targetAttribute' => ['user_id' => 'id']],
            [['warehouse_id'], 'exist', 'skipOnError' => true, 'targetClass' => Warehouse::class, 'targetAttribute' => ['warehouse_id' => 'id']],
            [['status_id'], 'exist', 'skipOnError' => true, 'targetClass' => MaterialStatuses::class, 'targetAttribute' => ['status_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'warehouse_id' => 'Warehouse ID',
            'user_id' => 'User ID',
            'comment' => 'Comment',
            'create_date' => 'Create Date',
            'status_id' => 'Status ID',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(Users::className(), ['id' => 'user_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getWarehouse()
    {
        return $this->hasOne(Warehouse::className(), ['id' => 'warehouse_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getStatus()
    {
        return $this->hasOne(MaterialStatuses::className(), ['id' => 'status_id']);
    }
}
