<?
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
    var $use_tls = false;
    
    //Don't touch
    var $conn;
    var $host;
    var $auth = false;
    var $TLS = false;

    function Send($to, $subject, $msg)
    {
        if(!$this->wRecv("220"))return false; //INIT
        $this->Put("EHLO <".$this->host.">");
        if(!$this->wRecv("250"))return false; //HELLO

        if($this->use_tls)$this->startTLS();

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
        $this->Put($msg);
        $this->Put(".");
        if(!$this->wRecv("250"))return true; // ok  354 tbm eh true (<- 354 End data with <CR><LF>.<CR><LF>)
        $this->Close();
        return true;
    }

    function startTLS()
    {
        if (!$this->TLS)return false;
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
        return true;
    }

    function toHeader($to, $subject)
    {
        $header = "Message-Id: <". date('YmdHis').".". md5(microtime()). strrchr($this->from,'@') ."> \r\n";
        $header .= "From: \"{$this->name}\" <".$this->from.">\r\n";
        $header .= "To: <".$to.">\r\n";
        $header .= "Subject: ".$subject."\r\n";
        $header .= "Date: ". date('D, d M Y H:i:s O') ."\r\n";
        $header .= "MIME-Version: 1.0\r\n";
        $header .= "X-Mailer: PHPMail\r\n";
        $header .= "Content-Type: Text/HTML; charset=UTF-8\r\n";
        return $header;
    }

    function Put($value)
    {
        if($this->debug)
        {
            echo "-> ".$value."\x0D\x0A";
        }
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
    $header = "Message-Id: <". date('YmdHis').".". md5(microtime()). strrchr($smtp->from,'@') ."> \r\n";
    $header .= "From: \"{$smtp->name}\" <".$smtp->from.">\n";
    $header .= "Date: ". date('D, d M Y H:i:s O') ."\r\n";
    $header .= "MIME-Version: 1.0\r\n";
    $header .= "X-Mailer: PHPMail\r\n";
    $header .= "Content-Type: Text/HTML; charset=UTF-8\r\n";
    @mail($to, $subject, $msg, $header);
    return false;
}

//send_mail("mytestmail@gmail.com", "subject", "message");
?>
