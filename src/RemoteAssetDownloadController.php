<?php

namespace OP;

use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Control\HTTPRequest;
use Exception;
use SilverStripe\Core\Config\Config;

/**
 * Downloads a single file
 * 
 * @see RemoteAssetDownloadTask.php
 */
class RemoteAssetDownloadController extends Controller {

	/**
	 * Downloads a single file
	 * @param SS_HTTPRequest $request checks the access code to make sure no
	 * public spoofing
	 */
	public function index(HTTPRequest $request) {
		// close the session to allow concurrent requests
		session_write_close();

		// bump up the memory
		ini_set('memory_limit', '1024M');
		set_time_limit(0);

		$params = $request->allParams();
		$config = Config::forClass(RemoteAssetTask::class);

		// note the url format for the key code is domain.com/remoteassetsync/<keyhere>
		if (!$config->key) {
			throw new Exception('Access key not set. See Readme.md for instructions on how to set this in your yml file');
		}
		if (!isset($params['AccessKey'])) {
			throw new Exception('Access key not set in URL');
		}


		if ($config->key != $params['AccessKey']) {
			throw new Exception('Key missmatch');
		}

		// download a file
		if (!$request->getVar('download')) {
			throw new Exception('Download file note set.');
		}

		$targetfile = preg_replace('/^\.\.\//', '', $request->getVar('download'));

		$targetfileencoded = implode('/', array_map('rawurlencode', explode('/', $targetfile)));
		$myurl = Controller::join_links($config->target, $targetfileencoded);

		try {
			$filecontents = RemoteAssetTask::DownloadFile($myurl);
		} catch (Exception $e) {
			throw new HTTPResponse_Exception($e->getMessage(), 400);
		}

		if (!$filecontents) {
			throw new HTTPResponse_Exception('Failed to download.', 400);
		}

		// create new folder if none exists
		$dirname = dirname($request->getVar('download'));
		if (!is_dir($dirname)) {
			mkdir($dirname, 0777, true);
		}

		// remove old file before saving
		if (file_exists($request->getVar('download'))) {
			unlink($request->getVar('download'));
		}

		if (!file_put_contents($request->getVar('download'), $filecontents)) {
			throw new HTTPResponse_Exception('Failed to write contents.', 400);
		}

		return json_encode('Success!');
	}

}
