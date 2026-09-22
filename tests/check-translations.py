import gettext
import json
import re
from pathlib import Path

root = Path(__file__).resolve().parents[1]
strings = set()
for path in (root / 'wp-content').rglob('*.php'):
    strings.update(re.findall(r"(?:__|_e|esc_html__|esc_html_e|esc_attr_e|esc_attr__)\('([^']*)',\s*'shadowalker'\)", path.read_text(encoding='utf-8')))
for locale in ('zh_CN', 'de_DE', 'fr_FR', 'es_ES'):
    source = json.loads((root / f'translations/{locale}.json').read_text(encoding='utf-8'))
    missing = strings - source.keys()
    assert not missing, f'{locale} missing: {sorted(missing)}'
    with (root / f'wp-content/themes/shadowalker/languages/{locale}.mo').open('rb') as stream:
        catalog = gettext.GNUTranslations(stream)
    for key in strings:
        assert catalog.gettext(key) == source[key], (locale, key)
        assert key.count('%s') == source[key].count('%s'), (locale, key)
    print(f'PASS {locale}: {len(strings)} source strings and binary catalog')
