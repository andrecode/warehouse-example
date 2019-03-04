<?php
namespace app\domains\warehouse\repositories;

use app\domains\orders\entities\Orders;
use app\domains\references\entities\MaterialStatuses;
use app\domains\references\entities\OrderStatuses;
use app\domains\warehouse\entities\OrdersWarehouse;
use app\domains\warehouse\entities\WarehouseComments;
use app\domains\warehouse\entities\WarehouseLog;
use app\domains\warehouse\enum\WarehouseStatus;
use app\domains\companies\entities\Companies;
use app\domains\warehouse\entities\Warehouse;
use yii\helpers\ArrayHelper;
use yii\db\ActiveRecord;
use yii\db\Expression;
use yii\helpers\Json;
use Monolog\Logger;

/**
 * Хранилище - репозиторий для работы склада
 *
 * Class DevicesRepository
 * @package app\domains\warehouse\repositories
 */
class WarehouseRepository
{
    /** @var Warehouse $model Модель склада */
    protected $model;

    /** @var WarehouseLog $log Модель Лога склада */
    protected $log;

    /** @var Logger $logger Системный логгер */
    protected $logger;

    /**
     * Конструктор класса
     *
     * @param Warehouse $model
     * @param WarehouseLog $log
     */
    public function __construct(Warehouse $model, WarehouseLog $log)
    {
        $this->model = $model;
        $this->log = $log;
        $monologComponent = \Yii::$app->monolog;
        $this->logger = $monologComponent->getLogger('warehouse');
    }

    /**
     * Вспомогательный метод поиска устройств
     *
     * @return \yii\db\ActiveQuery
     */
    public function find()
    {
        return $this->model->find();
    }

    /**
     * Метод обновления лога, в случае смены атрибутов записи об устройстве
     *
     * @param Warehouse $model модель устройства на складе
     * @param array $dirtyAttrs массив свойств
     *
     * @return array|bool массив ошибок или true
     */
    public function logUpdate(Warehouse $model, array $dirtyAttrs)
    {
        $log = new WarehouseLog;
        $log->warehouse_id = $model->id;
        $log->create_date = new Expression('NOW()');
        $log->action = 'Внесены изменения в данные об оборудовании:' . Json::encode($dirtyAttrs);

        $log->user_id = \Yii::$app->user->id;
        if (!$log->save()) {
            $this->logger->addError('Проблема с добавлением записи в лог оборудования', ['errors' => $log->errors]);

            return $log->errors;
        }
        $this->logger->addInfo('Внесены изменения в оборудование', ['attributes' => $dirtyAttrs]);

        return true;
    }

    /**
     * Метод добавления записи в лог
     *
     * @param Warehouse $model Модель устройства на складе
     *
     * @return array|bool ошибки или true
     */
    public function logCreate(Warehouse $model)
    {
        $log = new WarehouseLog;

        $log->warehouse_id = $model->id;
        $log->user_id = \Yii::$app->user->id;
        $log->create_date = $model->create_date;
        $info = $model->id . ' ' . $model->model->name . ' ' . $model->vendor->name;
        $info .= ' s/n: ' . $model->serial . ' кол-во: ' . $model->amount;

        $log->action = 'Добавлено оборудование ' . $info;
        if (!$log->save()) {
            $this->logger->addError('Проблема с добавлением записи в лог оборудования', ['model' => $log]);

            return $log->errors;
        }
        $this->logger->addInfo('Добавлено оборудование', ['model' => $model]);

        return true;
    }

    /**
     * Метод получения массива компаний для селекта
     *
     * @return array Массив компаний
     */
    public function getCompanies()
    {
        return  ArrayHelper::map([null => 'Ничего не выбрано'] +
            Companies::find()->cache(3600)->where(['active' => 1])->all(), 'id', 'name');
    }

    /**
     * Сеттер модели
     *
     * @param Warehouse $model
     *
     * @return $this
     */
    public function setModel(Warehouse $model)
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Метод поиска устройства на складе по серийному номеру
     *
     * @param string $serial Серийный номер
     *
     * @param int $statusId
     *
     * @return ActiveRecord[] Результат поиска
     */
    public function searchBySerial(string $serial, int $statusId = WarehouseStatus::NEW)
    {
        return $this->model::find()
            ->where('serial like ("%' . trim($serial) . '%")')
            ->andWhere(['status_id' => $statusId])
            ->andWhere(['responsible' => \Yii::$app->user->id])
            ->all();
    }

    /**
     * Метод привязки устройства к заявке
     *
     * @param int $orderId
     * @param int $warehouseId
     * @param string $comment
     *
     * @return array|bool массив ошибок или true
     */
    public function addDeviceToOrder(int $orderId, int $warehouseId, string $comment)
    {
        $orderWarehouse = new OrdersWarehouse;
        $orderWarehouse->comment = $comment;
        $orderWarehouse->order_id = $orderId;
        $orderWarehouse->warehouse_id = $warehouseId;
        $orderWarehouse->user_id = \Yii::$app->user->id;
        $orderWarehouse->create_date = new Expression('NOW()');

        if ($orderWarehouse->save()) {
            /** @var Warehouse $model */
            $model = $this->find()->where(['id' => $warehouseId])->one();
            $oldStatus = $model->status_id;
            $model->status_id = WarehouseStatus::AT_WORK;

            if ($model->save()) {
                $this->logUpdate($model, [
                    'Прежний статус' => WarehouseStatus::items()[$oldStatus],
                    'Новый статус' => WarehouseStatus::items()[$model->status_id]
                ]);

                return true;
            } else {
                return $model->errors;
            }
        } else {
            return $orderWarehouse->errors;
        }
    }


    /**
     * Сохранение коммента
     *
     * @param int $id
     * @param string $text
     *
     * @param null $userId
     * @return WarehouseComments|array
     */
    public function saveComment(int $id, string $text, $userId = null)
    {
        if ($id > 0) {
            $model = Warehouse::findOne($id);
            $comment = new WarehouseComments;
            $comment->warehouse_id = $id;
            $comment->create_date = new Expression('NOW()');
            $comment->comment = trim($text);
            $comment->status_id = $model->status_id;
            $comment->user_id = $userId ? $userId: \Yii::$app->user->id;
            if (!$comment->save()) {
                return $comment->errors;
            }

            return $comment;
        }
    }

    /**
     * @return Warehouse[]
     */
    public function getConsumables()
    {
        return $this->find()->where([
            'responsible' => \Yii::$app->user->id,
            'status_id' => WarehouseStatus::INSTALEER
        ])->andWhere('serial = ""')->all();
    }

    /**
     * @param Orders $order
     * @return Companies[]|OrdersWarehouse[]|Warehouse[]|array|ActiveRecord[]
     */
    public function getConsumablesForOrder(Orders $order)
    {
        return $this->find()->where([
          'status_id' => WarehouseStatus::AT_WORK,
        ])
            ->joinWith('ordersWarehouses ow')
            ->andWhere(['ow.order_id' => $order->id])
            ->andWhere('serial = ""')->all();
    }

}