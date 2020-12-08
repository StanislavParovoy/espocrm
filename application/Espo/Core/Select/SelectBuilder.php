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

use Espo\{
    ORM\QueryParams\Select as Query,
    ORM\QueryParams\SelectBuilder as QueryBuilder,
};

class SelectBuilder
{
    protected $entityType;

    protected $queryBuilder;

    protected $searchParams = null;

    protected $applyAccessControl = false;

    protected $applyDefaultOrder = false;

    protected $applyWherePermissionsCheck = false;

    protected $applyNoComplexExpressions = false;

    protected $inCategoryFilterList = [];

    protected $textFilter = null;

    protected $primaryFilter = null;

    protected $boolFilterList = [];

    public function __construct(string $entityType)
    {
        $this->entityType = $entityType;

        $this->queryBuilder = new QueryBuilder();

        $this->queryBuilder->from($entityType);
    }

    public function fromSearchParams(Search $searchParams) : self
    {
        //$this->searchParams = $searchParams;

        $this->withBoolFilterList(
            $searchParams->getBoolFilterList()
        );

        $primaryFilter = $searchParams->getPrimaryFilter();

        if ($primaryFilter) {
            $this->withPrimaryFilter($primaryFilter);
        }

        $textFilter = $searchParams->getTextFilter();

        if ($textFilter) {
            $this->withTextFilter($textFilter);
        }

        return $this;
    }

    public function fromQuery(Query $query) : self
    {
        if ($entity->entityType !== $query->getFrom()) {
            throw new Error("Not matching entity type.");
        }

        $this->queryBuilder->clone($query);

        return $this;
    }

    public function build() : Query
    {
        if ($this->searchParams) {
            $this->createSearchParamsHandler()->apply($this->queryBuilder, $this->searchParams);
        }

        return $this->queryBuilder->build();
    }

    public function withAccessControl() : self
    {
        $this->applyAccessControl = true;

        return $this;
    }

    public function withDefaultOrder() : self
    {
        $this->applyDefaultOrder = true;

        return $this;
    }

    public function withWherePermissionsCheck() : self
    {
        $this->applyWherePermissionsCheck = true;

        return $this;
    }

    public function withNoComplexExpressions() : self
    {
        $this->applyNoComplexExpressions = true;

        return $this;
    }

    public function withInCategoryFilter(string $field, string $categoryId) : self
    {
        $this->inCategoryFilterList[] = [$field, $categoryId];

        return $this;
    }

    public function withTextFilter(string $textFilter) : self
    {
        $this->textFilter = $textFilter;

        return $this;
    }

    public function withPrimaryFilter(string $primaryFilter) : self
    {
        $this->primaryFilter = $primaryFilter;

        return $this;
    }

    public function withBoolFilter(string $boolFilter) : self
    {
        $this->boolFilterList[] = $boolFilter;

        return $this;
    }

    public function withBoolFilterList(array $boolFilterList) : self
    {
        $this->boolFilterList[] = array_merge($this->boolFilterList, $boolFilterList);

        return $this;
    }

    /*public function withRawWhere(array $rawWhere) : self
    {
        $this->rawWhere = $rawWhere;

        return $this;
    }*/

}
