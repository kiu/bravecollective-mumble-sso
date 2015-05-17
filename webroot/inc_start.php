<?php if (!defined('GUEST')) die('go away'); ?>

<div class="container">
    <div class="jumbotron">
	<a href="https://wiki.braveineve.com" target="_blank"><img src="img/brave.png" class="pull-right"></a>
	<h1><?php echo $cfg_mumble_title;?></h1>
	<br>
	<p>
	    Click the button below to login through <i>EVE Online SSO</i> in order to register your in-game name on the comms server.
	</p>
	<br>

<?php
    $url = 'https://login.eveonline.com/oauth/authorize/?response_type=code';
    $url = $url . '&redirect_uri=' . $cfg_url_base . "sso.php";
    $url = $url . '&client_id=' . $cfg_ccp_client_id;
    $url = $url . '&scope=';
    $url = $url . '&state=' . $_SESSION['nonce'];
    echo '<a href="' . $url . '"><img src="img/eve_sso_black.png"></a>';
?>

	<br>
	<br>
	<br>
	<p style="font-size:80%">
	    Learn more about the security of <i>EVE Online SSO</i> in this <a href="http://community.eveonline.com/news/dev-blogs/eve-online-sso-and-what-you-need-to-know/" target="_blank">dev-blog</a> article by <a href="https://twitter.com/CCP_FoxFour">CCP FoxFour</a>.<br>
	    Summary: Verify that after clicking the login button, you are redirected to https://login.eveonline.com/ and it <a href="img/ccp_hf_cert.png" target="_blank">shows up</a> as certified by <i>CCP Hf [IS]</i>.
	</p>
    </div>

</div>
