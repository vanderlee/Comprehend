# Facade status

A dedicated immutable facade is Phase 3 work. Current typed helpers in
`Vanderlee\Comprehend\Library` are autoloaded by Composer and construct the
same supported parser objects. They do not coerce strings/arrays into parsers.
See the [reference](reference.md) and [builder](builder.md).
