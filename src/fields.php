<?php
namespace PhpProtobuf;

abstract class BaseField
{
    public $type;

    public $name = '';

    public $wire = 0;

    public $required = false;

    public $repeated = false;

    public static function instance($config)
    {
        $class = get_called_class();
        $instance = new $class();
        $instance->name = $config['name'];
        $instance->required = $config['required'] ?? false;
        $instance->repeated = $config['repeated'] ?? false;
        return $instance;
    }

    public abstract function decode($value);

    public abstract function encode($value);

    public function encodeField($index, $value)
    {
        if (!$this->repeated) {
            return array_merge([($index << 3) + $this->wire], $this->encode($value));
        }

        if (!is_array($value)) {
            throw new EncodeException("field '{$this->name}' defined as repeated, but value_to_encode is not an array");
        }
        $bytes = [];

        // repeated & non-scalar
        if (!in_array($this->type, Scheme::$scalar_types)) {
            foreach ($value as $v) {
                $bytes = array_merge($bytes, [($index << 3) + $this->wire], $this->encode($v));
            }
            return $bytes;
        }

        // repeated & scalar
        foreach ($value as $v) {
            $bytes = array_merge($bytes, $this->encode($v));
        }
        $bytes = array_merge(
            [($index << 3) + Scheme::WIRE_LENGTH_DELIMITED],
            $this->encode_varint(count($bytes)),
            $bytes
        );
        return $bytes;
    }

    public function merge($old, $new)
    {
        if ($this->repeated) {
            // for repeated fields, concate them
            return $old ? array_merge($old, [$new]) : [$new];
        } else {
            // for embeded fields, see EmbededField::merge()
            // otherwise, $new take place
            return $new;
        }
    }

    protected function encode_varint($n)
    {
        $bytes = [];
        $i = 0;
        do {
            $t = $n & 127;
            $n = $n >> 7;
            if ($i > 0) {
                $bytes[$i-1] += 128;
            }
            $bytes[$i++] = $t;
        } while ($n > 0);
        return $bytes;
    }
    
    protected function encode_zigzag($n)
    {
        $n = ($n > 0) ? (2 * $n) : (-2 * $n - 1);
        return $this->encode_varint($n);
    }
}

class IntField extends BaseField
{
    public $type = Scheme::TYPE_INT;

    public $wire = Scheme::WIRE_VARINT;

    public function decode($bytes)
    {
        $n = 0;
        $len = count($bytes);
        if ($len === 10 && ($bytes[$len-1] === 1)) {
            // negative
            array_pop($bytes);
            foreach ($bytes as $i => $byte) {
                $n += (~$byte & 0x7F) << ($i * 7);
            }
            return -1 - $n;
        }
        foreach ($bytes as $i => $byte) {
            $n += ($byte & 0x7F) << ($i * 7);
        }
        return $n;
    }

    public function encode($value)
    {
        if (!is_int($value)) {
            throw new EncodeException("int field '{$this->name}' is unable to encode ". gettype($value) ." values");
        }
        if ($value > 0) {
            return $this->encode_varint($value);
        }
        // todo: optimize me
        $ret = [];
        $packed = pack('q', $value);
        $buffer = $buffer_bits = 0;
        for ($i = 0, $len = strlen($packed); $i < $len;) {
            if ($buffer_bits < 7) {
                $buffer += ord($packed[$i++]) << $buffer_bits;
                $buffer_bits += 8;
            }
            $ret[] = ($buffer & 0x7F) | 0x80;
            $buffer = $buffer >> 7;
            $buffer_bits -= 7;
        }
        $ret[] = $buffer;
        return $ret;
    }
}

class UIntField extends BaseField
{
    public $type = Scheme::TYPE_UINT;

    public $wire = Scheme::WIRE_VARINT;

    public function decode($bytes)
    {
        $n = 0;
        foreach ($bytes as $i => $byte) {
            $n += ($byte & 0x7F) << ($i * 7);
        }
        return $n;
    }

    public function encode($value)
    {
        if (!is_int($value)) {
            throw new EncodeException("uint field '{$this->name}' is unable to encode ". gettype($value) ." values");
        }
        if ($value < 0) {
            throw new EncodeException("uint field '{$this->name}' is unable to encode negative value: $value");
        }
        return $this->encode_varint($value);
    }
}

class SIntField extends BaseField
{
    public $type = Scheme::TYPE_SINT;

    public $wire = Scheme::WIRE_VARINT;

    public function decode($bytes)
    {
        $n = 0;
        foreach ($bytes as $i => $byte) {
            $n += ($byte & 0x7F) << ($i * 7);
        }
        $is_negative = $n & 1;
        return $is_negative ? -1 - ($n >> 1) : $n >> 1;
    }

    public function encode($value)
    {
        if (!is_int($value)) {
            throw new EncodeException("sint field '{$this->name}' is unable to encode ". gettype($value) ." values");
        }
        $value = ($value >= 0) ? (2 * $value) : (-2 * $value - 1);
        return $this->encode_varint($value);
    }
}

class BoolField extends BaseField
{
    public $type = Scheme::TYPE_BOOL;

    public $wire = Scheme::WIRE_VARINT;

    public function decode($bytes)
    {
        return empty($bytes) ? false : true;
    }

    public function encode($value)
    {
        if (!is_bool($value)) {
            throw new EncodeException("bool field '{$this->name}' is unable to encode ". gettype($value) ." values");
        }
        return $value ? [1] : [];
    }
}

class StringField extends BaseField
{
    public $type = Scheme::TYPE_STRING;

    public $wire = Scheme::WIRE_LENGTH_DELIMITED;

    public function decode($bytes)
    {
        $i = 0;
        do {
            $b = $bytes[$i++];
        } while (($b & 0x80) === 0x80);
        $bytes = array_slice($bytes, $i);
        return implode(array_map("chr", $bytes));
    }

    public function encode($value)
    {
        if (!is_string($value)) {
            throw new EncodeException("string field '{$this->name}' is unable to encode ". gettype($value) ." values");
        }
        $ret = unpack('C*', $value);
        $ret = array_merge($this->encode_varint(count($ret)), $ret);
        return $ret;
    }
}

class FloatField extends BaseField
{
    public $type = Scheme::TYPE_FLOAT;

    public $wire = Scheme::WIRE_32BIT;

    public function decode($bytes)
    {
        $unpacked = unpack('f', pack('C*', $bytes[0], $bytes[1], $bytes[2], $bytes[3]));
        return $unpacked[1];
    }

    public function encode($value)
    {
        if (!is_float($value)) {
            throw new EncodeException("float field '{$this->name}' is unable to encode ". gettype($value) ." values");
        }
        return array_slice(unpack('C*', pack('f', $value)), 0);
    }
}

class DoubleField extends BaseField
{
    public $type = Scheme::TYPE_DOUBLE;

    public $wire = Scheme::WIRE_64BIT;

    public function decode($bytes)
    {
        $params = array_merge(['C*'], $bytes);
        $packed = call_user_func_array('pack', $params);
        $unpacked = unpack('d', $packed);
        return $unpacked[1];
    }

    public function encode($value)
    {
        if (!is_double($value)) {
            throw new EncodeException("double field '{$this->name}' is unable to encode ". gettype($value) ." values");
        }
        return array_slice(unpack('C*', pack('d', $value)), 0);
    }
}

class BytesField extends BaseField
{
    public $type = Scheme::TYPE_BYTES;

    public $wire = Scheme::WIRE_LENGTH_DELIMITED;

    public function decode($bytes)
    {
        $i = 0;
        do {
            $b = $bytes[$i++];
        } while (($b & 0x80) === 0x80);
        return array_slice($bytes, $i);
    }

    public function encode($value)
    {
        if (!is_array($value)) {
            throw new EncodeException("bytes field '{$this->name}' is unable to encode ". gettype($value) ." values");
        }
        $value = array_merge($this->encode_varint(count($value)), $value);
        return $value;
    }
}

class Fixed32Field extends BaseField
{
    public $type = Scheme::TYPE_FIXED32;

    public $wire = Scheme::WIRE_32BIT;

    public function decode($bytes)
    {
        $packed = pack("C*", $bytes[0], $bytes[1], $bytes[2], $bytes[3]);
        $unpacked = unpack('V', $packed);
        return $unpacked[1];
    }

    public function encode($value)
    {
        if (!is_int($value)) {
            throw new EncodeException("fixed32 field '{$this->name}' is unable to encode ". gettype($value) ." values");
        }
        if ($value < 0 || $value > 4294967295) {
            throw new EncodeException("fixed32 field '{$this->name}' is unable to encode value $value");
        }
        return array_slice(unpack('C*', pack('V', $value)), 0);
    }
}

class SFixed32Field extends BaseField
{
    public $type = Scheme::TYPE_FIXED32;

    public $wire = Scheme::WIRE_32BIT;

    public function decode($bytes)
    {
        $packed = pack("C*", $bytes[0], $bytes[1], $bytes[2], $bytes[3]);
        $unpacked = unpack('l', $packed);
        return $unpacked[1];
    }

    public function encode($value)
    {
        if (!is_int($value)) {
            throw new EncodeException("sfixed32 field '{$this->name}' is unable to encode ". gettype($value) ." values");
        }
        if (!is_int($value) || $value < -2147483648 || $value > 2147483647) {
            throw new EncodeException("sfixed32 '{$this->name}' is unable to encode value $value");
        }
        return array_slice(unpack('C*', pack('l', $value)), 0);
    }
}

class Fixed64Field extends BaseField
{
    public $type = Scheme::TYPE_FIXED64;

    public $wire = Scheme::WIRE_64BIT;

    public function decode($bytes)
    {
        $packed = call_user_func_array('pack', array_merge(['C*'], $bytes));
        $unpacked = unpack('P', $packed);
        return $unpacked[1];
    }

    public function encode($value)
    {
        if (!is_int($value)) {
            throw new EncodeException("fixed64 field '{$this->name}' is unable to encode ". gettype($value) ." values");
        }
        if ($value < 0) {
            throw new EncodeException("fixed64 field '{$this->name}' is unable to encode value $value");
        }
        return array_slice(unpack('C*', pack('P', $value)), 0);
    }
}

class SFixed64Field extends BaseField
{
    public $type = Scheme::TYPE_SFIXED64;

    public $wire = Scheme::WIRE_64BIT;

    public function decode($bytes)
    {
        $packed = call_user_func_array('pack', array_merge(['C*'], $bytes));
        $unpacked = unpack('q', $packed);
        return $unpacked[1];
    }

    public function encode($value)
    {
        if (!is_int($value)) {
            throw new EncodeException("sfixed64 field '{$this->name}' is unable to encode ". gettype($value) ." values");
        }
        return array_slice(unpack('C*', pack('q', $value)), 0);
    }
}

class EmbededField extends BaseField
{
    public $type = Scheme::TYPE_EMBEDED;

    public $wire = Scheme::WIRE_LENGTH_DELIMITED;

    public $scheme;

    public static function instance($config)
    {
        $instance = parent::instance($config);
        $instance->scheme = new Scheme($config['fields']);
        return $instance;
    }

    public function decode($bytes)
    {
        return $this->scheme->decode($bytes);
    }

    public function encode($value)
    {
        $bin = $this->scheme->encode($value);
        $bytes = unpack('C*', $bin);
        $bytes = array_merge($this->encode_varint(count($bytes)), $bytes);
        return $bytes;
    }

    public function merge($old, $new)
    {
        if ($this->repeated) {
            return $old ? array_merge($old, $new) : [$new];
        }
        return $old ? array_merge($old, $new) : $new;
    }
}