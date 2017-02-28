<?php
/*
 *	TPB Indexer CLI v1.4
 *	Written by Jake-Cryptic
 *	http://absolutedouble.co.uk
 * 	"3211770"
*/
//ini_set('memory_limit', '2M');
// Get and verify
require("export.cli.func.php");
require("simple_html_dom.php");

// Establish Database Connection
$db = new SQLite3("tpb_magnet_db.c.sqlite3");

if (!$db){
	die("Database Connection Error");
} else {
	echo "\n * (0) Connected to database";
}

$instance = new tpb_index("A",8337547,$db,1);

/*
// Create threads
$stack = array();

$stack[] = new tpb_index("A",8337547,$db,4);
$stack[] = new tpb_index("B",8337548,$db,4);
$stack[] = new tpb_index("C",8337549,$db,4);
$stack[] = new tpb_index("D",8337550,$db,4);

// Start The Threads
foreach ($stack as $t) {
	$t->start();
}*/

?>