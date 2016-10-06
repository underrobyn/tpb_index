<?php

function ScriptTimings($start){
	return round((microtime(true) - $start),2);
}

function ChooseProxySite(){
	$sites = array(
		"https://gameofbay.org/",
		"https://ukpirate.click/",
		"https://unblockedbay.info/",
		"https://tpbunblocked.org/"
	);
	$total	= count($sites)-1;
	$server = $sites[rand(0,$total)];
	
	return [$server, $server . "torrent/"];
}

function TorrentId($server){
	if (isset($_COOKIE["position"])) {
		$x = $_COOKIE["position"];
		$value = $x + 1;
	} else {
		$value = 8333560; //8325365 Error at 8328043
	}
	
	setcookie("position",$value,time()+31556926,"/");
	echo "<div style='text-align:center;font-family:sans-serif;font-weight:100;'><span style='font-size:1.3em'>Using Server: $server</span><br /><br />";
	
	return $value;
}

function GetPageById($id, $proxy){
	$response = array("id"=>$id, "html"=>false, "code"=> 200);
	
	try {
		$c = curl_init($proxy . $id);
		
		curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($c, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($c, CURLOPT_SSL_VERIFYPEER, 0);
		
		$response["html"] = curl_exec($c);
		$response["code"] = curl_getinfo($c, CURLINFO_HTTP_CODE);
		
		if ($c === FALSE)
			throw new Exception('cURL couldn\'t initialise');
		if ($response["html"] === FALSE)
			throw new Exception(curl_error($c), curl_errno($c));
		
		curl_close($c);
		
		return $response;
	} catch(Exception $e) {
		trigger_error(sprintf('cURL failed with error #%d: %s', $e->getCode(), $e->getMessage()), E_USER_ERROR);
		return false;
	}
}

function CheckStatusCode($response){
	$killCodes = [400,401,402,403,405,406,500,502,503,504,520,521,522,523,524,525];
	$skipCodes = [204,205,206,307,404];
	$contCodes = [100,101,200,201,202,207,301,302,304,308];
	
	if (in_array($response["code"], $killCodes)) AllowRetry($response, 1);
	if (in_array($response["code"], $skipCodes)) SkipToNextTorrent($response["id"]);
	if (in_array($response["code"], $contCodes)) return true;
	
	AllowRetry($response, 1);
}

function AllowRetry($response, $type){
	if ($type == 1){
		setcookie("position",$response["id"],time()+31556926,"/");
		die("Error(" . $response["code"] . ") with torrent id " . $response["id"] . ", <a onclick='window.reload()' href='#'>Retry?</a>");
	} else {
		setcookie("position",$response["id"],time()+31556926,"/");
		die("Unknown Error (" . $response["code"] . ") with torrent id " . $response["id"] . ", <a onclick='window.reload()' href='#'>Retry?</a>");
	}
}

function SkipToNextTorrent($id){
	die('<script type="text/javascript"> window.location.reload(); </script>');
}

function GetDataFromHTML($response, $start){
	$data = array();
	$html = str_get_html($response["html"]);
	
	if ($html->find("div[id=err]") || $html->find("div[class=searchfield]")) {
		echo "Error->(" . $response["id"] . ") in " . ScriptTimings($start) . "s";
		SkipToNextTorrent($response["id"]);
	}
	
	$data["title"] 		= $html->find("div[id=title]", 0)->plaintext;
	$data["type"] 		= $html->find("div[id=details] a", 0)->plaintext;
	$data["filecount"] 	= $html->find("div[id=details] a", 1)->plaintext;
	$data["user"] 		= $html->find("div[id=details] a", 2)->plaintext;
	$data["details"] 	= $html->find("div[class=nfo] pre", 0)->plaintext;
	$data["magnet"] 	= $html->find("div[class=download] a", 0)->href;
	
	return $data;
}

function LoadResultStore(){
	$db = new SQLite3("tpb_db.sqlite3");
	return $db;
}

function EscapeData($data){
	return SQLite3::escapeString($data);
}

function SaveResult($save, $db, $response){
	$query = "INSERT INTO Links (TorrentID, Name, Type, Files, Uploader, Magnet) VALUES(
	'".EscapeData($response['id'])."',
	'".EscapeData($save['title'])."',
	'".EscapeData($save['type'])."',
	'".EscapeData($save['filecount'])."',
	'".EscapeData($save['user'])."',
	'".EscapeData($save['magnet'])."')";
	
	$r = @$db->exec($query);
	if (!$r) {
		if ($db->lastErrorMsg() != "UNIQUE constraint failed: Links.TorrentID") {
			die("Failed to write result for " . $response["id"]);
		} else {
			echo "Result for torrent " . $response["id"] . " is already in database, skipping<br />";
		}
	}
	
	return true;
}

function NextTorrent($db, $response, $start){
	echo "Done->(" . $response["id"] . ") in " . ScriptTimings($start) . "s";
	$db->close();
	SkipToNextTorrent($response["id"]);
}