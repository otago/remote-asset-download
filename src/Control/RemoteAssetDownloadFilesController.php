<?php

namespace OP;

use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Upload;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Assets\Folder;
use SilverStripe\Assets\Upload_Validator;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Security\Security;
use SilverStripe\Assets\Storage\AssetStore;
use SilverStripe\Core\Config\Configurable;

/**
 * Takes in a post array of files to download from a remote server
 * 
 * @see RemoteAssetDownloadTask.php
 */
class RemoteAssetDownloadFilesController extends Controller
{
    use Configurable;

    // where the file assets live (e.g. mydomain.com/assets/myfile.jpg)

    /**
     * @config
     */
    private static $assets = 'assets';

    /**
     * Downloads a single file
     * @param SS_HTTPRequest $request checks the access code to make sure no
     * public spoofing
     */
    public function index(HTTPRequest $request)
    {
        if (!$this->getCurrentUser()) {
            return Security::permissionFailure();
        }

        // This data is a squashed array of the graphql information
        $files = $request->postVars();

        // this will iterate through the file array, and build the response
        $this->HandleFileRequestArray($files);

        // respond with json information of the upload status
        $response = HTTPResponse::create();
        $response->addHeader('Content-Type', 'application/json');
        $response->setBody(json_encode($files));
        return $response;
    }


    /**
     * Build a response array, and handle the remote curl request for the bulk download of files
     * 
     * @param array $files an array of files in a [['id'=>int, 'filename'=> string, 'parentId', int]] format
     */
    public function HandleFileRequestArray(array &$files)
    {

        // set the ability to upload files via curl and not POST
        Upload_Validator::config()->set('use_is_uploaded_file', false);

        // remove all array items that don't have a file name specified
        foreach ($files as $key => $downloaditem) {
            if (!key_exists('filename', $downloaditem)) {
                unset($files[$key]);
            }
        }

        // build the list of real target urls to download from
        foreach ($files as &$downloaditem) {
            $readfilescontroller = singleton(RemoteAssetReadFilesController::class);
            $downloaditem['url'] = Controller::join_links(
                $readfilescontroller->config()->target,
                $this->config()->get('assets'),
                $downloaditem['filename']
            );
        }

        // concurrently attempt to download these files
        $results = $this->DownloadFiles($files);

        // loop through each one, and try to build the SilverStripe objects and save into the asset manager
        foreach ($files as &$file) {

            // halt if you're start to run into files you've allready got
            $existingfilerecord = File::find($downloaditem['filename']);
            if ($existingfilerecord && $existingfilerecord->exists()) {
                $file['success'] = 'File allready exists (From: ' . $file['url'] . ') id #' . $file['id'];
                $file['code'] = 200;
                $file['finishquery'] = true;
            } else {
                $this->handleFile($file);
            }
        }

        // scrub the elements that are can't be encoded
        foreach ($files as &$file) {
            unset($file['result']);
            unset($file['resource']);
        }
    }

    /**
     * Find the requested user to handle the file request. If being run by cli, you will use the
     * client credentials found in the yml file.
     *
     * @return Member
     */
    public function getCurrentUser()
    {
        return Security::getCurrentUser();
    }

    /**
     * Binds SilverStripe logic to handle saving the file into the asset manager
     *
     * @param array $file file reference
     *
     * @return void nothing.
     */
    public function handleFile(&$file)
    {
        $file['code'] = curl_getinfo($file['resource'], CURLINFO_HTTP_CODE);
        if ($file['code'] === 200) {
            $fileClass = File::get_class_for_file_extension(File::get_file_extension($file['result']));

            $newfile = Injector::inst()->create($fileClass);
            $parentRecord = Folder::get()->byID($file['parentId']);

            // create parent folder if failure
            if (!$parentRecord) {
                $parentRecord = Folder::find_or_make(dirname($file['filename']));
                $parentRecord->ID = $file['parentId'];
                $parentRecord->write();
            }

            // check canCreate permissions
            if (!$newfile->canCreate($this->getCurrentUser(), ['Parent' => $parentRecord])) {
                $file['error'] = 'Cannot write file' . $file['filename'] . ', check your user permissions';
                $file['code'] = '400';
                return;
            }

            $tmpFile = $this->temporaryFile($file['filename'], $file['result']);

            // POST file structure fake
            $postfile = [
                'tmp_name' => $tmpFile,
                'name' => basename($file['filename']),
                'error' => '',
                'size' => filesize($tmpFile)
            ];

            // move to the SS file handling function
            $uploader = $this->getUpload();
            $uploadResult = $uploader->loadIntoFile(
                $postfile,
                $newfile,
                $parentRecord ? $parentRecord->getFilename() : '/'
            );

            if (!$uploadResult) {
                $file['error'] = 'failed to save upload result.' .  implode(', ', $uploader->getErrors());
                $file['code'] = '400';
                return;
            }
            $newfile->ParentID = $parentRecord->ID;
            $newfile->ID = $file['id'];
            $newfile->write();

            // we force this version to be accessable, else how would we get the file in the first place?
            $newfile->publishSingle();

            $file['success'] = 'Updated file (From: ' . $file['url'] . ') id #' . $newfile->ID;
        } else {
            $file['error'] = 'The remote url (From: ' . $file['url'] . ') resulted in a ' . $file['code'];
        }
    }

    /**
     * builds a temporay file, and sets it to be deleted after the script has ended.
     * 
     * @param string $name name of file
     * @param mixed $content content to go inside file
     * 
     * @return string name of temporay file
     */
    public function temporaryFile($name, $content)
    {
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $file = tempnam(sys_get_temp_dir(), $name) . '.' . $ext;

        file_put_contents($file, $content);

        register_shutdown_function(function () use ($file) {
            unlink($file);
        });

        return $file;
    }


    /**
     * will concurrently download the files
     * 
     * @see https://stackoverflow.com/questions/9308779/php-parallel-curl-requests
     *
     * @param array $filearray a list of files to download
     */
    public function DownloadFiles(&$filearray, $timeout = 60)
    {
        $node_count = count($filearray);
        $curl_arr = array();
        $master = curl_multi_init();

        for ($i = 0; $i < $node_count; $i++) {
            $url = $filearray[$i]['url'];
            $curl_arr[$i] = curl_init($url);
            curl_setopt($curl_arr[$i], CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl_arr[$i], CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl_arr[$i], CURLOPT_RETURNTRANSFER, true);
            curl_multi_add_handle($master, $curl_arr[$i]);
            $filearray[$i]['resource'] = $curl_arr[$i];
        }

        do {
            curl_multi_exec($master, $running);
        } while ($running > 0);


        for ($i = 0; $i < $node_count; $i++) {
            $filearray[$i]['result'] = curl_multi_getcontent($curl_arr[$i]);
        }
    }


    /**
     * builds an upload function in real time
     * 
     * @return Upload
     */
    protected function getUpload()
    {
        $uploadclassbase = singleton(Upload::class);
        $uploadclassbase->config()->set('replaceFile', 'true');
        $uploadclassbase->config()->set('defaultVisibility', AssetStore::CONFLICT_OVERWRITE);

        $upload = Upload::create();
        $upload->getValidator()->setAllowedExtensions(
            array_filter(File::config()->uninherited('allowed_extensions'))
        );

        $upload->getValidator()->setAllowedMaxFileSize(
            $this->config()->max_upload_size
        );

        return $upload;
    }
}
