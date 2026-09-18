param(
    [string]$WampPath = "C:\wamp64",
    [string]$Database = "logitix"
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

Write-Host ""
Write-Host "============================================="
Write-Host "       INSTALLATION AUTOMATIQUE LOGITIX"
Write-Host "============================================="
Write-Host ""

$ProjectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $ProjectRoot

function Pass($Message) {
    Write-Host "[PASS] $Message" -ForegroundColor Green
}

function Info($Message) {
    Write-Host "[INFO] $Message" -ForegroundColor Cyan
}

function Fail($Message) {
    Write-Host "[FAIL] $Message" -ForegroundColor Red
    exit 1
}

function New-LogitixPassword {
    $bytes = New-Object byte[] 32
    [System.Security.Cryptography.RandomNumberGenerator]::Fill($bytes)

    return [Convert]::ToBase64String($bytes).
        Replace("+", "-").
        Replace("/", "_").
        TrimEnd("=")
}

# ----------------------------------------------------------
# 1. Detection de WampServer
# ----------------------------------------------------------

if (-not (Test-Path $WampPath)) {
    Fail "WampServer introuvable dans $WampPath"
}

Pass "WampServer detecte : $WampPath"

# ----------------------------------------------------------
# 2. Detection PHP
# LOGITIX utilise PHP 8.4 de preference
# ----------------------------------------------------------

$php = Get-ChildItem "$WampPath\bin\php\php8.4*\php.exe" -ErrorAction SilentlyContinue |
    Sort-Object FullName -Descending |
    Select-Object -First 1

if (-not $php) {
    $php = Get-ChildItem "$WampPath\bin\php\php*\php.exe" -ErrorAction SilentlyContinue |
        Sort-Object FullName -Descending |
        Select-Object -First 1
}

if (-not $php) {
    Fail "php.exe introuvable dans WampServer."
}

$PhpExe = $php.FullName

Pass "PHP detecte : $PhpExe"

# ----------------------------------------------------------
# 3. Detection MySQL
# ----------------------------------------------------------

$mysql = Get-ChildItem "$WampPath\bin\mysql\mysql*\bin\mysql.exe" -ErrorAction SilentlyContinue |
    Sort-Object FullName -Descending |
    Select-Object -First 1

if (-not $mysql) {
    Fail "mysql.exe introuvable."
}

$MysqlExe = $mysql.FullName

Pass "MySQL detecte : $MysqlExe"

# ----------------------------------------------------------
# 4. Mot de passe root MySQL
# ----------------------------------------------------------

Write-Host ""
$RootSecure = Read-Host "Mot de passe ROOT MySQL" -AsSecureString

$Credential = New-Object System.Management.Automation.PSCredential(
    "root",
    $RootSecure
)

$RootPassword = $Credential.GetNetworkCredential().Password

# Utilise temporairement MYSQL_PWD pour eviter de mettre
# le mot de passe root dans l'historique PowerShell.
$env:MYSQL_PWD = $RootPassword

try {

    # ------------------------------------------------------
    # 5. Test root
    # ------------------------------------------------------

    Info "Verification de MySQL..."

    $test = & $MysqlExe -u root -N -B -e "SELECT 1;" 2>&1

    if ($LASTEXITCODE -ne 0) {
        Fail "Connexion root MySQL impossible : $test"
    }

    Pass "Connexion ROOT MySQL valide"

    # ------------------------------------------------------
    # 6. Verification de la base
    # ------------------------------------------------------

    $exists = & $MysqlExe -u root -N -B -e `
        "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME='$Database';"

    if (-not $exists) {

        Info "La base $Database n'existe pas."

        $SqlFile = Join-Path $ProjectRoot "Logitix_SQL_FINAL_COMPLET.sql"

        if (-not (Test-Path $SqlFile)) {
            Fail "Fichier SQL introuvable : $SqlFile"
        }

        Info "Installation de la base LOGITIX..."

        $SqlPath = $SqlFile.Replace("\", "/")

        & $MysqlExe `
            -u root `
            --default-character-set=utf8mb4 `
            -e "SOURCE $SqlPath;"

        if ($LASTEXITCODE -ne 0) {
            Fail "L'import SQL a echoue."
        }

        Pass "Base LOGITIX installee"
    }
    else {
        Pass "Base $Database deja presente"
    }

    # ------------------------------------------------------
    # 7. Generation automatique des mots de passe DB
    # ------------------------------------------------------

    $ClientPassword = New-LogitixPassword
    $AdminPassword  = New-LogitixPassword

    Info "Rotation automatique des comptes MySQL LOGITIX..."

    $AccountsSql = @"
CREATE USER IF NOT EXISTS 'logitix_client'@'127.0.0.1'
IDENTIFIED WITH caching_sha2_password BY '$ClientPassword';

CREATE USER IF NOT EXISTS 'logitix_admin'@'127.0.0.1'
IDENTIFIED WITH caching_sha2_password BY '$AdminPassword';

ALTER USER 'logitix_client'@'127.0.0.1'
IDENTIFIED WITH caching_sha2_password BY '$ClientPassword'
PASSWORD EXPIRE NEVER
ACCOUNT UNLOCK;

ALTER USER 'logitix_admin'@'127.0.0.1'
IDENTIFIED WITH caching_sha2_password BY '$AdminPassword'
PASSWORD EXPIRE NEVER
ACCOUNT UNLOCK;

SET DEFAULT ROLE NONE TO
'logitix_client'@'127.0.0.1',
'logitix_admin'@'127.0.0.1';
"@

    & $MysqlExe -u root -e $AccountsSql

    if ($LASTEXITCODE -ne 0) {
        Fail "Impossible de configurer les comptes LOGITIX."
    }

    Pass "Comptes MySQL LOGITIX configures"

    # ------------------------------------------------------
    # 8. Cle TOTP
    # ------------------------------------------------------

    $SecretDirectory = Join-Path $env:USERPROFILE ".logitix"
    $TotpBackupFile = Join-Path $SecretDirectory "totp.key"
    $EnvFile = Join-Path $ProjectRoot ".env.local"

    if (-not (Test-Path $SecretDirectory)) {
        New-Item -ItemType Directory -Path $SecretDirectory | Out-Null
    }

    $TotpKey = $null

    # Priorite 1 : recuperer la cle d'un .env.local existant
    if (Test-Path $EnvFile) {

        $ExistingEnv = Get-Content $EnvFile

        $TotpLine = $ExistingEnv |
            Where-Object { $_ -like "LOGITIX_TOTP_ENCRYPTION_KEY=*" } |
            Select-Object -First 1

        if ($TotpLine) {
            $Candidate = $TotpLine.Substring(
                "LOGITIX_TOTP_ENCRYPTION_KEY=".Length
            ).Trim()

            try {
                $decoded = [Convert]::FromBase64String($Candidate)

                if ($decoded.Length -eq 32) {
                    $TotpKey = $Candidate
                    Pass "Cle TOTP recuperee depuis .env.local"
                }
            }
            catch {}
        }
    }

    # Priorite 2 : sauvegarde locale hors Git
    if (-not $TotpKey -and (Test-Path $TotpBackupFile)) {

        $Candidate = (Get-Content $TotpBackupFile -Raw).Trim()

        try {
            $decoded = [Convert]::FromBase64String($Candidate)

            if ($decoded.Length -eq 32) {
                $TotpKey = $Candidate
                Pass "Cle TOTP restauree depuis $TotpBackupFile"
            }
        }
        catch {}
    }

    # Priorite 3 : nouvelle cle
    if (-not $TotpKey) {

        $TotpBytes = New-Object byte[] 32
        [System.Security.Cryptography.RandomNumberGenerator]::Fill($TotpBytes)

        $TotpKey = [Convert]::ToBase64String($TotpBytes)

        Pass "Nouvelle cle TOTP generee"
    }

    Set-Content `
        -Path $TotpBackupFile `
        -Value $TotpKey `
        -Encoding ASCII

    # ------------------------------------------------------
    # 9. Creation automatique .env.local
    # ------------------------------------------------------

    $ClientPasswordB64 =
        [Convert]::ToBase64String(
            [Text.Encoding]::UTF8.GetBytes($ClientPassword)
        )

    $AdminPasswordB64 =
        [Convert]::ToBase64String(
            [Text.Encoding]::UTF8.GetBytes($AdminPassword)
        )

    $EnvContent = @"
# Genere automatiquement par installer_logitix.ps1

LOGITIX_APP_ENV=development
LOGITIX_APP_URL=auto
LOGITIX_FORCE_HTTPS=false
LOGITIX_TRUST_PROXY_HTTPS=false
LOGITIX_TRUSTED_PROXY_IPS=

LOGITIX_ADMIN_REQUIRE_2FA=true
LOGITIX_ADMIN_BOOTSTRAP_ENABLED=false

LOGITIX_DB_HOST=127.0.0.1
LOGITIX_DB_PORT=3306
LOGITIX_DB_NAME=$Database

LOGITIX_DB_USER_CLIENT=logitix_client
LOGITIX_DB_PASS_CLIENT_B64=$ClientPasswordB64

LOGITIX_DB_USER_ADMIN=logitix_admin
LOGITIX_DB_PASS_ADMIN_B64=$AdminPasswordB64

LOGITIX_DB_REQUIRE_TLS=false
LOGITIX_DB_SSL_CA=

LOGITIX_TOTP_ENCRYPTION_KEY=$TotpKey
"@

    Set-Content `
        -Path $EnvFile `
        -Value $EnvContent `
        -Encoding UTF8

    Pass ".env.local genere automatiquement"

    # ------------------------------------------------------
    # 10. Diagnostic client
    # ------------------------------------------------------

    Write-Host ""
    Info "Diagnostic authentification CLIENT..."

    & $PhpExe "$ProjectRoot\tools\diagnose_registration.php"

    if ($LASTEXITCODE -ne 0) {
        Fail "Diagnostic client en echec."
    }

    Pass "Authentification CLIENT operationnelle"

    # ------------------------------------------------------
    # 11. Diagnostic admin
    # ------------------------------------------------------

    Write-Host ""
    Info "Diagnostic authentification ADMIN..."

    & $PhpExe "$ProjectRoot\tools\diagnose_admin.php"

    if ($LASTEXITCODE -ne 0) {
        Fail "Diagnostic admin en echec."
    }

    Pass "Authentification ADMIN operationnelle"

    Write-Host ""
    Write-Host "============================================="
    Write-Host "       LOGITIX EST PRET" -ForegroundColor Green
    Write-Host "============================================="
    Write-Host ""
    Write-Host "Projet : $ProjectRoot"
    Write-Host "PHP    : $PhpExe"
    Write-Host "MySQL  : $MysqlExe"
    Write-Host ""
    Write-Host ".env.local a ete cree automatiquement."
    Write-Host "Les mots de passe MySQL n'ont pas ete affiches."
    Write-Host ""
}
finally {

    # ------------------------------------------------------
    # Nettoyage des secrets de la session PowerShell
    # ------------------------------------------------------

    Remove-Item Env:\MYSQL_PWD -ErrorAction SilentlyContinue

    $RootPassword = $null
    $ClientPassword = $null
    $AdminPassword = $null
}