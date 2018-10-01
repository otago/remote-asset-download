<?php

namespace OP;

use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Config\Config;
use Exception;

/**
 * Returns a list of the files in JSON format. 
 * 
 * @see RemoteAssetDownloadTask.php
 */
class RemoteAssetListController extends Controller {

	/**
	 * Outputs the list of files, and the ID and edit dates of the Data Objects
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

		$list = [];
		RemoteAssetListController::recurseDir(ASSETS_PATH, $list);
		echo json_encode(array_values($list));
	}

	/**
	 * builds a list of all the files and folders in $dir
	 * @param string $dir
	 * @param array $array
	 */
	public static function recurseDir($dir, &$array) {
		if ($dh = opendir($dir)) {
			while (($file = readdir($dh)) !== false) {
				if ($file != '.' && $file != '..') {
					if (!is_dir($dir . '/' . $file)) {
						$array [] = $dir . '/' . $file;
					} else {
						RemoteAssetListController::recurseDir($dir . '/' . $file, $array);
					}
				}
			}
			closedir($dh);
		}
	}

}
