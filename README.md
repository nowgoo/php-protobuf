# php-protobuf
simple protobuf encoder and decoder

# features
- no need to compile .proto files
- decode & encode without a scheme definition [tbd]
- grpc client [tbd]

# install
```
composer require nowgoo/php-protobuf dev-master
```

# usage

## basic
``` php
use \PhpProtobuf\Scheme;
$scheme = new Scheme([
    // simple field
    1 => ['name'=>'foo', 'type'=>Scheme::TYPE_INT],
    // embeded field
    2 => ['name'=>'bar', 'type'=>Scheme::TYPE_EMBEDED, 'fields'=>[
        1 => ['name'=>'bar_sub', 'type'=>Scheme::TYPE_STRING]
    ]],
    // repeated field
    3 => ['name'=>'baz', 'type'=>Scheme::TYPE_INT, 'repeated'=>true],
]);

$binary = $scheme->encode([
    'foo' => 123456,
    'bar' => ['bar_sub' => 'hello, world'],
    'baz' => [3, 270, 86942]
]);
// binary = [08 c0 c4 07 12 0e 0a 0c 68 65 6c 6c 6f 2c 20 77 6f 72 6c 64 1a 06 03 8e 02 9e a7 05]

$decoded = $scheme->decode($binary);
// $decoded == ['foo'=>123456, 'bar'=>['bar_sub'=>'hello, world'], 'baz'=>[3,270,86942]];
```

## no scheme
tbd

## grpc
tbd

# licence
MIT License