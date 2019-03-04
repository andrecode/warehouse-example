<?php
namespace app\domains\warehouse\dto;

/**
 * Class WarehouseResponseDto
 * @package app\domains\warehouse\dto
 */
class WarehouseResponseDto
{
    /**
     *
     */
    const OK = 200;

    /**
     *
     */
    const ERROR = 500;

    /** @var int $code */
    protected $code;

    /** @var array $messages */
    protected $messages = [];


    /**
     * WarehouseResponseDto constructor.
     */
    public function __construct()
    {
        $this->setCode(WarehouseResponseDto::OK);
        $this->addMessage('Все в норме');
    }

    /**
     * @return mixed
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @param string $message
     */
    public function addMessage(string $message)
    {
        $this->messages[] = $message;
    }

    /**
     * @param int $code
     */
    public function setCode(int $code)
    {
        $this->code = $code;
    }

    /**
     * @return array
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * @return string
     */
    public function getMessageText(): string
    {
        return implode(',', $this->messages);
    }

}