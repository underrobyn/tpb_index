<?php
/*
 *	TPB Indexer Web v1.4
 *	Written by Jake-Cryptic
 *	http://absolutedouble.co.uk
 * 	"3211770"
*/

// Get and verify
require("improved.func.php");

$start	= microtime(true);
$site 	= ChooseProxySite();
$id 	= TorrentId($site[0]);
$page 	= GetPageById($id, $site[1]);
$cont	= CheckStatusCode($page);


// Analyse and store
require("simple_html_dom.php");

$parsed	= GetDataFromHTML($page, $start);
$store	= LoadResultStore();
$result	= SaveResult($parsed, $store, $page);

NextTorrent($store, $page, $start);
?>