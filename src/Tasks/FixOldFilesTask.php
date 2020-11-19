<?php

namespace OP;

use SilverStripe\Dev\BuildTask;
use Exception;
use SilverStripe\Control\Director;
use SilverStripe\View\ArrayData;
use SilverStripe\Security\Security;
use SilverStripe\Core\Injector\Injector;
use Psr\Log\LoggerInterface;
use SilverStripe\Assets\File;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\ORM\Queries\SQLUpdate;
use SilverStripe\Security\Authenticator;

/**
 * Will update file name database entries to the new DB structure
 *
 * @copyright (c) 2014, Otago Polytechnic
 *
 *
 * @package RemoteAssetTask
 */
class FixOldFilesTask extends BuildTask
{

    protected $title = "Move file names from Filename to FileFilename";
    protected $description = "Populates blank FileFilename db fields. Allows these files to work with sync file task";

    /**
     * will ask the target server to return the file list and the data object list
     * @param type $request
     */
    public function run($request)
    {
        
        $dbquery =SQLUpdate::create()
            ->setTable('File')
            ->addWhere('`FileFilename` IS NULL')
            ->addAssignments( ['"File"."FileFilename"' => ['"File"."Filename"' => []]])
            ->execute();

         //   die( $dbquery->sql());
    }
}
