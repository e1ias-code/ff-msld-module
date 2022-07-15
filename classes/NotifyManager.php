<?php

class NotifyManager
{
    protected $channels;

    public function __construct(...$channels)
    {
        $this->channels = $channels;
    }

    public function send($level, $message)
    {
        foreach($this->channels as $channel) {
            $channel->send($level, $message);
        }
    }
}