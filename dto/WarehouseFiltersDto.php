<?php
namespace app\domains\warehouse\dto;

class WarehouseFiltersDto
{
    /**
     * @var string
     */
    protected $field;

    /**
     * @var string
     */
    protected $value;

    public function __construct($field, $value)
    {
        $this->field = $field;
        $this->value = $value;
    }

    /**
     * @return mixed
     */
    public function getField()
    {
        return $this->field;
    }

    /**
     * @param mixed $field
     */
    public function setField($field)
    {
        $this->field = $field;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param mixed $value
     */
    public function setValue($value)
    {
        $this->value = $value;
    }

    /**
     * Метод проверки существования поля в массиве фильтров
     *
     * @param array $filters
     * @param string $field
     *
     * @return bool
     */
    public static function checkFieldExist(array $filters, string $field)
    {
        foreach ($filters as $item) {
            /** @var WarehouseFiltersDto $item */
            if ($item->getField() === $field) {
                if (strlen($item->getValue())) {
                    return true;
                }
            }
        }
        return false;
    }
}