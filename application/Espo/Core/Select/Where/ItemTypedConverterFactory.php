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

use Espo\Core\{
    Exceptions\Error,
};

use Espo\{
    Entities\User,
};

class ItemTypedConverterFactory
{
    protected $injectableFactory;
    protected $metadata;

    public function __construct(InjectableFactory $injectableFactory, Metadata $metadata)
    {
        $this->injectableFactory = $injectableFactory;
        $this->metadata = $metadata;
    }

    public function has(string $type) : bool
    {
        return (bool) $this->getClassName($type);
    }

    public function create(string $type, string $entityType, User $user) : ItemTypedConverter
    {
        $className = $this->getClassName($type);

        if (!$className) {
            throw new Error("Where item type class name is not defined.");
        }

        return $this->injectableFactory->createWith($className, [
            'entityType' => $entityType,
            'user' => $user,
        ]);
    }

    protected function getClassName(string $type) : ?string
    {
        return $this->metadata->get([
            'app', 'select', 'whereItemTypes', $type, 'converterClassName'
        ]);
    }
}
