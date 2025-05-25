// Generate RSA Keys using Node.js
// Run: node generate-rsa-keys.js

const crypto = require('crypto');
const fs = require('fs');

console.log('Generating 2048-bit RSA key pair...');

// Generate RSA key pair
const { publicKey, privateKey } = crypto.generateKeyPairSync('rsa', {
    modulusLength: 2048,
    publicKeyEncoding: {
        type: 'spki',
        format: 'pem'
    },
    privateKeyEncoding: {
        type: 'pkcs1',
        format: 'pem'
    }
});

console.log('\n✓ Keys generated successfully!\n');

// Save keys to files
fs.writeFileSync('ecw-private-key.pem', privateKey);
fs.writeFileSync('ecw-public-key.pem', publicKey);

console.log('Keys saved to:');
console.log('- ecw-private-key.pem');
console.log('- ecw-public-key.pem');

console.log('\n--- PRIVATE KEY ---');
console.log(privateKey);

console.log('\n--- PUBLIC KEY ---');
console.log(publicKey);

console.log('\nNext steps:');
console.log('1. Copy these keys to Azure Key Vault manually, or');
console.log('2. Use the PowerShell script: scripts/upload-keys-to-keyvault.ps1');
console.log('3. Update the script with your vault name and the keys above');
console.log('4. Run: ./upload-keys-to-keyvault.ps1');
