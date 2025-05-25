# Manual Key Vault Upload Script (PowerShell)
# Run this after generating RSA keys manually

# Set your Key Vault name
$vaultName = 'your-vault-name'

# Set the private key (replace with your generated private key)
$privateKey = @'
-----BEGIN RSA PRIVATE KEY-----
(Your private key content here)
-----END RSA PRIVATE KEY-----
'@

# Set the public key (replace with your generated public key)
$publicKey = @'
-----BEGIN PUBLIC KEY-----
(Your public key content here)
-----END PUBLIC KEY-----
'@

# Store in Key Vault using Azure CLI
Write-Host "Uploading private key to Key Vault..."
az keyvault secret set --vault-name $vaultName --name 'ecw-jwk-private-key' --value $privateKey

Write-Host "Uploading public key to Key Vault..."
az keyvault secret set --vault-name $vaultName --name 'ecw-jwk-public-key' --value $publicKey

Write-Host "Keys uploaded to Azure Key Vault successfully!"
Write-Host "Your JWK URL will be: https://yourdomain.com/api/ecw/jwk"
