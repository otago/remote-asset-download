<?php

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
class RemoteAssetTask extends BuildTask {

	protected $title = "Download Remote SilverStripe files";
	protected $description = "Retrieves files from a remote 
		SilverStripe instance, doesn't update existing files.";
	static public $groupings = 100;

	/**
	 * will ask the target server to return the file list and the data object list
	 * @param type $request
	 */
	public function run($request) {
		if (!$this->config()->target) {
			throw new Exception('Target not found in yml file. See readme.md for installation instructions.');
		}
		if (!$this->config()->key) {
			throw new Exception('Key not found in yml file. See readme.md for installation instructions.');
		}

		$myurl = Director::absoluteURL('/remoteassetdiff') . '/' . urlencode($this->config()->key);
		$downloadurl = Director::absoluteURL('/remoteassetdownload') . '/' . urlencode($this->config()->key) . '?m=' . time();

		// todo: if CLI, download without javascript
		if (Director::is_cli()) {
			ini_set('memory_limit', '1024M');
			set_time_limit(0);

			return;
		}

		echo ArrayData::create(array('FetchURL' => $myurl, 'DownloadURL' => $downloadurl, 'Target' => $this->config()->target, 'ToMachine' => Director::absoluteURL('/')))->renderWith('RemoteAssetTask');
	}

	/**
	 * will download the file and return it
	 * @param string $url
	 * @return data
	 */
	public static function DownloadFile($url, $timeout = 60) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

		if (Director::isDev() || Director::isTest()) {
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, '1');
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, '0');
		}

		$result = curl_exec($ch);
		$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		if ($code != 200) {
			$err = curl_error($ch);
			curl_close($ch);
			throw new Exception($err . ' code ' . $code);
		}
		curl_close($ch);
		return $result;
	}

}
