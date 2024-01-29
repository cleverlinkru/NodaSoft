<?php

namespace NW\WebService\References\Operations\Notification\Operations;

abstract class Operation
{
    abstract public function doOperation(): array;

    public function getRequest($pName)
    {
        return $_REQUEST[$pName];
    }
}
