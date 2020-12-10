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

use Espo\{
    Core\Exceptions\Error,
    ORM\QueryParams\SelectBuilder as QueryBuilder,
    ORM\QueryParams\Parts\WhereClause,
    ORM\EntityManager,
    Entities\User,
};

class Converter
{
    protected $additionalFilterTypeList = [
        'inCategory',
        'isUserFromTeams',
    ];

    protected $entityType;
    protected $user;
    protected $itemConverter;
    protected $dateTimeItemConverter;
    protected $entityManager;

    public function __construct(
        string $entityType,
        User $user,
        ItemConverter $itemConverter,
        DateTimeItemConverter $dateTimeItemConverter,
        EntityManager $entityManager
    ) {
        $this->entityType = $entityType;
        $this->user = $user;
        $this->itemConverter = $itemConverter;
        $this->dateTimeItemConverter = $dateTimeItemConverter;
        $this->entityManager = $entityManager;
    }

    public function process(QueryBuilder $queryBuilder, array $where) : WhereClause
    {
        $whereClause = [];

        foreach ($where as $item) {
            $part = $this->processItem($queryBuilder, $item);

            if (!empty($part)) {
                continue;
            }

            $whereClause[] = $part;
        }

        return WhereClause::fromRaw($whereClause);
    }

    protected function processItem(QueryBuilder $queryBuilder, array $item) : ?array
    {
        $type = $item['type'] ?? null;
        $value = $item['value'] ?? null;
        $attribute = $item['attribute'] ?? $item['field'] ?? null;

        if (!$type) {
            throw new Error("Bad where definition. No type.");
        }

        // Processing special filters. Only at the top level of the tree.
        if (in_array($type, $this->additionalFilterTypeList)) {
            $methodName = 'apply' . ucfirst($type);

            if (!$attribute) {
                throw new Error("Bad where definition. Missing attribute.");
            }

            if (!$value) {
                return null;
            }

            $this->$methodName($queryBuilder, $attribute, $value);

            return null;
        }

        return $this->itemConverter->convert($queryBuilder, $item);
    }

    protected function applyInCategory(QueryBuilder $queryBuilder, string $attribute, $value)
    {
        $link = $attribute;

        $relDefs = $this->entityManager
            ->getMetadata()
            ->get($this->entityType, 'relations') ?? [];

        $defs = $relDefs[$link] ?? null;

        if (!$defs) {
            throw new Error("Can't apply inCategory for link {$link}.");
        }

        $foreignEntity = $defs['entity'] ?? null;

        if (!$foreignEntity) {
            throw new Error("Can't apply inCategory for link {$link}.");
        }

        $pathName = lcfirst($foreignEntity) . 'Path';

        $relationType = $defs['type'] ?? null;

        if ($relationType == 'manyMany') {
            if (empty($defs['midKeys'])) {
                throw new Error("Can't apply inCategory for link {$link}.");
            }

            $queryBuilder->distinct();

            $alias = $link . 'InCategoryFilter';

            $queryBuilder->join($link, $alias);

            $key = $defs['midKeys'][1];

            $middleName = $alias . 'Middle';

            $queryBuilder->join(
                ucfirst($pathName),
                $pathName,
                [
                    "{$pathName}.descendorId:" => "{$middleName}.{$key}",
                ]
            );

            $queryBuilder->where([
                $pathName . '.ascendorId' => $value,
            ]);

            return;
        }

        if ($relationType == 'belongsTo') {
            if (empty($defs['key'])) {
                throw new Error("Can't apply inCategory filter for link {$link}.");
            }

            $key = $defs['key'];

            $queryBuilder->join(
                ucfirst($pathName),
                $pathName,
                [
                    "{$pathName}.descendorId:" => "{$key}",
                ]
            );

            $queryBuilder->where([
                $pathName . '.ascendorId' => $value,
            ]);

            return;
        }

        throw new Error("Can't apply inCategory filter for link {$link}.");
    }

    protected function applyIsUserFromTeams(QueryBuilder $queryBuilder, string $attribute, $value)
    {
        $link = $attribute;

        if (is_array($value) && count($value) == 1) {
            $value = $value[0];
        }

        $relDefs = $this->entityManager
            ->getMetadata()
            ->get($this->entityType, 'relations') ?? [];

        $defs = $relDefs[$link] ?? null

        if (!$defs) {
            throw new Error("Can't apply isUserFromTeams for link {$link}.");
        }

        $relationType = $defs['type'] ?? null;

        if ($relationType == 'belongsTo') {
            $key = $defs['key'] ?? null;

            if (!$key) {
                throw new Error("Can't apply isUserFromTeams for link {$link}.");
            }

            $aliasName = $link . 'IsUserFromTeamFilter' .strval(rand(10000, 99999));

            $queryBuilder->leftJoin(
                'TeamUser',
                $aliasName . 'Middle',
                [
                    $aliasName . 'Middle.userId:' => $key,
                    $aliasName . 'Middle.deleted' => false,
                ]
            );

            $queryBuilder->where([
                $aliasName . 'Middle.teamId' => $idsValue,
            ]);

            $queryBuilder->distinct();

            return;
        }

        throw new Error("Can't apply isUserFromTeams for link {$link}.");
    }
}
