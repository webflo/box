<?php

namespace HumbugBox383\KevinGH\RequirementChecker;

/**
@symfony
*/
class Terminal
{
    private static $width;
    private static $height;
    private static $stty;
    public function getWidth()
    {
        $width = \getenv('COLUMNS');
        if (\false !== $width) {
            return (int) \trim($width);
        }
        if (null === self::$width) {
            self::initDimensions();
        }
        return self::$width ?: 80;
    }
    public function getHeight()
    {
        $height = \getenv('LINES');
        if (\false !== $height) {
            return (int) \trim($height);
        }
        if (null === self::$height) {
            self::initDimensions();
        }
        return self::$height ?: 50;
    }
    public static function hasSttyAvailable()
    {
        if (null !== self::$stty) {
            return self::$stty;
        }
        \exec('stty 2>&1', $output, $exitcode);
        return self::$stty = 0 === $exitcode;
    }
    private static function initDimensions()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            if (\preg_match('/^(\\d+)x(\\d+)(?: \\((\\d+)x(\\d+)\\))?$/', \trim(\getenv('ANSICON')), $matches)) {
                self::$width = (int) $matches[1];
                self::$height = isset($matches[4]) ? (int) $matches[4] : (int) $matches[2];
            } elseif (!self::hasVt100Support() && self::hasSttyAvailable()) {
                self::initDimensionsUsingStty();
            } elseif (null !== ($dimensions = self::getConsoleMode())) {
                self::$width = (int) $dimensions[0];
                self::$height = (int) $dimensions[1];
            }
        } else {
            self::initDimensionsUsingStty();
        }
    }
    private static function hasVt100Support()
    {
        return \function_exists('sapi_windows_vt100_support') && \sapi_windows_vt100_support(\fopen('php://stdout', 'wb'));
    }
    private static function initDimensionsUsingStty()
    {
        if ($sttyString = self::getSttyColumns()) {
            if (\preg_match('/rows.(\\d+);.columns.(\\d+);/i', $sttyString, $matches)) {
                self::$width = (int) $matches[2];
                self::$height = (int) $matches[1];
            } elseif (\preg_match('/;.(\\d+).rows;.(\\d+).columns/i', $sttyString, $matches)) {
                self::$width = (int) $matches[2];
                self::$height = (int) $matches[1];
            }
        }
    }
    private static function getConsoleMode()
    {
        $info = self::readFromProcess('mode CON');
        if (null === $info || !\preg_match('/--------+\\r?\\n.+?(\\d+)\\r?\\n.+?(\\d+)\\r?\\n/', $info, $matches)) {
            return null;
        }
        return array((int) $matches[2], (int) $matches[1]);
    }
    private static function getSttyColumns()
    {
        return self::readFromProcess('stty -a | grep columns');
    }
    private static function readFromProcess($command)
    {
        if (!\function_exists('proc_open')) {
            return null;
        }
        $descriptorspec = array(1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
        $process = \proc_open($command, $descriptorspec, $pipes, null, null, array('suppress_errors' => \true));
        if (!\is_resource($process)) {
            return null;
        }
        $info = \stream_get_contents($pipes[1]);
        \fclose($pipes[1]);
        \fclose($pipes[2]);
        \proc_close($process);
        return $info;
    }
}
