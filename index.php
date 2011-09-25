<?php

// Facebook stuff
// App information
require_once('facebook/src/facebook.php');
header('Content-Type: text/html; charset=utf-8');
$app_secret = 'XXX';
$app_id = '133058413445569';
$app_addr = 'http://apps.facebook.com/sandbox_vladb/';

// Part of redirect script
$js = "<script type=\"text/javascript\">top.location.href =";

function logged_in_check() {
	global $js, $app_addr, $app_secret, $app_id;
	
	$fb = new Facebook(array('appId' => $app_id, 'secret' => $app_secret));
	$user = $fb->getUser();
	if (!$user) {
		$scope = '';
		$params = array('scope' => $scope, 'redirect_uri' => $app_addr);
		$login = $fb->getLoginUrl($params);
		$redirect_script = "$js \"$login\";</script>";
		echo $redirect_script;
		exit;
	} else {
		return $fb;
	}
}

function get_friend_list($fb) {
	$list = array();

	$query = "select uid, name from user where uid in (select uid2 from friend where uid1=me())";
	$list = $fb->api(array('method' => 'fql.query', 'query' => $query));

	return $list;
}

function get_mutual_friends($fb, $list) {
    $friend_ids = stringify_friend_list($list);
	$query = array();
    foreach ($list as $friend) {
		$id = $friend["uid"];
		$query[$id] = "select uid2 from friend where uid1=$id and uid2 in $friend_ids";
    }
	$queries = array_chunk($query, 200, true);
	$result = array();
	foreach ($queries as $chunk) {
		$result = array_merge($result, send_query($fb, $chunk));
	}
	return $result;
}

function stringify_friend_list($list) {
	$value = '(';
	foreach ($list as $friend) {
		$id = $friend["uid"];
		$value = $value . "$id,";
	}
	return substr($value, 0, -1) . ")";
}

function send_query($fb, $query) {
	$query = json_encode($query);
	
	$param = array(
		'method'   => 'fql.multiquery',
		'queries'  => $query,
		'callback' => ''
	);
	$result = $fb->api($param);
	
	return $result;
}

function get_social_graph($fb, $list) {
	$resource = get_mutual_friends($fb, $list);
	$graph = array();
	
	foreach ($resource as $entry) {
		$id = $entry["name"];
		$mutual_friends_list = $entry["fql_result_set"];
		$graph[$id] = array_map("process_result", $mutual_friends_list);
	}
	
	return $graph;
}

function process_result($entry) {
	return $entry["uid2"];
}

function bron_kerbosch($graph, $r, $p, $x) {
	global $recursive_calls;
	$recursive_calls++;
	if (count($p) == 0 && count($x) == 0) {
		process_clique($r);
	} else {
		$pivot = select_pivot($graph, $p);
		$i = count($p);
		while ($i > 0) {
			$i--;
			$v = array_shift($p);
			if (!in_array($v, $graph[$pivot])) {
				bron_kerbosch($graph, array_merge($r, array($v)), array_intersect($p, $graph[$v]), array_intersect($x, $graph[$v]));
				array_push($x, $v);
			} else {
				array_push($p, $v);
			}
		}
	}
}

function select_pivot($graph, $p) {
	$pivot = -1;
	$max = -1;
	foreach ($p as $vertex) {
		$degree = count($graph[$vertex]);
		if ($degree > $max) {
			$max = $degree;
			$pivot = $vertex;
		}
	}
	return $pivot;
}

function process_clique($clique) {
	global $names;
	echo "<li><ul>\n";
	foreach ($clique as $id) {
		echo "<li>" . $names[$id] . "</li>\n";
	}
	echo "</ul></li>";
}

// Access the friend's name by his ID
function assoc_id_name($list) {
	$result = array();
	foreach ($list as $entry) {
		$result[$entry["uid"]] = $entry["name"];
	}
	return $result;
}

function mtime() {
	return round(microtime(true) * 1000);
}

// Work is done here
$recursive_calls = 0;
$time_start = mtime();

$fb = logged_in_check();
$time_login = mtime();

$list = get_friend_list($fb);
$names = assoc_id_name($list);
$time_list = mtime();

$graph = get_social_graph($fb, $list);
$time_graph = mtime();

echo "<ol>\n";
bron_kerbosch($graph, array(), array_keys($graph), array());
echo "</ol>\n";
$time_algorithm = mtime();

echo "Number of recursive calls: $recursive_calls<br>\n";
echo "Time intervals:<ul>\n";
echo "<li>Log in: " . ($time_login - $time_start) . "ms</li>\n";
echo "<li>Friend list request: " . ($time_list - $time_login) . "ms</li>\n";
echo "<li>Social graph request: " . ($time_graph - $time_list) . "ms</li>\n";
echo "<li>Cliques listing: " . ($time_algorithm - $time_graph) . "ms</li></ul>\n";
?>
