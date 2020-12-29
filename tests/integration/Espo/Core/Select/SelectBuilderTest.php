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

namespace tests\integration\Espo\Core\Select;

use Espo\Core\{
    Select\SelectBuilderFactory,
    Select\SearchParams,
    InjectableFactory,
    Binding\BindingLoader,
    Binding\BindingData,
    Binding\Binding,
};

class SelectBuilderTest extends \tests\integration\Core\BaseTestCase
{
    protected function setUp() : void
    {
        parent::setUp();

        $injectableFactory = $this->getContainer()->get('injectableFactory');

        $this->factory = $injectableFactory->create(SelectBuilderFactory::class);
    }

    protected function initTest(array $aclData, bool $skipLogin = false)
    {
        $this->createUser('tester', [
            'data' => $aclData
        ]);

        if (!$skipLogin) {
            $this->auth('tester');
        }

        $app = $this->createApplication();

        $injectableFactory = $app->getContainer()->get('injectableFactory');

        $this->factory = $injectableFactory->create(SelectBuilderFactory::class);
    }

    public function testBuild1()
    {
        $this->initTest(
            [
                'Account' => [
                    'read' => 'team',
                ],
            ],
        );

        $builder = $this->factory->create();

        $searchParams = SearchParams::fromRaw([
            'orderBy' => 'name',
            'order' => SearchParams::ORDER_DESC,
            'primaryFilter' => 'customers',
            'boolFilterList' => ['onlyMy'],
            'where' => [
                [
                    'type' => 'equals',
                    'attribute' => 'name',
                    'value' => 'test',
                ],
                [
                    'type' => 'before',
                    'attribute' => 'createdAt',
                    'value' => '2020-12-12 10:00',
                    'dateTime' => true,
                ],
            ],
        ]);

        $query = $builder
            ->from('Account')
            ->withStrictAccessControl()
            ->withSearchParams($searchParams)
            ->build();

        $raw = $query->getRawParams();

        print_r($raw);

    }
}
