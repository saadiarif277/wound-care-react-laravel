# Node.js Version Strategy

## Current Configuration

- **Production/LTS Version**: Node.js 22 (LTS)
- **Configured in**: `.nvmrc` file

## Version Policy

### Production Environments
- **Always use LTS (Long Term Support) versions** for production deployments
- Current LTS version: Node.js 22
- LTS versions receive security updates and bug fixes for 30 months
- Provides stability and security required for production applications

### Development Environments
- Use the version specified in `.nvmrc` (Node.js 22)
- This ensures consistency across all development environments
- All team members should use the same Node.js version

### Version Updates
- Monitor Node.js release schedule at https://nodejs.org/en/about/releases/
- Update to new LTS versions after thorough testing
- Non-LTS versions (odd-numbered releases) should NOT be used in production

## Setup Instructions

### Using NVM (Recommended)
```bash
# Install and use the project's Node.js version
nvm install
nvm use

# Or on Windows with nvm-windows
nvm install $(cat .nvmrc)
nvm use $(cat .nvmrc)
```

### Manual Installation
1. Install Node.js 22 from https://nodejs.org/
2. Verify installation: `node --version` should output v22.x.x

## CI/CD Considerations

All CI/CD pipelines should use the Node.js version specified in `.nvmrc`:

```yaml
# Example GitHub Actions configuration
- name: Setup Node.js
  uses: actions/setup-node@v4
  with:
    node-version-file: '.nvmrc'
```

## Troubleshooting

If you encounter issues with Node.js version compatibility:

1. Check your current version: `node --version`
2. Ensure it matches the version in `.nvmrc`
3. If using NVM: `nvm use` to switch to the correct version
4. Clear node_modules and reinstall: `rm -rf node_modules && npm install`

## Security Considerations

- LTS versions receive security patches longer than current releases
- Production environments should never use experimental or current (non-LTS) releases
- Regularly update to latest LTS patch versions for security fixes 
