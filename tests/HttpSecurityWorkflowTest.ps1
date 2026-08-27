param(
    [string]$BaseUrl = 'http://127.0.0.1:8765'
)

$ErrorActionPreference = 'Stop'

function Assert-HttpTest([bool]$Condition, [string]$Message) {
    if (-not $Condition) {
        throw $Message
    }
}

function Get-StatusCode([string]$Url, [string]$Method = 'GET') {
    try {
        $response = Invoke-WebRequest -Uri $Url -Method $Method -UseBasicParsing -MaximumRedirection 0
        return [int]$response.StatusCode
    } catch {
        if ($_.Exception.Response -and $_.Exception.Response.StatusCode) {
            return [int]$_.Exception.Response.StatusCode.value__
        }

        throw
    }
}

$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$loginPage = Invoke-WebRequest -Uri "$BaseUrl/index.php?controller=user&action=login" -WebSession $session -UseBasicParsing
Assert-HttpTest ($loginPage.Content -match 'name="csrf_token" value="([a-f0-9]{64})"') 'Le jeton CSRF de connexion est absent.'
$csrfToken = $Matches[1]
$sessionCookieBeforeLogin = ($session.Cookies.GetCookies([uri]($BaseUrl + '/index.php')) | Where-Object Name -eq 'CKSGOSESSID' | Select-Object -First 1).Value

$loginResponse = Invoke-WebRequest -Uri "$BaseUrl/index.php?controller=user&action=doLogin" -Method POST -WebSession $session -UseBasicParsing -Body @{
    csrf_token = $csrfToken
    email = '__audit_user'
    password = 'Audit CKS GO 2026!'
}
Assert-HttpTest ($loginResponse.Content -match 'Audit User') 'La connexion HTTP du compte de test a échoué.'
$sessionCookieAfterLogin = ($session.Cookies.GetCookies([uri]($BaseUrl + '/index.php')) | Where-Object Name -eq 'CKSGOSESSID' | Select-Object -First 1).Value
Assert-HttpTest ($sessionCookieBeforeLogin -and $sessionCookieAfterLogin -and $sessionCookieBeforeLogin -ne $sessionCookieAfterLogin) 'L’identifiant de session n’est pas renouvelé à la connexion.'

$cartPage = Invoke-WebRequest -Uri "$BaseUrl/index.php?controller=shop&action=cart" -WebSession $session -UseBasicParsing
Assert-HttpTest ($cartPage.Content -match 'name="cart_item_id" value="([0-9]+)"') 'Le panier de test ne contient aucun article.'
$cartItemId = [int]$Matches[1]
Assert-HttpTest ($cartPage.Content -match 'name="quantity"[^>]*value="1"') 'La quantité initiale du panier de test est inattendue.'

$csrfFailure = Invoke-WebRequest -Uri "$BaseUrl/index.php?controller=shop&action=updateCartItem" -Method POST -WebSession $session -UseBasicParsing -Body @{
    cart_item_id = $cartItemId
    quantity = 2
}
Assert-HttpTest ($csrfFailure.Content -match 'token CSRF est invalide') 'Une mutation sans jeton CSRF n’est pas bloquée.'

$cartAfterCsrfFailure = Invoke-WebRequest -Uri "$BaseUrl/index.php?controller=shop&action=cart" -WebSession $session -UseBasicParsing
Assert-HttpTest ($cartAfterCsrfFailure.Content -match 'name="quantity"[^>]*value="1"') 'La mutation sans CSRF a modifié le panier.'

$idorResponse = Invoke-WebRequest -Uri "$BaseUrl/index.php?controller=user&action=showOrder&id=137" -WebSession $session -UseBasicParsing
Assert-HttpTest ($idorResponse.Content -match 'Commande introuvable') 'Un utilisateur peut consulter la commande d’un tiers.'

$xssPayload = '<svg/onload=alert(1)>'
$xssResponse = Invoke-WebRequest -Uri ($BaseUrl + '/index.php?controller=shop&action=index&q=' + [uri]::EscapeDataString($xssPayload)) -WebSession $session -UseBasicParsing
Assert-HttpTest (-not $xssResponse.Content.Contains($xssPayload)) 'La recherche reflète une charge XSS non échappée.'
Assert-HttpTest ($xssResponse.Content -match '&lt;svg/onload=alert\(1\)&gt;') 'La charge XSS n’est pas rendue sous forme échappée.'

$oversizedQuery = 'a' * 5000
$oversizedSearchResponse = Invoke-WebRequest -Uri ($BaseUrl + '/index.php?controller=shop&action=index&q=' + $oversizedQuery) -WebSession $session -UseBasicParsing
Assert-HttpTest ([int]$oversizedSearchResponse.StatusCode -eq 200) 'Une recherche volumineuse provoque une erreur serveur.'
Assert-HttpTest ($oversizedSearchResponse.Content -notmatch 'Stack trace|PDOException|Fatal error') 'Une recherche volumineuse divulgue une erreur interne.'

$injectionPayload = "' OR 1=1 --"
$injectionResponse = Invoke-WebRequest -Uri ($BaseUrl + '/index.php?controller=shop&action=index&q=' + [uri]::EscapeDataString($injectionPayload)) -WebSession $session -UseBasicParsing
Assert-HttpTest ([int]$injectionResponse.StatusCode -eq 200) 'Une recherche ressemblant à une injection SQL provoque une erreur.'
Assert-HttpTest ($injectionResponse.Content -notmatch 'PDOException|SQLSTATE|Fatal error') 'Une recherche divulgue des détails SQL.'

Assert-HttpTest ((Get-StatusCode "$BaseUrl/index.php?controller=user&action=render") -eq 404) 'Une méthode héritée du contrôleur est routable.'
Assert-HttpTest ((Get-StatusCode "$BaseUrl/config/local.php") -eq 404) 'La configuration locale est exposée par le serveur de test.'
Assert-HttpTest ((Get-StatusCode "$BaseUrl/tests/SecurityWorkflowTest.php") -eq 404) 'Les tests sont exposés par le serveur de test.'
Assert-HttpTest ((Get-StatusCode "$BaseUrl/index.php" 'TRACE') -eq 405) 'La méthode TRACE n’est pas refusée.'

$headersResponse = Invoke-WebRequest -Uri "$BaseUrl/index.php?controller=home&action=index" -WebSession $session -UseBasicParsing
$csp = [string]$headersResponse.Headers['Content-Security-Policy']
Assert-HttpTest ($csp -match "script-src 'self'" -and $csp -notmatch "unsafe-inline") 'La CSP autorise encore les scripts inline.'
Assert-HttpTest ([string]$headersResponse.Headers['X-Frame-Options'] -eq 'DENY') 'La protection anti-framing est insuffisante.'
Assert-HttpTest ([string]$headersResponse.Headers['Cache-Control'] -match 'no-store') 'Les pages authentifiées peuvent être mises en cache.'
Assert-HttpTest ([string]$headersResponse.Headers['Referrer-Policy'] -eq 'strict-origin-when-cross-origin') 'La politique de référent est absente ou insuffisante.'
Assert-HttpTest ([string]$headersResponse.Headers['Cross-Origin-Opener-Policy'] -eq 'same-origin') 'L’isolation de contexte navigateur est absente.'
Assert-HttpTest ([string]$headersResponse.Headers['Cross-Origin-Resource-Policy'] -eq 'same-origin') 'La politique de ressources cross-origin est absente.'
Assert-HttpTest ([string]$headersResponse.Headers['X-Request-ID'] -match '^[a-f0-9]{32}$') 'L’identifiant de corrélation HTTP est absent.'
$sessionCookieHeader = [string]$loginPage.Headers['Set-Cookie']
Assert-HttpTest ($sessionCookieHeader -match 'CKSGOSESSID=.*HttpOnly' -and $sessionCookieHeader -match 'SameSite=Lax') 'Le cookie de session ne porte pas les attributs HttpOnly et SameSite.'

Write-Output 'HttpSecurityWorkflowTest: OK'
