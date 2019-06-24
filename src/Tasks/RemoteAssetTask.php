<?php

namespace OP;

use SilverStripe\Dev\BuildTask;
use Exception;
use SilverStripe\Control\Director;
use SilverStripe\View\ArrayData;
use SilverStripe\Security\Security;
use SilverStripe\Core\Injector\Injector;
use Psr\Log\LoggerInterface;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Security\Authenticator;

/**
 * Downloads files from a remote server.
 *
 *  - The task will download *new* files
 *
 * @copyright (c) 2014, Otago Polytechnic
 *
 *
 * @package RemoteAssetTask
 */
class RemoteAssetTask extends BuildTask
{

    protected $title = "Download Remote SilverStripe files";
    protected $description = "Retrieves files from a remote 
		SilverStripe instance.";

    /**
     * will ask the target server to return the file list and the data object list
     * @param type $request
     */
    public function run($request)
    {

        // only authenticated peronal can run this task.
        if (!Security::getCurrentUser()) {

            // if running in cli, you'll use the user as specified in the yml file
            if (!Director::is_cli()) {
                return Security::permissionFailure();
            }
        }

        // if not configured correctly, fail
        if (!RemoteAssetReadFilesController::config()->target) {
            throw new Exception('Target not found in yml file. See readme.md for installation instructions.');
        }

        // build communication urls
        $RemoteAssetReadFilesControllerURL = Director::absoluteURL('/remoteassetreadfiles');
        $RemoteAssetDownloadFilesControllerURL = Director::absoluteURL('/remoteassetdownloadfiles');


        // download without javascript
        if (Director::is_cli()) {
            ini_set('memory_limit', '1024M');
            set_time_limit(0);

            // if running in cli mode, set the current user to the user specified in yml
            if (!$this->AuthenticateUser()) {
                throw new HTTPResponse_Exception('Invalid username/password');
            }

            // start downloading file lists and files
            $this->RemoteAssetSync();

            return;
        }

        // render the page
        echo ArrayData::create(
            [
                'RemoteAssetReadFilesControllerURL' => $RemoteAssetReadFilesControllerURL,
                'RemoteAssetDownloadFilesControllerURL' =>  $RemoteAssetDownloadFilesControllerURL,
                'Target' => singleton(RemoteAssetReadFilesController::class)->config()->target,
                'ToMachine' => Director::absoluteURL('/')
            ]
        )->renderWith('RemoteAssetTask');
    }

    /**
     * Set the current user to the user specified in yml
     *
     * @return boolean true on success, false on failure
     */
    public function AuthenticateUser()
    {
        $authenticators = Security::singleton()->getApplicableAuthenticators(Authenticator::LOGIN);
        $assetcontroller = singleton(RemoteAssetReadFilesController::class);

        foreach ($authenticators as $authenticator) {
            $member = $authenticator->authenticate(
                [
                    'Email' => $assetcontroller->config()->user,
                    'Password' => $assetcontroller->config()->password,
                ],
                Controller::curr()->getRequest()
            );
            if ($member) {
                Security::setCurrentUser($member);
                return true;
            }
        }
        return false;
    }

    /**
     * Downloads the files locally, with no JavaScript and ajax
     */
    public function RemoteAssetSync()
    {
        $this->log("Starting RemoteAssetTask");

        $daysago = 0;
        $offset = 0;
        $bailout = false;

        do {
            $this->log("Pulling list of files from $daysago days ago on page $offset");

            // request 10 latest files
            $result = singleton(RemoteAssetReadFilesController::class)->DownloadFile(
                Controller::join_links(singleton(RemoteAssetReadFilesController::class)->config()->target, 'admin/graphql'),
                json_encode(singleton(RemoteAssetReadFilesController::class)->buildgraphql($daysago, $offset))
            );

            $jsonpayload = json_decode($result, true);

            if (!$jsonpayload) {
                $bailout = true;
            }

            $remotefilesarray = array();

            try {
                foreach ($jsonpayload['data']['readFiles']['edges'] as $filenode) {
                    $remotefilesarray[] = $filenode['node'];
                }

                $RemoteAssetDownloadFilesController = singleton(RemoteAssetDownloadFilesController::class);
                $RemoteAssetDownloadFilesController->HandleFileRequestArray($remotefilesarray);
                $this->log("Requesting download of " . count($remotefilesarray) . ' files');

                foreach ($remotefilesarray as $fileresult) {
                    if (isset($fileresult['finishquery']) && $fileresult['finishquery']) {
                        $this->log("Time to finish querying. finished!");
                        //    $bailout = true;
                    }
                    if ($fileresult['code'] === 200) {
                        $this->log('[' . $fileresult['code'] . '] ' . $fileresult['success']);
                    } else {
                        $this->log('[' . $fileresult['code'] . '] ' . $fileresult['error']);
                    }
                }
            } catch (Exception $e) {
                $this->log('failure ' . $e->getMessage());
                $bailout = true;
            }

            if ($jsonpayload['data']['readFiles']['pageInfo']['hasNextPage'] === false) {
                $daysago++;
                $offset = 0;
            }
            if ($jsonpayload['data']['readFiles']['pageInfo']['hasNextPage'] === true) {
                $offset += 10;
            }
        } while (!$bailout);
    }

    /**
     * log any information to console while running in cli mode. it wil also
     *  log information to a logger interface.
     * 
     * @param string $str string to log
     */
    public function log($str)
    {
        if (Director::is_cli()) {
            echo $str . PHP_EOL;
        }
        Injector::inst()->get(LoggerInterface::class)->info($str);
    }
}
