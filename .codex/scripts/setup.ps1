$ErrorActionPreference = "Stop"

$ScriptPath = $MyInvocation.MyCommand.Path
$ScriptDir = Split-Path -Parent $ScriptPath
$RepoRoot = Resolve-Path (Join-Path $ScriptDir "..\..")

Set-Location $RepoRoot

Write-Host "====================================="
Write-Host "KEY Complex Restaurant - Codex Setup"
Write-Host "Windows Native Environment"
Write-Host "====================================="
Write-Host "Repository root: $RepoRoot"

$HasPhp = $false

Write-Host ""
Write-Host "Checking Git..."
if (Get-Command git -ErrorAction SilentlyContinue) {
    git --version
    Write-Host "Current branch:"
    git branch --show-current
} else {
    Write-Host "Git not found."
}

Write-Host ""
Write-Host "Checking PHP..."
if (Get-Command php -ErrorAction SilentlyContinue) {
    $HasPhp = $true
    php -v
} else {
    Write-Host "PHP not found. Install PHP 8.1+ or make sure it is added to PATH."
}

Write-Host ""
Write-Host "Checking MySQL client..."
if (Get-Command mysql -ErrorAction SilentlyContinue) {
    mysql --version
} else {
    Write-Host "MySQL client not found. This is OK if database is managed through XAMPP/WAMP/DirectAdmin."
}

Write-Host ""
Write-Host "Checking Python..."
if (Get-Command python -ErrorAction SilentlyContinue) {
    python --version
} else {
    Write-Host "Python not found. Optional unless scripts require it."
}

Write-Host ""
Write-Host "Checking Node..."
if (Get-Command node -ErrorAction SilentlyContinue) {
    node --version
} else {
    Write-Host "Node not found. Optional because this project has no Node build."
}

Write-Host ""
Write-Host "Creating local runtime folders if missing..."

$folders = @(
    ".\logs",
    ".\tmp",
    ".\storage",
    ".\storage\backups",
    ".\storage\exports",
    ".\storage\imports"
)

foreach ($folder in $folders) {
    if (!(Test-Path $folder)) {
        New-Item -ItemType Directory -Path $folder -Force | Out-Null
        Write-Host "Created: $folder"
    } else {
        Write-Host "Exists: $folder"
    }
}

Write-Host ""
Write-Host "Checking important project paths..."

$paths = @(
    ".\admin",
    ".\admin\index.php",
    ".\database",
    ".\database\schema.sql",
    ".\.codex\AGENTS.md",
    ".\.codex\RULES.md",
    ".\.codex\SECURITY.md",
    ".\.codex\skills"
)

foreach ($path in $paths) {
    if (Test-Path $path) {
        Write-Host "OK: $path"
    } else {
        Write-Host "Missing: $path"
    }
}

Write-Host ""
Write-Host "Running PHP syntax check for key files..."

if ($HasPhp) {
    $phpFiles = @(
        ".\admin\index.php",
        ".\admin\system-update.php"
    )

    foreach ($file in $phpFiles) {
        if (Test-Path $file) {
            php -l $file
        }
    }
} else {
    Write-Host "PHP syntax check skipped because PHP is not available in PATH."
}

Write-Host ""
Write-Host "Setup completed."