<?php
/**
 * allows for filters to be used for paginating files to be read
 * @author torleif west <torleifw@op.ac.nz>
 */
namespace OP;

use GraphQL\Type\Definition\Type;
use Psr\Log\LoggerInterface;
use SilverStripe\AssetAdmin\GraphQL\FileFilterInputTypeCreator;
use SilverStripe\Assets\Folder;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\Filterable;

class PaginatedFileFilterInputTypeCreator extends FileFilterInputTypeCreator
{

    /**
     * returns an array of attributes
     *
     * @return array
     */
    public function attributes()
    {
        return [
            'name' => 'PaginatedFileFilterInput',
            'description' => 'Input type for a File type',
        ];
    }

    /**
     * returns a data list
     *
     * @return DataArray
     */
    public function fields()
    {
        $fields = parent::fields();

        unset($fields['appCategory']);

        $fields = array_merge($fields, [
            'filenameExcludeStarts' => [
                'type' => Type::string(),
                'description' => 'Excludes files that start with this string'
            ],
            'filename' => [
                'type' => Type::string(),
                'description' => 'Searches for files with this string'
            ],
        ]);

        return $fields;
    }


    /**
     * Caution: Does NOT enforce canView permissions
     *
     * @param Filterable $list
     * @param array $filter
     * @return Filterable
     */
    public function filterList(Filterable $list, $filter)
    {

        $list =  parent::filterList($list, $filter);

        if (!empty($filter['filenameExcludeStarts'])) {
            $filters = explode(',', $filter['filenameExcludeStarts']);
            foreach ($filters as $filter) {
                $list = $list->exclude([
                    'FileFilename:StartsWith' => $filter
                ]);
                $list = $list->exclude([
                    'Filename:StartsWith' => $filter
                ]);
            }
        }
        if (!empty($filter['filename'])) {
            $list = $list->filterAny(array(
                'Filename:PartialMatch' => $filter['filename']
            ));
        }

        $list = $list->exclude([
            'ClassName' => Folder::class
        ]);
        
        return $list;
    }
}
