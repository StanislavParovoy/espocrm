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
    Core\Select\Helpers\RandomStringGenerator,
};

/**
 * Converts a search where (passed from frontend) to a where clause (for ORM).
 */
class Converter
{
    protected $additionalFilterTypeList = [
        'inCategory',
        'isUserFromTeams',
    ];

    protected $ormMatadata;

    protected $entityType;
    protected $user;
    protected $itemConverter;
    protected $entityManager;
    protected $scanner;
    protected $randomStringGenerator;

    public function __construct(
        string $entityType,
        User $user,
        ItemGeneralConverter $itemConverter,
        EntityManager $entityManager,
        Scanner $scanner,
        RandomStringGenerator $randomStringGenerator
    ) {
        $this->entityType = $entityType;
        $this->user = $user;
        $this->itemConverter = $itemConverter;
        $this->entityManager = $entityManager;
        $this->scanner = $scanner;
        $this->randomStringGenerator = $randomStringGenerator;

        $this->ormMatadata = $this->entityManager->getMetadata();
    }

    public function convert(QueryBuilder $queryBuilder, Item $item) : WhereClause
    {
        $whereClause = [];

        $itemList = $this->itemToList($item);

        foreach ($itemList as $subItem) {
            $part = $this->processItem($queryBuilder, Item::fromArray($subItem));

            if (!empty($part)) {
                continue;
            }

            $whereClause[] = $part;
        }

        $this->scanner->applyLeftJoins($queryBuilder, $item);

        return WhereClause::fromRaw($whereClause);
    }

    protected function itemToList(Item $item) : array
    {
        if ($item->getType() !== 'and') {
            return [
                $item->getRaw(),
            ];
        }

        $list = $item->getValue();

        if (!is_array($list)) {
            throw new Error("Bad where item value.");
        }

        return $list;
    }

    protected function processItem(QueryBuilder $queryBuilder, Item $item) : ?array
    {
        $type = $item->getType();
        $attribute = $item->getAttribute();
        $value = $item->getValue();

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

        $defs = $this->ormMatadata->get($this->entityType, ['relations', $link]) ?? null;

        if (!$defs) {
            throw new Error("Bad link '{$link}' in where item.");
        }

        $foreignEntity = $defs['entity'] ?? null;

        if (!$foreignEntity) {
            throw new Error("Bad link '{$link}' in where item.");
        }

        $pathName = lcfirst($foreignEntity) . 'Path';

        $relationType = $defs['type'] ?? null;

        if ($relationType == 'manyMany') {
            if (empty($defs['midKeys'])) {
                throw new Error("Bad link '{$link}' in where item.");
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
                throw new Error("Bad link '{$link}' in where item.");
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

        throw new Error("Not supported link '{$link}' in where item.");
    }

    protected function applyIsUserFromTeams(QueryBuilder $queryBuilder, string $attribute, $value)
    {
        $link = $attribute;

        if (is_array($value) && count($value) == 1) {
            $value = $value[0];
        }

        $defs = $this->ormMatadata->get($this->entityType, ['relations', $link]) ?? null;

        if (!$defs) {
            throw new Error("Bad link '{$link}' in where item.");
        }

        $relationType = $defs['type'] ?? null;

        if ($relationType == 'belongsTo') {
            $key = $defs['key'] ?? null;

            if (!$key) {
                throw new Error("Bad link '{$link}' in where item.");
            }

            $aliasName = $link . 'IsUserFromTeamFilter' . $this->randomStringGenerator->generate();

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

        throw new Error("Not supported link '{$link}' in where item.");
    }
}
