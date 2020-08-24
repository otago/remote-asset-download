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
    public function attributes()
    {
        return [
            'name' => 'readPaginatedFiles'
        ];
    }

    public function createConnection()
    {
        return Connection::create('readPaginatedFiles')
            ->setConnectionType($this->manager->getType('File'))
            ->setSortableFields(['ID', 'Title', 'Created', 'LastEdited'])
            ->setConnectionResolver(
                function ($object, array $args, $context, ResolveInfo $info) {
                    return File::get();
                }
            );
    }
}
