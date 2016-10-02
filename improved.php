<?php
/*
 *	TPB Indexer v1.2
 *	Written by Jake-Cryptic
 *	http://absolutedouble.co.uk
*/

// Get and verify
require("improved.func.php");

$site 	= ChooseProxySite();
$id 	= TorrentId();
$page 	= GetPageById($id, $site);
$cont	= CheckStatusCode($page);


// Analyse and store
require("simple_html_dom.php");

$parsed	= GetDataFromHTML($page);
$store	= LoadResultStore();
$result	= SaveResult($parsed, $store, $page);

NextTorrent($store, $page);
?>