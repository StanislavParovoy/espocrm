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

namespace Espo\Core\Select;

use Espo\Core\{
    Exceptions\Error,
};

/**
 * Search parameters.
 */
class SearchParams
{
    protected $rawParams;

    const ORDER_ASC = 'ASC';
    const ORDER_DESC = 'DESC';

    private function __construct()
    {
    }

    public function getRawParams() : array
    {
        return $this->rawParams;
    }

    public function getSelect() : ?array
    {
        return $this->rawParams['select'] ?? null;
    }

    public function getOrderBy() : ?string
    {
        return $this->rawParams['orderBy'] ?? null;
    }

    public function getOrder() : ?string
    {
        if (is_null($this->rawParams['order'])) {
            return null;
        }

        if (strtolower($this->rawParams['order']) === 'desc') {
            return self::ORDER_DESC;
        }

        return self::ORDER_ASC;
    }

    public function getOffset() : ?int
    {
        return $this->rawParams['offset'] ?? null;
    }

    public function getMaxSize() : ?int
    {
        return $this->rawParams['maxSize'] ?? null;
    }

    public function getTextFilter() : ?string
    {
        return $this->rawParams['textFilter'] ?? null;
    }

    public function getPrimaryFilter() : ?string
    {
        return $this->rawParams['primaryFilter'] ?? null;
    }

    public function getBoolFilterList() : array
    {
        return $this->rawParams['boolFilterList'] ?? [];
    }

    public function getWhere() : ?array
    {
        return $this->rawParams['where'] ?? null;
    }

    public static function fromRaw(array $params) : self
    {
        $object = new self();

        $rawParams = [];

        $select = $params['select'] ?? null;
        $orderBy = $params['orderBy'] ?? null;
        $order = $params['order'] ?? null;

        $offset = $params['offset'] ?? null;
        $maxSize = $params['maxSize'] ?? null;

        $boolFilterList = $params['boolFilterList'] ?? [];
        $primaryFilter = $params['primaryFilter'] ?? null;
        $textFilter = $params['textFilter'] ?? $params['q'] ?? null;

        $where = $params['where'] ?? null;

        if ($select && !is_array($select)) {
            throw new Error("select should be array.");
        }

        if ($orderBy && !is_string($orderBy)) {
            throw new Error("orderBy should be string.");
        }

        if ($order && !is_string($order)) {
            throw new Error("order should be string.");
        }

        if (!is_array($boolFilterList)) {
            throw new Error("boolFilterList should be array.");
        }

        if ($primaryFilter && !is_string($primaryFilter)) {
            throw new Error("primaryFilter should be string.");
        }

        if ($textFilter && !is_string($textFilter)) {
            throw new Error("textFilter should be string.");
        }

        if ($where && !is_array($where)) {
            throw new Error("where should be array.");
        }

        if ($offset && !is_int($offset)) {
            throw new Error("offset should be int.");
        }

        if ($maxSize && !is_int($maxSize)) {
            throw new Error("maxSize should be int.");
        }

        if ($order) {
            $order = strtolower($order);
        }

        $rawParams['select'] = $select;
        $rawParams['orderBy'] = $orderBy;
        $rawParams['order'] = $order;
        $rawParams['offset'] = $offset;
        $rawParams['maxSize'] = $maxSize;
        $rawParams['boolFilterList'] = $boolFilterList;
        $rawParams['primaryFilter'] = $primaryFilter;
        $rawParams['textFilter'] = $textFilter;
        $rawParams['where'] = $where;

        if ($where) {
            $this->adjustParams($rawParams);
        }

        $object->rawParams = $rawParams;

        return $object;
    }

    /**
     * For compatibility with the legacy definition.
     */
    protected function adjustParams(array &$params)
    {
        if (!$params['where']) {
            return;
        }

        foreach ($params['where'] as $item) {
            $type = $item['type'] ?? null;
            $value = $item['value'] ?? null;

            if ($type == 'bool' && !empty($value) && is_array($value)) {
                $params['boolFilterList'] = $value;
            }
            else if ($type == 'textFilter' && $value) {
                $params['textFilter'] = $value;

            }
            else if ($type == 'primary' && $value) {
                $params['primaryFilter'] = $value;
            }
        }
    }
}
