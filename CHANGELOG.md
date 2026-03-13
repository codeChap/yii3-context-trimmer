# Changelog

## 1.0.0 - under development

- Initial release
- Tokenizer-agnostic text preprocessing with sentence-aware segmentation
- Immutable fluent configuration API
- Configurable preprocessing pipeline: deduplication, short word removal, extraneous character removal, whitespace compression
- Custom tokenizer support (e.g. tiktoken for OpenAI models)
- Yii3 config-plugin integration (params, DI)
- Console command `context:trim` with file and stdin input
