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

namespace tests\unit\Espo\Core\Select;

use Espo\Core\{
    Exceptions\Error,
};

use Espo\Core\Select\{
    SelectBuilder,
    SearchParams,
    Factory\ApplierFactory,
    Appliers\WhereApplier,
    Appliers\SelectApplier,
    Appliers\OrderApplier,
    Appliers\LimitApplier,
    Appliers\AccessControlFilterApplier,
    Appliers\PrimaryFilterApplier,
    Appliers\BoolFilterListApplier,
    Appliers\TextFilterApplier,
    Appliers\AdditionalApplier,
    Where\Params as WhereParams,
    Where\Item as WhereItem,
    Order\Params as OrderParams,
    Text\FilterParams as TextFilterParams,
};

use Espo\{
    ORM\QueryParams\Select as Query,
    ORM\QueryParams\SelectBuilder as QueryBuilder,
    Entities\User,
};

class SearchBuilderTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp() : void
    {
        $this->user = $this->createMock(User::class);
        $this->applierFactory = $this->createMock(ApplierFactory::class);

        $this->entityType = 'Test';

        $whereApplier = $this->createMock(WhereApplier::class);
        $selectApplier = $this->createMock(SelectApplier::class);
        $orderApplier = $this->createMock(OrderApplier::class);
        $limitApplier = $this->createMock(WhereApplier::class);
        $accessControlFilterApplier = $this->createMock(AccessControlFilterApplier::class);
        $textFilterApplier = $this->createMock(TextFilterApplier::class);
        $primaryFilterApplier = $this->createMock(PrimaryFilterApplier::class);
        $boolFilterListApplier = $this->createMock(boolFilterListApplier::class);
        $additionalApplier = $this->createMock(AdditionalApplier::class);

        $this->applierFactory
            ->expects($this->any())
            ->method('create')
            ->will(
                $this->returnValueMap([
                    [$this->entityType, $this->user, ApplierFactory::WHERE, $whereApplier],
                    [$this->entityType, $this->user, ApplierFactory::SELECT, $selectApplier],
                    [$this->entityType, $this->user, ApplierFactory::ORDER, $orderApplier],
                    [$this->entityType, $this->user, ApplierFactory::LIMIT, $limitApplier],
                    [$this->entityType, $this->user, ApplierFactory::ACCESS_CONTROL_FILTER, $accessControlFilterApplier],
                    [$this->entityType, $this->user, ApplierFactory::TEXT_FILTER, $textFilterApplier],
                    [$this->entityType, $this->user, ApplierFactory::PRIMARY_FILTER, $primaryFilterApplier],
                    [$this->entityType, $this->user, ApplierFactory::BOOL_FILTER_LIST, $boolFilterListApplier],
                    [$this->entityType, $this->user, ApplierFactory::ADDITIONAL, $additionalApplier],
                ])
            );

        $this->selectBuilder = new SelectBuilder($this->user, $this->applierFactory);
    }

    public function testBuild1()
    {
        $searchParams = SearchParams::fromRaw([
            'where' => [
                [
                    'type' => 'equals',
                    'attribute' => 'test',
                    'value' => 'value',
                ],
            ],
        ]);

        $query = $this->selectBuilder
            ->from($this->entityType)
            ->withSearchParams($searchParams)
            ->withStrictAccessControl()
            ->build();

        $this->assertEquals($this->entityType, $query->getFrom());
    }
}
