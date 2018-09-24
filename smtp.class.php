<?php
/*
 * https://github.com/luizj/PHP-Smtp-Send-Mail/blob/master/smtp.class.php
 */
set_time_limit(0);

class Smtp
{
	var $serv  = "smtp.mail1.com";
	var $port  = "25";

	var $name  = "MyServiceName";
	var $from  = "your@mail.com";
	var $user  = "your@mail.com";
	var $pass  = "pass";

	//Addons (Optional)
	var $debug = false;
	var $use_tls = false;
	var $ssl_cabundle = '';   // Example /etc/ssl/cacert.pem
	var $ssl_passphrase = '';

	//Don't touch
	var $conn;
	var $host;
	var $auth = false;
	var $TLS = false;
	var $boundary;
	var $attachment = array();
	var $reply_to;
	var $timeout = 15;
    
	function Send($to, $subject, $msg)
	{
		$this->conn = @fsockopen($this->serv, $this->port, $errno, $errstr, $this->timeout);
		if($this->conn)
		{
			if($this->debug)echo "Connection OK"."\x0D\x0A";
		}else{
			if($this->debug)echo "Connection Fail"."\x0D\x0A";
			return false;
		}
		
		if(substr(PHP_OS,0,3) != 'WIN'){
			stream_set_timeout($this->conn, $this->timeout, 0);
		}

		$this->boundary = md5(time());

		if(!$this->wRecv("220"))return false; //INIT

		$this->Put("EHLO ".$this->serverHostname());
		if(!$this->wRecv("250"))return false; //HELLO

		if($this->use_tls && $this->TLS && extension_loaded('openssl'))
		{
			$this->startTLS();
		}
        
		if($this->auth)
		{
			$this->Put("AUTH LOGIN");
			if(!$this->wRecv("334"))return false; // ok
			$this->Put(base64_encode($this->user));
			if(!$this->wRecv("334"))return false; // ok
			$this->Put(base64_encode($this->pass));
			if(!$this->wRecv("235"))return false; // authenticated
		}
    
		$this->Put("MAIL FROM: <".$this->from.">");
		if(!$this->wRecv("250"))return false; // ok
		
		$tox = explode(",",$to);
        	$multiple_to = "";
		for($i=0; $i<sizeof($tox); $i++){
			if($i>0)$multiple_to .= ",";
			$multiple_to .= "<".$tox[$i].">";
			$this->Put("RCPT TO: <".$tox[$i].">");
			if(!$this->wRecv("250"))return false; // ok
		}
		$this->Put("DATA");
		$this->Put($this->toHeader($multiple_to, $subject));

		$msg = $this->minimize_output($msg);
		$this->Message_PlainText($msg);
		$this->Message_Html($msg);
		$this->setAttachment();

		$this->Put(".");
		while($this->wRecv("250")!=true){} // ok (<- 354 End data with <CR><LF>.<CR><LF>)
		$this->Close();
		return true;
	}
	
	function startTLS()
	{
		$this->Put("STARTTLS");
		if(!$this->wRecv("220"))return false; //HELLO

		stream_context_set_option($this->conn, 'ssl', 'verify_host', false);
		stream_context_set_option($this->conn, 'ssl', 'verify_peer', false);
		stream_context_set_option($this->conn, 'ssl', 'verify_peer_name', false);
		stream_context_set_option($this->conn, 'ssl', 'allow_self_signed', true);
		if($this->ssl_cabundle!="")stream_context_set_option($this->conn, 'ssl', 'local_cert', $this->ssl_cabundle);
		if($this->ssl_passphrase!="")stream_context_set_option($this->conn, 'ssl', 'passphrase', $this->ssl_passphrase);

		// Begin encrypted connection
		if (!stream_socket_enable_crypto(
			$this->conn,
			true,
			STREAM_CRYPTO_METHOD_TLS_CLIENT
		)){
			return false;
		}

		$this->Put("EHLO ".$this->serverHostname());
		if(!$this->wRecv("250"))return false; //HELLO
		return true;
	}
	
	function serverHostname()
	{
		$result = 'localhost.localdomain';
		if (isset($_SERVER) and array_key_exists('SERVER_NAME', $_SERVER) and !empty($_SERVER['SERVER_NAME'])) {
			$result = $_SERVER['SERVER_NAME'];
		} elseif (function_exists('gethostname') && gethostname() !== false) {
			$result = gethostname();
		} elseif (php_uname('n') !== false) {
			$result = php_uname('n');
		}
		if(filter_var($result, FILTER_VALIDATE_IP)) {
			$result = "[".$result."]";
		}
		return $result;
	}
	
	function toHeader($to, $subject)
	{
		$header  = "Message-Id: <". date('YmdHis').".". md5(microtime()). strrchr($this->from,'@') ."> \r\n";
		$header .= "From: \"".$this->name."\" <".$this->from.">\r\n";
		if($this->reply_to != ""){
			$header .= "Reply-To: <".$this->reply_to.">\r\n";
		}
		$header .= "To: ".$to."\r\n";

		if(function_exists('mb_encode_mimeheader')){
			$header .= "Subject: ".mb_encode_mimeheader($subject,"UTF-8")."\r\n";
		}else{
			$header .= "Subject: =?UTF-8?B?".base64_encode($subject)."?=\r\n";
		}

		$header .= "Date: ". date('D, d M Y H:i:s O') ."\r\n";
		$header .= "MIME-Version: 1.0\r\n";
		$header .= "X-Mailer: PHPMail\r\n";
		if(sizeof($this->attachment) > 0){
			$header .= "Content-Type: multipart/mixed; boundary=\"".$this->boundary."_\"\r\n\r\n";
			$header .= "--".$this->boundary."_\r\n";
		}

		$header .= "Content-Type: multipart/alternative; boundary=\"".$this->boundary."\"\r\n";

		return $header;
	}
	
	function Put($value)
	{
		if($this->debug)echo "-> ".$value."\x0D\x0A";
		return fputs($this->conn, $value."\r\n", strlen($value."\r\n"));
	}
	
	function wRecv($cod)
	{
		$ret = false;
		while(!feof($this->conn))
		{
			$c = fgets($this->conn);
			if(!$c || $c=="")return $ret;
			if($this->debug)echo "<- ".$c;
			if(substr($c,0, 3) == $cod)
			{
				if($cod == "250"){//Addons
					if(substr($c,4, 10) == "AUTH LOGIN")$this->auth = true;
					if(substr($c,4, 10) == "AUTH PLAIN")$this->auth = true;
					if(substr($c,4, 8)  == "STARTTLS")  $this->TLS  = true;
				}
				if($cod == "220"){//EHLO
					$exp = explode(" ", $c);
					$this->host = trim($exp[1]);
				}
				$ret = true;
			}
			if(substr($c,3, 1) != "-")return $ret;
		}
		return $ret;
	}
	
	function Close()
	{
		$this->Put("QUIT");
		return fclose($this->conn);
	}
	
	function minimize_output($b){//Minimiza o Html
		$s = array(
			'/\>[^\S ]+/su',  // strip whitespaces after tags, except space
			'/[^\S ]+\</su',  // strip whitespaces before tags, except space
			'/(\s)+/su'       // shorten multiple whitespace sequences
		);
		$r = array(
			'>',
			'<',
			'\\1'
		);
		$b = preg_replace($s, $r, $b);
		return $b;
	}
	
	function Message_PlainText($message){
		$message = preg_replace('/(<(script|style)\b[^>]*>).*?(<\/\2>)/s', "$1$3", $message);
		$this->Put("--".$this->boundary);
		$content  = "Content-Type: Text/Plain; charset=UTF-8\r\n\r\n";
		$content .= strip_tags(preg_replace('#<br\s*/?>#i', chr(13).chr(10), $message))."\r\n";
		$this->Put($content);
	}
	
	function Message_Html($message){
		$this->Put("--".$this->boundary);
		$content  = "Content-Type: Text/HTML; charset=UTF-8\r\n\r\n";
		if (strpos($message,'<html') !== false){
			$content .= $message."\r\n";
		}else{
            $content .= $this->minimize_output('<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title>'.$this->name.'</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
</head>
<body style="margin: 0; padding: 0;">
<table border="0" cellpadding="0" cellspacing="0"><tr><td>'.$message.'</td></tr></table>
</body>
</html>
');
		}
		$this->Put($content);
		$this->Put("--".$this->boundary."--");
	}
	
	function setAttachment(){
		for($i=0; $i<sizeof($this->attachment);$i++){
			$this->Put("\r\n--".$this->boundary."_");
			$content  = "Content-Type: ".$this->_mime_types($this->attachment[$i][0])."; name=\"".$this->attachment[$i][0]."\"\r\n";
			$content .= "Content-Disposition: attachment; filename=\"".$this->attachment[$i][0]."\"\r\n";
			$content .= "Content-Transfer-Encoding: base64\r\n\r\n";
			$content .= chunk_split(base64_encode($this->attachment[$i][1]));
			$this->Put($content);
		}
		if($i>0)$this->Put("--".$this->boundary."_--");
	}
	
	function _mime_types($filename){
		$qpos = strpos($filename, '?');
		if(false !== $qpos)$filename = substr($filename, 0, $qpos);
		$exp = explode(".", $filename);
		$ext = strtolower(end($exp));
		$mimes = array(
			'xl' => 'application/excel',
			'js' => 'application/javascript',
			'hqx' => 'application/mac-binhex40',
			'cpt' => 'application/mac-compactpro',
			'bin' => 'application/macbinary',
			'doc' => 'application/msword',
			'word' => 'application/msword',
			'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'xltx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.template',
			'potx' => 'application/vnd.openxmlformats-officedocument.presentationml.template',
			'ppsx' => 'application/vnd.openxmlformats-officedocument.presentationml.slideshow',
			'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
			'sldx' => 'application/vnd.openxmlformats-officedocument.presentationml.slide',
			'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'dotx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.template',
			'xlam' => 'application/vnd.ms-excel.addin.macroEnabled.12',
			'xlsb' => 'application/vnd.ms-excel.sheet.binary.macroEnabled.12',
			'class' => 'application/octet-stream',
			'dll' => 'application/octet-stream',
			'dms' => 'application/octet-stream',
			'exe' => 'application/octet-stream',
			'lha' => 'application/octet-stream',
			'lzh' => 'application/octet-stream',
			'psd' => 'application/octet-stream',
			'sea' => 'application/octet-stream',
			'so' => 'application/octet-stream',
			'oda' => 'application/oda',
			'pdf' => 'application/pdf',
			'ai' => 'application/postscript',
			'eps' => 'application/postscript',
			'ps' => 'application/postscript',
			'smi' => 'application/smil',
			'smil' => 'application/smil',
			'mif' => 'application/vnd.mif',
			'xls' => 'application/vnd.ms-excel',
			'ppt' => 'application/vnd.ms-powerpoint',
			'wbxml' => 'application/vnd.wap.wbxml',
			'wmlc' => 'application/vnd.wap.wmlc',
			'dcr' => 'application/x-director',
			'dir' => 'application/x-director',
			'dxr' => 'application/x-director',
			'dvi' => 'application/x-dvi',
			'gtar' => 'application/x-gtar',
			'php3' => 'application/x-httpd-php',
			'php4' => 'application/x-httpd-php',
			'php' => 'application/x-httpd-php',
			'phtml' => 'application/x-httpd-php',
			'phps' => 'application/x-httpd-php-source',
			'swf' => 'application/x-shockwave-flash',
			'sit' => 'application/x-stuffit',
			'tar' => 'application/x-tar',
			'tgz' => 'application/x-tar',
			'xht' => 'application/xhtml+xml',
			'xhtml' => 'application/xhtml+xml',
			'zip' => 'application/zip',
			'mid' => 'audio/midi',
			'midi' => 'audio/midi',
			'mp2' => 'audio/mpeg',
			'mp3' => 'audio/mpeg',
			'm4a' => 'audio/mp4',
			'mpga' => 'audio/mpeg',
			'aif' => 'audio/x-aiff',
			'aifc' => 'audio/x-aiff',
			'aiff' => 'audio/x-aiff',
			'ram' => 'audio/x-pn-realaudio',
			'rm' => 'audio/x-pn-realaudio',
			'rpm' => 'audio/x-pn-realaudio-plugin',
			'ra' => 'audio/x-realaudio',
			'wav' => 'audio/x-wav',
			'mka' => 'audio/x-matroska',
			'bmp' => 'image/bmp',
			'gif' => 'image/gif',
			'jpeg' => 'image/jpeg',
			'jpe' => 'image/jpeg',
			'jpg' => 'image/jpeg',
			'png' => 'image/png',
			'tiff' => 'image/tiff',
			'tif' => 'image/tiff',
			'webp' => 'image/webp',
			'heif' => 'image/heif',
			'heifs' => 'image/heif-sequence',
			'heic' => 'image/heic',
			'heics' => 'image/heic-sequence',
			'eml' => 'message/rfc822',
			'css' => 'text/css',
			'html' => 'text/html',
			'htm' => 'text/html',
			'shtml' => 'text/html',
			'log' => 'text/plain',
			'text' => 'text/plain',
			'txt' => 'text/plain',
			'rtx' => 'text/richtext',
			'rtf' => 'text/rtf',
			'vcf' => 'text/vcard',
			'vcard' => 'text/vcard',
			'ics' => 'text/calendar',
			'xml' => 'text/xml',
			'xsl' => 'text/xml',
			'wmv' => 'video/x-ms-wmv',
			'mpeg' => 'video/mpeg',
			'mpe' => 'video/mpeg',
			'mpg' => 'video/mpeg',
			'mp4' => 'video/mp4',
			'm4v' => 'video/mp4',
			'mov' => 'video/quicktime',
			'qt' => 'video/quicktime',
			'rv' => 'video/vnd.rn-realvideo',
			'avi' => 'video/x-msvideo',
			'movie' => 'video/x-sgi-movie',
			'webm' => 'video/webm',
			'mkv' => 'video/x-matroska',
		);
		if(array_key_exists($ext, $mimes))return $mimes[$ext];
		return 'application/octet-stream';
	}
}

function send_mail($to, $subject, $msg, $attachment=array(), $reply_to="")
{
	$smtp = new Smtp();
	$smtp->attachment = is_array($attachment)?$attachment:array();
	$smtp->reply_to = $reply_to;
	return $smtp->Send($to, $subject, $msg);
}
//send_mail("mytestmail@mywebsite.com", "subject", "message");
//or
//send_mail("mytestmail@mywebsite.com", "subject", "message", array(array("file.jpg","binary_of_jpg")));
//or
//send_mail("mytestmail@mywebsite.com", "subject", "message", NULL, "replymail@mywebsite.com");
?>
