<?php

abstract class Sources {
	const LIVE = 'live';
	const CACHE = 'cached';
}

/**
 * Caching the body of a HTTP response
 * based on https://gist.github.com/SaltwaterC/2215476
 * @param $url string
 * @param $duration int default 3600 (60 * 60 = one hour)
 * @param $skip_cache bool default FALSE
 * @return mixed $result containing status, source, and content
 */
function cache_url($url, $duration = 3600, $skip_cache = FALSE) {
	// settings
	$cachetime = $duration;
	$where = "cache";
	if ( ! is_dir($where)) {
		mkdir($where);
	}
	
	$hash = md5($url);
	$file = "$where/$hash.cache";
	
	// check the file
	$mtime = 0;
	if (file_exists($file)) {
		$mtime = filemtime($file);
	}
	$filetimemod = $mtime + $cachetime;
	
	// if the renewal date is smaller than now, return true; else false (no need for update)
	if ($filetimemod < time() OR $skip_cache) {
		$ch = curl_init($url);
		curl_setopt_array($ch, array(
			CURLOPT_HEADER         => FALSE,
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_USERAGENT      => "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:74.0) Gecko/20100101 Firefox/74.0",
			CURLOPT_FOLLOWLOCATION => TRUE,
			CURLOPT_MAXREDIRS      => 5,
			CURLOPT_CONNECTTIMEOUT => 15,
			CURLOPT_TIMEOUT        => 30,
		));
		$data = curl_exec($ch);

		$returnCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if($returnCode !== 200) {
			$data = "Error: [(" . $returnCode . ") " . curl_error($ch) . "]";
		} else {
			// save the file if there's data
			if ($data AND ! $skip_cache) {
				file_put_contents($file, $data);
			}
		}

		curl_close($ch);
		
		// want to track the return code as well as live or cache
		$result['code'] = $returnCode;
		$result['source'] = Sources::LIVE;
	} else {
		$data = file_get_contents($file);
		$result['code'] = 200;
		$result['source'] = Sources::CACHE;
	}
	$result['content'] = $data;
	
	return $result;
}

