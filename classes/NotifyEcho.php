<?php

class NotifyEcho extends Notify
{

    public function send($level, $message)
    {
        if($this->isValidLevel($level)) {
            echo $message;
        }
    }

}