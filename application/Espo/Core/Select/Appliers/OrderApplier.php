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

namespace Espo\Core\Select\Appliers;

use Espo\Core\{
    Exceptions\Error,
    Exceptions\Forbidden,
    Select\SearchParams,
    Select\Order\Params as OrderParams,
};

use Espo\{
    ORM\EntityManager,
    ORM\QueryParams\SelectBuilder as QueryBuilder,
    Entities\User,
};

class OrderApplier
{
    protected $selectAttributesDependancyMap = [];

    private $seed = null;

    protected $entityType;

    protected $user;
    protected $entityManager;
    protected $metadata;

    public function __construct(
        string $entityType,
        User $user,
        EntityManager $entityManager,
        Metadata $metadata
    ) {
        $this->entityType = $entityType;
        $this->user = $user;
        $this->entityManager = $entityManager;
        $this->metadata = $metadata;
    }

    public function apply(QueryBuilder $queryBuilder, OrderParams $params)
    {
        if ($params->applyDefaultOrder()) {
            $this->applyDefaultOrder($queryBuilder, $params->getOrder());

            return;
        }

        $orderBy = $params->getOrderBy();

        if ($params->forbidComplexExpressions() && $orderBy) {
            if (
                !is_string($orderBy)
                ||
                strpos($orderBy, '.') !== false
                ||
                strpos($orderBy, ':') !== false
            ) {
                throw new Forbidden("Complex expressions are forbidden in orderBy.");
            }
        }

        $orderBy = $orderBy ??
            $this->metadata->get([
                'entityDefs', $this->entityType, 'collection', 'orderBy'
            ]);

        if (!$orderBy) {
            return;
        }

        $this->applyOrder($queryBuilder, $orderBy, $params->getOrder());
    }

    protected function applyDefaultOrder(QueryBuilder $queryBuilder, ?string $order)
    {
        $orderBy = $this->metadata->get([
            'entityDefs', $this->entityType, 'collection', 'orderBy'
        ]);

        if (!$orderBy) {
            $queryBuilder->order('id', $order);

            return;
        }

        if (!$order && !is_array($orderBy)) {
            $order = $this->metadata->get([
                'entityDefs', $this->entityType, 'collection', 'order'
            ]) ?? null;

            if ($order === true || strtolower($order) === 'desc') {
                $order = SearchParams::ORDER_DESC;
            }
            else if ($order === false || strtolower($order) === 'asc') {
                $order = SearchParams::ORDER_ASC;
            }
            else if (!$order === null) {
                throw new Error("Bad default order.");
            }
        }

        $this->applyOrder($queryBuilder, $orderBy, $order);
    }

    protected function applyOrder(QueryBuilder $queryBuilder, string $orderBy, ?string $order)
    {
        if (!$orderBy) {
            throw new Error("Could not apply order.");
        }

        $order = $order ?? SearchParams::ORDER_ASC;

        $resultOrderBy = $orderBy;

        $type = $this->metadata->get([
            'entityDefs', $this->entityType, 'fields', $orderBy, 'type'
        ]);

        // @todo Order handler by type and entity type ?

        if (in_array($type, ['link', 'file', 'image', 'linkOne'])) {
            $resultOrderBy .= 'Name';
        }
        else if ($type === 'linkParent') {
            $resultOrderBy .= 'Type';
        }
        else if ($type === 'address') {
            $resultOrderBy = $this->getAddressOrderBy($orderBy, $order);
        }
        else if ($type === 'enum') {
            $resultOrderBy =
                $this->getEnumOrderBy($orderBy, $order) ??
                $resultOrderBy;
        }
        else {
            if (strpos($orderBy, '.') === false && strpos($orderBy, ':') === false) {
                if (!$this->getSeed()->hasAttribute($orderBy)) {
                    throw new Error("Order by non-existing field '{$orderBy}'.");
                }
            }
        }

        $orderByAttribute = null;

        if (!is_array($resultOrderBy)) {
            $orderByAttribute = $resultOrderBy;

            $resultOrderBy = [
                [$resultOrderBy, $order]
            ];
        }

        if (
            $orderBy != 'id' &&
            (
                !$orderByAttribute ||
                !$this->getSeed()->getAttributeParam($orderByAttribute, 'unique')
            ) &&
            $this->getSeed()->hasAttribute('id')
        ) {
            $resultOrderBy[] = ['id', $order];
        }

        $queryBuilder->order($resultOrderBy);
    }

    protected function getAddressOrderBy(string $orderBy, string $order) : array
    {
        return [
            [$orderBy . 'Country', $order],
            [$orderBy . 'City', $order],
            [$orderBy . 'Street', $order],
        ];
    }

    protected function getEnumOrderBy(string $orderBy, string $order) : ?array
    {
        $list = $this->metadata->get([
            'entityDefs', $this->entityType, 'fields', $orderBy, 'options'
        ]);

        if (!$list || !is_array($list) || !count($list)) {
            return null;
        }

        $isSorted = $this->metadata->get([
            'entityDefs', $this->entityType, 'fields', $orderBy, 'isSorted'
        ]);

        if ($isSorted) {
            asort($list);
        }

        if ($order === SearchParams::ORDER_DESC) {
            $list = array_reverse($list);
        }

        foreach ($list as $i => $listItem) {
            $list[$i] = str_replace(',', '_COMMA_', $listItem);
        }

        return [
            [
                'LIST:' . $orderBy . ':' . implode(',', $list)
            ]
        ];
    }

    protected function getSeed() : Entity
    {
        return $this->seed ?? $this->entityManager->getEntity($this->entityType);
    }
}
