<?php
namespace ReactCassandra\Type;

class Collection
{

    public $value;

    public function __construct($value = null)
    {
        if (!is_null($value)) {
            $this->value = $value;
        }
    }

    public static function parse($binary)
    {
        throw new \ReactCassandra\Exception('Not implemented yet');
    }

    public function __toString()
    {
        return self::binary($this->value);
    }

    public static function binary($value = [])
    {
        $packet = \ReactCassandra\Protocol\FrameHelper::writeInt(count($value));
        foreach ($value as $k => $v) {
            if (is_object($v)) {
                $v = (string)$v;
            }
            switch (true) {
                case is_null($v):
                    $packet .= \ReactCassandra\Protocol\FrameHelper::writeInt(-1);
                    break;
                case !isset($v):
                    // this 'll never happens, but we have some code for this
                    $packet .= \ReactCassandra\Protocol\FrameHelper::writeInt(-2);
                    break;
                case is_int($v):
                    $v = \ReactCassandra\Protocol\FrameHelper::writeInt($v);
                    $packet .= \ReactCassandra\Protocol\FrameHelper::writeInt(strlen($v)) . $v;
                    break;
                default:
                    $packet .= \ReactCassandra\Protocol\FrameHelper::writeInt(strlen($v)) . $v;
                    break;
            }
        }
        return $packet;
    }
}