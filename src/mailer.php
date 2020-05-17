<?php

function smtp_mail($subject, $body)
{
    global $conf;
    $relay = $conf['smtp_server'];
    $helo = $_SERVER['SERVER_NAME'];
    $date = sprintf('%s', date(DATE_RFC2822));
    $caps = '';

    $data = <<<EOD
From: <{$conf['smtp_sender']}>
To: <{$conf['blog_owner']}>
Subject: {$subject}
Date: {$date}

{$body}
.
EOD;

    $fp = stream_socket_client("tcp://{$relay}", $errno, $errstr, 5);
    if (!$fp) {
	return ['success' => 0, 'error' => "Error: $errstr ($errno)"];
    } else {
	$resp = fgets($fp);

	if (preg_match('/^220 /', $resp)) {
	    fwrite($fp, "EHLO {$helo}\r\n");
	    while (is_resource($fp) && !feof($fp)) {
		$str = @fgets($fp, 515);
		$caps .= $str;
		if ((isset($str[3]) and $str[3] == ' ')) {
		    break;
		}
	    }
	    fwrite($fp, "MAIL FROM: <{$from}>\r\n");
	    $resp = fgets($fp);
	    if (!preg_match("/^250 /", $resp)) {
		return ['success' => 0, 'error' => "Error: $resp"];
	    }
	    fwrite($fp, "RCPT TO: <{$to}>\r\n");
	    $resp = fgets($fp);
	    if (!preg_match("/^250 /", $resp)) {
		return ['success' => 0, 'error' => "Error: $resp"];
	    }
	    fwrite($fp, "DATA\r\n");
	    $resp = fgets($fp);
	    if (!preg_match("/^354 /", $resp)) {
		return ['success' => 0, 'error' => "Error: $resp"];
	    }
	    fwrite($fp, "$data\r\n");
	    $resp = fgets($fp);
	    if (!preg_match("/^250 /", $resp)) {
		return ['success' => 0, 'error' => "Error: $resp"];
	    }
	    fwrite($fp, "QUIT\r\n");
	}
	else {
	    return ['success' => 0, 'error' => "Error: $resp"];
	}
    }
    fclose($fp);
    return ['success' => 1];
}

?>

