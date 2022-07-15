<?php

class NotifyLog extends Notify
{
    protected $file;
    protected $fileName;

    public function __construct($file_name)
    {
        $this->fileName = $file_name;
        $this->file = fopen($this->fileName, 'a');
    }

    public static function withNameFromPath($path)
    {
        return new static(pathinfo($path)['filename'] . '.log');
    }

    public function __destruct()
    {
        fclose($this->file);
    }

    public function send($level, $message)
    {
        $str_level = '';
        switch($level) {
            case 1:
                $str_level = 'DEBUG';
                break;
            case 2:
                $str_level = 'INFO';
                break;
            case 4:
                $str_level = 'WARN';
                break;
            case 8:
                $str_level = 'ERROR';
                break;
            default:
                $str_level = 'LEVEL ' . $level;
            
        }
        if($this->isValidLevel($level)) {
            fwrite($this->file, $str_level . ': ' . $message . "\n");
        }
    }

}