<?php

namespace Hcode;

use Rain\Tpl;

class Mailer
{
    public const USERNAME = 'golservices_msg@multprocessing.com.br';
    public const PASSWORD = 'mpil2087';
    public const NAME_FROM = 'Hcode Store';
    private $mail;

    public function __construct($toAddress, $toName, $subject, $tplName, $data = [])
    {
        $config = [
            'tpl_dir' => $_SERVER['DOCUMENT_ROOT'] . '/views/email/',
            'cahce_dir' => $_SERVER['DOCUMENT_ROOT'] . '/views-cache/',
            'debug' => false,
        ];

        Tpl::configure($config);

        $tpl = new Tpl;

        foreach ($data as $key => $value) {
            $tpl->assign($key, $value);
        }

        $html = $tpl->draw($tplName, true);


        $this->mail = new \PHPMailer();
        $this->mail->isSMTP();
        $this->mail->SMTPDebug = 0;
        $this->mail->Debugoutput = 'html';
        $this->mail->Host = 'smtp.gmail.com';
        $this->mail->Port = 587;
        $this->mail->SMTPSecure = 'tls';
        $this->mail->SMTPAuth = true;
        $this->mail->Username = Mailer::USERNAME;
        $this->mail->Password = Mailer::PASSWORD;
        $this->mail->setFrom(Mailer::USERNAME, Mailer::NAME_FROM);
        $this->mail->addAddress($toAddress, $toName);
        $this->mail->Subject = $subject;
        $this->mail->msgHTML($html);
        $this->mail->AltBody = 'This is a plain-text message body';
    }

    public function send()
    {
        return $this->mail->send();
    }
}
