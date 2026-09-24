"""HTTP flow tests. Creates one private test inquiry; run only on disposable/staging sites."""
import re
import sys
import time
import urllib.error
import urllib.parse
import urllib.request

base = (sys.argv[1] if len(sys.argv) > 1 else 'http://localhost:8080').rstrip('/')
def request(path, data=None):
    encoded = urllib.parse.urlencode(data).encode() if data is not None else None
    try:
        with urllib.request.urlopen(base + path, encoded, timeout=60) as response:
            return response.status, response.read().decode('utf-8'), response.geturl()
    except urllib.error.HTTPError as error:
        return error.code, error.read().decode('utf-8'), error.url

for path, text in [('/', 'manifesto-heading'), ('/shop/', 'woocommerce'), ('/contact/', 'sw-form'), ('/our-story/', 'Shadowalker')]:
    status, body, _ = request(path)
    assert status == 200 and text in body, (path, status)
    assert 'Fatal error' not in body and 'critical error' not in body, path
    print('PASS GET', path)
status, body, _ = request('/not-a-real-shadowalker-page/')
assert status == 404 and '404 /' in body
print('PASS 404')
_, form, _ = request('/contact/')
nonce = re.search(r'name="sw_nonce" value="([^"]+)"', form).group(1)
payload = dict(action='sw_inquiry', sw_nonce=nonce, name='HTTP Test', email=f'http-{time.time_ns()}@example.test', country='US', quantity='2', message='Automated test inquiry for two conversion kits.', consent='1', return_url=base + '/contact/', website='')
invalid = payload | {'sw_nonce': 'invalid'}
assert request('/wp-admin/admin-post.php', invalid)[0] == 403
assert request('/wp-admin/admin-post.php', payload | {'quantity': '-1'})[0] == 422
assert request('/wp-admin/admin-post.php', payload | {'website': 'spam'})[0] == 400
assert request('/wp-admin/admin-post.php', payload | {'consent': ''})[0] == 422
status, body, url = request('/wp-admin/admin-post.php', payload)
assert status == 200 and 'inquiry=received' in url and 'Your inquiry has been saved' in body, (status, url)
assert request('/wp-admin/admin-post.php', payload)[0] == 429
print('PASS inquiry: nonce, field validation, honeypot, consent, persistence redirect and duplicate throttling')
