# Build script for Sentifyd Avatar Plugin

$pluginSlug = "sentifyd-avatar"
$version = "1.2.0"
$distDir = Join-Path $PSScriptRoot "dist"
$zipName = "$pluginSlug-v$version.zip"
$zipPath = Join-Path $distDir $zipName

# Ensure dist directory exists
if (-not (Test-Path $distDir)) {
    New-Item -ItemType Directory -Path $distDir | Out-Null
}

# Remove old zip if exists
if (Test-Path $zipPath) {
    Remove-Item $zipPath -Force
}

# Create a temporary directory for staging
$tempDir = Join-Path $distDir "temp_build"
$pluginDir = Join-Path $tempDir $pluginSlug

if (Test-Path $tempDir) {
    Remove-Item $tempDir -Recurse -Force
}
New-Item -ItemType Directory -Path $pluginDir | Out-Null

# Files and folders to include
$includeList = @(
    "sentifyd-avatar.php",
    "uninstall.php",
    "readme.txt",
    "LICENSE",
    "languages"
)

Write-Host "Copying files..." -ForegroundColor Cyan

foreach ($item in $includeList) {
    $sourcePath = Join-Path $PSScriptRoot $item
    if (Test-Path $sourcePath) {
        Copy-Item -Path $sourcePath -Destination $pluginDir -Recurse
    } else {
        Write-Warning "Item not found: $item"
    }
}

# Create Zip
Write-Host "Creating archive: $zipPath" -ForegroundColor Cyan
Compress-Archive -Path "$pluginDir" -DestinationPath $zipPath

# Cleanup
Remove-Item $tempDir -Recurse -Force

Write-Host "Build complete! Package is at: $zipPath" -ForegroundColor Green
