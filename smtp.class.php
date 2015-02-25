<?
error_reporting(15);
set_time_limit(10);

class Smtp
{
    var $conn;
    var $host;

    var $serv1 = "smtp.mail1.com";
    var $serv2 = "smtp.mail2.com";
    var $serv3 = "smtp.mail3.com";
 
    var $nome  = "mail.com";
    var $from  = "<your@mail.com>";
    var $user  = "your@mail.com";
    var $pass  = "pass";

    var $debug = false;
    
    //Addons (No Change)
    var $auth = false;

    function Send($to, $subject, $msg)
    {
        if(!$this->wRecv("220"))return false; //INIT

        $this->Put("EHLO <".$this->host.">");
        if(!$this->wRecv("250"))return false; //HELLO

        if($this->auth)
        {
            $this->Put("AUTH LOGIN");
            if(!$this->wRecv("334"))return false; // ok

            $this->Put(base64_encode($this->user));
            if(!$this->wRecv("334"))return false; // ok

            $this->Put(base64_encode($this->pass));
            if(!$this->wRecv("235"))return false; // authenticated
        }
        
        $this->Put("MAIL FROM: ".$this->from);
        if(!$this->wRecv("250"))return false; // ok

        $this->Put("RCPT TO: <".$to.">");
        if(!$this->wRecv("250"))return false; // ok
        
        $this->Put("DATA");
        $this->Put($this->toHeader($to, $subject));
        $this->Put($msg);
        $this->Put(".");
        if(!$this->wRecv("250"))return true; // ok  354 tbm eh true (<- 354 End data with <CR><LF>.<CR><LF>)
        $this->Close();
        //if(isset($this->conn)){
            return true;
        //}else{
        //    return false;
        //}
    }

    function Put($value)
    {
        if($this->debug)
        {
            echo "-> ".$value."\x0D\x0A";
        }
        return fputs($this->conn, $value . "\r\n");

    }

    function toHeader($to, $subject)
    {
        $header = "Message-Id: <". date('YmdHis').".". md5(microtime())."@". $this->nome ."> \r\n";
        $header .= "From: \"{$this->nome}\" ".$this->from."\r\n";
        $header .= "To: <".$to.">\r\n";
        $header .= "Subject: ".$subject."\r\n";
        $header .= "Date: ". date('D, d M Y H:i:s O') ."\r\n";
        $header .= "MIME-Version: 1.0\r\n";
	$header .= "X-Mailer: PHPMail\r\n";
        $header .= "Content-Type: Text/HTML; charset=UTF-8\r\n";
        return $header;
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
                //Addons
                if(substr($c,4, 4) == "AUTH")$this->auth = true;
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

    //Server 1
    $smtp->host = $smtp->serv1;
    $smtp->conn = @fsockopen($smtp->host, 25, $errno, $errstr, 5);
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

    //Server 2
    $smtp->host = $smtp->serv2;
    $smtp->conn = @fsockopen($smtp->host, 25, $errno, $errstr, 5);
    if($smtp->conn)
    {
        if($smtp->debug)echo "Connect 2"."\x0D\x0A";
        if(!$smtp->Send($to, $subject, $msg))
        {
            if($smtp->debug)echo "2 Fail"."\x0D\x0A";
        }else{
            return true;
        }
    }
    
    //Server 3
    $smtp->host = $smtp->serv3;
    $smtp->conn = @fsockopen($smtp->host, 25, $errno, $errstr, 5);
    if($smtp->conn)
    {
        if($smtp->debug)echo "Connect 3"."\x0D\x0A";
        if(!$smtp->Send($to, $subject, $msg))
        {
            if($smtp->debug)echo "3 Fail"."\x0D\x0A";
        }else{
            return true;
        }
    }

    //If 3 servers is down
    if($smtp->debug)echo "Connect Mail()"."\x0D\x0A";
    $headers = "From: ".$smtp->from."\n";
    $header .= "Date: ". date('D, d M Y H:i:s O') ."\r\n";
    $header .= "MIME-Version: 1.0\r\n";
    $header .= "X-Mailer: PHPMail\r\n";
    $header .= "Content-Type: Text/HTML; charset=UTF-8\r\n";
    @mail($to, $subject, $msg, $headers);
    return false;
}

//send_mail("mytestmail@gmail.com", "subject", "message");
?>
