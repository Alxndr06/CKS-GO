param(
    [string]$BaseUrl = 'http://127.0.0.1:8765'
)

$ErrorActionPreference = 'Stop'
$password = 'Audit CKS GO 2026!'
$routes = [ordered]@{
    dashboard = 'index.php?controller=admin&action=dashboard'
    support = 'index.php?controller=admin&action=tickets'
    alerts = 'index.php?controller=admin&action=alerts'
    users = 'index.php?controller=admin&action=showAllUsers'
    approvals = 'index.php?controller=admin&action=pendingUsers'
    shop = 'index.php?controller=shop&action=manageShop'
    orders = 'index.php?controller=admin&action=orders'
    payments = 'index.php?controller=admin&action=payments'
    news = 'index.php?controller=admin&action=news'
    logs = 'index.php?controller=admin&action=logs'
    settings = 'index.php?controller=admin&action=serverSettings'
}

$expected = @{
    user = @()
    assistant = @('dashboard', 'support', 'alerts')
    gestionnaire = @('dashboard', 'support', 'alerts', 'users', 'approvals', 'shop', 'orders', 'payments')
    responsable = @('dashboard', 'support', 'alerts', 'users', 'approvals', 'shop', 'orders', 'payments', 'news', 'logs')
    admin = @($routes.Keys)
}

function Assert-RoleTest([bool]$Condition, [string]$Message) {
    if (-not $Condition) {
        throw $Message
    }
}

foreach ($role in @('user', 'assistant', 'gestionnaire', 'responsable', 'admin')) {
    $session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $loginPage = Invoke-WebRequest -Uri "$BaseUrl/index.php?controller=user&action=login" -WebSession $session -UseBasicParsing
    Assert-RoleTest ($loginPage.Content -match 'name="csrf_token" value="([a-f0-9]{64})"') "Jeton CSRF absent pour $role."

    $loginResponse = Invoke-WebRequest -Uri "$BaseUrl/index.php?controller=user&action=doLogin" -Method POST -WebSession $session -UseBasicParsing -Body @{
        csrf_token = $Matches[1]
        email = "__audit_$role"
        password = $password
    }
    Assert-RoleTest ($loginResponse.Content -match 'Audit') "Connexion impossible pour $role."

    foreach ($entry in $routes.GetEnumerator()) {
        $response = Invoke-WebRequest -Uri "$BaseUrl/$($entry.Value)" -WebSession $session -UseBasicParsing
        $denied = $response.Content -match 'droits n'
        $shouldAllow = $expected[$role] -contains $entry.Key

        if ($shouldAllow) {
            Assert-RoleTest (-not $denied) "Le rôle $role est refusé sur $($entry.Key)."
        } else {
            Assert-RoleTest $denied "Le rôle $role accède indûment à $($entry.Key)."
        }
    }
}

Write-Output 'RoleAccessHttpTest: OK'
