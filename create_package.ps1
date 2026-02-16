# Deploy Package Script
$packageDir = "deploy_package"

# 1. Clean previous
if (Test-Path $packageDir) { Remove-Item -Recurse -Force $packageDir }
New-Item -ItemType Directory -Force -Path $packageDir | Out-Null
New-Item -ItemType Directory -Force -Path "$packageDir/api" | Out-Null

# 2. Copy Frontend
Write-Host "Copying Frontend..."
Copy-Item -Recurse "client/dist/*" "$packageDir/"

# 3. Copy Backend
Write-Host "Copying Backend..."
Copy-Item "api/*.php" "$packageDir/api/"
Copy-Item "api/composer.json" "$packageDir/api/"
Copy-Item "api/composer.lock" "$packageDir/api/"

# 4. Copy Services & Vendor
Write-Host "Copying Services & Vendor..."
Copy-Item -Recurse "api/services" "$packageDir/api/"
Copy-Item -Recurse "api/vendor" "$packageDir/api/"

# 5. Copy Reports (Empty Structure)
New-Item -ItemType Directory -Force -Path "$packageDir/api/reports" | Out-Null
# Copy mock.pdf if exists, or create placeholder
if (Test-Path "api/reports/mock.pdf") {
    Copy-Item "api/reports/mock.pdf" "$packageDir/api/reports/"
}

# 6. Copy Config
if (Test-Path ".htaccess") {
    Copy-Item ".htaccess" "$packageDir/"
}

Write-Host "Deployment Package Ready at ./$packageDir"
Write-Host "You can zip this folder and upload it to public_html"
