# Laravel integration

The separate [comprehend-laravel package](https://github.com/vanderlee/comprehend-laravel)
connects Comprehend grammars to Laravel's validation system. It supports Comprehend 2
and Illuminate 11, 12 and 13 on their supported PHP versions. Laravel dependencies
belong to the bridge; the Comprehend core remains framework independent.

## Installation

From your Laravel application's root directory:

```sh
composer require vanderlee/comprehend-laravel
```

Composer installs Comprehend automatically. No service provider or configuration
is required. See the [package README](https://github.com/vanderlee/comprehend-laravel#readme)
for current compatibility and installation details.

## Validate a field

Use `MatchesGrammar` in a Form Request's `rules()` method:

```php
use Vanderlee\Comprehend\Comprehend as C;
use Vanderlee\Comprehend\Laravel\Rules\MatchesGrammar;

public function rules(): array
{
    return [
        'reference' => [
            'required',
            'string',
            new MatchesGrammar(C::text('INV-')->then(C::range('0', '9')->some())),
        ],
    ];
}
```

This accepts `INV-123` and rejects `INV-`, `123` and `INV-123-extra`.
The rule accepts a `Comprehend` or `FrozenGrammar` object, requires a complete
UTF-8 string match and rejects recovered syntax. Syntax errors include source
locations. Use Laravel's `required` or `nullable` rules to control optional fields.

## Semantic validation and effects

Recognition runs by default. To also check the grammar's semantic constraints:

```php
new MatchesGrammar($grammar->freeze(), validateSemantics: true);
```

Semantic validation may evaluate lazy mappings, but the rule never calls
`commit()`. Validation leaves the field as its original string; parse explicitly
and call `value()` when the application needs a domain value. See
[semantic values](semantic-values.md) and [lifecycle and effects](lifecycle.md).

The bridge also accepts execution limits and custom validation messages. See
the [package documentation](https://github.com/vanderlee/comprehend-laravel#readme)
for those options and Laravel request middleware considerations.
