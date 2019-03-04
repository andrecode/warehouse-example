<?php
namespace app\domains\warehouse\repositories;

use app\domains\references\entities\Vendors;
use app\domains\references\entities\Models;
use app\models\ActiveDataProviderPager;
use yii\data\ActiveDataProvider;
use yii\data\Pagination;

/**
 * Класс - репозиторй для работы с устройствами
 *
 * @package app\domains\users\repositories
 */
class DevicesRepository
{
    /** @var Models $model Модель устройства */
    private $model;

    /** @var Vendors $vendor Производитель устройства */
    private $vendor;

    /**
     * Конструктор класса
     *
     * @param Vendors $vendor
     * @param Models $model
     */
    public function __construct(Vendors $vendor, Models $model)
    {
        $this->vendor = $vendor;
        $this->model = $model;
    }

    /**
     * Метод получения провайдера данных устройств
     *
     * @return ActiveDataProviderPager Провайдер с данными
     */
    public function getDevices()
    {
        $data = new ActiveDataProvider(['query' => self::find()]);
        $result = new ActiveDataProviderPager(
            $data,
            new Pagination([
                'totalCount' => $data->totalCount,
                'pageSize' => \Yii::$app->params['devices-per-page'],
            ])
        );
        $data->setPagination($result->getPager());

        return $result;
    }

    /**
     * Служебный метод поиска устрйств
     *
     * @return \yii\db\ActiveQuery
     */
    protected function find()
    {
        return $this->model->find();
    }
}