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

use Espo\Core\Select\{
    Factory\ApplierFactory,
    Applier\WhereApplier,
    Applier\SelectApplier,
    Applier\OrderApplier,
    Applier\LimitApplier,
    Applier\AccessControlFilterApplier,
    Applier\PrimaryFilterApplier,
    Applier\BoolFilterListApplier,
    Applier\TextFilterApplier,
    Where\Params as WhereParams,
};

use Espo\{
    ORM\QueryParams\Select as Query,
    ORM\QueryParams\SelectBuilder as QueryBuilder,
    Entities\User,
};

/**
 * Builds select queries for ORM.
 * Applies search parameters (passed from frontend), ACL restrictions, filters, etc.
 */
class SelectBuilder
{
    protected $entityType;

    protected $queryBuilder;

    protected $user = null;

    protected $searchParams = null;

    protected $applyAccessControlFilter = false;

    protected $applyDefaultOrder = false;

    protected $textFilter = null;

    protected $primaryFilter = null;

    protected $boolFilterList = [];

    protected $applyWherePermissionsCheck = false;

    protected $applyComplexExpressionsForbidden = false;

    protected $applierFactory;

    public function __construct(string $entityType, User $user, ApplierFactory $applierFactory)
    {
        $this->entityType = $entityType;

        $this->applierFactory = $applierFactory;

        $this->user = $user;

        $this->queryBuilder = new OrmSelectBuilder();

        $this->queryBuilder->from($entityType);
    }

    public function fromSearchParams(Search $searchParams) : self
    {
        $this->searchParams = $searchParams;

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
        $this->applyFromSearchParams();

        if ($this->applyDefaultOrder) {
            $this->applyDefaultOrder();
        }

        if ($this->primaryFilter) {
            $this->applyPrimaryFilter();
        }

        if (count($this->boolFilterList)) {
            $this->applyBoolFilterList();
        }

        if ($this->textFilter) {
            $this->applyTextFilter();
        }

        if ($this->applyAccessControlFilter) {
            $this->applyAccessControlFilter();
        }

        return $this->queryBuilder->build();
    }

    public function forUser(User $user) : self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Applies maximum restrictions for a user.
     */
    public function withStrictAccessControl() : self
    {
        $this->withAccessControlFilter();
        $this->withWherePermissionsCheck();
        $this->withComplexExpressionsForbidden();

        return $this;
    }

    public function withAccessControlFilter() : self
    {
        $this->applyAccessControlFilter = true;

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

    public function withComplexExpressionsForbidden() : self
    {
        $this->applyComplexExpressionsForbidden = true;

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

    protected function applyPrimaryFilter()
    {
        $this->createPrimaryFilterApplier()
            ->apply(
                $this->queryBuilder,
                $this->primaryFilter
            );
    }

    protected function applyBoolFilterList()
    {
        $this->createBoolFilterListApplier()
            ->apply(
                $this->queryBuilder,
                $this->boolFilterList
            );
    }

    protected function applyTextFilter()
    {
        $this->createTextFilterApplier()
            ->apply(
                $this->queryBuilder,
                $this->textFilter
            );
    }

    protected function applyAccessControlFilter()
    {
        $this->createAccessControlFilterApplier()
            ->apply(
                $this->queryBuilder
            );
    }

    protected function applyDefaultOrder()
    {
        // if null, null then apply default
        $this->createOrderApplier()
            ->apply(
                $this->queryBuilder
            );
    }

    protected function applyFromSearchParams()
    {
        if (!$this->searchParams) {
            return;
        }

        if (
            !$this->applyDefaultOrder &&
            (
                $this->searchParams->getOrderBy() || $this->searchParams->getOrder()
            )
        ) {
            // @todo move to class
            $params = [
                'forbidComplexExpressions' => $this->applyComplexExpressionsForbidden,
            ];

            $this->createOrderApplier()
                ->apply(
                    $this->queryBuilder,
                    $this->searchParams->getOrderBy(),
                    $this->searchParams->getOrder(),
                    $params
                );
        }

        if ($this->searchParams->getMaxSize() || $this->searchParams->getOffset()) {
            $this->createLimitApplier()
                ->apply(
                    $this->queryBuilder,
                    $this->searchParams->getOffset(),
                    $this->searchParams->getMaxSize()
                );
        }

        if ($this->searchParams->getSelect()) {
            $this->createSelectApplier()
                ->apply(
                    $this->queryBuilder,
                    $this->searchParams
                );
        }

        if ($this->searchParams->getWhere()) {
            $params = WhereParams::fromArray([
                'applyWherePermissionsCheck' => $this->applyWherePermissionsCheck,
                'forbidComplexExpressions' => $this->applyComplexExpressionsForbidden,
            ]);

            $this->createWhereApplier()
                ->apply(
                    $this->queryBuilder,
                    $this->searchParams->getWhere(),
                    $params
                );
        }
    }

    protected function createWhereApplier() : WhereApplier
    {
        return $this->applierFactory->create($this->entityType, $this->user, ApplierFactory::WHERE);
    }

    protected function createSelectApplier() : SelectApplier
    {
        return $this->applierFactory->create($this->entityType, $this->user, ApplierFactory::SELECT);
    }

    protected function createOrderApplier() : OrderApplier
    {
        return $this->applierFactory->create($this->entityType, $this->user, ApplierFactory::ORDER);
    }

    protected function createLimitApplier() : LimitApplier
    {
        return $this->applierFactory->create($this->entityType, $this->user, ApplierFactory::LIMIT);
    }

    protected function createAccessControlFilterApplier() : AccessControlFilterApplier
    {
        return $this->applierFactory->create($this->entityType, $this->user, ApplierFactory::ACCESS_CONTROL_FILTER);
    }

    protected function createTextFilterApplier() : TextFilterApplier
    {
        return $this->applierFactory->create($this->entityType, $this->user, ApplierFactory::TEXT_FILTER);
    }

    protected function createPrimaryFilterApplier() : PrimaryFilterApplier
    {
        return $this->applierFactory->create($this->entityType, $this->user, ApplierFactory::PRIMARY_FILTER);
    }

    protected function createBoolFilterListApplier() : BoolFilterListApplier
    {
        return $this->applierFactory->create($this->entityType, $this->user, ApplierFactory::BOOL_FILTER_LIST);
    }
}
