<?php

class tpb_index {
	
	public $torrentID;
	public $proxySite;
	public $siteLeech;
	public $databasec;
	public $starttime;
	public $tidoffset;
	private $threadID;
	
	public function __construct($id,$tid,$db,$offset){
		$this->databasec = $db;
		$this->tidoffset = $offset;
		
		$this->threadID = $id;
		
		print("\n * ({$id}) Thread Created\n");
		
		$this->Execute($tid);
	}
	
	public function Execute($tid){
		$this->torrentID = $tid;
		$this->Upd();
		
		$this->ChooseProxy();
		$this->starttime = microtime(true);
		
		$p = $this->GetPage();
		$c = $this->CheckStatusCode($p);
		$h = $this->DecodeHTML($p);
		
		if (count($h) !== 0){
			$r = $this->StoreResult($h);
			
			$this->NextTorrent();
		}
	}
	
	private function LogProgress($m){
		print("\n * ({$this->threadID}) {$m}\n");
	}
	
	private function ChooseProxy(){
		$proxies = array(
			"https://pirateproxy.vip/",
			"https://tpbunblocked.org/",
			"https://thepiratebay.red/",
			"https://123bay.org/",
			"https://thehiddenbay.info/"
		);
		
		$total	= count($proxies)-1;
		$server = $proxies[rand(0,$total)];
	
		$this->proxySite = $server;
		$this->siteLeech = $server . "torrent/";
	}
	
	private function GetPage(){
		try {
			$c = curl_init($this->siteLeech . $this->torrentID);
			
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
			
			$this->LogProgress("Got Page({$response["code"]}) For Torrent->({$this->torrentID}) using Proxy->({$this->proxySite})");
			
			return $response;
		} catch(Exception $e) {
			trigger_error(sprintf('cURL failed with error #%d: %s', $e->getCode(), $e->getMessage()), E_USER_ERROR);
			return false;
		}
	}
	
	private function AttemptRetry($x,$y){
		if ($y == 0){
			$this->LogProgress("Unknown error at Torrent->({$this->torrentID}) using Proxy->({$this->proxySite}); Exiting....");
			die();
		}
		$this->LogProgress("!Page For Torrent->({$this->torrentID}) using Proxy->({$this->proxySite}) returned " . $x["code"]);
		$this->NextTorrent();
	}
	
	private function CheckStatusCode($response){
		$killCodes = [400,401,402,403,405,406,500,502,503,504,520,521,522,523,524,525];
		$skipCodes = [204,205,206,307,404];
		$contCodes = [100,101,200,201,202,207,301,302,304,308];
		
		if (in_array($response["code"], $killCodes)) {
			$this->AttemptRetry($response, 1);
		} elseif (in_array($response["code"], $skipCodes)) {
			$this->NextTorrent();
		} elseif (in_array($response["code"], $contCodes)) {
			return true;
		} else {
			$this->AttemptRetry($response, 0);
		}
	}
	
	private function DecodeHTML($response){
		$data = array();
		$html = str_get_html($response["html"]);
		
		if (empty($html) || !$html->find('html')){
			print_r("\n**************************************\n\n");
			print_r("Torrent: {$this->torrentID}\nStatus: {$response['code']}\nProxy: {$this->siteLeech}\nTime: " . time() . "\n\n**************************************\n\n");
		} else {
			try {
				if ($html->find("div[id=err]") || $html->find("div[class=searchfield]")) {
					$this->LogProgress("Error(1:NO_SEARCH) At Torrent->({$this->torrentID})");
					$this->NextTorrent();
				} elseif (!$html->find("div[id=title]", 0) || empty($html->find("div[id=title]", 0))) {
					$this->LogProgress("Error(2:NO_TITLE) At Torrent->({$this->torrentID})");
					$this->NextTorrent();
				} else {
					$data["title"] 		= $html->find("div[id=title]", 0)->plaintext;
					$data["type"] 		= $html->find("div[id=details] a", 0)->plaintext;
					$data["filecount"] 	= $html->find("div[id=details] a", 1)->plaintext;
					$data["user"] 		= $html->find("div[id=details] a", 2)->plaintext;
					$data["details"] 	= $html->find("div[class=nfo] pre", 0)->plaintext;
					$data["magnet"] 	= $html->find("div[class=download] a", 0)->href;
					
					$this->LogProgress("Parsed HTML For Torrent->({$this->torrentID})");
				}
			} catch (Exception $e) {
				print_r($e);
				sleep(5);
			}
		}
		
		return $data;
	}
	
	private function EscapeData($data){
		return SQLite3::escapeString($data);
	}
	
	private function StoreResult($save){
		$query = "INSERT INTO Links (TorrentID, Name, Type, Files, Uploader, Magnet) VALUES(
		'".$this->EscapeData($this->torrentID)."',
		'".$this->EscapeData($save['title'])."',
		'".$this->EscapeData($save['type'])."',
		'".$this->EscapeData($save['filecount'])."',
		'".$this->EscapeData($save['user'])."',
		'".$this->EscapeData($save['magnet'])."')";
		
		$r = @$this->databasec->exec($query);
		if (!$r) {
			if ($this->databasec->lastErrorMsg() != "UNIQUE constraint failed: Links.TorrentID") {
				$this->LogProgress("Failed to write result for Torrent->({$this->torrentID})\n{$this->databasec->lastErrorMsg()}");
				die();
			} else {
				$this->LogProgress("Skipped Job For Torrent->({$this->torrentID})");
			}
		} else {
			$this->LogProgress("Finished Job For Torrent->({$this->torrentID})");
		}
		
		return true;
	}
	
	private function Upd(){
		$size = memory_get_usage();
		$unit = array('B','KB','MB','GB','TB','PB');
		$eusg = @round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
		$this->LogProgress("MemUSG: " . $eusg);
		
		@cli_set_process_title("jake-cryptic\\tpb_index - Torrent:{$this->torrentID} - RAM:{$eusg}");
	}
	
	private function NextTorrent(){
		$this->Execute(($this->torrentID + $this->tidoffset));
	}
}