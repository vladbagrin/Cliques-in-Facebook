<?php
require_once('facebook/src/facebook.php');
require_once('db.php');
require_once('friend_list.php');
require_once('utils.php');
header('Content-Type: text/html; charset=utf-8');
header('p3p: CP="NOI ADM DEV PSAi COM NAV OUR OTR STP IND DEM"');
session_start();
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
</head>
<body>
<div id="fb-root"></div>
<script type="text/javascript" src="helper.js"></script>
<link rel="stylesheet" type="text/css" href="facebook_style/fb-buttons.css" />
<link rel="stylesheet" type="text/css" href="style.css" />
<script src="http://connect.facebook.net/en_US/all.js"></script>
<script>
FB.init({
appId  : '139006766174656',
status : true, // check login status
cookie : true, // enable cookies to allow the server to access the session
xfbml  : true, // parse XFBML
//channelUrl : 'http://WWW.MYDOMAIN.COM/channel.html', // channel.html file
oauth  : true // enable OAuth 2.0
});
  
function share_with_friends() {
	if (FB != null) {
		FB.ui({
			method: 'apprequests',
			message: 'See who are the friends that interacted with you most.'
		});
	}
}

function post_to_wall() {
	var called_before = false; // Another hack - callback being called twice!
	var count = 5; // 'tis what I chose
	var since = document.getElementById('since');
	since = since.options[since.selectedIndex].value;
	send_top_friends_request(count, since, function (data) {
		if (called_before) {
			return;
		} else {
			called_before = true;
		}
		var desc = 'Since ' + date_string_map[since] + ', my top ' + count + ' friends by interaction are:' + 
				'<center></center>' + data;
		FB.ui({
			method: 'feed',
			name: 'Friend Sieve',
			link: 'http://apps.facebook.com/friend-sieve/',
			picture: 'http://fbrell.com/f8.jpg',
			caption: ' ',
			description: desc,
			actions: [
				{
					name: 'Find out yours',
					link: app_address
				}
			]
		},
		function(response) {
			// Duly noted
		});
	});
}
</script>
<div id="intro">
<?php
// User has to log in
$fb = logged_in_check();

//$_SESSION["fb"] = $fb;
$user = $fb->getUser();
$_SESSION["user_id"] = $user;

// Database stuff
$db = new dbWrapper();
$dbUserInfo = $db->getUser($user);
if ($dbUserInfo == null) {
	$user_info = $fb->api("/$user?fields=name");
	$name = $user_info['name'];
	
	// Updating the database with our new user
	if (!$db->insertUser($user, $name)) {
		throw new Exception("Could not add new user in the database");
	}
} else {
	$name = $dbUserInfo["name"];
}

?>
<?php echo "<img style=\"float:left\" src=\"http://graph.facebook.com/$user/picture\">"; ?>
<span id="ginormousAppName">Friend Sieve</span>
<a class="uibutton confirm" style="float:right" href="feedback_form.php">Feedback</a>
<br>
<div style="margin-left:10px;display:inline">
	<iframe src="http://www.facebook.com/plugins/like.php?app_id=199210346810336&amp;href=http%3A%2F%2Fapps.facebook.com%2Ffriend-sieve%2F&amp;send=false&amp;layout=standard&amp;width=500&amp;show_faces=false&amp;action=recommend&amp;colorscheme=light&amp;font&amp;height=35" scrolling="no" frameborder="0" style="border:none; overflow:hidden; width:500px; height:35px;" allowTransparency="true"></iframe>
</div>
<p id="introText">Sort your friends by how much they interact with you, how much they are connected to your social network or by how much you are alike.
Decide who are your most valuable friends or see whom you can safely send an unvitation.
</p>
<div class="uibutton-toolbar">
	<a class="uibutton icon add" href="#" onclick="updateData()">Update</a>
	
	<div class="uibutton-group">
		<a class="uibutton confirm" href="#" onclick="share_with_friends()">Share</a>
		<a class="uibutton confirm" href="#" onclick="post_to_wall()">Publish</a>
	</div>
	
	<span class="toolbar">
	<label for="since">Since:</label>
	<select id="since" onchange="refreshList()">
		<option value="-1week">Last week</>
		<option value="-1month">Last month</>
		<option value="-3month">Last 3 months</>
		<option value="-6month">Last half year</>
		<option selected value="-1year">Last year</>
		<option value="-2year">Last 2 years</>
	</select>
	<label for="pagesize">Page size:</label>
	<select id="pagesize" onchange="changePageSize()">
		<option value="10">10</option>
		<option value="25">25</option>
		<option value="50">50</option>
		<option value="100">100</option>
	</select>
	<label for="order">Order:</label>
	<select id="order" onchange="changeOrder()">
		<option value="desc">Desc</>
		<option value="asc">Asc</>
	</select>
	<label for="by">By:</label>
	<select id="by" onchange="changeOrder()">
		<option value="score">Score</>
		<option value="mutual">Mutual friends</>
	</select>
	</span>
</div>
</div>
<br>
<div id="list"></div>
<script type="text/javascript">
    sendListRequest(createBasicLink() + '&refresh=true')
</script>
</body>
</html>