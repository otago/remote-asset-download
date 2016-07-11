<?php

/**
 * Returns a list of the file to download in JSON format. 
 * 
 * @see RemoteAssetTask.php
 */
class RemoteAssetDiffController extends Page_Controller {

	private static $url_handlers = array(
		'$AccessKey!' => 'view',
	);
	private static $allowed_actions = array('view');

	/**
	 * Outputs the list of files, and the ID and edit dates of the Data Objects
	 * @param SS_HTTPRequest $request checks the access code to make sure no
	 * public spoofing
	 */
	public function view(SS_HTTPRequest $request) {
		// close the session to allow concurrent requests
		session_write_close();

		// bump up the memory
		ini_set('memory_limit', '1024M');
		set_time_limit(0);

		$params = $request->allParams();

		// note the url format for the key code is domain.com/remoteassetsync/<keyhere>
		if (!isset($params['AccessKey'])) {
			throw new Exception('Access key not set. See Readme.md for instructions on how to set this in your yml file.');
		}

		$config = Config::inst()->forClass('RemoteAssetTask');

		if ($config->key != $params['AccessKey']) {
			throw new Exception('Key missmatch');
		}

		$myurl = Controller::join_links($config->target, 'remoteassetlist/', urlencode($config->key)) . '?m=' . time();

		// fetch the remote list of files to check
		try {
			$remotefiles = RemoteAssetTask::DownloadFile($myurl, 60000);
		} catch (Exception $e) {
			throw new SS_HTTPResponse_Exception(json_encode($e->getMessage()), 400);
		}

		// decode
		$remotejson = json_decode($remotefiles);

		// list local files
		$list = array();
		RemoteAssetListController::recurseDir('../assets', $list);


		// these are the files that are different from your list
		//$downloadlist = array_diff($remotejson, $list);
		//http://stackoverflow.com/questions/2985799/handling-large-arrays-with-array-diff
		$downloadlist = $this->leo_array_diff($remotejson, $list);

		// if you're ignoring a file, remove it from the download list
		$ignorelist = array();
		$downloadlistlen = strlen('../assets');
		foreach ($downloadlist as $key => $file) {
			foreach ($config->excludedfolders as $ignoredterm) {
				if (strpos($file, $ignoredterm) === $downloadlistlen) {
					$ignorelist [] = $file;
					unset($downloadlist[$key]);
				}
			}
		}

		echo '{"download":';
		print_r(json_encode(array_values($downloadlist)));
		echo ',"ignored":';
		print_r(count($ignorelist));
		echo ',"synced":';
		print_r(count($remotejson) - count($downloadlist));
		echo '}';
	}

	public function leo_array_diff($a, $b) {
		$map = array();
		foreach ($a as $val)
			$map[$val] = 1;
		foreach ($b as $val)
			unset($map[$val]);
		return array_keys($map);
	}

}
