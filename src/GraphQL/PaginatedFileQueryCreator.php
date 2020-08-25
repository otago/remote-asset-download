<?php

/**
 * Payment Paginated Query Creator
 *
 * @author torleif west <torlief.west@op.ac.nz>
 */

namespace OP;

use SilverStripe\Assets\File;
use SilverStripe\GraphQL\Pagination\PaginatedQueryCreator;
use SilverStripe\GraphQL\Pagination\Connection;
use GraphQL\Type\Definition\ResolveInfo;

class PaginatedFileQueryCreator extends PaginatedQueryCreator
{
    /**
     * returns an array of attributes
     *
     * @return array
     */
    public function attributes()
    {
        return [
            'name' => 'readPaginatedFiles'
        ];
    }

    /**
     * creates the connector
     *
     * @return Connection
     */
    public function createConnection()
    {
        return Connection::create('readPaginatedFiles')
        ->setArgs(function () {
            return [
                'filter' => [
                    'type' => $this->manager->getType('PaginatedFileFilterInput')
                ]
            ];
        })
            ->setConnectionType($this->manager->getType('File'))
            ->setSortableFields(['ID', 'Title', 'Created', 'LastEdited'])
            ->setConnectionResolver(
                function ($object, array $args, $context, ResolveInfo $info) {
                    $list = File::get();
                    $filter = (!empty($args['filter'])) ? $args['filter'] : [];

                    $filterInputType = new PaginatedFileFilterInputTypeCreator($this->manager);
                    $list = $filterInputType->filterList($list, $filter);

                    return $list;
                }
            );
    }
}
