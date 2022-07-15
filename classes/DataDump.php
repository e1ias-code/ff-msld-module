<?php

class DataDump
{
    public static function dumpDataToScreen($data, $and_die = false)
    {
        echo '<pre>';
        var_dump($data);
        echo '</pre>';

        if($and_die) {
            die();
        }
    }

    public static function dumpHtmlToScreen($data, $and_die = false)
    {
        $tdata = $data;
        if(is_array($data)) {
            $tdata = implode(PHP_EOL, $data);
        }
        echo '<pre>';
        echo nl2br(htmlspecialchars($tdata));
        echo '</pre>';

        if($and_die) {
            die();
        }
    }

    public static function dumpDataToFile($data, $file_name, $and_die = false)
    {
        file_put_contents($file_name, $data);

        if($and_die) {
            die();
        }
    }

    public static function dumpJsonToScreen($data, $and_die = false)
    {
        echo '<pre>';
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        echo '</pre>';

        if($and_die) {
            die();
        }
    }

    public static function dumpJsonToFile($data, $file_name, $and_die = false)
    {
        file_put_contents($file_name, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        if($and_die) {
            die();
        }
    }

    public static function loadJsonFromFile($file_name)
    {
        return json_decode(file_get_contents($file_name), true);
    }

}