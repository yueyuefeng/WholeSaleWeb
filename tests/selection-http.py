"""Selection HTTP integration for disposable environments. Plans expire after 30 days; chats are deleted."""
import html, json, re, sys, urllib.request, urllib.error
base=(sys.argv[1] if len(sys.argv)>1 else 'http://localhost:8080').rstrip('/')
def get(path):
    with urllib.request.urlopen(path if path.startswith('http') else base+path) as r: return r.read().decode(),r.headers
page,_=get('/product/trail-electric-golf-cart/')
config=json.loads(re.search(r'window.swSelection=(\{[^\n]+\});',page)[1])
product_id=re.search(r'data-compare-id="(\d+)"',page)[1]
def post(url,data,nonce=None,origin=None):
    headers={'Content-Type':'application/json','X-Shadowalker-Nonce':config['nonce'] if nonce is None else nonce,'Origin':origin or base}
    r=urllib.request.Request(url,json.dumps(data).encode(),headers,method='POST')
    try: response=urllib.request.urlopen(r)
    except urllib.error.HTTPError as e: response=e
    return response.status,json.loads(response.read()),response.headers
body={'mode':'build','product_id':product_id,'choices':{'seats':'six','battery':'standard','canopy':'roof'}}
assert post(config['endpoint'],body,nonce='invalid')[0]==403
assert post(config['endpoint'],body,origin='https://invalid.example')[0]==403
assert post(config['endpoint'],body)[0]==422
body['choices']['battery']='extended'
status,data,headers=post(config['endpoint'],body)
assert status==201 and 'no-store' in headers['Cache-Control']
page,headers=get(data['url'])
assert 'noindex' in headers['X-Robots-Tag'] and 'no-cache' in headers['Cache-Control']
assert '6 seats' in page and 'Extended' in page
page,headers=get(data['chat_url'])
chat=json.loads(html.unescape(re.search(r'data-chat-config="([^"]+)"',page)[1]))
assert chat['plan']==data['plan'] and chat['product_id']==int(product_id) and 'sw-chat-plan' in page
status,session,_=post(chat['base'],{'plan':data['plan'],'locale':'en_US'})
assert status==201
request=urllib.request.Request(chat['base']+'/'+str(session['id']),headers={'X-Shadowalker-Token':session['token']},method='DELETE')
assert urllib.request.urlopen(request).status==200
page,headers=get('/compare/')
assert 'noindex' in headers['X-Robots-Tag']
print('PASS HTTP selection: nonce/origin, conflict, save, no-store/noindex, share, plan-aware chat')
