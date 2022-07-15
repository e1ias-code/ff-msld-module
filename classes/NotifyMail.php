<?php

class NotifyMail extends Notify
{
    protected $title;
    protected $email;
    protected $body;
    protected $headers;

    public function __construct()
    {
        $this->title = 'Ошибка';
        $this->body = [];
        $this->email = '';
        $this->headers = [
            'Content-type' => 'text/plain; charset=utf-8'
        ];
    }

    public function setTitle($title)
    {
        $this->title = $title;
    }

    public function setEmail($email)
    {
        $this->email = $email;
    }

    public function addParagraph($text)
    {
        $this->body[] = $text;
    }

    public function send($level, $message)
    {
        if(!$this->isValidLevel($level)) {
            return;
        }
        if(empty($this->email)) {
            return;
        }
        $text = $this->body;
        $text[] = $message;
        mail($this->email, $this->title, implode('\n\n', $text), $this->headers);
    }

}