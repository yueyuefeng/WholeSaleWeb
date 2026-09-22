"""Compile deterministic GNU gettext .po/.mo catalogs without external dependencies."""
import json
import struct
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'wp-content/themes/shadowalker/languages'
OUT.mkdir(parents=True, exist_ok=True)
for source in sorted((ROOT / 'translations').glob('*.json')):
    locale = source.stem
    messages = json.loads(source.read_text(encoding='utf-8'))
    messages[''] = f'Project-Id-Version: Shadowalker 1.0.0\nLanguage: {locale}\nMIME-Version: 1.0\nContent-Type: text/plain; charset=UTF-8\nContent-Transfer-Encoding: 8bit\n'
    keys = sorted(messages)
    ids = b''; values = b''; id_entries = []; value_entries = []
    for key in keys:
        raw_id = key.encode(); raw_value = messages[key].encode()
        id_entries.append((len(raw_id), len(ids))); ids += raw_id + b'\0'
        value_entries.append((len(raw_value), len(values))); values += raw_value + b'\0'
    count = len(keys); id_offset = 28 + count * 16; value_offset = id_offset + len(ids)
    header = struct.pack('<7I', 0x950412de, 0, count, 28, 28 + count * 8, 0, 0)
    index = b''.join(struct.pack('<2I', size, offset + id_offset) for size, offset in id_entries)
    index += b''.join(struct.pack('<2I', size, offset + value_offset) for size, offset in value_entries)
    (OUT / f'{locale}.mo').write_bytes(header + index + ids + values)
    po = '\n\n'.join(f'msgid {json.dumps(key, ensure_ascii=False)}\nmsgstr {json.dumps(messages[key], ensure_ascii=False)}' for key in keys)
    (OUT / f'{locale}.po').write_text(po + '\n', encoding='utf-8')
    print(f'{locale}: {count - 1} translated strings')
