<?php

abstract class Notify
{
    const DEBUG = 1;
    const INFO = 2;
    const WARNING = 4;
    const ERROR = 8;

    protected $level = 2;

    public function setLevel($level)
    {
        $this->level = $level;
    }

    public function getLevel()
    {
        return $this->level;
    }

    protected function isValidLevel($level)
    {
        if($level >= $this->level) {
            return true;
        } else {
            return false;
        }
    }

    abstract public function send($level, $message);

}