<?php
namespace app\domains\warehouse\enum;

use vjik\enum\Enum;

/**
 * Класс  - перечисление статусов устройств
 *
 * Class WarehouseStatus
 * @package app\domains\warehouse\enum
 */
class WarehouseStatus extends Enum
{
    /**
     * Новое
     */
    const NEW = 1;

    /**
     * Брак
     */
    const DEFECT = 2;

    /**
     * Возврат поставщику
     */
    const TO_SUPPLIER = 3;

    /**
     * У монтажника
     */
    const INSTALEER = 4;

    /**
     * Установлено
     */
    const AT_WORK = 5;

    /**
     * Демонтировано
     */
    const DISMANTLED = 6;

    /**
     * Метод возвращает полный ассоциативный массив статусов
     * @return array
     */
    public static function items()
    {
        return [
            self::NEW => 'Новое оборудование',
            self::DEFECT => 'Брак',
            self::TO_SUPPLIER => 'Возврат поставщику',
            self::INSTALEER => 'У монтажника',
            self::AT_WORK => 'Установлено',
            self::DISMANTLED => 'Демонтировано'
        ];
    }
}
