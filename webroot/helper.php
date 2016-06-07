<?php if (!defined('GUEST')) die('go away'); ?>

<?php

function krand($length) {
    $alphabet = "abcdefghkmnpqrstuvwxyzABCDEFGHKMNPQRSTUVWXYZ23456789";
    $pass = "";
    for($i = 0; $i < $length; $i++) {
	$pass = $pass . substr($alphabet, hexdec(bin2hex(openssl_random_pseudo_bytes(1))) % strlen($alphabet), 1);
    }
    return $pass;
}

function sstart() {
    global $_SESSION;

    session_start();

    if (!isset($_SESSION['nonce'])) {
	$_SESSION['nonce'] = krand(22);
    }
}

function sdestroy() {
    global $_SESSION;

    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
	$params = session_get_cookie_params();
	setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();
}

function toMumbleName($name) {
    return strtolower(preg_replace("/[^A-Za-z0-9\-]/", '_', $name));
}

function sso_update() {
    global $cfg_ccp_client_id, $cfg_ccp_client_secret, $cfg_user_agent, $cfg_sql_url, $cfg_sql_user, $cfg_sql_pass, $cfg_restrict_access_by_ticker, $_SESSION, $_GET;

    // ---- Check parameters

    if (!isset($_GET['state'])) {
	$_SESSION['error_code'] = 10;
	$_SESSION['error_message'] = 'State not found.';
	return false;
    }
    $sso_state = $_GET['state'];

    if (!isset($_GET['code'])) {
	$_SESSION['error_code'] = 11;
	$_SESSION['error_message'] = 'Code not found.';
	return false;
    }
    $sso_code = $_GET['code'];

    if (!isset($_SESSION['nonce'])) {
	$_SESSION['error_code'] = 12;
	$_SESSION['error_message'] = 'Nonce not found.';
	return false;
    }
    $nonce = $_SESSION['nonce'];

    // ---- Verify nonce

    if ($nonce != $sso_state) {
	$_SESSION['error_code'] = 20;
	$_SESSION['error_message'] = 'Nonce is out of sync.';
	return false;
    }

    // ---- Translate code to token

    $data = http_build_query(
	array(
	    'grant_type' => 'authorization_code',
	    'code' => $sso_code,
	)
    );
    $options = array(
	'http' => array(
	    'method'  => 'POST',
	    'header'  => array(
		'Authorization: Basic ' . base64_encode($cfg_ccp_client_id . ':' . $cfg_ccp_client_secret),
		'Content-type: application/x-www-form-urlencoded',
		'Host: login.eveonline.com',
		'User-Agent: ' . $cfg_user_agent,
	    ),
	    'content' => $data,
        ),
    );
    $result = file_get_contents('https://login.eveonline.com/oauth/token', false, stream_context_create($options));

    if (!$result) {
	$_SESSION['error_code'] = 30;
	$_SESSION['error_message'] = 'Failed to convert code to token.';
	return false;
    }
    $sso_token = json_decode($result)->access_token;

    // ---- Translate token to character

    $options = array(
	'http' => array(
	    'method'  => 'GET',
	    'header'  => array(
		'Authorization: Bearer ' . $sso_token,
		'Host: login.eveonline.com',
		'User-Agent: ' . $cfg_user_agent,
	    ),
        ),
    );
    $result = file_get_contents('https://login.eveonline.com/oauth/verify', false, stream_context_create($options));

    if (!$result) {
	$_SESSION['error_code'] = 40;
	$_SESSION['error_message'] = 'Failed to convert token to character.';
	return false;
    }

    $json = json_decode($result);
    $character_id = $json->CharacterID;
    $owner_hash = $json->CharacterOwnerHash;

    // ---- Database

    try {
	$dbr = new PDO($cfg_sql_url, $cfg_sql_user, $cfg_sql_pass);
    } catch (PDOException $e) {
	$_SESSION['error_code'] = 50;
	$_SESSION['error_message'] = 'Failed to connect to the database.';
	return false;
    }

    // ---- Character details

    $options = array(
	'http' => array(
	    'method'  => 'GET',
	    'header'  => array(
		'Host: api.eveonline.com',
		'User-Agent: ' . $cfg_user_agent,
	    ),
        ),
    );
    $result = file_get_contents('https://api.eveonline.com/eve/CharacterAffiliation.xml.aspx?ids=' . $character_id, false, stream_context_create($options));
    if (!$result) {
	$_SESSION['error_code'] = 60;
	$_SESSION['error_message'] = 'Failed to retrieve character details.';
	return false;
    }
    $apiInfo = new \SimpleXMLElement($result);
    $row = $apiInfo->result->rowset->row->attributes();

    $character_name = (string)$row->characterName;
    $corporation_id = (int)$row->corporationID;
    $corporation_name = (string)$row->corporationName;
    $alliance_id = (int)$row->allianceID;
    $alliance_name = (string)$row->allianceName;

    $mumble_username = toMumbleName($character_name);

    $updated_at = time();

    // ---- Access Restrictions

    if ($cfg_restrict_access_by_ticker > 0) {
	$stm = $dbr->prepare('SELECT * FROM ticker WHERE filter = :filter');
	$stm->bindValue(':filter', 'alliance-' . $alliance_id);
	if (!$stm->execute()) {
	    $_SESSION['error_code'] = 70;
	    $_SESSION['error_message'] = 'Failed to query ticker database for alliance.';
	    $arr = $stm->ErrorInfo();
	    error_log('SQL failure:'.$arr[0].':'.$arr[1].':'.$arr[2]);
	    return false;
	}
	$rowa = $stm->fetch();

	$stm = $dbr->prepare('SELECT * FROM ticker WHERE filter = :filter');
	$stm->bindValue(':filter', 'corporation-' . $corporation_id);
	if (!$stm->execute()) {
	    $_SESSION['error_code'] = 71;
	    $_SESSION['error_message'] = 'Failed to query ticker database for corporation.';
	    $arr = $stm->ErrorInfo();
	    error_log('SQL failure:'.$arr[0].':'.$arr[1].':'.$arr[2]);
	    return false;
	}
	$rowc = $stm->fetch();

	if ($cfg_restrict_access_by_ticker == 1 && !($rowa || $rowc)) {
	    $_SESSION['error_code'] = 72;
	    $_SESSION['error_message'] = 'Access to this server is for members of specific corporations and alliances only.';
	    return false;
	}
	if ($cfg_restrict_access_by_ticker == 2 && !($rowa && $rowc)) {
	    $_SESSION['error_code'] = 73;
	    $_SESSION['error_message'] = 'Access to this server is for members of specific corporations and alliances only.';
	    return false;
	}
    }

    // ---- Banning

    $stm = $dbr->prepare('SELECT * FROM ban WHERE filter = :filter');
    $stm->bindValue(':filter', 'alliance-' . $alliance_id);
    if (!$stm->execute()) {
	$_SESSION['error_code'] = 80;
	$_SESSION['error_message'] = 'Failed to query ban database for alliance.';
	$arr = $stm->ErrorInfo();
	error_log('SQL failure:'.$arr[0].':'.$arr[1].':'.$arr[2]);
	return false;
    }
    if ($row = $stm->fetch()) {
	$_SESSION['error_code'] = 81;
	$_SESSION['error_message'] = 'Your alliance is banned. Reason: ' . htmlentities($row['reason_public']);
	return false;
    }

    $stm = $dbr->prepare('SELECT * FROM ban WHERE filter = :filter');
    $stm->bindValue(':filter', 'corporation-' . $corporation_id);
    if (!$stm->execute()) {
	$_SESSION['error_code'] = 82;
	$_SESSION['error_message'] = 'Failed to query ban database for character.';
	$arr = $stm->ErrorInfo();
	error_log('SQL failure:'.$arr[0].':'.$arr[1].':'.$arr[2]);
	return false;
    }
    if ($row = $stm->fetch()) {
	$_SESSION['error_code'] = 83;
	$_SESSION['error_message'] = 'Your corporation is banned. Reason: ' . htmlentities($row['reason_public']);
	return false;
    }

    $stm = $dbr->prepare('SELECT * FROM ban WHERE filter = :filter');
    $stm->bindValue(':filter', 'character-' . $character_id);
    if (!$stm->execute()) {
	$_SESSION['error_code'] = 84;
	$_SESSION['error_message'] = 'Failed to query ban database for character.';
	$arr = $stm->ErrorInfo();
	error_log('SQL failure:'.$arr[0].':'.$arr[1].':'.$arr[2]);
	return false;
    }
    if ($row = $stm->fetch()) {
	$_SESSION['error_code'] = 85;
	$_SESSION['error_message'] = 'You are banned. Reason: ' . htmlentities($row['reason_public']);
	return false;
    }

    // ---- Update database

    $stm = $dbr->prepare('SELECT * FROM user WHERE character_id = :character_id');
    $stm->bindValue(':character_id', $character_id);
    if (!$stm->execute()) {
	$_SESSION['error_code'] = 90;
	$_SESSION['error_message'] = 'Failed to retrieve user.';
	$arr = $stm->ErrorInfo();
	error_log('SQL failure:'.$arr[0].':'.$arr[1].':'.$arr[2]);
	return false;
    }

    $mumble_password = krand(10);

    if ($row = $stm->fetch()) {
	if ($owner_hash == $row['owner_hash']) {
	    $mumble_password = $row['mumble_password'];
	}
	$stm = $dbr->prepare('UPDATE user set character_name = :character_name, corporation_id = :corporation_id, corporation_name = :corporation_name, alliance_id = :alliance_id, alliance_name = :alliance_name, mumble_username = :mumble_username, mumble_password = :mumble_password, updated_at = :updated_at, owner_hash = :owner_hash WHERE character_id = :character_id');
	$stm->bindValue(':character_id', $character_id);
	$stm->bindValue(':character_name', $character_name);
	$stm->bindValue(':corporation_id', $corporation_id);
	$stm->bindValue(':corporation_name', $corporation_name);
	$stm->bindValue(':alliance_id', $alliance_id);
	$stm->bindValue(':alliance_name', $alliance_name);
	$stm->bindValue(':mumble_username', $mumble_username);
	$stm->bindValue(':mumble_password', $mumble_password);
	$stm->bindValue(':updated_at', $updated_at);
	$stm->bindValue(':owner_hash', $owner_hash);
	if (!$stm->execute()) {
	    $_SESSION['error_code'] = 91;
	    $_SESSION['error_message'] = 'Failed to update user.';
	    $arr = $stm->ErrorInfo();
	    error_log('SQL failure:'.$arr[0].':'.$arr[1].':'.$arr[2]);
	    return false;
	}
    } else {
	$stm = $dbr->prepare('INSERT INTO user (character_id, character_name, corporation_id, corporation_name, alliance_id, alliance_name, mumble_username, mumble_password, created_at, updated_at, owner_hash) VALUES (:character_id, :character_name, :corporation_id, :corporation_name, :alliance_id, :alliance_name, :mumble_username, :mumble_password, :created_at, :updated_at, :owner_hash)');
	$stm->bindValue(':character_id', $character_id);
	$stm->bindValue(':character_name', $character_name);
	$stm->bindValue(':corporation_id', $corporation_id);
	$stm->bindValue(':corporation_name', $corporation_name);
	$stm->bindValue(':alliance_id', $alliance_id);
	$stm->bindValue(':alliance_name', $alliance_name);
	$stm->bindValue(':mumble_username', $mumble_username);
	$stm->bindValue(':mumble_password', $mumble_password);
	$stm->bindValue(':created_at', $updated_at);
	$stm->bindValue(':updated_at', $updated_at);
	$stm->bindValue(':owner_hash', $owner_hash);
	if (!$stm->execute()) {
	    $_SESSION['error_code'] = 92;
	    $_SESSION['error_message'] = 'Failed to insert user.';
	    $arr = $stm->ErrorInfo();
	    error_log('SQL failure:'.$arr[0].':'.$arr[1].':'.$arr[2]);
	    return false;
	}
    }

    // ---- Success

    $_SESSION['error_code'] = 0;
    $_SESSION['error_message'] = 'OK';

    $_SESSION['character_id'] = $character_id;
    $_SESSION['character_name'] = $character_name;
    $_SESSION['corporation_id'] = $corporation_id;
    $_SESSION['corporation_name'] = $corporation_name;
    $_SESSION['alliance_id'] = $alliance_id;
    $_SESSION['alliance_name'] = $alliance_name;

    $_SESSION['mumble_username'] = $mumble_username;
    $_SESSION['mumble_password'] = $mumble_password;

    $_SESSION['updated_at'] = $updated_at;

    return true;
}

function pass_update() {
    global $cfg_sql_url, $cfg_sql_user, $cfg_sql_pass, $_SESSION, $_GET;

    // ---- Verify access

    if (!isset($_SESSION['character_id']) || $_SESSION['character_id'] == 0) {
	$_SESSION['error_code'] = 100;
	$_SESSION['error_message'] = 'User not found.';
	return false;
    }
    $character_id = $_SESSION['character_id'];

    if (!isset($_SESSION['nonce'])) {
	$_SESSION['error_code'] = 101;
	$_SESSION['error_message'] = 'Nonce not found in session.';
	return false;
    }
    if (!isset($_GET['n'])) {
	$_SESSION['error_code'] = 102;
	$_SESSION['error_message'] = 'Nonce not found in url.';
	return false;
    }

    if ($_SESSION['nonce'] != $_GET['n']) {
	$_SESSION['error_code'] = 103;
	$_SESSION['error_message'] = 'Nonces dont match.';
	return false;
    }

    // ---- Database

    try {
	$dbr = new PDO($cfg_sql_url, $cfg_sql_user, $cfg_sql_pass);
    } catch (PDOException $e) {
	$_SESSION['error_code'] = 104;
	$_SESSION['error_message'] = 'Failed to connect to the database.';
	return false;
    }

    // ---- Update user

    $mumble_password = krand(10);

    $stm = $dbr->prepare('UPDATE user SET mumble_password = :mumble_password WHERE character_id = :character_id');
    $stm->bindValue(':character_id', $character_id);
    $stm->bindValue(':mumble_password', $mumble_password);

    if (!$stm->execute()) {
	$_SESSION['error_code'] = 105;
	$_SESSION['error_message'] = 'Failed to update password.';
	$arr = $stm->ErrorInfo();
	error_log('SQL failure:'.$arr[0].':'.$arr[1].':'.$arr[2]);
	return false;
    }
    if (!$stm->rowCount() > 0) {
	    $_SESSION['error_code'] = 106;
	    $_SESSION['error_message'] = 'Unknown user for password update.';
	    return false;
    }

    // ---- Success

    $_SESSION['error_code'] = 0;
    $_SESSION['error_message'] = 'OK';

    $_SESSION['mumble_password'] = $mumble_password;

    return true;
}

function character_refresh() {
    global $cfg_sql_url, $cfg_sql_user, $cfg_sql_pass, $cfg_user_agent;

    try {
	$dbr = new PDO($cfg_sql_url, $cfg_sql_user, $cfg_sql_pass);
    } catch (PDOException $e) {
	    echo "FAIL: Failed to connect to the database.\n";
	    return;
    }

    $stmu = $dbr->prepare('SELECT * FROM user WHERE updated_at < :updated_at');
    $stmu->bindValue(':updated_at', time() - (60 * 60 * 24));
    if (!$stmu->execute()) {
	$arr = $stmu->ErrorInfo();
	echo ('FAIL: Failed to retrieve users: '.$arr[0].':'.$arr[1].':'.$arr[2] . "\n");
	return false;
    }

    while ($row = $stmu->fetch()) {
	sleep(1);
	$character_id = $row['character_id'];
	echo "Updating: " . $character_id;

	$options = array(
	    'http' => array(
		'method'  => 'GET',
		'header'  => array(
		    'Host: api.eveonline.com',
		    'User-Agent: ' . $cfg_user_agent,
		),
	    ),
	);
	$result = file_get_contents('https://api.eveonline.com/eve/CharacterAffiliation.xml.aspx?ids=' . $character_id, false, stream_context_create($options));
	if (!$result) {
	    echo "...failed to retrieve affiliation.\n";
	    continue;
	}
	$apiInfo = new SimpleXMLElement($result);
	$row = $apiInfo->result->rowset->row->attributes();

	$character_name = (string)$row->characterName;
	$corporation_id = (int)$row->corporationID;
	$corporation_name = (string)$row->corporationName;
	$alliance_id = (int)$row->allianceID;
	$alliance_name = (string)$row->allianceName;
	$mumble_username = toMumbleName($character_name);
	$updated_at = time();

	$stm = $dbr->prepare('UPDATE user set character_name = :character_name, corporation_id = :corporation_id, corporation_name = :corporation_name, alliance_id = :alliance_id, alliance_name = :alliance_name, mumble_username = :mumble_username, updated_at = :updated_at WHERE character_id = :character_id');
	$stm->bindValue(':character_id', $character_id);
	$stm->bindValue(':character_name', $character_name);
	$stm->bindValue(':corporation_id', $corporation_id);
	$stm->bindValue(':corporation_name', $corporation_name);
	$stm->bindValue(':alliance_id', $alliance_id);
	$stm->bindValue(':alliance_name', $alliance_name);
	$stm->bindValue(':mumble_username', $mumble_username);
	$stm->bindValue(':updated_at', $updated_at);
	if (!$stm->execute()) {
	    $arr = $stm->ErrorInfo();
	    echo ('...failed to update user: '.$arr[0].':'.$arr[1].':'.$arr[2] . "\n");
	    continue;
	}
	echo "...OK\n";
    }

    return true;
}
?>
