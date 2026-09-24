"""Visitor chat REST checks against a disposable environment; removes its conversations."""
import html
import json
import re
import sys
import urllib.request
import urllib.error
import uuid
base = (sys.argv[1] if len(sys.argv) > 1 else 'http://localhost:8080').rstrip('/')
page = urllib.request.urlopen(base + '/contact/').read().decode()
config = json.loads(html.unescape(re.search(r'data-chat-config="([^"]+)"', page)[1]))
def request(method, suffix='', data=None, token='', nonce=None, origin=None):
    headers = {'Content-Type':'application/json', 'X-Shadowalker-Nonce':config['nonce'] if nonce is None else nonce}
    if token: headers['X-Shadowalker-Token'] = token
    if origin: headers['Origin'] = origin
    req = urllib.request.Request(config['base'] + suffix, json.dumps(data).encode() if data is not None else None, headers, method=method)
    try: response = urllib.request.urlopen(req)
    except urllib.error.HTTPError as error: response = error
    return response.status, json.loads(response.read()), response.headers
assert request('POST', data={}, nonce='wrong')[0] == 403
assert request('POST', data={}, origin='https://untrusted.example')[0] == 403
status, session, headers = request('POST', data={'locale':'en_US'}, origin=base)
assert status == 201 and 'no-store' in headers['Cache-Control']
suffix = '/' + str(session['id'])
try:
    assert request('GET', suffix)[0] == 403
    payload = {'text':'HTTP chat test: conversion kit compatibility', 'request_id':str(uuid.uuid4())}
    status, body, _ = request('POST', suffix+'/messages', payload, session['token'])
    assert status == 200 and body['messages'][0]['text'] == payload['text']
    assert request('POST', suffix+'/messages', payload, session['token'])[1]['messages'] == body['messages']
    assert request('GET', suffix, token=session['token'])[1]['messages'] == body['messages']
    assert request('POST', suffix+'/handoff', {}, session['token'])[1]['mode'] == 'human'
finally:
    assert request('DELETE', suffix, token=session['token'])[0] == 200
assert request('GET', suffix, token=session['token'])[0] == 403
print('PASS HTTP chat: nonce, origin, private history, send, retry, restore, handoff, deletion')
