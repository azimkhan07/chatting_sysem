param(
    [string]$Base = 'http://127.0.0.1:8001/api/v1',
    [string]$User = 'admin',
    [string]$Password = 'admin123'
)

$ErrorActionPreference = 'Stop'

function Login($identifier, $password) {
    $body = @{ identifier = $identifier; password = $password } | ConvertTo-Json
    $r = Invoke-RestMethod -Uri "$Base/admin/auth/login" -Method Post -Body $body -ContentType 'application/json'
    return @{ token = $r.data.access_token; role = $r.data.user.role }
}

function Call($method, $path, $token, $payload = $null) {
    # Deliberately not Invoke-WebRequest: on PowerShell 5.1 it silently drops the
    # body on PATCH, which surfaced as a bogus 422 "status is required" against
    # an API that was in fact correct. HttpWebRequest sends every verb's body.
    $req = [System.Net.HttpWebRequest]::Create("$Base$path")
    $req.Method = $method
    $req.Timeout = 30000
    $req.ReadWriteTimeout = 30000
    $req.Accept = 'application/json'
    if ($token) { $req.Headers['Authorization'] = "Bearer $token" }

    if ($null -ne $payload) {
        $bytes = [System.Text.Encoding]::UTF8.GetBytes(($payload | ConvertTo-Json -Depth 6 -Compress))
        $req.ContentType = 'application/json'
        $req.ContentLength = $bytes.Length
        $stream = $req.GetRequestStream()
        $stream.Write($bytes, 0, $bytes.Length)
        $stream.Close()
    }

    try {
        $resp = $req.GetResponse()
        $reader = New-Object System.IO.StreamReader($resp.GetResponseStream())
        $body = $reader.ReadToEnd()
        $reader.Close()
        $code = [int]$resp.StatusCode
        $resp.Close()
        return @{ code = $code; body = $body }
    } catch [System.Net.WebException] {
        $resp = $_.Exception.Response
        if ($null -ne $resp) {
            $reader = New-Object System.IO.StreamReader($resp.GetResponseStream())
            $body = $reader.ReadToEnd()
            $reader.Close()
            $code = [int]$resp.StatusCode
            $resp.Close()
            return @{ code = $code; body = $body }
        }
        return @{ code = -1; body = $_.Exception.Message }
    }
}

$pass = 0
$fail = 0
# Username/title suffix unique to this run, so a re-run exercises creation
# instead of colliding with the accounts the previous run left behind (409) and
# reporting a data-cleanup problem as an authorization failure.
$RunTag = [guid]::NewGuid().ToString('N').Substring(0, 6)
function Check($label, $expected, $actual) {
    if ($expected -eq $actual) {
        $script:pass++
        "  OK   $label -> $actual"
    } else {
        $script:fail++
        "  FAIL $label -> got $actual, want $expected"
    }
}

# Every seeded role is exercised, not just the three that happened to be seeded
# first. A role with no account cannot be tested, and an untested role's
# permissions are a guess - which is how the support role came to be able to read
# the mail and gateway configuration for as long as nobody checked it.
$accounts = @(
    @{ u = 'superadmin'; p = 'super123';    role = 'super_admin' },
    @{ u = 'admin';     p = 'admin123';    role = 'admin' },
    @{ u = 'support';   p = 'support123';  role = 'support' },
    @{ u = 'moderator'; p = 'moderator123'; role = 'moderator' }
)

# $canAdmin: reads the configuration screens and the team roster.
# $canOperate: writes anything at all.
# $canAppeal: decides a suspension appeal.
$expect = @{
    'super_admin' = @{ admin = $true;  operate = $true;  appeal = $true }
    'admin'       = @{ admin = $true;  operate = $true;  appeal = $false }
    'support'     = @{ admin = $false; operate = $true;  appeal = $true }
    'moderator'   = @{ admin = $false; operate = $false; appeal = $false }
}

$sessions = @{}
foreach ($a in $accounts) {
    try {
        $s = Login $a.u $a.p
        $sessions[$a.role] = $s.token
        "LOGIN OK  $($a.u) [$($a.role)]"
    } catch {
        $fail++
        "LOGIN FAIL $($a.u): $($_.ErrorDetails.Message)"
    }
}

"`n== unauthenticated =="
Check 'no token -> me' 401 (Call GET '/admin/auth/me' $null).code

foreach ($role in @('super_admin', 'admin', 'support', 'moderator')) {
    if (-not $sessions.ContainsKey($role)) { continue }
    $t = $sessions[$role]
    $e = $expect[$role]

    "`n== $role : everyday reads (any staff role) =="
    foreach ($probe in @(
        @{ label = 'me';             path = '/admin/auth/me' },
        @{ label = 'dashboard';      path = '/admin/dashboard/stats' },
        @{ label = 'users';          path = '/admin/users' },
        @{ label = 'subscriptions';  path = '/admin/subscriptions' },
        @{ label = 'sub stats';      path = '/admin/subscriptions/stats' },
        @{ label = 'reports';        path = '/admin/reports' },
        @{ label = 'tickets';        path = '/admin/support/tickets' },
        @{ label = 'agents';         path = '/admin/support/agents' },
        @{ label = 'appeals';        path = '/admin/appeals' },
        # The country catalogue is reference data, not configuration: it is the
        # name/code list behind the Users country filter, which is an everyday
        # read every role performs. Gating it on admin left support and moderator
        # with a filter that could not be opened.
        @{ label = 'countries';      path = '/admin/countries' }
    )) {
        Check $probe.label 200 (Call GET $probe.path $t).code
    }

    "`n== $role : configuration reads (admin only) =="
    foreach ($probe in @(
        @{ label = 'features';         path = '/admin/features' },
        @{ label = 'plan pricing';     path = '/admin/plans/simple/countries' },
        @{ label = 'email templates';  path = '/admin/email/templates' },
        @{ label = 'email config';     path = '/admin/email/config' },
        @{ label = 'gateways';         path = '/admin/gateways' }
    )) {
        $want = if ($e.admin) { 200 } else { 403 }
        Check $probe.label $want (Call GET $probe.path $t).code
    }

    "`n== $role : writes =="

    # Payloads follow the FormRequest rules exactly, otherwise these prove
    # nothing: a 422 from a malformed body looks identical to a role rejection
    # unless the body is valid and the role is the only thing left to fail.
    # A real, live feature keyword. The save endpoint validates that every
    # keyword on a plan exists in the active catalogue, so a made-up name here
    # returns 422 and the test would read as a role failure when it is really a
    # payload failure. `probe` used to sit here and passed only because the
    # endpoint accepted any string - which is how `calls` ended up on a plan.
    $pricing = @{ plan = 'simple'; country = 'IN'; currency = 'INR'; currency_symbol = 'Rs'; price_month_paisa = 999; features = @('chat_gif') }
    $gw = @{ name = 'Probe Gateway'; key = 'k'; merchant_id = 'm'; secret = 's'; currency = 'INR'; enabled = $false }
    $em = @{ title = "probe_${role}_$RunTag"; subject = 'Probe'; html_body = '<p>probe</p>'; enabled = $false }

    # Two different gates, deliberately not one. Configuration writes sit behind
    # `admin` as well as `operations`, so a support agent - which is an operator
    # and can work every queue - still cannot set prices or register a payment
    # gateway. The other writes are `operations` only.
    $r = Call POST '/admin/plans/pricing' $t $pricing
    if ($e.admin) { Check 'pricing write' 200 $r.code } else { Check 'pricing write' 403 $r.code }
    $r = Call POST '/admin/gateways' $t $gw
    if ($e.admin) { Check 'gateway write' 200 $r.code } else { Check 'gateway write' 403 $r.code }
    $r = Call POST '/admin/email/templates' $t $em
    if ($e.admin) { Check 'email write' 201 $r.code } else { Check 'email write' 403 $r.code }
    # id 999999 -> not found proves auth passed and the gate opened, so the role
    # is genuinely what decided the 404 rather than the row being absent.
    $r = Call PATCH '/admin/reports/999999' $t @{ status = 'dismissed'; resolution = 'x' }
    if ($e.operate) { Check 'report write' 404 $r.code } else { Check 'report write' 403 $r.code }
    $r = Call POST '/admin/subscriptions/999999/approve' $t $null
    if ($e.operate) { Check 'sub approve' 404 $r.code } else { Check 'sub approve' 403 $r.code }
    $r = Call POST '/admin/support/tickets/999999/reply' $t @{ reply = 'x' }
    if ($e.operate) { Check 'ticket reply' 404 $r.code } else { Check 'ticket reply' 403 $r.code }
    # appeal.handler is its own axis: the support desk owns appeals, so an admin
    # with every other write right is still refused here.
    $r = Call POST '/admin/appeals/999999/resolve' $t @{ action = 'approve'; resolution = 'x' }
    if ($e.appeal) { Check 'appeal write' 404 $r.code } else { Check 'appeal write' 403 $r.code }

    "`n== $role : team management =="
    $r = Call GET '/admin/staff' $t
    if ($e.admin) { Check 'staff read' 200 $r.code } else { Check 'staff read' 403 $r.code }

    # Unique username per role: reusing one name makes a later request return
    # 409 "already taken", which would mask the role check we are actually after.
    $probe = "probe_${role}_$RunTag"
    $r = Call POST '/admin/staff' $t @{ username = $probe; display_name = 'Probe'; password = 'probepass123'; password_confirmation = 'probepass123'; role = 'support' }
    if ($e.admin) { Check 'staff create support' 201 $r.code } else { Check 'staff create support' 403 $r.code }
    $r = Call POST '/admin/staff' $t @{ username = "${probe}_adm"; display_name = 'Probe'; password = 'probepass123'; password_confirmation = 'probepass123'; role = 'admin' }
    if ($role -eq 'super_admin') { Check 'staff create admin' 201 $r.code } else { Check 'staff create admin' 403 $r.code }
}

# The run creates staff accounts, so it tidies up after itself.
"`n== cleanup =="
if ($sessions.ContainsKey('super_admin')) {
    $su = $sessions['super_admin']
    $roster = Call GET '/admin/staff?limit=200' $su
    $probes = @()
    try {
        $probes = $roster.body | ConvertFrom-Json | ForEach-Object { $_.data.staff } |
            Where-Object { $_.username -like "probe_*_$RunTag*" }
    } catch {
        "  (could not parse roster for cleanup: HTTP $($roster.code))"
    }

    foreach ($p in $probes) {
        $d = Call DELETE "/admin/staff/$($p.id)" $su
        # An admin-role probe is refused on purpose: the controller protects
        # admin and super_admin rows from deletion. Asserting that refusal as a
        # pass is the honest outcome - the alternative is a run that reports a
        # failure every time for behaviour that is working exactly as designed.
        $want = if ($p.role -in @('admin', 'super_admin')) { 422 } else { 200 }
        Check "remove probe $($p.username) [$($p.role)]" $want $d.code
    }
    if ($probes.Count -eq 0) { "  no probe staff to remove" }

    # A refused admin probe stays in the roster, so the run reports the residue
    # rather than working around it. A real admin account therefore has no
    # offboarding path in the console: worth a product decision, but not this
    # script's to make.
    $sticky = @($probes | Where-Object { $_.role -in @('admin', 'super_admin') })
    if ($sticky.Count -gt 0) {
        ""
        "  NOTE: $($sticky.Count) admin-role probe(s) survive by design and need a direct"
        "        database delete to clear: $($sticky.username -join ', ')"
        "  NOTE: a real admin account has no offboarding path in the console - a"
        "        product decision, not a test failure."
    }
}

"`n==== $pass passed, $fail failed ===="
if ($fail -gt 0) { exit 1 }
