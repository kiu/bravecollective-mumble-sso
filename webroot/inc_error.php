<?php if (!defined('GUEST')) die('go away'); ?>

<div class="container">
    <div class="jumbotron">
	<h1>Danger, Will Robinson!</h1>
	<p>Hmmm...something went wrong:<br>
	<?php echo "<b>Error " . $_SESSION['error_code'] . "</b>: <i>" . $_SESSION['error_message'] . "</i>"; ?>
	<p>
	<a href="
	<?php echo $cfg_url_base; ?>
	" class="btn btn-primary btn-lg">Lets retry this!</a></p>
	<br>
	<span style="font-size:80%;">Reach out to <a href="http://evewho.com/pilot/kiu+Nakamura">kiu Nakamura</a> if this problem persists for a longer time.</span></p>

    </div>
</div>

<?php sdestroy(); ?>
