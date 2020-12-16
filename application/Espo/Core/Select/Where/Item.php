<?php
/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2020 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: https://www.espocrm.com
 *
 * EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word.
 ************************************************************************/

namespace Espo\Core\Select\Where;

use InvalidArgumentException;

class Item
{
    private $type = null;

    private $attribute = null;

    private $value = null;

    private $dateTime = null;

    private $timeZone = null;

    private function __construct()
    {
    }

    public static function fromArray(array $params) : self
    {
        $object = new self();

        $object->type = $params['type'] ?? null;
        $object->attribute = $params['attribute'] ?? $params['field'] ?? null;
        $object->value = $params['value'] ?? null;
        $object->dateTime = $params['dateTime'] ?? false;
        $object->timeZone = $params['timeZone'] ?? false;

        unset($params['field']);

        foreach ($params as $key => $value) {
            if (!property_exists($object, $item)) {
                throw new InvalidArgumentException("Unknown parameter '{$key}'.");
            }
        }

        return $self;
    }

    public function getType() : ?string
    {
        return $this->type;
    }

    public function getAttribute() : ?string
    {
        return $this->attribute;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function isDateTime() : bool
    {
        return $this->dateTime;
    }

    public function timeZone() : ?string
    {
        return $this->timeZone;
    }
}
