# Font fixtures

`tests/Integration/FontCompileTest.php` exercises real TCPDF font compilation:
key derivation, cache paths, `--force` rebuilds, and that a compiled face is
actually embedded in the output.

No font binaries are committed here - they are large and separately licensed -
so those tests **skip** unless at least one `.ttf` is present in this directory.

To run them, drop any TrueType file in:

```bash
cp /path/to/SomeFont-Regular.ttf tests/Fixtures/fonts/
vendor/bin/phpunit --testsuite integration
```

Everything that can be checked without a font binary - key derivation matching
TCPDF's own algorithm, path normalisation, collision and format detection - is
covered unconditionally by `tests/Unit/FontCompilerTest.php`.
