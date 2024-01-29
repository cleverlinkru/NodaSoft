<?php

namespace NW\WebService\References\Operations\Notification\Concators;

class Contractor
{
    const TYPE_CUSTOMER = 0;
    public $id;
    public $type;
    public $name;

    public static function getInstanceById(int $resellerId): self
    {
        return new self($resellerId); // fakes the getById method
    }

    public function getFullName(): string
    {
        return $this->name . ' ' . $this->id;
    }
}
