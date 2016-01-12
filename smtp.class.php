<?
/*
 * https://github.com/luizj/PHP-Smtp-Send-Mail/blob/master/smtp.class.php
 */
error_reporting(15);
set_time_limit(0);

class Smtp
{
    var $serv  = "smtp.mail1.com";
 
    var $name  = "MyServiceName";
    var $from  = "your@mail.com";
    var $user  = "your@mail.com";
    var $pass  = "pass";

    //Addons
    var $debug = false;
    var $use_tls = true;
    
    //Don't touch
    var $conn;
    var $host;
    var $auth = false;
    var $TLS = false;
    var $boundary;
  
    function Send($to, $subject, $msg)
    {
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
        $this->Put("RCPT TO: <".$to.">");
        if(!$this->wRecv("250"))return false; // ok
        $this->Put("DATA");
        $this->Put($this->toHeader($to, $subject));
    
        $this->Put("--".$this->boundary);
        $this->Put($this->Message_PlainText($msg));
        $this->Put("--".$this->boundary);
        $this->Put($this->Message_Html($msg));
        $this->Put("--".$this->boundary."--");
    
        $this->Put(".");
        if(!$this->wRecv("250"))return true; // ok (<- 354 End data with <CR><LF>.<CR><LF>)
        $this->Close();
        return true;
    }
  
    function startTLS()
    {
        $this->Put("STARTTLS");
        if(!$this->wRecv("220"))return false; //HELLO

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
            $result = $this->serv;
        }
        return $result;
    }
  
    function toHeader($to, $subject)
    {
        $header  = "Message-Id: <". date('YmdHis').".". md5(microtime()). strrchr($this->from,'@') ."> \r\n";
        $header .= "From: \"".$this->name."\" <".$this->from.">\r\n";
        $header .= "To: <".$to.">\r\n";
        
        if(function_exists('mb_encode_mimeheader')){
        	$header .= "Subject: ".mb_encode_mimeheader($subject,"UTF-8")."\r\n";
    	}else{
        	$header .= "Subject: =?UTF-8?B?".base64_encode($subject)."?=\r\n";
        }
        
        $header .= "Date: ". date('D, d M Y H:i:s O') ."\r\n";
        $header .= "MIME-Version: 1.0\r\n";
        $header .= "X-Mailer: PHPMail\r\n";
        $header .= "Content-Type: multipart/alternative; boundary=\"".$this->boundary."\"\r\n";
        return $header;
    }
  
    function Put($value)
    {
        if($this->debug)echo "-> ".$value."\x0D\x0A";
        return fputs($this->conn, $value."\r\n");
    }
  
    function wRecv($cod)
    {
        $ret = false;
        while(!feof($this->conn))
        {
            $c = fgets($this->conn);
            if($this->debug)echo "<- ".$c;
            if(substr($c,0, 3) == $cod)
            {
                if($cod == "250"){//Addons
                if(substr($c,4, 10) == "AUTH LOGIN")$this->auth = true;
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
        while (!feof ($this->conn))
        {
            echo "<- ".fgets($this->conn) . "\x0D";
            $c = fgets($this->conn);
            if($this->debug)echo "<- ".$c;
            if(substr($c,3, 1) != "-"){return;}
        }
        $this->Put("QUIT");
        return fclose($this->conn);
    }
  
  function Message_PlainText($message){
    $content  = "Content-Type: Text/Plain; charset=UTF-8\r\n\r\n";
    $content .= strip_tags(preg_replace('#<br\s*/?>#i', chr(13).chr(10), $message))."\r\n";
    return $content;
  }
  
  function Message_Html($message){
    $content  = "Content-Type: Text/HTML; charset=UTF-8\r\n\r\n";
    if (strpos($message,'<html') !== false){
      $content .= $message."\r\n";
    }else{
      $content .= '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
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
';
    }
    return $content;
  }
}

function send_mail($to, $subject, $msg)
{
    $smtp = new Smtp();
    //Server
    $smtp->conn = @fsockopen($smtp->serv, 25, $errno, $errstr, 5);
    if($smtp->conn)
    {
        if($smtp->debug)echo "Connect 1"."\x0D\x0A";
        if(!$smtp->Send($to, $subject, $msg))
        {
            if($smtp->debug)echo "1 Fail"."\x0D\x0A";
        }else{
            return true;
        }
    }
    //If server down
    if($smtp->debug)echo "Connect Mail()"."\x0D\x0A";
    $header  = "Message-Id: <". date('YmdHis').".". md5(microtime()). strrchr($smtp->from,'@') ."> \r\n";
    $header .= "From: \"".$smtp->name."\" <".$smtp->from.">\n";
    $header .= "Date: ". date('D, d M Y H:i:s O') ."\r\n";
    $header .= "MIME-Version: 1.0\r\n";
    $header .= "X-Mailer: PHPMail\r\n";
    $header .= "Content-Type: Text/HTML; charset=UTF-8\r\n";
    @mail($to, $subject, $msg, $header);
    return false;
}
//send_mail("mytestmail@gmail.com", "subject", "message");
?>
