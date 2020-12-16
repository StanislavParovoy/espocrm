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

namespace Espo\Core\Select\Text;

use Espo\Core\{
    Exceptions\Error,
    Utils\Metadata,
    Utils\Config,
};

use Espo\ORM\{
    EntityManager,
};

class FullTextSearchDataComposer
{
    const MIN_LENGTH = 4;

    protected $entityType;

    protected $config;
    protected $metadata;
    protected $entityManager;

    public function __construct(
        string $entityType,
        Config $config,
        Metadata $metadata,
        EntityManager $entityManager
    ) {
        $this->entityType = $entityType;
        $this->config = $config;
        $this->metadata = $metadata;
        $this->entityManager = $entityManager;
    }

    public function compose(string $filter, FullTextSearchDataComposerParams $params) : ?FullTextSearchData
    {
        if ($this->config->get('fullTextSearchDisabled')) {
            return null;
        }

        $isAuxiliaryUse = $params->isAuxiliaryUse();

        $fieldList = $this->getTextFilterFieldList();

        if ($isAuxiliaryUse) {
            $filter = str_replace('%', '', $filter);
        }

        $fullTextSearchColumnList = $this->entityManager
            ->metadata
            ->get($this->entityType, ['fullTextSearchColumnList']);

        $useFullTextSearch = false;

        if (
            $this->metadata->get([
                'entityDefs', $this->entityType, 'collection', 'fullTextSearch'
            ])
            &&
            !empty($fullTextSearchColumnList)
        ) {
            $fullTextSearchMinLength = $this->config->get('fullTextSearchMinLength') ?? self::MIN_LENGTH;

            if (!$fullTextSearchMinLength) {
                $fullTextSearchMinLength = 0;
            }

            $filterWoWildcards = str_replace('*', '', $filter);

            if (mb_strlen($filterWoWildcards) >= $fullTextSearchMinLength) {
                $useFullTextSearch = true;
            }
        }

        $fullTextSearchFieldList = [];

        if (!$useFullTextSearch) {
            return false;
        }

        foreach ($fieldList as $field) {
            if (strpos($field, '.') !== false) {
                continue;
            }

            $defs = $this->metadata->get([
                'entityDefs', $this->entityType, 'fields', $field
            ]) ?? [];

            if (empty($defs['type'])) {
                continue;
            }

            $fieldType = $defs['type'];

            if (!empty($defs['notStorable'])) {
                continue;
            }

            if (
                !$this->metadata->get([
                    'fields', $fieldType, 'fullTextSearch'
                ])
            ) {
                continue;
            }

            $fullTextSearchFieldList[] = $field;
        }

        if (!count($fullTextSearchFieldList)) {
            $useFullTextSearch = false;
        }

        if (substr_count($filter, '\'') % 2 != 0) {
            $useFullTextSearch = false;
        }

        if (substr_count($filter, '"') % 2 != 0) {
            $useFullTextSearch = false;
        }

        if (empty($fullTextSearchColumnList)) {
            $useFullTextSearch = false;
        }

        if ($isAuxiliaryUse) {
            if (mb_strpos($filter, '@') !== false) {
                $useFullTextSearch = false;
            }
        }

        if (!$useFullTextSearch) {
            return null;
        }

        $filter = str_replace(['(', ')'], '', $filter);

        if (
            $isAuxiliaryUse && mb_strpos($filter, '*') === false
            ||
            mb_strpos($filter, ' ') === false
            &&
            mb_strpos($filter, '+') === false
            &&
            mb_strpos($filter, '-') === false
            &&
            mb_strpos($filter, '*') === false
        ) {
            $function = 'MATCH_NATURAL_LANGUAGE';
        }
        else {
            $function = 'MATCH_BOOLEAN';
        }

        $filter = str_replace('"*', '"', $filter);
        $filter = str_replace('*"', '"', $filter);

        $filter = str_replace('\'', '\'\'', $filter);

        while (strpos($filter, '**')) {
            $filter = str_replace('**', '*', $filter);

            $filter = trim($filter);
        }

        while (mb_substr($filter, -2)  === ' *') {
            $filter = mb_substr($filter, 0, mb_strlen($filter) - 2);

            $filter = trim($filter);
        }

        $expression = $function . ':(' . implode(', ', $fullTextSearchColumnList) . ', ' . "'{$filter}'" . ')';

        return FullTextSearchData::fromArray([
            'expression' => $expression,
            'fieldList' => $fullTextSearchFieldList,
            'columnList' => $fullTextSearchColumnList,
        ]);
    }

    protected function getTextFilterFieldList() : array
    {
        return
            $this->metadata->get([
                'entityDefs', $this->entityType, 'collection', 'textFilterFields'
            ]) ??
            ['name'];
    }
}
