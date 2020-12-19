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
    Core\Exceptions\Forbidden,
    Core\Acl,
    ORM\QueryComposer\BaseQueryComposer as QueryComposer,
    ORM\EntityManager,
    ORM\Entity,
};

/**
 * Checks Where parameters. Throws an exception if anything not allowed is met.
 */
class PermissionsChecker
{
    private $seed = null;

    protected $entityType;

    protected $entityManager;
    protected $acl;

    public function __construct(string $entityType, EntityManager $entityManager, Acl $acl)
    {
        $this->entityType = $entityType;

        $this->entityManager = $entityManager;
        $this->acl = $acl;
    }

    public function check(array $where, Params $params)
    {
        foreach ($where as $item) {
            $this->checkItem(
                Item::fromArray($item),
                $params
            );
        }
    }

    protected function checkItem(Item $item, Params $params)
    {
        $type = $item->getType();
        $attribute = $item->getAttribute();
        $value = $item->getValue();

        $forbidComplexExpressions = $params->forbidComplexExpressions();
        $checkWherePermission = $params->applyWherePermissionsCheck();

        if ($forbidComplexExpressions) {
            if ($type && in_array($type, ['subQueryIn', 'subQueryNotIn', 'not'])) {
                throw new Forbidden("Sub-queries are forbidden in search params.");
            }
        }

        if ($attribute && $forbidComplexExpressions) {
            if (strpos($attribute, '.') !== false || strpos($attribute, ':')) {
                throw new Forbidden("Complex expressions are forbidden in search params.");
            }
        }

        if ($attribute && $checkWherePermission) {
            $argumentList = QueryComposer::getAllAttributesFromComplexExpression($attribute);

            foreach ($argumentList as $argument) {
                $this->checkWhereArgument($argument, $type);
            }
        }

        if (!empty($value) && is_array($value)) {
            $this->check($value, $params);
        }
    }

    protected function checkWhereArgument(string $attribute, string $type)
    {
        $entityType = $this->entityType;

        if (strpos($attribute, '.')) {
            list($link, $attribute) = explode('.', $attribute);

            if (!$this->getSeed()->hasRelation($link)) {
                // TODO allow alias
                throw new Forbidden("Bad relation '{$link}' in where.");
            }

            $foreignEntityType = $this->getSeed()->getRelationParam($link, 'entity');

            if (!$foreignEntityType) {
                throw new Forbidden("Bad relation '{$link}' in where.");
            }

            if (
                !$this->acl->checkScope($foreignEntityType) ||
                in_array($link, $this->acl->getScopeForbiddenLinkList($entityType))
            ) {
                throw new Forbidden("Forbidden relation '{$link}' in where.");
            }

            if (in_array($attribute, $this->acl->getScopeForbiddenAttributeList($foreignEntityType))) {
                throw new Forbidden("Forbidden attribute '{$link}.{$attribute}' in where.");
            }

            return;
        }

        if (
            in_array($type, ['isLinked', 'isNotLinked', 'linkedWith', 'notLinkedWith', 'isUserFromTeams'])
        ) {
            $link = $attribute;

            if (!$this->getSeed()->hasRelation($link)) {
                throw new Forbidden("Bad relation '{$link}' in where.");
            }

            $foreignEntityType = $this->getSeed()->getRelationParam($link, 'entity');

            if (!$foreignEntityType) {
                throw new Forbidden("Bad relation '{$link}' in where.");
            }

            if (
                in_array($link, $this->acl->getScopeForbiddenFieldList($entityType)) ||
                !$this->acl->checkScope($foreignEntityType) ||
                in_array($link, $this->acl->getScopeForbiddenLinkList($entityType))
            ) {
                throw new Forbidden("Forbidden relation '{$link}' in where.");
            }

            return;
        }

        if (in_array($attribute, $this->acl->getScopeForbiddenAttributeList($entityType))) {
            throw new Forbidden("Forbidden attribute '{$attribute}' in where.");
        }
    }

    protected function getSeed() : Entity
    {
        return $this->seed ?? $this->entityManager->getEntity($this->entityType);
    }
}
