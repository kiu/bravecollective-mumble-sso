<?php if (!defined('GUEST')) die('go away'); ?>

<div style="font-size:80%; position:fixed; top:5px; right:5px; z-index:23;"><a href="logout.php">Logout</a></div>

<div class="container">
    <div class="jumbotron">
        <a href="https://wiki.braveineve.com" target="_blank"><img src="img/brave.png" class="pull-right"></a>
        <h1>Success!</h1>
	<p>
	    Welcome <?php echo htmlentities($_SESSION['character_name']); ?>,<br>
	    you have been granted access to <?php echo $cfg_mumble_title; ?>.<br>
<!--	    Please make sure you have the latest stable <a href="http://wiki.mumble.info/wiki/Main_Page#Download_Mumble" target="_blank">Mumble</a> version installed. -->
	</p>
	<p style="font-size:80%;">
	    After connecting to the voice server, you need to be dragged into a channel by somebody from BRAVE in order to speak. Be patient.<br>
	</p>
	<br>
	<br>
    </div>
    <div class="row">

	<div class="col-xs-6">
	    <div class="panel panel-default">
		<div class="panel-heading">
		    <h3 class="panel-title">Get Started</h3>
		</div>
		<div class="panel-body">
		    Install Mumble and connect to our server:<br><br>
		    <a href="http://wiki.mumble.info/wiki/Main_Page#Download_Mumble" target="_blank" class="btn btn-primary">Download Mumble</a>
		    &nbsp;&nbsp;&nbsp;
<a href="
<?php
    $url = 'mumble://';
    $url = $url . $_SESSION['mumble_username'];;
    $url = $url . ':';
    $url = $url . $_SESSION['mumble_password'];
    $url = $url . '@';
    $url = $url . $cfg_mumble_address;
    $url = $url . ':';
    $url = $url . $cfg_mumble_port;
    $url = $url . '/';
    $url = $url . $cfg_mumble_channel;
    $url = $url . '?version=' . $cfg_mumble_version;
    $url = $url . '&title=' . $cfg_mumble_title;
    echo $url;
?>
" class="btn btn-primary">Connect to Mumble</a><br>
<br>
		</div>
	    </div>
	</div>

	<div class="col-xs-6">
	    <div class="panel panel-default">
		<div class="panel-heading">
		    <h3 class="panel-title">Manual Configuration</h3>
		</div>
		<div class="panel-body">
		    <div class="row">
			<div class="col-xs-3">
			    <b>Label</b><br>
			    <b>Address</b><br>
			    <b>Port</b><br>
			    <b>Username</b><br>
			    <b>Password</b><br>
			</div>
			<div class="col-xs-9">
			    <?php echo $cfg_mumble_title; ?><br>
			    <?php echo $cfg_mumble_address; ?><br>
			    <?php echo $cfg_mumble_port; ?><br>
			    <?php echo $_SESSION['mumble_username']; ?><br>
			    <?php echo $_SESSION['mumble_password']; ?> <a href="pass.php?n=<?php echo $_SESSION['nonce']; ?>" class="btn btn-danger btn-xs pull-right">Reset Password</a><br>
			</div>
		    </div>
		</div>
	    </div>
	</div>
    </div>

</div>

