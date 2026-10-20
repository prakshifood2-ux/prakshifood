"""Isolated end-to-end HTTP checks; Python standard library only. Run as http_checks.py."""
import os, re, json, time, secrets, subprocess, urllib.request, urllib.parse, urllib.error, http.cookiejar, email, email.policy
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PHP = os.environ.get('PHP_BINARY', r'H:\xampp\php\php.exe')
BASE = 'http://127.0.0.1:8098'
env = dict(os.environ, PRAKSHI_DB_NAME='prakshi_http_' + secrets.token_hex(6), PRAKSHI_BASE_URL=BASE, TEST_ADMIN_PASSWORD=secrets.token_hex(12))
OUTBOX=ROOT/'storage/mail'/env['PRAKSHI_DB_NAME']
env.update(PRAKSHI_MAIL_MODE='log',PRAKSHI_MAIL_LOG_DIR=str(OUTBOX))
flags = subprocess.CREATE_NO_WINDOW if os.name == 'nt' else 0
passed = 0

class Client:
    def __init__(self):
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        self.token = ''
    def get(self, route='', **params):
        return self.call(route, None, params)
    def post(self, route, data, token=True):
        data = dict(data)
        if token: data['_token'] = self.token
        return self.call(route, data)
    def call(self, route, data=None, params=None):
        url = BASE + '/index.php?' + urllib.parse.urlencode(dict(r=route, **(params or {})))
        req = urllib.request.Request(url, data=urllib.parse.urlencode(data).encode() if data is not None else None)
        try:
            response = self.opener.open(req, timeout=15)
        except urllib.error.HTTPError as error:
            response = error
        body = response.read().decode('utf-8-sig')
        found = re.search(r'name="_token" value="([a-f0-9]+)"', body)
        if found: self.token = found.group(1)
        return response.status, body, response.geturl()

def check(ok, label):
    global passed
    if not ok: raise AssertionError(label)
    passed += 1
    print('PASS:', label)

def latest_otp(email):
    for path in sorted(OUTBOX.glob('*.txt'),key=lambda p:p.stat().st_mtime_ns,reverse=True):
        content=path.read_text(encoding='utf-8')
        match=re.search(r'OTP is: ([0-9]{6})',content)
        if 'To: '+email+'\n' in content and match:return match.group(1)
    raise AssertionError('No OTP email for '+email)

def verify(client,email,admin=False):
    return client.post('admin/verify' if admin else 'verify',{'otp':latest_otp(email)})

subprocess.run([PHP, str(ROOT/'tests/http_fixture.php'), 'setup'], cwd=ROOT, env=env, check=True, capture_output=True, creationflags=flags)
log = open(ROOT/'storage/logs/http-test-server.log', 'w')
server = subprocess.Popen([PHP, '-S', '127.0.0.1:8098', 'tests/router.php'], cwd=ROOT, env=env, stdout=log, stderr=log, creationflags=flags)
try:
    time.sleep(1)
    guest=Client()
    for route, params in [('',{}),('shop',{}),('product',{'slug':'classic-mango-pickle'}),('bundle',{}),('login',{}),('register',{}),('forgot',{}),('page',{'slug':'contact'}),('page',{'slug':'shipping'}),('sitemap',{})]:
        status, body, _=guest.get(route,**params)
        check(status==200 and 'Fatal error' not in body, 'Public route '+(route or 'home'))
    check('authModal' in guest.get()[1] and 'modal fade show' not in guest.get()[1], 'No forced login while browsing')
    check(guest.post('contact', {'name':'Test','email':'test@example.test','message':'Test'}, token=False)[0]==422, 'CSRF rejected')
    check(guest.get('admin')[2].endswith('r=admin%2Flogin'), 'Guest denied admin access')
    guest.get()
    response=guest.post('cart/add',{'variant_id':1,'quantity':2})
    check(json.loads(response[1]).get('auth') is True, 'First add requests authentication')
    response=guest.post('register',{'name':'Test Customer','email':'buyer@example.test','password':'TestingPassword123','password_confirmation':'TestingPassword123','consent':'1'})
    check(response[0]==200 and 'Check your inbox' in response[1], 'Registration requires email OTP')
    check('r=login' in guest.get('account')[2], 'Unverified customer cannot access account')
    check('Incorrect code' in guest.post('verify',{'otp':'000000'})[1], 'Incorrect customer OTP rejected')
    check('wait 60 seconds' in guest.post('otp/resend',{})[1], 'OTP resend cooldown enforced')
    response=verify(guest,'buyer@example.test')
    check(response[0]==200 and 'r=cart' in response[2] and 'Classic Mango Pickle' in response[1], 'Registration resumes pending cart')
    check('value="2"' in response[1], 'Pending quantity preserved')
    check(guest.get('admin')[2].endswith('r=admin%2Flogin'), 'Customer denied admin access')
    check(guest.post('register',{'name':'Duplicate','email':'buyer@example.test','password':'TestingPassword123','password_confirmation':'TestingPassword123','consent':'1'})[0]==422,'Duplicate registration prevented')
    check(guest.post('cart/coupon',{'coupon':'WELCOME10'})[0]==200, 'Coupon applied')
    checkout=guest.get('checkout')
    key=re.search(r'name="checkout_key" value="([a-f0-9]+)"',checkout[1]).group(1)
    details={'checkout_key':key,'name':'Test Buyer','email':'buyer@example.test','mobile':'9999999999','address':'123 Test Lane','city':'Delhi','state':'Delhi','pincode':'110001','instructions':'HTTP test','payment_method':'cod'}
    check(guest.post('checkout',dict(details,mobile='invalid'))[0]==422,'Checkout validates address')
    response=guest.post('checkout',details)
    check(response[0]==200 and 'Thank you for choosing Prakshi' in response[1], 'COD checkout succeeds')
    order_id=urllib.parse.parse_qs(urllib.parse.urlparse(response[2]).query)['id'][0]
    check('Track your order' in response[1] and 'Packed' in response[1] and 'Out for delivery' in response[1], 'Customer sees delivery timeline')
    invoice_url=BASE+'/index.php?'+urllib.parse.urlencode({'r':'invoice','id':order_id})
    invoice=guest.opener.open(invoice_url).read()
    check(invoice.startswith(b'%PDF-1.4') and b'%%EOF' in invoice, 'Customer can download a PDF invoice')
    attachments=[]
    for path in OUTBOX.glob('*.eml'):
        message=email.message_from_bytes(path.read_bytes(),policy=email.policy.default)
        attachments += list(message.iter_attachments())
    check(any(p.get_content_type()=='application/pdf' and p.get_payload(decode=True).startswith(b'%PDF-1.4') for p in attachments),'Order confirmation email includes a valid PDF attachment')
    check('pending' in response[1], 'COD is not falsely marked paid')
    check(guest.post('checkout',details)[2]==response[2], 'Repeated checkout is idempotent')
    check('Your pantry is waiting' in guest.get('cart')[1], 'Cart cleared after checkout')
    other=Client();other.get('register')
    other.post('register',{'name':'Other','email':'other@example.test','password':'TestingPassword123','password_confirmation':'TestingPassword123','consent':'1'})
    verify(other,'other@example.test')
    check(other.get('order',id=order_id)[0]==422,'Order ownership enforced')
    check(other.get('invoice',id=order_id)[0]==422,'Another customer cannot download invoice')
    admin=Client();admin.get('admin/login')
    response=admin.post('admin/login',{'email':'owner@example.test','password':env['TEST_ADMIN_PASSWORD']})
    check('Check your inbox' in response[1], 'Admin password alone requires OTP')
    check('r=admin%2Flogin' in admin.get('admin')[2], 'Admin dashboard blocked before OTP')
    check('Incorrect code' in admin.post('admin/verify',{'otp':'000000'})[1], 'Incorrect admin OTP rejected')
    response=verify(admin,'owner@example.test',admin=True)
    check(response[0]==200 and 'Store overview' in response[1],'Admin login and database dashboard')
    for module in ['products','variants','categories','customers','coupons','bundles','reviews','media','admins','audit','inventory-log','messages','newsletter','coupon-usage']:
        check(admin.get('admin/list',module=module)[0]==200,'Admin listing '+module)
    for module, id in [('products',1),('variants',1),('categories',1),('coupons',1),('bundles',1),('admins',1)]:
        check(admin.get('admin/edit',module=module,id=id)[0]==200,'Admin editor '+module)
    for section in ['store','shipping','homepage','policies','razorpay']:
        check(admin.get('admin/settings',section=section)[0]==200,'Settings '+section)
    check(admin.get('admin/order',id=order_id)[0]==200,'Admin order details')
    response=admin.post('admin/save',{'module':'categories','id':'0','name':'HTTP Category','slug':'http-category','description':'Test category','sort_order':'99','active':'1'})
    check(response[0]==200 and 'HTTP Category' in response[1],'Admin creates category')
    check('HTTP Category' in guest.get('shop')[1],'Admin changes visible to storefront')
    response=admin.get('admin/export',module='orders')
    check(response[0]==200 and 'Total (INR)' in response[1] and 'Test Customer' in response[1],'Orders CSV export from database')
    check('password' not in admin.get('admin/export',module='customers')[1].lower(),'Export excludes password hashes')
    for state in ['Processing','Packed','Shipped','Out for delivery','Delivered']:
        check(admin.post('admin/order',{'id':order_id,'status':state,'tracking':'TRACK-TEST','courier':'Test Courier','tracking_url':'https://example.com/track/TEST','expected_delivery':'2026-11-01','notes':'INTERNAL-NOT-FOR-CUSTOMER'})[0]==200,'Order transition '+state)
        customer_view=guest.get('order',id=order_id)[1]
        check(state in customer_view and 'INTERNAL-NOT-FOR-CUSTOMER' not in customer_view,'Public order update excludes internal notes: '+state)
        check(any('Order '+state in p.read_text(encoding='utf-8') and 'To: buyer@example.test' in p.read_text(encoding='utf-8') for p in OUTBOX.glob('*.txt')),'Customer status email: '+state)
    check('https://example.com/track/TEST' in guest.get('order',id=order_id)[1],'Courier tracking link shown to customer')
    check(admin.post('admin/order',{'id':order_id,'action':'invoice-email'})[0]==200,'Admin can resend invoice')
    check('Customer emails &amp; invoice' in admin.get('admin/order',id=order_id)[1] or 'Customer emails & invoice' in admin.get('admin/order',id=order_id)[1],'Admin sees email delivery log')
    check(guest.post('review',{'product_id':'1','rating':'5','body':'A good test of the verified purchase review flow.'})[0]==200,'Delivered customer can review')
    check('A good test' not in other.get('product',slug='classic-mango-pickle')[1],'Unapproved review hidden')
    check(admin.post('admin/save',{'module':'reviews','id':'1','status':'approved','response':'Thank you'})[0]==200,'Admin approves review')
    check('A good test' in other.get('product',slug='classic-mango-pickle')[1],'Approved review published')
    check(admin.post('admin/order',{'id':order_id,'action':'cod-paid','receipt':'COLLECTION-TEST'})[0]==200,'COD collection recorded with receipt')
    check('PAYMENT CONFIRMED' in guest.get('order',id=order_id)[1],'Verified collection visible to customer')
    manager=Client();manager.get('admin/login');manager.post('admin/login',{'email':'manager@example.test','password':env['TEST_ADMIN_PASSWORD']})
    verify(manager,'manager@example.test',admin=True)
    check(manager.get('admin/settings',section='razorpay')[0]==403,'Manager cannot access payment secrets')
    check(guest.post('reorder',{'id':order_id})[0]==200,'Reorder adds current variants')
    saved_checkout=guest.get('checkout')
    check('Delivering to your saved address' in saved_checkout[1] and 'name="address"' not in saved_checkout[1], 'Returning customer is not asked to re-enter address')
    check('Edit address' in saved_checkout[1], 'Saved address has an explicit edit button')
    edit=guest.get('checkout',edit_address='1')
    check('name="address"' in edit[1] and 'value="123 Test Lane"' in edit[1], 'Address editor is prefilled')
    updated=dict(details,address='456 Updated Lane')
    saved_checkout=guest.post('address/save',updated)
    check('456 Updated Lane' in saved_checkout[1] and 'name="address"' not in saved_checkout[1], 'Saving edited address returns to summary')
    address_id=re.search(r'name="address_id" value="([0-9]+)"',saved_checkout[1]).group(1)
    new_key=re.search(r'name="checkout_key" value="([a-f0-9]+)"',saved_checkout[1]).group(1)
    check(other.post('checkout',{'address_mode':'saved','address_id':address_id,'payment_method':'cod','checkout_key':new_key})[0]==422, 'Cannot use another customer’s saved address')
    response=guest.post('checkout',{'address_mode':'saved','address_id':address_id,'payment_method':'cod','checkout_key':new_key})
    check(response[0]==200 and '456 Updated Lane' in response[1], 'Next checkout uses saved address without address fields')
    check('123 Test Lane' in guest.get('order',id=order_id)[1], 'Address editing preserves previous order delivery details')
    check(guest.get('account')[1].count('456 Updated Lane')==1, 'Repeated order does not duplicate saved address')
    check(guest.post('logout',{})[0]==200,'Customer logout')
    check('r=login' in guest.post('verify',{'otp':latest_otp('buyer@example.test')})[2], 'Used customer OTP cannot sign in again')
    guest.get('page',slug='contact')
    check(guest.post('contact',{'name':'Customer','email':'question@example.test','message':'Please help with my order.'})[0]==200,'Support contact form saves message')
    check(any('New Prakshi support request' in p.read_text(encoding='utf-8') and 'To: prakshifoods@gmail.com' in p.read_text(encoding='utf-8') for p in OUTBOX.glob('*.txt')),'Support request email addressed to store')
    print(f'\n{passed} HTTP checks passed.')
finally:
    server.terminate();server.wait(timeout=10);log.close()
    subprocess.run([PHP,str(ROOT/'tests/http_fixture.php'),'drop'],cwd=ROOT,env=env,check=True,capture_output=True,creationflags=flags)
