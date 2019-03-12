<?php

require 'inc/phpmailer/PHPMailerAutoload.php';
require_once("inc/ZabbixAPI.class.php");
include("inc/index.functions.php");
include("sendmail.conf.php");

include("config.inc.php");

function smtpmailer($to, $from, $from_name, $subject, $body, $filename) { 
 global $error;
 $mail = new PHPMailer();  
 $mail->setLanguage('it');
 
 $mail->IsSMTP();             // enable SMTP
 $mail->SMTPDebug = 0;        // debugging: 1 = errors and messages, 2 = messages only
 $mail->SMTPAuth = true;      // authentication enabled
 $mail->SMTPSecure = mailsec; // secure transfer enabled 
 $mail->Host = mailserver;
 $mail->Port = mailport; 
 $mail->Username = mailuser;  
 $mail->Password = mailpassword;           
 $mail->SetFrom($from, $from_name);
 $mail->Subject = $subject;
 $mail->Body = $body;
 $mail->AddAddress($to);
 
$mail->addAttachment($filename);

 if(!$mail->Send()) {
       echo 'Mailer Error: ' . $mail->ErrorInfo;
 return false;
 } else {
 return true;
 }
}

function recursive_return_array_value_by_key($needle, $haystack){
    $return = false;
    foreach($haystack as $key => $val){
        if(is_array($val)){
            $return = recursive_return_array_value_by_key($needle, $val);
        }
        else if($needle === $key){
            return "$val";
        }
    }
    return $return;
}

//fetch graph data host
ZabbixAPI::debugEnabled(TRUE);
ZabbixAPI::login($z_server,$z_user,$z_pass)
	or die('Unable to login: '.print_r(ZabbixAPI::getLastError(),true));
$hosts = ZabbixAPI::fetch_array('host','get',array('output'=>array('hostid','name'),'sortfield'=>'host','with_graphs'=>'1','sortfield'=>'name'))
	or die('Unable to get hosts: '.print_r(ZabbixAPI::getLastError(),true));

// for each host generate report and send mail
foreach(file(mailhosts) as $line) {
    $pieces = explode(" ", $line);
    $hostid = recursive_return_array_value_by_key($pieces[0], $hosts);
    
    $output = file_get_contents($z_server . 'zabbixpdf/createpdf.php?ReportType=host&ReportRange=last&timePeriod=Month&HostID=' . $hostid . '&GroupID=&GraphsOn=yes');

    if (!smtpmailer($pieces[1], mailuser, mailusername, mailsubject, mailbody, reportsroot . $pieces[0] . ".pdf")) {
       echo 'Mailer Error: '; //. $mail->ErrorInfo;
    } else {
      echo 'Message sent!';
    }
}
