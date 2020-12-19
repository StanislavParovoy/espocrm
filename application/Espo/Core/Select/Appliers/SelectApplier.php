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
    Select\SearchParams,
    Utils\FieldUtil,
};

use Espo\{
    ORM\EntityManager,
    ORM\QueryParams\SelectBuilder as QueryBuilder,
    Entities\User,
};

class SelectApplier
{
    protected $selectAttributesDependancyMap = [];

    protected $aclAttributeList = [
        'assignedUserId',
        'createdById',
    ];

    protected $aclPortalAttributeList = [
        'assignedUserId',
        'createdById',
        'contactId',
        'accountId',
    ];

    private $seed = null;

    protected $entityType;

    protected $user;
    protected $entityManager;
    protected $fieldUtil;
    protected $metadata;

    public function __construct(
        string $entityType,
        User $user,
        EntityManager $entityManager,
        FieldUtil $fieldUtil,
        Metadata $metadata
    ) {
        $this->entityType = $entityType;
        $this->user = $user;
        $this->entityManager = $entityManager;
        $this->fieldUtil = $fieldUtil;
        $this->metadata = $metadata;
    }

    public function apply(QueryBuilder $queryBuilder, SearchParams $searchParams)
    {
        $attributeList = $this->getSelectAttributeList($searchParams);

        if ($attributeList) {
            $queryBuilder->select($attributeList);
        }
    }

    protected function getSelectAttributeList(SearchParams $searchParams) : ?array
    {
        $passedAttributeList = $searchParams->getSelect();

        if (!$passedAttributeList) {
            return null;
        }

        $entityDefs = $this->getEntityDefs();

        $attributeList = [];

        if (!in_array('id', $passedAttributeList)) {
            $attributeList[] = 'id';
        }

        foreach ($this->getAclAttributeList() as $attribute) {
            if (in_array($attribute, $passedAttributeList)) {
                continue;
            }

            if (!$entityDefs->hasAttribute($attribute)) {
                continue;
            }

            $attributeList[] = $attribute;
        }

        foreach ($passedAttributeList as $attribute) {
            if (in_array($attribute, $attributeList)) {
                continue;
            }

            if (!$entityDefs->hasAttribute($attribute)) {
                continue;
            }

            $attributeList[] = $attribute;
        }

        $sortByField = $searchParams->getOrderBy() ??
            $this->metadata->get([
                'entityDefs', $this->entityType, 'collection', 'orderBy'
            ]);

        if ($sortByField) {
            $sortByAttributeList = $this->fieldUtil->getAttributeList($this->entityType, $sortByField);

            foreach ($sortByAttributeList as $attribute) {
                if (in_array($attribute, $attributeList)) {
                    continue;
                }

                if (!$entityDefs->hasAttribute($attribute)) {
                    continue;
                }

                $attributeList[] = $attribute;
            }
        }

        $selectAttributesDependancyMap = $this->metadata->get([
            'selectDefs', $this->entityType, 'selectAttributesDependancyMap'
        ]);

        foreach ($selectAttributesDependancyMap as $attribute => $dependantAttributeList) {
            if (!in_array($attribute, $attributeList)) {
                continue;
            }

            foreach ($dependantAttributeList as $dependantAttribute) {
                if (in_array($dependantAttribute, $attributeList)) {
                    continue;
                }

                $attributeList[] = $dependantAttribute;
            }
        }

        return $attributeList;
    }

    protected function getEntityDefs() : Entity
    {
        return $this->seed ?? $this->entityManager->getEntity($this->entityType);
    }

    protected function getAclAttributeList() : array
    {
        if ($this->user->isPortal()) {
            return $this->metadata->get([
                'selectDefs', $this->entityType, 'aclPortalAttributeList',
            ]) ??
            $this->aclPortalAttributeList;
        }

        return $this->metadata->get([
            'selectDefs', $this->entityType, 'aclAttributeList',
        ]) ??
        $this->aclAttributeList;
    }
}
