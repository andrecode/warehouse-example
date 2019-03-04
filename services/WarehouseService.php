<?php

namespace app\domains\warehouse\services;

use app\domains\orders\entities\Orders;
use app\domains\users\entities\Users;
use app\domains\warehouse\dto\WarehouseFiltersDto;
use app\domains\warehouse\dto\WarehouseResponseDto;
use app\domains\warehouse\entities\OrdersWarehouse;
use app\domains\warehouse\entities\WarehouseComments;
use app\domains\warehouse\entities\WarehouseLog;
use app\domains\warehouse\enum\WarehouseStatus;
use app\domains\warehouse\repositories\WarehouseRepository;
use app\domains\references\entities\MaterialStatuses;
use app\domains\warehouse\entities\Warehouse;
use app\domains\users\services\UserService;
use app\models\ActiveDataProviderPager;
use app\domains\users\enum\Roles;
use Monolog\Logger;
use yii\data\ActiveDataProvider;
use yii\data\Pagination;
use yii\db\ActiveQuery;
use yii\db\Exception;
use yii\db\Expression;
use yii\db\StaleObjectException;

/**
 * Сервис для работы с устройствами на складе
 *
 * @package app\domains\warehouse\services
 */
class WarehouseService
{
    /** @var Warehouse $warehouse Модель устройства на складе */
    protected $warehouse;

    /** @var WarehouseRepository $repository Репозиторий склада */
    protected $repository;

    /** @var UserService $userService Сервис работы с пользователями */
    protected $userService;

    /** @var Logger $logger */
    protected $logger;

    /**
     * Констуктор сервиса
     *
     * @param Warehouse $warehouse
     * @param WarehouseRepository $repository
     * @param UserService $userService
     */
    public function __construct(
        Warehouse $warehouse,
        WarehouseRepository $repository,
        UserService $userService
    ) {
        $this->warehouse = $warehouse;
        $this->repository = $repository;
        $this->userService = $userService;
        $monologComponent = \Yii::$app->monolog;
        $this->logger = $this->logger = $monologComponent->getLogger('warehouse');
    }

    /**
     * Метод сохранения данных устройства
     *
     * @param $r array Массив из реквеста
     *
     * @return array|bool массив ошибок или true
     */
    public function save($r)
    {
        if ((int)$r['id'] > 0) {
            $this->warehouse = Warehouse::findOne($r['id']);
            $attributes = $this->warehouse->getAttributes();
        } else {
            $attributes = [];
            $this->warehouse->setIsNewRecord(true);
        }

        $this->warehouse->setAttributes($r, false);
        $this->warehouse->create_date = new Expression('NOW()');
        $this->warehouse->user_id = \Yii::$app->user->id;

        if ($this->warehouse->validate() && $this->warehouse->save()) {
            $this->logger->addInfo('Успешно сохранил запись', ['warehouse' => $this->warehouse]);

            if ($this->warehouse->isNewRecord) {
                $this->warehouse->id = \Yii::$app->db->getLastInsertID();
                $this->repository->logCreate($this->warehouse);
            } else {
                try {
                    OrdersWarehouse::deleteAll([
                        'warehouse_id' => $r['id'],
                    ]);
                } catch (\Exception $e) {
                    $this->logger->addAlert('Проблема удаления связи', $e->getMessage());
                };
                if (isset($r['order_id']) && (int)$r['order_id'] > 0) {
                    $order = Orders::findOne($r['order_id']);
                    $ow = new OrdersWarehouse;
                    $ow->order_id = $order->id;
                    $ow->warehouse_id = $r['id'];
                    $ow->create_date = new Expression('NOW()');
                    $ow->user_id = \Yii::$app->user->id;
                    if (!$ow->save()) {
                        $this->logger->addAlert('Проблема с сохранением связи заявки и девайса', [
                            'errors' => $ow->errors
                        ]);
                        return $ow->errors;
                    } else {
                        $this->logger->addInfo(
                            'Ручная привязка устройства к заявке',
                            ['order_id' => $r['order_id'], 'warehouse_id' => $r['id']]
                        );
                    }
                    $this->repository->addDeviceToOrder($r['order_id'], $r['id'], 'Ручная привязка устройства к заявке');
                }
            }

            $newAttributes = $this->warehouse->getAttributes();
            $changedFields = array_diff($newAttributes, $attributes);

            $this->repository->logUpdate($this->warehouse, $this->prepareLogAttributes($changedFields));

            return true;
        }

        return $this->warehouse->errors;
    }

    /**
     * Служебный метод подготовки данных к логгированию
     *
     * @param array $attributes
     *
     * @return array массив атрибутов
     */
    protected function prepareLogAttributes(array $attributes)
    {
        unset($attributes['create_date']);

        return $attributes;
    }

    /**
     * Метод получения статусов устройств для селекта
     *
     * @param Warehouse $warehouse
     *
     * @return array массив статусов
     */
    public function getStatuses(Warehouse $warehouse)
    {
        $allStatuses = MaterialStatuses::getActiveList();
        unset($allStatuses[$warehouse->status_id]);
        $result = $allStatuses;

        return $result;
    }

    /**
     * Метод получения данных для главной таблицы устройств
     *
     * @param array $sortField
     *
     * @param array $filters
     * @return ActiveDataProviderPager провайдер с данными
     */
    public function getData(array $sortField, array $filters)
    {
        $query = $this->warehouse->find()->joinWith('model');

        if (isset($sortField['field']) && strlen($sortField['field'])) {
            $query->orderBy([$sortField['field'] => $sortField['order']]);
        }

        if ($this->userService::checkAccess([Roles::INSTALLER])) {
            $query->andWhere(['responsible' => \Yii::$app->user->id]);
        }

        $data = new ActiveDataProvider(['query' => $this->getFilters($filters, $query)]);
        $result = new ActiveDataProviderPager(
            $data,
            new Pagination([
                'totalCount' => $data->totalCount,
                'pageSize' => \Yii::$app->params['warehouse-item-per-page'],
            ])
        );
        $data->setPagination($result->getPager());

        return $result;
    }

    /**
     * @param WarehouseFiltersDto[] $filters
     * @param ActiveQuery $query
     *
     * @return ActiveQuery
     */
    public function getFilters(array $filters, ActiveQuery $query)
    {
        if (count($filters)) {
            foreach ($filters as $f) {
                if ($f->getField() === 'serial' && mb_strlen($f->getValue())) {
                    $query->andWhere('warehouse.serial like ("%' . trim($f->getValue()) . '%")');
                }

                if ($f->getField() === 'model' && mb_strlen($f->getValue())) {
                    $query->joinWith(['model m'])->andFilterWhere(['m.id' => $f->getValue()]);
                }

                if ($f->getField() === 'status_id' && mb_strlen($f->getValue())) {
                    $query->andFilterWhere(['warehouse.status_id' => $f->getValue()]);
                }
                if ($f->getField() === 'responsible_id' && mb_strlen($f->getValue())) {
                    if ($f->getValue() == '0') {
                        $query->andWhere('warehouse.responsible is null');
                    } else {
                        $query->andFilterWhere(['warehouse.responsible' => $f->getValue()]);
                    }
                }
                if ($f->getField() === 'company_id' && mb_strlen($f->getValue())) {
                    $query->andFilterWhere(['warehouse.company_id' => $f->getValue()]);
                }
                if ($f->getField() === 'warehouse_type' && mb_strlen($f->getValue())) {
                    switch ($f->getValue()) {
                        case 1:
                            $query->andWhere('amount = 1 and length(serial)');
                            break;
                        case 2:
                            $query->andWhere('amount > 1')
                                ->orderBy(['serial' => SORT_NUMERIC]);
                            break;
                    }
                }
            }
        } else {
            $query->andWhere('warehouse.status_id <> ' . WarehouseStatus::AT_WORK);
        }

        return $query;
    }

    /**
     * Перемещает оборудование по складу
     *
     * @param Warehouse $model
     * @param array $data
     * @return WarehouseResponseDto
     */
    public function move(Warehouse $model, array $data): WarehouseResponseDto
    {
        $result = new WarehouseResponseDto;

        $this->logger->addAlert('Начинаем перемещение оборудования', ['data' => $data]);

        // Если есть серийный номер или кол-во запасов равно кол-ву перемещения, значит перемещение единичное
        if ((isset($data['serial']) && mb_strlen($data['serial'])) ||
            (isset($data['amount']) && (int)$model->amount === (int)$data['amount'])
        ) {
            $this->logger->addAlert('Обычное перемещение');
            $saveResult = $this->save($data);
            if (is_array($saveResult)) {
                $result->addMessage($saveResult);
                $result->setCode(WarehouseResponseDto::ERROR);
            }
            $this->logger->addAlert('Простое перемещение выполнено');
            return $result;
        }

        if (!isset($data['responsible'])) {
            $this->logger->addAlert('Не был выбран ответственный сотрудник');
            $result->addMessage('Не был выбран ответственный сотрудник');
            $result->setCode(WarehouseResponseDto::ERROR);
        }


        if (!isset($data['amount'])) {
            $this->logger->addAlert('Не указано кол-во');
            $result->addMessage('Не указано кол-во');
            $result->setCode(WarehouseResponseDto::ERROR);
        }

        if (isset($data['amount']) && (int)$data['amount'] > (int)$model->amount) {
            $this->logger->addAlert('Запрошенное кол-во больше, чем есть на складе', [
                'Запрошено: ' => $data['amount'],
                'Есть' => $model->amount
            ]);

            $result->addMessage('Запрошенное кол-во больше, чем есть на складе');
            $result->setCode(WarehouseResponseDto::ERROR);
        }

        if ($result->getCode() !== WarehouseResponseDto::OK) {
            return $result;
        }

        $this->logger->addAlert('Все в порядке, переходим к разделению запасов');
        $old = Warehouse::findOne($data['id']);

        $newModel = new Warehouse;
        $newModel->attributes = $old->attributes;

        $newModel->amount = $data['amount'];
        $model->amount = $model->amount - $data['amount'];
        if (!$model->save()) {
            $this->logger->addAlert('Проблема с сохранением данных', ['errors' => $model->errors]);
            $result->setCode(WarehouseResponseDto::ERROR);
            $result->addMessage('Проблема с сохранением данных');

            return $result;
        }

        $newModel->company_id = $data['company_id'];
        $newModel->responsible = $data['responsible'];
        $newModel->user_id = \Yii::$app->user->id;
        $newModel->comment = isset($data['comment']) ? $data['comment'] : '';
        $newModel->status_id = isset($data['status_id']) ? $data['status_id'] : $old->status_id;
        $newModel->parent_id = $model->id;
        $newModel->create_date = new Expression('NOW()');

        if (!$newModel->save()) {
            $this->logger->addAlert('Проблема с сохранением данных', ['model' => $newModel->errors]);
            $result->setCode(WarehouseResponseDto::ERROR);
            $result->addMessage('Проблема с сохранением данных');
        }

        return $result;
    }

    /**
     * Метод сохранения нового комментария к смене статуса устройства
     *
     * @param int $oldStatus
     * @param int $newStatus
     * @param int $warehouseId
     *
     * @param int|null $responsible
     * @return WarehouseResponseDto
     */
    public function saveCommentStatus(
        int $oldStatus,
        int $newStatus,
        int $warehouseId,
        int $responsible = 0
    ): WarehouseResponseDto {
        $result = new WarehouseResponseDto;
        $model = new WarehouseComments;
        $oldStatus = MaterialStatuses::findOne($oldStatus);
        $newStatus = MaterialStatuses::findOne($newStatus);
        $responsibleUser = '';
        if ($responsible  > 0) {
            $responsibleUser = Users::findOne($responsible);
        }

        $model->create_date = new Expression('NOW()');
        $model->user_id = \Yii::$app->user->id;
        $model->warehouse_id = $warehouseId;
        $model->status_id = $newStatus->id;
        $model->comment = 'Смена статуса устройства c : ' .
        $oldStatus->getWithColor() . ' на  ' .
        $newStatus->getWithColor() . '<br/>Ответственный: ' . $responsibleUser->getShortName();

        if (!$model->save()) {
            $this->logger->addAlert('Проблема с соохранением комментария к устройству', $model->errors);
            $result->addMessage('Проблема с соохранением комментария к устройству');
            $result->setCode(500);
        }

        return $result;
    }

    /**
     * @param int $id
     * @return WarehouseResponseDto
     */
    public function delete(int $id): WarehouseResponseDto
    {
        $result = new WarehouseResponseDto();
        $model = $this->warehouse::findOne($id);
        $modelId = $model->id;
        $modelName = $model->model->name;
        WarehouseLog::deleteAll(['warehouse_id' => $id]);
        WarehouseComments::deleteAll(['warehouse_id' => $id]);

        try {
            $model->delete();
        } catch (StaleObjectException $e) {
            $this->logger->addAlert($e->getMessage());
            $result->setCode(WarehouseResponseDto::ERROR);
            $result->addMessage('Проблема с удалением со склада');
        } catch (\Exception $e) {
            $result->setCode(WarehouseResponseDto::ERROR);
            $result->addMessage('Проблема с удалением со склада');
            $this->logger->addAlert($e->getMessage());
        } catch (\Throwable $e) {
            $result->setCode(WarehouseResponseDto::ERROR);
            $result->addMessage('Проблема с удалением со склада');
            $this->logger->addAlert($e->getMessage());
        }
        $this->logger->addInfo(
            'Элемент со склада успешно удален',
            [
                'user_id' => \Yii::$app->user->id,
                'modelName' => $modelName,
                'id' => $modelId,
            ]
        );
        return $result;
    }


}