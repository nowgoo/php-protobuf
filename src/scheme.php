<?php
namespace PhpProtobuf;

class Scheme {

    const TYPE_INT = 0;
    const TYPE_UINT = 1;
    const TYPE_SINT = 2;
    const TYPE_BOOL = 3;
    const TYPE_STRING = 4;
    const TYPE_FLOAT = 5;
    const TYPE_DOUBLE = 6;
    const TYPE_BYTES = 7;
    const TYPE_FIXED32 = 8;
    const TYPE_FIXED64 = 9;
    const TYPE_SFIXED32 = 10;
    const TYPE_SFIXED64 = 11;
    const TYPE_EMBEDED = 20;
    
    const WIRE_VARINT = 0;
    const WIRE_64BIT = 1;
    const WIRE_LENGTH_DELIMITED = 2;
    const WIRE_START_GROUP = 3;
    const WIRE_END_GROUP = 4;
    const WIRE_32BIT = 5;

    public $fields = [];

    public static $scalar_types = [
        self::TYPE_INT,
        self::TYPE_UINT,
        self::TYPE_SINT,
    ];

    public function __construct($fields)
    {
        $classes = [
            self::TYPE_INT => 'IntField',
            self::TYPE_UINT => 'UIntField',
            self::TYPE_SINT => 'SIntField',
            self::TYPE_BOOL => 'BoolField',
            self::TYPE_FLOAT => 'FloatField',
            self::TYPE_DOUBLE => 'DoubleField',
            self::TYPE_STRING => 'StringField',
            self::TYPE_BYTES => 'BytesField',
            self::TYPE_FIXED32 => 'Fixed32Field',
            self::TYPE_SFIXED32 => 'SFixed32Field',
            self::TYPE_FIXED64 => 'Fixed64Field',
            self::TYPE_SFIXED64 => 'SFixed64Field',
            self::TYPE_EMBEDED => 'EmbededField',
        ];
        foreach ($fields as $i => $config) {
            if (is_array($config)) {
                $type = $config['type'];
                if (!isset($classes[$type])) {
                    throw new SchemeException("unknown type: $type");
                }
                $class = "\\PhpProtobuf\\".$classes[$type];
                $this->fields[$i] = $class::instance($config);
            } elseif (is_a($config, 'Scheme')) {
                $this->fields[$i] = $config;
            } else {
                throw new SchemeException("illegal config item");
            }
        }
    }

    /**
     * php array to binary string
     */
    public function encode(array $data)
    {
        $bytes = [];
        foreach ($this->fields as $index => $field) {
            $key = $field->name;
            if (!isset($data[$key])) {
                if ($field->required) {
                    throw new EncodeException("missing required field: $key");
                }
                continue;
            }
            $bytes = array_merge($bytes, $field->encodeField($index, $data[$key]));
        }
        return implode(array_map("chr", $bytes));
    }

    /**
     * binary string (or byte array) to php array
     */
    public function decode($bin)
    {
        if (is_array($bin)) {
            array_unshift($bin, 0);
            unset($bin[0]);
            $bytes = $bin;
        } else {
            $bytes = unpack('C*', $bin);
        }

        $decoded = [];
        $len = count($bytes);
        for ($i = 1; $i <= $len;) {
            $byte = $bytes[$i++];
            $index = $byte >> 3;
            if (!isset($this->fields[$index])) {
                throw new DecodeException("decode failed: unknown field index $index");
            }

            $wire = $byte & 7;
            $value_bytes = [];
            if ($wire === Scheme::WIRE_VARINT) {
                do {
                    $b = $bytes[$i++];
                    $value_bytes[] = $b;
                } while (($b & 0x80) === 0x80);
            } elseif ($wire === Scheme::WIRE_LENGTH_DELIMITED) {
                $value_length = $bytes[$i];
                $value_bytes = array_slice($bytes, $i++, $value_length);
                $i += $value_length;
            } elseif ($wire === Scheme::WIRE_32BIT) {
                $value_bytes = array_slice($bytes, $i-1, 4);
                $i += 4;
            } elseif ($wire === Scheme::WIRE_64BIT) {
                $value_bytes = array_slice($bytes, $i-1, 8);
                $i += 8;
            } else {
                throw new DecodeException("decode failed: unsupported wire type");
            }

            $field = $this->fields[$index];

            // repeated & scalar fields
            if ($field->repeated && in_array($field->type, self::$scalar_types)) {
                $buf = [];
                $old = $decoded[$field->name] ?? [];
                foreach ($value_bytes as $byte) {
                    $buf[] = $byte;
                    if (($byte & 0x80) === 0x80) {
                        continue;
                    }
                    $old[] = $field->decode($buf);
                    $buf = [];
                }
                $decoded[$field->name] = $old;
                continue;
            }

            $old = $decoded[$field->name] ?? null;
            $decoded[$field->name] = $field->merge($old, $field->decode($value_bytes));
        }
        return $decoded;
    }
}