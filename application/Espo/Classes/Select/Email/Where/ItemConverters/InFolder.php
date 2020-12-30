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

namespace Espo\Classes\Select\Email\Where\ItemConverters;

use Espo\Core{
    Select\Where\ItemConverter,
};

use Espo\{
    ORM\QueryParams\SelectBuilder as QueryBuilder,
    ORM\QueryParams\Parts\WhereItem as WhereClauseItem,
    ORM\EntityManager,
    Entities\User,
};

class InFolder implements ItemConverter
{
    protected $user;
    protected $entityManager;

    public function __construct(User $user, EntityManager $entityManager)
    {
        $this->user = $user;
        $this->entityManager = $entityManager;
    }

    public function convert(QueryBuilder $queryBuilder, Item $item) : WhereClauseItem
    {
        $folderId = $item->getValue();

        switch ($folderId) {
            case 'all':
                return WhereClauseItem::fromRaw([]);

            case 'inbox':
                return $this->convertInbox($queryBuilder);

            case 'important':
                return $this->convertImportant($queryBuilder);

            case 'sent':
                return $this->convertSent($queryBuilder);

            case 'trash':
                return $this->convertTrash($queryBuilder);

            case 'drafts':
                return $this->convertDrafts($queryBuilder);

            default:
                return $this->convertFolderId($queryBuilder, $folderId);
    }

    protected function convertInbox(QueryBuilder $queryBuilder) : WhereClauseItem
    {
        $emailAddressList = $this->entityManager
            ->getRepository('User')
            ->getRelation($this->user, 'emailAddresses')
            ->select(['id'])
            ->find();

        $emailAddressIdList = [];

        foreach ($emailAddressList as $emailAddress) {
            $emailAddressIdList[] = $emailAddress->id;
        }

        if (!$queryBuilder->hasLeftJoinAlias('emailUser')) {
             $this->joinEmailUser($queryBuilder);
        }

        $whereClause = [
            'emailUser.inTrash=' => false,
            'emailUser.folderId' => null,
            'emailUser.userId' => $this->user->id,
            [
                'status' => ['Archived', 'Sent'],
            ],
        ];

        if (!empty($emailAddressIdList)) {
            $whereClause['fromEmailAddressId!='] = $emailAddressIdList;

            $whereClause[] = [
                'OR' => [
                    'status' => 'Archived',
                    'createdById!=' => $this->user->id,
                ]
            ];
        }
        else {
            $whereClause[] = [
                'status' => 'Archived',
                'createdById!=' => $this->user->id,
            ];
        }

        return WhereClauseItem::fromRaw($whereClause);
    }

    protected function joinEmailUser(QueryBuilder $queryBuilder)
    {
        $queryBuilder->leftJoin(
            'EmailUser',
            'emailUser',
            [
                'emailUser.emailId:' => 'id',
                'emailUser.deleted' => false,
            ]
        );
    }
}
