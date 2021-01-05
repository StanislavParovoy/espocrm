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
    Application,
    Container,
    Select\SelectBuilderFactory,
    Select\SearchParams,
};

class SelectBuilderTest extends \tests\integration\Core\BaseTestCase
{
    protected function setUp() : void
    {
        parent::setUp();

        $injectableFactory = $this->getContainer()->get('injectableFactory');

        $this->factory = $injectableFactory->create(SelectBuilderFactory::class);
    }

    protected function initTest(array $aclData, bool $skipLogin = false) : Application
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

        return $app;
    }

    public function testBuild1()
    {
        $app = $this->initTest(
            [
                'Account' => [
                    'read' => 'team',
                ],
            ],
        );

        $container = $app->getContainer();

        $userId = $container->get('user')->id;

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

        $expected = [
            'from' => 'Account',
            'orderBy' => [
                [
                    'name',
                    'DESC',
                ],
                [
                    'id',
                    'DESC',
                ],
            ],
            'joins' => [],
            'leftJoins' => [
                [
                    'teams',
                    'teamsAccess',
                ],
            ],
            'distinct' => true,
            'whereClause' => [
                'OR' => [
                    [
                        'assignedUserId' => $userId,
                    ],
                ],
                [
                    'name=' => 'test',
                ],
                [
                    'createdAt<' => '2020-12-12 10:00:00',
                ],
                [
                    'type' => 'Customer',
                ],
                [
                    'OR' => [
                        'teamsAccess.id' => [],
                        'assignedUserId' => $userId,
                    ],
                ],
            ],
        ];

        $this->assertEquals($expected['from'], $raw['from']);
        $this->assertEquals($expected['whereClause'], $raw['whereClause']);
        $this->assertEquals($expected['orderBy'], $raw['orderBy']);
        $this->assertEquals($expected['leftJoins'], $raw['leftJoins']);

        $this->assertTrue($raw['distinct']);
    }

    public function testBuildLegacyAccessFilter()
    {
        $app = $this->initTest(
            [
                'Meeting' => [
                    'read' => 'team',
                ],
            ],
        );

        $container = $app->getContainer();

        $userId = $container->get('user')->id;

        $builder = $this->factory->create();

        $query = $builder
            ->from('Meeting')
            ->withStrictAccessControl()
            ->build();

        $raw = $query->getRawParams();

        $expected = [
            'from' => 'Meeting',
            'joins' => [],
            'leftJoins' => [
                [
                    'teams',
                    'teamsAccess',
                ],
                [
                    'users',
                    'usersAccess',
                ],
            ],
            'distinct' => true,
            'whereClause' => [
                [
                    'OR' => [
                        'teamsAccessMiddle.teamId' => [],
                        'usersAccessMiddle.userId' => $userId,
                        'assignedUserId' => $userId,
                    ],
                ]
            ],
        ];

        $this->assertEquals($expected['whereClause'], $raw['whereClause']);
        $this->assertEquals($expected['leftJoins'], $raw['leftJoins']);

        $this->assertTrue($raw['distinct']);
    }

    public function testBuildDefaultOrder()
    {
        $app = $this->initTest(
            [],
        );

        $searchParams = SearchParams::fromRaw([]);

        $builder = $this->factory->create();

        $query = $builder
            ->from('Meeting')
            ->withSearchParams($searchParams)
            ->build();

        $raw = $query->getRawParams();

        $expectedOrderBy = [
            ['dateStart', 'DESC'],
            ['id', 'DESC'],
        ];

        $this->assertEquals($expectedOrderBy, $raw['orderBy']);
    }

    public function testBuildMeetingDateTime()
    {
        $app = $this->initTest(
            [],
        );

        $searchParams = SearchParams::fromRaw([
            'where' => [
                [
                    'type' => 'on',
                    'attribute' => 'dateStart',
                    'value' => '2020-12-12',
                    'dateTime' => true,
                ],
            ],
        ]);

        $builder = $this->factory->create();

        $query = $builder
            ->from('Meeting')
            ->withSearchParams($searchParams)
            ->build();

        $raw = $query->getRawParams();

        $expectedWhereClause = [
            'OR' => [
                [
                    'dateStartDate=' => '2020-12-12',
                ],
                [
                    'AND' => [
                        [
                            'AND' => [
                                'dateStart>=' => '2020-12-12 00:00:00',
                                'dateStart<=' => '2020-12-12 23:59:59',
                            ],
                        ],
                        [
                            'dateStartDate=' => null,
                        ],
                    ],
                ],
            ],
        ];

        $this->assertEquals($expectedWhereClause, $raw['whereClause']);
    }

    public function testEmailInbox()
    {
        $app = $this->initTest(
            [],
        );

        $container = $app->getContainer();

        $userId = $container->get('user')->id;

        $emailAddressId = $this->createUserEmailAddress($container);

        $searchParams = SearchParams::fromRaw([
            'where' => [
                [
                    'type' => 'inFolder',
                    'attribute' => 'folderId',
                    'value' => 'inbox',
                ],
            ],
        ]);

        $builder = $this->factory->create();

        $query = $builder
            ->from('Email')
            ->withSearchParams($searchParams)
            ->build();

        $raw = $query->getRawParams();

        $expectedWhereClause = [
            'emailUser.inTrash' => false,
            'emailUser.folderId' => null,
            'emailUser.userId' => $userId,
            [
                'status' => ['Archived', 'Sent'],
            ],
            'fromEmailAddressId!=' => [$emailAddressId],
            [
                'OR' => [
                    'status' => 'Archived',
                    'createdById!=' => $userId,
                ],
            ],
        ];

        $expectedLeftJoins = [
            [
                'EmailUser',
                'emailUser',
                [
                    'emailUser.emailId:' => 'id',
                    'emailUser.deleted' => false,
                ],
            ],
        ];

        $this->assertEquals($expectedWhereClause, $raw['whereClause']);
        $this->assertEquals($expectedLeftJoins, $raw['leftJoins']);
    }

    public function testEmailSent()
    {
        $app = $this->initTest(
            [],
        );

        $container = $app->getContainer();

        $userId = $container->get('user')->id;

        $emailAddressId = $this->createUserEmailAddress($container);

        $searchParams = SearchParams::fromRaw([
            'where' => [
                [
                    'type' => 'inFolder',
                    'attribute' => 'folderId',
                    'value' => 'sent',
                ],
            ],
        ]);

        $builder = $this->factory->create();

        $query = $builder
            ->from('Email')
            ->withSearchParams($searchParams)
            ->build();

        $raw = $query->getRawParams();

        $expectedWhereClause = [
            'OR' => [
                'fromEmailAddressId' => [$emailAddressId],
                [
                    'status' => 'Sent',
                    'createdById' => $userId,
                ]
            ],
            [
                'status!=' => 'Draft',
            ],
            'emailUser.inTrash' => false,
        ];

        $expectedLeftJoins = [
            [
                'EmailUser',
                'emailUser',
                [
                    'emailUser.emailId:' => 'id',
                    'emailUser.deleted' => false,
                ],
            ],
        ];

        $this->assertEquals($expectedWhereClause, $raw['whereClause']);
        $this->assertEquals($expectedLeftJoins, $raw['leftJoins']);
    }

    protected function createUserEmailAddress(Container $container) : string
    {
        $userId = $container->get('user')->id;

        $em = $container->get('entityManager');

        $user = $em->getEntity('User', $userId);

        $emailAddress = $em->createEntity('EmailAddress', [
            'name' => 'test@test.com',
        ]);

        $em
            ->getRepository('User')
            ->getRelation($user, 'emailAddresses')
            ->relate($emailAddress);

        return $emailAddress->id;
    }
}
