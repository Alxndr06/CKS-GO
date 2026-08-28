param(
    [string]$PhpPath = 'C:\xampp\php\php.exe',
    [string]$Database = 'cksgo_test_suite',
    [string]$DbHost = '127.0.0.1',
    [string]$DbUser = 'root',
    [string]$DbPassword = '',
    [int]$Port = 8765,
    [switch]$KeepDatabase
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$serverProcess = $null
$databasePrepared = $false
$stdoutLog = [System.IO.Path]::GetTempFileName()
$stderrLog = [System.IO.Path]::GetTempFileName()
$baseUrl = "http://127.0.0.1:$Port"

if (-not (Test-Path -LiteralPath $PhpPath -PathType Leaf)) {
    throw "Executable PHP introuvable : $PhpPath"
}

if ($Database -notmatch '^cksgo_test_[a-z0-9_]{1,40}$') {
    throw 'Le nom de la base jetable doit respecter le format cksgo_test_*.'
}

$env:CKSGO_IGNORE_LOCAL_CONFIG = '1'
$env:CKSGO_DB_HOST = $DbHost
$env:CKSGO_DB_USER = $DbUser
$env:CKSGO_DB_PASS = $DbPassword
$env:CKSGO_DB_NAME = 'cksgo_runtime_guard'
$env:CKSGO_TEST_DB = $Database
$env:CKSGO_APP_ENV = 'test'
$env:CKSGO_APP_DEBUG = '0'
$env:CKSGO_MAIL_ENABLED = '0'

try {
    Push-Location $root

    & $PhpPath 'tests/bootstrap_test_database.php' '--reset'
    if ($LASTEXITCODE -ne 0) { throw 'La creation de la base de test a echoue.' }
    $databasePrepared = $true

    $phpTests = @(
        'tests/StaticSecurityAuditTest.php',
        'tests/SecurityWorkflowTest.php',
        'tests/AccessControlWorkflowTest.php',
        'tests/InventoryWorkflowTest.php',
        'tests/FinancialWorkflowTest.php',
        'tests/CommunicationWorkflowTest.php',
        'tests/AlertRefundWorkflowTest.php',
        'tests/prepare_browser_fixtures.php'
    )

    foreach ($test in $phpTests) {
        & $PhpPath $test
        if ($LASTEXITCODE -ne 0) {
            throw "Echec du test : $test"
        }
    }

    $env:CKSGO_DB_NAME = $Database
    $serverProcess = Start-Process `
        -FilePath $PhpPath `
        -ArgumentList @('-S', "127.0.0.1:$Port", 'tests/dev_router.php') `
        -WorkingDirectory $root `
        -WindowStyle Hidden `
        -RedirectStandardOutput $stdoutLog `
        -RedirectStandardError $stderrLog `
        -PassThru

    $ready = $false
    foreach ($attempt in 1..30) {
        try {
            $response = Invoke-WebRequest -Uri "$baseUrl/index.php" -UseBasicParsing -TimeoutSec 2
            if ([int]$response.StatusCode -eq 200) {
                $ready = $true
                break
            }
        } catch {
            Start-Sleep -Milliseconds 200
        }
    }

    if (-not $ready) {
        throw "Le serveur de test n'a pas demarre.`n$(Get-Content -LiteralPath $stderrLog -Raw)"
    }

    & "$PSScriptRoot/HttpSecurityWorkflowTest.ps1" -BaseUrl $baseUrl
    & "$PSScriptRoot/RoleAccessHttpTest.ps1" -BaseUrl $baseUrl

    Write-Output 'Suite complete CKS-GO : OK'
} finally {
    if ($null -ne $serverProcess -and -not $serverProcess.HasExited) {
        Stop-Process -Id $serverProcess.Id -Force
        $serverProcess.WaitForExit()
    }

    $env:CKSGO_DB_NAME = 'cksgo_runtime_guard'

    if ($databasePrepared -and -not $KeepDatabase -and (Test-Path -LiteralPath $PhpPath -PathType Leaf)) {
        & $PhpPath "$PSScriptRoot/bootstrap_test_database.php" '--drop'
    }

    Pop-Location -ErrorAction SilentlyContinue
    Remove-Item -LiteralPath $stdoutLog, $stderrLog -Force -ErrorAction SilentlyContinue
}
