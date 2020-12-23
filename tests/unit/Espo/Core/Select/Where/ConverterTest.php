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

namespace tests\unit\Espo\Core\Select\Where;

use Espo\Core\{
    Exceptions\Error,
    Select\Where\Converter,
    Select\Where\Item,
    Select\Where\Scanner,
    Select\Where\ItemConverterFactory,
    Select\Where\ItemGeneralConverter,
    Select\Where\DateTimeItemTransformer,
    Select\Helpers\RandomStringGenerator,
    Utils\Config,
};

use Espo\{
    ORM\EntityManager,
    ORM\Entity,
    ORM\Metadata as OrmMatadata,
    ORM\QueryParams\SelectBuilder as QueryBuilder,
    ORM\QueryParams\Parts\WhereClause,
    Entities\User,
};

class ConverterTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp() : void
    {
        $this->entityType = 'Test';

        $this->user = $this->createMock(User::class);

        $this->entityManager = $this->createMock(EntityManager::class);
        $this->config = $this->createMock(Config::class);

        $this->scanner = $this->createMock(Scanner::class);
        $this->randomStringGenerator = $this->createMock(RandomStringGenerator::class);
        $this->itemConverterFactory = $this->createMock(ItemConverterFactory::class);

        $this->entityManager = $this->createMock(EntityManager::class);
        $this->ormMatadata = $this->createMock(OrmMatadata::class);

        $this->entityManager
            ->expects($this->any())
            ->method('getMetadata')
            ->willReturn($this->ormMatadata);

        $this->queryBuilder = $this->createMock(QueryBuilder::class);

        $this->randomStringGenerator
            ->expects($this->any())
            ->method('generate')
            ->willReturn('Random');

        $this->dateTimeItemTransformer = new DateTimeItemTransformer(
            $this->entityType,
            $this->user
        );

        $this->itemConverter = new ItemGeneralConverter(
            $this->entityType,
            $this->user,
            $this->dateTimeItemTransformer,
            $this->scanner,
            $this->itemConverterFactory,
            $this->randomStringGenerator,
            $this->entityManager,
            $this->config
        );

        $this->converter = new Converter(
            $this->entityType,
            $this->user,
            $this->itemConverter,
            $this->scanner,
            $this->randomStringGenerator,
            $this->entityManager
        );
    }

    public function testConvertApplyLeftJoins()
    {
        $item = Item::fromArray([
            'type' => 'and',
            'value' => [
            ],
        ]);

        $this->scanner
            ->expects($this->once())
            ->method('applyLeftJoins')
            ->with($this->queryBuilder, $item);

        $whereClause = $this->converter->convert($this->queryBuilder, $item);
    }

    public function testConvertEquals1()
    {
        $item = Item::fromArray([
            'type' => 'and',
            'value' => [
                [
                    'type' => 'equals',
                    'attribute' => 'test',
                    'value' => 'test-value',
                ],
            ],
        ]);

        $whereClause = $this->converter->convert($this->queryBuilder, $item);

        $expected = [
            'test=' => 'test-value',
        ];

        $this->assertEquals($expected, $whereClause->getRaw());
    }

    public function testConvertEquals2()
    {
        $item = Item::fromArray([
            'type' => 'and',
            'value' => [
                [
                    'type' => 'equals',
                    'attribute' => 'test1',
                    'value' => 'value1',
                ],
                [
                    'type' => 'equals',
                    'attribute' => 'test2',
                    'value' => 'value2',
                ],
            ],
        ]);

        $whereClause = $this->converter->convert($this->queryBuilder, $item);

        $expected = [
            ['test1=' => 'value1'],
            ['test2=' => 'value2'],
        ];

        $this->assertEquals($expected, $whereClause->getRaw());
    }

    public function testConvertOr()
    {
        $item = Item::fromArray([
            'type' => 'and',
            'value' => [
                [
                    'type' => 'or',
                    'value' => [
                        [
                            'type' => 'equals',
                            'attribute' => 'test1',
                            'value' => 'value1',
                        ],
                        [
                            'type' => 'notEquals',
                            'attribute' => 'test2',
                            'value' => 'value2',
                        ],
                    ],
                ],

            ],
        ]);

        $whereClause = $this->converter->convert($this->queryBuilder, $item);

        $expected = [
            'OR' => [
                ['test1=' => 'value1'],
                ['test2!=' => 'value2'],
            ],
        ];

        $this->assertEquals($expected, $whereClause->getRaw());
    }

    public function testConvertInCategoryManyMany()
    {
        $this->ormMatadata
            ->expects($this->once())
            ->method('get')
            ->with($this->entityType, ['relations', 'test'])
            ->willReturn(
                [
                    'type' => Entity::MANY_MANY,
                    'entity' => 'Foreign',
                    'midKeys' => ['localId', 'foreignId'],
                ]
            );

        $item = Item::fromArray([
            'type' => 'and',
            'value' => [
                [
                    'type' => 'inCategory',
                    'attribute' => 'test',
                    'value' => 'value',
                ],
            ],
        ]);

        $this->queryBuilder
            ->expects($this->once())
            ->method('distinct');

        $this->queryBuilder
            ->method('join')
            ->withConsecutive(
                [
                    'test',
                    'testInCategoryFilter',
                ],
                [
                    'ForeignPath',
                    'foreignPath',
                    [
                         "foreignPath.descendorId:" => "testInCategoryFilterMiddle.foreignId",
                    ]
                ],
            );


        $whereClause = $this->converter->convert($this->queryBuilder, $item);

        $expected = [
            'foreignPath.ascendorId' => 'value',
        ];

        $this->assertEquals($expected, $whereClause->getRaw());
    }

    public function testConvertInCategoryBelongsTo()
    {
        $this->ormMatadata
            ->expects($this->once())
            ->method('get')
            ->with($this->entityType, ['relations', 'test'])
            ->willReturn(
                [
                    'type' => Entity::BELONGS_TO,
                    'entity' => 'Foreign',
                    'key' => 'foreignId',
                ]
            );

        $item = Item::fromArray([
            'type' => 'and',
            'value' => [
                [
                    'type' => 'inCategory',
                    'attribute' => 'test',
                    'value' => 'value',
                ],
            ],
        ]);

        $this->queryBuilder
            ->expects($this->never())
            ->method('distinct');

        $this->queryBuilder
            ->method('join')
            ->withConsecutive(
                [
                    'ForeignPath',
                    'foreignPath',
                    [
                        "foreignPath.descendorId:" => "foreignId",
                    ]
                ],
            );


        $whereClause = $this->converter->convert($this->queryBuilder, $item);

        $expected = [
            'foreignPath.ascendorId' => 'value',
        ];

        $this->assertEquals($expected, $whereClause->getRaw());
    }

    public function testConvertIsUserFromTeams()
    {
        $this->ormMatadata
            ->expects($this->once())
            ->method('get')
            ->with($this->entityType, ['relations', 'user'])
            ->willReturn(
                [
                    'type' => Entity::BELONGS_TO,
                    'entity' => 'User',
                    'key' => 'userId',
                ]
            );

        $item = Item::fromArray([
            'type' => 'and',
            'value' => [
                [
                    'type' => 'isUserFromTeams',
                    'attribute' => 'user',
                    'value' => 'valueTeamId',
                ],
            ],
        ]);

        $this->queryBuilder
            ->expects($this->once())
            ->method('distinct');

        $aliasName = 'userIsUserFromTeamsFilterRandom';

        $this->queryBuilder
            ->method('join')
            ->withConsecutive(
                [
                    'TeamUser',
                    $aliasName . 'Middle',
                    [
                        $aliasName . 'Middle.userId:' => 'userId',
                        $aliasName . 'Middle.deleted' => false,
                    ]
                ],
            );


        $whereClause = $this->converter->convert($this->queryBuilder, $item);

        $expected = [
            $aliasName . 'Middle.teamId' => 'valueTeamId',
        ];

        $this->assertEquals($expected, $whereClause->getRaw());
    }
}
